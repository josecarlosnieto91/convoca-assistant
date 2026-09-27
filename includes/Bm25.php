<?php
/**
 * BM25 ranking.
 *
 * Implementación propia desde la definición estándar de BM25 (Robertson & Zaragoza). Sin dependencias
 * y sin código de terceros: este plugin se publica en WordPress.org.
 *
 *   score(D, Q) = Σ_t  IDF(t) · ( f(t,D) · (k1 + 1) ) / ( f(t,D) + k1 · ( 1 − b + b · |D| / avgdl ) )
 *   IDF(t)      = ln( 1 + ( N − df(t) + 0,5 ) / ( df(t) + 0,5 ) )     ← variante de Lucene, sin negativos
 *
 * Ver docs/buscador-fusion-spec.md.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

defined( 'ABSPATH' ) || exit;

/**
 * Rankea las entradas del índice con BM25.
 */
class Bm25 {

	/**
	 * Peso de cada campo. Los mismos que usa Fuse en el cliente (title 4 · keywords 3 · categories 2 ·
	 * content 1 · tags 1) para que las dos señales del ranking sean comparables.
	 */
	public const FIELD_WEIGHTS = array(
		'title'      => 4.0,
		'keywords'   => 3.0,
		'categories' => 2.0,
		'content'    => 1.0,
		'tags'       => 1.0,
	);

	/**
	 * Estadísticas del corpus, cacheadas por hash del índice mientras dure la petición.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $stats = null;

	/**
	 * Clave de la caché (hash del índice + número de entradas).
	 *
	 * @var string
	 */
	private static string $stats_key = '';

	/**
	 * Rankea el corpus entero contra la consulta.
	 *
	 * @param array<string, mixed> $index_data Índice completo (con `entries`).
	 * @param array<int, string>   $tokens     Términos de la consulta, ya sin stopwords.
	 * @param float                $k1         Saturación de la frecuencia de término.
	 * @param float                $b          Normalización por longitud.
	 * @return array<int, array<string, mixed>> Entradas ordenadas por BM25 descendente.
	 */
	public static function rank( array $index_data, array $tokens, float $k1 = 1.2, float $b = 0.75 ): array {
		/**
		 * Entradas del índice.
		 *
		 * @var array<int, array<string, mixed>> $entries
		 */
		$entries = $index_data['entries'] ?? array();
		if ( empty( $entries ) || empty( $tokens ) ) {
			return array();
		}

		$stats = self::corpus_stats( $index_data );
		$terms = self::query_terms( $tokens );
		if ( empty( $terms ) ) {
			return array();
		}

		$total = (float) $stats['total'];
		$avgdl = $stats['avgdl'] > 0 ? $stats['avgdl'] : 1.0;
		$k1    = $k1 > 0 ? $k1 : 1.2;
		$b     = ( $b >= 0 && $b <= 1 ) ? $b : 0.75;

		/**
		 * Resultados puntuados, en el orden en que se van calculando.
		 *
		 * @var array<int, array<string, mixed>> $scored
		 */
		$scored = array();
		foreach ( $entries as $pos => $entry ) {
			/**
			 * Frecuencias ponderadas de este documento.
			 *
			 * @var array<string, float> $tf
			 */
			$tf    = $stats['tf'][ $pos ] ?? array();
			$dl    = (float) ( $stats['dl'][ $pos ] ?? 0.0 );
			$score = 0.0;

			foreach ( $terms as $term ) {
				$f = (float) ( $tf[ $term ] ?? 0.0 );
				if ( $f <= 0.0 ) {
					continue;
				}
				$df  = (float) ( $stats['df'][ $term ] ?? 0.0 );
				$idf = log( 1.0 + ( ( $total - $df + 0.5 ) / ( $df + 0.5 ) ) );
				$den = $f + $k1 * ( 1.0 - $b + $b * ( $dl / $avgdl ) );
				if ( $den <= 0.0 ) {
					continue;
				}
				$score += $idf * ( ( $f * ( $k1 + 1.0 ) ) / $den );
			}

			if ( $score > 0.0 ) {
				$scored[] = array(
					'entry' => $entry,
					'score' => round( $score, 4 ),
				);
			}
		}

		// usort es estable desde PHP 8.0, así que con puntuaciones iguales se conserva el orden del índice:
		// el ranking es reproducible entre ejecuciones.
		usort(
			$scored,
			static function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		return $scored;
	}

	/**
	 * Términos de la consulta: cada token y su raíz, sin repetir.
	 *
	 * @param array<int, string> $tokens Términos ya normalizados y sin stopwords.
	 * @return array<int, string>
	 */
	private static function query_terms( array $tokens ): array {
		$terms = array();
		foreach ( $tokens as $token ) {
			$token = (string) $token;
			if ( '' === $token ) {
				continue;
			}
			$terms[ $token ] = true;
		}
		return array_keys( $terms );
	}

	/**
	 * Estadísticas del corpus: frecuencias ponderadas por campo, longitudes, df y longitud media.
	 *
	 * @param array<string, mixed> $index_data Índice completo.
	 * @return array<string, mixed>
	 */
	private static function corpus_stats( array $index_data ): array {
		$key = (string) ( $index_data['hash'] ?? '' ) . '|' . count( $index_data['entries'] ?? array() );
		if ( null !== self::$stats && self::$stats_key === $key ) {
			return self::$stats;
		}

		/**
		 * Frecuencias ponderadas de cada documento, por posición.
		 *
		 * @var array<int, array<string, float>> $tf
		 */
		$tf = array();
		/**
		 * Longitud ponderada de cada documento.
		 *
		 * @var array<int, float> $dl
		 */
		$dl = array();
		/**
		 * En cuántos documentos aparece cada término.
		 *
		 * @var array<string, int> $df
		 */
		$df  = array();
		$sum = 0.0;

		foreach ( (array) ( $index_data['entries'] ?? array() ) as $pos => $entry ) {
			/**
			 * Frecuencias del documento que se está procesando.
			 *
			 * @var array<string, float> $freq
			 */
			$freq = array();
			$len  = 0.0;

			foreach ( self::FIELD_WEIGHTS as $field => $weight ) {
				$text = self::field_text( $entry, $field );
				if ( '' === $text ) {
					continue;
				}
				foreach ( self::tokenize( $text ) as $token ) {
					$freq[ $token ] = ( $freq[ $token ] ?? 0.0 ) + $weight;
					$len           += $weight;
				}
			}

			$tf[ $pos ] = $freq;
			$dl[ $pos ] = $len;
			$sum       += $len;

			foreach ( array_keys( $freq ) as $token ) {
				$df[ $token ] = ( $df[ $token ] ?? 0 ) + 1;
			}
		}

		$total = count( $index_data['entries'] ?? array() );

		self::$stats     = array(
			'tf'    => $tf,
			'dl'    => $dl,
			'df'    => $df,
			'total' => $total,
			'avgdl' => $total > 0 ? $sum / $total : 0.0,
		);
		self::$stats_key = $key;

		return self::$stats;
	}

	/**
	 * Texto de un campo de la entrada.
	 *
	 * @param array<string, mixed> $entry Entrada del índice.
	 * @param string               $field Nombre del campo.
	 * @return string
	 */
	private static function field_text( array $entry, string $field ): string {
		$value = $entry[ $field ] ?? '';
		if ( is_array( $value ) ) {
			$value = implode( ' ', array_map( 'strval', $value ) );
		}
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Tokeniza un texto: normaliza (tildes, signos) y parte. Sin raíces: se probó indexando además la raíz
	 * de cada término y las métricas salieron idénticas, así que no compensa el trabajo de indexado.
	 *
	 * @param string $text Texto a tokenizar.
	 * @return array<int, string>
	 */
	private static function tokenize( string $text ): array {
		$normalized = Searcher::normalize_text( $text );
		if ( '' === $normalized ) {
			return array();
		}
		$parts = preg_split( '/\s+/u', $normalized );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$tokens = array();
		foreach ( $parts as $part ) {
			$part = trim( (string) $part );
			if ( strlen( $part ) < 3 ) {
				continue;
			}
			$tokens[] = $part;
		}
		return $tokens;
	}
}

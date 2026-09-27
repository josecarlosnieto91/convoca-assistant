<?php
/**
 * Reranking heurístico del top-N.
 *
 * Reordena las primeras entradas de un ranking ya calculado con señales baratas y deterministas: solape
 * de términos con la consulta, coincidencia de título, cobertura en el título y tipo prioritario. No sale
 * a la red y no depende de ningún proveedor.
 *
 * No mete ni saca entradas: reordena las mismas N y deja el resto del ranking igual detrás, así que no
 * puede perder cobertura por abajo.
 *
 * Ver docs/buscador-rerank-spec.md.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

defined( 'ABSPATH' ) || exit;

/**
 * Reordena el top-N de un ranking.
 */
class Reranker {

	/**
	 * Peso de cada componente dentro de la señal heurística. Suman 1.
	 */
	public const COMPONENT_WEIGHTS = array(
		'titulo'     => 0.35,
		'cobertura'  => 0.30,
		'solape'     => 0.25,
		'prioridad'  => 0.10,
	);

	/**
	 * Profundidad por defecto: cuántas entradas se reordenan.
	 */
	public const DEFAULT_DEPTH = 20;

	/**
	 * Peso por defecto de la señal heurística frente a la puntuación base.
	 */
	public const DEFAULT_WEIGHT = 0.5;

	/**
	 * Reordena el top-N del ranking.
	 *
	 * @param array<int, array<string, mixed>> $results    Ranking base, ya ordenado.
	 * @param string                           $normalized Consulta normalizada.
	 * @param array<int, string>               $tokens     Términos de la consulta, sin stopwords.
	 * @param array<string, mixed>             $settings   Ajustes del plugin.
	 * @param int                              $depth      Cuántas entradas se reordenan.
	 * @param float                            $weight     Cuánto pesa la señal heurística (0-1).
	 * @return array<int, array<string, mixed>>
	 */
	public static function rerank( array $results, string $normalized, array $tokens, array $settings, int $depth = self::DEFAULT_DEPTH, float $weight = self::DEFAULT_WEIGHT ): array {
		if ( count( $results ) < 2 || empty( $tokens ) ) {
			return $results;
		}

		$depth  = $depth > 0 ? $depth : self::DEFAULT_DEPTH;
		$weight = ( $weight >= 0 && $weight <= 1 ) ? $weight : self::DEFAULT_WEIGHT;

		$priority_types = ! empty( $settings['priority_types'] ) ? (array) $settings['priority_types'] : array( 'convoca_faq', 'convoca_kb' );

		$cabeza = array_slice( $results, 0, $depth );
		$cola   = array_slice( $results, $depth );

		foreach ( $cabeza as $i => $item ) {
			$entry = $item['entry'] ?? array();
			$base  = (float) ( $item['score'] ?? 0.0 );
			$heur  = self::heuristic( $entry, $normalized, $tokens, $priority_types );

			$cabeza[ $i ]['score']          = round( ( $base * ( 1 - $weight ) ) + ( $heur * $weight ), 4 );
			$cabeza[ $i ]['score_base']     = $item['score'] ?? null;
			$cabeza[ $i ]['score_rerank']   = round( $heur, 4 );
		}

		// usort es estable desde PHP 8.0: con puntuaciones iguales se conserva el orden del ranking base.
		usort(
			$cabeza,
			static function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		return array_merge( $cabeza, $cola );
	}

	/**
	 * Señal heurística de una entrada: ¿esto responde a la pregunta?
	 *
	 * @param array<string, mixed> $entry          Entrada del índice.
	 * @param string               $normalized     Consulta normalizada.
	 * @param array<int, string>   $tokens         Términos de la consulta.
	 * @param array<int, string>   $priority_types Tipos con prioridad de producto.
	 * @return float 0-1
	 */
	public static function heuristic( array $entry, string $normalized, array $tokens, array $priority_types = array() ): float {
		$titulo = Searcher::normalize_text( (string) ( $entry['title'] ?? '' ) );
		$claves = Searcher::normalize_text( implode( ' ', (array) ( $entry['keywords'] ?? array() ) ) );
		$resto  = Searcher::normalize_text(
			implode(
				' ',
				array_merge(
					(array) ( $entry['categories'] ?? array() ),
					(array) ( $entry['tags'] ?? array() ),
					array( (string) ( $entry['excerpt'] ?? '' ), (string) ( $entry['content'] ?? '' ) )
				)
			)
		);

		// 1) Coincidencia de título: la entrada ES la respuesta.
		$titulo_score = 0.0;
		if ( '' !== $titulo && '' !== $normalized ) {
			if ( $titulo === $normalized ) {
				$titulo_score = 1.0;
			} elseif ( mb_strlen( $normalized ) >= 6 && ( false !== mb_strpos( $titulo, $normalized ) || false !== mb_strpos( $normalized, $titulo ) ) ) {
				$titulo_score = 0.85;
			}
		}

		// 2) Cobertura: cuántos términos distintos de la consulta están en el título.
		$en_titulo = 0;
		foreach ( $tokens as $token ) {
			if ( '' !== $token && false !== mb_strpos( $titulo, (string) $token ) ) {
				$en_titulo++;
			}
		}
		$cobertura = $en_titulo / max( 1, count( $tokens ) );

		// 3) Solape con el resto del contenido (cuerpo, extracto, claves): lo que el compuesto mira poco.
		$en_resto = 0;
		foreach ( $tokens as $token ) {
			if ( '' !== $token && ( false !== mb_strpos( $claves, (string) $token ) || false !== mb_strpos( $resto, (string) $token ) ) ) {
				$en_resto++;
			}
		}
		$solape = $en_resto / max( 1, count( $tokens ) );

		// 4) Tipo prioritario: la política del producto, igual que en el resto del buscador.
		$prioridad = in_array( (string) ( $entry['type'] ?? '' ), $priority_types, true ) ? 1.0 : 0.0;

		$w = self::COMPONENT_WEIGHTS;

		return ( $titulo_score * $w['titulo'] ) + ( $cobertura * $w['cobertura'] ) + ( $solape * $w['solape'] ) + ( $prioridad * $w['prioridad'] );
	}
}

<?php
/**
 * Arnés de calidad del buscador — MOTOR DEL SERVIDOR (Searcher).
 *
 * Mide el respaldo: el motor que atiende sin JavaScript, a los crawlers y a la API REST.
 * Corre SIN WordPress y SIN red: el índice y el grafo salen de los fixtures congelados.
 *
 * No fija umbrales de nota todavía (es la Fase 0: se mide, no se exige); comprueba que el
 * arnés está bien montado y que ninguna consulta queda sin diagnosticar.
 *
 * Spec: docs/buscador-evaluacion-spec.md
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Searcher;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Convoca\Assistant\Searcher
 */
class SearchQualityTest extends TestCase {

	private const FIXTURES       = __DIR__ . '/../fixtures/';
	private const FICHERO_INDICE = self::FIXTURES . 'index-lugg-20260927.json';
	private const FICHERO_GRAFO  = self::FIXTURES . 'graph-lugg-20260927.json';
	private const FICHERO_SET    = self::FIXTURES . 'eval-queries.json';
	private const FICHERO_SALIDA = self::FIXTURES . 'search-quality-server.json';

	/** Configuración REAL de Lugg (convoca_assistant_settings, 27/09/2026). */
	private const AJUSTES = array(
		'search_threshold'     => 0.1,
		'search_max_results'   => 10,
		'priority_types'       => array( 'convoca_faq', 'convoca_kb' ),
		'priority_boost'       => 1.35,
		'weights_fuzzy'        => 0.45,
		'weights_graph'        => 0.10,
		'weights_exact'        => 0.15,
		'weights_exact_title'  => 0.15,
	);

	/** @var array<int,array<string,mixed>> */
	private $consultas = array();

	/** @var array<string,string> Ficheros que este arnés escribe en el directorio del índice. */
	private $escritos = array();

	protected function setUp(): void {
		parent::setUp();

		// El motor lee de CONVOCA_ASSISTANT_INDEX_DIR: se le pone el corpus congelado.
		if ( ! is_dir( CONVOCA_ASSISTANT_INDEX_DIR ) ) {
			mkdir( CONVOCA_ASSISTANT_INDEX_DIR, 0777, true );
		}
		foreach ( array( self::FICHERO_INDICE => 'index.json', self::FICHERO_GRAFO => 'graph.json' ) as $origen => $destino ) {
			$ruta = CONVOCA_ASSISTANT_INDEX_DIR . $destino;
			copy( $origen, $ruta );
			$this->escritos[] = $ruta;
		}

		// La configuración de Lugg, tal cual.
		$GLOBALS['_assistant_options']['convoca_assistant_settings'] = self::AJUSTES;

		$this->consultas = json_decode( (string) file_get_contents( self::FICHERO_SET ), true )['consultas'];
	}

	protected function tearDown(): void {
		foreach ( $this->escritos as $ruta ) {
			if ( file_exists( $ruta ) ) {
				unlink( $ruta );
			}
		}
		unset( $GLOBALS['_assistant_options']['convoca_assistant_settings'] );
		parent::tearDown();
	}

	/**
	 * Ejecuta el set completo y devuelve los ids devueltos por consulta.
	 *
	 * @return array<string,array<int,string>>
	 */
	private function medir( string $engine = 'composite' ): array {
		$GLOBALS['_assistant_options']['convoca_assistant_settings']['search_engine'] = $engine;
		$devueltos = array();
		foreach ( $this->consultas as $c ) {
			$salida = Searcher::search( (string) $c['query'], 10, 0.1 );
			$ids    = array();
			foreach ( $salida as $r ) {
				if ( isset( $r['entry']['id'] ) ) {
					$ids[] = (string) $r['entry']['id'];
				}
			}
			$devueltos[ $c['query'] ] = $ids;
		}
		return $devueltos;
	}

	private static function posicion( array $devueltos, array $esperados ): int {
		foreach ( $devueltos as $i => $id ) {
			if ( in_array( (string) $id, $esperados, true ) ) {
				return $i + 1;
			}
		}
		return 0;
	}

	private static function ndcg5( array $devueltos, array $esperados ): float {
		$pos = self::posicion( array_slice( $devueltos, 0, 5 ), $esperados );
		if ( 0 === $pos ) {
			return 0.0;
		}
		return ( 1 / log( $pos + 1, 2 ) ) / 1.0;
	}

	/**
	 * El motor arranca con el corpus congelado y devuelve algo con la forma esperada.
	 */
	public function test_el_motor_arranca_con_el_corpus_congelado(): void {
		$salida = Searcher::search( 'taller', 10, 0.1 );
		$this->assertNotEmpty( $salida, 'El motor no devuelve nada para una consulta que sí tiene respuesta.' );
		$this->assertArrayHasKey( 'entry', $salida[0], 'La forma devuelta por Searcher cambió.' );
		$this->assertArrayHasKey( 'id', $salida[0]['entry'] );
	}

	/**
	 * Mide el set completo: ninguna consulta se queda sin diagnosticar.
	 */
	public function test_mide_el_set_completo_y_diagnostica_cada_consulta(): void {
		$devueltos = $this->medir();
		$this->assertCount( count( $this->consultas ), $devueltos, 'Hay consultas del set sin medir.' );

		$puntuables = 0;
		$suma1      = 0;
		$suma3      = 0;
		$suma_mrr   = 0.0;
		$suma_ndcg  = 0.0;
		$filas      = array();

		foreach ( $this->consultas as $c ) {
			$ids = $devueltos[ $c['query'] ];
			if ( empty( $c['esperados'] ) ) {
				$filas[] = array(
					'query'     => $c['query'],
					'esperados' => array(),
					'devueltos' => array_slice( $ids, 0, 5 ),
					'posicion'  => null,
					'cobertura' => count( $ids ) > 0,
				);
				continue;
			}
			$puntuables++;
			$pos = self::posicion( $ids, $c['esperados'] );
			if ( 1 === $pos ) {
				$suma1++;
			}
			if ( $pos >= 1 && $pos <= 3 ) {
				$suma3++;
			}
			if ( $pos >= 1 && $pos <= 10 ) {
				$suma_mrr += 1 / $pos;
			}
			$suma_ndcg += self::ndcg5( $ids, $c['esperados'] );
			$filas[]     = array(
				'query'     => $c['query'],
				'esperados' => $c['esperados'],
				'devueltos' => array_slice( $ids, 0, 5 ),
				'posicion'  => $pos,
				'cobertura' => true,
			);
		}

		$this->assertGreaterThan( 0, $puntuables, 'El set no tiene ninguna consulta puntuable.' );
		$this->assertGreaterThanOrEqual( 0, $suma_mrr );
		$this->assertLessThanOrEqual( $puntuables, $suma1 );

		$resumen = array(
			'consultas' => $puntuables,
			'recall1'   => $suma1 / $puntuables,
			'recall3'   => $suma3 / $puntuables,
			'mrr10'     => $suma_mrr / $puntuables,
			'ndcg5'     => $suma_ndcg / $puntuables,
		);

		$ancho = 0;
		foreach ( $filas as $f ) {
			$ancho = max( $ancho, mb_strlen( $f['query'] ) );
		}
		$lineas = array( '', '=== SERVIDOR (Searcher) ===' );
		foreach ( $filas as $f ) {
			if ( empty( $f['esperados'] ) ) {
				$estado = $f['cobertura'] ? 'cobertura: devuelve algo, sin respuesta esperada' : 'cobertura: NO devuelve nada';
			} else {
				$estado = 0 === $f['posicion'] ? 'FALLA' : 'posición ' . $f['posicion'];
			}
			$lineas[] = sprintf(
				'  %s  esperado [%s]  ·  %s  ·  devuelto: %s',
				str_pad( $f['query'], $ancho ),
				implode( ', ', $f['esperados'] ),
				$estado,
				$f['devueltos'] ? implode( ', ', $f['devueltos'] ) : '(nada)'
			);
		}
		$lineas[] = sprintf(
			'  Recall@1 %.1f%% · Recall@3 %.1f%% · MRR@10 %.3f · nDCG@5 %.3f  ·  %d consultas puntuables',
			$resumen['recall1'] * 100,
			$resumen['recall3'] * 100,
			$resumen['mrr10'],
			$resumen['ndcg5'],
			$resumen['consultas']
		);
		fwrite( STDERR, implode( "\n", $lineas ) . "\n" );

		file_put_contents(
			self::FICHERO_SALIDA,
			json_encode(
				array(
					'motor'   => 'servidor',
					'corpus'  => 'index-lugg-20260927.json',
					'ajustes' => self::AJUSTES,
					'resumen' => $resumen,
					'filas'   => $filas,
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			) . "\n"
		);
	}

	/**
	 * Fase 1: mide compuesto, BM25 y fusión sobre el mismo set y comprueba que el compuesto no se movió.
	 *
	 * El motor por defecto tiene que seguir dando EXACTAMENTE la línea base de la Fase 0: si esto falla,
	 * el refactor rompió el comportamiento actual y hay que arreglarlo antes de mirar la fusión.
	 */
	public function test_compara_los_motores_sin_romper_el_compuesto(): void {
		// Línea base de la Fase 0, con la precisión que imprime el arnés. La tolerancia (0,001) es mucho más
		// estrecha que el efecto de mover UNA consulta (~0,03 en recall@1), así que caza cualquier regresión.
		$linea_base = array( 'recall1' => 0.8182, 'recall3' => 0.9091, 'mrr10' => 0.8780, 'ndcg5' => 0.8977 );

		$motores = array();
		foreach ( array( 'composite', 'fusion' ) as $engine ) {
			$motores[ $engine ] = $this->medir( $engine );
		}

		// BM25 solo, como tercera columna: sirve para ver de dónde viene lo que hace la fusión.
		$indice = json_decode( (string) file_get_contents( self::FICHERO_INDICE ), true );
		$bm25   = array();
		foreach ( $this->consultas as $c ) {
			$tokens = array();
			foreach ( explode( ' ', Searcher::normalize_text( (string) $c['query'] ) ) as $t ) {
				if ( strlen( $t ) >= 3 && ! in_array( $t, (array) ( $indice['stop_words'] ?? array() ), true ) ) {
					$tokens[] = $t;
				}
			}
			$ids = array();
			foreach ( \Convoca\Assistant\Bm25::rank( $indice, $tokens ) as $r ) {
				$ids[] = (string) $r['entry']['id'];
			}
			$bm25[ $c['query'] ] = $ids;
		}
		$solo_bm25 = $this->metricas( $bm25 );

		// 1) El compuesto no se ha movido: es la garantía de que el refactor no cambió nada.
		$compuesto = $this->metricas( $motores['composite'] );
		foreach ( $linea_base as $clave => $esperado ) {
			$this->assertEqualsWithDelta(
				$esperado,
				$compuesto[ $clave ],
				0.001,
				"El motor compuesto se ha movido en {$clave}: {$compuesto[$clave]} frente a {$esperado}. El refactor rompió el comportamiento actual."
			);
		}

		$fusion = $this->metricas( $motores['fusion'] );

		// 2) Tabla de los dos motores, consulta a consulta.
		$ancho = 0;
		foreach ( $this->consultas as $c ) {
			$ancho = max( $ancho, mb_strlen( $c['query'] ) );
		}
		$lineas = array( '', '=== FASE 1: compuesto frente a fusión (BM25 + RRF) ===' );
		$mejoran = array();
		$empeoran = array();
		foreach ( $this->consultas as $c ) {
			$q = (string) $c['query'];
			$pc = self::posicion( $motores['composite'][ $q ], $c['esperados'] );
			$pf = self::posicion( $motores['fusion'][ $q ], $c['esperados'] );
			$pb = self::posicion( $bm25[ $q ] ?? array(), $c['esperados'] );
			if ( ! empty( $c['esperados'] ) ) {
				if ( $pf < $pc || ( 0 === $pc && $pf > 0 ) ) {
					$mejoran[] = $q;
				}
				if ( ( $pf > $pc && 0 !== $pf ) || ( 0 === $pf && $pc > 0 ) ) {
					$empeoran[] = $q;
				}
			}
			$fmt = static function ( $p ) {
				return null === $p ? '—' : ( 0 === $p ? 'FALLA' : 'p' . $p );
			};
			$lineas[] = sprintf(
				'  %s  compuesto %-6s bm25 %-6s fusión %-6s %s',
				str_pad( $q, $ancho ),
				$fmt( $pc ),
				$fmt( $pb ),
				$fmt( $pf ),
				( $pf < $pc || ( 0 === $pc && $pf > 0 ) ) ? '<-- MEJORA' : ( ( ( $pf > $pc && 0 !== $pf ) || ( 0 === $pf && $pc > 0 ) ) ? '<-- EMPEORA' : '' )
			);
		}
		$lineas[] = sprintf(
			'  BM25 SOLO recall@1 %.1f%% · recall@3 %.1f%% · mrr@10 %.3f · nDCG@5 %.3f',
			$solo_bm25['recall1'] * 100,
			$solo_bm25['recall3'] * 100,
			$solo_bm25['mrr10'],
			$solo_bm25['ndcg5']
		);
		$lineas[] = sprintf(
			'  COMPUESTO recall@1 %.1f%% · recall@3 %.1f%% · mrr@10 %.3f · nDCG@5 %.3f',
			$compuesto['recall1'] * 100,
			$compuesto['recall3'] * 100,
			$compuesto['mrr10'],
			$compuesto['ndcg5']
		);
		$lineas[] = sprintf(
			'  FUSIÓN    recall@1 %.1f%% · recall@3 %.1f%% · mrr@10 %.3f · nDCG@5 %.3f',
			$fusion['recall1'] * 100,
			$fusion['recall3'] * 100,
			$fusion['mrr10'],
			$fusion['ndcg5']
		);
		$lineas[] = '  Mejoran: ' . ( $mejoran ? implode( ', ', $mejoran ) : 'ninguna' );
		$lineas[] = '  Empeoran: ' . ( $empeoran ? implode( ', ', $empeoran ) : 'ninguna' );

		$relativo = $compuesto['recall1'] > 0 ? ( ( $fusion['recall1'] - $compuesto['recall1'] ) / $compuesto['recall1'] ) * 100 : 0.0;
		$cumple   = ( $relativo >= 10.0 ) && empty( $empeoran );
		$lineas[] = sprintf( '  Recall@1 relativo: %+.1f%% (el criterio pide ≥ +10%%) · consultas que empeoran: %d', $relativo, count( $empeoran ) );
		$lineas[] = '  VEREDICTO DE LA FASE 1: ' . ( $cumple ? 'CUMPLE' : 'NO CUMPLE (se documenta y no se despliega)' );
		fwrite( STDERR, implode( "\n", $lineas ) . "\n" );

		file_put_contents(
			self::FIXTURES . 'search-quality-engines.json',
			json_encode(
				array(
					'corpus'    => 'index-lugg-20260927.json',
					'provisional' => 'El set de 34 consultas NO tiene la revisión final de JC: estas métricas son provisionales.',
					'bm25_solo' => $solo_bm25,
					'compuesto' => $compuesto,
					'fusion'    => $fusion,
					'mejoran'   => $mejoran,
					'empeoran'  => $empeoran,
					'relativo_recall1' => $relativo,
					'cumple_criterio'  => $cumple,
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			) . "\n"
		);
	}

	/**
	 * Métricas agregadas de un conjunto de resultados devueltos.
	 *
	 * @param array<string, array<int, string>> $devueltos Ids por consulta.
	 * @return array<string, float|int>
	 */
	private function metricas( array $devueltos ): array {
		$puntuables = 0;
		$suma1      = 0;
		$suma3      = 0;
		$suma_mrr   = 0.0;
		$suma_ndcg  = 0.0;

		foreach ( $this->consultas as $c ) {
			if ( empty( $c['esperados'] ) ) {
				continue;
			}
			$puntuables++;
			$ids = $devueltos[ $c['query'] ] ?? array();
			$pos = self::posicion( $ids, $c['esperados'] );
			if ( 1 === $pos ) {
				$suma1++;
			}
			if ( $pos >= 1 && $pos <= 3 ) {
				$suma3++;
			}
			if ( $pos >= 1 && $pos <= 10 ) {
				$suma_mrr += 1 / $pos;
			}
			$suma_ndcg += self::ndcg5( $ids, $c['esperados'] );
		}

		$n = $puntuables > 0 ? $puntuables : 1;
		return array(
			'consultas' => $puntuables,
			'recall1'   => $suma1 / $n,
			'recall3'   => $suma3 / $n,
			'mrr10'     => $suma_mrr / $n,
			'ndcg5'     => $suma_ndcg / $n,
		);
	}
}

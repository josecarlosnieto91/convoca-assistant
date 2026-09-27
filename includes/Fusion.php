<?php
/**
 * Reciprocal Rank Fusion.
 *
 * Implementación propia desde la definición estándar de RRF (Cormack, Clarke & Buettcher, 2009):
 *
 *   RRF(d) = Σ_r  1 / ( k + rank_r(d) )
 *
 * Trabaja sobre POSICIONES, no sobre puntuaciones: es lo que hace falta cuando los rankings que se
 * fusionan viven en escalas distintas y no son comparables (aquí, compuesto 0-1 y BM25 sin escala fija).
 *
 * Ver docs/buscador-fusion-spec.md.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

defined( 'ABSPATH' ) || exit;

/**
 * Fusiona rankings.
 */
class Fusion {

	/**
	 * Valor de k por defecto. Amortigua cuánto pesa estar en cabeza de un ranking.
	 */
	public const DEFAULT_K = 60;

	/**
	 * Cuántas posiciones de cada ranking entran en la fusión por defecto.
	 */
	public const DEFAULT_DEPTH = 100;

	/**
	 * Fusiona varios rankings por posición.
	 *
	 * @param array<int, array<int, array<string, mixed>>> $rankings Lista de rankings. Cada ranking es una
	 *                                                               lista ordenada de ['entry' => …, 'score' => …].
	 * @param int                                          $k        Constante de amortiguación.
	 * @param int                                          $limit    Número de resultados a devolver.
	 * @param int                                          $depth    Cuántas posiciones de cada ranking entran en la
	 *                                                               fusión. La cola de un ranking largo es ruido: con
	 *                                                               listas completas, casi todo suma por el simple
	 *                                                               hecho de existir, y la señal buena se diluye.
	 * @return array<int, array<string, mixed>> Entradas fusionadas, con la puntuación normalizada 0-1.
	 */
	public static function rrf( array $rankings, int $k = self::DEFAULT_K, int $limit = 10, int $depth = self::DEFAULT_DEPTH ): array {
		$k = $k > 0 ? $k : self::DEFAULT_K;

		$accumulated = array();
		$entries     = array();

		foreach ( $rankings as $ranking ) {
			$rank = 0;
			foreach ( (array) $ranking as $item ) {
				$rank++;
				if ( $rank > $depth ) {
					break;
				}
				$entry = $item['entry'] ?? null;
				if ( ! is_array( $entry ) || ! isset( $entry['id'] ) ) {
					continue;
				}
				$id = (string) $entry['id'];
				if ( ! isset( $accumulated[ $id ] ) ) {
					$accumulated[ $id ] = 0.0;
					$entries[ $id ]     = $entry;
				}
				$accumulated[ $id ] += 1.0 / ( $k + $rank );
			}
		}

		if ( empty( $accumulated ) ) {
			return array();
		}

		arsort( $accumulated );

		// La puntuación se normaliza para que el campo `score` de la API siga en un rango comparable.
		// El orden no cambia: es un factor común.
		$max = max( $accumulated );

		$out = array();
		foreach ( $accumulated as $id => $score ) {
			$out[] = array(
				'entry' => $entries[ $id ],
				'score' => round( $max > 0 ? ( $score / $max ) : 0.0, 4 ),
			);
		}

		return array_slice( $out, 0, max( 1, $limit ) );
	}
}

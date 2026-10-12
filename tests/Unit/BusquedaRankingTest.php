<?php
/**
 * Reglas de ranking del buscador del servidor (issue #5).
 *
 * Cubre, con un indice minimo y sin WordPress, las reglas nuevas del Searcher:
 *  - el peso de una entrada solo refuerza coincidencias fuertes;
 *  - desempate a 0.90: una pagina cede ante una FAQ del mismo tema;
 *  - priority_types acepta page/post al guardar los ajustes.
 *
 * Cada prueba falla si se revierte su cambio (probado en negativo).
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Searcher;
use Convoca\Assistant\Settings;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Convoca\Assistant\Searcher
 */
class BusquedaRankingTest extends TestCase {

	private const FICHERO_INDICE = CONVOCA_ASSISTANT_INDEX_DIR . 'index.json';

	protected function tearDown(): void {
		if ( file_exists( self::FICHERO_INDICE ) ) {
			unlink( self::FICHERO_INDICE );
		}
		if ( file_exists( CONVOCA_ASSISTANT_INDEX_DIR . 'graph.json' ) ) {
			unlink( CONVOCA_ASSISTANT_INDEX_DIR . 'graph.json' );
		}
		parent::tearDown();
	}

	/**
	 * Escribe un indice minimo con las entradas dadas.
	 *
	 * @param array<int, array<string, mixed>> $entries Entradas.
	 * @return void
	 */
	private function escribir_indice( array $entries ): void {
		if ( ! is_dir( CONVOCA_ASSISTANT_INDEX_DIR ) ) {
			mkdir( CONVOCA_ASSISTANT_INDEX_DIR, 0777, true );
		}
		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			self::FICHERO_INDICE,
			(string) json_encode(
				array(
					'entries'    => $entries,
					'stop_words' => array(),
					'synonyms'   => array(),
				),
				JSON_UNESCAPED_UNICODE
			)
		);
	}

	/**
	 * Entrada minima con los campos que usa el Searcher.
	 *
	 * @param array<string, mixed> $extra Campos que se sobreescriben.
	 * @return array<string, mixed>
	 */
	private static function entrada( array $extra ): array {
		return array_merge(
			array(
				'content'    => '',
				'excerpt'    => '',
				'keywords'   => array(),
				'categories' => array(),
				'tags'       => array(),
				'date'       => '',
				'modified'   => '',
				'url'        => '',
				'thumbnail'  => '',
			),
			$extra
		);
	}

	/**
	 * Con la consulta floja (no esta ni en el titulo ni en las palabras clave), dos entradas
	 * identicas salvo el peso obtienen la MISMA puntuacion: el peso no ventaja a nadie.
	 */
	public function test_el_peso_no_desempata_en_coincidencia_floja(): void {
		$this->escribir_indice(
			array(
				self::entrada( array( 'id' => 11, 'type' => 'convoca_faq', 'weight' => 8, 'title' => 'Taller de cocina', 'keywords' => array( 'cocina musical' ) ) ),
				self::entrada( array( 'id' => 12, 'type' => 'convoca_faq', 'weight' => 2, 'title' => 'Taller de cocina', 'keywords' => array( 'cocina musical' ) ) ),
			)
		);

		$resultados = Searcher::search( 'musical cocina', 10, 0.05 );

		$this->assertCount( 2, $resultados );
		$this->assertSame( 11, $resultados[0]['entry']['id'], 'Con coincidencia floja el orden es el de insercion.' );
		$this->assertEqualsWithDelta(
			$resultados[0]['score'],
			$resultados[1]['score'],
			0.0001,
			'El peso 8 no puede dar mas puntuacion que el peso 2 en una coincidencia floja.'
		);
	}

	/**
	 * Con la consulta fuerte (esta en las palabras clave), el peso si refuerza.
	 */
	public function test_el_peso_refuerza_la_coincidencia_fuerte(): void {
		$this->escribir_indice(
			array(
				self::entrada( array( 'id' => 11, 'type' => 'convoca_faq', 'weight' => 8, 'title' => 'Taller de cocina', 'keywords' => array( 'cocina musical' ) ) ),
				self::entrada( array( 'id' => 12, 'type' => 'convoca_faq', 'weight' => 2, 'title' => 'Taller de cocina', 'keywords' => array( 'cocina musical' ) ) ),
			)
		);

		$resultados = Searcher::search( 'cocina musical', 10, 0.05 );

		$this->assertCount( 2, $resultados );
		$this->assertSame( 11, $resultados[0]['entry']['id'], 'Con coincidencia fuerte el peso 8 debe ir por delante.' );
		$this->assertGreaterThan(
			$resultados[1]['score'],
			$resultados[0]['score'],
			'El peso 8 debe puntuar mas que el peso 2 cuando la coincidencia es fuerte.'
		);
	}

	/**
	 * A 0.90, una pagina cede ante una FAQ del mismo tema.
	 */
	public function test_desempate_a_090_la_faq_gana_a_la_pagina(): void {
		$this->escribir_indice(
			array(
				self::entrada( array( 'id' => 10, 'type' => 'page', 'weight' => 1.0, 'title' => 'Manual de uso del Centro Social' ) ),
				self::entrada( array( 'id' => 20, 'type' => 'convoca_faq', 'weight' => 2.0, 'title' => 'Como consultar el manual de uso del centro' ) ),
			)
		);

		$resultados = Searcher::search( 'manual de uso del centro', 10, 0.05 );

		$this->assertCount( 2, $resultados );
		$this->assertSame( 20, $resultados[0]['entry']['id'], 'La FAQ debe ganar el empate a 0.90.' );
		$this->assertSame( 'convoca_faq', $resultados[0]['entry']['type'] );
	}

	/**
	 * priority_types admite page y post al guardar los ajustes.
	 */
	public function test_priority_types_admite_pagina_y_entrada(): void {
		$salida = Settings::sanitize( array( 'priority_types' => array( 'convoca_faq', 'page', 'post' ) ) );

		$this->assertSame( array( 'convoca_faq', 'page', 'post' ), $salida['priority_types'] );
	}
}

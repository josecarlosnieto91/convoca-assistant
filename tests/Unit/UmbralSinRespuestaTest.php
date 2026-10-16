<?php
/**
 * El umbral de «Sin respuesta», en una sola fuente (convoca-assistant#13).
 *
 * El widget —y la búsqueda por REST— descartan por umbral; la pantalla de «Sin respuesta» daba por contestada
 * **cualquier** consulta con una coincidencia, aunque fuera floja. Con el umbral del sitio en 0,15 y una
 * consulta que puntuaba 0,1033, la pantalla la daba por resuelta y el pendiente desaparecía sin resolverse.
 *
 * El umbral que se usa es `search_threshold`, que es **el mismo** con el que la búsqueda decidió que no había
 * respuesta (y por eso quedó registrada como `response_found = 0`): así la pantalla no puede contradecir a su
 * propio registro.
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Searcher;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Convoca\Assistant\Searcher
 */
class UmbralSinRespuestaTest extends TestCase {

	/**
	 * El registro de opciones del arranque, en limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['_assistant_options'] = array();
	}

	/**
	 * El umbral que se usa es el del sitio, no el de fábrica a espaldas de quien lo configuró.
	 */
	public function test_el_umbral_es_el_del_sitio(): void {
		update_option( 'convoca_assistant_settings', array( 'search_threshold' => 0.15 ) );
		$this->assertSame( 0.15, Searcher::umbral_configurado() );

		update_option( 'convoca_assistant_settings', array( 'search_threshold' => 0.30 ) );
		$this->assertSame( 0.30, Searcher::umbral_configurado() );
	}

	/**
	 * Y sin umbral configurado vale el de siempre (0,10), que es el que documenta la búsqueda.
	 */
	public function test_sin_configurar_vale_el_de_fabrica(): void {
		$this->assertSame( 0.10, Searcher::umbral_configurado() );
	}
}

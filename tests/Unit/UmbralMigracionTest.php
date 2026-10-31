<?php
/**
 * El umbral por defecto sube a 0.35 al actualizar (assistant#24).
 *
 * Con 0.10 cualquier coincidencia floja contaba como respuesta: el panel de «sin respuesta» se
 * vaciaba y el widget contestaba vaguedades. La migración sube **solo** el valor viejo; una elección
 * hecha a mano se respeta.
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Installer;
use PHPUnit\Framework\TestCase;

/**
 * Migración del umbral.
 */
final class UmbralMigracionTest extends TestCase {

	/**
	 * Escenario limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['_assistant_options'] = array();
	}

	/**
	 * El valor viejo por defecto sube a 0.35.
	 */
	public function test_el_valor_viejo_sube(): void {
		update_option( 'convoca_assistant_settings', array( 'search_threshold' => 0.1 ) );

		Installer::migrar_umbral();

		$ajustes = get_option( 'convoca_assistant_settings', array() );

		$this->assertSame( 0.35, (float) $ajustes['search_threshold'] );
	}

	/**
	 * Un umbral elegido a mano no se toca.
	 */
	public function test_un_valor_elegido_se_respeta(): void {
		update_option( 'convoca_assistant_settings', array( 'search_threshold' => 0.55 ) );

		Installer::migrar_umbral();

		$ajustes = get_option( 'convoca_assistant_settings', array() );

		$this->assertSame( 0.55, (float) $ajustes['search_threshold'] );
	}

	/**
	 * Sin ajustes guardados no se inventa nada.
	 */
	public function test_sin_ajustes_no_falla(): void {
		Installer::migrar_umbral();

		$this->assertSame( array(), get_option( 'convoca_assistant_settings', array() ) );
	}
}

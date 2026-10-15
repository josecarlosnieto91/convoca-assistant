<?php
/**
 * El limite de peticiones del assistant usa el contador de core, no uno propio (issue convoca-core#16).
 *
 * Aqui habia un contador con `transient` en lectura-modificacion-escritura: dos peticiones simultaneas leen el
 * mismo valor y escriben el mismo incremento, asi que el limite real era mas alto que el anunciado. Core ya lo
 * resolvio con un contador atomico en base. Habia TRES implementaciones de `check_rate_limit()` en el
 * ecosistema (core, assistant, members) que no hacian lo mismo.
 *
 * Se vigilan las dos mitades: que use el de core cuando esta, y que SIGA funcionando sin core (el plugin se
 * puede activar solo).
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use PHPUnit\Framework\TestCase;

class LimiteDePeticionesUnSoloContadorTest extends TestCase {

	private function fuente(): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/REST_Controller.php' );
	}

	public function test_usa_el_contador_de_core(): void {
		$f = $this->fuente();
		$this->assertStringContainsString( 'Convoca\\Core\\Utils::check_rate_limit(', $f, 'el contador atomico lo tiene core' );
		$this->assertStringContainsString( 'assistant_rest', $f, 'con accion propia, para no mezclar su contador con el de otros subsistemas' );
	}

	public function test_conserva_su_respuesta(): void {
		$f = $this->fuente();
		$this->assertStringContainsString( 'rate_limit_exceeded', $f, 'sigue respondiendo igual: WP_Error con 429' );
		$this->assertStringContainsString( "'status' => 429", $f );
	}

	public function test_funciona_sin_core(): void {
		$f = $this->fuente();
		$this->assertStringContainsString( 'get_transient( $key )', $f, 'si core no esta activo, el respaldo tiene que seguir ahi' );
	}
}

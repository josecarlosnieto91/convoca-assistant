<?php
/**
 * La sesión del asistente no viaja en una cookie blanda.
 *
 * `session_start()` sin parámetros usa los valores de php.ini: cookie sin `HttpOnly`, sin `Secure`
 * y sin `SameSite`. El identificador de sesión es una credencial; se endurece antes de abrir.
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Cookie de sesión del asistente.
 */
final class SesionCookieTest extends TestCase {

	/**
	 * La fuente endurece la cookie antes de `session_start()`.
	 */
	public function test_la_cookie_de_sesion_se_endurece(): void {
		$fuente = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Statistics.php' );

		$this->assertStringContainsString( 'session_set_cookie_params(', $fuente );
		$this->assertStringContainsString( "'httponly' => true", $fuente );
		$this->assertStringContainsString( "'secure'", $fuente );
		$this->assertStringContainsString( "'samesite' => 'Lax'", $fuente );

		$params = strpos( $fuente, 'session_set_cookie_params(' );
		$start  = strpos( $fuente, 'session_start();', $params );
		$this->assertNotFalse( $params );
		$this->assertNotFalse( $start, 'los parámetros van antes de abrir la sesión' );
		$this->assertLessThan( $start, $params );
	}
}

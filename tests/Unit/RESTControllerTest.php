<?php
/**
 * Tests unitarios para REST_Controller::get_client_ip.
 *
 * Verifica que por defecto se devuelve REMOTE_ADDR y se ignora X-Forwarded-For,
 * salvo que CONVOCA_ASSISTANT_TRUSTED_PROXY esté definido como verdadero.
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\REST_Controller;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Convoca\Assistant\REST_Controller
 */
class RESTControllerTest extends TestCase {

	/**
	 * Invoca el método privado estático get_client_ip() vía reflexión.
	 */
	private function get_client_ip(): string {
		$method = new \ReflectionMethod( REST_Controller::class, 'get_client_ip' );
		$method->setAccessible( true );
		return $method->invoke( null );
	}

	/**
	 * Sin proxy de confianza: devuelve REMOTE_ADDR e ignora X-Forwarded-For.
	 */
	public function test_get_client_ip_returns_remote_addr_and_ignores_xff(): void {
		$_SERVER['REMOTE_ADDR']          = '203.0.113.7';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 198.51.100.2';

		$this->assertSame( '203.0.113.7', $this->get_client_ip() );
	}

	/**
	 * Con CONVOCA_ASSISTANT_TRUSTED_PROXY verdadero: devuelve el primer IP de X-Forwarded-For.
	 */
	#[RunInSeparateProcess]
	public function test_get_client_ip_trusts_xff_when_trusted_proxy_defined(): void {
		define( 'CONVOCA_ASSISTANT_TRUSTED_PROXY', true );

		$_SERVER['REMOTE_ADDR']          = '203.0.113.7';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 198.51.100.2';

		$this->assertSame( '198.51.100.1', $this->get_client_ip() );
	}
}

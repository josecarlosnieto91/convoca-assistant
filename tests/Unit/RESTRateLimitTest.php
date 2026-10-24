<?php
/**
 * El límite de peticiones de la API del asistente.
 *
 * Es la puerta que evita que alguien machaque el buscador. Lo que se prueba: cuando el contador del núcleo deja pasar,
 * la petición sigue; cuando lo bloquea, la respuesta es un **429** con su mensaje (no un error genérico); y que se le
 * pasa al contador la acción propia del plugin, para no mezclarse con otros subsistemas.
 *
 * El camino de respaldo (core desactivado) no se mide aquí: la clase del núcleo existe en el arnés y no hay forma de
 * hacer `class_exists()` falso a media ejecución. Queda anotado para integración.
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\REST_Controller;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/REST_Controller.php';

/**
 * Límite de peticiones.
 */
final class RESTRateLimitTest extends TestCase {

	/**
	 * Previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo = array(
			'ok'      => $GLOBALS['convoca_as_ratelimit_ok'] ?? null,
			'llamadas' => $GLOBALS['convoca_as_ratelimit_llamadas'] ?? array(),
			'server'  => $_SERVER,
		);

		$GLOBALS['convoca_as_ratelimit_ok']       = true;
		$GLOBALS['convoca_as_ratelimit_llamadas'] = array();
		$_SERVER                                  = array();
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		if ( null === $this->previo['ok'] ) {
			unset( $GLOBALS['convoca_as_ratelimit_ok'] );
		} else {
			$GLOBALS['convoca_as_ratelimit_ok'] = $this->previo['ok'];
		}
		$GLOBALS['convoca_as_ratelimit_llamadas'] = $this->previo['llamadas'];
		$_SERVER                                  = $this->previo['server'];
	}

	/**
	 * Si el contador deja pasar, la petición sigue.
	 */
	public function test_deja_pasar(): void {
		$this->assertTrue( REST_Controller::check_rate_limit() );
	}

	/**
	 * Al contador se le pasa la acción propia del plugin y sus umbrales.
	 */
	public function test_usa_el_contador_del_plugin(): void {
		REST_Controller::check_rate_limit();

		$this->assertCount( 1, $GLOBALS['convoca_as_ratelimit_llamadas'] );
		$llamada = $GLOBALS['convoca_as_ratelimit_llamadas'][0];
		$this->assertSame( 'assistant_rest', $llamada[0], 'acción propia: no se mezcla con otros subsistemas' );
		$this->assertGreaterThan( 0, $llamada[1], 'tiene un máximo' );
		$this->assertGreaterThan( 0, $llamada[2], 'y una ventana' );
	}

	/**
	 * Cuando bloquea, la respuesta es 429 y se explica.
	 */
	public function test_bloquea_con_429(): void {
		$GLOBALS['convoca_as_ratelimit_ok'] = false;

		$resultado = REST_Controller::check_rate_limit();

		$this->assertInstanceOf( \WP_Error::class, $resultado );
		$this->assertSame( 'rate_limit_exceeded', $resultado->get_error_code() );
		$this->assertStringContainsString( 'Demasiadas solicitudes', $resultado->get_error_message() );
		$datos = $resultado->get_error_data();
		$this->assertSame( 429, $datos['status'], 'es un 429, no un 500' );
	}

	/**
	 * La IP del cliente: la de la petición, sin inventarla.
	 */
	public function test_la_ip_del_cliente(): void {
		$metodo = new \ReflectionMethod( REST_Controller::class, 'get_client_ip' );

		$_SERVER['REMOTE_ADDR'] = '198.51.100.7';
		$this->assertSame( '198.51.100.7', $metodo->invoke( null ) );

		$_SERVER['REMOTE_ADDR'] = 'no-es-una-ip';
		$this->assertSame( 'unknown', $metodo->invoke( null ), 'una IP inválida no cuenta' );

		unset( $_SERVER['REMOTE_ADDR'] );
		$this->assertSame( '127.0.0.1', $metodo->invoke( null ), 'sin dato se usa el local, como WordPress' );
	}
}

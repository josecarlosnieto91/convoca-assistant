<?php
/**
 * Las consultas sin respuesta se registran (convoca-assistant#24).
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Statistics;
use PHPUnit\Framework\TestCase;

/**
 * `response_id` es NOT NULL: enviar `null` hacía fallar el INSERT en silencio.
 */
final class RegistroConsultasTest extends TestCase {

	/**
	 * Doble de $wpdb limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['wpdb']->inserts = array();
	}

	/**
	 * Una consulta sin respuesta deja su fila, con 0 en los campos numéricos.
	 */
	public function test_una_consulta_sin_respuesta_se_registra(): void {
		Statistics::log( '¿Cuál es la capital de Mongolia?', null, false, 0.0, false, null );

		$inserts = $GLOBALS['wpdb']->inserts;

		$this->assertCount( 1, $inserts, 'la consulta tiene que llegar a la tabla' );

		$datos = $inserts[0][1];

		$this->assertSame( 0, $datos['response_id'], 'NOT NULL: null rompe el INSERT' );
		$this->assertSame( 0, $datos['response_found'] );
		$this->assertSame( 0, $datos['query_time_ms'] );
		$this->assertSame( '¿Cuál es la capital de Mongolia?', $datos['query'] );
	}

	/**
	 * Una consulta con respuesta guarda su identificador.
	 */
	public function test_una_consulta_con_respuesta_guarda_el_id(): void {
		Statistics::log( '¿Cómo me hago socio?', 42, true, 0.9, false, 12 );

		$datos = $GLOBALS['wpdb']->inserts[0][1];

		$this->assertSame( 42, $datos['response_id'] );
		$this->assertSame( 1, $datos['response_found'] );
		$this->assertSame( 12, $datos['query_time_ms'] );
	}
}

<?php
/**
 * El índice del asistente solo puede contener contenido publicado.
 *
 * `REST_Controller::get_index()` responde con `Access-Control-Allow-Origin: *`, y Sonar lo marca como
 * política CORS permisiva (php:S5122). Es aceptable **mientras** el índice no salga de lo público: si algún
 * provider indexara borradores o contenido privado, ese encabezado convertiría el endpoint en una fuga
 * legible desde cualquier web.
 *
 * Estas pruebas fijan la premisa en vez de fiarse de la revisión de un día.
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use PHPUnit\Framework\TestCase;

class IndiceSoloPublicoTest extends TestCase {

	/**
	 * Ficheros de provider del plugin.
	 *
	 * @return array<int, string>
	 */
	private function providers(): array {
		$base = dirname( __DIR__, 2 ) . '/includes/providers';

		return glob( $base . '/*.php' ) ?: array();
	}

	/**
	 * Hay providers que revisar (si no, la prueba no comprobaría nada).
	 */
	public function test_hay_providers(): void {
		$this->assertNotEmpty( $this->providers(), 'No se encontraron providers: la prueba no estaría comprobando nada.' );
	}

	/**
	 * Ninguna consulta de un provider puede pedir un estado distinto de `publish`.
	 */
	public function test_ningun_provider_saca_contenido_no_publicado(): void {
		foreach ( $this->providers() as $fichero ) {
			$codigo = (string) file_get_contents( $fichero );
			$nombre = basename( $fichero );

			preg_match_all( "/'post_status'\s*=>\s*'([^']+)'/", $codigo, $estados );

			$this->assertNotEmpty(
				$estados[1],
				sprintf( 'El provider %s no declara post_status: no se puede asegurar que el índice sea público.', $nombre )
			);

			foreach ( $estados[1] as $estado ) {
				$this->assertSame(
					'publish',
					$estado,
					sprintf( 'El provider %s consulta con post_status «%s»: el índice dejaría de ser público y el CORS abierto sería una fuga.', $nombre, $estado )
				);
			}
		}
	}

	/**
	 * El encabezado abierto sigue estando, y con el motivo escrito al lado.
	 */
	public function test_el_origen_abierto_esta_justificado_en_el_codigo(): void {
		$codigo = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/REST_Controller.php' );

		$this->assertStringContainsString( "header( 'Access-Control-Allow-Origin', '*' )", $codigo );

		$this->assertStringContainsString(
			'contenido **publicado**',
			$codigo,
			'El CORS abierto tiene que llevar escrito por qué es seguro.'
		);

		$this->assertStringContainsString(
			'post_status=publish',
			$codigo,
			'El comentario debe nombrar la premisa que lo hace seguro.'
		);
	}
}

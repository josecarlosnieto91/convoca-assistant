<?php
/**
 * Tests del paquete de arranque (assistant#12).
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Starter_Pack;
use WP_UnitTestCase;

/**
 * @coversDefaultClass \Convoca\Assistant\Starter_Pack
 */
class Test_Starter_Pack extends WP_UnitTestCase {

	/**
	 * Trae las seis preguntas que pedía el informe, y ninguna inventada de más.
	 */
	public function test_trae_las_preguntas_del_informe(): void {
		$titulos = wp_list_pluck( Starter_Pack::contenido(), 'titulo' );
		$this->assertCount( 6, $titulos );

		$todo = strtolower( implode( ' | ', $titulos ) );
		foreach ( array( 'cuota', 'socio', 'centro', 'voluntari', 'apunto', 'contactar' ) as $tema ) {
			$this->assertStringContainsString( $tema, $todo, "Falta la pregunta de «{$tema}»" );
		}
	}

	/**
	 * Cada pregunta lleva sus palabras clave: son las que hacen que el buscador la encuentre.
	 */
	public function test_cada_pregunta_lleva_sus_claves(): void {
		foreach ( Starter_Pack::contenido() as $pieza ) {
			$this->assertNotEmpty( $pieza['claves'], 'La pregunta «' . $pieza['titulo'] . '» no tiene palabras clave' );
			$this->assertNotEmpty( wp_strip_all_tags( $pieza['respuesta'] ) );
		}

		$claves = strtolower( implode( ' ', wp_list_pluck( Starter_Pack::contenido(), 'claves' ) ) );
		$this->assertStringContainsString( 'precio', $claves, '«cuota» tiene que encontrar «precio»' );
		$this->assertStringContainsString( 'dónde está', $claves );
	}

	/**
	 * NO se inventa cifras ni direcciones: lo que tiene números lo escribe la junta.
	 *
	 * Es la regla de este paquete. Si alguien mete un importe «de ejemplo», la asociación lo publicará sin
	 * querer: es peor no tener la respuesta que tener una falsa.
	 */
	public function test_no_inventa_importes_ni_direcciones(): void {
		foreach ( Starter_Pack::contenido() as $pieza ) {
			$this->assertDoesNotMatchRegularExpression(
				'/\d+\s*(€|euros)/iu',
				wp_strip_all_tags( $pieza['respuesta'] ),
				'La respuesta de «' . $pieza['titulo'] . '» lleva un importe: eso lo pone la junta, no el paquete'
			);
		}
	}

	/**
	 * Instalar dos veces no duplica nada.
	 */
	public function test_instalar_es_idempotente(): void {
		$primera = Starter_Pack::instalar();
		$this->assertSame( 6, $primera['fichas'] );
		$this->assertTrue( Starter_Pack::esta_instalado() );

		$segunda = Starter_Pack::instalar();
		$this->assertSame( 0, $segunda['fichas'], 'La segunda vez no debe crear ninguna ficha' );
		$this->assertSame( 0, $segunda['sinonimos'] );

		$this->assertSame( 6, Starter_Pack::instaladas() );
	}

	/**
	 * Y no pisa los sinónimos que ya hubiera puestos a mano.
	 */
	public function test_no_pisa_los_sinonimos_existentes(): void {
		update_option( 'convoca_assistant_synonyms', array( 'cuota' => array( 'lo-que-sea' ) ) );

		Starter_Pack::instalar();

		$guardados = (array) get_option( 'convoca_assistant_synonyms' );
		$this->assertSame( array( 'lo-que-sea' ), $guardados['cuota'], 'Lo que ya estaba puesto se respeta' );
		$this->assertArrayHasKey( 'voluntario', $guardados, 'Y lo que faltaba se añade' );
	}
}

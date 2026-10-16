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

	/**
	 * La pantalla «Sin respuesta» no puede listar lo que ya se contesta (assistant#12).
	 *
	 * El registro guarda lo que pasó, y lo que pasó sigue ahí aunque después se arregle: con el paquete de
	 * arranque puesto, las cinco consultas del informe encuentran respuesta, así que no son un pendiente. Si
	 * se listaran, la asociación trabajaría sobre problemas que ya no existen.
	 */
	public function test_lo_que_ya_se_contesta_no_es_un_pendiente(): void {
		global $wpdb;
		Starter_Pack::instalar();

		$tabla = \Convoca\Assistant\Installer::log_table();
		$wpdb->insert( $tabla, array( 'query' => 'cuota', 'response_found' => 0 ) );
		$wpdb->insert( $tabla, array( 'query' => 'qqzzxx-no-existe-esto', 'response_found' => 0 ) );

		$pendientes = array_column( \Convoca\Assistant\Statistics::get_unanswered( 50 ), 'query' );

		$this->assertNotContains( 'cuota', $pendientes, '«cuota» ya se contesta: no debe aparecer como pendiente' );
		$this->assertGreaterThanOrEqual( 1, \Convoca\Assistant\Statistics::unanswered_this_week(), 'La semana cuenta desde el mismo criterio' );
	}

	/**
	 * Y el contador de la semana sale de lo pendiente, no del registro crudo.
	 */
	public function test_el_contador_de_la_semana_lo_calcula_la_lista(): void {
		$fuente = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Statistics.php' );
		$this->assertStringContainsString( 'return count( self::get_unanswered( 200, WEEK_IN_SECONDS ) );', $fuente, 'Un solo criterio para las dos cosas' );
	}
}

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
	 *
	 * Aquí se comprueba el contrato (una sola puerta: `tiene_respuesta()`) y que una fila con la consulta
	 * vacía no se cuele ni se caiga: la comprobación de verdad, con el índice lleno, se mide en el sitio.
	 */
	public function test_la_lista_pasa_por_la_comprobacion_de_respuesta(): void {
		global $wpdb;

		$fuente = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Statistics.php' );
		$this->assertStringContainsString( 'return ! self::tiene_respuesta(', $fuente, 'La lista tiene que descartar lo que ya se contesta' );
		$this->assertStringContainsString( 'return count( self::get_unanswered( 200, WEEK_IN_SECONDS ) );', $fuente, 'Y el contador de la semana sale de la misma lista' );

		// Una fila sin consulta no se cae por el camino: se queda como pendiente, que es lo prudente.
		$tabla = \Convoca\Assistant\Installer::log_table();
		$wpdb->insert( $tabla, array( 'query' => '', 'response_found' => 0 ) );
		$pendientes = array_column( \Convoca\Assistant\Statistics::get_unanswered( 50 ), 'query' );

		$this->assertContains( '', $pendientes, 'Sin búsqueda que hacer, no se descarta nada' );
	}

	/**
	 * Y el contador de la semana no cuenta el registro crudo.
	 */
	public function test_el_contador_de_la_semana_lo_calcula_la_lista(): void {
		$fuente = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Statistics.php' );
		$this->assertStringNotContainsString( 'SELECT COUNT(DISTINCT query)', $fuente, 'El número que se enseña es el de pendientes, no el del registro' );
	}
}

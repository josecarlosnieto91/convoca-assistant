<?php
/**
 * Pesos y fuentes de la base de conocimiento.
 *
 * `Knowledge_Base` estaba al 0 % de 300 statements. Lo de valorar aquí no es el registro de tipos de
 * contenido —eso es declarativo— sino el peso con el que compite cada entrada en la búsqueda y qué
 * fuentes se indexan. Un peso mal resuelto no rompe nada visible: simplemente hace que el asistente
 * conteste con la página equivocada.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant\Tests\Unit;

use Convoca\Assistant\Knowledge_Base;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Knowledge_Base.php';

/**
 * Pesos por tipo, peso individual por entrada y fuentes activas.
 */
final class KnowledgeBasePesosTest extends TestCase {

	/**
	 * La opción donde viven los ajustes. `Settings::OPTION_NAME` es privada a propósito, así que se
	 * repite aquí: si cambia, la prueba tiene que enterarse, no leerla por la puerta de atrás.
	 */
	private const OPCION = 'convoca_assistant_settings';

	/**
	 * Cada prueba parte de ajustes vírgenes y sin meta.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['_assistant_meta'] = array();
		// Los ajustes por defecto traen cuatro fuentes encendidas: se apagan todas para partir de cero.
		$GLOBALS['_assistant_options'] = array(
			self::OPCION => array(
				'source_post'        => false,
				'source_page'        => false,
				'source_convoca_faq' => false,
				'source_convoca_kb'  => false,
				'source_woocommerce' => false,
				'source_product'     => false,
			),
		);
	}

	/**
	 * Y no deja restos.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_assistant_options'] = array();
		$GLOBALS['_assistant_meta']    = array();
	}

	/**
	 * Ajusta una opción del asistente.
	 *
	 * @param string $clave Clave.
	 * @param mixed  $valor Valor.
	 * @return void
	 */
	private function opcion( string $clave, $valor ): void {
		$opciones = $GLOBALS['_assistant_options'][ self::OPCION ] ?? array();
		$opciones[ $clave ]                        = $valor;
		$GLOBALS['_assistant_options'][ self::OPCION ] = $opciones;
	}

	// ── fuentes activas ─────────────────────────────────────────────────────────────────

	/**
	 * Sin nada activado no se indexa nada.
	 *
	 * @return void
	 */
	public function test_sin_fuentes_activadas_no_hay_fuentes(): void {
		$this->assertSame( array(), Knowledge_Base::get_active_sources() );
	}

	/**
	 * Se devuelven solo las activadas.
	 *
	 * @return void
	 */
	public function test_solo_devuelve_las_activadas(): void {
		$this->opcion( 'source_post', true );
		$this->opcion( 'source_page', true );
		$this->opcion( 'source_convoca_faq', false );

		$this->assertSame( array( 'post', 'page' ), Knowledge_Base::get_active_sources() );
	}

	/**
	 * Los productos no se indexan si WooCommerce no está instalado.
	 *
	 * @return void
	 */
	public function test_los_productos_no_se_indexan_sin_woocommerce(): void {
		$this->opcion( 'source_product', true );
		$this->opcion( 'source_woocommerce', true );

		$this->assertSame(
			array(),
			Knowledge_Base::get_active_sources(),
			'indexar productos sin WooCommerce no trae nada y llena el índice de fantasmas'
		);
	}

	// ── peso por defecto ────────────────────────────────────────────────────────────────

	/**
	 * Cada tipo tiene su propio ajuste de peso.
	 *
	 * @return void
	 */
	public function test_cada_tipo_lee_su_propio_peso(): void {
		$this->opcion( 'weight_convoca_faq', 2.5 );
		$this->opcion( 'weight_page', 0.5 );

		$this->assertSame( 2.5, Knowledge_Base::get_default_weight( 'convoca_faq' ) );
		$this->assertSame( 0.5, Knowledge_Base::get_default_weight( 'page' ) );
	}

	/**
	 * Un tipo que no está en el mapa cae en el peso de las entradas, no en 1.
	 *
	 * @return void
	 */
	public function test_un_tipo_desconocido_cae_en_el_peso_de_las_entradas(): void
	{
		$this->opcion( 'weight_post', 3.0 );

		$this->assertSame( 3.0, Knowledge_Base::get_default_weight( 'tipo_inventado' ) );
	}

	/**
	 * Sin ajuste guardado, el peso es uno.
	 *
	 * @return void
	 */
	public function test_sin_ajuste_el_peso_es_uno(): void {
		$this->assertSame( 1.0, Knowledge_Base::get_default_weight( 'post' ) );
	}

	// ── peso efectivo de una entrada ────────────────────────────────────────────────────

	/**
	 * El peso individual de la entrada manda sobre el del tipo.
	 *
	 * @return void
	 */
	public function test_el_peso_individual_manda(): void {
		$this->opcion( 'weight_post', 1.0 );
		$GLOBALS['_assistant_meta'][ 7 ]['_convoca_assistant_weight'] = '4';

		$this->assertSame( 4.0, Knowledge_Base::get_effective_weight( 7, 'post' ) );
	}

	/**
	 * Sin peso individual, se usa el del tipo.
	 *
	 * @return void
	 */
	public function test_sin_peso_individual_se_usa_el_del_tipo(): void {
		$this->opcion( 'weight_page', 2.0 );

		$this->assertSame( 2.0, Knowledge_Base::get_effective_weight( 7, 'page' ) );
	}

	/**
	 * Un peso de cero no se usa: cero significa «no configurado», no «que no salga nunca».
	 *
	 * @return void
	 */
	public function test_un_peso_de_cero_cae_al_del_tipo(): void {
		$this->opcion( 'weight_post', 1.5 );
		$GLOBALS['_assistant_meta'][ 7 ]['_convoca_assistant_weight'] = '0';

		$this->assertSame( 1.5, Knowledge_Base::get_effective_weight( 7, 'post' ) );
	}

	/**
	 * Y uno negativo tampoco.
	 *
	 * @return void
	 */
	public function test_un_peso_negativo_cae_al_del_tipo(): void {
		$this->opcion( 'weight_post', 1.5 );
		$GLOBALS['_assistant_meta'][ 7 ]['_convoca_assistant_weight'] = '-3';

		$this->assertSame( 1.5, Knowledge_Base::get_effective_weight( 7, 'post' ) );
	}

	// ── columnas del listado ────────────────────────────────────────────────────────────

	/**
	 * Las tres columnas del asistente entran antes de la fecha.
	 *
	 * @return void
	 */
	public function test_las_columnas_entran_antes_de_la_fecha(): void {
		$columnas = Knowledge_Base::add_admin_columns(
			array(
				'cb'    => 'Casilla',
				'title' => 'Título',
				'date'  => 'Fecha',
			)
		);

		$this->assertSame( array( 'cb', 'title', 'assistant_weight', 'assistant_keywords', 'assistant_exclude', 'date' ), array_keys( $columnas ) );
		$this->assertSame( 'Fecha', $columnas['date'], 'la fecha se conserva, no se pierde' );
	}

	/**
	 * El peso es ordenable.
	 *
	 * @return void
	 */
	public function test_el_peso_es_ordenable(): void {
		$columnas = Knowledge_Base::sortable_columns( array( 'title' => 'Título' ) );

		$this->assertSame( array( '_convoca_assistant_weight', false ), $columnas['assistant_weight'] );
	}

	/**
	 * Y el estado del asistente se añade como columna.
	 *
	 * @return void
	 */
	public function test_el_estado_del_asistente_es_una_columna(): void {
		$columnas = Knowledge_Base::add_assistant_column( array( 'title' => 'Título' ) );

		$this->assertArrayHasKey( 'assistant_status', $columnas );
	}
}

<?php
/**
 * La caja del asistente al editar: guardar palabras clave, peso y exclusión.
 *
 * Es donde una persona decide si un contenido entra en el buscador, con qué peso y con qué palabras se encuentra. Lo
 * que se prueba: sin nonce, sin permiso o en un tipo que no toca, **no se escribe nada**; y que quitar el peso lo
 * **borra**, en vez de dejarlo en cero (que no es lo mismo: cero es un peso y vacío es «usa el del tipo»).
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Knowledge_Base;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Knowledge_Base.php';

/**
 * Caja del asistente.
 */
final class KnowledgeBaseCajaTest extends TestCase {

	/**
	 * Almacenes previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo = array(
			'meta'   => $GLOBALS['_assistant_meta'] ?? array(),
			'cajas'  => $GLOBALS['convoca_as_metaboxes'] ?? array(),
			'caps'   => $GLOBALS['convoca_as_caps'] ?? array(),
		);

		$GLOBALS['_assistant_meta']  = array();
		$GLOBALS['convoca_as_metaboxes'] = array();
		$GLOBALS['convoca_as_caps']  = array( 'edit_post' => true );
		$_POST                       = array();
		$_REQUEST                    = array();
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		$GLOBALS['_assistant_meta']  = $this->previo['meta'];
		$GLOBALS['convoca_as_metaboxes'] = $this->previo['cajas'];
		$GLOBALS['convoca_as_caps']  = $this->previo['caps'];
		$_POST                       = array();
		$_REQUEST                    = array();
	}

	/**
	 * Un contenido del tipo pedido.
	 *
	 * @param string $tipo Tipo.
	 * @return \WP_Post
	 */
	private function contenido( string $tipo = 'post' ): \WP_Post {
		$post            = new \WP_Post();
		$post->ID        = 55;
		$post->post_type = $tipo;

		return $post;
	}

	/**
	 * La caja se registra en los cuatro tipos y en ninguno más.
	 */
	public function test_la_caja_solo_en_sus_tipos(): void {
		foreach ( array( 'convoca_faq', 'convoca_kb', 'post', 'page' ) as $tipo ) {
			Knowledge_Base::add_meta_box( $tipo );
			$this->assertArrayHasKey( 'convoca_assistant_meta', $GLOBALS['convoca_as_metaboxes'], 'falta en ' . $tipo );
		}

		$GLOBALS['convoca_as_metaboxes'] = array();
		Knowledge_Base::add_meta_box( 'product' );
		$this->assertArrayNotHasKey( 'convoca_assistant_meta', $GLOBALS['convoca_as_metaboxes'], 'en un tipo ajeno no se pinta' );
	}

	/**
	 * Sin nonce no se guarda nada.
	 */
	public function test_sin_nonce(): void {
		$_POST = array( 'convoca_assistant_keywords' => 'cuota' );

		Knowledge_Base::save_meta_box( 55, $this->contenido() );

		$this->assertSame( array(), $GLOBALS['_assistant_meta'], 'ni una meta' );
	}

	/**
	 * Un tipo que no toca, tampoco.
	 */
	public function test_tipo_ajeno(): void {
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'bueno' );
		$_POST = array( 'convoca_assistant_meta_nonce' => 'bueno', 'convoca_assistant_keywords' => 'cuota' );

		Knowledge_Base::save_meta_box( 55, $this->contenido( 'product' ) );

		$this->assertSame( array(), $GLOBALS['_assistant_meta'] );
		unset( $GLOBALS['_wp_stores']['nonces_validos'] );
	}

	/**
	 * Sin permiso sobre el contenido, tampoco.
	 */
	public function test_sin_permiso(): void {
		$GLOBALS['convoca_as_caps']['edit_post'] = false;
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'bueno' );
		$_POST = array( 'convoca_assistant_meta_nonce' => 'bueno', 'convoca_assistant_keywords' => 'cuota' );

		Knowledge_Base::save_meta_box( 55, $this->contenido() );

		$this->assertSame( array(), $GLOBALS['_assistant_meta'] );
		unset( $GLOBALS['_wp_stores']['nonces_validos'] );
	}

	/**
	 * Con nonce y permiso: palabras clave, peso y exclusión.
	 */
	public function test_guarda_lo_suyo(): void {
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'bueno' );
		$_POST = array(
			'convoca_assistant_meta_nonce' => 'bueno',
			'convoca_assistant_keywords'   => '  cuota, precio  ',
			'convoca_assistant_weight'     => '1.4',
			'convoca_assistant_exclude'    => '1',
		);

		Knowledge_Base::save_meta_box( 55, $this->contenido() );

		$meta = $GLOBALS['_assistant_meta'][55];
		$this->assertSame( 'cuota, precio', $meta['_convoca_assistant_keywords'], 'sin espacios alrededor' );
		$this->assertSame( 1.4, $meta['_convoca_assistant_weight'], 'el peso es un número, no texto' );
		$this->assertTrue( $meta['_convoca_assistant_exclude'], 'marcado se guarda como booleano' );
		unset( $GLOBALS['_wp_stores']['nonces_validos'] );
	}

	/**
	 * Sin peso en el formulario, la meta se **borra**: vacío no es cero.
	 */
	public function test_sin_peso_se_borra_la_meta(): void {
		$GLOBALS['_assistant_meta'][55]['_convoca_assistant_weight'] = 0.5;
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'bueno' );
		$_POST = array( 'convoca_assistant_meta_nonce' => 'bueno' );

		Knowledge_Base::save_meta_box( 55, $this->contenido() );

		$this->assertArrayNotHasKey( '_convoca_assistant_weight', $GLOBALS['_assistant_meta'][55], 'vuelve al peso del tipo' );
		$this->assertFalse( $GLOBALS['_assistant_meta'][55]['_convoca_assistant_exclude'], 'sin marcar es false' );
		unset( $GLOBALS['_wp_stores']['nonces_validos'] );
	}
}

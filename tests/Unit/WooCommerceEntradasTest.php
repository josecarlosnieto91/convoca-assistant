<?php
/**
 * Las entradas y relaciones del proveedor de WooCommerce.
 *
 * El buscador indexa productos con su precio y su SKU, y los relaciona por categoría y por etiqueta. Lo que se prueba:
 * un producto sin datos de WooCommerce no revienta (entra sin precio), el recuento sale de la consulta, y sin términos
 * no se inventan relaciones.
 *
 * Las clases del producto son las reales; lo que se dobla es el SDK de WooCommerce (`wc_get_product`, `wc_price`).
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Providers\WooCommerce_Provider;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/providers/WooCommerce_Provider.php';

/**
 * Entradas del proveedor.
 */
final class WooCommerceEntradasTest extends TestCase {

	/**
	 * Almacenes previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Proveedor.
	 *
	 * @var WooCommerce_Provider
	 */
	private WooCommerce_Provider $proveedor;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo = array(
			'posts'    => $GLOBALS['convoca_as_posts'] ?? array(),
			'meta'     => $GLOBALS['_assistant_meta'] ?? array(),
			'terms'    => $GLOBALS['convoca_as_terminos'] ?? array(),
			'productos' => $GLOBALS['convoca_as_productos'] ?? array(),
		);

		$GLOBALS['convoca_as_posts']     = array();
		$GLOBALS['_assistant_meta']      = array();
		$GLOBALS['convoca_as_terminos']  = array();
		$GLOBALS['convoca_as_productos'] = array();
		$this->proveedor                 = new WooCommerce_Provider();
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		$GLOBALS['convoca_as_posts']     = $this->previo['posts'];
		$GLOBALS['_assistant_meta']      = $this->previo['meta'];
		$GLOBALS['convoca_as_terminos']  = $this->previo['terms'];
		$GLOBALS['convoca_as_productos'] = $this->previo['productos'];
	}

	/**
	 * Un producto con título y contenido.
	 *
	 * @param int    $id     Identificador.
	 * @param string $titulo Título.
	 * @return void
	 */
	private function producto( int $id, string $titulo ): void {
		$post               = new \WP_Post();
		$post->ID           = $id;
		$post->post_type    = 'product';
		$post->post_status  = 'publish';
		$post->post_title   = $titulo;
		$post->post_content = 'Descripción del producto';
		$post->post_excerpt = '';

		// La consulta devuelve **ids**; `get_post()` los resuelve con el objeto del mismo almacén.
		$GLOBALS['convoca_as_posts']['product'][] = $id;
		$GLOBALS['convoca_as_posts'][ $id ]       = $post;
	}

	/**
	 * Dos productos con datos de WooCommerce salen con precio y SKU.
	 */
	public function test_las_entradas_llevan_precio_y_sku(): void {
		$this->producto( 10, 'Camiseta' );
		$this->producto( 11, 'Taza' );

		$GLOBALS['convoca_as_productos'][10] = new class() {
			/**
			 * Precio del producto.
			 *
			 * @return string
			 */
			public function get_price() {
				return '15.00';
			}

			/**
			 * SKU del producto.
			 *
			 * @return string
			 */
			public function get_sku() {
				return 'CAM-1';
			}
		};

		$entradas = $this->proveedor->get_entries();

		$this->assertCount( 2, $entradas );
		$por_id = array_column( $entradas, null, 'id' );
		$this->assertArrayHasKey( 10, $por_id );
		$this->assertArrayHasKey( 'price', $por_id[10], 'con producto de WC entra el precio' );
		$this->assertArrayHasKey( 'sku', $por_id[10] );
		$this->assertArrayNotHasKey( 'price', $por_id[11], 'sin producto de WC no se inventa precio' );
	}

	/**
	 * El recuento sale de la consulta.
	 */
	public function test_el_recuento(): void {
		$this->producto( 10, 'Uno' );
		$this->producto( 11, 'Dos' );
		$this->producto( 12, 'Tres' );

		$this->assertSame( 3, $this->proveedor->get_entry_count() );
	}

	/**
	 * Sin productos, cero entradas y cero en el recuento.
	 */
	public function test_sin_productos(): void {
		$this->assertSame( array(), $this->proveedor->get_entries() );
		$this->assertSame( 0, $this->proveedor->get_entry_count() );
	}

	/**
	 * Sin términos no hay relaciones: no se inventa parentesco.
	 */
	public function test_sin_terminos_no_hay_relaciones(): void {
		$GLOBALS['convoca_as_terminos'] = array();

		$this->assertSame( array(), $this->proveedor->get_relations( 10 ) );
	}

	/**
	 * Con categoría, los demás productos de la categoría se relacionan con peso 0,3.
	 */
	public function test_relaciones_por_categoria(): void {
		$this->producto( 20, 'Otro' );
		$this->producto( 21, 'Otro más' );
		$GLOBALS['convoca_as_terminos'][10]['product_cat'] = array( 5 );

		$relaciones = $this->proveedor->get_relations( 10 );

		$this->assertNotEmpty( $relaciones );
		foreach ( $relaciones as $relacion ) {
			$this->assertSame( 'same_product_category', $relacion['type'] );
			$this->assertSame( 0.3, $relacion['weight'] );
		}
	}

	/**
	 * Con etiqueta, peso 0,4 y su propio tipo de relación.
	 */
	public function test_relaciones_por_etiqueta(): void {
		$this->producto( 30, 'Con etiqueta' );
		$GLOBALS['convoca_as_terminos'][12]['product_tag'] = array( 7 );

		$relaciones = $this->proveedor->get_relations( 12 );

		$this->assertNotEmpty( $relaciones );
		$this->assertSame( 'same_product_tag', $relaciones[0]['type'] );
		$this->assertSame( 0.4, $relaciones[0]['weight'] );
	}
}

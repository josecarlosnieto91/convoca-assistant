<?php

/**
 * El proveedor de WooCommerce: qué entra en el índice cuando hay tienda.
 *
 * Estaba al 0 % (142 statements). Lo que se prueba es la limpieza del contenido —que el índice no
 * se llene de comentarios de bloque ni de etiquetas—, los términos, las palabras clave, el peso
 * efectivo y que sin WooCommerce el proveedor no esté disponible.
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Providers\WooCommerce_Provider;
use PHPUnit\Framework\TestCase;

class WooCommerceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_assistant_meta'] = [];
        $GLOBALS['convoca_as_terminos'] = [];
        $GLOBALS['convoca_as_miniatura'] = false;
    }

    protected function tearDown(): void
    {
        $GLOBALS['_assistant_meta'] = [];
        $GLOBALS['convoca_as_terminos'] = [];
        $GLOBALS['convoca_as_miniatura'] = false;
    }

    /**
     * Un post con los campos que usa el proveedor.
     *
     * @param array<string, mixed> $campos Campos.
     * @return object
     */
    private function post(array $campos): object
    {
        $post = class_exists('WP_Post') ? new \WP_Post() : new \stdClass();
        foreach ($campos as $clave => $valor) {
            $post->$clave = $valor;
        }

        return $post;
    }

    /**
     * Llama a un método privado del proveedor.
     *
     * @param string $metodo Método.
     * @param array  $args   Argumentos.
     * @return mixed
     */
    private function privado(string $metodo, array $args = [])
    {
        $ref = new \ReflectionMethod(WooCommerce_Provider::class, $metodo);

        return $ref->invokeArgs(new WooCommerce_Provider(), $args);
    }

    // ── identidad del proveedor ─────────────────────────────────────────────────────

    public function test_el_proveedor_se_identifica(): void
    {
        $proveedor = new WooCommerce_Provider();

        $this->assertSame('product', $proveedor->get_id(), 'el id es el del tipo de contenido, no el de la tienda');
        $this->assertSame('source_woocommerce', $proveedor->get_setting_key(), 'la clave de ajustes manda si se indexa o no');
        $this->assertSame(0.8, $proveedor->get_default_weight(), 'un producto pesa menos que una FAQ');
        $this->assertNotSame('', $proveedor->get_name());
        $this->assertNotSame('', $proveedor->get_description());
    }

    public function test_sin_woocommerce_instalado_no_esta_disponible(): void
    {
        $this->assertSame(class_exists('WooCommerce'), (new WooCommerce_Provider())->is_available());
    }

    // ── la limpieza del contenido ──────────────────────────────────────────────────

    public function test_el_contenido_se_limpia_de_bloques_y_etiquetas(): void
    {
        $sucio = '<!-- wp:paragraph --><p>Camiseta <strong>de algodón</strong></p><!-- /wp:paragraph --> [gallery ids="1,2"] &amp; envío gratis';

        $limpio = $this->privado('clean_content', [$sucio]);

        $this->assertStringNotContainsString('wp:', $limpio, 'los comentarios de bloque no son texto');
        $this->assertStringNotContainsString('<strong>', $limpio);
        $this->assertStringNotContainsString('[gallery', $limpio, 'un shortcode en el índice es ruido');
        $this->assertStringContainsString('Camiseta de algodón & envío gratis', $limpio);
    }

    public function test_los_espacios_repetidos_se_juntan(): void
    {
        $this->assertSame('uno dos', $this->privado('clean_content', ["uno\n\n   dos  "]));
    }

    // ── términos y palabras clave ──────────────────────────────────────────────────

    public function test_los_nombres_de_las_categorias_salen_de_sus_terminos(): void
    {
        $GLOBALS['convoca_as_terminos'][5]['product_cat'] = [
            (object) ['name' => 'Ropa'],
            (object) ['name' => 'Camisetas'],
        ];

        $this->assertSame(['Ropa', 'Camisetas'], $this->privado('get_term_names', [5, 'product_cat']));
    }

    public function test_sin_terminos_no_hay_nombres(): void
    {
        $this->assertSame([], $this->privado('get_term_names', [6, 'product_tag']), 'un producto sin etiquetas no inventa etiquetas');
    }

    public function test_las_palabras_clave_se_separan_por_coma_y_se_limpian(): void
    {
        $GLOBALS['_assistant_meta'][7]['_convoca_assistant_keywords'] = 'camiseta, algodón , ,x,  oferta ';

        $this->assertSame(['camiseta', 'algodón', 'oferta'], $this->privado('parse_keywords', [7]), 'las de una letra y las vacías no valen');
    }

    public function test_sin_palabras_clave_no_hay_lista(): void
    {
        $this->assertSame([], $this->privado('parse_keywords', [8]));
    }

    // ── el peso ───────────────────────────────────────────────────────────────────

    public function test_el_peso_individual_manda_sobre_el_del_tipo(): void
    {
        $GLOBALS['_assistant_meta'][9]['_convoca_assistant_weight'] = '1.7';

        $this->assertSame(1.7, $this->privado('get_effective_weight', [9]));
    }

    public function test_sin_peso_propio_el_producto_pesa_lo_de_su_tipo(): void
    {
        $this->assertSame(0.8, $this->privado('get_effective_weight', [10]));
    }

    // ── la entrada del índice ─────────────────────────────────────────────────────

    public function test_una_entrada_lleva_lo_que_el_buscador_necesita(): void
    {
        $post = $this->post([
            'ID' => 20,
            'post_title' => 'Camiseta de la entidad',
            'post_content' => '<!-- wp:paragraph --><p>Camiseta con el logo</p><!-- /wp:paragraph -->',
            'post_excerpt' => 'Con el logo',
            'post_date' => '2026-01-01 10:00:00',
            'post_modified' => '2026-01-02 10:00:00',
        ]);
        $GLOBALS['convoca_as_terminos'][20]['product_cat'] = [(object) ['name' => 'Ropa']];
        $GLOBALS['convoca_as_miniatura'] = 'https://example.test/camiseta.jpg';

        $entrada = $this->privado('build_entry', [$post, 5000]);

        $this->assertSame(20, $entrada['id']);
        $this->assertSame('product', $entrada['type']);
        $this->assertSame('Camiseta de la entidad', $entrada['title']);
        $this->assertSame('Camiseta con el logo', $entrada['content']);
        $this->assertSame('https://example.test/camiseta.jpg', $entrada['thumbnail']);
        $this->assertSame(['Ropa'], $entrada['categories']);
        $this->assertSame(0.8, $entrada['weight']);
        $this->assertSame('2026-01-02 10:00:00', $entrada['modified']);
    }

    public function test_el_contenido_se_recorta_al_limite_del_indice(): void
    {
        $post = $this->post([
            'ID' => 21,
            'post_title' => 'Producto largo',
            'post_content' => str_repeat('palabra ', 100),
            'post_excerpt' => '',
            'post_date' => '2026-01-01 10:00:00',
            'post_modified' => '2026-01-01 10:00:00',
        ]);

        $entrada = $this->privado('build_entry', [$post, 20]);

        $this->assertSame(20, mb_strlen($entrada['content']), 'el índice no puede crecer sin tope');
    }

    public function test_sin_miniatura_la_entrada_queda_sin_imagen(): void
    {
        $post = $this->post([
            'ID' => 22,
            'post_title' => 'Producto',
            'post_content' => 'Texto',
            'post_excerpt' => '',
            'post_date' => '2026-01-01 10:00:00',
            'post_modified' => '2026-01-01 10:00:00',
        ]);

        $this->assertSame('', $this->privado('build_entry', [$post, 5000])['thumbnail']);
    }
}

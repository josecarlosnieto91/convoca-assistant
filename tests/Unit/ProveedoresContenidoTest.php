<?php

/**
 * Los dos proveedores de contenido propio: FAQ y base de conocimiento.
 *
 * Estaban al 2,8 % y 3,2 %. Lo que importa aquí es que el índice **no** se llene de lo excluido
 * (la consulta filtra `_convoca_assistant_exclude`), que cada entrada lleve su categoría real
 * —`convoca_faq_cat`, no la taxonomía genérica— y que las relaciones salgan de la misma taxonomía.
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Providers\FAQ_Provider;
use Convoca\Assistant\Providers\KB_Provider;
use PHPUnit\Framework\TestCase;

class ProveedoresContenidoTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_assistant_meta'] = [];
        $GLOBALS['convoca_as_terminos'] = [];
        $GLOBALS['convoca_as_posts'] = [];
        $GLOBALS['convoca_as_post'] = null;
        $GLOBALS['convoca_as_miniatura'] = false;
    }

    protected function tearDown(): void
    {
        $GLOBALS['_assistant_meta'] = [];
        $GLOBALS['convoca_as_terminos'] = [];
        $GLOBALS['convoca_as_posts'] = [];
        $GLOBALS['convoca_as_post'] = null;
        $GLOBALS['convoca_as_miniatura'] = false;
    }

    /**
     * Un post con los campos que usan los proveedores.
     *
     * @param int                  $id     Identificador.
     * @param array<string, mixed> $campos Campos.
     * @return \WP_Post
     */
    private function post(int $id, array $campos = []): \WP_Post
    {
        $post = new \WP_Post();
        $post->ID = $id;
        $post->post_title = $campos['title'] ?? 'Pregunta ' . $id;
        $post->post_content = $campos['content'] ?? 'Respuesta ' . $id;
        $post->post_excerpt = $campos['excerpt'] ?? '';
        $post->post_status = $campos['status'] ?? 'publish';
        $post->post_type = $campos['type'] ?? 'convoca_faq';
        $post->post_date = '2026-01-01 10:00:00';
        $post->post_modified = '2026-01-01 10:00:00';
        $GLOBALS['convoca_as_posts'][$id] = $post;

        return $post;
    }

    // ── identidad y disponibilidad ─────────────────────────────────────────────────

    public function test_cada_proveedor_se_identifica(): void
    {
        $faq = new FAQ_Provider();
        $kb = new KB_Provider();

        $this->assertSame('convoca_faq', $faq->get_id());
        $this->assertSame('source_convoca_faq', $faq->get_setting_key(), 'la clave de ajustes decide si se indexa');
        $this->assertSame('convoca_kb', $kb->get_id());
        $this->assertSame('source_convoca_kb', $kb->get_setting_key());

        $this->assertGreaterThan($kb->get_default_weight(), $faq->get_default_weight(), 'una FAQ pesa más que un artículo largo');
    }

    public function test_sin_su_tipo_de_contenido_no_esta_disponible(): void
    {
        $this->assertSame(post_type_exists('convoca_faq'), (new FAQ_Provider())->is_available());
        $this->assertSame(post_type_exists('convoca_kb'), (new KB_Provider())->is_available());
    }

    // ── qué entra en el índice ─────────────────────────────────────────────────────

    public function test_la_consulta_de_faq_excluye_lo_marcado(): void
    {
        (new FAQ_Provider())->get_entries();

        $args = \WP_Query::$captured[array_key_last(\WP_Query::$captured)];

        $this->assertSame('convoca_faq', $args['post_type']);
        $this->assertSame('publish', $args['post_status'], 'un borrador no se indexa');
        $this->assertFalse($args['has_password'], 'el contenido con contraseña no entra');

        $claves = array_map(static fn ($c) => is_array($c) ? ($c['key'] ?? '') . '|' . ($c['compare'] ?? '') : $c, $args['meta_query']);
        $this->assertContains('_convoca_assistant_exclude|NOT EXISTS', $claves, 'sin esto, lo excluido vuelve al buscador');
    }

    public function test_las_entradas_se_construyen_para_cada_post(): void
    {
        $uno = $this->post(31, ['title' => '¿Cómo me hago socio?', 'content' => 'Con el formulario']);
        $dos = $this->post(32);
        // Filas de la consulta (ids) y objetos por id: el doble los distingue por la clave.
        $GLOBALS['convoca_as_posts']['convoca_faq'] = [31, 32];
        $GLOBALS['convoca_as_posts'][31] = $uno;
        $GLOBALS['convoca_as_posts'][32] = $dos;
        $GLOBALS['_assistant_meta'][31]['_convoca_assistant_weight'] = '2.5';
        $GLOBALS['convoca_as_terminos'][31]['convoca_faq_cat'] = [(object) ['name' => 'Socios']];

        $entradas = (new FAQ_Provider())->get_entries();

        $this->assertCount(2, $entradas, 'una entrada por FAQ publicada');
        $this->assertSame('¿Cómo me hago socio?', $entradas[0]['title']);
        $this->assertSame(2.5, $entradas[0]['weight'], 'el peso propio de la FAQ manda');
        $this->assertSame('convoca_faq', $entradas[0]['type'], 'el tipo que ve el buscador es el del contenido, no «post»');
        $this->assertSame('Pregunta 32', $entradas[1]['title'], 'la segunda también entra');
    }

    public function test_la_categoria_de_una_faq_sale_de_su_taxonomia(): void
    {
        $post = $this->post(41, ['title' => '¿Dónde estáis?']);
        $GLOBALS['convoca_as_terminos'][41]['convoca_faq_cat'] = [
            (object) ['name' => 'Centro'],
            (object) ['name' => 'Ubicación'],
        ];

        $ref = new \ReflectionMethod(FAQ_Provider::class, 'build_entry');
        $entrada = $ref->invoke(new FAQ_Provider(), $post, 5000);

        $this->assertSame(['Centro', 'Ubicación'], $entrada['categories'], 'la taxonomía genérica está vacía en una FAQ');
    }

    public function test_un_post_sin_terminos_no_inventa_categorias(): void
    {
        $post = $this->post(42);

        $ref = new \ReflectionMethod(FAQ_Provider::class, 'build_entry');
        $entrada = $ref->invoke(new FAQ_Provider(), $post, 5000);

        $this->assertSame([], $entrada['categories']);
    }

    // ── relaciones ────────────────────────────────────────────────────────────────

    public function test_las_relaciones_de_una_faq_usan_su_taxonomia(): void
    {
        $GLOBALS['convoca_as_terminos'][51]['convoca_faq_cat'] = [(object) ['term_id' => 3, 'name' => 'Socios']];

        $relaciones = (new FAQ_Provider())->get_relations(51);

        $this->assertIsArray($relaciones);
        $term = \WP_Query::$captured[array_key_last(\WP_Query::$captured)];
        $this->assertSame('convoca_faq_cat', $term['tax_query'][0]['taxonomy'] ?? '', 'las relaciones de FAQ se tejen por su categoría, no por la del blog');
    }

    public function test_una_faq_sin_categoria_no_tiene_relaciones_por_categoria(): void
    {
        $relaciones = (new FAQ_Provider())->get_relations(52);

        $this->assertSame([], $relaciones, 'sin categoría no hay con qué relacionar');
    }

    public function test_las_relaciones_de_un_articulo_usan_la_suya(): void
    {
        $GLOBALS['convoca_as_terminos'][61]['convoca_kb_cat'] = [(object) ['term_id' => 7, 'name' => 'Estatutos']];

        $relaciones = (new KB_Provider())->get_relations(61);

        $this->assertIsArray($relaciones);
        $consulta = \WP_Query::$captured[array_key_last(\WP_Query::$captured)];
        $this->assertSame('convoca_kb_cat', $consulta['tax_query'][0]['taxonomy'] ?? '');
    }
}

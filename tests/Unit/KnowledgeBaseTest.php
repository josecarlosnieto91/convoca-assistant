<?php

/**
 * La base de conocimiento: tipos de contenido, taxonomías, metadatos, columnas del listado y
 * los pesos que ordenan el buscador.
 *
 * `Knowledge_Base` estaba al 8,9 % (25 de 281). Lo que se comprueba aquí es lo que tiene
 * consecuencias: que los tipos de contenido se registren con sus anclas y su REST, que las
 * columnas del listado conserven la fecha en su sitio, y sobre todo el **peso efectivo** —el
 * individual manda, y si no hay, el del tipo—, que es lo que decide qué sale primero.
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Knowledge_Base;
use PHPUnit\Framework\TestCase;

class KnowledgeBaseTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['convoca_as_tipos'] = [];
        $GLOBALS['convoca_as_taxonomias'] = [];
        $GLOBALS['convoca_as_meta_registrada'] = [];
        $GLOBALS['convoca_as_metaboxes'] = [];
        $GLOBALS['convoca_as_acciones'] = [];
        $GLOBALS['convoca_as_filtros'] = [];
        $GLOBALS['_assistant_meta'] = [];
        $GLOBALS['convoca_as_caps'] = ['edit_posts' => true];
        unset($GLOBALS['convoca_as_salida']);
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['convoca_as_tipos'],
            $GLOBALS['convoca_as_taxonomias'],
            $GLOBALS['convoca_as_meta_registrada'],
            $GLOBALS['convoca_as_metaboxes'],
            $GLOBALS['convoca_as_acciones'],
            $GLOBALS['convoca_as_filtros'],
            $GLOBALS['_assistant_meta'],
            $GLOBALS['convoca_as_caps'],
            $GLOBALS['convoca_as_salida']
        );
    }

    /**
     * Escribe los ajustes que verá el plugin.
     *
     * `Settings::get_all()` lee de la opción y la mezcla con los valores por defecto, así que lo
     * que se escribe aquí manda sobre el defecto y lo que no se escribe se queda como venía.
     *
     * @param array<string, mixed> $valores Ajustes a fijar.
     */
    private function ajustes(array $valores): void
    {
        $GLOBALS['_assistant_options']['convoca_assistant_settings'] = $valores;
    }

    /**
     * Captura lo que imprime una función.
     *
     * @param callable $funcion Función a ejecutar.
     * @return string
     */
    private function salida(callable $funcion): string
    {
        ob_start();
        $funcion();

        return (string) ob_get_clean();
    }

    // ── tipos de contenido ───────────────────────────────────────────────────────────

    public function test_las_faq_se_registran_con_su_ancla_y_su_rest(): void
    {
        Knowledge_Base::register_faq_cpt();

        $tipo = $GLOBALS['convoca_as_tipos']['convoca_faq'];

        $this->assertSame('faq', $tipo['rewrite']['slug'], 'cambiar el ancla rompe los enlaces publicados');
        $this->assertTrue($tipo['show_in_rest'], 'sin REST el editor de bloques no puede guardar');
        $this->assertTrue($tipo['public'], 'una FAQ es contenido público');
        $this->assertContains('editor', $tipo['supports']);
    }

    public function test_los_articulos_de_la_base_se_registran_aparte(): void
    {
        Knowledge_Base::register_kb_cpt();

        $tipo = $GLOBALS['convoca_as_tipos']['convoca_kb'];

        $this->assertSame('knowledge-base', $tipo['rewrite']['slug']);
        $this->assertTrue($tipo['show_in_rest']);
        $this->assertSame('convoca-assistant', $tipo['show_in_menu'], 'van bajo el menú del asistente, no sueltos');
    }

    // ── taxonomías ──────────────────────────────────────────────────────────────────

    public function test_cada_tipo_tiene_su_categoria_jerarquica(): void
    {
        Knowledge_Base::register_taxonomies();

        $taxonomias = $GLOBALS['convoca_as_taxonomias'];

        $this->assertSame('convoca_faq', $taxonomias['convoca_faq_cat']['object_type']);
        $this->assertSame('faq-category', $taxonomias['convoca_faq_cat']['args']['rewrite']['slug']);
        $this->assertTrue($taxonomias['convoca_faq_cat']['args']['hierarchical'], 'sin jerarquía no hay subcategorías');

        $this->assertSame('convoca_kb', $taxonomias['convoca_kb_cat']['object_type']);
        $this->assertSame('kb-category', $taxonomias['convoca_kb_cat']['args']['rewrite']['slug']);
        $this->assertFalse($taxonomias['convoca_kb_cat']['args']['show_in_menu'], 'la categoría se gestiona dentro del tipo, no en su propio menú');
    }

    // ── metadatos ──────────────────────────────────────────────────────────────────

    public function test_los_metadatos_se_registran_en_los_cuatro_tipos(): void
    {
        Knowledge_Base::register_meta();

        $registradas = $GLOBALS['convoca_as_meta_registrada'];

        foreach (['convoca_faq', 'convoca_kb', 'post', 'page'] as $tipo) {
            $this->assertArrayHasKey($tipo, $registradas, 'sin metadato registrado, REST lo descarta');
            $this->assertArrayHasKey('_convoca_assistant_weight', $registradas[$tipo]);
            $this->assertArrayHasKey('_convoca_assistant_keywords', $registradas[$tipo]);
        }

        $this->assertTrue($registradas['post']['_convoca_assistant_exclude']['show_in_rest']);
    }

    // ── columnas del listado ────────────────────────────────────────────────────────

    public function test_las_columnas_del_asistente_van_antes_de_la_fecha(): void
    {
        $columnas = Knowledge_Base::add_admin_columns(['cb' => 'Casilla', 'title' => 'Título', 'date' => 'Fecha']);

        $this->assertArrayHasKey('assistant_weight', $columnas);
        $this->assertArrayHasKey('assistant_keywords', $columnas);
        $this->assertArrayHasKey('assistant_exclude', $columnas);
        $this->assertSame('Fecha', $columnas['date'], 'la fecha no se pierde');
        $this->assertSame('date', array_key_last($columnas), 'y sigue siendo la última columna');
    }

    public function test_un_listado_sin_fecha_no_inventa_una(): void
    {
        $columnas = Knowledge_Base::add_admin_columns(['cb' => 'Casilla', 'title' => 'Título']);

        $this->assertSame('', $columnas['date'], 'se conserva la clave vacía en vez de colocarla al final con texto');
    }

    public function test_la_columna_de_peso_muestra_el_valor(): void
    {
        $GLOBALS['_assistant_meta'][7]['_convoca_assistant_weight'] = '2.5';

        $html = $this->salida(static fn () => Knowledge_Base::render_admin_column('assistant_weight', 7));

        $this->assertStringContainsString('2.5', $html);
    }

    public function test_la_columna_de_exclusion_dice_que_no(): void
    {
        $html = $this->salida(static fn () => Knowledge_Base::render_admin_column('assistant_exclude', 8));

        $this->assertStringContainsString('No', $html, 'una celda vacía se lee como «no lo sé»');
    }

    public function test_una_columna_desconocida_no_pinta_nada(): void
    {
        $html = $this->salida(static fn () => Knowledge_Base::render_admin_column('title', 9));

        $this->assertSame('', $html);
    }

    public function test_las_columnas_ordenables_declaran_su_metadato(): void
    {
        $ordenables = Knowledge_Base::sortable_columns(['title' => 'title']);

        $this->assertArrayHasKey('assistant_weight', $ordenables);
    }

    public function test_los_posts_y_paginas_llevan_su_columna_compacta(): void
    {
        $columnas = Knowledge_Base::add_assistant_column(['title' => 'Título']);
        $this->assertArrayHasKey('assistant_status', $columnas);

        $html = $this->salida(static fn () => Knowledge_Base::render_assistant_column('assistant_status', 12));
        $this->assertNotSame('', $html, 'la columna tiene que decir algo');

        $otra = $this->salida(static fn () => Knowledge_Base::render_assistant_column('title', 12));
        $this->assertSame('', $otra, 'en otra columna no se mete');
    }

    public function test_un_post_excluido_se_ve_excluido_en_el_listado(): void
    {
        $GLOBALS['_assistant_meta'][13]['_convoca_assistant_exclude'] = true;

        $html = $this->salida(static fn () => Knowledge_Base::render_assistant_column('assistant_status', 13));

        $this->assertStringContainsString('Exclu', $html);
    }

    // ── pesos: lo que ordena el buscador ────────────────────────────────────────────

    public function test_solo_indexa_las_fuentes_marcadas_en_ajustes(): void
    {
        $this->ajustes([
            'source_post' => 1,
            'source_page' => 0,
            'source_convoca_faq' => 1,
            'source_convoca_kb' => 0,
            'source_woocommerce' => 0,
        ]);

        $fuentes = Knowledge_Base::get_active_sources();

        $this->assertSame(['post', 'convoca_faq'], $fuentes, 'indexar lo que no se ha pedido mete ruido en las respuestas');
    }

    public function test_woocommerce_sin_woocommerce_instalado_no_es_una_fuente(): void
    {
        $this->ajustes(['source_woocommerce' => 1, 'source_post' => 0, 'source_page' => 0, 'source_convoca_faq' => 0, 'source_convoca_kb' => 0]);

        $this->assertSame([], Knowledge_Base::get_active_sources(), 'sin la tienda instalada no hay productos que indexar');
    }

    public function test_el_peso_por_defecto_sale_de_ajustes(): void
    {
        $this->ajustes(['weight_convoca_faq' => 3]);

        $this->assertSame(3.0, Knowledge_Base::get_default_weight('convoca_faq'));
    }

    public function test_un_tipo_sin_peso_configurado_usa_uno(): void
    {
        $this->ajustes([]);
        $porDefecto = \Convoca\Assistant\Installer::default_settings()['weight_convoca_kb'];

        $this->assertSame((float) $porDefecto, Knowledge_Base::get_default_weight('convoca_kb'), 'sin configurar manda el valor por defecto del instalador');
        $this->assertSame(1.0, Knowledge_Base::get_default_weight('tipo-que-no-existe'), 'lo desconocido no puede quedar a cero: desaparecería del buscador');
    }

    public function test_el_peso_individual_manda_sobre_el_del_tipo(): void
    {
        $this->ajustes(['weight_convoca_faq' => 1]);
        $GLOBALS['_assistant_meta'][21]['_convoca_assistant_weight'] = '4.5';

        $this->assertSame(4.5, Knowledge_Base::get_effective_weight(21, 'convoca_faq'));
    }

    public function test_un_peso_individual_a_cero_o_vacio_no_cuenta(): void
    {
        $this->ajustes(['weight_convoca_faq' => 2]);

        $this->assertSame(2.0, Knowledge_Base::get_effective_weight(22, 'convoca_faq'), 'sin peso propio manda el del tipo');
        $GLOBALS['_assistant_meta'][23]['_convoca_assistant_weight'] = '0';
        $this->assertSame(2.0, Knowledge_Base::get_effective_weight(23, 'convoca_faq'));
    }
}

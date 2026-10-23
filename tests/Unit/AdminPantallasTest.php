<?php

/**
 * Las pantallas del asistente en el escritorio: menú, assets del panel y el aviso de
 * «consultas sin respuesta».
 *
 * `Admin` estaba al 0 % (148 statements). El aviso es lo que más importa: sin él, la pantalla
 * «Sin respuesta» se quedaba olvidada —fue el issue assistant#12—, así que se comprueban sus
 * cuatro salidas (sin permiso, en otra pantalla, cuando no hay nada pendiente y cuando lo hay) y
 * el botón de instalar el paquete de arranque.
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Admin;
use PHPUnit\Framework\TestCase;

class AdminPantallasTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['convoca_as_menus'] = [];
        $GLOBALS['convoca_as_submenus'] = [];
        $GLOBALS['convoca_as_estilos'] = [];
        $GLOBALS['convoca_as_scripts'] = [];
        $GLOBALS['convoca_as_localizados'] = [];
        $GLOBALS['convoca_as_caps'] = ['manage_options' => true];
        $GLOBALS['convoca_as_pantalla'] = 'toplevel_page_convoca-assistant';
        $GLOBALS['_assistant_options'] = [];
        $GLOBALS['wpdb']->resultados = [];
        unset($GLOBALS['convoca_as_salida']);
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['convoca_as_menus'],
            $GLOBALS['convoca_as_submenus'],
            $GLOBALS['convoca_as_estilos'],
            $GLOBALS['convoca_as_scripts'],
            $GLOBALS['convoca_as_localizados'],
            $GLOBALS['convoca_as_caps'],
            $GLOBALS['convoca_as_pantalla'],
            $GLOBALS['convoca_as_salida']
        );
        $GLOBALS['wpdb']->resultados = [];
    }

    /**
     * Captura la salida de una función.
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

    // ── el menú ─────────────────────────────────────────────────────────────────────

    public function test_el_menu_lleva_las_seis_pantallas_del_asistente(): void
    {
        Admin::register_menu();

        $this->assertArrayHasKey('convoca-assistant', $GLOBALS['convoca_as_menus'], 'sin menú no se llega al asistente');

        $submenus = $GLOBALS['convoca_as_submenus'];
        foreach ([
            'convoca-assistant',
            'convoca-assistant-knowledge',
            'convoca-assistant-synonyms',
            'convoca-assistant-analytics',
            'convoca-assistant-unanswered',
            'convoca-assistant-settings',
        ] as $slug) {
            $this->assertArrayHasKey($slug, $submenus, 'falta la pantalla ' . $slug);
            $this->assertSame('manage_options', $submenus[$slug]['cap'], $slug . ': esto es de administración, no de cualquiera');
        }
    }

    // ── los assets del panel ───────────────────────────────────────────────────────

    public function test_en_otra_pantalla_no_se_cargan_los_assets_del_asistente(): void
    {
        Admin::enqueue_admin_assets('edit.php');

        $this->assertSame([], $GLOBALS['convoca_as_estilos'], 'cargar el JS del asistente en todas las pantallas es peso de más');
        $this->assertSame([], $GLOBALS['convoca_as_scripts']);
    }

    public function test_en_su_pantalla_carga_estilo_script_y_los_datos(): void
    {
        Admin::enqueue_admin_assets('toplevel_page_convoca-assistant');

        $this->assertArrayHasKey('convoca-assistant-admin', $GLOBALS['convoca_as_estilos']);
        $this->assertArrayHasKey('convoca-assistant-admin', $GLOBALS['convoca_as_scripts']);

        $datos = $GLOBALS['convoca_as_localizados']['convocaAssistant'];

        $this->assertStringContainsString('/wp-json/convoca/v1/assistant/', (string) $datos['restUrl']);
        $this->assertSame('nonce-de-pruebas', $datos['nonce'], 'sin nonce el panel no puede llamar a REST');
        $this->assertStringContainsString('post-new.php?post_type=convoca_faq', $datos['createFaqUrl'], 'el enlace de crear FAQ es lo que se usa al ver una consulta sin respuesta');
        $this->assertIsArray($datos['indexStats']);
    }

    // ── el aviso de consultas sin respuesta ────────────────────────────────────────

    public function test_sin_permiso_el_aviso_no_sale(): void
    {
        $GLOBALS['convoca_as_caps'] = ['manage_options' => false];

        $this->assertSame('', $this->salida([Admin::class, 'aviso_sin_respuesta']));
    }

    public function test_en_una_pantalla_ajena_el_aviso_no_sale(): void
    {
        $GLOBALS['convoca_as_pantalla'] = 'edit.php';

        $this->assertSame('', $this->salida([Admin::class, 'aviso_sin_respuesta']), 'el aviso vive en las pantallas del asistente');
    }

    public function test_sin_pantalla_conocida_el_aviso_no_sale(): void
    {
        $GLOBALS['convoca_as_pantalla'] = null;

        $this->assertSame('', $this->salida([Admin::class, 'aviso_sin_respuesta']));
    }

    public function test_con_consultas_pendientes_el_aviso_las_cuenta_y_enlaza(): void
    {
        // Una fila pendiente en la consulta de «sin respuesta».
        $GLOBALS['wpdb']->resultados = [[
            ['query' => '', 'count' => 2, 'last_seen' => '2026-10-01 10:00:00'],
        ]];

        $html = $this->salida([Admin::class, 'aviso_sin_respuesta']);

        $this->assertStringContainsString('notice-warning', $html);
        $this->assertStringContainsString('1 consulta sin respuesta', $html, 'el número es lo que hace que alguien lo mire');
        $this->assertStringContainsString('convoca-assistant-unanswered', $html, 'y tiene que llevar a donde se arregla');
    }

    public function test_sin_pendientes_ni_arranque_instalado_avisa_de_lo_que_falta(): void
    {
        $GLOBALS['wpdb']->resultados = [[]];

        $html = $this->salida([Admin::class, 'aviso_sin_respuesta']);

        $this->assertStringContainsString('no hay consultas sin respuesta', $html);
        $this->assertStringContainsString('Instalar el paquete de arranque', $html, 'un asistente sin preguntas de arranque no contesta nada');
    }
}

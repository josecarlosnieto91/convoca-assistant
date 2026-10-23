<?php

/**
 * El widget del asistente en el front: qué se registra, qué se encola y qué se le cuenta al JS.
 *
 * `Widget` estaba al 0 % (144 statements). Aquí está la decisión de rendimiento que se puede
 * romper en silencio: el motor del chat (Fuse, sesión y chat, ~21 KiB comprimidos) se **registra
 * pero no se encola** salvo en una página con el chat embebido, y el JS lo inyecta al abrir. Si
 * alguien vuelve a encolarlo siempre, el sitio carga peso muerto en cada visita y nadie se entera.
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Widget;
use PHPUnit\Framework\TestCase;

class WidgetAssetsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['convoca_as_registrados'] = [];
        $GLOBALS['convoca_as_estilos'] = [];
        $GLOBALS['convoca_as_scripts'] = [];
        $GLOBALS['convoca_as_localizados'] = [];
        $GLOBALS['_assistant_options'] = [];
        $GLOBALS['convoca_as_post'] = null;
    }

    protected function tearDown(): void
    {
        $GLOBALS['convoca_as_registrados'] = [];
        $GLOBALS['convoca_as_estilos'] = [];
        $GLOBALS['convoca_as_scripts'] = [];
        $GLOBALS['convoca_as_localizados'] = [];
        $GLOBALS['_assistant_options'] = [];
        $GLOBALS['convoca_as_post'] = null;
    }

    /**
     * Fija ajustes del plugin.
     *
     * @param array<string, mixed> $valores Ajustes.
     */
    private function ajustes(array $valores): void
    {
        $GLOBALS['_assistant_options']['convoca_assistant_settings'] = $valores;
    }

    // ── el modo mantenimiento ───────────────────────────────────────────────────────

    public function test_en_mantenimiento_no_se_carga_nada(): void
    {
        $this->ajustes(['maintenance_mode' => 1, 'widget_enabled' => 1]);

        Widget::enqueue_assets();

        $this->assertSame([], $GLOBALS['convoca_as_registrados'], 'en mantenimiento no hay asistente que enseñar');
        $this->assertSame([], $GLOBALS['convoca_as_scripts']);
    }

    // ── lo que se registra y lo que se encola ───────────────────────────────────────

    public function test_el_motor_del_chat_se_registra_pero_no_se_encola(): void
    {
        $this->ajustes(['widget_enabled' => 1]);

        Widget::enqueue_assets();

        $registrados = $GLOBALS['convoca_as_registrados'];
        $encolados   = array_keys($GLOBALS['convoca_as_scripts']);

        $this->assertArrayHasKey('convoca-assistant-fuse', $registrados);
        $this->assertArrayHasKey('convoca-assistant-session', $registrados);
        $this->assertArrayHasKey('convoca-assistant-chat', $registrados);

        $this->assertNotContains('convoca-assistant-fuse', $encolados, 'el motor pesa ~21 KiB y solo hace falta al abrir el chat');
        $this->assertNotContains('convoca-assistant-chat', $encolados);

        $this->assertContains('convoca-assistant-widget', $encolados, 'el botón sí: es lo primero que se ve');
    }

    public function test_el_boton_lleva_su_hoja_y_el_chat_la_suya_sin_cargar(): void
    {
        $this->ajustes(['widget_enabled' => 1]);

        Widget::enqueue_assets();

        $this->assertArrayHasKey('convoca-assistant-widget', $GLOBALS['convoca_as_estilos']);
        $this->assertArrayHasKey('convoca-assistant-chat', $GLOBALS['convoca_as_registrados'], 'la del chat se registra para cargarla al abrir');
        $this->assertNotContains('convoca-assistant-chat', array_keys($GLOBALS['convoca_as_estilos']));
    }

    public function test_el_js_recibe_las_rutas_diferidas_para_inyectarlas(): void
    {
        $this->ajustes(['widget_enabled' => 1]);

        Widget::enqueue_assets();

        $datos = $GLOBALS['convoca_as_localizados']['convocaAssistant'];

        $this->assertIsArray($datos['lazyAssets']);
        $this->assertCount(3, $datos['lazyAssets']['js'], 'los tres del motor se cargan a demanda');
        $this->assertCount(1, $datos['lazyAssets']['css']);
        $this->assertStringContainsString('ver=', $datos['lazyAssets']['js'][0], 'sin versión en la URL, el navegador se queda con el fichero viejo');
    }

    public function test_en_una_pagina_con_el_chat_embebido_se_encola_todo(): void
    {
        $this->ajustes(['widget_enabled' => 1]);
        $GLOBALS['convoca_as_post'] = (object) [
            'ID' => 5,
            'post_content' => 'Texto de la página [convoca_assistant] y más texto',
        ];

        Widget::enqueue_assets();

        $encolados = array_keys($GLOBALS['convoca_as_scripts']);

        $this->assertContains('convoca-assistant-chat', $encolados, 'aquí el diálogo se ve sin interacción: diferirlo no tiene sentido');
        $this->assertContains('convoca-assistant-chat', array_keys($GLOBALS['convoca_as_estilos']));
        $this->assertNull($GLOBALS['convoca_as_localizados']['convocaAssistant']['lazyAssets'], 'no hay nada que diferir');
    }

    // ── lo que se le cuenta al JS ───────────────────────────────────────────────────

    public function test_los_valores_por_defecto_del_widget_llegan_al_js(): void
    {
        $this->ajustes(['widget_enabled' => 1]);

        Widget::enqueue_assets();

        $datos = $GLOBALS['convoca_as_localizados']['convocaAssistant'];

        $this->assertSame('#2563eb', $datos['settings']['primaryColor']);
        $this->assertSame('Asistente Virtual', $datos['settings']['title']);
        $this->assertSame(10, $datos['settings']['maxResults']);
        $this->assertSame(600, $datos['settings']['maxAnswerLength']);
        $this->assertSame(['convoca_faq', 'convoca_kb'], $datos['settings']['priorityTypes'], 'las FAQ y la base de conocimiento mandan');
        $this->assertSame(4, $datos['settings']['weights']['title'], 'un acierto en el título pesa más que en el cuerpo');
        $this->assertSame('Escribe tu pregunta aquí...', $datos['i18n']['placeholder'], 'los textos del widget son traducibles, no cadenas del JS');
        $this->assertArrayHasKey('nonce', $datos, 'sin nonce el widget no puede llamar a REST');
        $this->assertArrayHasKey('indexExists', $datos);
    }

    public function test_un_sitio_configurado_manda_sobre_los_valores_por_defecto(): void
    {
        $this->ajustes([
            'widget_enabled' => 1,
            'widget_title' => 'Pregúntame',
            'widget_primary_color' => '#ff8700',
            'search_max_results' => 4,
            'contact_email' => 'hola@example.test',
            'priority_types' => ['convoca_kb'],
        ]);

        Widget::enqueue_assets();

        $datos = $GLOBALS['convoca_as_localizados']['convocaAssistant'];

        $this->assertSame('Pregúntame', $datos['settings']['title']);
        $this->assertSame('#ff8700', $datos['settings']['primaryColor']);
        $this->assertSame(4, $datos['settings']['maxResults']);
        $this->assertSame('hola@example.test', $datos['settings']['contact']['email']);
        $this->assertSame(['convoca_kb'], $datos['settings']['priorityTypes']);
    }

    // ── el botón flotante ──────────────────────────────────────────────────────────

    public function test_el_boton_no_se_pinta_si_el_widget_esta_apagado(): void
    {
        $this->ajustes(['widget_enabled' => 0]);

        ob_start();
        Widget::render_floating_widget();
        $html = (string) ob_get_clean();

        $this->assertSame('', $html, 'un widget apagado que se pinta igual es un widget que no se puede apagar');
    }

    public function test_el_boton_tampoco_en_mantenimiento(): void
    {
        $this->ajustes(['widget_enabled' => 1, 'maintenance_mode' => 1]);

        ob_start();
        Widget::render_floating_widget();
        $html = (string) ob_get_clean();

        $this->assertSame('', $html);
    }
}

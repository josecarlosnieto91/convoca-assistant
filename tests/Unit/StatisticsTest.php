<?php

/**
 * La analítica del asistente: registro de consultas, cifras del panel y limpieza.
 *
 * `Statistics` estaba al 0 % (151 statements). No es adorno: de aquí salen la tasa de resolución,
 * las consultas sin respuesta y el aviso del panel. Lo que se prueba es el cálculo y las decisiones
 * (qué se registra, qué se anonimiza, qué se cuenta como «sin respuesta»), con un doble de `$wpdb`
 * que devuelve respuestas preparadas, no con una base de datos de verdad.
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Statistics;
use PHPUnit\Framework\TestCase;

class StatisticsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_assistant_options'] = [];
        $GLOBALS['wpdb']->vars = [];
        $GLOBALS['wpdb']->resultados = [];
        $GLOBALS['wpdb']->inserts = [];
        $GLOBALS['wpdb']->consultas = [];
        $_SESSION = ['convoca_assistant_session' => 'sesion-de-pruebas'];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_assistant_options'], $_SESSION);
        $GLOBALS['wpdb']->vars = [];
        $GLOBALS['wpdb']->resultados = [];
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

    // ── el registro de consultas ──────────────────────────────────────────────────────

    public function test_con_el_registro_apagado_no_se_guarda_nada(): void
    {
        $this->ajustes(['log_enabled' => 0]);

        Statistics::log('cómo me hago socio', null, false, 0.0, false, 12);

        $this->assertSame([], $GLOBALS['wpdb']->inserts, 'si está apagado no se escribe ni una fila');
    }

    public function test_una_consulta_registrada_lleva_su_resultado_y_su_tiempo(): void
    {
        $this->ajustes(['log_enabled' => 1]);

        Statistics::log('cómo me hago socio', 7, true, 0.87, false, 42, 'Mozilla/5.0', 'https://example.test/faq');

        $this->assertCount(1, $GLOBALS['wpdb']->inserts);
        [$tabla, $datos] = $GLOBALS['wpdb']->inserts[0];

        $this->assertStringContainsString('convoca_assistant', $tabla);
        $this->assertSame('cómo me hago socio', $datos['query']);
        $this->assertSame(7, $datos['response_id']);
        $this->assertSame(1, $datos['response_found'], 'el booleano se guarda como entero');
        $this->assertSame(0.87, (float) $datos['score']);
        $this->assertSame(42, $datos['query_time_ms']);
        $this->assertNotSame('', (string) $datos['session_id'], 'cada fila necesita una sesión: es lo que agrupa una conversación');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $datos['created_at'], 'la fecha va en UTC, no en la del servidor');
    }

    public function test_el_agente_de_usuario_se_anonimiza_si_esta_configurado(): void
    {
        $this->ajustes(['log_enabled' => 1, 'log_anonymous' => 1]);

        Statistics::log('consulta', null, false, 0.0, false, 5, 'Mozilla/5.0 (X11; Linux)');

        $hash = $GLOBALS['wpdb']->inserts[0][1]['user_agent_hash'];

        $this->assertSame(hash('sha256', 'Mozilla/5.0 (X11; Linux)'), $hash, 'guardar el navegador en claro es dato personal');
        $this->assertStringNotContainsString('Mozilla', $hash);
    }

    public function test_sin_anonimizar_se_guarda_tal_cual(): void
    {
        $this->ajustes(['log_enabled' => 1, 'log_anonymous' => 0]);

        Statistics::log('consulta', null, true, 0.5, true, 9, 'Mozilla/5.0');

        $this->assertSame('Mozilla/5.0', $GLOBALS['wpdb']->inserts[0][1]['user_agent_hash']);
        $this->assertSame(1, $GLOBALS['wpdb']->inserts[0][1]['clicked']);
    }

    public function test_sin_agente_de_usuario_no_se_inventa_uno(): void
    {
        $this->ajustes(['log_enabled' => 1, 'log_anonymous' => 1]);

        Statistics::log('consulta', null, true, 0.5, false, 9);

        $this->assertSame('', $GLOBALS['wpdb']->inserts[0][1]['user_agent_hash']);
    }

    // ── las cifras del panel ────────────────────────────────────────────────────────

    public function test_las_cifras_calculan_la_tasa_de_resolucion(): void
    {
        // total, encontradas, media de puntuación, media de tiempo, top, diario
        $GLOBALS['wpdb']->vars = [10, 7, 0.81234, 34.567];
        $GLOBALS['wpdb']->resultados = [
            [['query' => 'socios', 'count' => 4, 'avg_score' => 0.9]],
            [['day' => '2026-10-01', 'count' => 3]],
        ];

        $cifras = Statistics::get_stats(7);

        $this->assertSame(10, $cifras['total']);
        $this->assertSame(7, $cifras['found']);
        $this->assertSame(3, $cifras['not_found'], 'lo no encontrado se deriva, no se pregunta aparte');
        $this->assertSame(70.0, $cifras['resolution_rate']);
        $this->assertSame(0.8123, $cifras['avg_score'], 'redondeado a 4 decimales');
        $this->assertSame(34.57, $cifras['avg_time_ms'], 'redondeado a 2 decimales');
        $this->assertCount(1, $cifras['top_queries']);
        $this->assertCount(1, $cifras['daily']);
    }

    public function test_sin_consultas_la_tasa_es_cero_y_no_una_división_por_cero(): void
    {
        $GLOBALS['wpdb']->vars = [0, 0, null, null];
        $GLOBALS['wpdb']->resultados = [[], []];

        $cifras = Statistics::get_stats();

        $this->assertSame(0, $cifras['total']);
        $this->assertSame(0, $cifras['resolution_rate'], 'un panel sin datos no puede enseñar NAN');
        $this->assertSame(0.0, $cifras['avg_score']);
    }

    // ── las últimas consultas ───────────────────────────────────────────────────────

    public function test_las_ultimas_consultas_traen_el_titulo_de_lo_que_respondio(): void
    {
        $GLOBALS['wpdb']->resultados = [[
            ['id' => 1, 'query' => 'socios', 'response_id' => 12, 'response_found' => 1, 'score' => '0.75', 'clicked' => 1, 'query_time_ms' => '31', 'created_at' => '2026-10-01 10:00:00'],
            ['id' => 2, 'query' => 'sin respuesta', 'response_id' => 0, 'response_found' => 0, 'score' => '0', 'clicked' => 0, 'query_time_ms' => null, 'created_at' => '2026-10-01 10:01:00'],
        ]];

        $filas = Statistics::get_recent(5);

        $this->assertSame('Pregunta 12', $filas[0]['source'], 'sin el título, la fila no dice a qué respondió');
        $this->assertSame(0.75, $filas[0]['score']);
        $this->assertSame(31, $filas[0]['time_ms']);
        $this->assertSame('', $filas[1]['source'], 'una consulta sin respuesta no tiene origen');
        $this->assertSame(0, $filas[1]['time_ms']);
    }

    public function test_si_la_consulta_no_devuelve_nada_la_lista_es_vacia(): void
    {
        $GLOBALS['wpdb']->resultados = [null];

        $this->assertSame([], Statistics::get_recent());
    }

    // ── sin respuesta ───────────────────────────────────────────────────────────────

    public function test_lo_que_sigue_sin_respuesta_se_queda_en_la_lista(): void
    {
        // Comportamiento medido: `tiene_respuesta()` devuelve false para una consulta en blanco,
        // así que la fila se conserva. Se deja escrito para que nadie lo confunda con una prueba
        // de que el filtro funciona: lo que el filtro hace es quitar lo que YA se contesta.
        $GLOBALS['wpdb']->resultados = [[
            ['query' => '', 'count' => 3, 'last_seen' => '2026-10-01 10:00:00'],
        ]];

        $filas = Statistics::get_unanswered();

        $this->assertCount(1, $filas, 'sin respuesta comprobable, la fila sigue listada');
        $this->assertSame('', $filas[0]['query']);
    }

    public function test_el_aviso_del_panel_cuenta_las_pendientes_de_la_semana(): void
    {
        $GLOBALS['wpdb']->resultados = [[
            ['query' => '', 'count' => 1, 'last_seen' => '2026-10-01 10:00:00'],
        ]];

        $this->assertSame(1, Statistics::unanswered_this_week(), 'el aviso cuenta las filas que la pantalla enseña');
    }

    // ── limpieza ────────────────────────────────────────────────────────────────────

    public function test_la_limpieza_usa_la_retencion_configurada(): void
    {
        $this->ajustes(['log_retention_days' => 30]);

        Statistics::cleanup();

        $this->assertCount(1, $GLOBALS['wpdb']->consultas);
        $this->assertStringContainsString('DELETE FROM', $GLOBALS['wpdb']->consultas[0]);
        $esperado = gmdate('Y-m-d', time() - 30 * DAY_IN_SECONDS);
        $this->assertStringContainsString($esperado, $GLOBALS['wpdb']->consultas[0], 'la fecha de corte sale de la retención, no de un valor fijo');
    }

    public function test_sin_retencion_configurada_se_limpian_los_ultimos_noventa_dias(): void
    {
        Statistics::cleanup();

        $esperado = gmdate('Y-m-d', time() - 90 * DAY_IN_SECONDS);
        $this->assertStringContainsString($esperado, $GLOBALS['wpdb']->consultas[0]);
    }
}

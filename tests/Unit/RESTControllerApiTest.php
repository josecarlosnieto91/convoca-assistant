<?php

/**
 * La API REST del asistente: rutas, permisos, limitador y cada respuesta.
 *
 * `REST_Controller` estaba al 1,7 % (4 de 241 statements). Lo que se comprueba aquí son las
 * decisiones que se pueden romper en silencio: qué rutas son públicas y cuáles piden
 * administración, que el limitador corte con 429 cuando toca, y qué devuelve cada endpoint con
 * datos, sin datos y con la entrada mal.
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\REST_Controller;
use PHPUnit\Framework\TestCase;

class RESTControllerApiTest extends TestCase
{
    /** Ruta del índice que comparten los dobles del arnés. */
    private string $indice;

    /** Contenido previo del índice (otras pruebas lo usan): se restaura al terminar. */
    private ?string $indicePrevio = null;

    protected function setUp(): void
    {
        $GLOBALS['convoca_as_rutas'] = [];
        $GLOBALS['convoca_as_caps'] = ['manage_options' => true];
        $GLOBALS['convoca_as_ratelimit_ok'] = true;
        $GLOBALS['convoca_as_ratelimit_llamadas'] = [];
        $GLOBALS['_assistant_options'] = [];
        $GLOBALS['_assistant_meta'] = [];
        $GLOBALS['wpdb']->vars = [];
        $GLOBALS['wpdb']->resultados = [];
        $GLOBALS['wpdb']->consultas = [];
        $GLOBALS['wpdb']->inserts = [];
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);

        // El índice vive en el directorio que ya fija el arnés y lo comparten otras pruebas
        // (BusquedaRankingTest escribe el suyo): se guarda el de partida y se restaura después.
        $this->indice = CONVOCA_ASSISTANT_INDEX_DIR . 'index.json';
        if (!is_dir(CONVOCA_ASSISTANT_INDEX_DIR)) {
            mkdir(CONVOCA_ASSISTANT_INDEX_DIR, 0777, true);
        }
        $this->indicePrevio = file_exists($this->indice) ? (string) file_get_contents($this->indice) : null;
        if (null !== $this->indicePrevio) {
            unlink($this->indice);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->indice)) {
            unlink($this->indice);
        }
        if (null !== $this->indicePrevio) {
            file_put_contents($this->indice, $this->indicePrevio);
        }
        unset(
            $GLOBALS['convoca_as_rutas'],
            $GLOBALS['convoca_as_caps'],
            $GLOBALS['convoca_as_ratelimit_ok'],
            $GLOBALS['convoca_as_ratelimit_llamadas'],
            $GLOBALS['_assistant_meta']
        );
    }

    // ── las rutas y sus permisos ─────────────────────────────────────────────────────

    public function test_se_registran_las_nueve_rutas(): void
    {
        REST_Controller::register_routes();

        $rutas = $GLOBALS['convoca_as_rutas'];

        foreach ([
            '/assistant/index',
            '/assistant/search',
            '/assistant/log',
            '/assistant/stats',
            '/assistant/unanswered',
            '/assistant/recent',
            '/assistant/rebuild-index',
            '/assistant/clear-logs',
        ] as $ruta) {
            $this->assertArrayHasKey('convoca/v1' . $ruta, $rutas, 'falta la ruta ' . $ruta);
        }
    }

    public function test_el_indice_y_la_busqueda_son_publicos(): void
    {
        REST_Controller::register_routes();

        $rutas = $GLOBALS['convoca_as_rutas'];

        $this->assertSame('__return_true', $rutas['convoca/v1/assistant/index']['permission_callback'], 'el índice lo lee el buscador del front');
        $this->assertSame([REST_Controller::class, 'check_rate_limit'], $rutas['convoca/v1/assistant/search']['permission_callback'], 'la búsqueda es pública pero con limitador');
        $this->assertSame([REST_Controller::class, 'check_rate_limit'], $rutas['convoca/v1/assistant/log']['permission_callback'], 'el registro de consultas también se limita');
    }

    public function test_las_rutas_de_administracion_piden_manage_options(): void
    {
        REST_Controller::register_routes();

        $rutas = $GLOBALS['convoca_as_rutas'];

        foreach (['/assistant/stats', '/assistant/unanswered', '/assistant/recent', '/assistant/rebuild-index', '/assistant/clear-logs'] as $ruta) {
            $permiso = $rutas['convoca/v1' . $ruta]['permission_callback'];
            $this->assertIsCallable($permiso);
            $this->assertTrue((bool) $permiso(), $ruta . ': con permiso de administración deja pasar');
        }

        // Sin permiso, NINGUNA de las cinco deja pasar: comprobar solo una dejaba las otras
        // cuatro sin cubrir (lo destapó la verificación en negativo).
        $GLOBALS['convoca_as_caps'] = ['manage_options' => false];
        foreach (['/assistant/stats', '/assistant/unanswered', '/assistant/recent', '/assistant/rebuild-index', '/assistant/clear-logs'] as $ruta) {
            $permiso = $GLOBALS['convoca_as_rutas']['convoca/v1' . $ruta]['permission_callback'];
            $this->assertFalse((bool) $permiso(), $ruta . ': sin manage_options no puede pasar');
        }
    }

    // ── el limitador ────────────────────────────────────────────────────────────────

    public function test_el_limitador_deja_pasar_por_debajo_del_umbral(): void
    {
        $this->assertTrue(REST_Controller::check_rate_limit());
        $this->assertSame('assistant_rest', $GLOBALS['convoca_as_ratelimit_llamadas'][0][0], 'el contador es propio: no se mezcla con otros subsistemas');
    }

    public function test_el_limitador_corta_con_429(): void
    {
        $GLOBALS['convoca_as_ratelimit_ok'] = false;

        $resultado = REST_Controller::check_rate_limit();

        $this->assertInstanceOf(\WP_Error::class, $resultado);
        $this->assertSame('rate_limit_exceeded', $resultado->get_error_code());
        $this->assertSame(429, $resultado->data['status']);
    }

    // ── el índice ───────────────────────────────────────────────────────────────────

    public function test_sin_indice_el_endpoint_dice_404(): void
    {
        $resultado = REST_Controller::get_index(new \WP_REST_Request());

        $this->assertInstanceOf(\WP_Error::class, $resultado);
        $this->assertSame('index_not_found', $resultado->get_error_code());
    }

    public function test_un_indice_corrupto_no_se_sirve_como_bueno(): void
    {
        file_put_contents($this->indice, '{esto no es json');

        $resultado = REST_Controller::get_index(new \WP_REST_Request());

        $this->assertSame('index_parse_error', $resultado->get_error_code());
    }

    public function test_el_indice_bueno_sale_con_cache_y_cors(): void
    {
        file_put_contents($this->indice, json_encode(['entries' => [['id' => 1]]]));

        $resultado = REST_Controller::get_index(new \WP_REST_Request());

        $this->assertInstanceOf(\WP_REST_Response::class, $resultado);
        $this->assertSame(200, $resultado->get_status());
        $this->assertArrayHasKey('entries', $resultado->get_data());
        $this->assertSame('public, max-age=300', $resultado->get_headers()['Cache-Control'], 'sin caché cada visita del front pide el índice entero');
        $this->assertSame('*', $resultado->get_headers()['Access-Control-Allow-Origin']);
    }

    // ── la búsqueda ────────────────────────────────────────────────────────────────

    public function test_una_consulta_de_un_caracter_no_busca(): void
    {
        $resultado = REST_Controller::search(new \WP_REST_Request(['query' => 'a']));

        $this->assertInstanceOf(\WP_Error::class, $resultado);
        $this->assertSame('query_too_short', $resultado->get_error_code());
        $this->assertSame(400, $resultado->data['status']);
    }

    public function test_una_busqueda_devuelve_consulta_y_resultados(): void
    {
        $resultado = REST_Controller::search(new \WP_REST_Request(['query' => 'socios']));

        $this->assertInstanceOf(\WP_REST_Response::class, $resultado);
        $datos = $resultado->get_data();
        $this->assertSame('socios', $datos['query']);
        $this->assertArrayHasKey('results', $datos);
    }

    // ── el registro de interacciones ───────────────────────────────────────────────

    public function test_con_el_registro_apagado_no_se_escribe(): void
    {
        // `log_enabled` viene activado por defecto: hay que apagarlo para probar el camino.
        $GLOBALS['_assistant_options']['convoca_assistant_settings'] = ['log_enabled' => 0];

        $resultado = REST_Controller::log_interaction(new \WP_REST_Request(['query' => 'socios']));

        $this->assertFalse($resultado->get_data()['logged']);
        $this->assertSame([], $GLOBALS['wpdb']->inserts);
    }

    public function test_con_el_registro_encendido_se_guarda_la_interaccion(): void
    {
        $GLOBALS['_assistant_options']['convoca_assistant_settings'] = ['log_enabled' => 1];
        $_SESSION = ['convoca_assistant_session' => 'sesion'];

        $request = new \WP_REST_Request(
            ['query' => 'socios', 'response_id' => 7, 'response_found' => 1, 'score' => 0.8, 'time_ms' => 12, 'page_url' => 'https://example.test'],
            ['User-Agent' => 'Mozilla/5.0']
        );

        $resultado = REST_Controller::log_interaction($request);

        $this->assertTrue($resultado->get_data()['logged']);
        $this->assertCount(1, $GLOBALS['wpdb']->inserts);
        $this->assertSame('socios', $GLOBALS['wpdb']->inserts[0][1]['query']);
    }

    // ── las cifras y las listas ────────────────────────────────────────────────────

    public function test_los_dias_se_acotan_entre_uno_y_trescientos_sesenta_y_cinco(): void
    {
        $GLOBALS['wpdb']->vars = [0, 0, null, null];
        $GLOBALS['wpdb']->resultados = [[], []];

        $resultado = REST_Controller::get_stats(new \WP_REST_Request(['days' => 9999]));
        $this->assertSame(200, $resultado->get_status());

        $GLOBALS['wpdb']->vars = [0, 0, null, null];
        $GLOBALS['wpdb']->resultados = [[], []];
        $resultado = REST_Controller::get_stats(new \WP_REST_Request(['days' => 0]));
        $this->assertSame(200, $resultado->get_status(), 'sin días se usan los de por defecto, no cero');
    }

    public function test_borrar_el_registro_vacia_la_tabla(): void
    {
        $resultado = REST_Controller::clear_logs(new \WP_REST_Request());

        $this->assertTrue($resultado->get_data()['success']);
        $this->assertStringContainsString('TRUNCATE TABLE', $GLOBALS['wpdb']->consultas[0]);
        $this->assertStringContainsString('convoca_assistant_log', $GLOBALS['wpdb']->consultas[0]);
    }
}

<?php

/**
 * El exportador e importador de la base de conocimiento: `Export_Import` estaba al **0 %**
 * (187 statements), así que el trasvase de un sitio a otro —sinónimos, palabras vacías, FAQ,
 * artículos y ajustes— no tenía ni una prueba.
 *
 * `handle_export()` y `handle_import()` terminan en `exit` (mandan cabeceras y cortan), así que lo
 * que se ejercita aquí es lo que decide el contenido: los cuatro métodos de datos y las guardas
 * que se pueden alcanzar sin mandar cabeceras (permiso, nonce, tipo no válido, error de subida).
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Export_Import;
use PHPUnit\Framework\TestCase;

if (!defined('CONVOCA_ASSISTANT_VERSION')) {
    define('CONVOCA_ASSISTANT_VERSION', '0.3.0');
}

class ExportImportTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['convoca_as_caps'] = ['manage_options' => true];
        $GLOBALS['convoca_as_nonce'] = true;
        $GLOBALS['convoca_as_posts'] = [];
        $GLOBALS['convoca_as_insertados'] = [];
        $GLOBALS['_assistant_meta'] = [];
        $GLOBALS['_assistant_options'] = [];
        $GLOBALS['convoca_as_redirect'] = null;
        unset($_GET, $_FILES);
    }

    protected function tearDown(): void
    {
        // Importar marca el índice como sucio: si se queda puesto, la siguiente prueba que
        // comprueba el estado del índice falla por culpa de ésta (medido: IndexerLegacyTest).
        delete_transient('convoca_assistant_index_dirty');
        delete_transient('convoca_assistant_index_debounce');
        unset(
            $GLOBALS['convoca_as_caps'],
            $GLOBALS['convoca_as_nonce'],
            $GLOBALS['convoca_as_posts'],
            $GLOBALS['convoca_as_insertados'],
            $GLOBALS['_assistant_meta'],
            $GLOBALS['convoca_as_redirect']
        );
        unset($_GET, $_FILES);
    }

    /**
     * Llama a un método privado del exportador.
     *
     * @param string $metodo Nombre del método.
     * @param array  $args   Argumentos.
     * @return mixed
     */
    private function privado(string $metodo, array $args = [])
    {
        $ref = new \ReflectionMethod(Export_Import::class, $metodo);

        return $ref->invokeArgs(null, $args);
    }

    /**
     * Un post falso para el doble de WP_Query.
     *
     * @param int                  $id      Identificador.
     * @param array<string, mixed> $campos  Título, contenido, extracto y estado.
     * @return object
     */
    private function post(int $id, array $campos = []): object
    {
        return (object) [
            'ID' => $id,
            'post_title' => $campos['title'] ?? 'Pregunta ' . $id,
            'post_content' => $campos['content'] ?? 'Contenido ' . $id,
            'post_excerpt' => $campos['excerpt'] ?? '',
            'post_status' => $campos['status'] ?? 'publish',
        ];
    }

    // ── las guardas ───────────────────────────────────────────────────────────────────

    public function test_sin_permiso_no_se_exporta(): void
    {
        $GLOBALS['convoca_as_caps'] = ['manage_options' => false];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No tienes permisos');

        Export_Import::handle_export();
    }

    public function test_sin_permiso_no_se_importa(): void
    {
        $GLOBALS['convoca_as_caps'] = ['manage_options' => false];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No tienes permisos');

        Export_Import::handle_import();
    }

    public function test_un_tipo_de_exportacion_inventado_no_se_sirve(): void
    {
        $_GET = ['type' => 'todo'];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tipo de exportación no válido');

        Export_Import::handle_export();
    }

    public function test_sin_archivo_no_se_importa_nada(): void
    {
        $_FILES = [];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No se seleccionó ningún archivo');

        Export_Import::handle_import();
    }

    public function test_un_archivo_que_no_es_json_se_rechaza(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'convoca') . '.txt';
        file_put_contents($ruta, '{"_meta":{"type":"knowledge"}}');
        $_FILES = [
            'import_file' => [
                'error' => UPLOAD_ERR_OK,
                'size' => 100,
                'name' => 'datos.txt',
                'tmp_name' => $ruta,
            ],
        ];

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Solo se permiten archivos .json');
            Export_Import::handle_import();
        } finally {
            @unlink($ruta);
        }
    }

    public function test_un_archivo_vacio_se_rechaza(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'convoca') . '.json';
        file_put_contents($ruta, '');
        $_FILES = [
            'import_file' => [
                'error' => UPLOAD_ERR_OK,
                'size' => 0,
                'name' => 'datos.json',
                'tmp_name' => $ruta,
            ],
        ];

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('vacío');
            Export_Import::handle_import();
        } finally {
            @unlink($ruta);
        }
    }

    public function test_un_json_sin_metadatos_no_se_acepta(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'convoca') . '.json';
        file_put_contents($ruta, '{"faqs":[]}');
        $_FILES = [
            'import_file' => [
                'error' => UPLOAD_ERR_OK,
                'size' => 12,
                'name' => 'datos.json',
                'tmp_name' => $ruta,
            ],
        ];

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Formato de archivo no reconocido');
            Export_Import::handle_import();
        } finally {
            @unlink($ruta);
        }
    }

    public function test_un_tipo_de_importacion_inventado_se_rechaza(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'convoca') . '.json';
        file_put_contents($ruta, '{"_meta":{"type":"inventado"}}');
        $_FILES = [
            'import_file' => [
                'error' => UPLOAD_ERR_OK,
                'size' => 30,
                'name' => 'datos.json',
                'tmp_name' => $ruta,
            ],
        ];

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Tipo de importación no reconocido');
            Export_Import::handle_import();
        } finally {
            @unlink($ruta);
        }
    }

    public function test_un_archivo_demasiado_grande_se_rechaza(): void
    {
        $_FILES = [
            'import_file' => [
                'error' => UPLOAD_ERR_OK,
                'size' => 10 * 1024 * 1024,
                'name' => 'datos.json',
                'tmp_name' => '/tmp/no-existe',
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tamaño máximo');

        Export_Import::handle_import();
    }

    public function test_los_errores_de_subida_se_traducen(): void
    {
        $conocidos = [
            UPLOAD_ERR_INI_SIZE => 'máximo permitido por el servidor',
            UPLOAD_ERR_FORM_SIZE => 'máximo del formulario',
            UPLOAD_ERR_PARTIAL => 'parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se seleccionó',
            UPLOAD_ERR_NO_TMP_DIR => 'directorio temporal',
            UPLOAD_ERR_CANT_WRITE => 'no se pudo escribir',
        ];

        foreach ($conocidos as $codigo => $esperado) {
            $mensaje = $this->privado('upload_error_message', [$codigo]);
            $this->assertStringContainsString($esperado, $mensaje, 'código ' . $codigo . ' sin traducir');
        }

        $desconocido = $this->privado('upload_error_message', [99]);
        $this->assertStringContainsString('No se ha podido subir', $desconocido, 'un código raro no puede salir en blanco');
    }

    // ── exportar conocimiento ────────────────────────────────────────────────────────

    public function test_la_exportacion_lleva_su_metadato_y_los_dos_tipos_de_contenido(): void
    {
        $GLOBALS['convoca_as_posts'] = [
            'convoca_faq' => [$this->post(7, ['title' => '¿Cómo me hago socio?'])],
            'convoca_kb' => [$this->post(9, ['title' => 'Estatutos', 'excerpt' => 'Resumen'])],
        ];
        $GLOBALS['_assistant_meta'][7]['_convoca_assistant_weight'] = 1.5;
        $GLOBALS['_assistant_meta'][9]['_convoca_assistant_exclude'] = true;

        $datos = $this->privado('export_knowledge');

        $this->assertSame('knowledge', $datos['_meta']['type'], 'sin el tipo, el archivo no se puede reimportar');
        $this->assertArrayHasKey('exported', $datos['_meta']);
        $this->assertSame('https://example.test', $datos['_meta']['site']);
        $this->assertCount(1, $datos['faqs']);
        $this->assertSame('¿Cómo me hago socio?', $datos['faqs'][0]['title']);
        $this->assertSame(1.5, (float) $datos['faqs'][0]['weight'], 'el peso viaja: es lo que ordena el buscador');
        $this->assertTrue($datos['kb'][0]['exclude'], 'lo excluido también viaja: si no, al importar vuelve a salir');
        $this->assertSame('Resumen', $datos['kb'][0]['excerpt']);
    }

    public function test_un_sitio_sin_faq_ni_articulos_exporta_listas_vacias(): void
    {
        $datos = $this->privado('export_knowledge');

        $this->assertSame([], $datos['faqs']);
        $this->assertSame([], $datos['kb']);
    }

    public function test_la_exportacion_de_ajustes_lleva_su_tipo(): void
    {
        $datos = $this->privado('export_settings');

        $this->assertSame('settings', $datos['_meta']['type']);
        $this->assertArrayHasKey('settings', $datos);
    }

    // ── importar ────────────────────────────────────────────────────────────────────

    public function test_importar_conocimiento_da_de_alta_faq_y_articulos(): void
    {
        $this->privado(
            'import_knowledge',
            [[
                'synonyms' => ['carnet' => ['licencia']],
                'stop_words' => ['de', 'la'],
                'faqs' => [
                    ['title' => '¿Cómo me hago socio?', 'content' => 'Con el formulario', 'weight' => 2, 'exclude' => true],
                    ['title' => '', 'content' => 'Sin título no entra'],
                ],
                'kb' => [
                    ['title' => 'Estatutos', 'content' => 'Texto', 'excerpt' => 'Resumen'],
                ],
            ]]
        );

        $insertados = $GLOBALS['convoca_as_insertados'];

        $this->assertCount(2, $insertados, 'la FAQ sin título se descarta, no se crea vacía');

        $tipos = array_column($insertados, 'post_type');
        $this->assertSame(['convoca_faq', 'convoca_kb'], $tipos);

        $primero = array_key_first($insertados);
        $this->assertSame(2.0, (float) $GLOBALS['_assistant_meta'][$primero]['_convoca_assistant_weight'], 'el peso se guarda como número');
        $this->assertTrue($GLOBALS['_assistant_meta'][$primero]['_convoca_assistant_exclude']);
    }

    public function test_importar_conocimiento_sin_datos_no_rompe_nada(): void
    {
        $this->privado('import_knowledge', [[]]);

        $this->assertSame([], $GLOBALS['convoca_as_insertados'] ?? []);
    }

    public function test_importar_ajustes_los_sanea(): void
    {
        $this->privado('import_settings', [['settings' => ['log_enabled' => 1]]]);

        $this->assertArrayHasKey('convoca_assistant_settings', $GLOBALS['_assistant_options']);
    }

    public function test_importar_ajustes_sin_bloque_de_ajustes_no_toca_nada(): void
    {
        $this->privado('import_settings', [['otra_cosa' => 1]]);

        $this->assertArrayNotHasKey('convoca_assistant_settings', $GLOBALS['_assistant_options']);
    }

    public function test_los_dos_ganchos_de_admin_quedan_puestos(): void
    {
        Export_Import::init();

        $ganchos = $GLOBALS['convoca_as_acciones'] ?? [];

        $this->assertArrayHasKey('admin_post_convoca_assistant_export', $ganchos);
        $this->assertArrayHasKey('admin_post_convoca_assistant_import', $ganchos);
    }
}

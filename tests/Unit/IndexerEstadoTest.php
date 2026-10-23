<?php

/**
 * El índice de conocimiento: cuándo se marca sucio, cuándo se reconstruye y qué ficheros usa.
 *
 * `Indexer` estaba al 12,9 % (26 de 201 statements). Lo que importa no es generar el JSON —eso son
 * los proveedores— sino las decisiones que se pueden romper en silencio: el tope de ritmo que evita
 * cadenas de reconstrucciones (issue #11), el debounce de la marca de sucio, qué cambios de
 * metadatos ensucian el índice, y los ficheros con sus tamaños.
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Indexer;
use Convoca\Core\Logger;
use PHPUnit\Framework\TestCase;

class IndexerEstadoTest extends TestCase
{
    /** Ruta del índice compartido por el arnés. */
    private string $indice;

    protected function setUp(): void
    {
        $GLOBALS['_assistant_transients'] = [];
        $GLOBALS['_assistant_cron'] = [];
        $GLOBALS['convoca_as_gancho_actual'] = '';
        $GLOBALS['convoca_as_eventos'] = [];
        Logger::$escritos = [];

        if (!is_dir(CONVOCA_ASSISTANT_INDEX_DIR)) {
            mkdir(CONVOCA_ASSISTANT_INDEX_DIR, 0777, true);
        }
        $this->indice = CONVOCA_ASSISTANT_INDEX_DIR . 'index.json';
        if (file_exists($this->indice)) {
            unlink($this->indice);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->indice)) {
            unlink($this->indice);
        }
        // Se vacían, NO se borran: los dobles del arnés guardan una referencia al global y
        // hacer unset() la deja en null (rompía IndexerLegacyTest).
        $GLOBALS['_assistant_transients'] = [];
        $GLOBALS['_assistant_cron'] = [];
        $GLOBALS['convoca_as_gancho_actual'] = '';
        $GLOBALS['convoca_as_eventos'] = [];
    }

    /**
     * Escribe una transitoria.
     *
     * @param string $clave Clave.
     * @param mixed  $valor Valor.
     */
    private function transitoria(string $clave, $valor): void
    {
        set_transient($clave, $valor);
    }

    // ── la marca de sucio ───────────────────────────────────────────────────────────

    public function test_sin_marca_el_indice_no_esta_sucio(): void
    {
        $this->assertFalse(Indexer::is_dirty());
    }

    public function test_marcar_sucio_apunta_quien_y_programa_la_reconstruccion(): void
    {
        $GLOBALS['convoca_as_gancho_actual'] = 'save_post';

        Indexer::mark_dirty(42);

        $this->assertTrue(Indexer::is_dirty());
        $this->assertSame('save_post #42', get_transient('convoca_assistant_index_dirty_reason'), 'sin saber quién lo ensució no se puede explicar una cadena de reconstrucciones');
        $this->assertArrayHasKey('convoca_assistant_regenerate_now', $GLOBALS['_assistant_cron'], 'la reconstrucción se programa al guardar');
    }

    public function test_un_objeto_como_motivo_deja_su_id(): void
    {
        Indexer::mark_dirty((object) ['ID' => 7]);

        $this->assertStringEndsWith('#7', (string) get_transient('convoca_assistant_index_dirty_reason'));
    }

    public function test_dos_marcas_seguidas_no_repiten_el_trabajo(): void
    {
        $this->transitoria('convoca_assistant_index_debounce', time());

        Indexer::mark_dirty();

        $this->assertFalse(Indexer::is_dirty(), 'el debounce evita marcar sucio en cada guardado de un bucle');
        $this->assertArrayNotHasKey('convoca_assistant_regenerate_now', $GLOBALS['_assistant_cron'] ?? []);
    }

    // ── el tope de ritmo ────────────────────────────────────────────────────────────

    public function test_si_no_esta_sucio_y_hay_indice_no_se_reconstruye(): void
    {
        file_put_contents($this->indice, str_repeat('x', 100));

        $resultado = Indexer::maybe_regenerate();

        $this->assertTrue($resultado['skipped']);
        $this->assertSame('Index is up to date.', $resultado['message']);
    }

    public function test_sin_indice_se_reconstruye_aunque_no_este_sucio(): void
    {
        // Sin fichero, el índice no existe: hay que construirlo aunque no haya marca de sucio.
        $resultado = Indexer::maybe_regenerate();

        $this->assertArrayHasKey('success', $resultado, 'sin índice no se puede decir «al día»');
    }

    public function test_una_reconstruccion_reciente_aplaza_la_siguiente(): void
    {
        $this->transitoria('convoca_assistant_index_dirty', time());
        $this->transitoria('convoca_assistant_index_last_rebuild', time() - 5);
        $this->transitoria('convoca_assistant_index_dirty_reason', 'save_post #9');

        $resultado = Indexer::maybe_regenerate();

        $this->assertTrue($resultado['skipped'], 'sin este tope se encadenaban reconstrucciones completas');
        $this->assertStringContainsString('aplazada', $resultado['message']);
        $this->assertStringContainsString('save_post #9', $resultado['message'], 'y dice qué la ensució');
        $this->assertFalse(file_exists($this->indice), 'aplazada quiere decir que no se ha construido nada');
    }

    // ── los cambios de metadatos ────────────────────────────────────────────────────

    public function test_los_metadatos_del_asistente_ensucian_el_indice(): void
    {
        foreach (['_convoca_assistant_keywords', '_convoca_assistant_weight', '_convoca_assistant_exclude'] as $clave) {
            $GLOBALS['_assistant_transients'] = [];
            Indexer::on_meta_change(1, 2, $clave, 'x');
            $this->assertTrue(Indexer::is_dirty(), $clave . ' cambia lo que el buscador ve');
        }
    }

    public function test_otro_metadato_no_ensucia_nada(): void
    {
        Indexer::on_meta_change(1, 2, '_edit_last', 'x');

        $this->assertFalse(Indexer::is_dirty(), 'marcar sucio por cualquier metadato reconstruiría el índice sin motivo');
    }

    // ── los cron ────────────────────────────────────────────────────────────────────

    public function test_el_intervalo_de_cinco_minutos_se_declara(): void
    {
        $horarios = Indexer::add_cron_interval([]);

        $this->assertSame(300, $horarios['every_5_minutes']['interval']);
        $this->assertSame('Cada 5 minutos', $horarios['every_5_minutes']['display']);
    }

    public function test_si_ya_hay_una_reconstruccion_pendiente_no_se_programa_otra(): void
    {
        $GLOBALS['_assistant_cron']['convoca_assistant_regenerate_now'] = time();

        $this->assertFalse(Indexer::schedule_regenerate_now(), 'ya había una pendiente: no se duplica');
    }

    public function test_el_cron_de_respaldo_se_autorrepara(): void
    {
        $this->assertTrue(Indexer::schedule_backup_cron(), 'si el evento se perdió, se vuelve a registrar');
        $this->assertArrayHasKey('convoca_assistant_regenerate', $GLOBALS['_assistant_cron']);

        // Marca vieja y distinta: si se reprogramara, el valor cambiaría a «ahora» y se vería.
        $GLOBALS['_assistant_cron']['convoca_assistant_regenerate'] = time() - 5000;

        $this->assertTrue(Indexer::schedule_backup_cron(), 'y si ya estaba, sigue diciendo que está');
        $this->assertSame(
            time() - 5000,
            $GLOBALS['_assistant_cron']['convoca_assistant_regenerate'],
            'sin reprogramar: un cron que ya existe no se duplica'
        );
    }

    // ── los ficheros ────────────────────────────────────────────────────────────────

    public function test_sin_fichero_el_indice_no_existe(): void
    {
        $this->assertFalse(Indexer::index_exists());
    }

    public function test_un_fichero_demasiado_pequeño_no_cuenta_como_indice(): void
    {
        file_put_contents($this->indice, '{}');

        $this->assertFalse(Indexer::index_exists(), 'un índice de dos bytes está roto, no vacío');
    }

    public function test_un_indice_de_verdad_si_existe(): void
    {
        file_put_contents($this->indice, json_encode(['entries' => array_fill(0, 5, ['id' => 1])]));

        $this->assertTrue(Indexer::index_exists());
        $this->assertNotSame('', Indexer::get_index_url(), 'el front necesita la URL del índice');
        $this->assertStringContainsString('index.json', Indexer::get_index_path());
    }

    public function test_el_tamaño_del_indice_no_se_inventa(): void
    {
        file_put_contents($this->indice, str_repeat('x', 2048));

        $this->assertNotSame('', Indexer::get_index_size());
    }

    public function test_las_cifras_dicen_cuantas_entradas_hay(): void
    {
        // Un índice de verdad ocupa más de 50 bytes: por debajo, index_exists() lo considera roto.
        file_put_contents($this->indice, json_encode(['entries' => [
            ['id' => 1, 'title' => 'Cómo hacerse socio', 'content' => 'Con el formulario de la web'],
            ['id' => 2, 'title' => 'Horario del centro', 'content' => 'De lunes a viernes por la tarde'],
        ]]));

        $cifras = Indexer::get_stats();

        $this->assertTrue($cifras['exists']);
        $this->assertSame(2, $cifras['total'], 'el total sale del fichero, no de una opción');
    }

    public function test_borrar_los_ficheros_deja_el_indice_fuera(): void
    {
        file_put_contents($this->indice, json_encode(['entries' => [['id' => 1]]]));

        Indexer::clear_index_files();

        $this->assertFalse(Indexer::index_exists());
    }
}

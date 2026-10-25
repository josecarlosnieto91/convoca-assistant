<?php

/**
 * Las tres pantallas de consulta del panel: Dashboard, Sin respuesta y Analytics.
 *
 * Estaban al 0 % (41 + 10 + 15 sentencias). El Dashboard es el que tiene lógica de verdad:
 * decide entre «Generado» / «No generado», avisa de «pendiente de regenerar» y pinta entradas,
 * tamaño, fecha y hash leídos del índice. Una pantalla de estado que miente es peor que no
 * tenerla (se dio por bueno un índice inexistente), así que se prueban las dos ramas.
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Admin;
use PHPUnit\Framework\TestCase;

class PlantillasConsultasTest extends TestCase
{
	/** Fichero de índice de la suite. */
	private const INDICE = CONVOCA_ASSISTANT_INDEX_DIR . 'index.json';

	/**
	 * Estado limpio: sin índice en disco y sin transients.
	 */
	protected function setUp(): void
	{
		$GLOBALS['_assistant_options'] = [];
		$GLOBALS['_assistant_transients'] = [];
		if (!is_dir(CONVOCA_ASSISTANT_INDEX_DIR)) {
			mkdir(CONVOCA_ASSISTANT_INDEX_DIR, 0777, true);
		}
		if (file_exists(self::INDICE)) {
			unlink(self::INDICE);
		}
	}

	/**
	 * Recoge el índice de prueba.
	 */
	protected function tearDown(): void
	{
		if (file_exists(self::INDICE)) {
			unlink(self::INDICE);
		}
		$GLOBALS['_assistant_options'] = [];
		$GLOBALS['_assistant_transients'] = [];
	}

	/**
	 * Escribe un índice con esa lista de entradas.
	 *
	 * @param array<int, array<string, mixed>> $entradas Entradas.
	 */
	private function escribir_indice(array $entradas): void
	{
		// Con el contenido que llevaría de verdad (título y texto): el índice real pasa de
		// 50 bytes y `index_exists()` exige eso, así que un JSON de juguete no vale.
		file_put_contents(self::INDICE, (string) wp_json_encode([
			'version'   => '0.2.22',
			'generated' => '2026-10-24 10:00:00',
			'entries'   => array_map(
				static fn(array $entrada): array => $entrada + [
					'title'   => 'Pregunta de prueba sobre el asistente',
					'content' => 'Contenido de la entrada que se indexa para el buscador del asistente.',
				],
				$entradas
			),
		]));
	}

	/**
	 * Pinta una plantilla y devuelve la salida.
	 *
	 * @param callable $render Render.
	 * @return string
	 */
	private function pintar(callable $render): string
	{
		ob_start();
		$render();

		return (string) ob_get_clean();
	}

	// ── Dashboard: estado del índice ────────────────────────────────────────────────

	public function test_sin_indice_el_dashboard_dice_que_no_esta_generado(): void
	{
		$html = $this->pintar([Admin::class, 'render_dashboard']);

		$this->assertStringContainsString('● No generado', $html, 'sin fichero de índice el panel no puede decir «generado»');
		$this->assertStringNotContainsString('● Generado', $html);
		$this->assertStringNotContainsString('pendiente de regenerar', $html, 'no hay índice: no hay nada pendiente de regenerar');
	}

	public function test_con_indice_el_dashboard_dice_generado_y_cuenta_las_entradas(): void
	{
		$this->escribir_indice([['id' => 1], ['id' => 2], ['id' => 3]]);
		$GLOBALS['_assistant_options']['convoca_assistant_index_hash'] = 'abcdef1234567890';
		$GLOBALS['_assistant_options']['convoca_assistant_index_generated'] = time() - 300;

		$html = $this->pintar([Admin::class, 'render_dashboard']);

		$this->assertStringContainsString('● Generado', $html);
		$this->assertStringNotContainsString('● No generado', $html);
		$this->assertMatchesRegularExpression('/<td>\s*3\s*<\/td>/', $html, 'las entradas del índice son las que hay en el fichero');
		$this->assertStringContainsString('abcdef1234567890', $html, 'el hash es lo que permite saber si el índice es el de esta versión del contenido');
		$this->assertStringContainsString('Hace 5 minutos', $html, 'la última generación se cuenta desde su marca de tiempo');
	}

	public function test_el_dashboard_avisa_de_un_indice_pendiente_de_regenerar(): void
	{
		$this->escribir_indice([['id' => 1]]);
		set_transient('convoca_assistant_index_dirty', time(), HOUR_IN_SECONDS);

		$html = $this->pintar([Admin::class, 'render_dashboard']);

		$this->assertStringContainsString('● Generado', $html);
		$this->assertStringContainsString('(pendiente de regenerar)', $html, 'un índice viejo sin avisar hace que el chat responda con contenido que ya no está');
	}

	public function test_un_indice_demasiado_pequeno_cuenta_como_inexistente(): void
	{
		// index_exists() exige más de 50 bytes: un fichero truncado por un fallo al escribir no
		// puede pasar por bueno (el JS se lo descargaría y se quedaría sin nada).
		file_put_contents(self::INDICE, '{"entries":[]}');

		$html = $this->pintar([Admin::class, 'render_dashboard']);

		$this->assertStringContainsString('● No generado', $html, 'un índice de menos de 50 bytes no es un índice');
	}

	public function test_sin_generacion_previa_la_fecha_es_nunca(): void
	{
		$html = $this->pintar([Admin::class, 'render_dashboard']);

		$this->assertStringContainsString('Nunca', $html, 'sin marca de generación, «Nunca» es la verdad');
	}

	public function test_sin_indice_el_tamano_es_un_guion(): void
	{
		$html = $this->pintar([Admin::class, 'render_dashboard']);

		$this->assertStringContainsString('>—<', $html, 'sin fichero no hay tamaño que enseñar');
	}

	public function test_el_dashboard_ofrece_regenerar_el_indice(): void
	{
		$html = $this->pintar([Admin::class, 'render_dashboard']);

		$this->assertStringContainsString('id="convoca-rebuild-index"', $html, 'el JS del panel busca ese id');
		$this->assertStringContainsString('Regenerar índice ahora', $html);
		$this->assertStringContainsString('id="convoca-stat-total"', $html, 'las tarjetas que rellena el JS');
		$this->assertStringContainsString('id="convoca-chart-daily"', $html);
		$this->assertStringContainsString('id="convoca-top-queries"', $html);
	}

	// ── Sin respuesta ───────────────────────────────────────────────────────────────

	public function test_la_pantalla_sin_respuesta_tiene_las_cuatro_columnas(): void
	{
		$html = $this->pintar([Admin::class, 'render_unanswered']);

		$this->assertStringContainsString('id="convoca-unanswered-table"', $html, 'el JS vuelca las filas ahí');
		$this->assertStringContainsString('id="convoca-unanswered-rows"', $html);
		$this->assertStringContainsString('colspan="4"', $html, 'cuatro columnas: consulta, veces, última vez y acción');
		foreach (['Consulta', 'Veces', 'Última vez', 'Acción'] as $columna) {
			$this->assertStringContainsString($columna, $html);
		}
	}

	// ── Analytics ───────────────────────────────────────────────────────────────────

	public function test_analytics_declara_las_seis_columnas_de_la_tabla(): void
	{
		$html = $this->pintar([Admin::class, 'render_analytics']);

		$this->assertStringContainsString('id="convoca-analytics-table"', $html);
		$this->assertStringContainsString('id="convoca-analytics-rows"', $html);
		$this->assertStringContainsString('id="convoca-analytics-summary"', $html);
		$this->assertStringContainsString('colspan="6"', $html, 'la fila de «Cargando…» tiene que cubrir la tabla entera');
		foreach (['Consulta', 'Score', 'Fuente', 'Click', 'Tiempo', 'Fecha'] as $columna) {
			$this->assertStringContainsString($columna, $html);
		}
	}
}

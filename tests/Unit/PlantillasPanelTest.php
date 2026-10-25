<?php

/**
 * Las plantillas del panel que pintan formularios: Ajustes y Herramientas.
 *
 * Las dos estaban al 0 % (109 + 54 sentencias). No son HTML decorativo: son la única vía por
 * la que un ajuste guardado llega al formulario, y lo hacen con `checked()`, `selected()`,
 * `esc_attr()` y `esc_textarea()`. Un fallo ahí no rompe nada visible —el panel sigue
 * cargando— pero deja la casilla desmarcada o el valor sin escapar, y eso se cuela en
 * producción sin que nadie lo note. Cada prueba de aquí fija una de esas salidas.
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Admin;
use PHPUnit\Framework\TestCase;

class PlantillasPanelTest extends TestCase
{
	/**
	 * Estado limpio entre pruebas y constantes que las plantillas esperan.
	 */
	protected function setUp(): void
	{
		$GLOBALS['_assistant_options'] = [];
		$GLOBALS['convoca_as_borrados'] = [];
		unset($_GET['imported']);
	}

	/**
	 * Estado limpio al terminar.
	 */
	protected function tearDown(): void
	{
		$GLOBALS['_assistant_options'] = [];
		unset($_GET['imported'], $GLOBALS['convoca_as_borrados']);
	}

	/**
	 * Fija ajustes guardados (se mezclan sobre los de fábrica).
	 *
	 * @param array<string, mixed> $valores Ajustes.
	 */
	private function ajustes(array $valores): void
	{
		$GLOBALS['_assistant_options']['convoca_assistant_settings'] = $valores;
	}

	/**
	 * Pinta una plantilla del panel y devuelve su salida.
	 *
	 * @param callable $render Render de la plantilla.
	 * @return string
	 */
	private function pintar(callable $render): string
	{
		ob_start();
		$render();

		return (string) ob_get_clean();
	}

	/**
	 * Devuelve la etiqueta <input> cuyo atributo name es el indicado.
	 *
	 * @param string $html   Salida.
	 * @param string $nombre Nombre del campo.
	 * @return string
	 */
	private function campo(string $html, string $nombre): string
	{
		$patron = '/<input\b[^>]*name="' . preg_quote($nombre, '/') . '"[^>]*>/i';
		if (!preg_match($patron, $html, $m)) {
			$this->fail('No hay ningún input con name="' . $nombre . '".');
		}

		return $m[0];
	}

	/**
	 * Devuelve la etiqueta <option> con ese value.
	 *
	 * @param string $html  Salida.
	 * @param string $valor Valor.
	 * @return string
	 */
	private function opcion(string $html, string $valor): string
	{
		$patron = '/<option\b[^>]*value="' . preg_quote($valor, '/') . '"[^>]*>/i';
		if (!preg_match($patron, $html, $m)) {
			$this->fail('No hay ninguna opción con value="' . $valor . '".');
		}

		return $m[0];
	}

	// ── Ajustes: el formulario llega entero ─────────────────────────────────────────

	public function test_los_ajustes_se_pintan_como_un_formulario_del_panel(): void
	{
		$html = $this->pintar([Admin::class, 'render_settings']);

		$this->assertStringContainsString('<form method="post" action="options.php">', $html, 'sin este action el navegador no manda nada al panel');
		$this->assertStringContainsString("name='option_page' value='convoca_assistant'", $html, 'el grupo de ajustes tiene que ser el que registra Settings::register_settings');
		$this->assertStringContainsString('name="action" value="update"', $html, 'sin action=update options.php ignora el envío');
		$this->assertStringContainsString('value="Guardar ajustes"', $html, 'el botón tiene que existir y decir qué hace');
		foreach (['widget_enabled', 'search_mode', 'log_retention_days', 'maintenance_message'] as $campo) {
			$this->assertStringContainsString('convoca_assistant_settings[' . $campo . ']', $html, 'falta el campo ' . $campo . ': se guardaría sin él');
		}
		$this->assertStringContainsString('convoca_assistant_settings[priority_types][]', $html, 'los tipos prioritarios son un array de casillas');
	}

	// ── Ajustes: las casillas siguen al valor guardado ──────────────────────────────

	public function test_una_casilla_marcada_guarda_el_cero_y_otra_el_uno(): void
	{
		$this->ajustes([
			'widget_enabled'   => false,
			'search_fallback'  => true,
			'maintenance_mode' => false,
		]);

		$html = $this->pintar([Admin::class, 'render_settings']);

		$this->assertStringNotContainsString("checked='checked'", $this->campo($html, 'convoca_assistant_settings[widget_enabled]'), 'widget_enabled está a false: la casilla no puede salir marcada');
		$this->assertStringContainsString("checked='checked'", $this->campo($html, 'convoca_assistant_settings[search_fallback]'), 'search_fallback está a true: la casilla tiene que salir marcada');
		$this->assertStringNotContainsString("checked='checked'", $this->campo($html, 'convoca_assistant_settings[maintenance_mode]'), 'maintenance_mode está a false');
	}

	public function test_el_valor_cero_no_marca_la_casilla(): void
	{
		// 0 y false son el mismo «no» para checked(): si la plantilla usara `isset`, un 0
		// guardado saldría marcado y el ajuste se leería al revés.
		$this->ajustes(['log_enabled' => 0, 'log_anonymous' => 1]);

		$html = $this->pintar([Admin::class, 'render_settings']);

		$this->assertStringNotContainsString("checked='checked'", $this->campo($html, 'convoca_assistant_settings[log_enabled]'));
		$this->assertStringContainsString("checked='checked'", $this->campo($html, 'convoca_assistant_settings[log_anonymous]'));
	}

	// ── Ajustes: los desplegables ───────────────────────────────────────────────────

	public function test_los_desplegables_marcan_la_opcion_guardada(): void
	{
		$this->ajustes([
			'widget_position' => 'bottom-left',
			'search_mode'     => 'both',
			'widget_auto_open' => 'scroll',
			'search_engine'   => 'fusion',
		]);

		$html = $this->pintar([Admin::class, 'render_settings']);

		$this->assertStringContainsString("selected='selected'", $this->opcion($html, 'bottom-left'), 'la posición guardada es la que tiene que verse');
		$this->assertStringNotContainsString("selected='selected'", $this->opcion($html, 'bottom-right'), 'y la otra, no');
		$this->assertStringContainsString("selected='selected'", $this->opcion($html, 'both'));
		$this->assertStringNotContainsString("selected='selected'", $this->opcion($html, 'client'));
		$this->assertStringContainsString("selected='selected'", $this->opcion($html, 'scroll'));
		$this->assertStringContainsString("selected='selected'", $this->opcion($html, 'fusion'), 'el motor de fusión tiene que poder elegirse');
		$this->assertStringNotContainsString("selected='selected'", $this->opcion($html, 'composite'));

		// La vuelta: con el otro valor guardado, la marca cambia de sitio. Sin esto, una
		// plantilla que marcara siempre la misma opción pasaría la prueba de arriba.
		$this->ajustes(['widget_position' => 'bottom-right']);
		$inverso = $this->pintar([Admin::class, 'render_settings']);

		$this->assertStringContainsString("selected='selected'", $this->opcion($inverso, 'bottom-right'));
		$this->assertStringNotContainsString("selected='selected'", $this->opcion($inverso, 'bottom-left'));
	}

	public function test_sin_motor_guardado_el_compuesto_es_el_que_aparece(): void
	{
		// `search_engine` no está en los ajustes de una instalación vieja: el `?? 'composite'`
		// de la plantilla es lo que evita que el desplegable salga sin ninguna opción marcada.
		$this->ajustes(['widget_title' => 'Asistente']);

		$html = $this->pintar([Admin::class, 'render_settings']);

		$this->assertStringContainsString("selected='selected'", $this->opcion($html, 'composite'), 'sin valor guardado, el motor de siempre es el que se ofrece');
	}

	// ── Ajustes: los números y el texto guardado ────────────────────────────────────

	public function test_los_campos_numericos_llevan_el_valor_guardado(): void
	{
		$this->ajustes([
			'search_max_results' => 25,
			'direct_threshold'   => 0.65,
			'priority_boost'     => 1.5,
			'log_retention_days' => 30,
			'answer_max_length'  => 800,
		]);

		$html = $this->pintar([Admin::class, 'render_settings']);

		$this->assertStringContainsString('value="25"', $this->campo($html, 'convoca_assistant_settings[search_max_results]'));
		$this->assertStringContainsString('value="0.65"', $this->campo($html, 'convoca_assistant_settings[direct_threshold]'), 'el umbral de respuesta directa se afina a mano: tiene que llegar con sus decimales');
		$this->assertStringContainsString('value="1.5"', $this->campo($html, 'convoca_assistant_settings[priority_boost]'));
		$this->assertStringContainsString('value="30"', $this->campo($html, 'convoca_assistant_settings[log_retention_days]'));
		$this->assertStringContainsString('value="800"', $this->campo($html, 'convoca_assistant_settings[answer_max_length]'));
	}

	public function test_lo_guardado_se_escapa_al_pintarlo(): void
	{
		$this->ajustes([
			'widget_title'         => 'Asistente "Pro" <script>alert(1)</script>',
			'maintenance_message'  => 'Volvemos <b>pronto</b>',
			'widget_greeting'      => 'Hola & adiós',
		]);

		$html = $this->pintar([Admin::class, 'render_settings']);

		$this->assertStringNotContainsString('<script>', $html, 'un título guardado no puede inyectar etiquetas en el panel');
		$this->assertStringContainsString('&lt;script&gt;', $html, 'tiene que salir escapado, no borrado');
		$this->assertStringContainsString('&quot;Pro&quot;', $html, 'las comillas también: rompen el atributo value');
		$this->assertStringNotContainsString('<b>pronto</b>', $html);
	}

	public function test_el_saludo_no_se_recodifica(): void
	{
		// esc_textarea() del núcleo va con double_encode = false: un «&amp;» guardado se pinta
		// tal cual. Si alguien lo cambia por esc_html(), el usuario ve «&amp;amp;» en el área
		// de texto y al guardar se le duplica la entidad.
		$this->ajustes(['widget_greeting' => 'Precio &amp; plazas']);

		$html = $this->pintar([Admin::class, 'render_settings']);

		$this->assertStringContainsString('Precio &amp; plazas', $html, 'la entidad guardada no se vuelve a codificar');
		$this->assertStringNotContainsString('&amp;amp;', $html, 'recodificarla la estropea');
	}

	// ── Ajustes: tipos prioritarios (array de casillas) ─────────────────────────────

	public function test_los_tipos_prioritarios_marcan_solo_los_guardados(): void
	{
		$this->ajustes(['priority_types' => ['page']]);

		$html = $this->pintar([Admin::class, 'render_settings']);

		preg_match_all('/<input\b[^>]*name="convoca_assistant_settings\[priority_types\]\[\]"[^>]*>/i', $html, $m);
		$this->assertCount(4, $m[0], 'las cuatro fuentes tienen que poder ser prioritarias');

		$marcadas = array_values(array_filter($m[0], static fn($tag) => str_contains($tag, "checked='checked'")));
		$this->assertCount(1, $marcadas, 'solo la guardada puede salir marcada');
		$this->assertStringContainsString('value="page"', $marcadas[0]);
	}

	public function test_sin_tipos_prioritarios_no_se_marca_ninguno(): void
	{
		$this->ajustes(['priority_types' => []]);

		$html = $this->pintar([Admin::class, 'render_settings']);

		preg_match_all('/<input\b[^>]*name="convoca_assistant_settings\[priority_types\]\[\]"[^>]*>/i', $html, $m);
		foreach ($m[0] as $tag) {
			$this->assertStringNotContainsString("checked='checked'", $tag, 'una lista vacía no marca nada');
		}
	}

	public function test_los_datos_de_contacto_vacios_salen_como_campo_vacio(): void
	{
		$this->ajustes(['contact_email' => '', 'contact_phone' => '', 'contact_whatsapp' => '']);

		$html = $this->pintar([Admin::class, 'render_settings']);

		$this->assertStringContainsString('value=""', $this->campo($html, 'convoca_assistant_settings[contact_email]'), 'un email vacío no puede dejar el atributo a medias');
		$this->assertStringContainsString('value=""', $this->campo($html, 'convoca_assistant_settings[contact_whatsapp]'));
	}

	// ── Herramientas: el aviso de importación ───────────────────────────────────────

	public function test_herramientas_sin_importacion_no_avisa(): void
	{
		$html = $this->pintar([Admin::class, 'render_tools']);

		$this->assertStringNotContainsString('Importación completada.', $html, 'nadie ha importado nada: el aviso sería mentira');
		$this->assertStringNotContainsString('notice-success', $html);
	}

	public function test_herramientas_avisa_cuando_la_importacion_ha_terminado(): void
	{
		$_GET['imported'] = '1';

		$html = $this->pintar([Admin::class, 'render_tools']);

		$this->assertStringContainsString('notice notice-success', $html, 'sin el aviso, una importación terminada se queda sin confirmar');
		$this->assertStringContainsString('Importación completada.', $html);
	}

	// ── Herramientas: los dos formularios y sus nonces ──────────────────────────────

	public function test_herramientas_lleva_los_dos_formularios_con_su_nonce(): void
	{
		$html = $this->pintar([Admin::class, 'render_tools']);

		$this->assertSame(2, substr_count($html, 'action="https://example.test/wp-admin/admin-post.php"'), 'exportar e importar van a admin-post.php');
		$this->assertStringContainsString('name="action" value="convoca_assistant_export"', $html);
		$this->assertStringContainsString('name="action" value="convoca_assistant_import"', $html);
		$this->assertStringContainsString('accept=".json"', $html, 'el importador solo acepta JSON');
		$this->assertStringContainsString('enctype="multipart/form-data"', $html, 'sin esto el fichero no viaja');
		$this->assertStringContainsString('name="_wpnonce"', $html, 'sin nonce el panel rechaza el envío');
		$this->assertGreaterThanOrEqual(2, substr_count($html, 'name="_wpnonce"'), 'cada formulario necesita el suyo');
	}

	public function test_el_modo_debug_de_herramientas_sigue_al_ajuste(): void
	{
		$this->ajustes(['debug_mode' => true]);
		$con_debug = $this->pintar([Admin::class, 'render_tools']);

		$this->ajustes(['debug_mode' => false]);
		$sin_debug = $this->pintar([Admin::class, 'render_tools']);

		$this->assertStringContainsString("checked='checked'", $this->campo($con_debug, 'convoca_assistant_settings[debug_mode]'));
		$this->assertStringNotContainsString("checked='checked'", $this->campo($sin_debug, 'convoca_assistant_settings[debug_mode]'));
	}

	public function test_herramientas_ofrece_regenerar_y_limpiar(): void
	{
		$html = $this->pintar([Admin::class, 'render_tools']);

		$this->assertStringContainsString('id="convoca-rebuild-index-tools"', $html, 'el JS busca ese id');
		$this->assertStringContainsString('id="convoca-clear-logs"', $html);
		$this->assertStringContainsString('id="convoca-debug-query"', $html, 'la caja de probar consultas');
	}
}

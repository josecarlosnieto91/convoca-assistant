<?php

/**
 * Las plantillas de Conocimiento y Sinónimos.
 *
 * Estaban al 0 % (50 + 61 sentencias). Las dos deciden con datos que vienen de fuera: los
 * proveedores registrados y los sinónimos guardados. Lo que se fija aquí:
 *
 *  · un proveedor NO disponible sale deshabilitado y con su aviso — si se pintara como los
 *    demás, se marcaría una fuente que no puede indexar nada;
 *  · un proveedor sin peso guardado cae al peso por defecto en vez de pintar «0»;
 *  · el término de un sinónimo y sus palabras se escapan: el término viaja en un `value=`
 *    de un formulario de borrado, que es el sitio clásico por donde se rompe el HTML.
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Admin;
use Convoca\Assistant\Provider_Registry;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class PlantillasConocimientoTest extends TestCase
{
	/**
	 * Estado limpio y proveedores sin cachear.
	 */
	protected function setUp(): void
	{
		$this->limpiar();
	}

	/**
	 * Estado limpio al terminar.
	 */
	protected function tearDown(): void
	{
		$this->limpiar();
	}

	/**
	 * Deja opciones, globals y la caché de proveedores como recién arrancados.
	 */
	private function limpiar(): void
	{
		$GLOBALS['_assistant_options']       = [];
		$GLOBALS['convoca_as_tipos_registrados'] = ['post', 'page'];
		unset($_GET['settings-updated'], $_GET['saved'], $_GET['removed']);
		$this->olvidar_proveedores();
	}

	/**
	 * Vacía la caché estática de Provider_Registry (es privada: no hay reset público).
	 */
	private function olvidar_proveedores(): void
	{
		$prop = new ReflectionProperty(Provider_Registry::class, 'providers');
		$prop->setValue(null, null);
	}

	/**
	 * Fija ajustes guardados.
	 *
	 * @param array<string, mixed> $valores Ajustes.
	 */
	private function ajustes(array $valores): void
	{
		$GLOBALS['_assistant_options']['convoca_assistant_settings'] = $valores;
	}

	/**
	 * Pinta una plantilla del panel.
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

	/**
	 * Etiqueta <input> con ese name.
	 *
	 * @param string $html   Salida.
	 * @param string $nombre Nombre.
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

	// ── Conocimiento: fuentes no disponibles ────────────────────────────────────────

	public function test_un_proveedor_no_disponible_sale_deshabilitado_y_avisado(): void
	{
		// Sin los CPT del plugin registrados (y sin WooCommerce), tres proveedores no pueden
		// indexar: su casilla tiene que salir disabled o el panel ofrecería marcar el vacío.
		$html = $this->pintar([Admin::class, 'render_knowledge']);

		$faq = $this->campo($html, 'convoca_assistant_settings[source_convoca_faq]');
		$this->assertStringContainsString("disabled='disabled'", $faq, 'la FAQ no está registrada: no se puede marcar');
		$this->assertStringContainsString('(no disponible)', $html);

		$paginas = $this->campo($html, 'convoca_assistant_settings[source_page]');
		$this->assertStringNotContainsString('disabled', $paginas, 'las páginas sí existen: su casilla se puede marcar');
	}

	public function test_con_los_tipos_registrados_todos_los_proveedores_se_pueden_marcar(): void
	{
		$GLOBALS['convoca_as_tipos_registrados'] = ['post', 'page', 'convoca_faq', 'convoca_kb'];
		$this->olvidar_proveedores();

		$html = $this->pintar([Admin::class, 'render_knowledge']);

		$this->assertStringNotContainsString("disabled='disabled'", $this->campo($html, 'convoca_assistant_settings[source_convoca_kb]'), 'con la KB registrada su casilla se puede marcar');
		$this->assertStringNotContainsString("disabled='disabled'", $this->campo($html, 'convoca_assistant_settings[source_convoca_faq]'));
		// El de WooCommerce sigue fuera: aquí no hay clase WooCommerce, y esa es la única
		// fuente que debe quedar avisada.
		$this->assertSame(1, substr_count($html, '(no disponible)'), 'solo el proveedor de WooCommerce sigue sin poder indexar');
		$this->assertStringContainsString("disabled='disabled'", $this->campo($html, 'convoca_assistant_settings[source_woocommerce]'));
	}

	// ── Conocimiento: las casillas de fuente ────────────────────────────────────────

	public function test_las_casillas_de_fuente_siguen_al_ajuste(): void
	{
		$this->ajustes(['source_page' => false, 'source_post' => true]);

		$html = $this->pintar([Admin::class, 'render_knowledge']);

		$this->assertStringNotContainsString("checked='checked'", $this->campo($html, 'convoca_assistant_settings[source_page]'));
		$this->assertStringContainsString("checked='checked'", $this->campo($html, 'convoca_assistant_settings[source_post]'));
	}

	// ── Conocimiento: la tabla de pesos ─────────────────────────────────────────────

	public function test_solo_los_proveedores_disponibles_tienen_fila_de_peso(): void
	{
		$html = $this->pintar([Admin::class, 'render_knowledge']);

		$this->assertStringContainsString('convoca_assistant_settings[weight_post]', $html);
		$this->assertStringContainsString('convoca_assistant_settings[weight_page]', $html);
		$this->assertStringNotContainsString('convoca_assistant_settings[weight_convoca_kb]', $html, 'un tipo que no se puede indexar no tiene peso que ajustar');
	}

	public function test_el_peso_guardado_es_el_que_se_pinta(): void
	{
		$this->ajustes(['weight_page' => 3.5]);

		$html = $this->pintar([Admin::class, 'render_knowledge']);

		$this->assertStringContainsString('value="3.5"', $this->campo($html, 'convoca_assistant_settings[weight_page]'), 'el peso guardado tiene que verse');
	}

	public function test_sin_peso_guardado_se_pinta_el_de_defecto_del_proveedor(): void
	{
		// Un peso a null (migración a medias) no puede pintar value="": el campo saldría vacío
		// y al guardar se perdería el peso que el proveedor trae de fábrica.
		$this->ajustes(['weight_page' => null]);

		$html = $this->pintar([Admin::class, 'render_knowledge']);

		$this->assertStringContainsString('value="1"', $this->campo($html, 'convoca_assistant_settings[weight_page]'), 'páginas pesa 1.0 por defecto');
	}

	// ── Conocimiento: el aviso y los accesos a los listados ─────────────────────────

	public function test_el_aviso_de_guardado_solo_aparece_cuando_toca(): void
	{
		$this->assertStringNotContainsString('Ajustes guardados.', $this->pintar([Admin::class, 'render_knowledge']), 'sin el parámetro no hay nada guardado que anunciar');

		$_GET['settings-updated'] = 'true';
		$this->assertStringContainsString('Ajustes guardados.', $this->pintar([Admin::class, 'render_knowledge']), 'el panel tiene que confirmar el guardado');
	}

	public function test_accede_a_los_cuatro_listados(): void
	{
		$html = $this->pintar([Admin::class, 'render_knowledge']);

		foreach (['edit.php?post_type=convoca_faq', 'edit.php?post_type=convoca_kb', 'edit.php?post_type=page'] as $destino) {
			$this->assertStringContainsString('https://example.test/wp-admin/' . $destino, $html, 'falta el acceso a ' . $destino);
		}
	}

	public function test_el_formulario_de_conocimiento_guarda_en_el_grupo_de_ajustes(): void
	{
		$html = $this->pintar([Admin::class, 'render_knowledge']);

		$this->assertStringContainsString("name='option_page' value='convoca_assistant'", $html);
		$this->assertStringContainsString('value="Guardar cambios"', $html);
	}

	// ── Sinónimos: sin datos ────────────────────────────────────────────────────────

	public function test_sin_sinonimos_lo_dice_en_vez_de_pintar_una_tabla_vacia(): void
	{
		$html = $this->pintar([Admin::class, 'render_synonyms']);

		$this->assertStringContainsString('No hay sinónimos definidos.', $html);
		$this->assertStringNotContainsString('wp-list-table', $html, 'una tabla sin filas solo añade ruido');
	}

	// ── Sinónimos: con datos ────────────────────────────────────────────────────────

	public function test_los_sinonimos_guardados_salen_en_la_tabla(): void
	{
		$GLOBALS['_assistant_options']['convoca_assistant_synonyms'] = [
			'ordenador' => ['computadora', 'pc'],
			'socio'     => ['miembro'],
		];

		$html = $this->pintar([Admin::class, 'render_synonyms']);

		$this->assertStringContainsString('wp-list-table', $html);
		$this->assertStringContainsString('<strong>ordenador</strong>', $html);
		$this->assertStringContainsString('computadora, pc', $html, 'los sinónimos de un término van juntos y separados por coma');
		$this->assertStringContainsString('<strong>socio</strong>', $html);
		$this->assertSame(2, substr_count($html, 'convoca_assistant_synonym_remove'), 'cada fila necesita su formulario de borrado');
	}

	public function test_el_termino_y_sus_palabras_se_escapan(): void
	{
		$GLOBALS['_assistant_options']['convoca_assistant_synonyms'] = [
			'" onclick="alert(1)' => ['<script>x</script>'],
		];

		$html = $this->pintar([Admin::class, 'render_synonyms']);

		$this->assertStringNotContainsString('<script>x</script>', $html, 'un sinónimo guardado no puede inyectar etiquetas');
		$this->assertStringContainsString('&lt;script&gt;', $html);
		$this->assertStringNotContainsString('value="" onclick=', $html, 'el término viaja en un value=: si no se escapa, rompe el atributo');
		$this->assertStringContainsString('&quot; onclick=&quot;alert(1)', $html);
	}

	public function test_los_avisos_de_sinonimos_dependen_de_su_parametro(): void
	{
		$sin = $this->pintar([Admin::class, 'render_synonyms']);
		$this->assertStringNotContainsString('Sinónimos guardados correctamente.', $sin);
		$this->assertStringNotContainsString('Sinónimo eliminado.', $sin);

		$_GET['saved'] = '1';
		$guardado = $this->pintar([Admin::class, 'render_synonyms']);
		$this->assertStringContainsString('Sinónimos guardados correctamente.', $guardado);

		unset($_GET['saved']);
		$_GET['removed'] = '1';
		$borrado = $this->pintar([Admin::class, 'render_synonyms']);
		$this->assertStringContainsString('Sinónimo eliminado.', $borrado);
	}

	public function test_el_formulario_de_alta_pide_termino_y_palabras(): void
	{
		$html = $this->pintar([Admin::class, 'render_synonyms']);

		$this->assertStringContainsString('name="action" value="convoca_assistant_synonym_add"', $html);
		$this->assertStringContainsString('name="term" required', $html, 'sin término no hay sinónimo que guardar');
		$this->assertStringContainsString('name="synonyms"', $html);
		$this->assertStringContainsString('Un sinónimo por línea.', $html);
	}

	// ── Sinónimos: stop words ───────────────────────────────────────────────────────

	public function test_las_stop_words_salen_una_por_linea(): void
	{
		$GLOBALS['_assistant_options']['convoca_assistant_stop_words'] = ['a', 'de', 'que'];

		$html = $this->pintar([Admin::class, 'render_synonyms']);

		if (!preg_match('/<textarea\b[^>]*name="stop_words"[^>]*>(.*?)<\/textarea>/s', $html, $m)) {
			$this->fail('No hay textarea de stop words.');
		}

		$this->assertSame("a\nde\nque", $m[1], 'el textarea se edita a mano: una palabra por línea, sin comas ni corchetes');
	}

	public function test_las_stop_words_no_se_recodifican_al_pintarlas(): void
	{
		// esc_textarea() va con double_encode = false: una entidad YA escrita («&amp;») se deja
		// como está —no se convierte en «&amp;amp;»—, mientras que un `<` suelto sí se escapa.
		$GLOBALS['_assistant_options']['convoca_assistant_stop_words'] = ['a &amp; b', '<x>'];

		$html = $this->pintar([Admin::class, 'render_synonyms']);

		$this->assertStringContainsString("a &amp; b\n&lt;x&gt;", $html);
		$this->assertStringNotContainsString('&amp;amp;', $html, 'recodificar la entidad la estropea al guardar');
	}
}

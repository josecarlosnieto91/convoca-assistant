<?php

/**
 * El HTML del widget flotante (`assets/templates/widget-html.php`, 19 sentencias al 0 %).
 *
 * Es la única plantilla que se pinta en el front de cualquier visita: su posición, su color y
 * su saludo salen de ajustes que escribe el dueño del sitio, y viajan a atributos HTML. Un
 * saludo con comillas mal escapadas no rompe la página, solo el widget — y nadie lo ve hasta
 * que un visitante pregunta. Aquí se fija lo que se pinta y cómo se escapa, por las dos vías:
 * la plantilla directamente (sus ramas) y `Widget::render_floating_widget()` (la pública).
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Widget;
use PHPUnit\Framework\TestCase;

class WidgetHtmlTest extends TestCase
{
	/**
	 * Estado limpio entre pruebas.
	 */
	protected function setUp(): void
	{
		$GLOBALS['_assistant_options'] = [];
	}

	/**
	 * Estado limpio al terminar.
	 */
	protected function tearDown(): void
	{
		$GLOBALS['_assistant_options'] = [];
	}

	/**
	 * Pinta la plantilla con las variables que le pasa el widget.
	 *
	 * @param string               $position Posición.
	 * @param string               $color    Color primario.
	 * @param string               $title    Título.
	 * @param array<string, mixed> $settings Ajustes disponibles en la plantilla.
	 * @return string
	 */
	private function plantilla(string $position, string $color, string $title, array $settings): string
	{
		ob_start();
		include CONVOCA_ASSISTANT_DIR . 'assets/templates/widget-html.php';

		return (string) ob_get_clean();
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

	// ── la plantilla: posición, color y título ──────────────────────────────────────

	public function test_la_plantilla_lleva_posicion_color_y_titulo(): void
	{
		$html = $this->plantilla('bottom-left', '#ff8700', 'Pregúntame', ['widget_greeting' => 'Hola']);

		$this->assertStringContainsString('id="convoca-assistant-widget"', $html, 'el JS busca ese id para montar el chat');
		$this->assertStringContainsString('data-position="bottom-left"', $html, 'la posición la aplica el CSS desde este atributo');
		$this->assertStringContainsString('--convoca-primary: #ff8700;', $html, 'el color del panel se pasa como variable CSS');
		$this->assertStringContainsString('Pregúntame', $html, 'el título aparece en la cabecera del chat');
		$this->assertStringContainsString('aria-label="Pregúntame"', $html, 'y también etiqueta el diálogo');
		$this->assertStringContainsString('Hola', $html, 'el saludo es el primer mensaje del bot');
	}

	public function test_el_titulo_y_el_saludo_se_escapan(): void
	{
		$html = $this->plantilla('bottom-right', '#2563eb', 'Ayu<da> "ya"', ['widget_greeting' => 'Hola <b>tú</b>']);

		$this->assertStringNotContainsString('<b>tú</b>', $html, 'el saludo es texto, no HTML: si se cuela, el sitio del cliente pinta lo que le manden');
		$this->assertStringContainsString('Hola &lt;b&gt;tú&lt;/b&gt;', $html);

		// El título se escapa en DOS sitios (el atributo del diálogo y la cabecera visible): se fijan
		// los dos, porque asegurar solo uno deja el otro libre para que alguien lo «simplifique».
		$this->assertMatchesRegularExpression('/class="convoca-chat-title">([^<]*)</', $html);
		preg_match('/class="convoca-chat-title">([^<]*)</', $html, $cabecera);
		$this->assertSame('Ayu&lt;da&gt; &quot;ya&quot;', $cabecera[1], 'la cabecera visible también va escapada');
		// Se mira el ATRIBUTO, no el texto de la página: el título se escapa en dos sitios y un
		// assert sobre «contiene la cadena escapada» pasaba aunque el aria-label quedara sin escapar.
		// Hay dos aria-label (el botón flotante y el diálogo): se mira el DEL DIÁLOGO, que es el que
		// lleva el título. Con un assert genérico sobre «contiene la cadena escapada», el span de la
		// cabecera satisfacía la prueba aunque el atributo del diálogo quedara sin escapar.
		$this->assertMatchesRegularExpression('/id="convoca-chat-container"[^>]*aria-label="([^"]*)"/s', $html);
		preg_match('/id="convoca-chat-container"[^>]*aria-label="([^"]*)"/s', $html, $etiqueta);
		$this->assertSame(
			'Ayu&lt;da&gt; &quot;ya&quot;',
			$etiqueta[1],
			'el título va en un aria-label: sin escapar, rompe el atributo'
		);
	}

	public function test_sin_saludo_guardado_se_usa_el_de_fabrica(): void
	{
		$html = $this->plantilla('bottom-right', '#2563eb', 'Asistente Virtual', []);

		$this->assertStringContainsString('¡Hola! ¿En qué puedo ayudarte?', $html, 'un ajuste sin saludo no puede dejar el chat con el primer mensaje vacío');
	}

	public function test_un_saludo_vacio_si_se_respeta(): void
	{
		// `??` y no `empty()`: si el dueño borra el saludo a propósito, se queda en blanco.
		$html = $this->plantilla('bottom-right', '#2563eb', 'Asistente Virtual', ['widget_greeting' => '']);

		$this->assertStringNotContainsString('¡Hola! ¿En qué puedo ayudarte?', $html, 'borrar el saludo es una decisión, no un descuido');
	}

	public function test_los_controles_del_chat_estan_etiquetados(): void
	{
		$html = $this->plantilla('bottom-right', '#2563eb', 'Asistente Virtual', ['widget_greeting' => 'Hola']);

		foreach (['Abrir asistente virtual', 'Cerrar', 'Pregunta', 'Enviar'] as $etiqueta) {
			$this->assertStringContainsString('aria-label="' . $etiqueta . '"', $html, 'sin etiqueta, el widget es inusable con lector de pantalla');
		}
		$this->assertStringContainsString('role="dialog"', $html);
		$this->assertStringContainsString('aria-live="polite"', $html, 'los mensajes nuevos se anuncian: es un chat');
		$this->assertStringContainsString('id="convoca-chat-input"', $html);
		$this->assertStringContainsString('id="convoca-chat-send"', $html);
	}

	public function test_el_dialogo_arranca_cerrado(): void
	{
		$html = $this->plantilla('bottom-right', '#2563eb', 'Asistente Virtual', ['widget_greeting' => 'Hola']);

		$this->assertStringContainsString('aria-expanded="false"', $html, 'el botón empieza cerrado');
		$this->assertStringContainsString('aria-hidden="true"', $html, 'y el diálogo también');
	}

	// ── la vía pública: Widget::render_floating_widget() ────────────────────────────

	public function test_el_widget_flotante_se_pinta_una_sola_vez_por_peticion(): void
	{
		$this->ajustes([
			'widget_enabled'       => 1,
			'maintenance_mode'     => 0,
			'widget_position'      => 'bottom-left',
			'widget_primary_color' => '#112233',
			'widget_title'         => 'Asistente del centro',
			'widget_greeting'      => '¿En qué te ayudo?',
		]);

		ob_start();
		Widget::render_floating_widget();
		$primera = (string) ob_get_clean();

		ob_start();
		Widget::render_floating_widget();
		$segunda = (string) ob_get_clean();

		$this->assertStringContainsString('data-position="bottom-left"', $primera, 'la posición guardada llega al HTML');
		$this->assertStringContainsString('--convoca-primary: #112233;', $primera);
		$this->assertStringContainsString('¿En qué te ayudo?', $primera);
		$this->assertSame('', $segunda, 'dos botones flotantes en la misma página son un widget duplicado');
	}
}

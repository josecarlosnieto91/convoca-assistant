<?php

/**
 * El arranque del plugin (`convoca-assistant.php`, 56 sentencias al 0 %).
 *
 * Es el fichero que WordPress carga en CADA petición, antes que nada más. Lo que decide:
 * de dónde sale la versión con la que se cachean los assets, qué se engancha en
 * `plugins_loaded`, y que en una petición del front no se cargue nada del panel. Dos fallos
 * clásicos se vigilan aquí:
 *
 *  · la versión clavada a mano (pasó: doce releases sirviendo `?ver=0.2.13`), que deja CDN y
 *    navegadores con el JS viejo — por eso se deriva de la cabecera y se comprueba contra ella;
 *  · que `Admin`, `Settings`, `Synonyms` y `Export_Import` se enganchen solo en el panel: si
 *    se cargan en el front, cada visita paga el panel entero.
 *
 * El fichero se carga UNA vez por proceso (declara `Convoca\Assistant\load_plugin_textdomain()`:
 * un segundo `require` sería un fatal de redeclaración), y las pruebas vuelven a ejecutar el
 * callback de `plugins_loaded` las veces que haga falta.
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use PHPUnit\Framework\TestCase;

class ArranquePluginTest extends TestCase
{
	/** Ruta del fichero de arranque. */
	private const ARRANQUE = CONVOCA_ASSISTANT_DIR . 'convoca-assistant.php';

	/** ¿Está ya cargado en este proceso? */
	private static bool $cargado = false;

	/** Callback de `plugins_loaded` que engancha el fichero de arranque. */
	private static $arranque;

	/** Ganchos de activación apuntados al cargar. */
	private static array $activacion = [];

	/** Ganchos de desactivación apuntados al cargar. */
	private static array $desactivacion = [];

	/**
	 * Limpia lo que apuntan los dobles y recarga el arranque la primera vez.
	 */
	protected function setUp(): void
	{
		$GLOBALS['convoca_as_acciones'] = [];
		$GLOBALS['convoca_as_acciones_lista'] = [];
		$GLOBALS['convoca_as_activacion'] = [];
		$GLOBALS['convoca_as_desactivacion'] = [];
		$GLOBALS['convoca_as_textdomain'] = [];
		$GLOBALS['convoca_as_es_admin'] = false;

		if (!self::$cargado) {
			require_once self::ARRANQUE;
			self::$cargado     = true;
			self::$arranque    = $GLOBALS['convoca_as_acciones']['plugins_loaded'] ?? null;
			self::$activacion  = $GLOBALS['convoca_as_activacion'];
			self::$desactivacion = $GLOBALS['convoca_as_desactivacion'];
		}

		// El fichero se carga una sola vez: lo que apuntó al cargar se vuelve a poner en su
		// sitio, porque cada prueba empieza con los dobles limpios.
		$GLOBALS['convoca_as_acciones']['plugins_loaded'] = self::$arranque;
		$GLOBALS['convoca_as_activacion']                 = self::$activacion;
		$GLOBALS['convoca_as_desactivacion']              = self::$desactivacion;
	}

	/**
	 * Deja los dobles como estaban.
	 */
	protected function tearDown(): void
	{
		unset(
			$GLOBALS['convoca_as_acciones'],
			$GLOBALS['convoca_as_acciones_lista'],
			$GLOBALS['convoca_as_activacion'],
			$GLOBALS['convoca_as_desactivacion'],
			$GLOBALS['convoca_as_textdomain'],
			$GLOBALS['convoca_as_es_admin']
		);
	}

	/**
	 * Ejecuta el callback que el plugin engancha a `plugins_loaded`.
	 *
	 * @param bool $es_admin Si la petición es del panel.
	 * @return void
	 */
	private function arrancar(bool $es_admin): void
	{
		$GLOBALS['convoca_as_es_admin'] = $es_admin;
		$GLOBALS['convoca_as_acciones_lista'] = [];

		$callback = $GLOBALS['convoca_as_acciones']['plugins_loaded'] ?? null;
		$this->assertIsCallable($callback, 'el plugin tiene que engancharse a plugins_loaded');

		$callback();
	}

	/**
	 * Lista de ganchos enganchados, sin repetir.
	 *
	 * @return string[]
	 */
	private function ganchos(): array
	{
		return array_keys($GLOBALS['convoca_as_acciones_lista']);
	}

	// ── constantes ──────────────────────────────────────────────────────────────────

	public function test_la_version_sale_de_la_cabecera_del_plugin(): void
	{
		// Se mide en un proceso limpio a propósito: `tests/Unit/ExportImportTest.php` define
		// CONVOCA_ASSISTANT_VERSION a nivel de fichero, así que en esta suite la constante ya
		// existe antes de cargar este fichero y el bloque de derivación no llegaría a correr.
		// En un proceso limpio se ve lo que pasa en producción: el plugin lee su cabecera.
		$fuente = (string) file_get_contents(self::ARRANQUE);
		$this->assertSame(1, preg_match('/^\s*\*\s*Version:\s*(\S+)/mi', $fuente, $m), 'el fichero tiene que declarar su versión');
		$version_cabecera = $m[1];

		$script = sys_get_temp_dir() . '/convoca-version-arranque.php';
		file_put_contents(
			$script,
			'<?php' . "\n"
			. 'require ' . var_export(dirname(__DIR__) . '/bootstrap.php', true) . ';' . "\n"
			. 'require ' . var_export(self::ARRANQUE, true) . ';' . "\n"
			. 'echo CONVOCA_ASSISTANT_VERSION;' . "\n"
		);

		exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1', $lineas, $codigo);
		unlink($script);

		$this->assertSame(0, $codigo, 'el proceso limpio tiene que terminar bien: ' . implode("\n", $lineas));
		$version = trim(implode("\n", $lineas));
		$this->assertSame($version_cabecera, $version, 'CONVOCA_ASSISTANT_VERSION tiene que seguir a la cabecera: es el cache-buster de los assets');
		$this->assertNotSame('0.2.16', $version, '0.2.16 es el valor de reserva: llegar ahí significa que la cabecera no se leyó');
	}

	public function test_las_rutas_del_plugin_estan_bien_formadas(): void
	{
		$this->assertSame(dirname(self::ARRANQUE) . '/', CONVOCA_ASSISTANT_DIR, 'el directorio lleva barra final: todas las rutas internas se concatenan sin más');
		$this->assertSame(realpath(self::ARRANQUE), CONVOCA_ASSISTANT_FILE, 'el fichero principal es el que usan los ganchos de activación');
		$this->assertStringEndsWith('/', CONVOCA_ASSISTANT_URL);
		$this->assertSame(CONVOCA_ASSISTANT_URL . 'assets/', CONVOCA_ASSISTANT_ASSETS_URL, 'los assets cuelgan del plugin, no de una URL fija');
		$this->assertSame('1.0.0', CONVOCA_ASSISTANT_DB_VERSION, 'la versión de esquema la lee Installer::maybe_upgrade');
	}

	// ── plugins_loaded: el front no carga el panel ──────────────────────────────────

	public function test_en_el_front_solo_se_arranca_lo_publico(): void
	{
		$this->arrancar(false);

		$ganchos = $this->ganchos();
		$this->assertContains('wp_enqueue_scripts', $ganchos, 'el widget del front tiene que encolarse');
		$this->assertContains('wp_footer', $ganchos, 'y pintarse en el pie');
		$this->assertContains('rest_api_init', $ganchos, 'el endpoint del chat es del front');
		$this->assertContains('init', $ganchos, 'el dominio de traducciones se carga en init');

		foreach (['admin_menu', 'admin_enqueue_scripts', 'admin_notices', 'admin_post_convoca_assistant_import'] as $solo_panel) {
			$this->assertNotContains($solo_panel, $ganchos, 'en el front no se engancha ' . $solo_panel . ': el panel entero se pagaría en cada visita');
		}
	}

	public function test_en_el_panel_se_arranca_tambien_la_parte_de_administracion(): void
	{
		$this->arrancar(true);

		$ganchos = $this->ganchos();
		$this->assertContains('admin_menu', $ganchos, 'sin menú no se llega al asistente');
		$this->assertContains('admin_enqueue_scripts', $ganchos);
		$this->assertContains('admin_notices', $ganchos, 'el aviso de «sin respuesta» sale del panel');
		$this->assertContains('admin_post_convoca_assistant_import', $ganchos, 'y el importador también');
	}

	public function test_el_panel_engancha_dos_veces_admin_init(): void
	{
		// Settings::init registra los ajustes y Installer::maybe_upgrade las migraciones: los
		// dos van a admin_init. Con un arnés que solo guarde el último callback, el segundo
		// desaparecería sin que nadie lo notara — que es exactamente lo que hacía el doble.
		$this->arrancar(true);

		$en_admin_init = $GLOBALS['convoca_as_acciones_lista']['admin_init'] ?? [];
		$this->assertCount(2, $en_admin_init, 'admin_init tiene que llevar los ajustes Y la migración de esquema');
	}

	public function test_el_textdomain_se_carga_en_init(): void
	{
		$this->arrancar(false);

		$this->assertTrue(
			function_exists('Convoca\\Assistant\\load_plugin_textdomain'),
			'la función vive en el namespace del plugin (es un callback, no un método)'
		);

		call_user_func($GLOBALS['convoca_as_acciones_lista']['init'][0]);

		$this->assertCount(1, $GLOBALS['convoca_as_textdomain'], 'el dominio se carga una vez');
		$this->assertSame('convoca-assistant', $GLOBALS['convoca_as_textdomain'][0][0]);
		$this->assertSame('convoca-assistant/languages', $GLOBALS['convoca_as_textdomain'][0][2], 'la ruta cuelga del plugin: si no, WordPress busca las traducciones donde no están');
	}

	// ── ganchos de activación y desactivación ──────────────────────────────────────

	public function test_registra_activacion_y_desactivacion_sobre_su_propio_fichero(): void
	{
		$this->assertArrayHasKey(CONVOCA_ASSISTANT_FILE, $GLOBALS['convoca_as_activacion'], 'sin gancho de activación no se crea la tabla de logs ni el paquete de arranque');
		$this->assertArrayHasKey(CONVOCA_ASSISTANT_FILE, $GLOBALS['convoca_as_desactivacion'], 'y sin el de desactivación se quedan las reglas de reescritura apuntando al vacío');
		$this->assertIsCallable($GLOBALS['convoca_as_activacion'][CONVOCA_ASSISTANT_FILE]);
		$this->assertIsCallable($GLOBALS['convoca_as_desactivacion'][CONVOCA_ASSISTANT_FILE]);
	}
}

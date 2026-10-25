<?php

/**
 * El desinstalador (`uninstall.php`, 34 sentencias al 0 %).
 *
 * Es el fichero que decide qué queda en la base de datos de un cliente después de pulsar
 * «Desinstalar». Dos cosas se pueden romper aquí y ninguna se ve en el panel: que deje basura
 * (opciones, transients, cron y una tabla propia que ya no usa nadie) o que se lleve por delante
 * los datos de alguien que solo quería desactivar el plugin un rato — para eso está
 * `convoca_uninstall_keep_data`.
 *
 * Se prueba por efecto observable: lo que se borra, lo que se limpia y lo que NO se toca.
 * La rama del borrado de la carpeta del índice (`WP_Filesystem`) no se puede recorrer desde
 * unitario: `uninstall.php` exige `ABSPATH . 'wp-admin/includes/file.php'`, que aquí no existe.
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use PHPUnit\Framework\TestCase;

class DesinstalacionTest extends TestCase
{
	/**
	 * Opciones que el desinstalador tiene que borrar.
	 */
	private const OPCIONES = [
		'convoca_assistant_version',
		'convoca_assistant_db_version',
		'convoca_assistant_settings',
		'convoca_assistant_synonyms',
		'convoca_assistant_stop_words',
		'convoca_assistant_index_hash',
		'convoca_assistant_maintenance',
		'convoca_assistant_index_schema',
		'convoca_assistant_index_generated',
	];

	/**
	 * Prepara un WordPress «con datos» y sin carpeta de índice en disco.
	 */
	protected function setUp(): void
	{
		if (!defined('WP_UNINSTALL_PLUGIN')) {
			define('WP_UNINSTALL_PLUGIN', 'convoca-assistant/convoca-assistant.php');
		}

		$GLOBALS['_assistant_options'] = [];
		foreach (self::OPCIONES as $opcion) {
			$GLOBALS['_assistant_options'][$opcion] = 'algo';
		}
		$GLOBALS['_assistant_options']['convoca_assistant_index_dirty'] = 1;
		// Ajustes que NO son del plugin: si el desinstalador barre de más, se ve aquí.
		$GLOBALS['_assistant_options']['blogname'] = 'Asociación';
		$GLOBALS['_assistant_options']['active_plugins'] = ['convoca-assistant/convoca-assistant.php'];

		$GLOBALS['_assistant_transients'] = [
			'convoca_assistant_index_dirty'              => time(),
			'convoca_assistant_index_debounce'           => time(),
			'otro_plugin_transient_que_no_es_nuestro'    => 'x',
		];
		$GLOBALS['_assistant_cron'] = [
			'convoca_assistant_regenerate'      => time(),
			'convoca_assistant_regenerate_now'  => time(),
			'convoca_assistant_log_cleanup'     => time(),
		];
		$GLOBALS['convoca_as_borrados'] = [];
		$GLOBALS['convoca_as_cron_limpiados'] = [];
		$GLOBALS['wpdb']->consultas = [];
		$GLOBALS['convoca_as_upload_base'] = sys_get_temp_dir() . '/convoca-sin-indice';
	}

	/**
	 * Devuelve el estado a como estaba.
	 */
	protected function tearDown(): void
	{
		$GLOBALS['_assistant_options'] = [];
		$GLOBALS['_assistant_transients'] = [];
		$GLOBALS['_assistant_cron'] = [];
		unset(
			$GLOBALS['convoca_as_borrados'],
			$GLOBALS['convoca_as_cron_limpiados'],
			$GLOBALS['convoca_as_upload_base']
		);
		$GLOBALS['wpdb']->consultas = [];
	}

	/**
	 * Ejecuta el desinstalador (el fichero de producto) y devuelve su salida.
	 *
	 * @return string
	 */
	private function desinstalar(): string
	{
		ob_start();
		include dirname(__DIR__, 2) . '/uninstall.php';

		return (string) ob_get_clean();
	}

	// ── el borrado completo ─────────────────────────────────────────────────────────

	public function test_borra_las_opciones_del_plugin(): void
	{
		$this->desinstalar();

		foreach (self::OPCIONES as $opcion) {
			$this->assertArrayNotHasKey($opcion, $GLOBALS['_assistant_options'], 'la opción ' . $opcion . ' se queda huérfana al desinstalar');
			$this->assertContains($opcion, $GLOBALS['convoca_as_borrados']);
		}
	}

	public function test_no_toca_las_opciones_de_otros(): void
	{
		$this->desinstalar();

		$this->assertArrayHasKey('blogname', $GLOBALS['_assistant_options'], 'el nombre del sitio no es del plugin');
		$this->assertArrayHasKey('active_plugins', $GLOBALS['_assistant_options'], 'y menos la lista de plugins activos: borrarla deja el sitio sin plugins');
	}

	public function test_limpia_sus_transients_y_solo_los_suyos(): void
	{
		$this->desinstalar();

		$this->assertArrayNotHasKey('convoca_assistant_index_dirty', $GLOBALS['_assistant_transients'], 'el aviso de índice sucio no puede sobrevivir al plugin');
		$this->assertArrayNotHasKey('convoca_assistant_index_debounce', $GLOBALS['_assistant_transients']);
		$this->assertArrayHasKey('otro_plugin_transient_que_no_es_nuestro', $GLOBALS['_assistant_transients'], 'los transients de otros plugins no se tocan');
	}

	public function test_vacia_sus_tareas_programadas(): void
	{
		$this->desinstalar();

		$this->assertSame(
			[
				'convoca_assistant_regenerate',
				'convoca_assistant_regenerate_now',
				'convoca_assistant_log_cleanup',
			],
			$GLOBALS['convoca_as_cron_limpiados'],
			'un cron que sobrevive al plugin intenta ejecutar código que ya no está'
		);
		$this->assertSame([], $GLOBALS['_assistant_cron'], 'y no puede quedar ningún evento apuntado');
	}

	public function test_se_lleva_su_tabla_de_registro(): void
	{
		$this->desinstalar();

		$consultas = $GLOBALS['wpdb']->consultas;
		$this->assertCount(1, $consultas, 'una sola consulta: la de borrar la tabla');
		$this->assertStringContainsString('DROP TABLE IF EXISTS wp_convoca_assistant_log', $consultas[0], 'la tabla de logs es del plugin y no la usa nadie más');
	}

	public function test_sin_carpeta_de_indice_no_intenta_borrarla(): void
	{
		// No hay `convoca-assistant/` en subidas: el desinstalador no puede requerir
		// wp-admin/includes/file.php (que en unitario no existe) ni llamar a WP_Filesystem.
		$this->assertFalse(is_dir($GLOBALS['convoca_as_upload_base'] . '/convoca-assistant/'), 'precondición: no hay carpeta de índice');

		$this->assertSame('', $this->desinstalar(), 'sin carpeta no debe pintar nada');
	}

	// ── el modo conservar datos ─────────────────────────────────────────────────────

	public function test_con_conservar_datos_no_borra_nada(): void
	{
		$GLOBALS['_assistant_options']['convoca_uninstall_keep_data'] = 1;

		$this->desinstalar();

		foreach (self::OPCIONES as $opcion) {
			$this->assertArrayHasKey($opcion, $GLOBALS['_assistant_options'], 'con «conservar datos» marcado, ' . $opcion . ' tiene que seguir ahí');
		}
		$this->assertSame([], $GLOBALS['convoca_as_borrados'], 'no se borra ni una opción');
		$this->assertSame([], $GLOBALS['convoca_as_cron_limpiados'], 'ni se vacía el cron');
		$this->assertSame([], $GLOBALS['wpdb']->consultas, 'ni se toca la tabla de logs');
	}

	public function test_con_conservar_datos_a_cero_si_borra(): void
	{
		// El ajuste existe, pero a 0: es la vía de escape, no el estado por defecto.
		$GLOBALS['_assistant_options']['convoca_uninstall_keep_data'] = 0;

		$this->desinstalar();

		$this->assertContains('convoca_assistant_settings', $GLOBALS['convoca_as_borrados']);
	}

	// ── la guarda de WordPress ──────────────────────────────────────────────────────

	public function test_sin_la_constante_de_wordpress_no_borra_nada(): void
	{
		// WP_UNINSTALL_PLUGIN la define WordPress al desinstalar. Sin ella, incluir el fichero
		// (un `require` suelto, un escaneo) no puede vaciar la base de datos: el `exit` de la
		// guarda tiene que cortar ANTES. Se comprueba en un proceso aparte porque el `exit`
		// no se puede interceptar aquí.
		$script = sys_get_temp_dir() . '/convoca-guarda-uninstall.php';
		file_put_contents(
			$script,
			'<?php' . "\n"
			. 'require ' . var_export(dirname(__DIR__) . '/bootstrap.php', true) . ';' . "\n"
			. 'if ( getenv( "CONVOCA_DEFINIR_CONSTANTE" ) ) { define( "WP_UNINSTALL_PLUGIN", "convoca-assistant/convoca-assistant.php" ); }' . "\n"
			. '$GLOBALS["_assistant_options"]["convoca_assistant_settings"] = "algo";' . "\n"
			. 'register_shutdown_function( static function () { echo "BORRADOS:" . count( $GLOBALS["convoca_as_borrados"] ?? [] ); } );' . "\n"
			. 'include ' . var_export(dirname(__DIR__, 2) . '/uninstall.php', true) . ';' . "\n"
		);
		$this->assertFileExists($script);

		$sin = $this->ejecutar($script, null);
		$this->assertStringContainsString('BORRADOS:0', $sin, 'sin WP_UNINSTALL_PLUGIN no se borra nada');

		$con = $this->ejecutar($script, '1');
		$this->assertStringContainsString('BORRADOS:9', $con, 'con la constante, las nueve opciones: así se sabe que la guarda es la que corta');

		unlink($script);
	}

	/**
	 * Ejecuta un script de PHP en un proceso aparte.
	 *
	 * @param string      $script   Ruta del script.
	 * @param string|null $constante Valor de CONVOCA_DEFINIR_CONSTANTE.
	 * @return string Salida.
	 */
	private function ejecutar(string $script, ?string $constante): string
	{
		$entorno = $constante === null ? 'env -u CONVOCA_DEFINIR_CONSTANTE' : 'env CONVOCA_DEFINIR_CONSTANTE=1';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		exec($entorno . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1', $lineas, $codigo);

		$this->assertSame(0, $codigo, 'el proceso hijo debe terminar bien: ' . implode("\n", $lineas));

		return implode("\n", $lineas);
	}
}

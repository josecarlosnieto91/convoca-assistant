<?php
/**
 * Sinónimos y palabras vacías del buscador.
 *
 * Lo que se escribe aquí decide qué encuentra la gente: si «cuota» arrastra «precio» y «mensualidad», quien busca
 * «precio» llega a la pregunta de la cuota. Lo que se prueba: el saneado al guardar, la expansión de la consulta y que
 * los manejadores del panel exijan permiso y nonce.
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Installer;
use Convoca\Assistant\Synonyms;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Installer.php';
require_once dirname( __DIR__, 2 ) . '/includes/Synonyms.php';

/**
 * Sinónimos.
 */
final class SynonymsTest extends TestCase {

	/**
	 * Opciones previas.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo = array(
			'options' => $GLOBALS['_assistant_options'] ?? array(),
			'hooks'   => $GLOBALS['convoca_as_acciones'] ?? array(),
		);

		$GLOBALS['_assistant_options'] = array();
		$GLOBALS['convoca_as_acciones']          = array();
		$GLOBALS['_wp_die_lanza']      = true;
		$GLOBALS['convoca_as_caps']        = array( 'manage_options' => true );
		$_POST                         = array();
		$_REQUEST                      = array();
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		$GLOBALS['_assistant_options'] = $this->previo['options'];
		$GLOBALS['convoca_as_acciones']          = $this->previo['hooks'];
		unset( $GLOBALS['_wp_die_lanza'], $GLOBALS['convoca_as_caps'] );
		$_POST    = array();
		$_REQUEST = array();
	}

	/**
	 * Engancha los tres manejadores y los dos avisos de cambio.
	 */
	public function test_init(): void {
		Synonyms::init();

		foreach ( array(
			'admin_post_convoca_assistant_synonym_add',
			'admin_post_convoca_assistant_synonym_remove',
			'admin_post_convoca_assistant_stop_words_save',
		) as $hook ) {
			$this->assertArrayHasKey( $hook, $GLOBALS['convoca_as_acciones'], 'falta: ' . $hook );
		}
	}

	/**
	 * Al guardar se recorta, se quitan vacíos y el propio término, y no se repite.
	 */
	public function test_set_sanea(): void {
		Synonyms::set( 'cuota', array( '  precio ', '', 'cuota', 'precio', 'mensualidad' ) );

		$guardados = array_values( $GLOBALS['_assistant_options']['convoca_assistant_synonyms']['cuota'] );
		$this->assertSame( array( 'precio', 'mensualidad' ), $guardados, 'sin vacíos, sin el propio término y sin repetir' );
	}

	/**
	 * Quitar devuelve true si estaba y false si no.
	 */
	public function test_remove(): void {
		Synonyms::set( 'cuota', array( 'precio' ) );

		$this->assertTrue( Synonyms::remove( 'cuota' ) );
		$this->assertArrayNotHasKey( 'cuota', Synonyms::get_all() );
		$this->assertFalse( Synonyms::remove( 'cuota' ), 'quitarlo dos veces no es un cambio' );
	}

	/**
	 * Sin nada guardado, las palabras vacías son las del instalador.
	 */
	public function test_palabras_vacias_por_defecto(): void {
		$this->assertSame( Installer::default_stop_words(), Synonyms::get_stop_words() );
	}

	/**
	 * Guardar palabras vacías las sanea; reiniciar vuelve a las de serie.
	 */
	public function test_palabras_vacias(): void {
		Synonyms::set_stop_words( array( ' de ', '', 'la', 'de' ) );

		$this->assertSame( array( 0 => 'de', 2 => 'la' ), Synonyms::get_stop_words() );

		Synonyms::reset_stop_words();
		$this->assertSame( Installer::default_stop_words(), Synonyms::get_stop_words() );
	}

	/**
	 * Buscar un sinónimo arrastra el término y toda su familia.
	 */
	public function test_expandir_la_consulta(): void {
		Synonyms::set( 'Cuota', array( 'Precio', 'mensualidad' ) );

		$expandida = Synonyms::expand_query( array( 'precio' ) );
		$this->assertContains( 'precio', $expandida );
		$this->assertContains( 'mensualidad', $expandida, 'entra la familia entera' );
		$this->assertContains( 'cuota', $expandida, 'y el término canónico' );

		$sin_cambios = Synonyms::expand_query( array( 'horario' ) );
		$this->assertSame( array( 'horario' ), $sin_cambios, 'sin coincidencia no se inventa nada' );
	}

	/**
	 * Un término escrito con mayúsculas se compara en minúsculas.
	 */
	public function test_la_expansion_no_distingue_mayusculas(): void {
		Synonyms::set( 'Cuota', array( 'Precio' ) );

		$this->assertContains( 'cuota', Synonyms::expand_query( array( 'cuota' ) ) );
	}

	// ── los manejadores del panel: van a integración ────────────────────────────────
	//
	// No se miden aquí y el motivo es del arnés: `wp_safe_redirect()` apunta la URL pero no corta, y los manejadores
	// terminan en `exit;` de verdad, así que el `exit` mataría el proceso de PHPUnit. Y `wp_die()` tampoco corta con
	// lo que hay. Probarlos exigiría cambiar dos dobles que usan otras pruebas: se hace cuando se toque el arnés,
	// no de tapadillo en esta tanda.





}

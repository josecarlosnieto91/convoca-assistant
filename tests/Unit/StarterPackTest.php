<?php
/**
 * El paquete de arranque de preguntas frecuentes.
 *
 * Se instala una vez y **no puede duplicar**: si alguien pulsa dos veces, o si la mitad ya estaba puesta, solo se crean
 * las que faltan. Y no puede pisar los sinónimos que la asociación ya haya escrito a mano. Eso es lo que se prueba.
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Starter_Pack;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Starter_Pack.php';

/**
 * Paquete de arranque.
 */
final class StarterPackTest extends TestCase {

	/**
	 * Almacenes previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo = array(
			'meta'       => $GLOBALS['_assistant_meta'] ?? array(),
			'posts'      => $GLOBALS['convoca_as_posts'] ?? array(),
			'insertados' => $GLOBALS['convoca_as_insertados'] ?? array(),
			'options'    => $GLOBALS['_assistant_options'] ?? array(),
		);

		$GLOBALS['_assistant_meta']        = array();
		$GLOBALS['convoca_as_posts']       = array();
		$GLOBALS['convoca_as_insertados']  = array();
		$GLOBALS['_assistant_options']  = array();
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		$GLOBALS['_assistant_meta']       = $this->previo['meta'];
		$GLOBALS['convoca_as_posts']      = $this->previo['posts'];
		$GLOBALS['convoca_as_insertados'] = $this->previo['insertados'];
		$GLOBALS['_assistant_options'] = $this->previo['options'];
	}

	/**
	 * Siembra una FAQ marcada como del paquete.
	 *
	 * @param int    $id     Identificador.
	 * @param string $titulo Título.
	 * @param bool   $marca  Lleva la marca del paquete.
	 * @return void
	 */
	private function faq( int $id, string $titulo, bool $marca = true ): void {
		$post              = new \stdClass();
		$post->ID          = $id;
		$post->post_type   = 'convoca_faq';
		$post->post_status = 'publish';
		$post->post_title  = $titulo;

		$GLOBALS['convoca_as_posts']['convoca_faq'][] = $post;
		if ( $marca ) {
			$GLOBALS['_assistant_meta'][ $id ][ Starter_Pack::META_MARCA ] = '1';
		}
	}

	/**
	 * Seis preguntas, cada una con sus claves y su respuesta.
	 */
	public function test_el_contenido(): void {
		$piezas = Starter_Pack::contenido();

		$this->assertCount( 6, $piezas );
		foreach ( $piezas as $pieza ) {
			$this->assertNotSame( '', $pieza['titulo'] );
			$this->assertNotSame( '', $pieza['claves'], 'sin claves la pregunta no se encuentra' );
			$this->assertStringContainsString( '<p>', $pieza['respuesta'], 'respuesta en HTML de párrafo' );
		}

		// Los títulos no se repiten: dos iguales serían una pregunta duplicada en el buscador.
		$titulos = array_column( $piezas, 'titulo' );
		$this->assertSame( $titulos, array_unique( $titulos ) );
	}

	/**
	 * Los sinónimos son un mapa de término a lista no vacía.
	 */
	public function test_los_sinonimos(): void {
		$sinonimos = Starter_Pack::sinonimos();

		$this->assertNotEmpty( $sinonimos );
		foreach ( $sinonimos as $termino => $lista ) {
			$this->assertIsString( $termino );
			$this->assertNotEmpty( $lista, 'sin lista no hay sinónimo: ' . $termino );
		}
	}

	/**
	 * Cuenta solo las marcadas como del paquete.
	 */
	public function test_cuenta_las_instaladas(): void {
		$this->faq( 1, 'Una', true );
		$this->faq( 2, 'Otra', true );
		$this->faq( 3, 'A mano', false );

		$this->assertSame( 2, Starter_Pack::instaladas(), 'la escrita a mano no cuenta como del paquete' );
		$this->assertFalse( Starter_Pack::esta_instalado() );
	}

	/**
	 * Con las seis puestas, el paquete está instalado.
	 */
	public function test_esta_instalado(): void {
		foreach ( Starter_Pack::contenido() as $i => $pieza ) {
			$this->faq( 10 + $i, $pieza['titulo'], true );
		}

		$this->assertSame( 6, Starter_Pack::instaladas() );
		$this->assertTrue( Starter_Pack::esta_instalado() );
	}

	/**
	 * La instalación crea las seis, las marca y añade los sinónimos.
	 */
	public function test_instalar_desde_cero(): void {
		$resultado = Starter_Pack::instalar();

		$this->assertSame( 6, $resultado['fichas'] );
		$this->assertSame( count( Starter_Pack::sinonimos() ), $resultado['sinonimos'], 'todos los sinónimos son nuevos' );
		$this->assertCount( 6, $GLOBALS['convoca_as_insertados'] );

		foreach ( $GLOBALS['_assistant_meta'] as $id => $meta ) {
			$this->assertSame( '1', $meta[ Starter_Pack::META_MARCA ], 'la ficha queda marcada' );
			$this->assertNotSame( '', $meta['_convoca_assistant_keywords'], 'y con sus claves' );
		}

		$this->assertSame( 6, Starter_Pack::instaladas() );
	}

	/**
	 * Pulsar dos veces no duplica: la segunda vez no crea ninguna.
	 */
	public function test_instalar_dos_veces_no_duplica(): void {
		Starter_Pack::instalar();
		$posts = count( $GLOBALS['convoca_as_insertados'] );

		$segunda = Starter_Pack::instalar();

		$this->assertSame( 0, $segunda['fichas'], 'la segunda vez no crea nada' );
		$this->assertSame( 0, $segunda['sinonimos'], 'y no reescribe sinónimos' );
		$this->assertSame( $posts, count( $GLOBALS['convoca_as_insertados'] ) );
	}

	/**
	 * Un sinónimo ya escrito no se pisa.
	 */
	public function test_no_pisa_los_sinonimos_existentes(): void {
		$GLOBALS['_assistant_options']['convoca_assistant_synonyms'] = array(
			'cuota' => array( 'lo-que-puso-la-asociacion' ),
		);

		Starter_Pack::instalar();

		$guardados = $GLOBALS['_assistant_options']['convoca_assistant_synonyms'];
		$this->assertSame( array( 'lo-que-puso-la-asociacion' ), $guardados['cuota'], 'lo escrito a mano manda' );
		$this->assertArrayHasKey( 'socio', $guardados, 'los que faltaban sí se añaden' );
	}
}

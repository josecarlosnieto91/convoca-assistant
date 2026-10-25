<?php
/**
 * Consultas que tienen respuesta y el buscador callaba (issue #16).
 *
 * Medido sobre el registro real del buscador —109 consultas entre producción y la demo— tres preguntas del centro
 * con respuesta en el corpus no recibían nada, mientras la misma idea en forma corta sí respondía:
 *
 *   «¿Cuánto cuesta la cuota?»      callaba   ·  «cuota»            0,4237
 *   «¿Dónde está el centro social?» callaba   ·  «dónde estáis»     0,4245
 *   «¿Qué servicios ofrecéis?»      callaba (producción)
 *
 * Eran 3 de las 8 que callaban en toda la muestra; las otras 5 (`hola`, `gracias`, `xyzzy`…) callan bien.
 *
 * Dos causas, las dos medidas:
 *
 *  1. `match_fuerte` comparaba la consulta **entera** contra el título, y una pregunta natural nunca es un
 *     match exacto. La misma entrada daba 0,5569 con «cuota» y 0,3939 con la pregunta — justo por debajo del
 *     umbral de 0,40. Ahora basta con que un token de contenido aparezca como palabra del título. Es el único
 *     cambio necesario: medido en negativo, es el único que hace caer estas pruebas.
 *  2. Se probó también a unir la lista de palabras vacías del índice con la de fábrica, y a añadir «cuanto» a
 *     esa lista. Ninguno de los dos cambios hacía falta —las pruebas pasaban igual sin ellos—, así que **no se
 *     quedaron**: un cambio que no mueve ninguna medición es deuda, no una mejora.
 *
 * **El umbral no se toca**: está justificado con mediciones en el issue #15 y bajarlo reabriría aquello.
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Searcher;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Convoca\Assistant\Searcher
 */
class ConsultasDelCentroTest extends TestCase {

	private const FICHERO_INDICE = CONVOCA_ASSISTANT_INDEX_DIR . 'index.json';

	/**
	 * El umbral con el que responde el widget.
	 */
	private const UMBRAL = 0.40;

	protected function tearDown(): void {
		if ( file_exists( self::FICHERO_INDICE ) ) {
			unlink( self::FICHERO_INDICE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
		parent::tearDown();
	}

	/**
	 * Escribe el índice del centro.
	 *
	 * @param array<int, string> $stop_words Palabras vacías que se guardan en el índice.
	 * @return void
	 */
	private function escribir_indice( array $stop_words = array() ): void {
		if ( ! is_dir( CONVOCA_ASSISTANT_INDEX_DIR ) ) {
			mkdir( CONVOCA_ASSISTANT_INDEX_DIR, 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		}

		$base = array(
			'excerpt'    => '',
			'keywords'   => array(),
			'categories' => array(),
			'tags'       => array(),
			'date'       => '',
			'modified'   => '',
			'url'        => '',
			'thumbnail'  => '',
			'weight'     => 5,
		);

		$entradas = array(
			array_merge(
				$base,
				array(
					'id'      => 1,
					'type'    => 'convoca_faq',
					'title'   => 'Cuota de socio',
					'content' => 'La cuota anual es de 30 euros para socios.',
				)
			),
			array_merge(
				$base,
				array(
					'id'      => 2,
					'type'    => 'convoca_faq',
					'title'   => 'Centro social',
					'content' => 'El centro social esta en la calle principal del pueblo.',
				)
			),
			array_merge(
				$base,
				array(
					'id'      => 3,
					'type'    => 'convoca_faq',
					'title'   => 'Servicios',
					'content' => 'Ofrecemos talleres, excursiones y voluntariado.',
				)
			),
		);

		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			self::FICHERO_INDICE,
			(string) json_encode(
				array(
					'entries'    => $entradas,
					'stop_words' => $stop_words,
					'synonyms'   => array(),
				),
				JSON_UNESCAPED_UNICODE
			)
		);
	}

	/**
	 * Identificador de la primera entrada que devuelve una consulta, o 0 si calla.
	 *
	 * @param string $consulta Consulta tal cual la escribiría una persona.
	 * @return int
	 */
	private function primera( string $consulta ): int {
		$resultados = Searcher::search( $consulta, 10, self::UMBRAL );

		if ( empty( $resultados ) ) {
			return 0;
		}

		// El resultado envuelve la entrada: `[ 'entry' => [...], 'score' => … ]`. Se aceptan las dos formas para
		// que la prueba no dependa de si el motor entró por el compuesto o por la fusión.
		return (int) ( $resultados[0]['entry']['id'] ?? $resultados[0]['id'] ?? 0 );
	}

	/**
	 * La pregunta larga encuentra lo mismo que la palabra suelta.
	 *
	 * Antes daba 0,3939 y el umbral de 0,40 la cortaba.
	 */
	public function test_la_pregunta_de_la_cuota_encuentra_la_cuota(): void {
		$this->escribir_indice();

		$this->assertSame( 1, $this->primera( 'cuota' ), 'la palabra suelta ya respondía' );
		$this->assertSame( 1, $this->primera( '¿Cuánto cuesta la cuota?' ), 'la pregunta larga debe encontrar la misma entrada' );
	}

	/**
	 * «dónde está el centro social» encuentra el centro.
	 */
	public function test_la_pregunta_del_centro_encuentra_el_centro(): void {
		$this->escribir_indice();

		$this->assertSame( 2, $this->primera( '¿Dónde está el centro social?' ) );
	}

	/**
	 * «qué servicios ofrecéis» encuentra los servicios.
	 */
	public function test_la_pregunta_de_los_servicios_encuentra_los_servicios(): void {
		$this->escribir_indice();

		$this->assertSame( 3, $this->primera( '¿Qué servicios ofrecéis?' ) );
	}

	/**
	 * La pregunta larga funciona con cualquier lista de palabras vacías.
	 *
	 * El índice guarda las palabras vacías con las que se construyó, así que un sitio instalado hace meses
	 * tokeniza con una lista vieja. Aquí se escribe la lista **vacía**, que es el caso extremo: la pregunta se
	 * parte en más tokens («cuanto», «cuesta», «cuota») y aun así tiene que encontrar la cuota, porque lo que la
	 * salva es que «cuota» es palabra del título y no cuántos tokens lleve la frase.
	 */
	public function test_la_pregunta_larga_funciona_con_una_lista_de_palabras_vacias_vieja(): void {
		$this->escribir_indice( array() );

		$this->assertSame( 1, $this->primera( '¿Cuánto cuesta la cuota?' ) );
	}

	/**
	 * Callar cuando no hay nada que decir sigue siendo correcto.
	 *
	 * Son las otras cinco de las ocho que callaban en la muestra real. Bajarlas a que respondieran algo sería
	 * peor que callar.
	 */
	public function test_las_consultas_sin_contenido_siguen_callando(): void {
		$this->escribir_indice();

		foreach ( array( 'hola', 'gracias', 'xyzzy' ) as $vacia ) {
			$this->assertSame( 0, $this->primera( $vacia ), sprintf( '«%s» no debe devolver nada', $vacia ) );
		}
	}
}

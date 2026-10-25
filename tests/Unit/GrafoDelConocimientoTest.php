<?php
/**
 * El grafo del conocimiento: cómo se construye (issue #18).
 *
 * `Graph_Builder::build()` era el único fichero de `includes/` sin una sola prueba. El caso que se retiró con
 * el issue #18 decía que «la estructura del grafo no es la que esperaba la prueba»; esto fija la estructura que
 * **sí** produce hoy, medida, en vez de dejar el hueco.
 *
 * Lo que se mide aquí:
 *  - sin proveedores y sin índice, el grafo sale vacío pero bien formado;
 *  - con índice, las entradas del **mismo tipo** se encadenan con peso 0,15 y tipo `same_type_<tipo>`;
 *  - dos tipos producen dos cadenas independientes;
 *  - el número de nodos son los identificadores únicos, no las aristas;
 *  - una entrada que ya trae aristas de un proveedor no recibe además la cadena automática.
 *
 * **Excepción escrita**: el filtro `convoca_assistant/graph_data` no se mide porque `apply_filters()` del arnés
 * es un no-op que devuelve el valor tal cual. Probarlo exigiría doblar la función y afectaría a todo el repo;
 * queda declarado en vez de fingir una prueba que no comprueba nada.
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Graph_Builder;
use Convoca\Assistant\Provider_Registry;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Convoca\Assistant\Graph_Builder
 */
class GrafoDelConocimientoTest extends TestCase {

	private const FICHERO_INDICE = CONVOCA_ASSISTANT_INDEX_DIR . 'index.json';

	protected function tearDown(): void {
		if ( file_exists( self::FICHERO_INDICE ) ) {
			unlink( self::FICHERO_INDICE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}

		// El ajuste que enciende el proveedor de prueba no puede sobrevivir a la prueba.
		unset( $GLOBALS['_assistant_options']['convoca_assistant_settings'] );

		parent::tearDown();
	}

	/**
	 * Deja el registro de proveedores vacío.
	 *
	 * `Provider_Registry::$providers` es privada y no tiene un reset público, así que se vacía por reflexión.
	 *
	 * @param array<string, object> $providers Proveedores a dejar registrados.
	 * @return void
	 */
	private function sembrar_proveedores( array $providers = array() ): void {
		$prop = new \ReflectionProperty( Provider_Registry::class, 'providers' );
		$prop->setValue( null, $providers );
	}

	/**
	 * Escribe el índice con los pares (id, tipo) dados.
	 *
	 * @param array<int, array{id: int, type: string}> $entradas Entradas del índice.
	 * @return void
	 */
	private function escribir_indice( array $entradas ): void {
		if ( ! is_dir( CONVOCA_ASSISTANT_INDEX_DIR ) ) {
			mkdir( CONVOCA_ASSISTANT_INDEX_DIR, 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		}

		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			self::FICHERO_INDICE,
			(string) json_encode( array( 'entries' => $entradas ), JSON_UNESCAPED_UNICODE )
		);
	}

	/**
	 * Sin proveedores y sin índice, el grafo está vacío pero tiene la forma esperada.
	 */
	public function test_sin_nada_el_grafo_sale_vacio(): void {
		$this->sembrar_proveedores();

		$grafo = Graph_Builder::build();

		$this->assertSame( 0, $grafo['nodes'] );
		$this->assertSame( array(), $grafo['edges'] );
		$this->assertArrayHasKey( 'version', $grafo );
		$this->assertArrayHasKey( 'generated', $grafo );
	}

	/**
	 * Tres entradas del mismo tipo se encadenan en orden: 1-2 y 2-3.
	 *
	 * No se conectan todas con todas —eso daría un grafo completo y no un recorrido—, sino cada una con la
	 * siguiente.
	 */
	public function test_las_entradas_del_mismo_tipo_se_encadenan(): void {
		$this->sembrar_proveedores();
		$this->escribir_indice(
			array(
				array( 'id' => 11, 'type' => 'convoca_faq' ),
				array( 'id' => 12, 'type' => 'convoca_faq' ),
				array( 'id' => 13, 'type' => 'convoca_faq' ),
			)
		);

		$grafo = Graph_Builder::build();

		$this->assertSame( 3, $grafo['nodes'], 'tres identificadores únicos' );
		$this->assertCount( 2, $grafo['edges'], 'encadenadas: 11-12 y 12-13, no todas con todas' );

		$pares = array();
		foreach ( $grafo['edges'] as $arista ) {
			$this->assertSame( 'same_type_convoca_faq', $arista['type'] );
			$this->assertSame( 0.15, $arista['weight'], 'el peso automático es flojo, no compite con un proveedor' );
			$pares[] = array( $arista['from'], $arista['to'] );
		}

		$this->assertSame( array( array( 11, 12 ), array( 12, 13 ) ), $pares );
	}

	/**
	 * Dos tipos dan dos cadenas, y no se mezclan entre sí.
	 */
	public function test_dos_tipos_no_se_mezclan(): void {
		$this->sembrar_proveedores();
		$this->escribir_indice(
			array(
				array( 'id' => 21, 'type' => 'convoca_faq' ),
				array( 'id' => 22, 'type' => 'convoca_faq' ),
				array( 'id' => 31, 'type' => 'convoca_actividad' ),
				array( 'id' => 32, 'type' => 'convoca_actividad' ),
			)
		);

		$grafo = Graph_Builder::build();

		$this->assertSame( 4, $grafo['nodes'] );
		$this->assertCount( 2, $grafo['edges'] );

		$tipos = array_column( $grafo['edges'], 'type' );
		sort( $tipos );
		$this->assertSame( array( 'same_type_convoca_actividad', 'same_type_convoca_faq' ), $tipos );
	}

	/**
	 * Una relación sin `weight` no suelta un warning y entra con el peso neutro.
	 *
	 * El contrato promete `weight`, pero lo implementa cada proveedor. Antes, `$rel['weight']` a pelo daba
	 * `Undefined array key "weight"` por cada relación en cada construcción del grafo.
	 */
	public function test_una_relacion_sin_peso_no_suelta_un_warning(): void {
		$proveedor = new class() {
			public function is_available(): bool {
				return true;
			}

			public function get_setting_key(): string {
				return 'proveedor_prueba_activo';
			}

			public function get_entries( int $limit ): array {
				unset( $limit );
				return array( array( 'id' => 51 ) );
			}

			public function get_relations( int $id ): array {
				unset( $id );
				// Sin `weight`, como lo devolvería un proveedor de fuera que sigue el contrato a medias.
				return array( array( 'to' => 52, 'type' => 'manual' ) );
			}
		};

		$this->sembrar_proveedores( array( 'prueba' => $proveedor ) );
		update_option( 'convoca_assistant_settings', array( 'proveedor_prueba_activo' => true ) );

		$grafo = Graph_Builder::build();

		$this->assertCount( 1, $grafo['edges'] );
		$this->assertSame( 0.3, $grafo['edges'][0]['weight'], 'un enlace sin peso entra con el peso neutro del contrato' );
	}

	/**
	 * Una entrada que ya tiene aristas no recibe además la cadena automática.
	 *
	 * El fallback existe para tipos **sin** relaciones; si el primero del tipo ya viene enlazado por un
	 * proveedor, se entiende que ese tipo está cubierto.
	 */
	public function test_el_fallback_no_pisa_un_tipo_que_ya_tiene_aristas(): void {
		$proveedor = new class() {
			public function is_available(): bool {
				return true;
			}

			public function get_setting_key(): string {
				return 'proveedor_prueba_activo';
			}

			public function get_entries( int $limit ): array {
				unset( $limit );
				return array( array( 'id' => 41 ), array( 'id' => 42 ) );
			}

			public function get_relations( int $id ): array {
				return 41 === $id ? array( array( 'to' => 999, 'type' => 'manual' ) ) : array();
			}
		};

		$this->sembrar_proveedores( array( 'prueba' => $proveedor ) );
		// `get_active()` solo cuenta un proveedor si su ajuste está encendido.
		update_option( 'convoca_assistant_settings', array( 'proveedor_prueba_activo' => true ) );
		$this->escribir_indice(
			array(
				array( 'id' => 41, 'type' => 'convoca_faq' ),
				array( 'id' => 42, 'type' => 'convoca_faq' ),
			)
		);

		$grafo = Graph_Builder::build();

		$tipos = array_column( $grafo['edges'], 'type' );
		$this->assertContains( 'manual', $tipos, 'la arista del proveedor está' );
		$this->assertNotContains( 'same_type_convoca_faq', $tipos, 'no se añade la cadena: el tipo ya estaba enlazado' );
		$this->assertCount( 1, $grafo['edges'] );
	}
}

<?php
/**
 * Tests unitarios para el proveedor Posts_Provider.
 *
 * Verifica que las consultas de indexación excluyen el contenido protegido
 * por contraseña (has_password => false) y que el proveedor está disponible.
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Providers\Posts_Provider;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Convoca\Assistant\Providers\Posts_Provider
 */
class PostsProviderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		\WP_Query::$captured = array();
	}

	/**
	 * get_entries() debe indexar solo posts publicados y sin contraseña.
	 */
	public function test_get_entries_query_excludes_password_protected_posts(): void {
		$provider = new Posts_Provider();
		$provider->get_entries();

		$this->assertNotEmpty( \WP_Query::$captured, 'get_entries() debe crear una WP_Query.' );

		$args = \WP_Query::$captured[0];
		$this->assertArrayHasKey( 'has_password', $args, 'La query debe especificar has_password.' );
		$this->assertFalse( $args['has_password'], 'has_password debe ser false.' );
		$this->assertSame( 'publish', $args['post_status'] );
	}

	/**
	 * get_entry_count() también debe excluir posts protegidos por contraseña.
	 */
	public function test_get_entry_count_query_excludes_password_protected_posts(): void {
		$provider = new Posts_Provider();
		$provider->get_entry_count();

		$this->assertNotEmpty( \WP_Query::$captured, 'get_entry_count() debe crear una WP_Query.' );

		$args = \WP_Query::$captured[0];
		$this->assertArrayHasKey( 'has_password', $args, 'La query debe especificar has_password.' );
		$this->assertFalse( $args['has_password'], 'has_password debe ser false.' );
	}

	/**
	 * El proveedor está disponible cuando existe el post type 'post'.
	 */
	public function test_is_available_returns_true(): void {
		$provider = new Posts_Provider();
		$this->assertTrue( $provider->is_available() );
		$this->assertSame( 'post', $provider->get_id() );
	}
}

<?php
/**
 * Tests for the Indexer class.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Indexer;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Convoca\Assistant\Indexer
 */
class IndexerLegacyTest extends TestCase {

	/**
	 * Parte de un índice limpio.
	 *
	 * `is_dirty()` es un TRANSIENT: sobrevive a la prueba que lo puso. `test_dirty_flag` empezaba con
	 * `assertFalse( Indexer::is_dirty() )` **dando por hecho** que nadie había marcado el índice, así que fallaba
	 * si antes corrió cualquier caso que llame a `mark_dirty()` — que son varios (assistant#22).
	 * El escenario se monta aquí, no se hereda.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		delete_transient( 'convoca_assistant_index_dirty' );
		delete_transient( 'convoca_assistant_index_debounce' );
	}

	/**
	 * Test dirty flag lifecycle.
	 */
	public function test_dirty_flag(): void {
		$this->assertFalse( Indexer::is_dirty() );

		Indexer::mark_dirty();
		$this->assertTrue( Indexer::is_dirty() );
	}

	/**
	 * Test index file path.
	 */
	public function test_index_path(): void {
		$path = Indexer::get_index_path();
		$this->assertStringContainsString( 'index.json', $path );
	}
}

<?php
/**
 * Tests for the Indexer class.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Indexer;
use WP_UnitTestCase;

/**
 * @coversDefaultClass \Convoca\Assistant\Indexer
 */
class Test_Indexer extends WP_UnitTestCase {

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

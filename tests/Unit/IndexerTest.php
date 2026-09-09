<?php
/**
 * Unit tests for Indexer immediate regeneration (decision D20).
 *
 * Verifies that marking the index dirty schedules a one-shot immediate
 * regeneration (deduped), that the debounce window avoids double scheduling,
 * that the recurring every-5-minutes backup cron stays scheduled, and that
 * maybe_regenerate() keeps skipping a clean, existing index.
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Indexer;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Convoca\Assistant\Indexer
 */
class IndexerTest extends TestCase {

	/**
	 * Reset the mocked transient, cron and option stores before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['_assistant_transients'] = array();
		$GLOBALS['_assistant_cron']       = array();
		$GLOBALS['_assistant_options']    = array();

		$this->remove_index_file();
	}

	/**
	 * Clean up the temporary index file after each test.
	 */
	protected function tearDown(): void {
		$this->remove_index_file();
		parent::tearDown();
	}

	/**
	 * Remove the temporary index file and directory, if present.
	 *
	 * @return void
	 */
	private function remove_index_file(): void {
		$file = CONVOCA_ASSISTANT_INDEX_DIR . 'index.json';
		if ( file_exists( $file ) ) {
			unlink( $file );
		}
		if ( is_dir( CONVOCA_ASSISTANT_INDEX_DIR ) ) {
			@rmdir( CONVOCA_ASSISTANT_INDEX_DIR );
		}
	}

	/**
	 * Write a dummy index file large enough to satisfy index_exists().
	 *
	 * @return void
	 */
	private function write_index_file(): void {
		if ( ! is_dir( CONVOCA_ASSISTANT_INDEX_DIR ) ) {
			mkdir( CONVOCA_ASSISTANT_INDEX_DIR, 0777, true );
		}
		file_put_contents( CONVOCA_ASSISTANT_INDEX_DIR . 'index.json', str_repeat( 'x', 100 ) );
	}

	/**
	 * mark_dirty() sets the dirty flag and schedules a one-shot regeneration.
	 */
	public function test_mark_dirty_sets_flag_and_schedules_immediate_regeneration(): void {
		$this->assertFalse( Indexer::is_dirty() );

		Indexer::mark_dirty();

		$this->assertTrue( Indexer::is_dirty() );
		$this->assertNotFalse(
			wp_next_scheduled( 'convoca_assistant_regenerate_now' ),
			'mark_dirty() debe agendar una regeneración inmediata.'
		);
	}

	/**
	 * Within the debounce window a second mark_dirty() must not re-schedule.
	 */
	public function test_mark_dirty_debounce_does_not_reschedule(): void {
		Indexer::mark_dirty();

		// Simula que el evento único ya se disparó (se desagenda solo).
		wp_clear_scheduled_hook( 'convoca_assistant_regenerate_now' );

		// Segunda marca dentro de la ventana de debounce: no debe reagendar.
		Indexer::mark_dirty();

		$this->assertFalse(
			wp_next_scheduled( 'convoca_assistant_regenerate_now' ),
			'Dentro del debounce no debe reagendarse el evento único.'
		);
	}

	/**
	 * schedule_regenerate_now() dedupes: true once, false while pending.
	 */
	public function test_schedule_regenerate_now_dedupes(): void {
		$this->assertTrue( Indexer::schedule_regenerate_now() );
		$this->assertFalse( Indexer::schedule_regenerate_now() );

		wp_clear_scheduled_hook( 'convoca_assistant_regenerate_now' );
		$this->assertTrue( Indexer::schedule_regenerate_now() );
	}

	/**
	 * schedule_backup_cron() ensures the recurring backup cron is scheduled.
	 */
	public function test_schedule_backup_cron_schedules_and_dedupes(): void {
		$this->assertTrue( Indexer::schedule_backup_cron() );
		$this->assertNotFalse( wp_next_scheduled( 'convoca_assistant_regenerate' ) );

		// Ya programado: sigue devolviendo true sin duplicar.
		$this->assertTrue( Indexer::schedule_backup_cron() );
	}

	/**
	 * maybe_regenerate() skips when the index exists and is not dirty.
	 */
	public function test_maybe_regenerate_skips_clean_existing_index(): void {
		$this->write_index_file();

		$result = Indexer::maybe_regenerate();

		$this->assertArrayHasKey( 'success', $result );
		$this->assertTrue( $result['success'] );
		$this->assertTrue( $result['skipped'] );
	}
}

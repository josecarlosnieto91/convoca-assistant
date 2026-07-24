<?php
/**
 * Cache: manages caching of the knowledge index.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

/**
 * Handles caching strategies for the knowledge index:
 * file-based cache, transient cache, and cache busting.
 */
class Cache {

	/**
	 * Transient key for the index dirty flag.
	 */
	private const DIRTY_TRANSIENT = 'convoca_assistant_index_dirty';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'convoca_assistant/index_url', array( __CLASS__, 'add_cache_buster' ) );
	}

	/**
	 * Check if the index needs regeneration.
	 *
	 * @return bool
	 */
	public static function is_dirty(): bool {
		return false !== get_transient( self::DIRTY_TRANSIENT );
	}

	/**
	 * Get when the index was last dirtied.
	 *
	 * @return int Timestamp or 0.
	 */
	public static function dirty_since(): int {
		return (int) get_transient( self::DIRTY_TRANSIENT );
	}

	/**
	 * Mark the index as dirty.
	 *
	 * @return void
	 */
	public static function mark_dirty(): void {
		set_transient( self::DIRTY_TRANSIENT, time(), HOUR_IN_SECONDS );
	}

	/**
	 * Clear the dirty flag.
	 *
	 * @return void
	 */
	public static function clear_dirty(): void {
		delete_transient( self::DIRTY_TRANSIENT );
	}

	/**
	 * Get the index file path.
	 *
	 * @return string
	 */
	public static function index_file_path(): string {
		return CONVOCA_ASSISTANT_INDEX_DIR . 'index.json';
	}

	/**
	 * Get the index file URL with cache busting.
	 *
	 * @return string
	 */
	public static function index_url(): string {
		$hash = get_option( 'convoca_assistant_index_hash', '' );
		$url  = CONVOCA_ASSISTANT_INDEX_DIR . 'index.json';

		$upload = wp_upload_dir();
		$url    = $upload['baseurl'] . '/convoca-assistant/index.json';

		if ( $hash ) {
			$url = add_query_arg( 'v', substr( $hash, 0, 8 ), $url );
		}

		/**
		 * Filter the index URL.
		 *
		 * @param string $url The index URL.
		 */
		return apply_filters( 'convoca_assistant/index_url', $url );
	}

	/**
	 * Get index file size.
	 *
	 * @return int Size in bytes or 0.
	 */
	public static function index_size(): int {
		$file = self::index_file_path();
		return file_exists( $file ) ? filesize( $file ) : 0;
	}

	/**
	 * Add cache buster query string.
	 *
	 * @param string $url Original URL.
	 * @return string
	 */
	public static function add_cache_buster( string $url ): string {
		$hash = get_option( 'convoca_assistant_index_hash', '' );
		if ( $hash ) {
			return add_query_arg( 'v', substr( $hash, 0, 8 ), $url );
		}
		return $url;
	}
}

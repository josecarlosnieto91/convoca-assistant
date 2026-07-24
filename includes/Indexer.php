<?php
/**
 * Indexer: generates the compressed JSON knowledge index.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

use Convoca\Core\Logger;

/**
 * Builds, caches, and maintains the searchable knowledge index from
 * active content sources. Supports automatic regeneration via cron,
 * content change triggers, debounced dirty flags, and WooCommerce.
 *
 * @phpstan-type IndexEntry array{id: int, type: string, title: string, content: string, excerpt: string, url: string, thumbnail: string, categories: string[], tags: string[], keywords: string[], weight: float, date: string, modified: string, price?: string, sku?: string}
 * @phpstan-type KnowledgeIndex array{version: string, generated: int, locale: string, total: int, hash: string, entries: IndexEntry[], synonyms: array<string, string[]>, stop_words: string[], config: array<string, mixed>}
 */
class Indexer {

	/**
	 * Current index schema version. Bump to force full rebuild.
	 */
	private const INDEX_SCHEMA_VERSION = 1;

	/**
	 * Debounce window in seconds: multiple saves within this window
	 * trigger a single regeneration.
	 */
	private const DEBOUNCE_WINDOW = 30;

	/**
	 * Initialize hooks and custom cron schedule.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Register custom cron interval.
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_interval' ) );

		// Scheduled regeneration.
		add_action( 'convoca_assistant_regenerate', array( __CLASS__, 'maybe_regenerate' ) );

		// Content change triggers (debounced).
		add_action( 'wp_insert_post', array( __CLASS__, 'mark_dirty' ), 10, 3 );
		add_action( 'delete_post', array( __CLASS__, 'mark_dirty' ) );
		add_action( 'trashed_post', array( __CLASS__, 'mark_dirty' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'mark_dirty' ) );

		// Metadata changes.
		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta_change' ), 10, 4 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta_change' ), 10, 4 );

		// Taxonomy changes.
		add_action( 'set_object_terms', array( __CLASS__, 'mark_dirty' ), 10, 1 );

		// Settings/synonyms changes (class_synonyms hooks into update_option).
	}

	/**
	 * Register the every_5_minutes cron schedule.
	 *
	 * @param array<string, array{interval: int, display: string}> $schedules Existing schedules.
	 * @return array
	 */
	public static function add_cron_interval( array $schedules ): array {
		$schedules['every_5_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Cada 5 minutos', 'convoca-assistant' ),
		);
		return $schedules;
	}

	/* ── Regeneration ──────────────────────────── */

	/**
	 * Rebuild the index only if dirty.
	 *
	 * @return array
	 */
	public static function maybe_regenerate(): array {
		if ( ! self::is_dirty() && self::index_exists() ) {
			return array(
				'success' => true,
				'skipped' => true,
				'message' => __( 'Index is up to date.', 'convoca-assistant' ),
			);
		}
		return self::regenerate();
	}

	/**
	 * Force-rebuild the full knowledge index.
	 *
	 * @return array{success: bool, count?: int, size?: int, error?: string}
	 */
	public static function regenerate(): array {
		/**
		 * Fires before the index is regenerated.
		 */
		do_action( 'convoca_assistant/before_index' );

		// Version migration: if stored schema is old, force full reset.
		$stored_schema = (int) get_option( 'convoca_assistant_index_schema', 0 );
		if ( $stored_schema < self::INDEX_SCHEMA_VERSION ) {
			self::clear_index_files();
			update_option( 'convoca_assistant_index_schema', self::INDEX_SCHEMA_VERSION );
		}

		try {
			$entries = self::collect_entries();
		} catch ( \Throwable $e ) {
			Logger::error( 'Index collection failed: ' . $e->getMessage(), 'convoca-assistant' );
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}

		$synonyms   = get_option( 'convoca_assistant_synonyms', array() );
		$stop_words = get_option( 'convoca_assistant_stop_words', Installer::default_stop_words() );
		$settings   = get_option( 'convoca_assistant_settings', Installer::default_settings() );

		$index = array(
			'version'    => CONVOCA_ASSISTANT_VERSION,
			'schema'     => self::INDEX_SCHEMA_VERSION,
			'generated'  => time(),
			'locale'     => get_locale(),
			'site_name'  => get_bloginfo( 'name' ),
			'total'      => count( $entries ),
			'hash'       => '',
			'entries'    => $entries,
			'synonyms'   => $synonyms,
			'stop_words' => $stop_words,
			'config'     => array(
				'fuse_threshold' => (float) ( $settings['search_fuse_threshold'] ?? 0.4 ),
				'fuse_distance'  => (int) ( $settings['search_fuse_distance'] ?? 100 ),
				'weights'        => array(
					'title'      => 4,
					'keywords'   => 3,
					'categories' => 2,
					'content'    => 1,
					'tags'       => 1,
				),
			),
		);

		// Generate hash before writing (without hash in JSON so hash is stable).
		$json_no_hash = wp_json_encode( $index, JSON_UNESCAPED_UNICODE );
		if ( false === $json_no_hash ) {
			return array( 'success' => false, 'error' => 'JSON encoding failed.' );
		}
		$hash          = md5( $json_no_hash );
		$index['hash'] = $hash;

		$json_final = wp_json_encode( $index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json_final ) {
			return array( 'success' => false, 'error' => 'JSON encoding failed (final).' );
		}

		// Ensure directory exists.
		$dir = CONVOCA_ASSISTANT_INDEX_DIR;
		if ( ! is_dir( $dir ) ) {
			$created = wp_mkdir_p( $dir );
			if ( ! $created ) {
				return array( 'success' => false, 'error' => 'Could not create index directory.' );
			}
		}

		// Protect with .htaccess.
		if ( ! file_exists( $dir . '.htaccess' ) ) {
			file_put_contents( $dir . '.htaccess', "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		// Write uncompressed JSON.
		$written_json = file_put_contents( $dir . 'index.json', $json_final ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $written_json ) {
			return array( 'success' => false, 'error' => 'Could not write index.json.' );
		}

		// Clean up any leftover .gz files from previous versions.
		if ( file_exists( $dir . 'index.json.gz' ) ) {
			wp_delete_file( $dir . 'index.json.gz' );
		}

		update_option( 'convoca_assistant_index_hash', $hash );
		update_option( 'convoca_assistant_index_generated', time() );

		// Build and write knowledge graph.
		$graph_result = Graph_Builder::write();
		if ( $graph_result['success'] ) {
			Logger::info(
				sprintf( 'Graph built: %d nodes, %d edges.', $graph_result['nodes'], $graph_result['edges'] ),
				'convoca-assistant'
			);
		}

		delete_transient( 'convoca_assistant_index_dirty' );
		delete_transient( 'convoca_assistant_index_debounce' );

		/**
		 * Fires after the index has been regenerated.
		 *
		 * @param array $index The complete index data.
		 */
		do_action( 'convoca_assistant/after_index', $index );

		Logger::info(
			sprintf( 'Index regenerated: %d entries, %s.', count( $entries ), size_format( $written_json ) ?: $written_json . ' bytes' ),
			'convoca-assistant'
		);

		return array(
			'success' => true,
			'count'   => count( $entries ),
			'size'    => $written_json,
		);
	}

	/* ── Content Collection ────────────────────── */

	/**
	 * Collect all eligible entries from active sources via Provider_Registry.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_entries(): array {
		$settings    = get_option( 'convoca_assistant_settings', Installer::default_settings() );
		$max_content = (int) ( $settings['index_max_content'] ?? 5000 );

		/**
		 * Filter the collected entries before indexing.
		 *
		 * @param array $entries The collected entries.
		 */
		return apply_filters( 'convoca_assistant/index_data', Provider_Registry::collect_entries( $max_content ) );
	}

	/**
	 * Build a single index entry from a post.
	 *
	 * @param \WP_Post $post        Post object.
	 * @param string   $post_type   Post type slug.
	 * @param int      $max_content Max content length.
	 * @return array<string, mixed>
	 */
	private static function build_entry( \WP_Post $post, string $post_type, int $max_content ): array {
		// Clean content: strip shortcodes first, then blocks, then tags.
		$content = get_the_content( null, false, $post );
		$content = self::clean_content( $content ?? '' );
		$content = mb_substr( $content, 0, $max_content );

		return array(
			'id'         => $post->ID,
			'type'       => $post_type,
						'title'      => $post->post_title,
			'content'    => $content,
			'excerpt'    => self::clean_content( (string) get_the_excerpt( $post ) ),
			'url'        => get_permalink( $post ),
			'thumbnail'  => get_the_post_thumbnail_url( $post, 'thumbnail' ) ?: '',
			'categories' => self::get_taxonomy_terms( $post->ID, $post_type ),
			'tags'       => self::get_term_names( $post->ID, 'post_tag' ),
			'keywords'   => self::parse_keywords( $post->ID ),
			'weight'     => Knowledge_Base::get_effective_weight( $post->ID, $post_type ),
			'date'       => $post->post_date,
			'modified'   => $post->post_modified,
		);
	}

	/**
	 * Strip shortcodes, block comments, and HTML tags from content.
	 *
	 * @param string $raw Raw content possibly containing blocks and shortcodes.
	 * @return string
	 */
	private static function clean_content( string $raw ): string {
		// Remove block editor comments.
		$text = preg_replace( '/<!--\s*wp:.*?-->/s', '', $raw );
		$text = preg_replace( '/<!--\s*\/wp:.*?-->/s', '', $text );

		// Strip shortcodes.
		$text = strip_shortcodes( $text );

		// Strip all HTML tags.
		$text = wp_strip_all_tags( $text );

		// Decode entities.
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

		// Normalize whitespace.
		$text = preg_replace( '/\s+/u', ' ', $text );

		return trim( $text );
	}

	/**
	 * Collect taxonomy terms relevant to indexing.
	 *
	 * For standard posts/pages: 'category'.
	 * For FAQ: 'convoca_faq_cat'.
	 * For KB: 'convoca_kb_cat'.
	 * Plus any registered taxonomies on the post type.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 * @return string[]
	 */
	private static function get_taxonomy_terms( int $post_id, string $post_type ): array {
		$taxonomies = array();

		switch ( $post_type ) {
			case 'convoca_faq':
				$taxonomies[] = 'convoca_faq_cat';
				break;
			case 'convoca_kb':
				$taxonomies[] = 'convoca_kb_cat';
				break;
			default:
				$taxonomies[] = 'category';
				break;
		}

		$all = array();
		foreach ( $taxonomies as $tax ) {
			$terms = self::get_term_names( $post_id, $tax );
			$all   = array_merge( $all, $terms );
		}

		return $all;
	}

	/**
	 * Get term names for a post by taxonomy.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return string[]
	 */
	private static function get_term_names( int $post_id, string $taxonomy ): array {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}
		return wp_list_pluck( $terms, 'name' );
	}

	/**
	 * Parse keywords meta into a clean array.
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	private static function parse_keywords( int $post_id ): array {
		$raw = get_post_meta( $post_id, '_convoca_assistant_keywords', true );
		if ( empty( $raw ) ) {
			return array();
		}

		$keywords = array_map( 'trim', explode( ',', (string) $raw ) );
		$keywords = array_filter( $keywords, function ( $kw ) {
			return strlen( $kw ) > 1;
		} );

		return array_values( $keywords );
	}

	/* ── Dirty flag & debounce ──────────────────── */

	/**
	 * Whether the index needs regeneration.
	 *
	 * @return bool
	 */
	public static function is_dirty(): bool {
		return false !== get_transient( 'convoca_assistant_index_dirty' );
	}

	/**
	 * Mark the index as dirty with debounce.
	 *
	 * Debounce: if marked dirty within the last DEBOUNCE_WINDOW
	 * seconds by a previous call, skip to avoid flooding.
	 *
	 * @return void
	 */
	public static function mark_dirty(): void {
		$last = get_transient( 'convoca_assistant_index_debounce' );
		if ( $last && ( time() - (int) $last ) < self::DEBOUNCE_WINDOW ) {
			return;
		}

		set_transient( 'convoca_assistant_index_dirty', time(), HOUR_IN_SECONDS );
		set_transient( 'convoca_assistant_index_debounce', time(), self::DEBOUNCE_WINDOW + 5 );
	}

	/**
	 * React to metadata changes that affect the index.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public static function on_meta_change( int $meta_id, int $post_id, string $meta_key, $meta_value ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$relevant = array(
			'_convoca_assistant_keywords',
			'_convoca_assistant_weight',
			'_convoca_assistant_exclude',
		);

		if ( in_array( $meta_key, $relevant, true ) ) {
			self::mark_dirty();
		}
	}

	/* ── File management ────────────────────────── */

	/**
	 * Check if index exists and is valid.
	 *
	 * @return bool
	 */
	public static function index_exists(): bool {
		$file = CONVOCA_ASSISTANT_INDEX_DIR . 'index.json';
		return file_exists( $file ) && filesize( $file ) > 50;
	}

	/**
	 * Get the index file URL with cache-busting hash.
	 *
	 * @return string
	 */
	public static function get_index_url(): string {
		$upload   = wp_upload_dir();
		$hash     = get_option( 'convoca_assistant_index_hash', '' );
		$url      = $upload['baseurl'] . '/convoca-assistant/index.json';

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
	 * Get the index file path.
	 *
	 * @return string
	 */
	public static function get_index_path(): string {
		return CONVOCA_ASSISTANT_INDEX_DIR . 'index.json';
	}

	/**
	 * Get index file size with format.
	 *
	 * @return string Human-readable size or '—'.
	 */
	public static function get_index_size(): string {
		$file = self::get_index_path();
		if ( ! file_exists( $file ) ) {
			return '—';
		}
		$size = filesize( $file );
		return $size ? size_format( $size ) ?: $size . ' bytes' : '—';
	}

	/**
	 * Get index stats for the dashboard.
	 *
	 * @return array{exists: bool, total: int, size: string, generated: string, hash: string, dirty: bool}
	 */
	public static function get_stats(): array {
		$index_file = self::get_index_path();
		$entries    = 0;

		if ( file_exists( $index_file ) ) {
			$data = file_get_contents( $index_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( $data ) {
				$decoded = json_decode( $data, true );
				$entries = is_array( $decoded ) ? count( $decoded['entries'] ?? array() ) : 0;
			}
		}

		$generated = get_option( 'convoca_assistant_index_generated', 0 );

		return array(
			'exists'    => self::index_exists(),
			'total'     => $entries,
			'size'      => self::get_index_size(),
			'generated' => $generated ? sprintf(
				/* translators: %s: human-readable time diff */
				__( 'Hace %s', 'convoca-assistant' ),
				human_time_diff( $generated )
			) : __( 'Nunca', 'convoca-assistant' ),
			'hash'      => get_option( 'convoca_assistant_index_hash', '' ),
			'dirty'     => self::is_dirty(),
		);
	}

	/**
	 * Delete all index files from disk.
	 *
	 * @return void
	 */
	public static function clear_index_files(): void {
		$dir = CONVOCA_ASSISTANT_INDEX_DIR;
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = glob( $dir . 'index.*' );
		if ( is_array( $files ) ) {
			array_map( 'unlink', $files );
		}
	}
}

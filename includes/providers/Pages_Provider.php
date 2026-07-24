<?php
/**
 * Pages Provider: indexes WordPress pages.
 *
 * @package Convoca\Assistant\Providers
 */

namespace Convoca\Assistant\Providers;

/**
 * Provider for standard 'page' post type.
 */
class Pages_Provider extends Posts_Provider {

	/**
	 * Get provider ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'page';
	}

	/**
	 * Get provider name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Páginas', 'convoca-assistant' );
	}

	/**
	 * Get provider description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Páginas estáticas de WordPress', 'convoca-assistant' );
	}

	/**
	 * Whether this provider is available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return post_type_exists( 'page' );
	}

	/**
	 * Get all entries for indexing.
	 *
	 * @param int $max_content Maximum content length.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_entries( int $max_content = 5000 ): array {
		$entries = array();
		$query   = new \WP_Query(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'OR',
					array( 'key' => '_convoca_assistant_exclude', 'compare' => 'NOT EXISTS' ),
					array( 'key' => '_convoca_assistant_exclude', 'value' => '0', 'compare' => '=' ),
				),
				'no_found_rows'  => true,
			)
		);

		foreach ( $query->posts as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}
			$entries[] = $this->build_entry( $post, $max_content );
		}

		return $entries;
	}

	/**
	 * Get relations for a page (pages typically have fewer relations).
	 *
	 * @param int $entry_id Post ID.
	 * @return array
	 */
	public function get_relations( int $entry_id ): array {
		return array(); // Pages don't have categories/tags by default.
	}

	/**
	 * Get default weight.
	 *
	 * @return float
	 */
	public function get_default_weight(): float {
		return 1.0;
	}

	/**
	 * Get settings key.
	 *
	 * @return string
	 */
	public function get_setting_key(): string {
		return 'source_page';
	}
}

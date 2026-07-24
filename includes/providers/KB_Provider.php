<?php
/**
 * KB Provider: indexes convoca_kb custom post type (Knowledge Base).
 *
 * @package Convoca\Assistant\Providers
 */

namespace Convoca\Assistant\Providers;

/**
 * Provider for 'convoca_kb' custom post type.
 */
class KB_Provider extends Posts_Provider {

	/**
	 * Get provider ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'convoca_kb';
	}

	/**
	 * Get provider name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Base de Conocimiento', 'convoca-assistant' );
	}

	/**
	 * Get provider description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Artículos de la base de conocimiento', 'convoca-assistant' );
	}

	/**
	 * Whether this provider is available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return post_type_exists( 'convoca_kb' );
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
				'post_type'      => 'convoca_kb',
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
	 * Get relations for a KB article (via convoca_kb_cat taxonomy).
	 *
	 * @param int $entry_id Post ID.
	 * @return array
	 */
	public function get_relations( int $entry_id ): array {
		$relations = array();
		$terms     = wp_get_post_terms( $entry_id, 'convoca_kb_cat', array( 'fields' => 'ids' ) );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return $relations;
		}

		$related = new \WP_Query(
			array(
				'post_type'      => 'convoca_kb',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => 'convoca_kb_cat',
						'field'    => 'term_id',
						'terms'    => $terms,
					),
				),
				'post__not_in'   => array( $entry_id ),
				'no_found_rows'  => true,
			)
		);

		foreach ( $related->posts as $rid ) {
			$relations[] = array(
				'to'     => (int) $rid,
				'type'   => 'same_kb_category',
				'weight' => 0.3,
			);
		}

		return $relations;
	}

	/**
	 * Get default weight.
	 *
	 * @return float
	 */
	public function get_default_weight(): float {
		return 1.5;
	}

	/**
	 * Get settings key.
	 *
	 * @return string
	 */
	public function get_setting_key(): string {
		return 'source_convoca_kb';
	}
}

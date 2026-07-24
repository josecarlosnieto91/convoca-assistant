<?php
/**
 * Posts Provider: indexes standard WordPress posts.
 *
 * @package Convoca\Assistant\Providers
 */

namespace Convoca\Assistant\Providers;

use Convoca\Assistant\Knowledge_Provider_Interface;

/**
 * Provider for standard 'post' post type.
 */
class Posts_Provider implements Knowledge_Provider_Interface {

	/**
	 * Get provider ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'post';
	}

	/**
	 * Get provider name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Entradas', 'convoca-assistant' );
	}

	/**
	 * Get provider description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Entradas estándar de WordPress', 'convoca-assistant' );
	}

	/**
	 * Whether this provider is available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return post_type_exists( 'post' );
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
				'post_type'      => 'post',
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
	 * Get entry count.
	 *
	 * @return int
	 */
	public function get_entry_count(): int {
		$query = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * Get relations for a post.
	 *
	 * @param int $entry_id Post ID.
	 * @return array<int, array{to: int, type: string, weight: float}>
	 */
	public function get_relations( int $entry_id ): array {
		$relations = array();
		$post      = get_post( $entry_id );

		if ( ! $post ) {
			return $relations;
		}

		// Same category relations.
		$categories = wp_get_post_categories( $entry_id, array( 'fields' => 'ids' ) );
		if ( ! empty( $categories ) ) {
			$cat_query = new \WP_Query(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'posts_per_page' => 10,
					'fields'         => 'ids',
					'category__in'   => $categories,
					'post__not_in'   => array( $entry_id ),
					'no_found_rows'  => true,
				)
			);
			foreach ( $cat_query->posts as $related_id ) {
				$relations[] = array(
					'to'     => (int) $related_id,
					'type'   => 'same_category',
					'weight' => 0.3,
				);
			}
		}

		// Same tags relations.
		$tags = wp_get_post_tags( $entry_id, array( 'fields' => 'ids' ) );
		if ( ! empty( $tags ) ) {
			$tag_query = new \WP_Query(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'posts_per_page' => 10,
					'fields'         => 'ids',
					'tag__in'        => $tags,
					'post__not_in'   => array( $entry_id ),
					'no_found_rows'  => true,
				)
			);
			foreach ( $tag_query->posts as $related_id ) {
				$relations[] = array(
					'to'     => (int) $related_id,
					'type'   => 'same_tag',
					'weight' => 0.4,
				);
			}
		}

		return $relations;
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
		return 'source_post';
	}

	/**
	 * Build a single index entry from a post.
	 *
	 * @param \WP_Post $post        Post object.
	 * @param int      $max_content Max content length.
	 * @return array<string, mixed>
	 */
	private function build_entry( \WP_Post $post, int $max_content ): array {
		$raw     = get_the_content( null, false, $post );
		$content = self::clean_content( $raw ?? '' );
		$content = mb_substr( $content, 0, $max_content );

		return array(
			'id'         => $post->ID,
			'type'       => 'post',
			'title'      => $post->post_title,
			'content'    => $content,
			'excerpt'    => self::clean_content( (string) get_the_excerpt( $post ) ),
			'url'        => get_permalink( $post ),
			'thumbnail'  => get_the_post_thumbnail_url( $post, 'thumbnail' ) ?: '',
			'categories' => self::get_term_names( $post->ID, 'category' ),
			'tags'       => self::get_term_names( $post->ID, 'post_tag' ),
			'keywords'   => self::parse_keywords( $post->ID ),
			'weight'     => self::get_effective_weight( $post->ID ),
			'date'       => $post->post_date,
			'modified'   => $post->post_modified,
		);
	}

	/**
	 * Clean content: strip blocks, shortcodes, tags.
	 *
	 * @param string $raw Raw content.
	 * @return string
	 */
	private static function clean_content( string $raw ): string {
		$text = preg_replace( '/<!--\s*wp:.*?-->/s', '', $raw );
		$text = preg_replace( '/<!--\s*\/wp:.*?-->/s', '', $text );
		$text = strip_shortcodes( $text ?? '' );
		$text = wp_strip_all_tags( $text ?? '' );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text ?? '' );
		return trim( $text ?? '' );
	}

	/**
	 * Get term names for a post.
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
	 * Parse keywords from post meta.
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
		$keywords = array_filter( $keywords, fn( $kw ) => strlen( $kw ) > 1 );
		return array_values( $keywords );
	}

	/**
	 * Get effective weight (individual or default).
	 *
	 * @param int $post_id Post ID.
	 * @return float
	 */
	private static function get_effective_weight( int $post_id ): float {
		$individual = get_post_meta( $post_id, '_convoca_assistant_weight', true );
		return ! empty( $individual ) ? (float) $individual : 1.0;
	}
}

<?php
/**
 * WooCommerce Provider: indexes WooCommerce products.
 *
 * @package Convoca\Assistant\Providers
 */

namespace Convoca\Assistant\Providers;

use Convoca\Assistant\Knowledge_Provider_Interface;

/**
 * Provider for WooCommerce products.
 */
class WooCommerce_Provider implements Knowledge_Provider_Interface {

	/**
	 * Get provider ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'product';
	}

	/**
	 * Get provider name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Productos WooCommerce', 'convoca-assistant' );
	}

	/**
	 * Get provider description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Productos con precio y SKU', 'convoca-assistant' );
	}

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return class_exists( 'WooCommerce' );
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
				'post_type'      => 'product',
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

			$entry = $this->build_entry( $post, $max_content );

			// WooCommerce extras.
			$product = \wc_get_product( $post_id );
			if ( $product ) {
				$entry['price'] = \wc_price( $product->get_price() );
				$entry['sku']   = $product->get_sku();
			}

			$entries[] = $entry;
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
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * Get relations for a product.
	 *
	 * @param int $entry_id Product ID.
	 * @return array
	 */
	public function get_relations( int $entry_id ): array {
		$relations = array();

		// Same category.
		$terms = wp_get_post_terms( $entry_id, 'product_cat', array( 'fields' => 'ids' ) );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			$related = new \WP_Query(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => 10,
					'fields'         => 'ids',
					'tax_query'      => array(
						array(
							'taxonomy' => 'product_cat',
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
					'type'   => 'same_product_category',
					'weight' => 0.3,
				);
			}
		}

		// Same tag.
		$tags = wp_get_post_terms( $entry_id, 'product_tag', array( 'fields' => 'ids' ) );
		if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
			$related = new \WP_Query(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => 10,
					'fields'         => 'ids',
					'tax_query'      => array(
						array(
							'taxonomy' => 'product_tag',
							'field'    => 'term_id',
							'terms'    => $tags,
						),
					),
					'post__not_in'   => array( $entry_id ),
					'no_found_rows'  => true,
				)
			);
			foreach ( $related->posts as $rid ) {
				$relations[] = array(
					'to'     => (int) $rid,
					'type'   => 'same_product_tag',
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
		return 0.8;
	}

	/**
	 * Get settings key.
	 *
	 * @return string
	 */
	public function get_setting_key(): string {
		return 'source_woocommerce';
	}

	/**
	 * Build a single index entry.
	 *
	 * @param \WP_Post $post        Post object.
	 * @param int      $max_content Max content length.
	 * @return array<string, mixed>
	 */
	private function build_entry( \WP_Post $post, int $max_content ): array {
		$raw     = get_the_content( null, false, $post );
		$content = $this->clean_content( $raw ?? '' );
		$content = mb_substr( $content, 0, $max_content );

		return array(
			'id'         => $post->ID,
			'type'       => 'product',
			'title'      => $post->post_title,
			'content'    => $content,
			'excerpt'    => $this->clean_content( (string) get_the_excerpt( $post ) ),
			'url'        => get_permalink( $post ),
			'thumbnail'  => get_the_post_thumbnail_url( $post, 'thumbnail' ) ?: '',
			'categories' => $this->get_term_names( $post->ID, 'product_cat' ),
			'tags'       => $this->get_term_names( $post->ID, 'product_tag' ),
			'keywords'   => $this->parse_keywords( $post->ID ),
			'weight'     => $this->get_effective_weight( $post->ID ),
			'date'       => $post->post_date,
			'modified'   => $post->post_modified,
		);
	}

	/**
	 * Clean content.
	 *
	 * @param string $raw Raw content.
	 * @return string
	 */
	private function clean_content( string $raw ): string {
		$text = preg_replace( '/<!--\s*wp:.*?-->/s', '', $raw );
		$text = preg_replace( '/<!--\s*\/wp:.*?-->/s', '', $text );
		$text = strip_shortcodes( $text ?? '' );
		$text = wp_strip_all_tags( $text ?? '' );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text ?? '' );
		return trim( $text ?? '' );
	}

	/**
	 * Get term names.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return string[]
	 */
	private function get_term_names( int $post_id, string $taxonomy ): array {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}
		return wp_list_pluck( $terms, 'name' );
	}

	/**
	 * Parse keywords from meta.
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	private function parse_keywords( int $post_id ): array {
		$raw = get_post_meta( $post_id, '_convoca_assistant_keywords', true );
		if ( empty( $raw ) ) {
			return array();
		}
		$keywords = array_map( 'trim', explode( ',', (string) $raw ) );
		$keywords = array_filter( $keywords, fn( $kw ) => strlen( $kw ) > 1 );
		return array_values( $keywords );
	}

	/**
	 * Get effective weight.
	 *
	 * @param int $post_id Post ID.
	 * @return float
	 */
	private function get_effective_weight( int $post_id ): float {
		$individual = get_post_meta( $post_id, '_convoca_assistant_weight', true );
		return ! empty( $individual ) ? (float) $individual : 0.8;
	}
}

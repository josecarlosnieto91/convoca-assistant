<?php
/**
 * Interface for knowledge providers.
 *
 * Any source of content that can be indexed and searched
 * must implement this interface.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

/**
 * Contract for knowledge sources.
 *
 * Implement this to register custom content types
 * as searchable knowledge sources.
 */
interface Knowledge_Provider_Interface {

	/**
	 * Unique provider identifier (e.g. 'post', 'woocommerce').
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Human-readable name (e.g. 'Entradas', 'Productos WooCommerce').
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Description of what this provider indexes.
	 *
	 * @return string
	 */
	public function get_description(): string;

	/**
	 * Whether this provider is available on the current site.
	 * (e.g., WooCommerce provider returns false if WC not active)
	 *
	 * @return bool
	 */
	public function is_available(): bool;

	/**
	 * Get all entries to index.
	 *
	 * Each entry is an associative array with keys:
	 *   id, type, title, content, excerpt, url, thumbnail,
	 *   categories, tags, keywords, weight, date, modified
	 *
	 * @param int $max_content Maximum content length in characters.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_entries( int $max_content = 5000 ): array;

	/**
	 * Count of available entries.
	 *
	 * @return int
	 */
	public function get_entry_count(): int;

	/**
	 * Get relations for a specific entry.
	 *
	 * Returns array of edges: [['to' => post_id, 'type' => 'same_category', 'weight' => 0.3], ...]
	 *
	 * @param int $entry_id Post ID.
	 * @return array<int, array{to: int, type: string, weight: float}>
	 */
	public function get_relations( int $entry_id ): array;

	/**
	 * Default search weight for this source.
	 *
	 * @return float
	 */
	public function get_default_weight(): float;

	/**
	 * Settings key for enabling/disabling this source.
	 *
	 * @return string
	 */
	public function get_setting_key(): string;
}

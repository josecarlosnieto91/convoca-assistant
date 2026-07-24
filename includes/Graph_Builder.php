<?php
/**
 * Graph Builder: generates the knowledge graph (graph.json).
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

/**
 * Builds and manages the weighted knowledge graph that relates
 * content nodes through shared taxonomies, internal links, and
 * custom relations from providers.
 */
class Graph_Builder {

	/**
	 * Build the full knowledge graph.
	 *
	 * Collects edges from all active providers, deduplicates,
	 * and returns the graph structure.
	 *
	 * @return array{version: string, generated: int, nodes: int, edges: array}
	 */
	public static function build(): array {
		/**
		 * Fires before the graph is built.
		 */
		do_action( 'convoca_assistant/before_graph' );

		$edges = Provider_Registry::collect_relations();
		$nodes = array();
		$seen  = array();

		// Collect unique node IDs.
		foreach ( $edges as $edge ) {
			$nodes[ $edge['from'] ] = true;
			$nodes[ $edge['to'] ]   = true;
		}

		// Fallback: add edges between same-type entries from the index.
		$index_path = Indexer::get_index_path();
		if ( file_exists( $index_path ) ) {
			$index_data = json_decode( file_get_contents( $index_path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$by_type    = array();
			if ( ! empty( $index_data['entries'] ) ) {
				foreach ( $index_data['entries'] as $entry ) {
					$type = $entry['type'] ?? 'unknown';
					$by_type[ $type ][] = $entry['id'];
				}
				foreach ( $by_type as $type => $ids ) {
					if ( ! empty( $nodes[ $ids[0] ] ?? false ) ) {
						continue; // Already has edges from provider.
					}
					for ( $i = 1; $i < count( $ids ); $i++ ) {
						$edge_key = min( $ids[ $i - 1 ], $ids[ $i ] ) . '-' . max( $ids[ $i - 1 ], $ids[ $i ] );
						if ( ! isset( $seen[ $edge_key ] ) ) {
							$edges[] = array(
								'from'   => $ids[ $i - 1 ],
								'to'     => $ids[ $i ],
								'type'   => 'same_type_' . $type,
								'weight' => 0.15,
							);
							$nodes[ $ids[ $i - 1 ] ] = true;
							$nodes[ $ids[ $i ] ]     = true;
							$seen[ $edge_key ]       = true;
						}
					}
				}
			}
		}

		/**
		 * Filter the graph edges before serialization.
		 *
		 * @param array $edges Array of edge definitions.
		 */
		$edges = apply_filters( 'convoca_assistant/graph_data', $edges );

		$graph = array(
			'version'   => CONVOCA_ASSISTANT_VERSION,
			'generated' => time(),
			'nodes'     => count( $nodes ),
			'edges'     => array_values( $edges ),
		);

		return $graph;
	}

	/**
	 * Write graph.json to disk.
	 *
	 * @return array{success: bool, nodes: int, edges: int, size: int}
	 */
	public static function write(): array {
		$graph = self::build();

		$dir = CONVOCA_ASSISTANT_INDEX_DIR;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$json = wp_json_encode( $graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$size = file_put_contents( $dir . 'graph.json', $json ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		/**
		 * Fires after the graph has been written.
		 *
		 * @param array $graph The complete graph data.
		 */
		do_action( 'convoca_assistant/after_graph', $graph );

		return array(
			'success' => true,
			'nodes'   => $graph['nodes'],
			'edges'   => count( $graph['edges'] ),
			'size'    => $size ?: 0,
		);
	}

	/**
	 * Load graph.json from disk.
	 *
	 * @return array|null
	 */
	public static function load(): ?array {
		$file = CONVOCA_ASSISTANT_INDEX_DIR . 'graph.json';
		if ( ! file_exists( $file ) ) {
			return null;
		}
		$data = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! $data ) {
			return null;
		}
		return json_decode( $data, true );
	}

	/**
	 * Get graph score for an entry ID.
	 *
	 * How many edges connect to this node, normalized.
	 *
	 * @param int   $entry_id Entry post ID.
	 * @param array $graph    Loaded graph data.
	 * @return float Score 0-1.
	 */
	public static function get_node_score( int $entry_id, array $graph ): float {
		if ( empty( $graph['edges'] ) ) {
			return 0.0;
		}

		$edge_count  = 0;
		$total_edges = count( $graph['edges'] );

		foreach ( $graph['edges'] as $edge ) {
			if ( (int) $edge['from'] === $entry_id || (int) $edge['to'] === $entry_id ) {
				$edge_count++;
			}
		}

		return $total_edges > 0 ? min( $edge_count / sqrt( $total_edges ), 1.0 ) : 0.0;
	}

	/**
	 * Get related entry IDs for a given entry from the graph.
	 *
	 * @param int   $entry_id Post ID.
	 * @param array $graph    Loaded graph.
	 * @param int   $limit    Max related entries.
	 * @return array<int, array{id: int, type: string, weight: float}>
	 */
	public static function get_related( int $entry_id, array $graph, int $limit = 5 ): array {
		$related = array();

		foreach ( $graph['edges'] as $edge ) {
			$related_id = null;
			$weight     = (float) $edge['weight'];

			if ( (int) $edge['from'] === $entry_id ) {
				$related_id = (int) $edge['to'];
			} elseif ( (int) $edge['to'] === $entry_id ) {
				$related_id = (int) $edge['from'];
			}

			if ( null !== $related_id && ! isset( $related[ $related_id ] ) ) {
				$related[ $related_id ] = array(
					'id'     => $related_id,
					'type'   => $edge['type'],
					'weight' => $weight,
				);
			}
		}

		usort( $related, function ( $a, $b ) {
			return $b['weight'] <=> $a['weight'];
		} );

		return array_slice( array_values( $related ), 0, $limit );
	}
}

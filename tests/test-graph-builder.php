<?php
/**
 * Tests for the Graph_Builder class.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Graph_Builder;
use WP_UnitTestCase;

/**
 * @coversDefaultClass \Convoca\Assistant\Graph_Builder
 */
class Test_Graph_Builder extends WP_UnitTestCase {

	/**
	 * Test graph score calculation.
	 */
	public function test_get_node_score(): void {
		$graph = array(
			'version'   => '1.0',
			'generated' => time(),
			'nodes'     => 3,
			'edges'     => array(
				array( 'from' => 1, 'to' => 2, 'type' => 'same_category', 'weight' => 0.3 ),
				array( 'from' => 1, 'to' => 3, 'type' => 'same_tag', 'weight' => 0.4 ),
				array( 'from' => 2, 'to' => 3, 'type' => 'same_category', 'weight' => 0.3 ),
			),
		);

		$score1 = Graph_Builder::get_node_score( 1, $graph );
		$score2 = Graph_Builder::get_node_score( 2, $graph );
		$score4 = Graph_Builder::get_node_score( 999, $graph );

		$this->assertGreaterThan( 0, $score1, 'Node 1 has 2 edges' );
		$this->assertGreaterThan( 0, $score2, 'Node 2 has 2 edges' );
		$this->assertEquals( 0, $score4, 'Node 999 has 0 edges' );
	}

	/**
	 * Test get_related returns related entries sorted by weight.
	 */
	public function test_get_related(): void {
		$graph = array(
			'version'   => '1.0',
			'generated' => time(),
			'nodes'     => 4,
			'edges'     => array(
				array( 'from' => 1, 'to' => 2, 'type' => 'same_category', 'weight' => 0.3 ),
				array( 'from' => 1, 'to' => 3, 'type' => 'same_tag', 'weight' => 0.4 ),
				array( 'from' => 2, 'to' => 3, 'type' => 'same_category', 'weight' => 0.3 ),
			),
		);

		$related = Graph_Builder::get_related( 1, $graph, 5 );

		$this->assertCount( 2, $related );
		$this->assertEquals( 3, $related[0]['id'] );
		$this->assertEquals( 2, $related[1]['id'] );
	}

	/**
	 * Test build returns valid graph structure.
	 */
	public function test_build_structure(): void {
		$graph = Graph_Builder::build();
		$this->assertArrayHasKey( 'version', $graph );
		$this->assertArrayHasKey( 'edges', $graph );
		$this->assertArrayHasKey( 'nodes', $graph );
	}
}

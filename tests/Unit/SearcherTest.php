<?php
/**
 * Tests for the Searcher class.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Searcher;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Convoca\Assistant\Searcher
 */
class SearcherTest extends TestCase {


	/**
	 * Test score is low for unrelated content.
	 */
	public function test_low_score_for_unrelated(): void {
		$ref = new \ReflectionMethod( Searcher::class, 'calculate_score' );
		$ref->setAccessible( true );

		$entry = array(
			'title'      => 'Historia del club de lectura',
			'content'    => 'El club de lectura se fundó en 2010.',
			'keywords'   => array( 'club', 'lectura', 'historia' ),
			'categories' => array(),
			'tags'       => array(),
			'weight'     => 1.0,
			'date'       => '2020-01-01 00:00:00',
		);

		$score = $ref->invoke( null, $entry, 'como pagar la cuota', array( 'como', 'pagar', 'cuota' ), array( 'como', 'pagar', 'cuota' ) );

		$this->assertLessThan( 0.3, $score, 'Score should be low for unrelated content' );
	}
}

<?php
/**
 * Tests for the Searcher class.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant\Tests;

use Convoca\Assistant\Searcher;
use WP_UnitTestCase;

/**
 * @coversDefaultClass \Convoca\Assistant\Searcher
 */
class Test_Searcher extends WP_UnitTestCase {

	/**
	 * Test that search returns empty array for empty query.
	 */
	public function test_search_empty_query(): void {
		$results = Searcher::search( '' );
		$this->assertIsArray( $results );
		$this->assertEmpty( $results );
	}

	/**
	 * Test normalization: lowercase, accents, punctuation.
	 */
	public function test_normalize_spanish(): void {
		// Use reflection to test private normalize.
		$ref = new \ReflectionMethod( Searcher::class, 'normalize' );
		$ref->setAccessible( true );

		$result = $ref->invoke( null, '¿Cómo estás?' );
		$this->assertEquals( 'como estas', $result );

		$result = $ref->invoke( null, '¡Hola, mundo!' );
		$this->assertEquals( 'hola mundo', $result );
	}

	/**
	 * Test stemmer removes common suffixes.
	 */
	public function test_stem_spanish(): void {
		$ref = new \ReflectionMethod( Searcher::class, 'stem_spanish' );
		$ref->setAccessible( true );

		$this->assertStringContainsString( 'inscripc', $ref->invoke( null, 'inscripción' ) );
		$this->assertStringContainsString( 'registr', $ref->invoke( null, 'registro' ) );
		$this->assertStringContainsString( 'pregunt', $ref->invoke( null, 'pregunta' ) );
	}

	/**
	 * Test tokenization removes stop words.
	 */
	public function test_tokenize_removes_stop_words(): void {
		$ref = new \ReflectionMethod( Searcher::class, 'tokenize' );
		$ref->setAccessible( true );

		$stop_words = array( 'el', 'la', 'de', 'en', 'y' );
		$result     = $ref->invoke( null, 'el gato en la casa y el perro', $stop_words );

		$this->assertNotContains( 'el', $result );
		$this->assertNotContains( 'la', $result );
		$this->assertContains( 'gato', $result );
		$this->assertContains( 'perro', $result );
	}

	/**
	 * Test synonym expansion.
	 */
	public function test_expand_synonyms(): void {
		$ref = new \ReflectionMethod( Searcher::class, 'expand_synonyms' );
		$ref->setAccessible( true );

		$synonyms = array(
			'ordenador' => array( 'computadora', 'pc', 'equipo' ),
		);

		$result = $ref->invoke( null, array( 'ordenador' ), $synonyms );

		$this->assertContains( 'computadora', $result );
		$this->assertContains( 'pc', $result );
		$this->assertContains( 'equipo', $result );
	}

	/**
	 * Test composite score calculation.
	 */
	public function test_calculate_score(): void {
		$ref = new \ReflectionMethod( Searcher::class, 'calculate_score' );
		$ref->setAccessible( true );

		$entry = array(
			'title'      => 'Cómo registrarse en la asociación',
			'content'    => 'Para registrarse como socio debe completar el formulario de inscripción.',
			'keywords'   => array( 'registro', 'inscripción', 'socio' ),
			'categories' => array( 'Socios' ),
			'tags'       => array(),
			'weight'     => 2.0,
			'date'       => gmdate( 'Y-m-d H:i:s', time() - 10 * DAY_IN_SECONDS ),
		);

		$score = $ref->invoke( null, $entry, 'como registrarse', array( 'como', 'registrarse' ), array( 'como', 'registrarse' ) );

		$this->assertIsFloat( $score );
		$this->assertGreaterThan( 0.5, $score, 'Score should be high for exact title match' );
	}

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

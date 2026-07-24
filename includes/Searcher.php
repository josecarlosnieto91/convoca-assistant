<?php
/**
 * Searcher: server-side fallback search engine.
 *
 * Primary search happens client-side via Fuse.js.
 * This class provides an equivalent server-side fallback
 * for JS-disabled browsers, crawlers, and REST API calls.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

/**
 * Server-side search using Levenshtein distance and the same
 * composite scoring algorithm as the client-side Fuse.js engine.
 */
class Searcher {

	private const SCORE_THRESHOLD = 0.10;

	/**
	 * Perform a search query against the knowledge index.
	 *
	 * @param string $query       The user's search query.
	 * @param int    $max_results Maximum results to return.
	 * @param float  $threshold   Minimum score threshold override.
	 * @return array<int, array<string, mixed>>
	 */
	public static function search( string $query, int $max_results = 10, float $threshold = 0.10 ): array {
		/**
		 * Fires before a search is executed.
		 *
		 * @param string $query The search query.
		 */
		do_action( 'convoca_assistant/before_search', $query );

		$index_data = self::load_index();
		if ( ! $index_data || empty( $index_data['entries'] ) ) {
			return array();
		}

		$normalized = self::normalize( $query );
		$stop_words = $index_data['stop_words'] ?? Installer::default_stop_words();
		$tokens     = self::tokenize( $normalized, $stop_words );

		if ( empty( $tokens ) ) {
			return array();
		}

		$synonyms = $index_data['synonyms'] ?? array();
		$expanded = self::expand_synonyms( $tokens, $synonyms );
		$use_threshold = max( $threshold, self::SCORE_THRESHOLD );

		$results = array();

		foreach ( $index_data['entries'] as $entry ) {
			$score = self::calculate_score( $entry, $normalized, $tokens, $expanded );

			if ( $score >= $use_threshold ) {
				$results[] = array(
					'entry' => $entry,
					'score' => round( $score, 4 ),
				);
			}
		}

		usort( $results, function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );

		return array_slice( $results, 0, $max_results );
	}

	/* ── Composite Score ────────────────────────── */

	/**
	 * Calculate the composite score for a single entry.
	 *
	 * Formula: same as client-side JS engine.
	 *
	 * @param array  $entry     Knowledge entry.
	 * @param string $query     Normalized query string.
	 * @param array  $tokens    Tokenized query words.
	 * @param array  $expanded  Query words expanded with synonyms.
	 * @return float Score 0-1.
	 */
	private static function calculate_score( array $entry, string $query, array $tokens, array $expanded ): float {
		$title_lower    = mb_strtolower( $entry['title'] ?? '' );
		$content_lower  = mb_strtolower( $entry['content'] ?? '' );
		$keywords_str   = mb_strtolower( implode( ' ', $entry['keywords'] ?? array() ) );
		$categories_str = mb_strtolower( implode( ' ', $entry['categories'] ?? array() ) );
		$tags_str       = mb_strtolower( implode( ' ', $entry['tags'] ?? array() ) );
		$excerpt_lower  = mb_strtolower( $entry['excerpt'] ?? '' );

		// 1) Fuzzy score via Levenshtein on title.
		$fuzzy_score = self::fuzzy_match( $title_lower, $tokens );

		// 2) Exact match bonus.
		$exact_bonus = 0.0;
		if ( false !== mb_strpos( $title_lower, $query ) ) {
			$exact_bonus = 0.15;
		} elseif ( false !== mb_strpos( $keywords_str, $query ) ) {
			$exact_bonus = 0.10;
		} elseif ( false !== mb_strpos( $content_lower, $query ) ) {
			$exact_bonus = 0.05;
		}

		// 3) Synonym bonus.
		$synonym_bonus = self::synonym_bonus( $content_lower . ' ' . $title_lower, $tokens, $expanded );

		// 4) Stem bonus.
		$stem_bonus = self::stem_bonus( $tokens, $title_lower, $content_lower, $keywords_str );

		// 5) Coverage score.
		$coverage = self::coverage_score( $tokens, $title_lower, $content_lower, $keywords_str, $categories_str, $tags_str, $excerpt_lower );

		// 6) Recency bonus.
		$recency = self::recency_bonus( $entry['date'] ?? $entry['modified'] ?? '' );

		// 7) Graph score (how connected this entry is in the knowledge graph).
		$graph_score = self::graph_score( $entry['id'] ?? 0 );

		// 8) Weight factor.
		$weight = (float) ( $entry['weight'] ?? 1.0 );

		// Composite with graph score (20%).
		$score = ( $fuzzy_score   * 0.40 )
			   + ( $graph_score   * 0.20 )
			   + ( $exact_bonus   * 0.10 )
			   + ( $synonym_bonus * 0.10 )
			   + ( $stem_bonus    * 0.05 )
			   + ( $coverage      * 0.05 )
			   + ( $recency       * 0.05 )
			   + ( ( $weight / 10.0 ) * 0.05 );

		// Boost from weight multiplier.
		$score = $score * ( 0.5 + ( $weight / 20.0 ) );

		return min( $score, 1.0 );
	}

	/**
	 * Levenshtein fuzzy matching on title.
	 *
	 * @param string $title  Title text (lowercase).
	 * @param array  $tokens Tokenized query words.
	 * @return float Score 0-1.
	 */
	private static function fuzzy_match( string $title, array $tokens ): float {
		if ( empty( $title ) || empty( $tokens ) ) {
			return 0.0;
		}

		$max_score   = 0.0;
		$title_words = explode( ' ', $title );

		foreach ( $tokens as $query_word ) {
			foreach ( $title_words as $title_word ) {
				$len = max( strlen( $query_word ), strlen( $title_word ) );
				if ( 0 === $len ) {
					continue;
				}
				$lev       = levenshtein( $query_word, $title_word );
				$word_score = 1.0 - ( $lev / $len );
				if ( $word_score > $max_score ) {
					$max_score = $word_score;
				}
			}
		}

		return $max_score;
	}

	/**
	 * Bonus when synonyms appear in content.
	 *
	 * @param string $text     Combined text.
	 * @param array  $tokens   Original words.
	 * @param array  $expanded Expanded words.
	 * @return float
	 */
	private static function synonym_bonus( string $text, array $tokens, array $expanded ): float {
		$extra = array_diff( $expanded, $tokens );
		if ( empty( $extra ) ) {
			return 0.0;
		}

		$hits = 0;
		foreach ( $extra as $syn ) {
			if ( false !== mb_strpos( $text, $syn ) ) {
				++$hits;
			}
		}

		return ( $hits / count( $extra ) ) * 0.10;
	}

	/**
	 * Stem matching bonus.
	 *
	 * @param array  $tokens        Query words.
	 * @param string $title         Lowercase title.
	 * @param string $content       Lowercase content.
	 * @param string $keywords      Lowercase keywords.
	 * @return float
	 */
	private static function stem_bonus( array $tokens, string $title, string $content, string $keywords ): float {
		$search_space = $title . ' ' . $content . ' ' . $keywords;
		$hits         = 0;

		foreach ( $tokens as $word ) {
			$stem = self::stem_spanish( $word );
			if ( strlen( $stem ) < 3 ) {
				continue;
			}
			if ( false !== mb_strpos( $search_space, $stem ) ) {
				++$hits;
			}
		}

		return count( $tokens ) > 0 ? ( $hits / count( $tokens ) ) * 0.05 : 0.0;
	}

	/**
	 * Coverage: how many query words appear in the entry.
	 *
	 * @param array  $tokens   Query words.
	 * @param string $title    Title.
	 * @param string $content  Content.
	 * @param string $keywords Keywords.
	 * @param string $cats     Categories.
	 * @param string $tags     Tags.
	 * @param string $excerpt  Excerpt.
	 * @return float
	 */
	private static function coverage_score( array $tokens, string $title, string $content, string $keywords, string $cats, string $tags, string $excerpt ): float {
		if ( empty( $tokens ) ) {
			return 0.0;
		}

		$search_space = "{$title} {$content} {$keywords} {$cats} {$tags} {$excerpt}";
		$matches      = 0;

		foreach ( $tokens as $word ) {
			if ( false !== mb_strpos( $search_space, $word ) ) {
				++$matches;
			}
		}

		return $matches / count( $tokens );
	}

	/**
	 * Recency bonus based on post date.
	 *
	 * @param string $date MySQL date string.
	 * @return float
	 */
	private static function recency_bonus( string $date ): float {
		if ( empty( $date ) ) {
			return 0.0;
		}

		$timestamp = strtotime( $date );
		if ( false === $timestamp ) {
			return 0.0;
		}

		$days_ago = ( time() - $timestamp ) / DAY_IN_SECONDS;

		if ( $days_ago < 30 ) {
			return 0.05;
		}
		if ( $days_ago < 90 ) {
			return 0.03;
		}
		if ( $days_ago < 365 ) {
			return 0.01;
		}
		return 0.0;
	}

	/**
	 * Graph score: how well-connected an entry is in the knowledge graph.
	 *
	 * @param int $entry_id Entry post ID.
	 * @return float Score 0-1.
	 */
	private static function graph_score( int $entry_id ): float {
		static $graph = null;
		if ( null === $graph ) {
			$graph = Graph_Builder::load();
		}
		if ( ! $graph ) {
			return 0.0;
		}
		return Graph_Builder::get_node_score( $entry_id, $graph );
	}

	/* ── Text processing ────────────────────────── */

	/**
	 * Light Spanish stemmer.
	 *
	 * @param string $word Word to stem.
	 * @return string
	 */
	private static function stem_spanish( string $word ): string {
		if ( strlen( $word ) < 4 ) {
			return mb_strtolower( $word );
		}

		$patterns = array(
			'/ando$|iendo$|ando[ns]?$/i',
			'/aba$|abas$|abamos$|abais$|aban$/i',
			'/ia$|ias$|iamos$|iais$|ian$/i',
			'/e$|aste$|o$|amos$|asteis$|aron$/i',
			'/ere$|eras$|era$|eremos$|ereis$|eran$/i',
			'/ire$|iras$|ira$|iremos$|ireis$|iran$/i',
			'/ado$|ido$|ada$|ida$/i',
			'/ar$|er$|ir$/i',
			'/cion$|ciones$/i',
			'/miento$|mientos$/i',
			'/mente$/i',
			'/dor$|dora$|dores$|doras$/i',
			'/ero$|era$|eros$|eras$/i',
			'/ista$|istas$/i',
			'/azo$|aza$|azos$|azas$/i',
			'/ito$|ita$|itos$|itas$/i',
			'/on$|ona$|ones$/i',
			'/ble$|bles$/i',
			'/eza$|ezas$/i',
			'/ivo$|iva$|ivos$|ivas$/i',
		);

		$stemmed = mb_strtolower( $word );
		foreach ( $patterns as $pattern ) {
			$candidate = preg_replace( $pattern, '', $stemmed );
			if ( $candidate !== $stemmed && strlen( $candidate ) >= 3 ) {
				$stemmed = $candidate;
				break;
			}
		}

		return $stemmed;
	}

	/**
	 * Normalize text: lowercase, remove accents, strip punctuation.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private static function normalize( string $text ): string {
		$text = mb_strtolower( trim( $text ) );
		$text = remove_accents( $text );
		$text = preg_replace( '/[¿?!¡,.;:\-\'"«»()\[\]{}]/u', ' ', $text );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return trim( $text );
	}

	/**
	 * Tokenize and remove stop words.
	 *
	 * @param string   $text      Normalized text.
	 * @param string[] $stop_words Words to exclude.
	 * @return string[]
	 */
	private static function tokenize( string $text, array $stop_words ): array {
		$words = explode( ' ', $text );
		$words = array_filter( $words, function ( $w ) use ( $stop_words ) {
			return strlen( $w ) > 1 && ! in_array( $w, $stop_words, true );
		} );
		return array_values( $words );
	}

	/**
	 * Expand query words with synonyms.
	 *
	 * @param string[]               $tokens   Tokenized words.
	 * @param array<string, string[]> $synonyms Synonym dictionary.
	 * @return string[]
	 */
	private static function expand_synonyms( array $tokens, array $synonyms ): array {
		$expanded = $tokens;

		foreach ( $tokens as $token ) {
			foreach ( $synonyms as $term => $syn_list ) {
				$term_lower = mb_strtolower( $term );
				$syn_lower  = array_map( 'mb_strtolower', $syn_list );

				if ( $token === $term_lower || in_array( $token, $syn_lower, true ) ) {
					$expanded = array_merge( $expanded, $syn_lower, array( $term_lower ) );
				}
			}
		}

		return array_unique( $expanded );
	}

	/* ── Index loading ──────────────────────────── */

	/**
	 * Load the knowledge index from disk.
	 *
	 * @return array|null
	 */
	private static function load_index(): ?array {
		$dir    = CONVOCA_ASSISTANT_INDEX_DIR;
		$file   = $dir . 'index.json';

		if ( file_exists( $file ) ) {
			$data = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( $data ) {
				return json_decode( $data, true );
			}
		}

		// Try GZIP fallback.
		$gz_file = $dir . 'index.json.gz';
		if ( file_exists( $gz_file ) ) {
			$data = file_get_contents( $gz_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( $data ) {
				$decompressed = gzdecode( $data );
				if ( $decompressed ) {
					return json_decode( $decompressed, true );
				}
			}
		}

		return null;
	}
}

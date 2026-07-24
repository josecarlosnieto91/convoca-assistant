<?php
/**
 * Synonyms: manages synonyms, stop words, and admin-post handlers.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

/**
 * Handles the synonym dictionary and stop words list used
 * during search indexing and query expansion.
 */
class Synonyms {

	private const SYNONYM_OPTION    = 'convoca_assistant_synonyms';
	private const STOP_WORDS_OPTION = 'convoca_assistant_stop_words';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Mark dirty when synonyms or stop words change.
		add_action( 'update_option_' . self::SYNONYM_OPTION, array( __CLASS__, 'on_change' ) );
		add_action( 'update_option_' . self::STOP_WORDS_OPTION, array( __CLASS__, 'on_change' ) );

		// Admin-post handlers.
		add_action( 'admin_post_convoca_assistant_synonym_add', array( __CLASS__, 'handle_add' ) );
		add_action( 'admin_post_convoca_assistant_synonym_remove', array( __CLASS__, 'handle_remove' ) );
		add_action( 'admin_post_convoca_assistant_stop_words_save', array( __CLASS__, 'handle_stop_words_save' ) );
	}

	/* ── CRUD ──────────────────────────────────── */

	/**
	 * Get all synonyms.
	 *
	 * @return array<string, string[]>
	 */
	public static function get_all(): array {
		return get_option( self::SYNONYM_OPTION, array() );
	}

	/**
	 * Add or update a synonym entry.
	 *
	 * @param string   $term     Canonical term.
	 * @param string[] $synonyms List of synonyms.
	 * @return bool
	 */
	public static function set( string $term, array $synonyms ): bool {
		$all = self::get_all();
		$synonyms = array_map( 'trim', $synonyms );
		$synonyms = array_filter( $synonyms, function ( $s ) use ( $term ) {
			return ! empty( $s ) && $s !== $term;
		} );
		$all[ $term ] = array_unique( $synonyms );
		return update_option( self::SYNONYM_OPTION, $all );
	}

	/**
	 * Remove a synonym entry.
	 *
	 * @param string $term The term to remove.
	 * @return bool
	 */
	public static function remove( string $term ): bool {
		$all = self::get_all();
		if ( isset( $all[ $term ] ) ) {
			unset( $all[ $term ] );
			return update_option( self::SYNONYM_OPTION, $all );
		}
		return false;
	}

	/* ── Stop words ────────────────────────────── */

	/**
	 * Get stop words.
	 *
	 * @return string[]
	 */
	public static function get_stop_words(): array {
		return get_option( self::STOP_WORDS_OPTION, Installer::default_stop_words() );
	}

	/**
	 * Set stop words.
	 *
	 * @param string[] $words List of stop words.
	 * @return bool
	 */
	public static function set_stop_words( array $words ): bool {
		$words = array_map( 'trim', $words );
		$words = array_filter( $words, function ( $w ) {
			return ! empty( $w );
		} );
		return update_option( self::STOP_WORDS_OPTION, array_unique( $words ) );
	}

	/**
	 * Reset stop words to defaults.
	 *
	 * @return bool
	 */
	public static function reset_stop_words(): bool {
		return update_option( self::STOP_WORDS_OPTION, Installer::default_stop_words() );
	}

	/* ── Query expansion ───────────────────────── */

	/**
	 * Expand query tokens with synonyms.
	 *
	 * @param string[] $tokens Tokenized query words.
	 * @return string[]
	 */
	public static function expand_query( array $tokens ): array {
		$synonyms = self::get_all();
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

	/* ── Admin-post handlers ───────────────────── */

	/**
	 * Handle synonym add via admin-post.
	 *
	 * @return void
	 */
	public static function handle_add(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-assistant' ) );
		}
		check_admin_referer( 'convoca_assistant_synonym_add' );

		$term = sanitize_text_field( wp_unslash( $_POST['term'] ?? '' ) );
		$syns = sanitize_textarea_field( wp_unslash( $_POST['synonyms'] ?? '' ) );

		if ( empty( $term ) ) {
			wp_safe_redirect( add_query_arg( 'page', 'convoca-assistant-synonyms', admin_url( 'admin.php' ) ) );
			exit;
		}

		$list = array_filter( array_map( 'trim', explode( "\n", $syns ) ) );
		self::set( $term, $list );

		wp_safe_redirect( add_query_arg(
			array( 'page' => 'convoca-assistant-synonyms', 'saved' => '1' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * Handle synonym remove via admin-post.
	 *
	 * @return void
	 */
	public static function handle_remove(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-assistant' ) );
		}
		check_admin_referer( 'convoca_assistant_synonym_remove' );

		$term = sanitize_text_field( wp_unslash( $_POST['term'] ?? '' ) );
		self::remove( $term );

		wp_safe_redirect( add_query_arg(
			array( 'page' => 'convoca-assistant-synonyms', 'removed' => '1' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * Handle stop words save via admin-post.
	 *
	 * @return void
	 */
	public static function handle_stop_words_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos.', 'convoca-assistant' ) );
		}
		check_admin_referer( 'convoca_assistant_stop_words_save' );

		$raw  = sanitize_textarea_field( wp_unslash( $_POST['stop_words'] ?? '' ) );
		$list = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
		self::set_stop_words( $list );

		wp_safe_redirect( add_query_arg(
			array( 'page' => 'convoca-assistant-synonyms', 'saved' => '1' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * Mark index as dirty when synonyms or stop words change.
	 *
	 * @return void
	 */
	public static function on_change(): void {
		Indexer::mark_dirty();
	}
}

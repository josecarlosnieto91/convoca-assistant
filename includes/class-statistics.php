<?php
/**
 * Statistics: logs interactions and provides analytics.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

/**
 * Handles logging of chat interactions, query analytics,
 * and reporting of unanswered questions.
 */
class Statistics {

	/**
	 * Table name (with prefix).
	 *
	 * @var string
	 */
	private static string $table = '';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'convoca_assistant_log_cleanup', array( __CLASS__, 'cleanup' ) );
	}

	/**
	 * Get the full table name.
	 *
	 * @return string
	 */
	private static function table(): string {
		if ( empty( self::$table ) ) {
			global $wpdb;
			self::$table = $wpdb->prefix . 'convoca_assistant_log';
		}
		return self::$table;
	}

	/**
	 * Log a chat interaction.
	 *
	 * @param string      $query          The user's query.
	 * @param int|null    $response_id    Post ID of the response.
	 * @param bool        $response_found Whether a response was found.
	 * @param float       $score          Search score.
	 * @param bool        $clicked        Whether the user clicked the source link.
	 * @param int|null    $time_ms        Query time in milliseconds.
	 * @param string      $user_agent     User-Agent header.
	 * @param string|null $page_url       URL where the query was made.
	 * @return void
	 */
	public static function log(
		string $query,
		?int $response_id,
		bool $response_found,
		float $score,
		bool $clicked,
		?int $time_ms,
		string $user_agent = '',
		?string $page_url = ''
	): void {
		$settings = get_option( 'convoca_assistant_settings', Installer::default_settings() );

		if ( empty( $settings['log_enabled'] ) ) {
			return;
		}

		global $wpdb;

		$ua_hash = '';
		if ( ! empty( $user_agent ) && ! empty( $settings['log_anonymous'] ) ) {
			$ua_hash = hash( 'sha256', $user_agent );
		} elseif ( ! empty( $user_agent ) ) {
			$ua_hash = $user_agent;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table(),
			array(
				'session_id'    => self::get_session_id(),
				'query'         => $query,
				'response_id'   => $response_id,
				'response_found' => $response_found ? 1 : 0,
				'score'         => $score,
				'clicked'       => $clicked ? 1 : 0,
				'query_time_ms' => $time_ms,
				'page_url'      => $page_url ? esc_url_raw( $page_url ) : '',
				'user_agent_hash' => $ua_hash,
			),
			array( '%s', '%s', '%d', '%d', '%f', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Get analytics data.
	 *
	 * @param int $days Number of days to look back.
	 * @return array<string, mixed>
	 */
	public static function get_stats( int $days = 30 ): array {
		global $wpdb;
		$table  = self::table();
		$since  = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$since
		) );

		$found = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND response_found = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$since
		) );

		$avg_score = (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT AVG(score) FROM {$table} WHERE created_at >= %s AND response_found = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$since
		) );

		$avg_time = (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT AVG(query_time_ms) FROM {$table} WHERE created_at >= %s AND query_time_ms IS NOT NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$since
		) );

		$top_queries = $wpdb->get_results( $wpdb->prepare(
			"SELECT query, COUNT(*) as count, AVG(score) as avg_score
			FROM {$table} WHERE created_at >= %s
			GROUP BY query ORDER BY count DESC LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$since
		), ARRAY_A );

		$daily = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(created_at) as day, COUNT(*) as count
			FROM {$table} WHERE created_at >= %s
			GROUP BY day ORDER BY day ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$since
		), ARRAY_A );

		return array(
			'total'       => $total,
			'found'       => $found,
			'not_found'   => $total - $found,
			'resolution_rate' => $total > 0 ? round( ( $found / $total ) * 100, 1 ) : 0,
			'avg_score'   => round( $avg_score, 4 ),
			'avg_time_ms' => round( $avg_time, 2 ),
			'top_queries' => $top_queries,
			'daily'       => $daily,
		);
	}

	/**
	 * Get unanswered queries.
	 *
	 * @param int $limit Max results.
	 * @return array[]
	 */
	public static function get_unanswered( int $limit = 50 ): array {
		global $wpdb;
		$table = self::table();

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT query, COUNT(*) as count, MAX(created_at) as last_seen
			FROM {$table}
			WHERE response_found = 0
			GROUP BY query
			ORDER BY count DESC
			LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$limit
		), ARRAY_A );
	}

	/**
	 * Delete old log entries based on retention setting.
	 *
	 * @return void
	 */
	public static function cleanup(): void {
		$settings  = get_option( 'convoca_assistant_settings', Installer::default_settings() );
		$retention = (int) ( $settings['log_retention_days'] ?? 90 );

		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $retention * DAY_IN_SECONDS ) );

		$table = self::table();
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$cutoff
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Get or generate a session ID (stored in browser session).
	 *
	 * @return string
	 */
	private static function get_session_id(): string {
		if ( ! session_id() ) {
			session_start();
		}
		if ( empty( $_SESSION['convoca_assistant_session'] ) ) {
			$_SESSION['convoca_assistant_session'] = wp_generate_uuid4();
		}
		return $_SESSION['convoca_assistant_session'];
	}
}

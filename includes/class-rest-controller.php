<?php
/**
 * REST Controller: public and admin REST API endpoints.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

/**
 * Registers and handles all REST API endpoints for Convoca Assistant.
 * Includes rate limiting, input validation, and CORS support.
 */
class REST_Controller {

	private const API_NAMESPACE = 'convoca/v1';

	/** Rate limit: max requests per window per IP. */
	private const RATE_LIMIT_MAX    = 60;
	private const RATE_LIMIT_WINDOW = 60; // seconds.

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register all REST routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		// Public: get knowledge index (CORS-friendly).
		register_rest_route(
			self::API_NAMESPACE,
			'/assistant/index',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_index' ),
				'permission_callback' => '__return_true',
			)
		);

		// Public: server-side search (rate-limited).
		register_rest_route(
			self::API_NAMESPACE,
			'/assistant/search',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'search' ),
				'permission_callback' => array( __CLASS__, 'check_rate_limit' ),
				'args'                => self::search_args(),
			)
		);

		// Public: log interaction (rate-limited).
		register_rest_route(
			self::API_NAMESPACE,
			'/assistant/log',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'log_interaction' ),
				'permission_callback' => array( __CLASS__, 'check_rate_limit' ),
				'args'                => self::log_args(),
			)
		);

		// Admin: get statistics.
		register_rest_route(
			self::API_NAMESPACE,
			'/assistant/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_stats' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// Admin: get unanswered queries.
		register_rest_route(
			self::API_NAMESPACE,
			'/assistant/unanswered',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_unanswered' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// Admin: rebuild index.
		register_rest_route(
			self::API_NAMESPACE,
			'/assistant/rebuild-index',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rebuild_index' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// Admin: clear logs.
		register_rest_route(
			self::API_NAMESPACE,
			'/assistant/clear-logs',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'clear_logs' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/* ── Rate limiting ─────────────────────────── */

	/**
	 * Check rate limit for public endpoints.
	 *
	 * @return bool|WP_Error
	 */
	public static function check_rate_limit() {
		$ip = self::get_client_ip();
		$key = 'convoca_ratelimit_' . md5( $ip );
		$window = get_transient( $key );

		if ( false !== $window && (int) $window >= self::RATE_LIMIT_MAX ) {
			return new \WP_Error(
				'rate_limit_exceeded',
				__( 'Demasiadas solicitudes. Inténtalo de nuevo en un minuto.', 'convoca-assistant' ),
				array( 'status' => 429 )
			);
		}

		if ( false === $window ) {
			set_transient( $key, 1, self::RATE_LIMIT_WINDOW );
		} else {
			set_transient( $key, (int) $window + 1, self::RATE_LIMIT_WINDOW );
		}

		return true;
	}

	/**
	 * Get client IP address safely.
	 *
	 * @return string
	 */
	private static function get_client_ip(): string {
		$headers = array(
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'HTTP_CLIENT_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $h ) {
			if ( ! empty( $_SERVER[ $h ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $h ] ) );
				$ips = explode( ',', $ip );
				return trim( $ips[0] );
			}
		}

		return '127.0.0.1';
	}

	/* ── Arg schemas ───────────────────────────── */

	/**
	 * Search endpoint argument schema.
	 *
	 * @return array
	 */
	private static function search_args(): array {
		return array(
			'query' => array(
				'required'          => true,
				'type'              => 'string',
				'minLength'         => 2,
				'maxLength'         => 500,
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Log endpoint argument schema.
	 *
	 * @return array
	 */
	private static function log_args(): array {
		return array(
			'query'          => array(
				'required'          => true,
				'type'              => 'string',
				'maxLength'         => 500,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'response_id'    => array(
				'type'              => 'integer',
				'default'           => 0,
			),
			'response_found' => array(
				'type'              => 'boolean',
				'default'           => false,
			),
			'score'          => array(
				'type'              => 'number',
				'default'           => 0.0,
			),
			'clicked'        => array(
				'type'              => 'boolean',
				'default'           => false,
			),
			'time_ms'        => array(
				'type'              => 'integer',
				'default'           => 0,
			),
			'page_url'       => array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			),
		);
	}

	/* ── Endpoint callbacks ────────────────────── */

	/**
	 * GET /assistant/index — return the knowledge index.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_index( $request ) {
		$file = CONVOCA_ASSISTANT_INDEX_DIR . 'index.json';

		if ( ! file_exists( $file ) ) {
			return new \WP_Error(
				'index_not_found',
				__( 'Knowledge index not found.', 'convoca-assistant' ),
				array( 'status' => 404 )
			);
		}

		$data = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $data ) {
			return new \WP_Error(
				'index_read_error',
				__( 'Could not read index.', 'convoca-assistant' ),
				array( 'status' => 500 )
			);
		}

		$decoded = json_decode( $data, true );
		if ( null === $decoded ) {
			return new \WP_Error(
				'index_parse_error',
				__( 'Index is corrupted.', 'convoca-assistant' ),
				array( 'status' => 500 )
			);
		}

		$response = new \WP_REST_Response( $decoded, 200 );
		$response->header( 'Content-Type', 'application/json; charset=utf-8' );
		$response->header( 'Cache-Control', 'public, max-age=300' );
		$response->header( 'Access-Control-Allow-Origin', '*' );

		return $response;
	}

	/**
	 * POST /assistant/search — server-side search fallback.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function search( $request ) {
		$query = $request->get_param( 'query' );

		if ( strlen( $query ) < 2 ) {
			return new \WP_Error(
				'query_too_short',
				__( 'La consulta debe tener al menos 2 caracteres.', 'convoca-assistant' ),
				array( 'status' => 400 )
			);
		}

		$start  = microtime( true );
		$settings    = get_option( 'convoca_assistant_settings', Installer::default_settings() );
		$max_results = (int) ( $settings['search_max_results'] ?? 10 );
		$threshold   = (float) ( $settings['search_threshold'] ?? 0.10 );
		$results     = Searcher::search( $query, $max_results, $threshold );
		$elapsed     = ( microtime( true ) - $start ) * 1000;

		return new \WP_REST_Response(
			array(
				'query'   => $query,
				'results' => $results,
				'total'   => count( $results ),
				'time_ms' => round( $elapsed, 2 ),
			),
			200
		);
	}

	/**
	 * POST /assistant/log — log a user interaction.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function log_interaction( $request ) {
		$settings = get_option( 'convoca_assistant_settings', Installer::default_settings() );

		if ( empty( $settings['log_enabled'] ) ) {
			return new \WP_REST_Response( array( 'logged' => false ), 200 );
		}

		$args = $request->get_params();

		Statistics::log(
			$args['query'] ?? '',
			! empty( $args['response_id'] ) ? (int) $args['response_id'] : null,
			! empty( $args['response_found'] ),
			(float) ( $args['score'] ?? 0.0 ),
			! empty( $args['clicked'] ),
			(int) ( $args['time_ms'] ?? 0 ),
			$request->get_header( 'User-Agent' ) ?: '',
			$args['page_url'] ?? ''
		);

		return new \WP_REST_Response( array( 'logged' => true ), 200 );
	}

	/**
	 * GET /assistant/stats — admin analytics.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function get_stats( $request ) {
		$days = min( (int) $request->get_param( 'days' ), 365 );
		$days = max( $days, 1 );
		if ( 0 === $days ) {
			$days = 30;
		}
		return new \WP_REST_Response( Statistics::get_stats( $days ), 200 );
	}

	/**
	 * GET /assistant/unanswered — admin unmet queries.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function get_unanswered( $request ) {
		$limit = min( (int) $request->get_param( 'limit' ), 200 );
		$limit = max( $limit, 1 );
		return new \WP_REST_Response( Statistics::get_unanswered( $limit ), 200 );
	}

	/**
	 * POST /assistant/rebuild-index — force index rebuild.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rebuild_index( $request ) {
		$result = Indexer::regenerate();
		$status = $result['success'] ? 200 : 500;
		return new \WP_REST_Response( $result, $status );
	}

	/**
	 * POST /assistant/clear-logs — delete all log entries.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public static function clear_logs( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'convoca_assistant_log';
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return new \WP_REST_Response( array( 'success' => true, 'message' => __( 'Logs eliminados.', 'convoca-assistant' ) ), 200 );
	}
}

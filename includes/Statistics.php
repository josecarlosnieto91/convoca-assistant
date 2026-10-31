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
		$settings = Settings::get_all();

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

		$insertado = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table(),
			array(
				'session_id'      => self::get_session_id(),
				'query'           => $query,
				// La columna es NOT NULL: un `null` aquí hace fallar el INSERT y la consulta sin
				// respuesta se perdía en silencio (convoca-assistant#24).
				'response_id'     => null === $response_id ? 0 : $response_id,
				'response_found'  => $response_found ? 1 : 0,
				'score'           => $score,
				'clicked'         => $clicked ? 1 : 0,
				'query_time_ms'   => null === $time_ms ? 0 : $time_ms,
				'page_url'        => $page_url ? esc_url_raw( $page_url ) : '',
				'user_agent_hash' => $ua_hash,
				// Explicit UTC: analytics/cleanup filter with gmdate(), so never rely on
				// the DB server default (which follows the server timezone, not UTC).
				'created_at'      => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%d', '%d', '%f', '%d', '%d', '%s', '%s', '%s' )
		);

		// Un fallo de escritura no puede volver a pasar en silencio.
		if ( false === $insertado && class_exists( '\Convoca\Core\Logger' ) ) {
			\Convoca\Core\Logger::warning( 'No se ha podido registrar la consulta: ' . (string) $wpdb->last_error, 'Convoca Assistant', 0 );
		}
	}

	/**
	 * Get analytics data.
	 *
	 * @param int $days Number of days to look back.
	 * @return array<string, mixed>
	 */
	public static function get_stats( int $days = 30 ): array {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since
			)
		);

		$found = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND response_found = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since
			)
		);

		$avg_score = (float) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT AVG(score) FROM {$table} WHERE created_at >= %s AND response_found = 1",
				$since
			)
		);

		$avg_time = (float) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT AVG(query_time_ms) FROM {$table} WHERE created_at >= %s AND query_time_ms IS NOT NULL",
				$since
			)
		);

		$top_queries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT query, COUNT(*) as count, AVG(score) as avg_score
			FROM {$table} WHERE created_at >= %s
			GROUP BY query ORDER BY count DESC LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since
			),
			ARRAY_A
		);

		$daily = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) as day, COUNT(*) as count
			FROM {$table} WHERE created_at >= %s
			GROUP BY day ORDER BY day ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$since
			),
			ARRAY_A
		);

		return array(
			'total'           => $total,
			'found'           => $found,
			'not_found'       => $total - $found,
			'resolution_rate' => $total > 0 ? round( ( $found / $total ) * 100, 1 ) : 0,
			'avg_score'       => round( $avg_score, 4 ),
			'avg_time_ms'     => round( $avg_time, 2 ),
			'top_queries'     => $top_queries,
			'daily'           => $daily,
		);
	}

	/**
	 * Get the most recent logged interactions (for the Analytics table).
	 *
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_recent( int $limit = 20 ): array {
		global $wpdb;
		$table = self::table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, query, response_id, response_found, score, clicked, query_time_ms, created_at
				FROM {$table}
				ORDER BY created_at DESC, id DESC
				LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$response_id    = (int) $row['response_id'];
			$row['source']  = $response_id > 0 ? (string) get_the_title( $response_id ) : '';
			$row['score']   = (float) $row['score'];
			$row['time_ms'] = (int) $row['query_time_ms'];
		}
		unset( $row );

		return $rows;
	}

	/**
	/**
	 * Cuántas consultas distintas se quedaron sin respuesta en los últimos 7 días.
	 *
	 * Es lo que enseña el aviso del panel: un número que se puede arreglar, con enlace a la pantalla donde
	 * se arregla (assistant#12).
	 */
	public static function unanswered_this_week(): int {
		return count( self::get_unanswered( 200, WEEK_IN_SECONDS ) );
	}

	/**
	 * ¿Esta consulta encuentra algo HOY?
	 *
	 * El registro es un registro: guarda lo que pasó, y lo que pasó sigue ahí aunque después se arregle. Sin
	 * esta comprobación, la pantalla «Sin respuesta» mostraba como pendientes consultas que ya se contestan
	 * —y el aviso del panel daba un número que no era un problema—, que es justo lo que pasaba con las cinco
	 * del informe (assistant#12): todas tienen respuesta desde que está el paquete de arranque.
	 *
	 * @param string $query Consulta tal como la escribió la persona.
	 */
	private static function tiene_respuesta( string $query ): bool {
		if ( '' === trim( $query ) || ! class_exists( __NAMESPACE__ . '\\Searcher' ) ) {
			return false;
		}

		// Con el umbral del sitio, el mismo que el widget: sin él, cualquier coincidencia floja valía como
		// respuesta y la pantalla daba por resuelto lo que nadie había contestado (convoca-assistant#13).
		$resultado = Searcher::search( $query, 10, Searcher::umbral_configurado() );
		$lista     = $resultado['results'] ?? $resultado;

		return count( $lista ) > 0;
	}

	/**
	 * Consultas que se quedaron sin respuesta, agrupadas.
	 *
	 * @param int $limit     Cuántas devolver.
	 * @param int $segundos  Ventana de tiempo en segundos (0 = todo el registro).
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_unanswered( int $limit = 50, int $segundos = 0 ): array {
		global $wpdb;
		$table = self::table();

		$desde = '';
		if ( $segundos > 0 ) {
			$desde = $wpdb->prepare( 'AND created_at >= %s', gmdate( 'Y-m-d H:i:s', time() - $segundos ) );
		}

		$filas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT query, COUNT(*) as count, MAX(created_at) as last_seen
				FROM {$table}"
					. " WHERE response_found = 0 {$desde} " // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					. 'GROUP BY query
				ORDER BY count DESC
				LIMIT %d',
				$limit
			),
			ARRAY_A
		);

		// Y de lo registrado, solo lo que SIGUE sin respuesta: lo que ya se contesta no es un pendiente.
		return array_values(
			array_filter(
				(array) $filas,
				static function ( $fila ) {
					return ! self::tiene_respuesta( (string) ( $fila['query'] ?? '' ) );
				}
			)
		);
	}

	/**
	 * Delete old log entries based on retention setting.
	 *
	 * @return void
	 */
	public static function cleanup(): void {
		$settings  = Settings::get_all();
		$retention = (int) ( $settings['log_retention_days'] ?? 90 );

		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $retention * DAY_IN_SECONDS ) );

		$table = self::table();
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cutoff
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- UUID generada por el propio plugin (wp_generate_uuid4).
		return $_SESSION['convoca_assistant_session'];
	}
}

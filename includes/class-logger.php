<?php
/**
 * Logger: local logging with optional Convoca Core integration.
 *
 * If Convoca Core is active, delegates to Convoca\Core\Logger.
 * Otherwise falls back to PHP error_log.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

/**
 * Minimal logger for Convoca Assistant.
 * Works with or without Convoca Core.
 */
class Logger {

	/**
	 * Log an info message.
	 *
	 * @param string $message Log message.
	 * @param string $context Logger context/tag.
	 * @return void
	 */
	public static function info( string $message, string $context = 'convoca-assistant' ): void {
		if ( class_exists( '\\Convoca\\Core\\Logger' ) ) {
			\Convoca\Core\Logger::info( $message, $context );
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "[{$context}] {$message}" );
		}
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message Error message.
	 * @param string $context Logger context/tag.
	 * @return void
	 */
	public static function error( string $message, string $context = 'convoca-assistant' ): void {
		if ( class_exists( '\\Convoca\\Core\\Logger' ) ) {
			\Convoca\Core\Logger::error( $message, $context );
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "[{$context}] ERROR: {$message}" );
		}
	}
}

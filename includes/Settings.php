<?php
/**
 * Settings: handles plugin settings registration via Settings API.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

/**
 * Registers, sanitizes, and returns plugin settings using the
 * WordPress Settings API.
 */
class Settings {

	/**
	 * Option name.
	 */
	private const OPTION_NAME = 'convoca_assistant_settings';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Register settings group.
	 *
	 * @return void
	 */
	public static function register_settings(): void {
		register_setting(
			'convoca_assistant',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => Installer::default_settings(),
			)
		);
	}

	/**
	 * Get a specific setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$settings = get_option( self::OPTION_NAME, Installer::default_settings() );
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Update a specific setting.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool
	 */
	public static function set( string $key, $value ): bool {
		$settings = get_option( self::OPTION_NAME, Installer::default_settings() );
		$settings[ $key ] = $value;
		return update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Get all settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all(): array {
		return get_option( self::OPTION_NAME, Installer::default_settings() );
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $input ): array {
		$defaults = Installer::default_settings();
		$output   = $defaults;

		foreach ( $input as $key => $value ) {
			switch ( $key ) {
				// Booleans.
				case 'widget_enabled':
				case 'source_post':
				case 'source_page':
				case 'source_convoca_faq':
				case 'source_convoca_kb':
				case 'source_woocommerce':
				case 'search_fallback':
				case 'index_auto_regenerate':
				case 'index_compress':
				case 'log_anonymous':
				case 'log_enabled':
				case 'maintenance_mode':
				case 'debug_mode':
					$output[ $key ] = ! empty( $value );
					break;

				// Floats.
				case 'weight_convoca_faq':
				case 'weight_convoca_kb':
				case 'weight_post':
				case 'weight_page':
				case 'weight_product':
				case 'search_fuse_threshold':
				case 'search_threshold':
					$output[ $key ] = (float) $value;
					break;

				// Integers.
				case 'search_fuse_distance':
				case 'search_fuse_distance':
				case 'search_max_results':
				case 'index_max_content':
				case 'log_retention_days':
				case 'widget_auto_open_scroll':
					$output[ $key ] = absint( $value );
					break;

				// Strings.
				case 'widget_position':
					$output[ $key ] = in_array( $value, array( 'bottom-right', 'bottom-left' ), true ) ? $value : 'bottom-right';
					break;
				case 'widget_auto_open':
					$output[ $key ] = in_array( $value, array( 'never', 'always', 'scroll' ), true ) ? $value : 'never';
					break;
				case 'search_mode':
					$output[ $key ] = in_array( $value, array( 'client', 'server', 'both' ), true ) ? $value : 'client';
					break;

				// Sanitized strings.
				case 'widget_primary_color':
					$output[ $key ] = sanitize_hex_color( $value ) ?: $defaults[ $key ];
					break;
				case 'widget_title':
				case 'widget_greeting':
				case 'maintenance_message':
					$output[ $key ] = sanitize_text_field( $value );
					break;

				default:
					$output[ $key ] = isset( $defaults[ $key ] ) ? $defaults[ $key ] : null;
					break;
			}
		}

		return $output;
	}
}

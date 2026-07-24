<?php
/**
 * Provider Registry: manages all registered knowledge providers.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

/**
 * Central registry for Knowledge_Provider_Interface implementations.
 * Providers register via the 'convoca_assistant/providers' filter.
 */
class Provider_Registry {

	/**
	 * Cached provider instances.
	 *
	 * @var array<string, Knowledge_Provider_Interface>|null
	 */
	private static ?array $providers = null;

	/**
	 * Get all registered providers.
	 *
	 * @return array<string, Knowledge_Provider_Interface>
	 */
	public static function get_all(): array {
		if ( null === self::$providers ) {
			$built_in = self::built_in_providers();
			self::$providers = array();

			// Register built-in providers first.
			foreach ( $built_in as $provider ) {
				self::$providers[ $provider->get_id() ] = $provider;
			}

			/**
			 * Filter the available knowledge providers.
			 *
			 * @param array<string, Knowledge_Provider_Interface> $providers Registered providers.
			 */
			$filtered = apply_filters( 'convoca_assistant/providers', self::$providers );

			// Ensure all are valid.
			foreach ( $filtered as $id => $provider ) {
				if ( ! $provider instanceof Knowledge_Provider_Interface ) {
					unset( $filtered[ $id ] );
				}
			}

			self::$providers = $filtered;
		}

		return self::$providers;
	}

	/**
	 * Get a single provider by ID.
	 *
	 * @param string $id Provider identifier.
	 * @return Knowledge_Provider_Interface|null
	 */
	public static function get( string $id ): ?Knowledge_Provider_Interface {
		$providers = self::get_all();
		return $providers[ $id ] ?? null;
	}

	/**
	 * Get all providers that are currently available and active.
	 *
	 * @return array<string, Knowledge_Provider_Interface>
	 */
	public static function get_active(): array {
		$all   = self::get_all();
		$settings = get_option( 'convoca_assistant_settings', Installer::default_settings() );
		$active   = array();

		foreach ( $all as $id => $provider ) {
			if ( ! $provider->is_available() ) {
				continue;
			}
			$setting_key = $provider->get_setting_key();
			if ( ! empty( $settings[ $setting_key ] ) ) {
				$active[ $id ] = $provider;
			}
		}

		return $active;
	}

	/**
	 * Collect all entries from all active providers.
	 *
	 * @param int $max_content Max content length.
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect_entries( int $max_content = 5000 ): array {
		$providers = self::get_active();
		$entries   = array();
		$id_seen   = array();

		foreach ( $providers as $provider ) {
			$provider_entries = $provider->get_entries( $max_content );

			foreach ( $provider_entries as $entry ) {
				if ( isset( $id_seen[ $entry['id'] ] ) ) {
					continue;
				}
				$id_seen[ $entry['id'] ] = true;
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * Collect all relations from all active providers.
	 *
	 * @return array<int, array{from: int, to: int, type: string, weight: float}>
	 */
	public static function collect_relations(): array {
		$providers = self::get_active();
		$edges     = array();
		$seen      = array();

		foreach ( $providers as $provider ) {
			$entries = $provider->get_entries( 100 ); // Only need IDs.
			foreach ( $entries as $entry ) {
				$relations = $provider->get_relations( $entry['id'] );
				foreach ( $relations as $rel ) {
					$key = "{$entry['id']}-{$rel['to']}-{$rel['type']}";
					if ( isset( $seen[ $key ] ) ) {
						continue;
					}
					$seen[ $key ] = true;
					$edges[] = array(
						'from'   => $entry['id'],
						'to'     => $rel['to'],
						'type'   => $rel['type'],
						'weight' => $rel['weight'],
					);
				}
			}
		}

		return $edges;
	}

	/**
	 * Register default built-in providers.
	 *
	 * @return Knowledge_Provider_Interface[]
	 */
	private static function built_in_providers(): array {
		$providers = array();

		$classes = array(
			'Convoca\\Assistant\\Providers\\Posts_Provider',
			'Convoca\\Assistant\\Providers\\Pages_Provider',
			'Convoca\\Assistant\\Providers\\FAQ_Provider',
			'Convoca\\Assistant\\Providers\\KB_Provider',
			'Convoca\\Assistant\\Providers\\WooCommerce_Provider',
		);

		foreach ( $classes as $class ) {
			if ( class_exists( $class ) ) {
				$instance = new $class();
				$providers[ $instance->get_id() ] = $instance;
			}
		}

		return $providers;
	}
}

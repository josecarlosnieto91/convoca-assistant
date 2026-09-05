<?php
/**
 * PHPStan bootstrap: constantes WP de runtime (las define wp-load en producción)
 * y stubs de símbolos de plugins hermanos ausentes en el análisis aislado.
 */

// Stubs de Convoca\Core (plugin hermano, ausente en análisis aislado).
namespace Convoca\Core {
	if ( ! class_exists( 'Logger' ) ) {
		class Logger {
			public static function info( ...$args ): void {}
			public static function error( ...$args ): void {}
			public static function warning( ...$args ): void {}
		}
	}
}

// Stubs de WooCommerce (plugin opcional, ausente en análisis aislado).
namespace {
	if ( ! function_exists( 'wc_get_product' ) ) {
		/**
		 * @return object|null
		 */
		function wc_get_product( $the_product = false ) {
			return null;
		}
	}
	if ( ! function_exists( 'wc_price' ) ) {
		/**
		 * @return string
		 */
		function wc_price( $price, $args = array() ) {
			return (string) $price;
		}
	}
}

namespace {
	define( 'ABSPATH', '/tmp/wp/' );
	define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'MONTH_IN_SECONDS', 30 * DAY_IN_SECONDS );
	define( 'WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS );
	define( 'YEAR_IN_SECONDS', 365 * DAY_IN_SECONDS );
	define( 'ARRAY_A', 'ARRAY_A' );
	define( 'OBJECT', 'OBJECT' );
	define( 'OBJECT_K', 'OBJECT_K' );
	define( 'WP_DEBUG', true );

	// Constantes del plugin (definidas en convoca-assistant.php en runtime).
	define( 'CONVOCA_ASSISTANT_VERSION', '0.2.2' );
	define( 'CONVOCA_ASSISTANT_DB_VERSION', '1.0.0' );
	define( 'CONVOCA_ASSISTANT_FILE', '/tmp/wp/wp-content/plugins/convoca-assistant/convoca-assistant.php' );
	define( 'CONVOCA_ASSISTANT_DIR', '/tmp/wp/wp-content/plugins/convoca-assistant/' );
	define( 'CONVOCA_ASSISTANT_URL', 'https://example.org/wp-content/plugins/convoca-assistant/' );
	define( 'CONVOCA_ASSISTANT_ASSETS_URL', CONVOCA_ASSISTANT_URL . 'assets/' );
}

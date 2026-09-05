<?php
/**
 * Bootstrap para tests unitarios de Convoca Assistant (sin entorno WordPress real).
 *
 * Proporciona stubs mínimos de funciones/globales de WordPress para poder
 * probar REST_Controller::get_client_ip y Posts_Provider sin base de datos.
 *
 * @package Convoca\Assistant
 */

namespace {

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	}

	// ─── Funciones de WordPress necesarias para las clases bajo test ───

	if ( ! function_exists( 'sanitize_text_field' ) ) {
		function sanitize_text_field( $str ) {
			return is_string( $str ) ? trim( $str ) : '';
		}
	}

	if ( ! function_exists( 'wp_unslash' ) ) {
		function wp_unslash( $value ) {
			return is_string( $value ) ? stripslashes( $value ) : $value;
		}
	}

	if ( ! function_exists( '__' ) ) {
		function __( $text, $domain = 'default' ) {
			return $text;
		}
	}

	if ( ! function_exists( '_x' ) ) {
		function _x( $text, $context, $domain = 'default' ) {
			return $text;
		}
	}

	if ( ! function_exists( 'esc_html' ) ) {
		function esc_html( $text ) {
			return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'esc_attr' ) ) {
		function esc_attr( $text ) {
			return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'post_type_exists' ) ) {
		function post_type_exists( $type ) {
			return in_array( $type, array( 'post', 'page' ), true );
		}
	}

	if ( ! function_exists( 'get_post' ) ) {
		function get_post( $id = null ) {
			if ( null === $id ) {
				return null;
			}
			$post           = new stdClass();
			$post->ID       = (int) $id;
			$post->post_title = "Post $id";
			$post->post_type = 'post';
			$post->post_status = 'publish';
			$post->post_date = '2026-06-01 10:00:00';
			$post->post_modified = '2026-06-01 10:00:00';
			return $post;
		}
	}

	// ─── WP_Query stub: registra los argumentos del constructor ───

	if ( ! class_exists( 'WP_Query' ) ) {
		class WP_Query {
			/** @var array<int, array<string, mixed>> Argumentos de cada instanciación. */
			public static array $captured = array();

			/** @var array<int, mixed> */
			public array $posts = array();

			public int $found_posts = 0;

			public function __construct( $args = null ) {
				self::$captured[] = is_array( $args ) ? $args : array();
				$this->posts      = array();
				$this->found_posts = 0;
			}
		}
	}

	// Cargar el autoload de Composer (PSR-4 de Convoca\Assistant).
	$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
	if ( file_exists( $autoload ) ) {
		require_once $autoload;
	}
}

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

	if ( ! defined( 'DAY_IN_SECONDS' ) ) {
		define( 'DAY_IN_SECONDS', 86400 );
	}
	if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
		define( 'HOUR_IN_SECONDS', 3600 );
	}

	// ─── Funciones de WordPress necesarias para las clases bajo test ───

	if ( ! function_exists( 'sanitize_text_field' ) ) {
		function sanitize_text_field( $str ) {
			return is_string( $str ) ? trim( $str ) : '';
		}
	}

	if ( ! function_exists( 'sanitize_email' ) ) {
		function sanitize_email( $email ) {
			if ( ! is_string( $email ) ) {
				return '';
			}
			$email = preg_replace( '/[^a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~@-]/', '', $email );
			return ( false !== strpos( $email, '@' ) ) ? $email : '';
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
				// El post «actual» de la petición: lo fija la prueba (null por defecto, como antes).
				return $GLOBALS['convoca_as_post'] ?? null;
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
				// Las filas las prepara la prueba en convoca_as_posts[<post_type>]. Sin nada
				// preparado devuelve vacío, como antes.
				$this->posts       = $GLOBALS['convoca_as_posts'][ ( is_array( $args ) ? ( $args['post_type'] ?? '' ) : '' ) ] ?? array();
				$this->found_posts = count( $this->posts );
			}
		}
	}

	// ─── Options y filtros (para Settings) ───

	$GLOBALS['_assistant_options'] = array();

	if ( ! function_exists( 'get_option' ) ) {
		function get_option( $option, $default = false ) {
			$store =& $GLOBALS['_assistant_options'];
			return array_key_exists( $option, $store ) ? $store[ $option ] : $default;
		}
	}
	if ( ! function_exists( 'get_post_meta' ) ) {
		/**
		 * Meta desde un mapa que fija la prueba: el peso individual de una entrada vive ahí.
		 */
		function get_post_meta( $post_id, $key = '', $single = false ) {
			unset( $single );
			$map = $GLOBALS['_assistant_meta'] ?? array();
			return $map[ (int) $post_id ][ $key ] ?? '';
		}
	}
	if ( ! function_exists( 'update_option' ) ) {
		function update_option( $option, $value, $autoload = null ) {
			$GLOBALS['_assistant_options'][ $option ] = $value;
			return true;
		}
	}
	if ( ! function_exists( 'delete_option' ) ) {
		function delete_option( $option ) {
			unset( $GLOBALS['_assistant_options'][ $option ] );
			return true;
		}
	}
	if ( ! function_exists( 'add_option' ) ) {
		function add_option( $option, $value = '', $deprecated = '', $autoload = 'yes' ) {
			if ( ! array_key_exists( $option, $GLOBALS['_assistant_options'] ) ) {
				$GLOBALS['_assistant_options'][ $option ] = $value;
			}
			return true;
		}
	}
	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( $tag, $value, ...$args ) {
			return $value;
		}
	}
	if ( ! function_exists( 'add_filter' ) ) {
		function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
			return true;
		}
	}
	if ( ! function_exists( 'absint' ) ) {
		function absint( $maybeint ) {
			return abs( (int) $maybeint );
		}
	}
	if ( ! function_exists( 'sanitize_key' ) ) {
		function sanitize_key( $key ) {
			return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
		}
	}
	if ( ! function_exists( 'sanitize_hex_color' ) ) {
		function sanitize_hex_color( $color ) {
			if ( preg_match( '/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', (string) $color ) ) {
				return strtolower( $color );
			}
			return '';
		}
	}

	// ─── Transients (para Indexer::mark_dirty / is_dirty) ───

	$GLOBALS['_assistant_transients'] = array();

	if ( ! function_exists( 'get_transient' ) ) {
		function get_transient( $transient ) {
			return array_key_exists( $transient, $GLOBALS['_assistant_transients'] ) ? $GLOBALS['_assistant_transients'][ $transient ] : false;
		}
	}
	if ( ! function_exists( 'set_transient' ) ) {
		function set_transient( $transient, $value, $expiration = 0 ) {
			$GLOBALS['_assistant_transients'][ $transient ] = $value;
			return true;
		}
	}
	if ( ! function_exists( 'delete_transient' ) ) {
		function delete_transient( $transient ) {
			unset( $GLOBALS['_assistant_transients'][ $transient ] );
			return true;
		}
	}

	// ─── Cron API (para Indexer::schedule_regenerate_now / schedule_backup_cron) ───

	$GLOBALS['_assistant_cron'] = array();

	if ( ! function_exists( 'wp_next_scheduled' ) ) {
		function wp_next_scheduled( $hook, $args = array() ) {
			return isset( $GLOBALS['_assistant_cron'][ $hook ] ) ? $GLOBALS['_assistant_cron'][ $hook ] : false;
		}
	}
	if ( ! function_exists( 'wp_schedule_single_event' ) ) {
		function wp_schedule_single_event( $timestamp, $hook, $args = array() ) {
			$GLOBALS['_assistant_cron'][ $hook ] = $timestamp;
			return true;
		}
	}
	if ( ! function_exists( 'wp_schedule_event' ) ) {
		function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
			$GLOBALS['_assistant_cron'][ $hook ] = $timestamp;
			return true;
		}
	}
	if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
		function wp_clear_scheduled_hook( $hook, $args = array() ) {
			unset( $GLOBALS['_assistant_cron'][ $hook ] );
			return true;
		}
	}

	// ─── Hooks (no-op) ───

	if ( ! function_exists( 'add_action' ) ) {
		function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
			$GLOBALS['convoca_as_acciones'][ $tag ] = $callback;
			return true;
		}
	}
	if ( ! function_exists( 'do_action' ) ) {
		function do_action( $tag, ...$args ) {
			return true;
		}
	}

	// Constante del directorio del índice (para Indexer::index_exists en tests).
	// ─── remove_accents() del núcleo (lo usa Searcher::normalize) ───
	// Equivalente al del núcleo para el rango latino, que es el que aparece en este corpus.
	// Con la guarda de siempre, para no pisar el del núcleo si algún día se corre con WordPress.
	if ( ! function_exists( 'remove_accents' ) ) {
		function remove_accents( $string ) {
			if ( ! preg_match( '/[\x80-\xff]/', (string) $string ) ) {
				return $string;
			}
			/*
			 * SIEMPRE con la tabla, sin `transliterator_transliterate` aunque la extensión `intl`
			 * esté disponible. Aquí NO se imita a WordPress: se busca que el banco mida siempre lo
			 * mismo. Con la extensión puesta (mi máquina) el arnés daba 78,8% de recall@1 y sin ella
			 * (el CI) 81,8%: una consulta cambiaba de sitio según el entorno y la aserción de la línea
			 * base se volvía un falso rojo. Que producción dependa de `intl` es otro asunto y va en su
			 * issue.
			 */
			return strtr(
				$string,
				array(
					'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ā' => 'a', 'ã' => 'a', 'å' => 'a',
					'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e', 'ē' => 'e',
					'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ī' => 'i',
					'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'ō' => 'o', 'õ' => 'o',
					'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ū' => 'u',
					'ñ' => 'n', 'ç' => 'c',
					'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ç' => 'C',
					'º' => 'o', 'ª' => 'a', '€' => 'EUR', '¡' => '!', '¿' => '?',
				)
			);
		}
	}

	if ( ! defined( 'CONVOCA_ASSISTANT_INDEX_DIR' ) ) {
		define( 'CONVOCA_ASSISTANT_INDEX_DIR', sys_get_temp_dir() . '/convoca-assistant-tests/' );
	}

	// Cargar el autoload de Composer (PSR-4 de Convoca\Assistant).
	$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
	if ( file_exists( $autoload ) ) {
		require_once $autoload;
	}

	// ── Dobles que faltaban para el exportador/importador ─────────────────────────
	if ( ! defined( 'DAY_IN_SECONDS' ) ) {
		define( 'DAY_IN_SECONDS', 86400 );
	}
	if ( ! function_exists( 'current_user_can' ) ) {
		function current_user_can( $cap, ...$args ) {
			if ( isset( $GLOBALS['convoca_as_caps'][ $cap ] ) ) {
				return (bool) $GLOBALS['convoca_as_caps'][ $cap ];
			}
			return true;
		}
	}
	if ( ! function_exists( 'wp_die' ) ) {
		function wp_die( $mensaje = '' ) {
			throw new RuntimeException( 'wp_die: ' . ( is_scalar( $mensaje ) ? $mensaje : '?' ) );
		}
	}
	if ( ! function_exists( 'check_admin_referer' ) ) {
		function check_admin_referer( $accion = -1, $campo = '_wpnonce' ) {
			return $GLOBALS['convoca_as_nonce'] ?? true;
		}
	}

	if ( ! function_exists( 'get_locale' ) ) {
		function get_locale() {
			return 'es_ES';
		}
	}
	if ( ! function_exists( 'get_bloginfo' ) ) {
		function get_bloginfo( $mostrar = '' ) {
			return 'https://example.test';
		}
	}
	if ( ! function_exists( 'size_format' ) ) {
		function size_format( $bytes, $decimales = 0 ) {
			return round( (int) $bytes / 1048576, $decimales ) . ' MB';
		}
	}
	if ( ! function_exists( 'nocache_headers' ) ) {
		function nocache_headers() {
			return true;
		}
	}
	if ( ! function_exists( 'wp_safe_redirect' ) ) {
		function wp_safe_redirect( $url, $estado = 302 ) {
			$GLOBALS['convoca_as_redirect'] = $url;
			return true;
		}
	}
	if ( ! function_exists( 'admin_url' ) ) {
		function admin_url( $ruta = '' ) {
			return 'https://example.test/wp-admin/' . ltrim( (string) $ruta, '/' );
		}
	}
	if ( ! function_exists( 'add_query_arg' ) ) {
		function add_query_arg( $args, $valor = '', $url = '' ) {
			if ( is_array( $args ) ) {
				$destino = (string) $valor;
				$pares   = $args;
			} else {
				$destino = (string) $url;
				$pares   = array( $args => $valor );
			}
			return $destino . ( str_contains( $destino, '?' ) ? '&' : '?' ) . http_build_query( $pares );
		}
	}
	if ( ! function_exists( 'wp_json_encode' ) ) {
		function wp_json_encode( $dato, $opciones = 0, $profundidad = 512 ) {
			return json_encode( $dato, $opciones, $profundidad );
		}
	}
	if ( ! function_exists( 'wp_insert_post' ) ) {
		function wp_insert_post( $datos, $error = false ) {
			$id = 5000 + count( $GLOBALS['convoca_as_insertados'] ?? array() );
			$GLOBALS['convoca_as_insertados'][ $id ] = $datos;
			return $id;
		}
	}
	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( $cosa ) {
			return false;
		}
	}
	if ( ! function_exists( 'update_post_meta' ) ) {
		function update_post_meta( $id, $clave, $valor ) {
			// Mismo almacén que lee el doble de get_post_meta(): uno solo por dato.
			$GLOBALS['_assistant_meta'][ (int) $id ][ $clave ] = $valor;
			return true;
		}
	}
	if ( ! function_exists( 'esc_html' ) ) {
		function esc_html( $t ) {
			return (string) $t;
		}
	}
	if ( ! function_exists( 'esc_html__' ) ) {
		function esc_html__( $t, $dominio = null ) {
			return (string) $t;
		}
	}


	if ( ! function_exists( 'register_post_type' ) ) {
		function register_post_type( $tipo, $args = array() ) {
			$GLOBALS['convoca_as_tipos'][ $tipo ] = $args;
			return (object) array( 'name' => $tipo );
		}
	}
	if ( ! function_exists( 'register_taxonomy' ) ) {
		function register_taxonomy( $taxonomia, $object_type, $args = array() ) {
			$GLOBALS['convoca_as_taxonomias'][ $taxonomia ] = array(
				'object_type' => $object_type,
				'args'        => $args,
			);
			return true;
		}
	}
	if ( ! function_exists( 'register_post_meta' ) ) {
		function register_post_meta( $tipo, $clave, $args = array() ) {
			$GLOBALS['convoca_as_meta_registrada'][ $tipo ][ $clave ] = $args;
			return true;
		}
	}
	if ( ! function_exists( 'add_meta_box' ) ) {
		function add_meta_box( $id, $titulo, $callback, $pantalla = null, $contexto = 'advanced', $prioridad = 'default' ) {
			$GLOBALS['convoca_as_metaboxes'][ $id ] = compact( 'titulo', 'pantalla', 'contexto' );
			return true;
		}
	}
	if ( ! function_exists( 'wp_trim_words' ) ) {
		function wp_trim_words( $texto, $num = 55, $mas = '…' ) {
			$palabras = preg_split( '/\s+/', trim( (string) $texto ) );
			if ( count( $palabras ) <= $num ) {
				return (string) $texto;
			}
			return implode( ' ', array_slice( $palabras, 0, $num ) ) . $mas;
		}
	}
	if ( ! function_exists( 'get_post_type' ) ) {
		function get_post_type( $post = null ) {
			$id = is_object( $post ) ? (int) $post->ID : (int) $post;
			return $GLOBALS['convoca_as_tipos_post'][ $id ] ?? 'convoca_faq';
		}
	}


	// ── Base de datos: doble de $wpdb con cola de respuestas ──────────────────────
	if ( ! defined( 'ARRAY_A' ) ) {
		define( 'ARRAY_A', 'ARRAY_A' );
	}
	if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
		define( 'WEEK_IN_SECONDS', 604800 );
	}
	if ( ! isset( $GLOBALS['wpdb'] ) ) {
		$GLOBALS['wpdb'] = new class {
			public $prefix = 'wp_';
			public $posts  = 'wp_posts';
			public $postmeta = 'wp_postmeta';
			public $options = 'wp_options';

			/**
			 * Respuestas preparadas por la prueba para get_var().
			 *
			 * @var array<int, mixed>
			 */
			public $vars = array();

			/**
			 * Respuestas preparadas para get_results().
			 *
			 * @var array<int, mixed>
			 */
			public $resultados = array();

			/** Inserciones realizadas. */
			public $inserts = array();

			/** Consultas ejecutadas con query(). */
			public $consultas = array();

			/**
			 * Devuelve la siguiente respuesta preparada.
			 *
			 * @param string $sql Consulta.
			 * @return mixed
			 */
			public function get_var( $sql = '' ) {
				return array_shift( $this->vars );
			}

			/**
			 * Devuelve el siguiente juego de filas preparado.
			 *
			 * @param string $sql    Consulta.
			 * @param string $salida Formato de salida.
			 * @return mixed
			 */
			public function get_results( $sql = '', $salida = null ) {
				return array_shift( $this->resultados );
			}

			/**
			 * Anota una inserción.
			 *
			 * @param string $tabla  Tabla.
			 * @param array  $datos  Datos.
			 * @param array  $formato Formatos.
			 * @return int
			 */
			public function insert( $tabla, $datos, $formato = null ) {
				$this->inserts[] = array( $tabla, $datos );
				return count( $this->inserts );
			}

			/**
			 * Anota una consulta de escritura.
			 *
			 * @param string $sql Consulta.
			 * @return bool
			 */
			public function query( $sql ) {
				$this->consultas[] = $sql;
				return true;
			}

			/**
			 * Doble de prepare(): devuelve la consulta con los valores sustituidos.
			 *
			 * @param string $sql Consulta.
			 * @param mixed  ...$args Valores.
			 * @return string
			 */
			public function prepare( $sql, ...$args ) {
				$args = ( 1 === count( $args ) && is_array( $args[0] ) ) ? $args[0] : $args;
				foreach ( $args as $valor ) {
					$sql = preg_replace( '/%[sdf]/', is_string( $valor ) ? "'" . $valor . "'" : (string) $valor, $sql, 1 );
				}
				return $sql;
			}
		};
	}
	if ( ! function_exists( 'get_the_title' ) ) {
		function get_the_title( $post = null ) {
			$id = is_object( $post ) ? (int) $post->ID : (int) $post;
			return $GLOBALS['convoca_as_titulos'][ $id ] ?? 'Pregunta ' . $id;
		}
	}
	if ( ! function_exists( 'wp_generate_uuid4' ) ) {
		function wp_generate_uuid4() {
			return 'uuid-de-pruebas';
		}
	}
	if ( ! function_exists( 'esc_url_raw' ) ) {
		function esc_url_raw( $url ) {
			return (string) $url;
		}
	}
	if ( ! function_exists( 'is_admin' ) ) {
		function is_admin() {
			return false;
		}
	}


	// ── Pantallas de administración ───────────────────────────────────────────────
	if ( ! function_exists( 'add_menu_page' ) ) {
		function add_menu_page( $titulo, $menu, $cap, $slug, $cb = '', $icono = '', $pos = null ) {
			$GLOBALS['convoca_as_menus'][ $slug ] = compact( 'titulo', 'cap', 'cb' );
			return 'hook-' . $slug;
		}
	}
	if ( ! function_exists( 'add_submenu_page' ) ) {
		function add_submenu_page( $padre, $titulo, $menu, $cap, $slug, $cb = '' ) {
			$GLOBALS['convoca_as_submenus'][ $slug ] = compact( 'padre', 'titulo', 'cap', 'cb' );
			return 'hook-' . $slug;
		}
	}
	if ( ! function_exists( 'wp_enqueue_style' ) ) {
		function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = '', $media = 'all' ) {
			$GLOBALS['convoca_as_estilos'][ $handle ] = compact( 'src', 'deps', 'ver' );
		}
	}
	if ( ! function_exists( 'wp_enqueue_script' ) ) {
		function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = '', $footer = false ) {
			$GLOBALS['convoca_as_scripts'][ $handle ] = compact( 'src', 'deps', 'ver', 'footer' );
		}
	}
	if ( ! function_exists( 'wp_localize_script' ) ) {
		function wp_localize_script( $handle, $objeto, $datos ) {
			$GLOBALS['convoca_as_localizados'][ $objeto ] = $datos;
			return true;
		}
	}
	if ( ! function_exists( 'rest_url' ) ) {
		function rest_url( $ruta = '' ) {
			return 'https://example.test/wp-json/' . ltrim( (string) $ruta, '/' );
		}
	}
	if ( ! function_exists( 'wp_create_nonce' ) ) {
		function wp_create_nonce( $accion = -1 ) {
			return 'nonce-de-pruebas';
		}
	}
	if ( ! function_exists( 'get_current_screen' ) ) {
		function get_current_screen() {
			$id = $GLOBALS['convoca_as_pantalla'] ?? null;
			return null === $id ? null : (object) array( 'id' => $id );
		}
	}
	if ( ! function_exists( '_n' ) ) {
		function _n( $singular, $plural, $numero, $dominio = null ) {
			return 1 === (int) $numero ? $singular : $plural;
		}
	}
	if ( ! function_exists( 'esc_url' ) ) {
		function esc_url( $url ) {
			return (string) $url;
		}
	}
	if ( ! function_exists( 'esc_html_e' ) ) {
		function esc_html_e( $texto, $dominio = null ) {
			echo (string) $texto;
		}
	}
	if ( ! function_exists( 'wp_nonce_url' ) ) {
		function wp_nonce_url( $url, $accion = -1, $nombre = '_wpnonce' ) {
			return $url . ( str_contains( (string) $url, '?' ) ? '&' : '?' ) . $nombre . '=nonce';
		}
	}
	if ( ! defined( 'CONVOCA_ASSISTANT_ASSETS_URL' ) ) {
		define( 'CONVOCA_ASSISTANT_ASSETS_URL', 'https://example.test/wp-content/plugins/convoca-assistant/assets/' );
	}
	if ( ! defined( 'CONVOCA_ASSISTANT_DIR' ) ) {
		define( 'CONVOCA_ASSISTANT_DIR', dirname( __DIR__ ) . '/' );
	}


	if ( ! function_exists( 'get_posts' ) ) {
		function get_posts( $args = array() ) {
			// Lo que prepare la prueba para ese tipo de contenido; vacío por defecto.
			$GLOBALS['convoca_as_posts_consultados'][ $args['post_type'] ?? '' ] = $args;
			return $GLOBALS['convoca_as_posts'][ $args['post_type'] ?? '' ] ?? array();
		}
	}


	// Dobles de REST y del núcleo: en su propio fichero, porque declaran clases y funciones.
	require_once __DIR__ . '/StubRest.php';
	require_once __DIR__ . '/StubCoreUtils.php';

	require_once __DIR__ . '/StubLogger.php';

	if ( ! function_exists( 'current_filter' ) ) {
		/**
		 * Gancho en curso (doble).
		 *
		 * @return string
		 */
		function current_filter() {
			return $GLOBALS['convoca_as_gancho_actual'] ?? '';
		}
	}
	if ( ! function_exists( 'wp_schedule_single_event' ) ) {
		/**
		 * Apunta un evento único programado.
		 *
		 * @param int    $cuando Marca de tiempo.
		 * @param string $gancho Nombre.
		 * @return bool
		 */
		function wp_schedule_single_event( $cuando, $gancho ) {
			$GLOBALS['convoca_as_eventos_unicos'][] = array( $cuando, $gancho );
			return true;
		}
	}

	if ( ! function_exists( 'wp_upload_dir' ) ) {
		/**
		 * Directorio de subidas (doble).
		 *
		 * @return array<string, string>
		 */
		function wp_upload_dir() {
			$base = $GLOBALS['convoca_as_upload_base'] ?? sys_get_temp_dir();
			return array( 'basedir' => $base, 'baseurl' => 'https://example.test/uploads' );
		}
	}
	if ( ! function_exists( 'human_time_diff' ) ) {
		/**
		 * Diferencia legible entre dos marcas (doble).
		 *
		 * @param int $desde Marca inicial.
		 * @param int $hasta Marca final.
		 * @return string
		 */
		function human_time_diff( $desde = 0, $hasta = 0 ) {
			return '5 minutos';
		}
	}

	if ( ! function_exists( 'wp_register_script' ) ) {
		/**
		 * Registra un script (doble que apunta el alta).
		 *
		 * @param string $handle Handle.
		 * @param string $src    URL.
		 * @param array  $deps   Dependencias.
		 * @param string $ver    Versión.
		 * @param bool   $footer En el pie.
		 * @return bool
		 */
		function wp_register_script( $handle, $src = '', $deps = array(), $ver = '', $footer = false ) {
			$GLOBALS['convoca_as_registrados'][ $handle ] = compact( 'src', 'deps', 'ver', 'footer' );
			return true;
		}
	}
	if ( ! function_exists( 'wp_register_style' ) ) {
		/**
		 * Registra una hoja (doble que apunta el alta).
		 *
		 * @param string $handle Handle.
		 * @param string $src    URL.
		 * @param array  $deps   Dependencias.
		 * @param string $ver    Versión.
		 * @return bool
		 */
		function wp_register_style( $handle, $src = '', $deps = array(), $ver = '' ) {
			$GLOBALS['convoca_as_registrados'][ $handle ] = compact( 'src', 'deps', 'ver' );
			return true;
		}
	}
	if ( ! function_exists( 'has_shortcode' ) ) {
		/**
		 * ¿El contenido lleva ese shortcode?
		 *
		 * @param string $contenido Contenido.
		 * @param string $tag       Shortcode.
		 * @return bool
		 */
		function has_shortcode( $contenido, $tag ) {
			return str_contains( (string) $contenido, '[' . $tag );
		}
	}
	if ( ! function_exists( 'esc_attr' ) ) {
		/**
		 * Escapa para atributo (doble).
		 *
		 * @param string $t Texto.
		 * @return string
		 */
		function esc_attr( $t ) {
			return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' );
		}
	}

}

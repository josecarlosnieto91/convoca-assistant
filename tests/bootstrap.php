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
			// La prueba puede registrar tipos (los CPT del plugin los registra Knowledge_Base::init
			// en WordPress; aquí no hay `init` que los dé de alta). Sin nada preparado, los de serie.
			$registrados = $GLOBALS['convoca_as_tipos_registrados'] ?? null;
			if ( is_array( $registrados ) ) {
				return in_array( $type, $registrados, true );
			}
			return in_array( $type, array( 'post', 'page' ), true );
		}
	}

	if ( ! function_exists( 'get_post' ) ) {
		function get_post( $id = null ) {
			if ( null === $id ) {
				// El post «actual» de la petición: lo fija la prueba (null por defecto, como antes).
				return $GLOBALS['convoca_as_post'] ?? null;
			}
			// Si la prueba ha preparado ese id, se usa tal cual; si no, un WP_Post mínimo (los
			// proveedores tipan contra WP_Post y un stdClass los revienta).
			if ( isset( $GLOBALS['convoca_as_posts'][ (int) $id ] ) && is_object( $GLOBALS['convoca_as_posts'][ (int) $id ] ) ) {
				return $GLOBALS['convoca_as_posts'][ (int) $id ];
			}
			$post           = new \WP_Post();
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
			// Se apunta el borrado: la desinstalación se juzga por lo que deja de existir.
			$GLOBALS['convoca_as_borrados'][] = (string) $option;
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
			// Se apunta el gancho limpiado: la desinstalación tiene que dejar el cron vacío.
			$GLOBALS['convoca_as_cron_limpiados'][] = (string) $hook;
			unset( $GLOBALS['_assistant_cron'][ $hook ] );
			return true;
		}
	}

	// ─── Hooks (no-op) ───
	// Ojo: `convoca_as_acciones[$tag]` guarda SOLO el último callback, que es cómodo para
	// disparar un gancho a mano pero NO es el contrato de WordPress (allí cada gancho tiene
	// una lista). Por eso se apunta también la lista completa en `convoca_as_acciones_lista`:
	// sin ella no se puede comprobar que dos clases distintas se enganchen al mismo gancho.

	if ( ! function_exists( 'add_action' ) ) {
		function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
			$GLOBALS['convoca_as_acciones'][ $tag ]                = $callback;
			$GLOBALS['convoca_as_acciones_lista'][ $tag ][]        = $callback;
			return true;
		}
	}
	if ( ! function_exists( 'do_action' ) ) {
		function do_action( $tag, ...$args ) {
			return true;
		}
	}
	if ( ! function_exists( 'add_shortcode' ) ) {
		/**
		 * Registra un shortcode (doble que apunta el alta).
		 *
		 * @param string   $tag      Shortcode.
		 * @param callable $callback Callback.
		 * @return void
		 */
		function add_shortcode( $tag, $callback ) {
			$GLOBALS['convoca_as_shortcodes'][ $tag ] = $callback;
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
	if ( ! function_exists( 'wp_verify_nonce' ) ) {
		function wp_verify_nonce( $nonce, $accion = -1 ) {
			unset( $accion );
			$validos = $GLOBALS['_wp_stores']['nonces_validos'] ?? null;
			if ( null === $validos ) {
				return 1;
			}
			return in_array( (string) $nonce, array_map( 'strval', (array) $validos ), true ) ? 1 : false;
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
	if ( ! function_exists( 'sanitize_textarea_field' ) ) {
		function sanitize_textarea_field( $valor ) {
			$valor = is_string( $valor ) ? $valor : (string) $valor;
			$limpio = trim( preg_replace( '/<[^>]*>/', '', $valor ) );
			return $limpio;
		}
	}
	// ─── WooCommerce (SDK externo: se dobla; la clase del producto NO) ───
	if ( ! function_exists( 'wc_get_product' ) ) {
		function wc_get_product( $id ) {
			return $GLOBALS['convoca_as_productos'][ (int) $id ] ?? null;
		}
	}
	if ( ! function_exists( 'wc_price' ) ) {
		function wc_price( $importe ) {
			return number_format( (float) $importe, 2, ',', '.' ) . ' €';
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

			// Lo que se inserta tiene que aparecer después en `get_posts()`: si no, «ya existe» nunca ve lo que
			// acaba de crear esta misma petición y el paquete de arranque duplicaba en la segunda pasada.
			$tipo = (string) ( $datos['post_type'] ?? 'post' );
			$post = new \stdClass();
			$post->ID         = $id;
			$post->post_type  = $tipo;
			$post->post_title = (string) ( $datos['post_title'] ?? '' );
			$GLOBALS['convoca_as_posts'][ $tipo ][] = $post;

			return $id;
		}
	}
	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( $cosa ) {
			return false;
		}
	}
	if ( ! function_exists( 'delete_post_meta' ) ) {
		function delete_post_meta( $id, $clave ) {
			unset( $GLOBALS['_assistant_meta'][ (int) $id ][ $clave ] );
			return true;
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
			// La prueba decide si la petición es del panel; por defecto, no (como antes).
			return (bool) ( $GLOBALS['convoca_as_es_admin'] ?? false );
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
			$tipo = $args['post_type'] ?? '';
			$GLOBALS['convoca_as_posts_consultados'][ $tipo ] = $args;
			$posts = $GLOBALS['convoca_as_posts'][ $tipo ] ?? array();

			// Filtro por metadato, como WordPress: quien pide `meta_key`/`meta_value` no recibe los que no lo tienen.
			// Sin esto, «cuántas del paquete hay» contaba todas y la deduplicación del arranque era decorativa.
			if ( ! empty( $args['meta_key'] ) ) {
				$clave   = (string) $args['meta_key'];
				$buscado = (string) ( $args['meta_value'] ?? '' );
				$posts   = array_values(
					array_filter(
						$posts,
						static function ( $post ) use ( $clave, $buscado ) {
							$id    = is_object( $post ) ? (int) $post->ID : (int) $post;
							$valor = $GLOBALS['_assistant_meta'][ $id ][ $clave ] ?? null;
							return null !== $valor && (string) $valor === $buscado;
						}
					)
				);
			}

			// Filtro por título exacto: es como el paquete de arranque sabe que una pregunta ya está puesta.
			if ( ! empty( $args['title'] ) ) {
				$titulo = (string) $args['title'];
				$posts  = array_values(
					array_filter(
						$posts,
						static function ( $post ) use ( $titulo ) {
							if ( is_object( $post ) ) {
								return (string) ( $post->post_title ?? '' ) === $titulo;
							}
							$datos = $GLOBALS['convoca_as_insertados'][ (int) $post ] ?? array();
							return (string) ( $datos['post_title'] ?? '' ) === $titulo;
						}
					)
				);
			}

			$limite = (int) ( $args['numberposts'] ?? 0 );
			if ( $limite > 0 ) {
				$posts = array_slice( $posts, 0, $limite );
			}

			return $posts;
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

	if ( ! function_exists( 'strip_shortcodes' ) ) {
		/**
		 * Quita shortcodes (doble).
		 *
		 * @param string $texto Texto.
		 * @return string
		 */
		function strip_shortcodes( $texto ) {
			return preg_replace( '/\[[^\]]+\]/', '', (string) $texto );
		}
	}
	if ( ! function_exists( 'get_the_terms' ) ) {
		/**
		 * Términos de un post (doble que lee de convoca_as_terminos).
		 *
		 * @param int    $post_id  Post.
		 * @param string $taxonomy Taxonomía.
		 * @return array|false
		 */
		function get_the_terms( $post_id, $taxonomy ) {
			return $GLOBALS['convoca_as_terminos'][ (int) $post_id ][ $taxonomy ] ?? false;
		}
	}
	if ( ! function_exists( 'wp_list_pluck' ) ) {
		/**
		 * Extrae un campo de una lista (doble).
		 *
		 * @param array  $lista Lista.
		 * @param string $campo Campo.
		 * @return array
		 */
		function wp_list_pluck( $lista, $campo ) {
			return array_map( static fn( $fila ) => is_object( $fila ) ? ( $fila->$campo ?? '' ) : ( $fila[ $campo ] ?? '' ), (array) $lista );
		}
	}
	if ( ! function_exists( 'get_the_content' ) ) {
		/**
		 * Contenido de un post (doble).
		 *
		 * @param mixed $a    Ignorado.
		 * @param bool  $b    Ignorado.
		 * @param mixed $post Post.
		 * @return string
		 */
		function get_the_content( $a = null, $b = false, $post = null ) {
			return (string) ( is_object( $post ) ? ( $post->post_content ?? '' ) : '' );
		}
	}
	if ( ! function_exists( 'get_the_excerpt' ) ) {
		/**
		 * Extracto de un post (doble).
		 *
		 * @param mixed $post Post.
		 * @return string
		 */
		function get_the_excerpt( $post = null ) {
			return (string) ( is_object( $post ) ? ( $post->post_excerpt ?? '' ) : '' );
		}
	}
	if ( ! function_exists( 'get_the_post_thumbnail_url' ) ) {
		/**
		 * Miniatura de un post (doble).
		 *
		 * @param mixed  $post Post.
		 * @param string $tam  Tamaño.
		 * @return string|false
		 */
		function get_the_post_thumbnail_url( $post = null, $tam = '' ) {
			return $GLOBALS['convoca_as_miniatura'] ?? false;
		}
	}

	if ( ! function_exists( 'wp_strip_all_tags' ) ) {
		/**
		 * Quita etiquetas y normaliza (doble de WordPress).
		 *
		 * @param string $texto Texto.
		 * @return string
		 */
		function wp_strip_all_tags( $texto ) {
			return trim( strip_tags( (string) $texto ) );
		}
	}
	if ( ! function_exists( 'html_entity_decode' ) ) {
		// html_entity_decode es nativa; este bloque existe para dejar claro que NO se dobla.
	}

	if ( ! function_exists( 'get_permalink' ) ) {
		/**
		 * Enlace de una entrada (doble).
		 *
		 * @param mixed $post Entrada o id.
		 * @return string
		 */
		function get_permalink( $post = 0 ) {
			$id = is_object( $post ) ? (int) ( $post->ID ?? 0 ) : (int) $post;
			return 'https://example.test/?p=' . $id;
		}
	}

	if ( ! function_exists( 'wp_get_post_terms' ) ) {
		/**
		 * Términos de un post por taxonomía (doble que lee de convoca_as_terminos).
		 *
		 * @param int    $post_id  Post.
		 * @param string $taxonomy Taxonomía.
		 * @param array  $args     Argumentos.
		 * @return array
		 */
		function wp_get_post_terms( $post_id, $taxonomy, $args = array() ) {
			return $GLOBALS['convoca_as_terminos'][ (int) $post_id ][ $taxonomy ] ?? array();
		}
	}

	// ── Formularios del panel: escapes y helpers de plantilla ────────────────────
	// Estos helpers del núcleo NO son no-ops y el arnés los tenía a cero: sin ellos las
	// plantillas de `assets/templates` no se podían ni incluir. Se copia el contrato, no una
	// versión cómoda:
	//   · checked()/selected()/disabled() comparan como CADENA —(1 == '1', pero también
	//     '' == false— y emiten el atributo con comillas simples, como el núcleo;
	//   · esc_textarea() NO vuelve a codificar entidades (double_encode = false), así que
	//     un `&amp;` guardado se pinta tal cual y una prueba que lo doble se daría cuenta;
	//   · submit_button() imprime un <input type="submit"> con el texto escapado y envuelve
	//     en <p class="submit">, que es lo que hace el panel de verdad.

	if ( ! function_exists( '__checked_selected_helper' ) ) {
		/**
		 * Emite el atributo de estado cuando el valor coincide (contrato del núcleo).
		 *
		 * @param mixed  $helper  Valor actual.
		 * @param mixed  $current Valor de referencia.
		 * @param bool   $echo    Si imprime.
		 * @param string $type    Nombre del atributo.
		 * @return string
		 */
		function __checked_selected_helper( $helper, $current, $echo, $type ) {
			$result = ( (string) $helper === (string) $current ) ? " $type='$type'" : '';
			if ( $echo ) {
				echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- doble del núcleo.
			}
			return $result;
		}
	}
	if ( ! function_exists( 'checked' ) ) {
		/**
		 * Atributo `checked` (doble fiel del núcleo).
		 *
		 * @param mixed $checked Valor.
		 * @param mixed $current Referencia.
		 * @param bool  $echo    Si imprime.
		 * @return string
		 */
		function checked( $checked, $current = true, $echo = true ) {
			return __checked_selected_helper( $checked, $current, $echo, 'checked' );
		}
	}
	if ( ! function_exists( 'selected' ) ) {
		/**
		 * Atributo `selected` (doble fiel del núcleo).
		 *
		 * @param mixed $selected Valor.
		 * @param mixed $current  Referencia.
		 * @param bool  $echo     Si imprime.
		 * @return string
		 */
		function selected( $selected, $current = true, $echo = true ) {
			return __checked_selected_helper( $selected, $current, $echo, 'selected' );
		}
	}
	if ( ! function_exists( 'disabled' ) ) {
		/**
		 * Atributo `disabled` (doble fiel del núcleo).
		 *
		 * @param mixed $disabled Valor.
		 * @param mixed $current  Referencia.
		 * @param bool  $echo     Si imprime.
		 * @return string
		 */
		function disabled( $disabled, $current = true, $echo = true ) {
			return __checked_selected_helper( $disabled, $current, $echo, 'disabled' );
		}
	}
	if ( ! function_exists( 'esc_textarea' ) ) {
		/**
		 * Escapa para textarea sin recodificar entidades (como el núcleo).
		 *
		 * @param string $text Texto.
		 * @return string
		 */
		function esc_textarea( $text ) {
			$charset = get_option( 'blog_charset' );
			$charset = is_string( $charset ) && '' !== $charset ? $charset : 'UTF-8';
			return htmlspecialchars( (string) $text, ENT_QUOTES, $charset, false );
		}
	}
	if ( ! function_exists( 'esc_attr__' ) ) {
		/**
		 * Traduce y escapa para atributo (doble).
		 *
		 * @param string      $text    Texto.
		 * @param string|null $dominio Dominio.
		 * @return string
		 */
		function esc_attr__( $text, $dominio = null ) {
			unset( $dominio );
			return esc_attr( $text );
		}
	}
	if ( ! function_exists( 'esc_attr_e' ) ) {
		/**
		 * Traduce, escapa e imprime para atributo (doble).
		 *
		 * @param string      $text    Texto.
		 * @param string|null $dominio Dominio.
		 * @return void
		 */
		function esc_attr_e( $text, $dominio = null ) {
			echo esc_attr__( $text, $dominio ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
		}
	}
	if ( ! function_exists( 'wp_referer_field' ) ) {
		/**
		 * Campo oculto con la referencia (doble del núcleo).
		 *
		 * @param bool $display Si imprime.
		 * @return string
		 */
		function wp_referer_field( $display = true ) {
			$field = '<input type="hidden" name="_wp_http_referer" value="' . esc_attr( (string) ( $_SERVER['REQUEST_URI'] ?? '' ) ) . '" />';
			if ( $display ) {
				echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
			}
			return $field;
		}
	}
	if ( ! function_exists( 'wp_nonce_field' ) ) {
		/**
		 * Campo oculto de nonce (doble del núcleo: id y name iguales, nonce de la acción).
		 *
		 * @param string|int $accion   Acción.
		 * @param string     $nombre   Nombre del campo.
		 * @param bool       $referer  Si añade el campo de referencia.
		 * @param bool       $display  Si imprime.
		 * @return string
		 */
		function wp_nonce_field( $accion = -1, $nombre = '_wpnonce', $referer = true, $display = true ) {
			$nombre      = esc_attr( $nombre );
			$nonce_field = '<input type="hidden" id="' . $nombre . '" name="' . $nombre . '" value="' . wp_create_nonce( $accion ) . '" />';
			if ( $referer ) {
				$nonce_field .= wp_referer_field( false );
			}
			if ( $display ) {
				echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
			}
			return $nonce_field;
		}
	}
	if ( ! function_exists( 'settings_fields' ) ) {
		/**
		 * Campos ocultos del grupo de ajustes (doble del núcleo).
		 *
		 * @param string $grupo Grupo.
		 * @return void
		 */
		function settings_fields( $grupo ) {
			echo "<input type='hidden' name='option_page' value='" . esc_attr( $grupo ) . "' />";
			echo '<input type="hidden" name="action" value="update" />';
			wp_nonce_field( $grupo . '-options' );
		}
	}
	if ( ! function_exists( 'get_submit_button' ) ) {
		/**
		 * HTML del botón de envío (doble del núcleo).
		 *
		 * @param string|null $texto     Texto.
		 * @param string      $tipo      Tipo (primary/secondary/…).
		 * @param string      $nombre    Nombre del campo.
		 * @param bool        $envolver  Si envuelve en <p class="submit">.
		 * @return string
		 */
		function get_submit_button( $texto = null, $tipo = 'primary', $nombre = 'submit', $envolver = true ) {
			$clases = array( 'button' );
			foreach ( explode( ' ', (string) $tipo ) as $trozo ) {
				$trozo = trim( $trozo );
				if ( '' === $trozo || 'secondary' === $trozo || 'button-secondary' === $trozo ) {
					continue;
				}
				$clases[] = in_array( $trozo, array( 'primary', 'small', 'large' ), true ) ? 'button-' . $trozo : $trozo;
			}
			if ( null === $texto ) {
				$texto = __( 'Save Changes' );
			}
			$boton = '<input type="submit" name="' . esc_attr( $nombre ) . '" id="' . esc_attr( $nombre ) . '" class="' . esc_attr( implode( ' ', array_unique( $clases ) ) ) . '" value="' . esc_attr( $texto ) . '" />';
			if ( $envolver ) {
				$boton = '<p class="submit">' . $boton . '</p>';
			}
			return $boton;
		}
	}
	if ( ! function_exists( 'submit_button' ) ) {
		/**
		 * Imprime el botón de envío (doble del núcleo).
		 *
		 * @param string|null $texto    Texto.
		 * @param string      $tipo     Tipo.
		 * @param string      $nombre   Nombre.
		 * @param bool        $envolver Si envuelve.
		 * @return void
		 */
		function submit_button( $texto = null, $tipo = 'primary', $nombre = 'submit', $envolver = true ) {
			echo get_submit_button( $texto, $tipo, $nombre, $envolver ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado.
		}
	}

	// ── Arranque del plugin: cabecera, rutas y ganchos de activación ─────────────
	if ( ! function_exists( '_cleanup_header_comment' ) ) {
		/**
		 * Limpia el comentario de una cabecera (doble del núcleo).
		 *
		 * @param string $str Valor.
		 * @return string
		 */
		function _cleanup_header_comment( $str ) {
			return trim( preg_replace( '/\s*(?:\*\/|\?>).*/', '', (string) $str ) );
		}
	}
	if ( ! function_exists( 'get_file_data' ) ) {
		/**
		 * Cabeceras de un fichero (doble fiel: lee los primeros 8 KiB y aplica el mismo
		 * regex que el núcleo, así una cabecera mal puesta devuelve '' y no una invención).
		 *
		 * @param string $fichero  Ruta.
		 * @param array  $cabeceras Mapa campo => regex.
		 * @param string $contexto Contexto.
		 * @return array<string, string>
		 */
		function get_file_data( $fichero, $cabeceras = array(), $contexto = '' ) {
			unset( $contexto );
			$datos = array();
			if ( ! is_readable( $fichero ) ) {
				return array_fill_keys( array_keys( (array) $cabeceras ), '' );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- doble de pruebas.
			$contenido = (string) file_get_contents( $fichero, false, null, 0, 8192 );
			foreach ( (array) $cabeceras as $campo => $regex ) {
				$patron = '/^(?:[ 	]*<\?php)?[ 	\/*#@]*' . preg_quote( (string) $regex, '/' ) . ':(.*)$/mi';
				$datos[ $campo ] = ( preg_match( $patron, $contenido, $m ) && '' !== trim( $m[1] ) ) ? _cleanup_header_comment( $m[1] ) : '';
			}
			return $datos;
		}
	}
	if ( ! function_exists( 'plugin_dir_path' ) ) {
		/**
		 * Directorio del plugin con barra final (doble).
		 *
		 * @param string $fichero Fichero.
		 * @return string
		 */
		function plugin_dir_path( $fichero ) {
			return rtrim( dirname( $fichero ), '/\\' ) . '/';
		}
	}
	if ( ! function_exists( 'plugin_dir_url' ) ) {
		/**
		 * URL del plugin con barra final (doble).
		 *
		 * @param string $fichero Fichero.
		 * @return string
		 */
		function plugin_dir_url( $fichero ) {
			return 'https://example.test/wp-content/plugins/' . basename( dirname( $fichero ) ) . '/';
		}
	}
	if ( ! function_exists( 'plugin_basename' ) ) {
		/**
		 * Ruta relativa dentro de plugins (doble).
		 *
		 * @param string $fichero Fichero.
		 * @return string
		 */
		function plugin_basename( $fichero ) {
			return basename( dirname( $fichero ) ) . '/' . basename( $fichero );
		}
	}
	if ( ! function_exists( 'register_activation_hook' ) ) {
		/**
		 * Apunta el gancho de activación (doble que guarda el callback).
		 *
		 * @param string   $fichero Fichero.
		 * @param callable $cb      Callback.
		 * @return void
		 */
		function register_activation_hook( $fichero, $cb ) {
			$GLOBALS['convoca_as_activacion'][ $fichero ] = $cb;
		}
	}
	if ( ! function_exists( 'register_deactivation_hook' ) ) {
		/**
		 * Apunta el gancho de desactivación (doble que guarda el callback).
		 *
		 * @param string   $fichero Fichero.
		 * @param callable $cb      Callback.
		 * @return void
		 */
		function register_deactivation_hook( $fichero, $cb ) {
			$GLOBALS['convoca_as_desactivacion'][ $fichero ] = $cb;
		}
	}
	if ( ! function_exists( 'load_plugin_textdomain' ) ) {
		/**
		 * Carga el dominio de traducciones (doble que apunta la llamada).
		 *
		 * @param string       $dominio    Dominio.
		 * @param string|false $obsoleto   Obsoleto.
		 * @param string|false $ruta       Ruta.
		 * @return bool
		 */
		function load_plugin_textdomain( $dominio, $obsoleto = false, $ruta = false ) {
			$GLOBALS['convoca_as_textdomain'][] = array( $dominio, $obsoleto, $ruta );
			return true;
		}
	}

}

<?php
/**
 * Dobles de la API REST de WordPress para las pruebas unitarias.
 *
 * `register_rest_route()` apunta cada ruta con sus argumentos: es la única forma de comprobar que
 * una ruta existe, con qué método y con qué permiso, sin levantar un WordPress.
 */

namespace {

	if ( ! class_exists( 'WP_REST_Response' ) ) {
		/**
		 * Respuesta REST mínima: datos, código y cabeceras.
		 */
		class WP_REST_Response {
			/** Datos de la respuesta. */
			public $data;
			/** Código de estado. */
			public $status;
			/** Cabeceras puestas. */
			public $headers = array();

			/**
			 * Constructor.
			 *
			 * @param mixed $data   Datos.
			 * @param int   $status Código.
			 */
			public function __construct( $data = null, $status = 200 ) {
				$this->data   = $data;
				$this->status = (int) $status;
			}

			/**
			 * Devuelve los datos.
			 *
			 * @return mixed
			 */
			public function get_data() {
				return $this->data;
			}

			/**
			 * Devuelve el código de estado.
			 *
			 * @return int
			 */
			public function get_status() {
				return $this->status;
			}

			/**
			 * Añade una cabecera.
			 *
			 * @param string $clave  Nombre.
			 * @param string $valor  Valor.
			 * @return void
			 */
			public function header( $clave, $valor ) {
				$this->headers[ $clave ] = $valor;
			}

			/**
			 * Cabeceras puestas.
			 *
			 * @return array<string, string>
			 */
			public function get_headers() {
				return $this->headers;
			}
		}
	}

	if ( ! class_exists( 'WP_Error' ) ) {
		/**
		 * Error de WordPress.
		 */
		class WP_Error {
			/** Código. */
			public $code;
			/** Mensaje. */
			public $message;
			/** Datos. */
			public $data;

			/**
			 * Constructor.
			 *
			 * @param string $code    Código.
			 * @param string $message Mensaje.
			 * @param mixed  $data    Datos.
			 */
			public function __construct( $code = '', $message = '', $data = null ) {
				$this->code    = $code;
				$this->message = $message;
				$this->data    = $data;
			}

			/**
			 * Código del error.
			 *
			 * @return string
			 */
			public function get_error_code() {
				return $this->code;
			}

			/**
			 * Mensaje del error.
			 *
			 * @return string
			 */
			public function get_error_message() {
				return $this->message;
			}
		}
	}

	if ( ! function_exists( 'register_rest_route' ) ) {
		/**
		 * Apunta una ruta registrada.
		 *
		 * @param string $namespace Espacio de nombres.
		 * @param string $ruta      Ruta.
		 * @param array  $args      Argumentos.
		 * @return bool
		 */
		function register_rest_route( $namespace, $ruta, $args = array() ) {
			$GLOBALS['convoca_as_rutas'][ $namespace . $ruta ] = $args;
			return true;
		}
	}

	if ( ! function_exists( '__return_true' ) ) {
		/**
		 * Callback que siempre dice que sí.
		 *
		 * @return bool
		 */
		function __return_true() {
			return true;
		}
	}

	if ( ! class_exists( 'WP_REST_Request' ) ) {
		/**
		 * Petición REST mínima para las pruebas.
		 */
		class WP_REST_Request {
			/** Parámetros. */
			public $params;
			/** Cabeceras. */
			public $headers;

			/**
			 * Constructor.
			 *
			 * @param array $params  Parámetros.
			 * @param array $headers Cabeceras.
			 */
			public function __construct( $params = array(), $headers = array() ) {
				$this->params  = $params;
				$this->headers = $headers;
			}

			/**
			 * Un parámetro.
			 *
			 * @param string $clave Nombre.
			 * @return mixed
			 */
			public function get_param( $clave ) {
				return $this->params[ $clave ] ?? null;
			}

			/**
			 * Todos los parámetros.
			 *
			 * @return array
			 */
			public function get_params() {
				return $this->params;
			}

			/**
			 * Una cabecera.
			 *
			 * @param string $clave Nombre.
			 * @return string|null
			 */
			public function get_header( $clave ) {
				return $this->headers[ $clave ] ?? null;
			}
		}
	}
	if ( ! class_exists( 'WP_Post' ) ) {
		/**
		 * Entrada de WordPress (doble mínimo): los campos que leen los proveedores.
		 */
		class WP_Post {
			/** @var int */
			public $ID = 0;
			/** @var string */
			public $post_title = '';
			/** @var string */
			public $post_content = '';
			/** @var string */
			public $post_excerpt = '';
			/** @var string */
			public $post_status = 'publish';
			/** @var string */
			public $post_type = 'post';
			/** @var string */
			public $post_date = '';
			/** @var string */
			public $post_modified = '';
			/** @var string */
			public $post_name = '';
		}
	}

}

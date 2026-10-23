<?php
/**
 * Doble de `Convoca\Core\Logger` para las pruebas del asistente.
 *
 * El índice apunta en el registro quién lo ensució y cuándo se reconstruyó: se guarda lo escrito
 * para poder comprobarlo, no para que no reviente.
 */

namespace Convoca\Core;

if ( ! class_exists( 'Convoca\\Core\\Logger' ) ) {
	/**
	 * Registro de eventos (doble).
	 */
	class Logger {
		/** Eventos escritos: [nivel, mensaje, contexto]. */
		public static $escritos = array();

		/**
		 * Registra un evento informativo.
		 *
		 * @param string $mensaje  Texto.
		 * @param string $contexto Módulo.
		 * @return void
		 */
		public static function info( $mensaje, $contexto = '' ) {
			self::$escritos[] = array( 'info', $mensaje, $contexto );
		}

		/**
		 * Registra un error.
		 *
		 * @param string $mensaje  Texto.
		 * @param string $contexto Módulo.
		 * @return void
		 */
		public static function error( $mensaje, $contexto = '' ) {
			self::$escritos[] = array( 'error', $mensaje, $contexto );
		}

		/**
		 * Registra un aviso.
		 *
		 * @param string $mensaje  Texto.
		 * @param string $contexto Módulo.
		 * @return void
		 */
		public static function warning( $mensaje, $contexto = '' ) {
			self::$escritos[] = array( 'warning', $mensaje, $contexto );
		}

		/**
		 * Registra una traza.
		 *
		 * @param string $mensaje  Texto.
		 * @param string $contexto Módulo.
		 * @return void
		 */
		public static function debug( $mensaje, $contexto = '' ) {
			self::$escritos[] = array( 'debug', $mensaje, $contexto );
		}
	}
}

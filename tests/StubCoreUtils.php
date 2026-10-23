<?php
/**
 * Doble de `Convoca\Core\Utils` para las pruebas del asistente.
 *
 * El limitador de peticiones del plugin usa el contador atómico del núcleo cuando está
 * disponible (`Utils::check_rate_limit`). Este doble permite decidir en la prueba si deja pasar
 * o si corta, que es lo que hay que comprobar.
 */

namespace Convoca\Core;

if ( ! class_exists( 'Convoca\\Core\\Utils' ) ) {
	/**
	 * Utilidades del núcleo (solo lo que usa el asistente).
	 */
	class Utils {
		/**
		 * ¿Deja pasar la petición?
		 *
		 * @param string $accion  Nombre del contador.
		 * @param int    $maximo  Peticiones permitidas.
		 * @param int    $ventana Ventana en segundos.
		 * @return bool
		 */
		public static function check_rate_limit( $accion, $maximo, $ventana ) {
			$GLOBALS['convoca_as_ratelimit_llamadas'][] = array( $accion, $maximo, $ventana );

			return $GLOBALS['convoca_as_ratelimit_ok'] ?? true;
		}
	}
}

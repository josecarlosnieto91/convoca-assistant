<?php
/**
 * Paquete de arranque del asistente.
 *
 * El asistente es una búsqueda local sobre el contenido del sitio: no se inventa nada, pero tampoco sabe
 * nada que no se le haya escrito. Recién instalado arranca **vacío**, así que las preguntas más corrientes
 * —la cuota, dónde está el centro, cómo hacerse socio— se quedan sin respuesta hasta que alguien se sienta
 * a escribir. Cada frase sin respuesta es una persona que se va a preguntar por WhatsApp o por teléfono.
 *
 * Esto es un punto de partida, no un texto definitivo: son seis preguntas frecuentes corrientes con una
 * respuesta que dice la verdad de cómo funciona la asociación y **dónde está el dato concreto** (la página
 * de contacto, el formulario de alta, la ficha de la actividad). Lo que tiene cifras o direcciones lo
 * escribe la junta: aquí no se inventa ni un importe ni una dirección.
 *
 * Se instala solo al activar el plugin y, en los sitios donde ya estaba instalado, con el botón del aviso
 * del panel. Es idempotente: cada pieza lleva su marca, así que nunca duplica nada.
 *
 * @package Convoca\Assistant
 */

namespace Convoca\Assistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contenido de arranque y su instalación.
 */
class Starter_Pack {

	/**
	 * Marca que llevan las piezas instaladas por este paquete.
	 */
	public const META_MARCA = '_convoca_assistant_arranque';

	/**
	 * Las seis preguntas de arranque.
	 *
	 * @return array<int, array{titulo: string, respuesta: string, claves: string}>
	 */
	public static function contenido(): array {
		return array(
			array(
				'titulo'    => '¿Cuánto cuesta hacerse socio o socia?',
				'claves'    => 'cuota, precio, cuánto cuesta, mensualidad, suscripción, coste, tarifa, '
				. 'socio, socia, membresía, cuota anual',
				'respuesta' => '<p>La cuota la fija la asamblea cada año y se cobra por domiciliación bancaria. '
						. 'El importe vigente y las formas de pago están en la página de <strong>hazte socio</strong>.</p>'
						. '<p>Las actividades tienen un precio para socios y otro general: el descuento se aplica '
						. 'solo cuando te apuntas con tu cuenta de socio. Si tienes cualquier duda con la cuota, '
						. 'escríbenos y te la confirmamos.</p>',
			),
			array(
				'titulo'    => '¿Dónde está el centro social y cuándo abre?',
				'claves'    => 'dónde está, donde está, centro social, dirección, ubicación, cómo llegar, '
				. 'horario, horarios, apertura, sede, local',
				'respuesta' => '<p>La dirección exacta, el plano y el horario de apertura están en la página de '
						. '<strong>contacto</strong>, junto con el teléfono y el correo.</p>'
						. '<p>Si vienes a una actividad concreta, en su ficha tienes la ubicación y la hora '
						. 'de inicio.</p>',
			),
			array(
				'titulo'    => '¿Cómo me hago socio o socia?',
				'claves'    => 'hacerse socio, ser socio, alta, darse de alta, asociarse, '
				. 'inscribirse como socio, membresía, formulario de socios',
				'respuesta' => '<p>Se hace en un minuto desde el <strong>formulario de alta de socios</strong> de la web: '
						. 'rellenas tus datos, aceptas las condiciones y eliges la forma de pago.</p>'
						. '<p>Al terminar recibirás un correo con la confirmación. Si prefieres hacerlo en papel, '
						. 'pásate por el centro y lo rellenamos juntas.</p>',
			),
			array(
				'titulo'    => '¿Cómo puedo ser voluntario o voluntaria?',
				'claves'    => 'voluntario, voluntaria, voluntariado, ser voluntario, colaborar, ayudar, '
				. 'turnos, horas',
				'respuesta' => '<p>Desde el <strong>formulario de voluntariado</strong> de la web nos cuentas en qué te '
						. 'apetece ayudar. La junta lo revisa y te avisa cuando te acepta.</p>'
						. '<p>A partir de ahí puedes apuntarte a los turnos y anotar tus horas desde tu área '
						. 'personal de la web.</p>',
			),
			array(
				'titulo'    => '¿Cómo me apunto a una actividad?',
				'claves'    => 'apuntarse, inscribirse, inscripción, reservar, plaza, plazas, matrícula, '
				. 'actividad, taller, curso',
				'respuesta' => '<p>En la ficha de cada actividad tienes su formulario de inscripción: elige si vas como '
						. 'socio o como público general, rellena tus datos y envíalo. Recibirás un correo con '
						. 'la confirmación.</p>'
						. '<p>Si las plazas están llenas, desde la misma ficha puedes apuntarte a la lista de '
						. 'espera: te avisamos si se libera alguna.</p>',
			),
			array(
				'titulo'    => '¿Cómo puedo contactar con vosotros?',
				'claves'    => 'contacto, contactar, teléfono, correo, email, escribir, whatsapp, '
				. 'hablar con vosotros, atención',
				'respuesta' => '<p>Por teléfono, por WhatsApp o por correo, y también en persona en el centro dentro del '
						. 'horario de apertura. Todos los datos están en la página de <strong>contacto</strong>.</p>'
						. '<p>Si tu duda es sobre tu cuota, tu inscripción o tus horas, dinos tu nombre y el '
						. 'correo con el que te diste de alta y lo miramos enseguida.</p>',
			),
		);
	}

	/**
	 * Sinónimos de arranque, para lo más obvio.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function sinonimos(): array {
		return array(
			'cuota'      => array( 'precio', 'mensualidad', 'suscripción', 'coste', 'tarifa' ),
			'socio'      => array( 'socia', 'membresía', 'asociado', 'asociada', 'socios' ),
			'voluntario' => array( 'voluntaria', 'voluntariado', 'colaborar' ),
			'actividad'  => array( 'actividades', 'taller', 'curso', 'talleres' ),
			'centro'     => array( 'sede', 'local', 'centro social' ),
			'apuntarse'  => array( 'inscribirse', 'inscripción', 'reservar', 'matrícula' ),
			'horario'    => array( 'horarios', 'apertura' ),
			'contacto'   => array( 'contactar', 'teléfono', 'correo', 'email' ),
		);
	}

	/**
	 * Cuántas de las seis están ya puestas.
	 */
	public static function instaladas(): int {
		$puestos = get_posts(
			array(
				'post_type'   => 'convoca_faq',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => self::META_MARCA, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return count( $puestos );
	}

	/**
	 * Si el paquete está entero.
	 */
	public static function esta_instalado(): bool {
		return self::instaladas() >= count( self::contenido() );
	}

	/**
	 * Instala el paquete. No duplica nada: solo crea las que falten.
	 *
	 * @return array{fichas: int, sinonimos: int} Lo que se ha creado de nuevo.
	 */
	public static function instalar(): array {
		$creadas = 0;

		foreach ( self::contenido() as $pieza ) {
			if ( self::ya_existe( $pieza['titulo'] ) ) {
				continue;
			}

			$id = wp_insert_post(
				array(
					'post_type'    => 'convoca_faq',
					'post_status'  => 'publish',
					'post_title'   => $pieza['titulo'],
					'post_content' => $pieza['respuesta'],
				),
				true
			);

			if ( is_wp_error( $id ) || ! $id ) {
				continue;
			}

			update_post_meta( (int) $id, self::META_MARCA, '1' );
			update_post_meta( (int) $id, '_convoca_assistant_keywords', $pieza['claves'] );
			++$creadas;
		}

		$sinonimos = self::instalar_sinonimos();

		// El índice es lo que de verdad lee el buscador: si no se rehace, las preguntas nuevas no se
		// encuentran hasta el siguiente guardado.
		if ( $creadas > 0 && class_exists( __NAMESPACE__ . '\\Indexer' ) ) {
			Indexer::regenerate();
		}

		return array(
			'fichas'    => $creadas,
			'sinonimos' => $sinonimos,
		);
	}

	/**
	 * Añade los sinónimos que falten, sin pisar los que ya haya puestos.
	 *
	 * @return int Cuántos términos nuevos.
	 */
	private static function instalar_sinonimos(): int {
		$actuales = (array) get_option( 'convoca_assistant_synonyms', array() );
		$nuevos   = 0;

		foreach ( self::sinonimos() as $termino => $lista ) {
			if ( isset( $actuales[ $termino ] ) && ! empty( $actuales[ $termino ] ) ) {
				continue;
			}
			$actuales[ $termino ] = $lista;
			++$nuevos;
		}

		if ( $nuevos > 0 ) {
			update_option( 'convoca_assistant_synonyms', $actuales );
		}

		return $nuevos;
	}

	/**
	 * Si esa pregunta ya está puesta (por el paquete o a mano, con el mismo título).
	 *
	 * @param string $titulo Título de la pregunta.
	 */
	private static function ya_existe( string $titulo ): bool {
		$existentes = get_posts(
			array(
				'post_type'   => 'convoca_faq',
				'post_status' => 'any',
				'numberposts' => 1,
				'fields'      => 'ids',
				'title'       => $titulo,
			)
		);

		return ! empty( $existentes );
	}
}

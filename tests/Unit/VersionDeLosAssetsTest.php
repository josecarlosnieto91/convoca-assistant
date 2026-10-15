<?php
/**
 * La versión del plugin, en un solo sitio (issue #10).
 *
 * `CONVOCA_ASSISTANT_VERSION` es el cache-buster de los assets y la versión que se declara en el índice.
 * Estuvo clavada en **0.2.13** mientras el plugin iba por la 0.2.15: los assets se servían con
 * `?ver=0.2.13`, así que la CDN y los navegadores guardaban JS/CSS viejos bajo esa misma URL — el navegador
 * no tenía forma de saber que habían cambiado. Es el mismo defecto que se cerró en la 0.2.9 (allí la
 * constante estaba en 0.2.7) y volvió a pasar al subir la versión a mano, porque nada lo comprobaba.
 *
 * Este contrato lo comprueba, y por eso mira el CÓDIGO y no sólo los números: la constante tiene que
 * **derivarse de la cabecera** (como en el resto del ecosistema) y no ser un literal que alguien tenga que
 * acordarse de subir. Probado en negativo: sobre el fichero de HEAD, que tenía el literal, estas aserciones
 * caen.
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

/**
 * Coherencia de la versión del plugin.
 */
class VersionDeLosAssetsTest extends \PHPUnit\Framework\TestCase {

	/**
	 * Fichero principal del plugin.
	 *
	 * @return string
	 */
	private function principal(): string {
		$ruta = dirname( __DIR__, 2 ) . '/convoca-assistant.php';
		$this->assertFileExists( $ruta );

		return (string) file_get_contents( $ruta );
	}

	/**
	 * La versión del plugin, tal y como la declara la cabecera.
	 *
	 * @return string
	 */
	private function version_de_la_cabecera(): string {
		$this->assertSame(
			1,
			preg_match( '/^ \* Version:\s+([0-9][0-9A-Za-z.\-]*)/m', $this->principal(), $m ),
			'La cabecera del plugin no declara Version:.'
		);

		return $m[1];
	}

	/**
	 * La constante no es un literal: sale de la cabecera.
	 */
	public function test_la_constante_de_version_se_deriva_de_la_cabecera(): void {
		$fuente = $this->principal();

		$this->assertSame(
			0,
			preg_match( "/define\(\s*'CONVOCA_ASSISTANT_VERSION',\s*'[0-9]/", $fuente ),
			"CONVOCA_ASSISTANT_VERSION está clavada con un literal: al subir la versión se queda atrás y los\n" .
			'assets se sirven con un ?ver= viejo. Tiene que derivarse de la cabecera con get_file_data().'
		);

		$this->assertMatchesRegularExpression(
			"/get_file_data\(\s*__FILE__.*\n.*CONVOCA_ASSISTANT_VERSION/s",
			$fuente,
			'La constante tiene que salir de la cabecera del plugin (get_file_data), no de un literal.'
		);
	}

	/**
	 * La cabecera y el `Stable tag` del readme dicen lo mismo, y el `.pot` también.
	 */
	public function test_las_tres_versiones_coinciden(): void {
		$raiz    = dirname( __DIR__, 2 );
		$version = $this->version_de_la_cabecera();

		$readme = (string) file_get_contents( $raiz . '/readme.txt' );
		$this->assertMatchesRegularExpression(
			'/^Stable tag:\s*' . preg_quote( $version, '/' ) . '\s*$/m',
			$readme,
			"El «Stable tag» del readme.txt no coincide con la versión de la cabecera ({$version})."
		);

		$pot = (string) file_get_contents( $raiz . '/languages/convoca-assistant.pot' );
		$this->assertStringContainsString(
			$version,
			$pot,
			"El .pot no lleva la versión {$version} en su cabecera: para eso está check-pot-al-dia.py."
		);
	}
}

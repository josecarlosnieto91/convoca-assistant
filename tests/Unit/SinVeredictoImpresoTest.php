<?php
/**
 * La suite no imprime veredictos que nadie lee (issue #9).
 *
 * Contexto: el banco de calidad de búsqueda escribía «VEREDICTO DE LA FASE 2: NO CUMPLE» y la suite terminaba en
 * verde con exit 0. Un veredicto que solo se imprime no lo lee nadie: es el caso de manual de un verde que no
 * valida nada.
 *
 * Criterio de hecho del issue: *la suite no puede imprimir «NO CUMPLE» y salir con 0*. Aquí se vigilan las dos
 * mitades de eso: que no se imprima el veredicto, **y que la medida siga estando** — porque borrar el número
 * para «arreglar» el texto sería cambiar un problema por otro peor.
 *
 * @package Convoca\Assistant\Tests
 */

namespace Convoca\Assistant\Tests;

use PHPUnit\Framework\TestCase;

class SinVeredictoImpresoTest extends TestCase {

	/** El código SIN comentarios: los comentarios que EXPLICAN el arreglo nombran lo que se quitó. */
	private function fuente(): string {
		return php_strip_whitespace( dirname( __DIR__ ) . '/Unit/SearchQualityTest.php' );
	}

	public function test_no_se_imprime_ningun_veredicto(): void {
		$this->assertStringNotContainsString( 'NO CUMPLE', $this->fuente(), 'O falla, o no lo dice' );
		$this->assertStringNotContainsString( 'VEREDICTO', $this->fuente(), 'La palabra es la que se lee como una puerta que no existe' );
	}

	public function test_la_medida_sigue_estando(): void {
		$fuente = $this->fuente();
		$this->assertStringContainsString( 'nDCG@5', $fuente, 'El número tiene que seguir a la vista' );
		$this->assertStringContainsString( '+0,05', $fuente, 'Y el objetivo de la fase, para que se pueda juzgar' );
		$this->assertStringContainsString( '>= +10%%', $fuente, 'Lo mismo para la fase 1' );
	}
}

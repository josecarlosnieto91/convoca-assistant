<?php
/**
 * Tabla combinada de la calidad del buscador (los dos motores, consulta a consulta).
 *
 * Lee lo que han dejado los dos arneses y lo pinta junto: es la tabla de trabajo de las
 * fases siguientes y la que se pega en el issue y en la spec.
 *
 * Uso: php tests/quality-table.php
 *
 * @package Convoca\Assistant\Tests
 */

$fixtures = __DIR__ . '/fixtures/';
$fuentes  = array(
	'servidor' => $fixtures . 'search-quality-server.json',
	'cliente'  => $fixtures . 'search-quality-client.json',
);

$datos = array();
foreach ( $fuentes as $motor => $ruta ) {
	if ( ! file_exists( $ruta ) ) {
		fwrite( STDERR, "Falta {$ruta}: hay que correr antes el arnés del {$motor}.\n" );
		exit( 1 );
	}
	$datos[ $motor ] = json_decode( (string) file_get_contents( $ruta ), true );
}

$por_motor = array();
foreach ( $datos as $motor => $d ) {
	foreach ( $d['filas'] as $f ) {
		$por_motor[ $motor ][ $f['query'] ] = $f;
	}
}

$orden = array();
foreach ( $datos['servidor']['filas'] as $f ) {
	$orden[] = $f['query'];
}

$ancho = 0;
foreach ( $orden as $q ) {
	$ancho = max( $ancho, mb_strlen( $q ) );
}

echo "\n=== CALIDAD DEL BUSCADOR — los dos motores ===\n";
echo "Corpus: " . $datos['servidor']['corpus'] . " (congelado)\n\n";
printf( "  %s  %-10s %-10s\n", str_pad( 'CONSULTA', $ancho ), 'SERVIDOR', 'CLIENTE' );
echo '  ' . str_repeat( '-', $ancho + 24 ) . "\n";

foreach ( $orden as $q ) {
	$s = $por_motor['servidor'][ $q ] ?? null;
	$c = $por_motor['cliente'][ $q ] ?? null;

	$etiqueta = static function ( $f ) {
		if ( null === $f ) {
			return '—';
		}
		if ( empty( $f['esperados'] ) ) {
			return $f['cobertura'] ? 'cubre' : 'nada';
		}
		return 0 === $f['posicion'] ? 'FALLA' : 'pos ' . $f['posicion'];
	};

	$marca = '';
	if ( $s && $c && ! empty( $s['esperados'] ) ) {
		$ps = $s['posicion'];
		$pc = $c['posicion'];
		if ( $ps !== $pc ) {
			$marca = ( ( 0 === $pc || $pc > $ps ) && 0 !== $ps ) ? '  <-- el cliente va peor' : ( ( 0 === $ps || $ps > $pc ) && 0 !== $pc ? '  <-- el servidor va peor' : '  <-- discrepan' );
		}
	}

	printf( "  %s  %-10s %-10s%s\n", str_pad( $q, $ancho ), $etiqueta( $s ), $etiqueta( $c ), $marca );
}

echo "\n";
printf( "  %-14s %10s %10s %10s %10s\n", 'RESUMEN', 'Recall@1', 'Recall@3', 'MRR@10', 'nDCG@5' );
foreach ( $datos as $motor => $d ) {
	printf(
		"  %-14s %9.1f%% %9.1f%% %10.3f %10.3f\n",
		$motor,
		$d['resumen']['recall1'] * 100,
		$d['resumen']['recall3'] * 100,
		$d['resumen']['mrr10'],
		$d['resumen']['ndcg5']
	);
}
$sin_fallos = array();
foreach ( $orden as $q ) {
	$s = $por_motor['servidor'][ $q ];
	if ( ! empty( $s['esperados'] ) && 0 === $s['posicion'] ) {
		$sin_fallos[] = $q;
	}
}
if ( $sin_fallos ) {
	echo "\n  Consultas que el servidor no acierta ni en el top-10 ({count}):\n";
	foreach ( $sin_fallos as $q ) {
		echo "    · {$q}\n";
	}
}
echo "\n  Tabla de trabajo de las fases siguientes: cada línea sin «pos 1» es un candidato.\n\n";

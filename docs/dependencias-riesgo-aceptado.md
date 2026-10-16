# Dependencias con riesgo aceptado

Aqui se apunta lo que el escaner de dependencias marca y NO se arregla, con el motivo. La regla:
un aviso no se cierra por costumbre, se cierra cuando esta escrito por que no aplica.

## `sprintf-js` 1.0.3 — GHSA-hp3w-g68c-fv3c (CVE-2026-97058, medio)

- **Que dice**: denegacion de servicio por especificadores de precision sin limite.
- **Por que no se arregla**: el aviso cubre **todas** las versiones publicadas (`<= 1.1.3`, y la
  ultima publicada es la 1.1.3): **no existe version corregida**. El paquete esta sin mantenimiento.
- **Por que el riesgo es aceptable**: llega por una cadena que solo existe en el **arnes de
  pruebas** — `jest` -> `babel-plugin-istanbul` -> `@istanbuljs/load-nyc-config` -> `js-yaml@3` ->
  `argparse@1` -> `sprintf-js` — y **nunca recibe entrada de terceros**: `argparse` la usa para dar
  formato a sus propios mensajes de error. No entra en el plugin que se despliega.
- **Que se probo**: subir a `jest@30` (la suite sigue verde, 17 pruebas) **no la elimina**: la
  cadena sigue igual. Forzar `js-yaml@4` con `overrides` rompe `load-nyc-config`, que usa la API de
  la version 3. No hay fork mantenido que merezca la pena meter por esta via.
- **Cuando se revisa**: si `sprintf-js` publica una version fuera de ese rango, o si alguien saca
  del arbol de pruebas esa cadena, se sube y se quita este apartado.

## `jest` 29 → 30 (16/10/2026): esto SÍ tenía arreglo

Lo primero que se miró fue solo el aviso concreto (`sprintf-js`) y se concluyó «no hay arreglo». Al
mirar el árbol **entero** con `npm audit`, el panorama era otro: **34 vulnerabilidades, 29 de ellas
altas** (`braces`, `micromatch`, la familia `@jest/*`), todas de la cadena de `jest` 29, y npm decía
que se arreglaban subiendo a **`jest` 30.5.2** (salto de versión mayor, solo en dependencias de
prueba).

Comprobado en instalación limpia (`rm -rf node_modules` + `npm install`):

| | antes (jest 29) | después (jest 30) |
|---|---|---|
| altas | **29** | **0** |
| moderadas | 5 | 19 |
| total | 34 | 19 |

Y la suite sigue verde: 17 pruebas de cliente, y `composer test` (phpcs + phpstan + phpunit, 36
pruebas / 224 aserciones) en **exit 0**. Las 19 moderadas que quedan son la misma cadena de
`sprintf-js` contada por paquete.

**Error propio anotado**: la primera vez se probó el salto a jest 30, se vio que `sprintf-js` seguía
apareciendo y se **revirtió** — sin mirar el recuento total, que es donde estaba la ganancia (29
altas). Mirar el aviso suelto no es mirar el árbol.

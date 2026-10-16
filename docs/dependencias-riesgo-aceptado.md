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

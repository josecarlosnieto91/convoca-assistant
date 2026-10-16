# Nota: la rama `gauntlet/p6-assistant-busqueda` (issue #5)

Esta rama quedo **fosilizada**: su arreglo del buscador **ya esta en `main`**, pero no por una fusion
de git (su commit no es ancestro de `main`), sino porque se re-implemento en la linea posterior.
Se borro el 16/10/2026. Su commit, por si hiciera falta alguna vez, era **`c53aeea`**.

## Como se comprobo (16/10/2026)

- `git diff --stat <rama> main -- includes/` deja `Searcher.php` con **14 lineas** de diferencia, y
  son `umbral_configurado()` (la 0.2.20, POSTERIOR: la anade `main`). El codigo del arreglo —umbral,
  dedupe por la mejor puntuacion, desempate a 0,90, peso solo en coincidencia fuerte— coincide.
- `assets/js/assistant-chat.js` es **identico** en las dos (md5).
- Los tests que traia la rama (`tests/Unit/BusquedaRankingTest.php` y
  `tests/assistant-chat-busqueda.test.js`) estan **en `main`** y pasan: 22/22 unitarios y 17 de cliente.
- **Control negativo** (lo que prueba que los tests miden algo): con el codigo del ancestro comun
  (`92833fd`) y SOLO los tests de la rama, fallan 2 en PHP —«el peso no desempata en coincidencia
  floja» (0,4151 frente a 0,6591) y «la FAQ debe ganar el empate a 0,90» (10 frente a 20)— y 3 de 5
  en el cliente. Con el codigo de `main`, los cinco pasan.

## Consecuencia

**No hay que fusionarla.** Fusionarla habria **revertido 485 lineas** de trabajo posterior de `main`
(el `Starter_Pack`, los cambios de `Admin`/`Statistics`, tests y CI incluidos): la rama es una linea
vieja, no un pendiente.

El arnes de calidad (`composer test:quality`) **no** sirve para validar este arreglo: mide posiciones
(recall, MRR, nDCG) con `search_threshold` a 0,1 a proposito, y el cambio es de **umbral y desempate**.
Sus numeros son identicos con y sin el arreglo. La vara valida son los tests de ranking, en positivo y
en negativo.

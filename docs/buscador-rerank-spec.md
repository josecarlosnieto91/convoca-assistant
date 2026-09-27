# Fase 2 — Reranking del top-N (solo heurístico propio)

Spec de la Fase 2 del goal `convoca-buscador-evaluacion-2026-09`. Va antes del código (regla 1).

- Parte de la línea base (`buscador-evaluacion-spec.md`) y del resultado de la Fase 1
  (`buscador-fusion-spec.md`): **la fusión no supera al compuesto**.
- **Estado del set de consultas: sin revisión final de JC.** Las métricas de este documento son
  **provisionales**.

---

## 1. Qué cambia respecto al plan original (y por qué)

El goal preveía el reranker **sobre el top-20 de la fusión**. La Fase 1 demostró que la fusión va por
detrás del compuesto, así que rerankear sobre ella sería afinar el ranking equivocado.

Lo que se hace en su lugar, y que sigue siendo la Fase 2:

- El reranker se mide **sobre el ranking del compuesto**, que es el que está en producción y el que mejor
  acierta. Ahí es donde hay margen, si lo hay: los fallos de la línea base son la respuesta esperada en
  las posiciones 2-7, no fuera del top-10. Un buen reranker del top-20 los subiría.
- **Solo backend heurístico propio.** El goal preveía un segundo backend LLM vía `Provider_Registry`. **No
  se implementa**, y se dice por qué: el criterio de esta fase se puede demostrar (o refutar) sin tocar un
  modelo, y meter un proveedor de red añadiría latencia, coste y una superficie de fallo que hoy no hacen
  falta para decidir. Si el heurístico deja margen, ya habrá motivo para plantearlo. **YAGNI.**

## 2. Qué se implementa

### `includes/Reranker.php` — heurístico propio, sin red

Sobre las **20 primeras** entradas del ranking base, calcula para cada una una señal de «esto responde a
la pregunta» y reordena:

| Componente | Qué mira | Por qué |
|---|---|---|
| `solape` | proporción de términos de la consulta presentes en el título, las palabras clave, el extracto y el cuerpo | el compuesto mira mucho el título; aquí el cuerpo pesa |
| `titulo` | coincidencia exacta (o contención) del título normalizado con la consulta | el caso «esta entrada ES la respuesta», que el compuesto premia a 0,90 |
| `cobertura` | términos distintos de la consulta que aparecen en el título | penaliza coincidencias de una sola palabra en títulos largos |
| `prioridad` | tipo prioritario (`convoca_faq`, `convoca_kb`) | la política del producto, igual que en el resto del buscador |

Combinación: `final = base · (1 − w) + heurístico · w`, con `w` configurable (`search_rerank_weight`,
por defecto 0,5). El heurístico es determinista y no sale a la red.

**Ninguna entrada sale ni entra del ranking por el reranker**: se reordenan las mismas 20 y el resto del
ranking queda igual detrás. Así el cambio no puede empeorar el recall@3 por perder una entrada.

### Ajuste

- `search_rerank` — **apagado por defecto** (`false`). Encendido, rerankea el top-20 con el heurístico.
- `search_rerank_weight` — 0,5 por defecto.
- `search_rerank_depth` — 20 por defecto.

Se documentan en Ajustes y en la wiki, con el resultado medido.

## 3. Criterio de aceptación del goal

nDCG@5 **+5 puntos** sobre la fase anterior (la línea base: 0,898 → ≥ 0,948) y **p95 de latencia** dentro
del límite (≤ 300 ms en el camino sin proveedor).

Sobre la latencia, precisión importante y no negociable: **la p95 de producción no se puede medir
todavía** (haría falta tráfico real). Lo que se mide aquí es el **tiempo real de cómputo del reranker** en
el arnés, que es una parte de esa p95 y se reporta como lo que es. La p95 de producción queda como
**PENDIENTE: humana/real**, no se inventa.

## 4. Lo que no se hace

- **No se implementa el backend LLM.** Ver §1.
- **No se fuerza ninguna caída de proveedor** porque no hay proveedor: no hay nada que caiga. El
  fallback limpio se demuestra de otra forma: con el reranker apagado el ranking es **exactamente** el
  compuesto, y eso lo comprueba el arnés al decimal.
- **No se enciende nada por defecto.**
- Si el criterio no se cumple, se cierra documentando el resultado y el reranker se queda apagado.

## 5. Pendiente humano (no bloquea)

| Punto | Estado |
|---|---|
| Revisión del set de 34 consultas | **PENDIENTE: humana (JC)** |
| p95 en producción | **PENDIENTE: real/tráfico** |
| Capturas de persona usando la aplicación | **PENDIENTE: humana** |

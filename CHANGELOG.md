## [0.2.19] — 2026-10-16

### Arreglado
- **La pantalla «Sin respuesta» ya no lista lo que sí tiene respuesta (assistant#12).** El registro es un
  registro: guarda lo que pasó, y lo que pasó sigue ahí aunque después se arregle. Con el paquete de arranque
  puesto, las cinco consultas del informe encuentran respuesta, pero seguían apareciendo como pendientes —y el
  aviso del panel daba un número que ya no era un problema—. Ahora lo registrado se comprueba contra la
  búsqueda actual y solo se enseña lo que **sigue** sin respuesta. Un solo criterio para la pantalla y para el
  contador de la semana.

### Notas
- Medido en la demo antes del cambio: 5 consultas sin resolver, y **las 5** encuentran respuesta hoy.
- Pruebas nuevas: una consulta que ya se contesta no aparece como pendiente, y el contador de la semana sale
  de la misma lista.


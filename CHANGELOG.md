## [0.2.18] — 2026-10-16

### Añadido
- **Paquete de arranque del asistente (assistant#12).** El asistente es una búsqueda local sobre el contenido
  del sitio: no se inventa nada, pero tampoco sabe nada que no se le haya escrito, así que recién instalado
  arranca **vacío** y las preguntas de siempre —la cuota, dónde está el centro— se quedaban sin respuesta
  (medido: 5 consultas sin resolver en su propio registro).
  Ahora trae seis preguntas frecuentes de partida (cuota y formas de pago, dónde está el centro y horarios,
  cómo hacerse socio, cómo ser voluntario, cómo apuntarse a una actividad, cómo contactar) con sus palabras
  clave, y **sinónimos de arranque** (cuota = precio = mensualidad; socio = socia = membresía…). Se instala al
  activar el plugin y, donde ya estaba instalado, con un botón en el propio aviso del panel. Es idempotente.
- **Aviso en el panel**: «hay N consultas sin respuesta esta semana», con enlace a la pantalla donde se
  resuelven. Sin esto, esa pantalla se queda olvidada —que es exactamente lo que pasaba.

### Arreglado
- **El menú del asistente ya no mezcla idiomas.** «Dashboard» y «Analytics» convivían con «Conocimiento»,
  «Sinónimos» y «Sin respuesta». Ahora son **Resumen** y **Estadísticas**.

### Notas
- Este paquete **no inventa cifras ni direcciones**: la respuesta de la cuota remite al importe vigente y a la
  página de alta, y la del centro, a la de contacto. Lo que lleva números lo escribe la junta. Hay una prueba
  que lo vigila (`test_no_inventa_importes_ni_direcciones`).
- Pruebas nuevas en `tests/test-starter-pack.php`: las seis preguntas, sus claves, que no se inventen
  importes, que instalar dos veces no duplique nada y que no se pisen los sinónimos ya puestos.


# MANUAL_USUARIO.md — Convoca Assistant v0.2.1

> Guía para administradores: asistente conversacional local sin IA para WordPress.

## 1. Introducción

Convoca Assistant es un asistente conversacional que responde preguntas sobre tu sitio WordPress usando tu propio contenido. **Sin IA, sin APIs externas, 100% local.** Tus datos nunca salen de tu servidor — compatible GDPR.

### ¿Cómo funciona?

1. **Indexa** tu contenido (páginas, entradas, FAQs, base de conocimiento) en un archivo JSON
2. **Busca** con Fuse.js (búsqueda difusa) + expansión semántica (n-gramas, sinónimos, lematización)
3. **Responde** desde el widget flotante sin enviar datos a terceros

### Capacidades

- ✅ Búsqueda difusa con Fuse.js (tolerancia a errores tipográficos)
- ✅ Expansión semántica: sinónimos, n-gramas, lematización
- ✅ Grafo de conocimiento: contenido relacionado como chips clickables
- ✅ Memoria de sesión: contexto de las últimas consultas
- ✅ 5 proveedores de contenido: Posts, Pages, FAQ, Knowledge Base, WooCommerce
- ✅ Widget flotante personalizable
- ✅ Estadísticas de búsqueda y preguntas sin responder
- ✅ API REST para integraciones externas

## 2. Instalación

1. Sube la carpeta `convoca-assistant` a `/wp-content/plugins/`
2. Activa el plugin desde el menú **Plugins**
3. Ve a **Convoca Assistant → Dashboard** y haz clic en **Regenerar índice**
4. El widget aparecerá automáticamente en la esquina inferior derecha de tu sitio

### Requisitos

- PHP 8.1+
- WordPress 6.4+
- Sin dependencias externas

## 3. Panel de administración

El panel tiene 3 pestañas:

| Pestaña | Descripción |
|---------|-------------|
| **📊 Dashboard** | Estadísticas, regenerar índice, estado de proveedores |
| **🔧 Ajustes** | Apariencia del widget (color, posición, mensajes), fuentes de contenido, sinónimos |
| **🔍 Preguntas sin respuesta** | Lista de consultas que el asistente no pudo responder |

### Shortcode

El widget flotante aparece automáticamente, pero también puedes incrustar el chat en cualquier página:

```
[convoca_assistant]
```

### Dashboard

- **Consultas totales**: búsquedas realizadas desde el widget
- **Tasa de respuesta**: porcentaje de consultas respondidas exitosamente
- **Proveedores activos**: qué fuentes de contenido están indexando
- **Entradas indexadas**: total de entradas en la base de conocimiento
- **Regenerar índice**: botón para reindexar todo el contenido

### Ajustes

| Sección | Opciones |
|---------|----------|
| **General** | Activar/desactivar widget, maintenance mode, mensaje de mantenimiento |
| **Apariencia** | Color primario, posición (izquierda/derecha), título, mensaje de bienvenida |
| **Comportamiento** | Fuentes de contenido (posts, pages, FAQs, KB), umbral de búsqueda |
| **Sinónimos** | Añadir/editar/eliminar grupos de sinónimos para mejorar resultados |

## 4. Sinónimos

Los sinónimos permiten que el asistente entienda diferentes formas de preguntar lo mismo.

### Grupos por defecto

```
contactar → contacto, escribir, llamar, email, correo, mensaje
hacerse   → hacer, hago, registrarse, apuntarse, inscribirse
socio     → socia, asociado, miembro, asociacion
cuota     → cuotas, membresía, tarifa, precio, pago, mensualidad
actividad → actividades, taller, talleres, curso, evento, excursion
funciona  → funcionar, funcionamiento, como funciona, que es, explicacion
```

Puedes añadir o modificar grupos desde **Ajustes → Sinónimos**.

## 5. API REST

El plugin expone endpoints para integraciones externas:

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/wp-json/convoca/v1/assistant/search` | POST | Buscar en la base de conocimiento |
| `/wp-json/convoca/v1/assistant/log` | POST | Registrar una consulta |
| `/wp-json/convoca/v1/assistant/stats` | GET | Obtener estadísticas |
| `/wp-json/convoca/v1/assistant/unanswered` | GET | Obtener preguntas sin respuesta |
| `/wp-json/convoca/v1/assistant/rebuild-index` | POST | Regenerar el índice (admin) |
| `/wp-json/convoca/v1/assistant/graph` | GET | Obtener el grafo de conocimiento |
| `/wp-json/convoca/v1/assistant/feedback` | POST | Enviar feedback (👍/👎) |

### Ejemplo de búsqueda

```bash
curl -X POST https://tusitio.com/wp-json/convoca/v1/assistant/search \
  -H "Content-Type: application/json" \
  -d '{"query": "cómo hacerse socio", "session_id": "abc123"}'
```

## 6. Proveedores de contenido

El plugin incluye 5 proveedores built-in:

| Proveedor | Fuente | CTI slug |
|-----------|--------|----------|
| Posts | Entradas del blog | `post` |
| Pages | Páginas estáticas | `page` |
| FAQ | FAQs personalizadas | `convoca_faq` |
| KB | Base de conocimiento | `convoca_kb` |
| WooCommerce | Productos | `product` |

Cada proveedor puede activarse/desactivarse desde **Ajustes → Fuentes de contenido**.

### Proveedores personalizados

Puedes añadir proveedores externos mediante el filtro:

```php
add_filter('convoca_assistant/providers', function ($providers) {
    $providers['eventos'] = new Mi_Proveedor_Eventos();
    return $providers;
});
```

Ver `docs/DEVELOPER.md` para la guía completa de desarrollo de proveedores.

## 7. Widget flotante

El widget aparece en la esquina inferior derecha de todas las páginas.

| Elemento | Descripción |
|----------|-------------|
| 💬 Botón flotante | Abre/cierra el chat |
| Campo de texto | Escribe tu pregunta y pulsa Enter o el botón ► |
| Chips de contenido relacionado | Preguntas sugeridas basadas en el grafo de conocimiento |
| 👍/👎 | Feedback para cada respuesta |
| 📋 | Ver historial de la sesión actual |

### Personalización CSS

Clases CSS disponibles:

- `.convoca-assistant-widget` — contenedor del widget
- `.convoca-assistant-toggle` — botón flotante
- `.convoca-assistant-chat` — ventana de chat
- `.convoca-assistant-message` — mensaje individual
- `.convoca-assistant-chip` — chip de contenido relacionado

## 8. Solución de problemas

| Problema | Causa | Solución |
|----------|-------|----------|
| "Error desconocido" en widget | Assets JS no cargados o caché | Hard refresh (Ctrl+F5). Verificar que `wp_footer()` existe en el theme |
| Widget no aparece | Theme sin `wp_footer` | El plugin intenta hooks alternativos. Verificar que el theme no bloquea scripts |
| Sin resultados en búsqueda | Índice no generado o vacío | Ir a Dashboard → Regenerar índice |
| Sinónimos no funcionan | No se guardaron o no se regeneró índice | Guardar sinónimos y regenerar índice |
| Búsqueda lenta | Muchas entradas indexadas | Ajustar el umbral de Fuse.js en Ajustes |

## 9. Compatibilidad

- ✅ Temas clásicos de WordPress
- ✅ Temas FSE (Full Site Editing)
- ✅ Page builders (Elementor, Divi, Beaver Builder)
- ✅ Plugins de caché (WP Rocket, W3 Total Cache, LiteSpeed Cache)
- ✅ GDPR: sin cookies, IPs anonimizadas, sin datos a terceros

## 10. Changelog

Ver `CHANGELOG.md` para el historial completo de versiones.

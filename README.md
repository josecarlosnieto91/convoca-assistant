# Convoca Assistant

> Asistente conversacional local sin IA para WordPress.

Convoca Assistant es un plugin de WordPress que añade un asistente virtual a tu sitio web. A diferencia de los chatbots con IA, este funciona **sin APIs externas, sin modelos LLM, sin cloud**. Utiliza búsqueda difusa (Fuzzy Search) con Fuse.js sobre tu propia base de conocimiento generada automáticamente desde tu contenido.

**Sin costes recurrentes. Sin dependencias externas. Sin datos fuera de tu servidor. 100% GDPR friendly.**

## 🚀 Características

- 🔍 **Búsqueda difusa** — Fuse.js v7.1 en el cliente, Levenshtein en el servidor
- 📦 **Sin IA** — Totalmente local, sin APIs externas, sin llamadas cloud
- ⚡ **Rápido** — Búsquedas en cliente <5ms con el índice precargado
- 🧠 **Indexación automática** — El índice JSON se genera desde tu contenido (posts, pages, FAQs, KB, WooCommerce)
- 🎨 **Widget flotante** — Interfaz conversacional moderna, responsive, modo oscuro, WCAG accesible
- 🔤 **Sinónimos inteligentes** — Diccionario de sinónimos configurable para mejorar las búsquedas
- 📊 **Analytics** — Estadísticas de consultas, tasa de resolución, preguntas sin respuesta
- 🛡️ **GDPR** — Logging anónimo, retención configurable, sin almacenamiento externo
- 🧩 **Integrable** — Shortcode `[convoca_assistant]`, CSS personalizable, hooks y filtros

## 📦 Requisitos

| Requisito | Mínimo |
|-----------|--------|
| WordPress | 6.4+ |
| PHP | 8.1+ |
| Convoca Core | Opcional (mejora logging) |

## 🔧 Instalación

1. Sube la carpeta `convoca-assistant` a `/wp-content/plugins/`
2. Activa el plugin desde el panel de administración
3. Ve a **Convoca Assistant > Ajustes** para configurar el asistente
4. El widget aparecerá automáticamente en la esquina inferior derecha

## ⚙️ Uso

### Widget flotante

El widget se muestra automáticamente en todas las páginas. Configurable desde:

**Convoca Assistant > Ajustes**:
- Posición (derecha/izquierda)
- Color primario
- Título y mensaje de bienvenida
- Auto-apertura (nunca/siempre/al hacer scroll)
- Modo mantenimiento

### Shortcode

```php
[convoca_assistant]
```

Inserta el chat en línea en cualquier entrada, página o widget.

### Bases de conocimiento

El plugin genera automáticamente un índice con:

- **Entradas** (`post`)
- **Páginas** (`page`)
- **FAQ** (`convoca_faq`) — CPT propio
- **Base de Conocimiento** (`convoca_kb`) — CPT propio
- **Productos WooCommerce** (opcional)

### Sinónimos

Gestiona sinónimos desde **Convoca Assistant > Sinónimos** para mejorar la precisión de las búsquedas.

```
ordenador → computadora, pc, equipo
cuota     → tarifa, suscripción, membresía
```

### API REST

| Método | Endpoint | Uso |
|--------|----------|-----|
| GET | `/wp-json/convoca/v1/assistant/index` | Obtener índice de conocimiento |
| POST | `/wp-json/convoca/v1/assistant/search` | Búsqueda servidor |
| POST | `/wp-json/convoca/v1/assistant/log` | Registrar interacción |
| GET | `/wp-json/convoca/v1/assistant/stats` | Estadísticas (admin) |
| GET | `/wp-json/convoca/v1/assistant/unanswered` | Consultas sin respuesta (admin) |
| POST | `/wp-json/convoca/v1/assistant/rebuild-index` | Regenerar índice (admin) |

## 🧪 Tests

```bash
# PHPCS
composer run phpcs

# PHPUnit (requiere WP test suite)
composer run phpunit

# Jest (JS tests)
cd tests && npm install && npx jest

# Todo junto
composer run test
```

## 📄 Changelog

Ver [CHANGELOG.md](CHANGELOG.md).

## 📝 Licencia

GPL-2.0-or-later — Ver [LICENSE](LICENSE).

---

Desarrollado por [José Carlos Nieto Ramos](https://getconvoca.app)

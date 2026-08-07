# Convoca Assistant 🗣️

**Asistente conversacional local sin IA para WordPress.**

Búsqueda difusa con Fuse.js sobre tu base de conocimiento. Sin APIs externas, sin cloud, compatible GDPR.

## Requisitos

- WordPress 6.4+
- PHP 8.1+
- Convoca Core (opcional — fallback a Logger local)

## Instalación

```bash
# Desde el repositorio
cd wp-content/plugins/
git clone https://github.com/josecarlosnieto91/convoca-assistant.git
cd convoca-assistant
composer install --no-dev

# Activar
wp plugin activate convoca-assistant
```

## Uso

### Widget flotante

El asistente se muestra como un botón flotante en la esquina inferior derecha. Al hacer clic, se abre el chat.

### Shortcodes

```
[convoca_assistant]                 — Chat embebido en cualquier página
```

### REST API

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/wp-json/convoca/v1/assistant/search` | POST | Búsqueda semántica |
| `/wp-json/convoca/v1/assistant/log` | POST | Registrar interacción |
| `/wp-json/convoca/v1/assistant/stats` | GET | Estadísticas |
| `/wp-json/convoca/v1/assistant/unanswered` | GET | Consultas sin respuesta |
| `/wp-json/convoca/v1/assistant/rebuild-index` | POST | Reconstruir índice |
| `/wp-json/convoca/v1/assistant/clear-logs` | POST | Limpiar logs |

**Ejemplo de búsqueda:**

```bash
curl -X POST https://tudominio.com/wp-json/convoca/v1/assistant/search \
  -H "Content-Type: application/json" \
  -d '{"query":"¿cómo hacerse socio?"}'
```

## Configuración

### Ajustes (Admin → Convoca Assistant → Ajustes)

| Opción | Descripción | Default |
|--------|-------------|---------|
| `widget_title` | Nombre del asistente en el chat | `Asistente Virtual` |
| `widget_greeting` | Mensaje de bienvenida | `¡Hola! Soy el asistente virtual...` |
| `widget_primary_color` | Color primario del widget | `#2563eb` |
| `widget_position` | Posición del widget | `bottom-right` |

### Sinónimos

Los sinónimos por defecto incluyen:

```
contactar → contacto, escribir, llamar, email, correo, mensaje
hacerse   → hacer, hago, hace, haz, registrarse, apuntarse, darse, inscribirse
socio     → socia, asociado, miembro, asociacion
cuota     → cuotas, membresía, tarifa, precio, coste, pago, mensualidad
actividad → actividades, taller, talleres, curso, evento, excursion
reservar  → reserva, inscripcion, apuntarse
funciona  → funcionar, funcionamiento, como funciona, que es, explicacion
informacion → información, info, datos, saber
```

Se gestionan desde el panel de administración.

## Arquitectura

```
convoca-assistant/
├── assets/
│   ├── css/                  # Estilos del widget y chat
│   ├── js/
│   │   ├── assistant-chat.js     # Motor de búsqueda y chat (Fuse.js + expansión)
│   │   ├── assistant-session.js  # Memoria de sesión en localStorage
│   │   ├── assistant-widget.js   # Widget flotante (UI)
│   │   ├── assistant-admin.js    # Panel de administración
│   │   └── lib/fuse.bundle.js    # Fuse.js (búsqueda difusa)
│   └── templates/            # Plantillas HTML del widget
├── includes/
│   ├── class-indexer.php         # Generación del índice JSON
│   ├── class-searcher.php        # Motor de búsqueda server-side
│   ├── class-rest-controller.php # API REST
│   ├── class-widget.php          # Widget y shortcodes
│   ├── class-admin.php           # Panel de administración
│   ├── class-settings.php        # Opciones y configuración
│   ├── class-knowledge-base.php  # CPT y taxonomías
│   ├── class-statistics.php      # Estadísticas y logging
│   ├── class-synonyms.php        # Gestión de sinónimos
│   ├── class-export-import.php   # Import/Export
│   ├── class-graph-builder.php   # Grafo de conocimiento
│   └── providers/
│       ├── class-knowledge-provider-interface.php
│       ├── class-provider-registry.php
│       ├── class-posts-provider.php
│       ├── class-pages-provider.php
│       ├── class-faq-provider.php
│       ├── class-taxonomies-provider.php
│       └── class-shortcodes-provider.php
├── convoca-assistant.php      # Plugin principal
├── CHANGELOG.md
└── README.md
```

### Providers

El sistema de providers permite extender las fuentes de conocimiento:

| Provider | Fuente | Prioridad |
|----------|--------|-----------|
| FAQ_Provider | CPT `convoca_faq` | Alta |
| Pages_Provider | Páginas publicadas | Media |
| Posts_Provider | Entradas publicadas | Media |
| Taxonomies_Provider | Taxonomy archive descriptions | Baja |
| Shortcodes_Provider | Shortcodes registrados | Baja |

Para crear un provider personalizado, implementa `Knowledge_Provider_Interface`.

### Motor de búsqueda

1. El usuario escribe una consulta.
2. Se normaliza (minúsculas, sin tildes, stop words).
3. Se expande semánticamente (sinónimos + lemas + n-gramas).
4. Se busca en el índice JSON con Fuse.js (threshold 0.4).
5. Se calcula un score compuesto: Fuse.js (80%) + graph (20%).
6. Se agrupan resultados por cluster semántico.
7. Se muestran con contexto de sesión si hay 2+ consultas en 10 min.
8. Se ofrece contenido relacionado del grafo de conocimiento.

### Saludos

El asistente detecta automáticamente saludos y responde sin buscar en la KB:

`hola`, `buenos días`, `buenas tardes`, `buenas noches`, `hey`, `hello`, `hi`, `saludos`, `qué tal`

## Licencia

GPL-2.0-or-later

## 📖 Documentación

La documentación completa (manual de usuario, API REST, hooks, instalación) vive en la wiki:

👉 **[Convoca assistant](https://docs.getconvoca.app/plugins/convoca-assistant/)**

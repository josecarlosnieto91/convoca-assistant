# CONTRIBUTING.md — Convoca Assistant

> Guía para contribuir a Convoca Assistant.

## Primeros pasos

1. Clona el repositorio: `git clone https://github.com/josecarlosnieto91/convoca-assistant.git`
2. Instala dependencias: `composer install`
3. Ejecuta los tests: `composer test`
4. Asegúrate de que todo pasa antes de hacer cambios

## Entorno de desarrollo

El plugin se puede probar en el entorno Convoca Dev:

```bash
cd ~/.openclaw/workspace/convoca-dev
podman compose up -d
```

- WordPress: `http://localhost:8080`
- Admin: `http://localhost:8080/wp-admin/`

Los plugins se montan por bind mount: los cambios en `workspace/convoca-assistant` se reflejan al instante.

## Estándares de código

- **PHP 8.1+** obligatorio
- **WordPress 6.4+** requerido
- **PSR-4** para autoloading (`Convoca\Assistant\` → `includes/`)
- Sigue el estándar de codificación de WordPress con PHPCS
- Usa namespaces totalmente cualificados o `use` imports

### Testing

```bash
# PHPCS
composer phpcs

# PHPStan (nivel 5+)
composer phpstan

# PHPUnit (tests unitarios)
composer phpunit

# Todo junto
composer test
```

### Convención de commits

```
feat: descripción breve
fix: descripción del bug corregido
refactor: qué se reorganizó
docs: qué se documentó
chore: tarea de mantenimiento
```

## Versionado

Seguimos **SemVer**:

- **MAJOR**: Cambios que rompen compatibilidad (0.2 → 1.0)
- **MINOR**: Nuevas funcionalidades compatibles (0.2 → 0.3)
- **PATCH**: Correcciones de bugs (0.2.0 → 0.2.1)

## Releases

1. Actualiza `CHANGELOG.md`
2. Actualiza la versión en `convoca-assistant.php` (header + constante)
3. Crea un tag: `git tag v0.2.1`
4. Push: `git push --tags`

## Contacto

- **Autor**: José Carlos Nieto Ramos
- **GitHub**: [josecarlosnieto91](https://github.com/josecarlosnieto91)
- **Web**: [getconvoca.app](https://getconvoca.app)

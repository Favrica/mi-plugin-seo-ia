# Guía para Desarrolladores

## Estructura
- **MiPluginSEOIA.php**: Archivo principal, maneja menús y lógica de alto nivel.
- **includes/Application/**: Lógica de negocio (SEOHandler, ContentGenerator).
- **includes/Core/**: Servicios y abstracciones (SEOService, AIClientInterface).
- **includes/Infrastructure/**: Adaptadores y clientes (AIClient, WordPressAdapter).
- **includes/templates/**: Plantillas de interfaz (admin-page.php).

## Añadir un Módulo
1. Crea una clase en `includes/Application/` (e.g., `PreviewHandler.php`).
2. Registra la funcionalidad en `MiPluginSEOIA.php`.
3. Actualiza la interfaz en `admin-page.php`.

## Depuración
- Logs en `wp-content/debug.log`.
- Activa `WP_DEBUG` en `wp-config.php`.
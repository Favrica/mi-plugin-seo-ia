# Referencia de API - Mi Plugin SEO con IA

Este documento detalla las clases y métodos principales del plugin.

## Clase: SEOHandler
Ubicación: `includes/Application/SEOHandler.php`

### Método: `__construct(SEOService $seoService, WordPressAdapter $wpAdapter)`
- **Descripción**: Inicializa el manejador SEO con dependencias.
- **Parámetros**:
  - `$seoService` (SEOService): Servicio para generar datos SEO.
  - `$wpAdapter` (WordPressAdapter): Adaptador para interactuar con WordPress.
- **Retorno**: Ninguno.

### Método: `handlePageSEO($pageId)`
- **Descripción**: Genera y aplica datos SEO a una página.
- **Parámetros**:
  - `$pageId` (int): ID de la página.
- **Retorno**: Ninguno.
- **Excepciones**: `\InvalidArgumentException` si `$pageId` no es numérico.
- **Ejemplo**:
  ```php
  $seoHandler->handlePageSEO(42);
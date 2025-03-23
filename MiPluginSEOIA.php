<?php
/*
Plugin Name: Mi Plugin SEO con IA
Description: Plugin que utiliza IA para generar palabras clave, títulos, meta descripciones y contenido relacionado.
Version: 1.1
Author: SEO Sniffer
*/

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/Core/AIClientInterface.php';
require_once __DIR__ . '/includes/Core/SEOService.php';
require_once __DIR__ . '/includes/Infrastructure/AIClient.php';
require_once __DIR__ . '/includes/Infrastructure/WordPressAdapter.php';
require_once __DIR__ . '/includes/Application/SEOHandler.php';
require_once __DIR__ . '/includes/Application/ContentGenerator.php';

function is_premium_user() {
    return get_option('mi_plugin_seo_ia_premium_key', '') === 'VALID_KEY'; // Simplificado por ahora
}

function mi_plugin_seo_ia_render_admin_page() {
    $message = '';
    $log_content = '';
    ob_start();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('mi_plugin_seo_ia_action', 'mi_plugin_seo_ia_nonce')) {
        $pageId = intval($_POST['page_id']);
        $action = sanitize_text_field($_POST['action']);
        if ($pageId > 0) {
            try {
                $api_key = get_option('mi_plugin_seo_ia_api_key', '');
                $ai_model = get_option('mi_plugin_seo_ia_model', 'qwen/qwen-vl-plus:free');
                if (empty($api_key)) {
                    throw new Exception('Por favor, configura la clave API en la página de ajustes.');
                }
                $aiClient = new \MiPluginSEOIA\Infrastructure\AIClient($api_key, $ai_model);
                $wpAdapter = new \MiPluginSEOIA\Infrastructure\WordPressAdapter();

                if ($action === 'analyze_seo') {
                    $seoService = new \MiPluginSEOIA\Core\SEOService($aiClient);
                    $seoHandler = new \MiPluginSEOIA\Application\SEOHandler($seoService, $wpAdapter);
                    $suggestions = $seoHandler->analyzePageSEO($pageId);

                    if (empty($suggestions)) {
                        $message = '<div class="notice notice-warning"><p>No se pudieron generar sugerencias SEO. Revisa el log para más detalles.</p></div>';
                    } else {
                        $preview = $seoHandler->previewSEOSuggestions($pageId, $suggestions);
                        $message = '<div class="notice notice-info"><p>Análisis SEO completado. Previsualización:</p>';
                        $message .= '<ul>';
                        $message .= '<li><strong>Título:</strong> ' . esc_html($preview['title']) . '</li>';
                        $message .= '<li><strong>Meta Descripción:</strong> ' . esc_html($preview['meta_description']) . '</li>';
                        $message .= '<li><strong>Palabras Clave:</strong> ' . esc_html(implode(', ', $preview['keywords'])) . '</li>';
                        $message .= '<li><strong>Contenido:</strong> <div style="border: 1px solid #ddd; padding: 10px; max-height: 200px; overflow-y: auto;">' . wp_kses_post($preview['content']) . '</div></li>';
                        $message .= '</ul>';
                        $message .= '<form method="post">' . wp_nonce_field('mi_plugin_seo_ia_action', 'mi_plugin_seo_ia_nonce', true, false);
                        $message .= '<input type="hidden" name="page_id" value="' . esc_attr($pageId) . '">';
                        $message .= '<button type="submit" name="action" value="apply_seo_suggestions" class="button button-primary">Aplicar Cambios</button>';
                        $message .= '<button type="submit" name="action" value="discard_preview" class="button">Descartar</button>';
                        $message .= '</form></div>';
                    }
                } elseif ($action === 'apply_seo_suggestions') {
                    $seoService = new \MiPluginSEOIA\Core\SEOService($aiClient);
                    $seoHandler = new \MiPluginSEOIA\Application\SEOHandler($seoService, $wpAdapter);
                    $suggestions = $seoHandler->analyzePageSEO($pageId);
                    $seoHandler->applySEOSuggestions($pageId, $suggestions);
                    $message = '<div class="notice notice-success"><p>Sugerencias SEO aplicadas correctamente.</p></div>';
                } elseif ($action === 'discard_preview') {
                    $message = '<div class="notice notice-success"><p>Previsualización descartada.</p></div>';
                } elseif ($action === 'generate_article') {
                    $daily_count = get_transient('mi_plugin_seo_ia_daily_count_' . get_current_user_id());
                    if (!is_premium_user() && $daily_count >= 1) {
                        $message = '<div class="notice notice-error"><p>Límite diario alcanzado. Actualiza a Premium para contenido ilimitado.</p></div>';
                    } else {
                        $content_type = sanitize_text_field($_POST['content_type']);
                        $category_id = ($content_type === 'post' && !empty($_POST['category_id'])) ? intval($_POST['category_id']) : null;
                        $contentGenerator = new \MiPluginSEOIA\Application\ContentGenerator($aiClient, $wpAdapter);
                        $new_post_id = $contentGenerator->generateRelatedArticle($pageId, $content_type, $category_id);
                        if (!is_premium_user()) {
                            set_transient('mi_plugin_seo_ia_daily_count_' . get_current_user_id(), ($daily_count ?: 0) + 1, DAY_IN_SECONDS);
                        }
                        $preview_content = get_post_field('post_content', $new_post_id);
                        $preview_keywords = get_post_meta($new_post_id, '_mi_plugin_seo_keywords', true);
                        $preview_meta = get_post_meta($new_post_id, '_mi_plugin_seo_meta_description', true);
                        $preview_title = get_post_meta($new_post_id, '_mi_plugin_seo_title', true);
                        
                        $message = '<div class="notice notice-success"><p>Previsualización generada. Revisa y confirma:</p>';
                        $message .= '<p><strong>Título:</strong> ' . esc_html($preview_title) . '</p>';
                        $message .= '<p><strong>Contenido:</strong> <div style="border: 1px solid #ddd; padding: 10px; max-height: 300px; overflow-y: auto;">' . wp_kses_post($preview_content) . '</div></p>';
                        $message .= '<p><strong>Palabras Clave:</strong> ' . esc_html(implode(', ', $preview_keywords)) . '</p>';
                        $message .= '<p><strong>Meta Descripción:</strong> ' . esc_html($preview_meta) . '</p>';
                        $message .= '<form method="post">' . wp_nonce_field('mi_plugin_seo_ia_action', 'mi_plugin_seo_ia_nonce', true, false);
                        $message .= '<input type="hidden" name="page_id" value="' . esc_attr($pageId) . '">';
                        $message .= '<input type="hidden" name="content_type" value="' . esc_attr($content_type) . '">';
                        $message .= '<input type="hidden" name="category_id" value="' . esc_attr($category_id) . '">';
                        $message .= '<button type="submit" name="action" value="confirm_article" class="button button-primary">Aceptar</button>';
                        $message .= '<button type="submit" name="action" value="discard_article" class="button" onclick="return confirm(\'¿Estás seguro de descartar este borrador?\');">Descartar</button>';
                        $message .= '</form></div>';
                        update_option('mi_plugin_seo_ia_preview_id', $new_post_id);
                    }
                } elseif ($action === 'confirm_article') {
                    $new_post_id = get_option('mi_plugin_seo_ia_preview_id');
                    $content_type = sanitize_text_field($_POST['content_type']);
                    if (!$new_post_id || !get_post($new_post_id)) {
                        throw new Exception('No se encontró el borrador para confirmar.');
                    }
                    $message = '<div class="notice notice-success"><p>' . ($content_type === 'post' ? 'Artículo' : 'Página') . ' relacionado guardado como borrador.</p></div>';
                    $edit_link = get_edit_post_link($new_post_id, '');
                    $log_entry = '[' . gmdate('d-M-Y H:i:s') . ' UTC] ' . ($content_type === 'post' ? 'Artículo' : 'Página') . ' relacionado generado: <a href="' . esc_url($edit_link) . '" target="_blank">' . get_the_title($new_post_id) . '</a>';
                    error_log($log_entry);
                    delete_option('mi_plugin_seo_ia_preview_id');
                } elseif ($action === 'discard_article') {
                    $new_post_id = get_option('mi_plugin_seo_ia_preview_id');
                    if ($new_post_id && get_post($new_post_id)) {
                        wp_delete_post($new_post_id, true);
                        $message = '<div class="notice notice-success"><p>Borrador descartado correctamente.</p></div>';
                        delete_option('mi_plugin_seo_ia_preview_id');
                    } else {
                        throw new Exception('No se encontró el borrador para descartar.');
                    }
                }
            } catch (\Exception $e) {
                $message = '<div class="notice notice-error"><p>Error: ' . esc_html($e->getMessage()) . '</p></div>';
            }
        } else {
            $message = '<div class="notice notice-error"><p>ID de página inválido.</p></div>';
        }
    }

    $log_file = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($log_file) && is_readable($log_file)) {
        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $recent_lines = array_slice($lines, -5);
        $log_content = implode('<br>', $recent_lines);
    } else {
        $log_content = 'No se encontró el archivo de log o no es legible.';
    }

    require_once __DIR__ . '/includes/templates/admin-page.php';
    ob_end_flush();
}

function mi_plugin_seo_ia_render_settings_page() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('mi_plugin_seo_ia_settings', 'mi_plugin_seo_ia_settings_nonce')) {
        $api_key = sanitize_text_field($_POST['mi_plugin_seo_ia_api_key']);
        $ai_model = sanitize_text_field($_POST['mi_plugin_seo_ia_model']);
        update_option('mi_plugin_seo_ia_api_key', $api_key);
        update_option('mi_plugin_seo_ia_model', $ai_model);
        echo '<div class="notice notice-success"><p>Ajustes guardados correctamente.</p></div>';
    }

    $api_key = get_option('mi_plugin_seo_ia_api_key', '');
    $ai_model = get_option('mi_plugin_seo_ia_model', 'qwen/qwen-vl-plus:free');
    ?>
    <div class="wrap">
        <h1>Ajustes de SEO con IA</h1>
        <form method="post">
            <?php wp_nonce_field('mi_plugin_seo_ia_settings', 'mi_plugin_seo_ia_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="mi_plugin_seo_ia_api_key">Clave API de OpenRouter</label></th>
                    <td>
                        <input type="text" name="mi_plugin_seo_ia_api_key" id="mi_plugin_seo_ia_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                        <p class="description">Ingresa tu clave API de OpenRouter.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mi_plugin_seo_ia_model">Modelo de IA</label></th>
                    <td>
                        <select name="mi_plugin_seo_ia_model" id="mi_plugin_seo_ia_model">
                            <option value="qwen/qwen-vl-plus:free" <?php selected($ai_model, 'qwen/qwen-vl-plus:free'); ?>>Qwen VL Plus (Gratis)</option>
                            <option value="openai/gpt-3.5-turbo" <?php selected($ai_model, 'openai/gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                            <option value="anthropic/claude-3-haiku" <?php selected($ai_model, 'anthropic/claude-3-haiku'); ?>>Claude 3 Haiku</option>
                        </select>
                        <p class="description">Selecciona el modelo de IA para generar el contenido.</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" class="button button-primary" value="Guardar cambios" />
            </p>
        </form>
    </div>
    <?php
}

function mi_plugin_seo_ia_render_generated_posts_page() {
    ?>
    <div class="wrap">
        <h1>Publicaciones Generadas por IA</h1>
        <?php
        $args = [
            'post_type' => ['post', 'page'],
            'post_status' => ['draft', 'publish'],
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_mi_plugin_seo_ia_generated',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ];
        $posts = new WP_Query($args);
        if ($posts->have_posts()) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Título</th><th>Tipo</th><th>Estado</th><th>Palabras Clave</th><th>Meta Descripción</th><th>Acciones</th></tr></thead><tbody>';
            while ($posts->have_posts()) {
                $posts->the_post();
                $edit_link = get_edit_post_link(get_the_ID());
                $view_link = get_permalink(get_the_ID());
                $keywords = get_post_meta(get_the_ID(), '_mi_plugin_seo_keywords', true);
                $meta_description = get_post_meta(get_the_ID(), '_mi_plugin_seo_meta_description', true);
                echo '<tr>';
                echo '<td>' . get_the_title() . '</td>';
                echo '<td>' . get_post_type() . '</td>';
                echo '<td>' . get_post_status() . '</td>';
                echo '<td>' . (is_array($keywords) ? esc_html(implode(', ', $keywords)) : 'N/A') . '</td>';
                echo '<td>' . esc_html($meta_description ?: 'N/A') . '</td>';
                echo '<td><a href="' . esc_url($edit_link) . '" target="_blank">Editar</a> | <a href="' . esc_url($view_link) . '" target="_blank">Ver</a> | <a href="' . admin_url('admin-ajax.php?action=mi_plugin_seo_ia_export&id=' . get_the_ID()) . '" target="_blank">Exportar</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            wp_reset_postdata();
        } else {
            echo '<p>No se han generado publicaciones todavía.</p>';
        }
        ?>
    </div>
    <?php
}

add_action('admin_menu', 'mi_plugin_seo_ia_register_menu');
function mi_plugin_seo_ia_register_menu() {
    add_menu_page(
        'SEO con IA',
        'SEO con IA',
        'manage_options',
        'mi-plugin-seo-ia',
        'mi_plugin_seo_ia_render_admin_page',
        plugins_url('assets/icon.png', __FILE__)
    );
    add_submenu_page(
        'mi-plugin-seo-ia',
        'Ajustes de SEO con IA',
        'Ajustes',
        'manage_options',
        'mi-plugin-seo-ia-settings',
        'mi_plugin_seo_ia_render_settings_page'
    );
    add_submenu_page(
        'mi-plugin-seo-ia',
        'Publicaciones Generadas',
        'Publicaciones Generadas',
        'manage_options',
        'mi-plugin-seo-ia-posts',
        'mi_plugin_seo_ia_render_generated_posts_page'
    );
    add_submenu_page(
        'mi-plugin-seo-ia',
        'Documentación',
        'Documentación',
        'manage_options',
        'mi-plugin-seo-ia-docs',
        function() {
            echo '<div class="wrap"><h1>Documentación</h1><p>Consulta la documentación en <a href="' . plugins_url('docs/USER_GUIDE.md', __FILE__) . '" target="_blank">Guía del Usuario</a> y <a href="' . plugins_url('docs/DEV_GUIDE.md', __FILE__) . '" target="_blank">Guía para Desarrolladores</a>.</p></div>';
        }
    );
}

add_action('admin_enqueue_scripts', 'mi_plugin_seo_ia_enqueue_scripts');
function mi_plugin_seo_ia_enqueue_scripts($hook) {
    if (in_array($hook, ['post.php', 'post-new.php'])) {
        wp_enqueue_script('mi-plugin-seo-ia-editor', plugins_url('assets/editor.js', __FILE__), ['jquery'], '1.0', true);
        wp_localize_script('mi-plugin-seo-ia-editor', 'miPluginSEOIA', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mi_plugin_seo_ia_generate_content'),
        ]);
    }
}

add_action('wp_ajax_mi_plugin_seo_ia_generate_content', 'mi_plugin_seo_ia_generate_content');
function mi_plugin_seo_ia_generate_content() {
    check_ajax_referer('mi_plugin_seo_ia_generate_content', 'nonce');
    $post_id = intval($_POST['post_id']);
    $api_key = get_option('mi_plugin_seo_ia_api_key', '');
    $ai_model = get_option('mi_plugin_seo_ia_model', 'qwen/qwen-vl-plus:free');

    if (empty($api_key)) {
        wp_send_json_error('Clave API no configurada.');
    }

    $wpAdapter = new \MiPluginSEOIA\Infrastructure\WordPressAdapter();
    $content = $wpAdapter->getPageContent($post_id);
    $aiClient = new \MiPluginSEOIA\Infrastructure\AIClient($api_key, $ai_model);

    $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
        'body' => json_encode([
            'model' => $ai_model,
            'messages' => [
                ['role' => 'user', 'content' => "Genera un párrafo de 100-150 palabras relacionado con este contenido: " . $content]
            ]
        ]),
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error('Error al generar contenido: ' . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    $generated_content = $data['choices'][0]['message']['content'] ?? '';

    wp_send_json_success($generated_content);
}

add_action('media_buttons', 'mi_plugin_seo_ia_add_media_button');
function mi_plugin_seo_ia_add_media_button() {
    echo '<button type="button" id="mi-plugin-seo-ia-generate" class="button">Generar Contenido con IA</button>';
}

add_filter('pre_set_site_transient_update_plugins', 'mi_plugin_seo_ia_check_updates');
function mi_plugin_seo_ia_check_updates($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin_slug = plugin_basename(__FILE__);
    $current_version = '1.1';
    $update_url = 'https://api.github.com/repos/tu-usuario/mi-plugin-seo-ia/releases/latest';

    $response = wp_remote_get($update_url);
    if (is_wp_error($response)) {
        return $transient;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    $new_version = $data['tag_name'] ?? $current_version;

    if (version_compare($new_version, $current_version, '>')) {
        $transient->response[$plugin_slug] = (object) [
            'slug' => 'mi-plugin-seo-ia',
            'new_version' => $new_version,
            'url' => 'https://github.com/tu-usuario/mi-plugin-seo-ia',
            'package' => $data['assets'][0]['browser_download_url'] ?? ''
        ];
    }

    return $transient;
}

add_filter('plugins_api', 'mi_plugin_seo_ia_plugin_info', 10, 3);
function mi_plugin_seo_ia_plugin_info($api, $action, $args) {
    if ($action !== 'plugin_information' || $args->slug !== 'mi-plugin-seo-ia') {
        return $api;
    }

    $response = wp_remote_get('https://api.github.com/repos/tu-usuario/mi-plugin-seo-ia/releases/latest');
    if (is_wp_error($response)) {
        return $api;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    $api = new stdClass();
    $api->name = 'Mi Plugin SEO con IA';
    $api->slug = 'mi-plugin-seo-ia';
    $api->version = $data['tag_name'] ?? '1.1';
    $api->author = 'SEO Sniffer';
    $api->download_link = $data['assets'][0]['browser_download_url'] ?? '';
    $api->sections = ['description' => $data['body'] ?? 'Plugin que utiliza IA para SEO y contenido.'];

    return $api;
}
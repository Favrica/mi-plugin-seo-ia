<?php
namespace MiPluginSEOIA\Infrastructure;

class WordPressAdapter {
    public function getPageContent($pageId) {
        $post = get_post($pageId);
        if (!$post || is_wp_error($post)) {
            error_log("WordPressAdapter::getPageContent - No se encontró el post con ID: $pageId o ocurrió un error: " . (is_wp_error($post) ? $post->get_error_message() : 'Post no encontrado'));
            return '';
        }
        $content = apply_filters('the_content', $post->post_content);
        $content = wp_strip_all_tags($content);
        return $content ?: '';
    }

    public function updatePageSEO($pageId, $title, $metaDescription, $keywords) {
        if (!is_numeric($pageId)) {
            throw new \InvalidArgumentException('El ID de la página debe ser un número.');
        }

        $title = sanitize_text_field($title);
        $metaDescription = sanitize_text_field($metaDescription);
        $keywords = array_map('sanitize_text_field', (array) $keywords);

        update_post_meta($pageId, '_mi_plugin_seo_title', $title);
        update_post_meta($pageId, '_mi_plugin_seo_meta_description', $metaDescription);
        update_post_meta($pageId, '_mi_plugin_seo_keywords', $keywords);

        if (defined('WPSEO_VERSION')) {
            update_post_meta($pageId, '_yoast_wpseo_title', $title);
            update_post_meta($pageId, '_yoast_wpseo_metadesc', $metaDescription);
            update_post_meta($pageId, '_yoast_wpseo_focuskw', !empty($keywords) ? $keywords[0] : '');
        }
    }
}
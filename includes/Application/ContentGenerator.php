<?php
namespace MiPluginSEOIA\Application;

use MiPluginSEOIA\Infrastructure\AIClient;
use MiPluginSEOIA\Infrastructure\WordPressAdapter;
use MiPluginSEOIA\Core\SEOService;

class ContentGenerator {
    private $aiClient;
    private $wpAdapter;
    private $seoService;

    public function __construct(AIClient $aiClient, WordPressAdapter $wpAdapter) {
        $this->aiClient = $aiClient;
        $this->wpAdapter = $wpAdapter;
        $this->seoService = new SEOService($aiClient);
    }

    public function generateRelatedArticle($pageId, $postType = 'post', $categoryId = null) {
        $content = $this->wpAdapter->getPageContent($pageId);
        
        $prompt = "Genera un artículo de 300-500 palabras relacionado con este contenido: " . $content . ". DEBES incluir exactamente 2 enlaces internos en el formato '[enlace:URL|título]' (e.g., [enlace:https://ejemplo.com/pagina|título]) y 1 enlace externo a una fuente confiable. No repitas frases ni añadas listas innecesarias al final.";
        
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'body' => json_encode([
                'model' => $this->aiClient->getModel(),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ]
            ]),
            'headers' => [
                'Authorization' => 'Bearer ' . $this->aiClient->getApiKey(),
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('Error al generar artículo: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $article = $data['choices'][0]['message']['content'] ?? '';

        // Generar SEO
        $keywords = $this->seoService->generateKeywords($article);
        $meta_description = $this->seoService->generateMetaDescription($article);
        $title = $this->seoService->generateTitle($article); // Usar título dinámico

        // Procesar enlaces
        $article = $this->replaceInternalLinks($article, $keywords);
        $article = $this->ensureInternalLinks($article, $keywords);
        $article = preg_replace('/<p>t[ií]tulo<\/p>/i', '', $article);

        // Crear el post/página
        $post_args = [
            'post_title' => $title, // Aplicar el título generado
            'post_content' => $article,
            'post_status' => 'draft',
            'post_type' => $postType,
            'post_author' => get_current_user_id(),
        ];

        if ($postType === 'post' && $categoryId) {
            $post_args['post_category'] = [$categoryId];
        }

        $new_post_id = wp_insert_post($post_args);

        if (is_wp_error($new_post_id)) {
            throw new \Exception('Error al crear borrador: ' . $new_post_id->get_error_message());
        }

        // Guardar datos SEO
        update_post_meta($new_post_id, '_mi_plugin_seo_ia_generated', '1');
        update_post_meta($new_post_id, '_mi_plugin_seo_keywords', $keywords);
        update_post_meta($new_post_id, '_mi_plugin_seo_meta_description', $meta_description);
        update_post_meta($new_post_id, '_mi_plugin_seo_title', $title);

        if (defined('WPSEO_VERSION')) {
            $focus_keyword = !empty($keywords) ? $keywords[0] : '';
            update_post_meta($new_post_id, '_yoast_wpseo_focuskw', $focus_keyword);
            update_post_meta($new_post_id, '_yoast_wpseo_metadesc', $meta_description);
            update_post_meta($new_post_id, '_yoast_wpseo_title', $title);
        }

        return $new_post_id;
    }

    private function replaceInternalLinks($article, $keywords) {
        preg_match_all('/\[enlace:([^\|]+)\|([^\]]+)\]/', $article, $matches, PREG_SET_ORDER);
        $used_urls = [];
        foreach ($matches as $match) {
            $url = $match[1];
            $link_title = $match[2];
            if (strpos($url, 'https://ejemplo.com') === 0) {
                $replacement = $this->findInternalLink($keywords, $used_urls);
                if ($replacement && !in_array($replacement['url'], $used_urls)) {
                    $article = str_replace($match[0], '<a href="' . esc_url($replacement['url']) . '">' . esc_html($link_title) . '</a>', $article);
                    $used_urls[] = $replacement['url'];
                } else {
                    $article = str_replace($match[0], esc_html($link_title), $article);
                }
            } else {
                $article = str_replace($match[0], '<a href="' . esc_url($url) . '">' . esc_html($link_title) . '</a>', $article);
            }
        }
        return $article;
    }

    private function ensureInternalLinks($article, $keywords) {
        preg_match_all('/<a href=["\']https?:\/\/' . preg_quote(parse_url(get_site_url(), PHP_URL_HOST)) . '[^"\']*["\'][^>]*>.*?<\/a>/i', $article, $existing_links);
        $internal_link_count = count($existing_links[0]);

        if ($internal_link_count < 2) {
            $needed_links = 2 - $internal_link_count;
            $paragraphs = preg_split('/<\/p>/', $article, -1, PREG_SPLIT_NO_EMPTY);
            $link_positions = array_rand($paragraphs, min($needed_links, count($paragraphs)));

            if (!is_array($link_positions)) {
                $link_positions = [$link_positions];
            }

            foreach ($link_positions as $pos) {
                if (!empty($paragraphs[$pos])) {
                    $replacement = $this->findInternalLink($keywords, array_column($existing_links[0], 'url'));
                    if ($replacement) {
                        $keyword_to_link = $keywords[array_rand($keywords)];
                        $paragraphs[$pos] .= ' <a href="' . esc_url($replacement['url']) . '">' . esc_html($keyword_to_link) . '</a>';
                    }
                }
            }
            $article = implode('</p>', $paragraphs);
        }
        return $article;
    }

    private function findInternalLink($keywords, $exclude_urls = []) {
        $args = [
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => 1,
            's' => implode(' ', $keywords),
            'exclude' => array_map(function($url) {
                return url_to_postid($url);
            }, $exclude_urls),
        ];
        $query = new \WP_Query($args);
        if ($query->have_posts()) {
            $query->the_post();
            $link = [
                'url' => get_permalink(),
                'title' => get_the_title(),
            ];
            wp_reset_postdata();
            return $link;
        }
        return null;
    }
}
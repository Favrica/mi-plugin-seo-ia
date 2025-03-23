<?php
namespace MiPluginSEOIA\Application;

use MiPluginSEOIA\Core\SEOService;
use MiPluginSEOIA\Infrastructure\WordPressAdapter;

class SEOHandler {
    private $seoService;
    private $wpAdapter;

    public function __construct(SEOService $seoService, WordPressAdapter $wpAdapter) {
        $this->seoService = $seoService;
        $this->wpAdapter = $wpAdapter;
    }

    public function handlePageSEO($pageId) {
        if (!is_numeric($pageId)) {
            throw new \InvalidArgumentException('El ID de la página debe ser un número.');
        }

        $content = $this->wpAdapter->getPageContent($pageId);
        if (empty($content) || !is_string($content)) {
            error_log("SEOHandler::handlePageSEO - Contenido inválido para la página con ID: $pageId - " . var_export($content, true));
            return;
        }

        $keywords = $this->seoService->generateKeywords($content);
        $metaDescription = $this->seoService->generateMetaDescription($content);
        $title = $this->seoService->generateTitle($content);

        $this->wpAdapter->updatePageSEO($pageId, $title, $metaDescription, $keywords);
    }

    public function analyzePageSEO($pageId) {
        $content = $this->wpAdapter->getPageContent($pageId);
        if (empty($content) || !is_string($content)) {
            error_log("SEOHandler::analyzePageSEO - Contenido inválido para la página con ID: $pageId - " . var_export($content, true));
            return ['No se encontró contenido válido para analizar.'];
        }

        $current_title = get_the_title($pageId);
        $current_meta_desc = get_post_meta($pageId, '_yoast_wpseo_metadesc', true) ?: get_post_meta($pageId, '_mi_plugin_seo_meta_description', true);
        $current_keywords = get_post_meta($pageId, '_mi_plugin_seo_keywords', true) ?: [];

        $suggestions = [];

        if (strlen($current_title) > 60) {
            $suggested_title = $this->seoService->generateTitle($content);
            $suggestions[] = "El título supera los 60 caracteres. Sugerencia: Redúcelo a '$suggested_title'.";
        } elseif (empty($current_title)) {
            $suggested_title = $this->seoService->generateTitle($content);
            $suggestions[] = "No hay título definido. Sugerencia: Usa '$suggested_title'.";
        }

        if (strlen($current_meta_desc) > 160) {
            $suggested_meta = $this->seoService->generateMetaDescription($content);
            $suggestions[] = "La meta descripción supera los 160 caracteres. Sugerencia: Redúcelo a '$suggested_meta'.";
        } elseif (empty($current_meta_desc)) {
            $suggested_meta = $this->seoService->generateMetaDescription($content);
            $suggestions[] = "No hay meta descripción. Sugerencia: Usa '$suggested_meta'.";
        }

        if (empty($current_keywords) || count($current_keywords) < 3) {
            $suggested_keywords = $this->seoService->generateKeywords($content);
            $suggestions[] = "Faltan palabras clave o son insuficientes. Sugerencia: Usa " . implode(', ', $suggested_keywords) . ".";
        }

        if (substr_count($content, '<a href=') < 1) {
            $suggestions[] = "El contenido no tiene enlaces internos. Añade al menos 1-2 enlaces a páginas relacionadas.";
        }

        $word_count = str_word_count(strip_tags($content));
        if ($word_count < 300) {
            $suggestions[] = "El contenido tiene menos de 300 palabras ($word_count). Considera ampliarlo para mejorar el SEO.";
        }

        return $suggestions ?: ['El SEO parece estar bien optimizado.'];
    }

    public function applySEOSuggestions($pageId, $suggestions) {
        $content = $this->wpAdapter->getPageContent($pageId);
        if (empty($content) || !is_string($content)) {
            error_log("SEOHandler::applySEOSuggestions - Contenido inválido para la página con ID: $pageId - " . var_export($content, true));
            return;
        }

        $new_title = get_the_title($pageId);
        $new_meta_desc = get_post_meta($pageId, '_mi_plugin_seo_meta_description', true);
        $new_keywords = get_post_meta($pageId, '_mi_plugin_seo_keywords', true) ?: [];
        $updated_content = $content;

        foreach ($suggestions as $suggestion) {
            if (strpos($suggestion, "El título supera los 60 caracteres") === 0 || strpos($suggestion, "No hay título definido") === 0) {
                preg_match("/Sugerencia: Usa '(.+?)'/", $suggestion, $matches);
                if (!empty($matches[1])) {
                    $new_title = $matches[1];
                }
            }

            if (strpos($suggestion, "La meta descripción supera los 160 caracteres") === 0 || strpos($suggestion, "No hay meta descripción") === 0) {
                preg_match("/Sugerencia: Usa '(.+?)'/", $suggestion, $matches);
                if (!empty($matches[1])) {
                    $new_meta_desc = $matches[1];
                }
            }

            if (strpos($suggestion, "Faltan palabras clave o son insuficientes") === 0) {
                preg_match("/Sugerencia: Usa (.+?)\./", $suggestion, $matches);
                if (!empty($matches[1])) {
                    $new_keywords = explode(', ', $matches[1]);
                }
            }

            if (strpos($suggestion, "El contenido no tiene enlaces internos") === 0) {
                $keywords = $this->seoService->generateKeywords($content);
                $links_added = 0;
                $paragraphs = preg_split('/<\/p>/', $content, -1, PREG_SPLIT_NO_EMPTY);
                $link_positions = array_rand($paragraphs, min(2, count($paragraphs)));
                if (!is_array($link_positions)) $link_positions = [$link_positions];

                foreach ($link_positions as $pos) {
                    if ($links_added < 2 && !empty($paragraphs[$pos])) {
                        $replacement = $this->findInternalLink($keywords);
                        if ($replacement) {
                            $keyword_to_link = $keywords[array_rand($keywords)];
                            $paragraphs[$pos] .= ' <a href="' . esc_url($replacement['url']) . '">' . esc_html($keyword_to_link) . '</a>';
                            $links_added++;
                        }
                    }
                }
                $updated_content = implode('</p>', $paragraphs);
            }
        }

        wp_update_post([
            'ID' => $pageId,
            'post_title' => $new_title,
            'post_content' => $updated_content,
        ]);
        $this->wpAdapter->updatePageSEO($pageId, $new_title, $new_meta_desc, $new_keywords);
    }

    private function findInternalLink($keywords) {
        $args = [
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => 1,
            's' => implode(' ', $keywords),
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
	
	public function previewSEOSuggestions($pageId, $suggestions) {
		$content = $this->wpAdapter->getPageContent($pageId);
		if (empty($content) || !is_string($content)) {
			error_log("SEOHandler::previewSEOSuggestions - Contenido inválido para la página con ID: $pageId - " . var_export($content, true));
			return null;
		}

		$new_title = get_the_title($pageId);
		$new_meta_desc = get_post_meta($pageId, '_mi_plugin_seo_meta_description', true);
		$new_keywords = get_post_meta($pageId, '_mi_plugin_seo_keywords', true) ?: [];
		$updated_content = $content;

		foreach ($suggestions as $suggestion) {
			if (strpos($suggestion, "El título supera los 60 caracteres") === 0 || strpos($suggestion, "No hay título definido") === 0) {
				preg_match("/Sugerencia: Usa '(.+?)'/", $suggestion, $matches);
				if (!empty($matches[1])) {
					$new_title = $matches[1];
				}
			}

			if (strpos($suggestion, "La meta descripción supera los 160 caracteres") === 0 || strpos($suggestion, "No hay meta descripción") === 0) {
				preg_match("/Sugerencia: Usa '(.+?)'/", $suggestion, $matches);
				if (!empty($matches[1])) {
					$new_meta_desc = $matches[1];
				}
			}

			if (strpos($suggestion, "Faltan palabras clave o son insuficientes") === 0) {
				preg_match("/Sugerencia: Usa (.+?)\./", $suggestion, $matches);
				if (!empty($matches[1])) {
					$new_keywords = explode(', ', $matches[1]);
				}
			}

			if (strpos($suggestion, "El contenido no tiene enlaces internos") === 0) {
				$keywords = $this->seoService->generateKeywords($content);
				$links_added = 0;
				$paragraphs = preg_split('/<\/p>/', $content, -1, PREG_SPLIT_NO_EMPTY);
				$link_positions = array_rand($paragraphs, min(2, count($paragraphs)));
				if (!is_array($link_positions)) $link_positions = [$link_positions];

				foreach ($link_positions as $pos) {
					if ($links_added < 2 && !empty($paragraphs[$pos])) {
						$replacement = $this->findInternalLink($keywords);
						if ($replacement) {
							$keyword_to_link = $keywords[array_rand($keywords)];
							$paragraphs[$pos] .= ' <a href="' . esc_url($replacement['url']) . '">' . esc_html($keyword_to_link) . '</a>';
							$links_added++;
						}
					}
				}
				$updated_content = implode('</p>', $paragraphs);
			}
		}

		return [
			'title' => $new_title,
			'meta_description' => $new_meta_desc,
			'keywords' => $new_keywords,
			'content' => $updated_content
		];
	}	
}
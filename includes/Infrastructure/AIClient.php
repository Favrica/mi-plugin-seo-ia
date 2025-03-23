<?php
namespace MiPluginSEOIA\Infrastructure;

class AIClient {
    private $apiKey;
    private $model;

    public function __construct($apiKey, $model) {
        $this->apiKey = $apiKey ?: ''; // Asegurar que sea string
        $this->model = $model ?: 'qwen/qwen-vl-plus:free'; // Modelo por defecto
    }

    public function getApiKey() {
        return $this->apiKey;
    }

    public function getModel() {
        return $this->model;
    }

    public function getKeywords($content) {
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'body' => json_encode([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => "Genera una lista de 5 a 10 palabras clave relevantes para SEO basadas en este contenido, evitando shortcodes o texto de relleno como 'Lorem ipsum': " . $content]
                ]
            ]),
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            error_log('Error al conectar con la API: ' . $response->get_error_message());
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $keywords_string = $data['choices'][0]['message']['content'] ?? '';
        $keywords = array_filter(preg_split('/\r\n|\n|\r/', $keywords_string));
        $keywords = array_map(function($keyword) {
            return preg_replace('/^\d+\.\s*/', '', trim($keyword));
        }, $keywords);

        return array_slice($keywords, 0, 10);
    }

    public function getTitle($content) {
        return 'Título sugerido por la IA';
    }

    public function getMetaDescription($content) {
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'body' => json_encode([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => "Genera una meta descripción de 120-160 caracteres basada en este contenido, optimizada para SEO y evitando shortcodes o texto de relleno: " . $content]
                ]
            ]),
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            error_log('Error al generar meta descripción: ' . $response->get_error_message());
            return 'Meta descripción no disponible debido a un error en la API.';
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $meta_description = $data['choices'][0]['message']['content'] ?? 'Meta descripción no disponible';

        $meta_description = trim($meta_description);
        if (strlen($meta_description) > 160) {
            $meta_description = substr($meta_description, 0, 157) . '...';
        } elseif (strlen($meta_description) < 120) {
            $meta_description .= ' ' . str_repeat(' ', 120 - strlen($meta_description));
        }

        return $meta_description;
    }
}
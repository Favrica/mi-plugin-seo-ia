<?php
namespace MiPluginSEOIA\Core;

use MiPluginSEOIA\Infrastructure\AIClient;

class SEOService {
    private $aiClient;

    public function __construct(AIClient $aiClient) {
        $this->aiClient = $aiClient;
    }

    public function generateKeywords($content) {
        if (empty($content) || !is_string($content)) {
            error_log('SEOService::generateKeywords - Contenido inválido: ' . var_export($content, true));
            return ['palabra_clave_default'];
        }

        $body = [
            'model' => $this->aiClient->getModel(),
            'messages' => [
                ['role' => 'user', 'content' => "Genera 3-5 palabras clave SEO basadas en este contenido: " . substr($content, 0, 1000)] // Aumentado a 1000
            ]
        ];
        $body_json = json_encode($body);
        if ($body_json === false) {
            error_log('SEOService::generateKeywords - Fallo en json_encode: ' . json_last_error_msg() . ' - Datos: ' . var_export($body, true));
            return ['palabra_clave_default'];
        }

        error_log('SEOService::generateKeywords - Enviando solicitud con body: ' . $body_json);

        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'body' => $body_json,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->aiClient->getApiKey(),
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            error_log('SEOService::generateKeywords - Error en wp_remote_post: ' . $response->get_error_message());
            return ['palabra_clave_default'];
        }

        $body_response = wp_remote_retrieve_body($response);
        error_log('SEOService::generateKeywords - Respuesta recibida: ' . $body_response); // Añadido para depurar

        $data = json_decode($body_response, true);
        return explode(', ', $data['choices'][0]['message']['content'] ?? 'palabra_clave_default');
    }

    public function generateMetaDescription($content) {
        if (empty($content) || !is_string($content)) {
            error_log('SEOService::generateMetaDescription - Contenido inválido: ' . var_export($content, true));
            return 'Descripción SEO por defecto.';
        }

        $body = [
            'model' => $this->aiClient->getModel(),
            'messages' => [
                ['role' => 'user', 'content' => "Genera una meta descripción SEO (máximo 160 caracteres) basada en este contenido: " . substr($content, 0, 500)]
            ]
        ];
        $body_json = json_encode($body);
        if ($body_json === false) {
            error_log('SEOService::generateMetaDescription - Fallo en json_encode: ' . json_last_error_msg() . ' - Datos: ' . var_export($body, true));
            return 'Descripción SEO por defecto.';
        }

        error_log('SEOService::generateMetaDescription - Enviando solicitud con body: ' . $body_json);

        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'body' => $body_json,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->aiClient->getApiKey(),
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            error_log('SEOService::generateMetaDescription - Error en wp_remote_post: ' . $response->get_error_message());
            return 'Descripción SEO por defecto.';
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        return $data['choices'][0]['message']['content'] ?? 'Descripción SEO por defecto.';
    }

    public function generateTitle($content) {
        if (empty($content) || !is_string($content)) {
            error_log('SEOService::generateTitle - Contenido inválido: ' . var_export($content, true));
            return 'Título SEO por defecto';
        }

        $body = [
            'model' => $this->aiClient->getModel(),
            'messages' => [
                ['role' => 'user', 'content' => "Genera un título SEO optimizado (máximo 60 caracteres) basado en este contenido: " . substr($content, 0, 500)]
            ]
        ];
        $body_json = json_encode($body);
        if ($body_json === false) {
            error_log('SEOService::generateTitle - Fallo en json_encode: ' . json_last_error_msg() . ' - Datos: ' . var_export($body, true));
            return 'Título SEO por defecto';
        }

        error_log('SEOService::generateTitle - Enviando solicitud con body: ' . $body_json);

        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', [
            'body' => $body_json,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->aiClient->getApiKey(),
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            error_log('SEOService::generateTitle - Error en wp_remote_post: ' . $response->get_error_message());
            return 'Título SEO por defecto';
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        return $data['choices'][0]['message']['content'] ?? 'Título SEO por defecto';
    }
}
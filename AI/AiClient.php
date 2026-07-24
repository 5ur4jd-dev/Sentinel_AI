<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Db/Database.php';

class AiClient
{
    private string $provider;
    private array $config;
    private PDO $db;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config.php';
        $this->provider = $this->config['AI_PROVIDER'] ?? 'openrouter';
        $this->db = Database::getConnection();
    }

    public function generateStructuredJson(string $leadId, string $systemPrompt, string $userPrompt, array $instructions, int $maxRetries = 1): ?array
    {
        $endpoint = '/chat/completions';
        $prompt = $this->buildPrompt($systemPrompt, $userPrompt, $instructions);
        $responseBody = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $result = $this->callProvider($endpoint, $prompt);
            $this->logRequest($leadId, $endpoint, $prompt, $result['body'] ?? null, $result['success'], $result['error'] ?? null);

            if (!$result['success']) {
                if ($attempt === $maxRetries) {
                    return null;
                }
                continue;
            }

            $responseBody = $result['body'];
            $parsed = $this->parseJson($responseBody);
            if ($parsed !== null) {
                return $parsed;
            }

            if ($attempt === $maxRetries) {
                return null;
            }
        }

        return null;
    }

    private function buildPrompt(string $systemPrompt, string $userPrompt, array $instructions): array
    {
        return [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt . "\n\n" . implode("\n", $instructions)],
            ],
            'temperature' => 0.3,
            'max_tokens' => 1200,
        ];
    }

    private function parseJson(string $text): ?array
    {
        $json = trim($text);
        if (str_starts_with($json, '```')) {
            $json = preg_replace('/^```(?:json)?\s*/', '', $json);
            $json = preg_replace('/```$/', '', $json);
        }

        $decoded = json_decode($json, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private function callProvider(string $endpoint, array $payload): array
    {
        if ($this->provider === 'nvidia') {
            return $this->callNvidia($endpoint, $payload);
        }

        return $this->callOpenRouter($endpoint, $payload);
    }

    private function callOpenRouter(string $endpoint, array $payload): array
    {
        $url = 'https://openrouter.ai/api/v1' . $endpoint;
        $apiKey = $this->config['OPENROUTER_API_KEY'];
        if (($payload['model'] ?? '') === 'gpt-4o-mini') {
            $payload['model'] = 'openai/gpt-4o-mini';
        }
        return $this->curlPost($url, $payload, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ]);
    }

    private function callNvidia(string $endpoint, array $payload): array
    {
        $url = 'https://api.build.nvidia.com/v1' . $endpoint;
        $apiKey = $this->config['NVIDIA_API_KEY'];
        if (($payload['model'] ?? '') === 'gpt-4o-mini') {
            $payload['model'] = 'meta/llama-3.1-70b-instruct';
        }
        return $this->curlPost($url, $payload, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ]);
    }

    private function curlPost(string $url, array $payload, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($body === false || $status >= 400) {
            return [
                'success' => false,
                'body' => $body,
                'error' => $error ?: 'HTTP ' . $status,
            ];
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'body' => $body,
                'error' => 'Invalid JSON from provider',
            ];
        }

        $content = $decoded['choices'][0]['message']['content'] ?? null;
        $errorMsg = $decoded['error']['message'] ?? $decoded['error'] ?? null;
        if (is_array($errorMsg)) {
            $errorMsg = json_encode($errorMsg, JSON_UNESCAPED_UNICODE);
        }
        return [
            'success' => $content !== null,
            'body' => $content,
            'error' => $content !== null ? null : ($errorMsg ?: 'Missing content in provider response'),
        ];
    }

    private function logRequest(string $leadId, string $endpoint, array $prompt, ?string $response, bool $success, ?string $errorMessage): void
    {
        $sql = 'INSERT INTO ai_logs (lead_id, provider, endpoint, prompt, response, success, error_message) VALUES (:lead_id, :provider, :endpoint, :prompt, :response, :success, :error_message)';
        $statement = $this->db->prepare($sql);
        $statement->execute([
            ':lead_id' => $leadId,
            ':provider' => $this->provider,
            ':endpoint' => $endpoint,
            ':prompt' => json_encode($prompt, JSON_UNESCAPED_UNICODE),
            ':response' => $response,
            ':success' => $success ? 1 : 0,
            ':error_message' => $errorMessage,
        ]);
    }
}

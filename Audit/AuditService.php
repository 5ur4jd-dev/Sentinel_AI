<?php

require_once __DIR__ . '/../AI/AiClient.php';
require_once __DIR__ . '/../Db/Database.php';

class AuditService
{
    private PDO $db;
    private AiClient $aiClient;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->aiClient = new AiClient();
    }

    public function fetchWebsiteSnapshot(string $url): array
    {
        $snapshot = [
            'status' => 'error',
            'headers' => [],
            'html' => '',
            'response_time_ms' => null,
            'error' => null,
        ];

        $start = microtime(true);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);

        $snapshot['response_time_ms'] = (int)((microtime(true) - $start) * 1000);

        if ($response === false || $info['http_code'] >= 400) {
            $snapshot['error'] = $error ?: 'HTTP ' . $info['http_code'];
            return $snapshot;
        }

        $headerSize = $info['header_size'];
        $snapshot['headers'] = $this->parseHeaders(substr($response, 0, $headerSize));
        $snapshot['html'] = substr($response, $headerSize);
        $snapshot['status'] = 'ok';

        return $snapshot;
    }

    public function analyzeLead(int $leadId, string $websiteUrl): array
    {
        $snapshot = $this->fetchWebsiteSnapshot($websiteUrl);
        $audit = [
            'findings' => [],
            'error' => null,
        ];

        if ($snapshot['status'] !== 'ok') {
            return ['success' => false, 'snapshot' => $snapshot, 'audit' => null, 'error' => $snapshot['error']];
        }

        $parsed = $this->parseHtml($snapshot['html']);
        $leadText = $this->extractText($parsed);

        $systemPrompt = 'You are an AI auditor for website quality. Provide evidence-based findings. Do not generate generic statements. Output only valid JSON in the requested format.';
        $userPrompt = 'Analyze the website HTML and metadata, then produce audit findings as structured JSON objects with category, observation, evidence, and impact. Use the supplied fields to identify issues in performance, design, responsiveness, accessibility, SEO, conversion, trust, CTA quality, navigation, UX structure, content quality, and technical issues.';
        $instructions = [
            'Return JSON with top-level keys: findings (array), business_summary (string).',
            'Each finding must contain category, observation, evidence, impact.',
            'Use visible evidence from HTML content, metadata, headers, and site structure.',
            'Avoid vague phrases like "needs improvement" without specifics.',
            'If unable to parse a signal, state that explicitly in evidence.',
        ];

        $rawAudit = $this->aiClient->generateStructuredJson((string)$leadId, $systemPrompt, $userPrompt . "\nWebsite HTML:\n" . $snapshot['html'] . "\nExtracted text:\n" . $leadText, $instructions);

        if ($rawAudit === null) {
            return ['success' => false, 'snapshot' => $snapshot, 'audit' => null, 'error' => 'AI audit failed or returned malformed JSON'];
        }

        $audit['findings'] = $rawAudit['findings'] ?? [];
        $summary = $rawAudit['business_summary'] ?? '';

        $this->saveAudit($leadId, $snapshot['html'], $audit['findings'], $summary);
        $this->saveBusinessSummary($leadId, $summary);

        return ['success' => true, 'snapshot' => $snapshot, 'audit' => $audit, 'summary' => $summary];
    }

    private function saveAudit(int $leadId, string $html, array $findings, string $summary): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $sql = 'INSERT INTO audits (lead_id, website_snapshot, audit_json, summary, status) VALUES (:lead_id, :website_snapshot, :audit_json, :summary, :status) ON CONFLICT(lead_id) DO UPDATE SET website_snapshot = :website_snapshot_u, audit_json = :audit_json_u, summary = :summary_u, status = :status_u, updated_at = CURRENT_TIMESTAMP';
        } else {
            $sql = 'INSERT INTO audits (lead_id, website_snapshot, audit_json, summary, status) VALUES (:lead_id, :website_snapshot, :audit_json, :summary, :status) ON DUPLICATE KEY UPDATE website_snapshot = :website_snapshot_u, audit_json = :audit_json_u, summary = :summary_u, status = :status_u, updated_at = CURRENT_TIMESTAMP';
        }
        $statement = $this->db->prepare($sql);
        $statement->execute([
            ':lead_id' => $leadId,
            ':website_snapshot' => $html,
            ':audit_json' => json_encode(['findings' => $findings], JSON_UNESCAPED_UNICODE),
            ':summary' => $summary,
            ':status' => 'Complete',
            ':website_snapshot_u' => $html,
            ':audit_json_u' => json_encode(['findings' => $findings], JSON_UNESCAPED_UNICODE),
            ':summary_u' => $summary,
            ':status_u' => 'Complete',
        ]);
    }

    private function saveBusinessSummary(int $leadId, string $summary): void
    {
        // Business summary stored inside audits table summary field
    }

    public function getAuditByLeadId(int $leadId): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM audits WHERE lead_id = :lead_id');
        $statement->execute([':lead_id' => $leadId]);
        $audit = $statement->fetch();
        if (!$audit) {
            return null;
        }

        $audit['audit_json'] = json_decode($audit['audit_json'], true);
        return $audit;
    }

    private function parseHeaders(string $headerText): array
    {
        $lines = preg_split('/\r?\n/', trim($headerText));
        $headers = [];
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$name, $value] = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }
        return $headers;
    }

    private function parseHtml(string $html): DOMDocument
    {
        libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $document->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        return $document;
    }

    private function extractText(DOMDocument $document): string
    {
        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//body//*[not(self::script) and not(self::style)]');
        $text = [];

        foreach ($nodes as $node) {
            $content = trim($node->textContent);
            if ($content !== '') {
                $text[] = preg_replace('/\s+/', ' ', $content);
            }
        }

        return implode("\n", $text);
    }
}

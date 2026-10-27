<?php

require_once __DIR__ . '/AiClient.php';
require_once __DIR__ . '/../Leads/LeadModel.php';
require_once __DIR__ . '/../Audit/AuditService.php';

class DiscoveryEngine
{
    private AiClient $aiClient;
    private LeadModel $leadModel;
    private AuditService $auditService;

    public function __construct()
    {
        $this->aiClient = new AiClient();
        $this->leadModel = new LeadModel();
        $this->auditService = new AuditService();
    }

    /**
     * Discover businesses based on industry and location keywords.
     */
    public function discover(string $industry, string $location, int $limit = 5): array
    {
        $systemPrompt = "You are a web-grounded business researcher. Use the web search tool before answering. Return only real businesses found in current search results. Never invent a business, website, person, email, phone number, address, or review. If search results do not provide enough evidence, return an empty businesses array.";
        $userPrompt = "Find up to $limit real businesses matching Category: \"$industry\", Location: \"$location\". Search for official business websites and identify businesses that actually operate in that location. Do not use directories as the business website unless no official website exists.";
        $instructions = [
            "Return JSON with a top-level key 'businesses' containing an array of objects.",
            "Each business must contain: business_name, website_url, source_url, contact_name, contact_email, contact_phone, industry, location, notes.",
            "source_url must be the exact web-search result URL supporting this business. website_url must be the official website URL, not a guessed URL.",
            "Only include contact details when they appear in the web results or official website. Otherwise use empty strings.",
            "For notes, summarize facts supported by the source. If evidence is insufficient, omit the business."
        ];

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'businesses' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                    'business_name' => ['type' => 'string'], 'website_url' => ['type' => 'string'], 'source_url' => ['type' => 'string'],
                    'contact_name' => ['type' => 'string'], 'contact_email' => ['type' => 'string'], 'contact_phone' => ['type' => 'string'],
                    'industry' => ['type' => 'string'], 'location' => ['type' => 'string'], 'notes' => ['type' => 'string'],
                ], 'required' => ['business_name', 'website_url', 'source_url', 'contact_name', 'contact_email', 'contact_phone', 'industry', 'location', 'notes']]],
            ],
            'required' => ['businesses'],
        ];

        // Try using AI to locate real targets
        // Web-grounded discovery is expensive on free endpoints. Do not repeat
        // a search automatically; an empty result is safer than duplicate or
        // stale lead data after a timeout.
        $raw = $this->aiClient->generateStructuredJson("0", $systemPrompt, $userPrompt, $instructions, 0, true, $schema);

        if ($raw === null || empty($raw['businesses'])) {
            return [];
        }

        $discovered = [];
        foreach (array_slice($raw['businesses'], 0, max(1, min($limit, 25))) as $biz) {
            if (empty($biz['business_name']) || empty($biz['website_url'])) {
                continue;
            }

            // LLM output is only a candidate. Do not persist it until the URL
            // resolves to a real public website.
            $websiteUrl = trim((string)$biz['website_url']);
            $sourceUrl = trim((string)($biz['source_url'] ?? ''));
            if (!filter_var($websiteUrl, FILTER_VALIDATE_URL) || !filter_var($sourceUrl, FILTER_VALIDATE_URL) || $this->isPlaceholderDomain($websiteUrl)) {
                continue;
            }
            $snapshot = $this->auditService->fetchWebsiteSnapshot($websiteUrl);
            if ($snapshot['status'] !== 'ok' || trim($snapshot['html']) === '') {
                continue;
            }
            if ($this->leadModel->getByWebsite($websiteUrl)) {
                continue;
            }

            // Create lead record
            $leadId = $this->leadModel->create([
                'business_name' => $biz['business_name'],
                'website_url' => $websiteUrl,
                'contact_name' => $biz['contact_name'] ?? '',
                'contact_email' => $biz['contact_email'] ?? '',
                'contact_phone' => $biz['contact_phone'] ?? '',
                'notes' => $biz['notes'] ?? '',
                'source' => 'AI discovery: ' . $sourceUrl,
                'status' => 'New',
                'industry' => $biz['industry'] ?? $industry,
                'location' => $biz['location'] ?? $location,
                'score' => 0,
                'qualification_details' => null,
                'handover_status' => 'None',
                'handover_summary' => null,
            ]);

            $biz['id'] = $leadId;
            $discovered[] = $biz;
        }

        return $discovered;
    }

    private function isPlaceholderDomain(string $url): bool
    {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        return $host === '' || $host === 'localhost' || str_ends_with($host, '.test') || str_ends_with($host, '.example') || in_array($host, ['example.com', 'example.org', 'example.net'], true);
    }

}

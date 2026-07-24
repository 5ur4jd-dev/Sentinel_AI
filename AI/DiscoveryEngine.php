<?php

require_once __DIR__ . '/AiClient.php';
require_once __DIR__ . '/../Leads/LeadModel.php';

class DiscoveryEngine
{
    private AiClient $aiClient;
    private LeadModel $leadModel;

    public function __construct()
    {
        $this->aiClient = new AiClient();
        $this->leadModel = new LeadModel();
    }

    /**
     * Discover businesses based on industry and location keywords.
     */
    public function discover(string $industry, string $location, int $limit = 5): array
    {
        $systemPrompt = "You are an autonomous business intelligence crawler. Your goal is to find REAL, existing businesses that match the user targeting preferences. Do NOT generate fictional names, fake contact info, or placeholder URLs. Return ONLY verified organizations that exist on the public web. Output ONLY valid structured JSON.";
        $userPrompt = "Find $limit real, existing businesses matching: Category: \"$industry\", Location: \"$location\".";
        $instructions = [
            "Return JSON with a top-level key 'businesses' containing an array of objects.",
            "Each business must contain: business_name, website_url, contact_name, contact_email, contact_phone, industry, location, notes.",
            "Under no circumstances generate mock or fictional business data. If you cannot find real, verified businesses, return an empty array.",
            "For notes, include a brief description of the actual business services."
        ];

        // Try using AI to locate real targets
        $raw = $this->aiClient->generateStructuredJson("0", $systemPrompt, $userPrompt, $instructions);

        if ($raw === null || empty($raw['businesses'])) {
            return [];
        }

        $discovered = [];
        foreach ($raw['businesses'] as $biz) {
            if (empty($biz['business_name']) || empty($biz['website_url'])) {
                continue;
            }

            // Create lead record
            $leadId = $this->leadModel->create([
                'business_name' => $biz['business_name'],
                'website_url' => $biz['website_url'],
                'contact_name' => $biz['contact_name'] ?? '',
                'contact_email' => $biz['contact_email'] ?? '',
                'contact_phone' => $biz['contact_phone'] ?? '',
                'notes' => $biz['notes'] ?? '',
                'source' => 'Discovery Engine (' . htmlspecialchars($industry) . ')',
                'status' => 'New',
                'industry' => $biz['industry'] ?: $industry,
                'location' => $biz['location'] ?: $location,
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

}

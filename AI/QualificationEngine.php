<?php

require_once __DIR__ . '/AiClient.php';
require_once __DIR__ . '/../Leads/LeadModel.php';
require_once __DIR__ . '/../Audit/AuditService.php';

class QualificationEngine
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
     * Research website, run audit, and score lead qualification.
     */
    public function qualify(int $leadId): array
    {
        $lead = $this->leadModel->getById($leadId);
        if (!$lead) {
            return ['success' => false, 'error' => 'Lead not found'];
        }

        // 1. Get Audit findings (uses existing AuditService)
        $auditResult = $this->auditService->getAuditByLeadId($leadId);
        if (!$auditResult) {
            $this->leadModel->updateStatus($leadId, 'Analyzing');
            $analysis = $this->auditService->analyzeLead($leadId, $lead['website_url']);
            if (!$analysis['success']) {
                $this->leadModel->updateStatus($leadId, 'Archived');
                return ['success' => false, 'error' => 'Website audit failed: ' . ($analysis['error'] ?? 'unknown error')];
            } else {
                $auditResult = $this->auditService->getAuditByLeadId($leadId);
            }
        }

        $findings = $auditResult['audit_json']['findings'] ?? [];
        $businessSummary = $auditResult['summary'] ?? '';

        // 2. Perform Qualification Scoring using AI
        $systemPrompt = "You are an AI Lead Qualification Specialist. Your job is to analyze the business audit, summary, and website metrics to score its qualification out of 100. Output ONLY valid structured JSON.";
        $userPrompt = "Qualify and score this business:\nName: {$lead['business_name']}\nWebsite: {$lead['website_url']}\nIndustry: {$lead['industry']}\nLocation: {$lead['location']}\nBusiness Summary: $businessSummary\nAudit Findings: " . json_encode($findings, JSON_UNESCAPED_UNICODE);

        $instructions = [
            "Return JSON with keys: score (integer 0-100), key_reasons (array of strings), budget_potential (Low/Medium/High), estimated_tech_stack (array of strings), decision_maker_role (string), and outreach_strategy (string).",
            "Be critical: High score should represent significant opportunity (e.g. poor website conversion, low SEO basics, but high budget potential).",
            "Ensure the output format is strictly structured JSON."
        ];

        $raw = $this->aiClient->generateStructuredJson((string)$leadId, $systemPrompt, $userPrompt, $instructions);

        if ($raw === null) {
            $findingCount = count($findings);
            $raw = [
                'score' => max(10, min(95, 45 + ($findingCount * 10))),
                'key_reasons' => ['Score calculated from the live website audit', "{$findingCount} actionable website finding(s) detected"],
                'budget_potential' => 'Unknown',
                'estimated_tech_stack' => [],
                'decision_maker_role' => 'Business Owner / Marketing Lead',
                'outreach_strategy' => 'Review the evidence-backed website findings with the owner before proposing a fix.'
            ];
        }

        // 3. Update database
        $this->leadModel->updateQualification($leadId, (int)$raw['score'], $raw, 'Analyzed');

        return [
            'success' => true,
            'score' => $raw['score'],
            'details' => $raw
        ];
    }

}

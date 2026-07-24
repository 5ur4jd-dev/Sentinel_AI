<?php

require_once __DIR__ . '/AiClient.php';
require_once __DIR__ . '/../Leads/LeadModel.php';
require_once __DIR__ . '/../Audit/AuditService.php';

class ConversationEngine
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
     * Handle incoming prospect message, generate AI reply, and evaluate buying intent.
     */
    public function respondToProspect(int $leadId, string $prospectMessage): string
    {
        // 1. Record prospect's message
        $this->leadModel->addMessage($leadId, 'prospect', $prospectMessage);
        $this->leadModel->updateStatus($leadId, 'In Conversation');

        // 2. Fetch history and context
        $lead = $this->leadModel->getById($leadId);
        $history = $this->leadModel->getConversations($leadId);
        $auditResult = $this->auditService->getAuditByLeadId($leadId);
        $findings = $auditResult['audit_json']['findings'] ?? [];

        // Build history text
        $formattedHistory = "";
        foreach ($history as $msg) {
            $senderName = ($msg['sender'] === 'sdr') ? "AI SDR (You)" : "Prospect";
            $formattedHistory .= "[{$senderName}]: {$msg['message']}\n";
        }

        // 3. Generate response and intent check via AI
        $systemPrompt = "You are an autonomous AI Sales Development Representative (SDR) for Sentinel AI. Your goal is to guide the conversation with the prospect, answer their questions, handle objections, and schedule a sales meeting. Keep responses short, professional, and helpful. Output ONLY valid structured JSON.";
        
        $userPrompt = "Context of Lead:\n" .
                      "Business Name: {$lead['business_name']}\n" .
                      "Industry: {$lead['industry']}\n" .
                      "Audit Findings: " . json_encode($findings, JSON_UNESCAPED_UNICODE) . "\n\n" .
                      "Conversation History:\n" . $formattedHistory . "\n" .
                      "Generate your next response as the AI SDR.";

        $instructions = [
            "Return JSON with keys: reply_message (string), buying_intent_detected (boolean), handover_summary (string or null).",
            "Set buying_intent_detected to true ONLY when the prospect is asking to set up a meeting, book a call, requests pricing, or requests a human callback.",
            "If buying_intent_detected is true, provide a structured summary in handover_summary including: Business Name, Decision Maker, Key Pain Points, Needs, and Recommended Next Step."
        ];

        $raw = $this->aiClient->generateStructuredJson((string)$leadId, $systemPrompt, $userPrompt, $instructions);

        $reply = "";
        $buyingIntent = false;
        $handoverSummary = null;

        if ($raw !== null) {
            $reply = trim($raw['reply_message'] ?? '');
            $buyingIntent = (bool)($raw['buying_intent_detected'] ?? false);
            $handoverSummary = $raw['handover_summary'] ?? null;
            if (is_array($handoverSummary)) {
                $formatted = "";
                foreach ($handoverSummary as $key => $val) {
                    if (is_array($val)) {
                        $val = implode(", ", $val);
                    }
                    $formatted .= "**" . ucwords(str_replace('_', ' ', $key)) . "**: " . $val . "\n";
                }
                $handoverSummary = trim($formatted);
            }
        }

        if (empty($reply)) {
            // Default reply fallback if AI fails
            if (stripos($prospectMessage, 'yes') !== false || stripos($prospectMessage, 'interested') !== false || stripos($prospectMessage, 'call') !== false || stripos($prospectMessage, 'meeting') !== false) {
                $reply = "That sounds great! I'll have our human representative get in touch with you shortly to book a convenient time. Looking forward to talking!";
                $buyingIntent = true;
                $handoverSummary = "Prospect expressed interest in setting up a call. Business: {$lead['business_name']}. Request: '{$prospectMessage}'";
            } else {
                $reply = "Thank you for the update. Let me know if you would be open to scheduling a quick 10-minute call to discuss your website opportunities.";
            }
        }

        // 4. Record AI SDR response
        $this->leadModel->addMessage($leadId, 'sdr', $reply);

        // 5. Escalate to human if buying intent detected
        if ($buyingIntent) {
            $this->leadModel->updateStatus($leadId, 'Qualified');
            $this->leadModel->updateHandover($leadId, 'Pending', $handoverSummary ?: 'Prospect is ready for handoff.');
        }

        return $reply;
    }
}

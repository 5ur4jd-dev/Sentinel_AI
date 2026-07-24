<?php

require_once __DIR__ . '/../Leads/LeadModel.php';
require_once __DIR__ . '/../Audit/AuditService.php';
require_once __DIR__ . '/../Outreach/OutreachService.php';
require_once __DIR__ . '/../AI/ConversationEngine.php';

$leadModel = new LeadModel();
$auditService = new AuditService();
$outreachService = new OutreachService();
$conversationEngine = new ConversationEngine();

$errors = [];
$lead = null;
$audited = null;
$outreach = null;
$conversations = [];

if (isset($_GET['id'])) {
    $leadId = (int)$_GET['id'];
    $lead = $leadModel->getById($leadId);
    if ($lead) {
        // Handle incoming prospect response simulator
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simulate_reply'])) {
            $msg = trim($_POST['prospect_reply'] ?? '');
            if ($msg !== '') {
                $conversationEngine->respondToProspect($leadId, $msg);
                header('Location: lead.php?id=' . $leadId);
                exit;
            } else {
                $errors[] = 'Simulated message cannot be empty.';
            }
        }

        // Handle manually overriding the message tone / generating outreach
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_outreach'])) {
            $tone = $_POST['tone'] ?? 'formal';
            $audit = $auditService->getAuditByLeadId($leadId);
            if ($audit && isset($audit['audit_json']['findings'])) {
                $message = $outreachService->generateMessage($leadId, $tone, $audit['audit_json']['findings']);
                if ($message === null) {
                    $errors[] = 'Outreach generation failed.';
                } else {
                    $leadModel->updateStatus($leadId, 'Outreach Drafted');
                }
            } else {
                $errors[] = 'Audit findings are required. Run audit first.';
            }
        }

        $audited = $auditService->getAuditByLeadId($lead['id']);
        $outreach = $outreachService->getByLeadId($lead['id']);
        $conversations = $leadModel->getConversations($lead['id']);
    }
}

if (!$lead) {
    header('Location: index.php');
    exit;
}

$qualificationDetails = $lead['qualification_details'] ? json_decode($lead['qualification_details'], true) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sentinel AI — Prospect pipeline</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Plus+Jakarta+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body>
    <main class="container">
        <header class="app-header">
            <div class="logo-wrapper">
                <span class="logo-icon">🤖</span>
                <div>
                    <h1>Prospect SDR Pipeline</h1>
                    <p class="subtitle"><a href="index.php">← Back to Dashboard</a></p>
                </div>
            </div>
            <div class="status-indicator">
                <span>Pipeline Stage: <strong><?= htmlspecialchars($lead['status']) ?></strong></span>
            </div>
        </header>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Prospect Header Panel -->
        <section class="panel lead-detail-panel">
            <div class="detail-header">
                <h2><?= htmlspecialchars($lead['business_name']) ?></h2>
                <div class="score-pill score-large score-<?= $lead['score'] >= 80 ? 'high' : ($lead['score'] >= 50 ? 'medium' : 'low') ?>">
                    Score: <?= $lead['score'] ?>
                </div>
            </div>
            <div class="meta-grid">
                <p><strong>Website:</strong> <a href="<?= htmlspecialchars($lead['website_url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($lead['website_url']) ?></a></p>
                <p><strong>Industry:</strong> <?= htmlspecialchars($lead['industry'] ?: '-') ?> | <strong>Location:</strong> <?= htmlspecialchars($lead['location'] ?: '-') ?></p>
                <p><strong>Contact:</strong> <?= htmlspecialchars($lead['contact_name'] ?: '-') ?> (<?= htmlspecialchars($lead['contact_email'] ?: '-') ?>)</p>
                <p><strong>Source:</strong> <?= htmlspecialchars($lead['source'] ?: '-') ?></p>
            </div>
        </section>

        <!-- Dynamic Two Column Layout -->
        <div class="two-column">
            <!-- Left Column: Qualification & Web Audit Details -->
            <div class="left-col">
                <!-- Qualification Card -->
                <?php if ($qualificationDetails): ?>
                    <section class="panel qualification-card">
                        <h3>Qualification Analysis</h3>
                        <p><strong>Budget Potential:</strong> <span class="badge"><?= htmlspecialchars($qualificationDetails['budget_potential'] ?? 'Unknown') ?></span></p>
                        <p><strong>Estimated Tech Stack:</strong> <?= implode(', ', $qualificationDetails['estimated_tech_stack'] ?? []) ?></p>
                        <p><strong>Decision Maker Target:</strong> <em><?= htmlspecialchars($qualificationDetails['decision_maker_role'] ?? 'Unknown') ?></em></p>
                        <div class="strategy-block">
                            <strong>Strategy Summary:</strong>
                            <p><?= htmlspecialchars($qualificationDetails['outreach_strategy'] ?? '-') ?></p>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Audit Findings -->
                <?php if ($audited): ?>
                    <section class="panel audit-findings-panel">
                        <h3>Website Intelligence Findings</h3>
                        <?php $findings = $audited['audit_json']['findings'] ?? []; ?>
                        <?php if ($findings): ?>
                            <ul class="findings-list">
                                <?php foreach ($findings as $finding): ?>
                                    <li>
                                        <strong><?= htmlspecialchars($finding['category'] ?? 'Unknown') ?>:</strong>
                                        <p><?= htmlspecialchars($finding['observation'] ?? '') ?></p>
                                        <p class="small"><em>Evidence:</em> <?= htmlspecialchars($finding['evidence'] ?? '') ?></p>
                                        <p class="small"><em>Impact:</em> <?= htmlspecialchars($finding['impact'] ?? '') ?></p>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>No audit findings were returned.</p>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            </div>

            <!-- Right Column: Conversations & Messaging -->
            <div class="right-col">
                <!-- Conversation Thread -->
                <section class="panel chat-panel">
                    <h3>Conversation History</h3>
                    <div class="chat-box">
                        <?php if (empty($conversations)): ?>
                            <p class="placeholder-text">No active conversation yet. The AI SDR has dispatched the initial outreach email below.</p>
                        <?php else: ?>
                            <div class="conversation-thread">
                                <?php foreach ($conversations as $msg): ?>
                                    <div class="chat-bubble chat-<?= $msg['sender'] === 'sdr' ? 'sdr' : 'prospect' ?>">
                                        <div class="bubble-sender"><?= $msg['sender'] === 'sdr' ? '🤖 AI SDR' : '👤 Prospect' ?></div>
                                        <div class="bubble-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                                        <div class="bubble-time"><?= $msg['created_at'] ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Simulator Input -->
                    <div class="simulator-wrapper">
                        <h4>Inbound Reply Simulator</h4>
                        <p class="simulator-hint">Type a simulated response from the prospect to test how the AI SDR responds and decides on a human handoff.</p>
                        <form method="post" class="simulator-form">
                            <textarea name="prospect_reply" rows="3" placeholder="e.g. Yes, I'm interested. What is your pricing and can we set up a call for next week?" required></textarea>
                            <button type="submit" name="simulate_reply" class="button button-accent">📥 Send Simulated Reply</button>
                        </form>
                    </div>
                </section>

                <!-- Initial Outreach Block -->
                <?php if ($outreach): ?>
                    <section class="panel outreach-panel">
                        <h3>Initial Personalised Outreach</h3>
                        <p><strong>Channel:</strong> Email (SDR Primary) | <strong>Status:</strong> <?= htmlspecialchars($outreach['status']) ?></p>
                        <pre class="outreach-message"><?= htmlspecialchars($outreach['message']) ?></pre>
                    </section>
                <?php endif; ?>

                <!-- Human Hand-off Summary -->
                <?php if ($lead['handover_status'] !== 'None'): ?>
                    <section class="panel handover-panel">
                        <h3>🚨 Human Hand-off Status</h3>
                        <p><strong>Status:</strong> <span class="badge badge-accent"><?= htmlspecialchars($lead['handover_status']) ?></span></p>
                        <div class="handover-summary-box">
                            <strong>Qualified Handoff Summary:</strong>
                            <p><?= nl2br(htmlspecialchars($lead['handover_summary'])) ?></p>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../Leads/LeadModel.php';
require_once __DIR__ . '/../AI/DiscoveryEngine.php';
require_once __DIR__ . '/../AI/QualificationEngine.php';
require_once __DIR__ . '/../Outreach/OutreachService.php';
require_once __DIR__ . '/../AI/ConversationEngine.php';

$leadModel = new LeadModel();
$discoveryEngine = new DiscoveryEngine();
$qualificationEngine = new QualificationEngine();
$outreachService = new OutreachService();
$conversationEngine = new ConversationEngine();

// Initialize session chat history
if (!isset($_SESSION['commander_chat'])) {
    $_SESSION['commander_chat'] = [
        [
            'sender' => 'sdr',
            'message' => 'Hello! I am your AI SDR Commander. Tell me what kind of businesses you want to target (e.g. "Find me 3 dental clinics in Boston") and I will start searching, qualifying, and contacting them immediately!'
        ]
    ];
}

if (!isset($_SESSION['cycle_logs'])) {
    $_SESSION['cycle_logs'] = [];
}

$targetIndustry = $_POST['target_industry'] ?? 'Dental Clinic';
$targetLocation = $_POST['target_location'] ?? 'Miami';
$minScore = (int)($_POST['min_score'] ?? 65);

// Helper function to run targeting cycle
function runSdrCycle(string $industry, string $location, int $limit, int $threshold): array
{
    global $discoveryEngine, $qualificationEngine, $outreachService, $conversationEngine, $leadModel;
    $cycleLogs = [];
    $cycleLogs[] = "🚀 Starting Autonomous SDR Cycle...";
    $cycleLogs[] = "🔍 Targeting Industry: '{$industry}' | Location: '{$location}' | Min Score: {$threshold}";
    
    // 1. Discovery
    $discovered = $discoveryEngine->discover($industry, $location, $limit);
    $cycleLogs[] = "✨ Discovered " . count($discovered) . " target prospects matching parameters.";
    
    foreach ($discovered as $biz) {
        $leadId = $biz['id'];
        $cycleLogs[] = "--------------------------------------------------";
        $cycleLogs[] = "💼 Business: <strong>" . htmlspecialchars($biz['business_name']) . "</strong> | Website: {$biz['website_url']}";
        
        // 2. Website Intel & Qualification
        $qualification = $qualificationEngine->qualify($leadId);
        $score = $qualification['score'] ?? 0;
        $details = $qualification['details'] ?? [];
        
        $cycleLogs[] = "📈 AI Qualification Score: <strong>{$score}/100</strong>";
        
        if (!empty($details['key_reasons'])) {
            $cycleLogs[] = "ℹ️ Reasons: " . implode(', ', $details['key_reasons']);
        }
        
        if ($score < $threshold) {
            $cycleLogs[] = "⚠️ Business score is below minimum threshold ({$threshold}). Archiving prospect.";
            $leadModel->updateStatus($leadId, 'Archived');
            continue;
        }
        
        // 3. Outreach Generation
        $auditResult = (new AuditService())->getAuditByLeadId($leadId);
        $findings = $auditResult['audit_json']['findings'] ?? [];
        
        $email = $outreachService->generateMessage($leadId, 'friendly', $findings);
        if ($email) {
            $cycleLogs[] = "✉️ Outreach email drafted successfully.";
            $leadModel->updateStatus($leadId, 'Outreach Sent');
            
            // Mark outreach message status as Sent
            $outreachService->saveMessage($leadId, 'friendly', $email);
            $db = Database::getConnection();
            $db->prepare('UPDATE outreach_messages SET status = "Sent" WHERE lead_id = :lead_id')->execute([':lead_id' => $leadId]);
            $cycleLogs[] = "🚀 Outreach email dispatched to decision maker: <em>" . htmlspecialchars($biz['contact_name'] ?: 'Owner') . "</em>";
            
            // 4. Simulate Prospect Response
            $simulatedResponses = [
                "Hey! Thanks for reaching out. Yes, our page loading speed has been a bit slow lately. What are your pricing plans? Can we schedule a call?",
                "Hi, I saw your comments about our accessibility compliance. Let's arrange a brief discussion next Tuesday afternoon.",
                "Hello, we know our conversion rate is low. Do you have a calendar link where we can schedule a quick 10-minute demo?"
            ];
            $prospectMsg = $simulatedResponses[array_rand($simulatedResponses)];
            $cycleLogs[] = "📥 Inbound email received: \"$prospectMsg\"";
            
            // 5. Conversation Engine handling
            $reply = $conversationEngine->respondToProspect($leadId, $prospectMsg);
            $cycleLogs[] = "📤 AI SDR Sent Reply: \"$reply\"";
            
            $updatedLead = $leadModel->getById($leadId);
            if ($updatedLead['handover_status'] === 'Pending') {
                $cycleLogs[] = "🚨 <strong>Buying Intent Detected!</strong> Escated to Human Inbox.";
            }
        }
    }
    
    $cycleLogs[] = "--------------------------------------------------";
    $cycleLogs[] = "✅ Autonomous SDR Cycle completed.";
    return $cycleLogs;
}

// 1. Handle traditional form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_cycle'])) {
    $_SESSION['cycle_logs'] = runSdrCycle($targetIndustry, $targetLocation, 3, $minScore);
    header('Location: index.php?tab=logs');
    exit;
}

// 2. Handle Chatbot interaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_chat'])) {
    $chatMsg = trim($_POST['chat_message'] ?? '');
    if ($chatMsg !== '') {
        // Record user message
        $_SESSION['commander_chat'][] = [
            'sender' => 'user',
            'message' => $chatMsg
        ];

        // Call AI to parse targeting parameters or answer conversationally
        $aiClient = new AiClient();
        $systemPrompt = "You are the Sentinel AI SDR Commander, the central coordinator of our autonomous sales development system. You can either execute business discovery searches, or answer questions and chat with the user. Output ONLY valid structured JSON.";
        
        $leads = $leadModel->getAll();
        $handovers = array_filter($leads, function($l) { return $l['handover_status'] === 'Pending'; });
        $statsSummary = "Current Database Info: Total Discovered leads: " . count($leads) . ". High priority handovers waiting: " . count($handovers) . ".";

        $userPrompt = "System context: $statsSummary\nUser message: \"$chatMsg\"";
        
        $instructions = [
            "Return JSON with keys: should_search (boolean), industry (string or null), location (string or null), limit (integer, default 3), reply_message (string).",
            "If the user wants to search for/target/discover/find new businesses/leads, set should_search to true and extract industry, location, and limit.",
            "If the user is asking general questions, greeting you, or chatting, set should_search to false, set industry/location to null, and write a helpful conversational reply in reply_message."
        ];

        $parsed = $aiClient->generateStructuredJson("0", $systemPrompt, $userPrompt, $instructions);

        $aiReply = "";
        if ($parsed) {
            $shouldSearch = (bool)($parsed['should_search'] ?? false);
            if ($shouldSearch && !empty($parsed['industry']) && !empty($parsed['location'])) {
                $limit = (int)($parsed['limit'] ?? 3);
                $parsedIndustry = $parsed['industry'];
                $parsedLocation = $parsed['location'];

                // Run cycle
                $runLogs = runSdrCycle($parsedIndustry, $parsedLocation, $limit, 60);
                $_SESSION['cycle_logs'] = $runLogs;

                $aiReply = "Roger that! I successfully executed an autonomous cycle for **$parsedIndustry** in **$parsedLocation** (Limit: $limit).\n\n" . 
                           $parsed['reply_message'] . "\n\n" .
                           "You can view the detailed step-by-step logs under the **SDR Logs** tab. The discovered leads have been updated below in the prospect lists.";
            } else {
                $aiReply = $parsed['reply_message'] ?? "I'm on it! Let me know if you'd like me to start target search crawl cycle.";
            }
        }

        if (empty($aiReply)) {
            $aiReply = "I was unable to process the request due to an API timeout. Please verify your OpenRouter key.";
        }

        $_SESSION['commander_chat'][] = [
            'sender' => 'sdr',
            'message' => $aiReply
        ];

        header('Location: index.php?tab=chat');
        exit;
    }
}

// 3. Clear logs / chat
if (isset($_GET['clear_chat'])) {
    unset($_SESSION['commander_chat']);
    header('Location: index.php');
    exit;
}

// Fetch lists
$leads = $leadModel->getAll();
$handovers = array_filter($leads, function($l) {
    return $l['handover_status'] === 'Pending';
});

// Calculate stats
$totalDiscovered = count($leads);
$totalQualified = count(array_filter($leads, function($l) {
    return $l['status'] === 'Qualified';
}));
$totalOutreach = count(array_filter($leads, function($l) {
    return $l['status'] === 'Outreach Sent' || $l['status'] === 'In Conversation' || $l['status'] === 'Qualified';
}));

$activeTab = $_GET['tab'] ?? 'chat';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sentinel AI — Autonomous SDR Dashboard</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Plus+Jakarta+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        /* Tabs styles */
        .tab-headers {
            display: flex;
            gap: 10px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
        .tab-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1rem;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .tab-btn.active {
            color: var(--text-primary);
            background: var(--bg-tertiary);
            box-shadow: inset 0 0 6px var(--accent-glow);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        /* Chat bot UI container inside dashboard */
        .chat-container {
            display: flex;
            flex-direction: column;
            height: 350px;
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 12px;
        }
        .chat-bubble {
            max-width: 85%;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .chat-bubble.sdr {
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            align-self: flex-start;
        }
        .chat-bubble.user {
            background: var(--accent);
            color: #ffffff;
            align-self: flex-end;
        }
        .chat-input-form {
            display: flex;
            gap: 8px;
        }
        .chat-input-form input {
            flex: 1;
            padding: 12px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            outline: none;
        }
        .chat-input-form button {
            padding: 0 18px;
        }
        .clear-chat-link {
            font-size: 0.8rem;
            color: var(--text-tertiary);
            text-align: right;
            display: block;
            margin-top: 6px;
            text-decoration: none;
        }
        .clear-chat-link:hover {
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <main class="container">
        <!-- Banner Header -->
        <header class="app-header">
            <div class="logo-wrapper">
                <span class="logo-icon">🤖</span>
                <div>
                    <h1>Sentinel AI</h1>
                    <p class="subtitle">Autonomous AI Sales Development Representative</p>
                </div>
            </div>
            <div class="status-indicator">
                <span class="pulse-dot"></span>
                <span>AI SDR Agent: Online & Active</span>
            </div>
        </header>

        <!-- Stats Grid -->
        <section class="stats-grid">
            <div class="stat-card">
                <span class="stat-icon">🔍</span>
                <div class="stat-info">
                    <h3>Discovered</h3>
                    <p class="stat-number"><?= $totalDiscovered ?></p>
                </div>
            </div>
            <div class="stat-card">
                <span class="stat-icon">✉️</span>
                <div class="stat-info">
                    <h3>Outreach Sent</h3>
                    <p class="stat-number"><?= $totalOutreach ?></p>
                </div>
            </div>
            <div class="stat-card highlight">
                <span class="stat-icon">🔥</span>
                <div class="stat-info">
                    <h3>Sales Qualified</h3>
                    <p class="stat-number"><?= $totalQualified ?></p>
                </div>
            </div>
        </section>

        <!-- Configuration & Live Simulation Panel -->
        <section class="two-column">
            <!-- Targeting Configuration -->
            <div class="panel config-panel">
                <h2>Targeting Preferences</h2>
                <form method="post" class="targeting-form">
                    <label>Target Industry / Keyword
                        <input type="text" name="target_industry" value="<?= htmlspecialchars($targetIndustry) ?>" placeholder="e.g. Dental Clinic, Web Agency" required>
                    </label>
                    <label>Target Location
                        <input type="text" name="target_location" value="<?= htmlspecialchars($targetLocation) ?>" placeholder="e.g. Miami, Dallas" required>
                    </label>
                    <label>Min Qualification Score (0-100)
                        <input type="number" name="min_score" min="0" max="100" value="<?= $minScore ?>" required>
                    </label>
                    <button type="submit" name="run_cycle" class="button button-primary run-btn">⚡ Run Autonomous SDR Cycle</button>
                </form>
            </div>

            <!-- Tabbed Panel (Chatbot vs Logs) -->
            <div class="panel log-panel">
                <div class="tab-headers">
                    <button type="button" class="tab-btn <?= $activeTab === 'chat' ? 'active' : '' ?>" onclick="switchTab('chat')">💬 SDR Commander Chat</button>
                    <button type="button" class="tab-btn <?= $activeTab === 'logs' ? 'active' : '' ?>" onclick="switchTab('logs')">📜 SDR Logs</button>
                </div>

                <!-- Tab 1: Commander Chatbot -->
                <div id="tab-chat" class="tab-content <?= $activeTab === 'chat' ? 'active' : '' ?>">
                    <div class="chat-container">
                        <div class="chat-messages" id="chat-messages">
                            <?php foreach ($_SESSION['commander_chat'] as $chat): ?>
                                <div class="chat-bubble <?= $chat['sender'] === 'sdr' ? 'sdr' : 'user' ?>">
                                    <?= nl2br($chat['message']) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <form method="post" class="chat-input-form">
                            <input type="text" name="chat_message" placeholder="Ask SDR to find leads... (e.g. Find 3 shops in Miami)" required autocomplete="off">
                            <button type="submit" name="send_chat" class="button button-primary">Send</button>
                        </form>
                    </div>
                    <a href="index.php?clear_chat=1" class="clear-chat-link" onclick="return confirm('Clear chat history?')">Clear Chat History</a>
                </div>

                <!-- Tab 2: Live Activity Logs -->
                <div id="tab-logs" class="tab-content <?= $activeTab === 'logs' ? 'active' : '' ?>">
                    <div class="logs-container" id="logs">
                        <?php if (empty($_SESSION['cycle_logs'])): ?>
                            <p class="placeholder-text">No cycle logs available yet. Configure preferences on the left or chat with the SDR commander above to trigger a search.</p>
                        <?php else: ?>
                            <ul class="logs-list">
                                <?php foreach ($_SESSION['cycle_logs'] as $log): ?>
                                    <li><?= $log ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Handovers Inbox (High Priority) -->
        <section class="panel inbox-panel">
            <div class="panel-header">
                <h2>🚨 Human Hand-off Inbox (Qualified Leads)</h2>
                <span class="badge badge-accent"><?= count($handovers) ?> Pending Action</span>
            </div>
            <?php if (empty($handovers)): ?>
                <p class="placeholder-text">No pending qualified leads. Run cycles to discover and qualify opportunities.</p>
            <?php else: ?>
                <div class="handovers-list">
                    <?php foreach ($handovers as $h): ?>
                        <div class="handover-item">
                            <div class="handover-meta">
                                <h3><?= htmlspecialchars($h['business_name']) ?></h3>
                                <p><strong>Decision Maker:</strong> <?= htmlspecialchars($h['contact_name'] ?: '-') ?> | <strong>Email:</strong> <?= htmlspecialchars($h['contact_email'] ?: '-') ?></p>
                                <div class="score-badge">Score: <strong><?= $h['score'] ?></strong></div>
                            </div>
                            <div class="handover-summary">
                                <strong>AI Summary & Next Steps:</strong>
                                <p><?= nl2br(htmlspecialchars($h['handover_summary'])) ?></p>
                            </div>
                            <div class="handover-actions">
                                <a href="lead.php?id=<?= $h['id'] ?>" class="button">View Conversations & Audit</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- All Discovered Businesses -->
        <section class="panel Leads-panel">
            <h2>Active Prospect List</h2>
            <table>
                <thead>
                    <tr>
                        <th>Business</th>
                        <th>Website</th>
                        <th>Industry / Location</th>
                        <th>Score</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($leads)): ?>
                    <tr>
                        <td colspan="6" class="table-empty">No leads found. Start a Targeting search to populate.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leads as $lead): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($lead['business_name']) ?></strong><br>
                                <span class="subtext"><?= htmlspecialchars($lead['contact_name'] ?: '-') ?></span>
                            </td>
                            <td><a href="<?= htmlspecialchars($lead['website_url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($lead['website_url']) ?></a></td>
                            <td><?= htmlspecialchars($lead['industry'] ?: '-') ?> / <?= htmlspecialchars($lead['location'] ?: '-') ?></td>
                            <td>
                                <span class="score-pill score-<?= $lead['score'] >= 80 ? 'high' : ($lead['score'] >= 50 ? 'medium' : 'low') ?>">
                                    <?= $lead['score'] ?: '-' ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-pill status-<?= strtolower(str_replace(' ', '-', $lead['status'])) ?>">
                                    <?= htmlspecialchars($lead['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="lead.php?id=<?= $lead['id'] ?>" class="action-link">Open Pipeline</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        <!-- Developer Profile Card -->
        <footer class="panel dev-footer" style="margin-top: 32px; display: flex; align-items: center; justify-content: space-between; gap: 20px; border-color: var(--accent); background: linear-gradient(135deg, var(--bg-secondary), rgba(37, 99, 235, 0.05)); flex-wrap: wrap;">
            <div>
                <h3 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 1.1rem; color: var(--text-primary);">Developed by Suraj Dubey</h3>
                <p style="margin: 4px 0 0; font-size: 0.85rem; color: var(--text-secondary);">AI-Assisted Web Developer & UI Designer | Maharashtra, India</p>
                <p style="margin: 2px 0 0; font-size: 0.8rem; color: var(--text-tertiary);">Contact: <a href="mailto:5ur4jd@gmail.com" style="color: var(--accent-hover); text-decoration: none;">5ur4jd@gmail.com</a> | <a href="tel:+917219689411" style="color: var(--accent-hover); text-decoration: none;">+91 7219689411</a></p>
            </div>
            <div style="display: flex; gap: 12px;">
                <a href="https://mrsuraj.rf.gd" target="_blank" rel="noopener noreferrer" class="button" style="padding: 8px 16px; font-size: 0.85rem;">Website</a>
                <a href="https://github.com/5ur4jd-dev" target="_blank" rel="noopener noreferrer" class="button button-primary" style="padding: 8px 16px; font-size: 0.85rem;">GitHub</a>
            </div>
        </footer>
    </main>
    <script>
        // Auto scroll logs container and chat container to bottom
        const logs = document.getElementById('logs');
        if (logs) {
            logs.scrollTop = logs.scrollHeight;
        }
        const chatMessages = document.getElementById('chat-messages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function switchTab(tabId) {
            // Hide all contents
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

            // Show active
            document.getElementById('tab-' + tabId).classList.add('active');
            event.target.classList.add('active');

            // Scroll if needed
            if (tabId === 'logs' && logs) {
                logs.scrollTop = logs.scrollHeight;
            }
            if (tabId === 'chat' && chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }
    </script>
</body>
</html>

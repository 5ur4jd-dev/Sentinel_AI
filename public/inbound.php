<?php

// Generic inbound endpoint for Mailgun, Postmark, SendGrid HTTP relay, or a
// small internal mail forwarder. It accepts a normalized JSON payload:
// {"token":"...","to":"owner@example.com","from":"prospect@example.com","text":"..."}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Leads/LeadModel.php';
require_once __DIR__ . '/../AI/ConversationEngine.php';

header('Content-Type: application/json');
$config = require __DIR__ . '/../config.php';
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Expected a JSON request body.']);
    exit;
}

$expectedToken = (string)($config['INBOUND_WEBHOOK_TOKEN'] ?? '');
if ($expectedToken === '' || !hash_equals($expectedToken, (string)($payload['token'] ?? ''))) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid webhook token.']);
    exit;
}

$from = trim((string)($payload['from'] ?? ''));
$text = trim((string)($payload['text'] ?? $payload['body'] ?? ''));
if (!filter_var($from, FILTER_VALIDATE_EMAIL) || $text === '') {
    http_response_code(422);
    echo json_encode(['error' => 'A valid from email and non-empty text are required.']);
    exit;
}

$model = new LeadModel();
$db = Database::getConnection();
$statement = $db->prepare('SELECT id FROM leads WHERE contact_email = :email ORDER BY id DESC LIMIT 1');
$statement->execute([':email' => $from]);
$lead = $statement->fetch();
if (!$lead) {
    http_response_code(404);
    echo json_encode(['error' => 'No lead matches this sender email.']);
    exit;
}

$reply = (new ConversationEngine())->respondToProspect((int)$lead['id'], $text);
echo json_encode(['success' => true, 'lead_id' => (int)$lead['id'], 'reply' => $reply]);

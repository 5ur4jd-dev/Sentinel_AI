<?php

require_once __DIR__ . '/../Leads/LeadModel.php';
require_once __DIR__ . '/../Audit/AuditService.php';
require_once __DIR__ . '/../Outreach/OutreachService.php';

$leadModel = new LeadModel();
$auditService = new AuditService();
$outreachService = new OutreachService();

$lead = null;
$audit = null;
$outreach = null;

if (isset($_GET['id'])) {
    $lead = $leadModel->getById((int)$_GET['id']);
    if ($lead) {
        $audit = $auditService->getAuditByLeadId($lead['id']);
        $outreach = $outreachService->getByLeadId($lead['id']);
    }
}

if (!$lead) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export report — <?= htmlspecialchars($lead['business_name']) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <main class="container printable">
        <header>
            <h1>Sentinel AI report</h1>
            <p><?= htmlspecialchars($lead['business_name']) ?> — <?= htmlspecialchars($lead['website_url']) ?></p>
        </header>

        <section class="panel">
            <h2>Business summary</h2>
            <p><?= nl2br(htmlspecialchars($audit['summary'] ?? 'No summary available.')) ?></p>
        </section>

        <section class="panel">
            <h2>Audit findings</h2>
            <?php $findings = $audit['audit_json']['findings'] ?? []; ?>
            <?php if ($findings): ?>
                <ol class="findings-list">
                    <?php foreach ($findings as $finding): ?>
                        <li>
                            <strong><?= htmlspecialchars($finding['category'] ?? 'Unknown') ?>:</strong>
                            <p><?= htmlspecialchars($finding['observation'] ?? '') ?></p>
                            <p class="small"><em>Evidence:</em> <?= htmlspecialchars($finding['evidence'] ?? '') ?></p>
                            <p class="small"><em>Impact:</em> <?= htmlspecialchars($finding['impact'] ?? '') ?></p>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p>No audit findings are available. Run analysis first.</p>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h2>Outreach</h2>
            <?php if ($outreach): ?>
                <p><strong>Status:</strong> <?= htmlspecialchars($outreach['status']) ?></p>
                <pre class="outreach-message"><?= htmlspecialchars($outreach['message']) ?></pre>
            <?php else: ?>
                <p>No outreach message available.</p>
            <?php endif; ?>
        </section>

        <footer>
            <p>Sentinel AI v0.1 export.</p>
        </footer>
    </main>
</body>
</html>

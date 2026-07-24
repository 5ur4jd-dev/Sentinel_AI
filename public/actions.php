<?php

require_once __DIR__ . '/../Leads/LeadModel.php';

$leadModel = new LeadModel();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? '';

if ($id > 0 && $action === 'delete') {
    $leadModel->delete($id);
}

header('Location: index.php');
exit;

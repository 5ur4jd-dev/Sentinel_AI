<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../AI/AiClient.php';
require_once __DIR__ . '/../Audit/AuditService.php';
require_once __DIR__ . '/../Leads/LeadModel.php';

$success = true;

function assertEqual($actual, $expected, $message) {
    global $success;
    if ($actual === $expected) {
        echo "✅ PASS: $message\n";
    } else {
        echo "❌ FAIL: $message\n";
        echo "   Expected: " . print_r($expected, true) . "\n";
        echo "   Actual:   " . print_r($actual, true) . "\n";
        $success = false;
    }
}

// 1. Test AiClient JSON parsing
echo "--- Testing AiClient JSON Parsing ---\n";
// We subclass to avoid DB connection in constructor
class TestAiClient extends AiClient {
    public function __construct() {
        // Skip DB connection
    }
    public function testParseJson(string $text) {
        // Call the private/protected method via reflection or expose it
        $reflector = new ReflectionClass('AiClient');
        $method = $reflector->getMethod('parseJson');
        return $method->invoke($this, $text);
    }
}

$ai = new TestAiClient();
assertEqual(
    $ai->testParseJson('{"findings": [], "business_summary": "Test"}'),
    ["findings" => [], "business_summary" => "Test"],
    "Should parse clean JSON"
);
assertEqual(
    $ai->testParseJson("```json\n{\"findings\": [], \"business_summary\": \"Test\"}\n```"),
    ["findings" => [], "business_summary" => "Test"],
    "Should parse JSON wrapped in markdown code blocks"
);
assertEqual(
    $ai->testParseJson("```\n{\"findings\": [], \"business_summary\": \"Test\"}\n```"),
    ["findings" => [], "business_summary" => "Test"],
    "Should parse JSON wrapped in code blocks without language specifier"
);
assertEqual(
    $ai->testParseJson('invalid-json'),
    null,
    "Should return null for invalid JSON"
);

// 2. Test AuditService text extraction and HTML parsing
echo "\n--- Testing AuditService HTML & Text Parsing ---\n";
class TestAuditService extends AuditService {
    public function __construct() {
        // Skip DB connection
    }
    public function testParseHtml(string $html) {
        $reflector = new ReflectionClass('AuditService');
        $method = $reflector->getMethod('parseHtml');
        return $method->invoke($this, $html);
    }
    public function testExtractText($doc) {
        $reflector = new ReflectionClass('AuditService');
        $method = $reflector->getMethod('extractText');
        return $method->invoke($this, $doc);
    }
    public function testParseHeaders(string $headers) {
        $reflector = new ReflectionClass('AuditService');
        $method = $reflector->getMethod('parseHeaders');
        return $method->invoke($this, $headers);
    }
}

$audit = new TestAuditService();
$html = '<html><head><title>Ignored</title></head><body><h1>Hello World</h1><script>console.log("ignore");</script><p>Nice day.</p></body></html>';
$doc = $audit->testParseHtml($html);
$text = $audit->testExtractText($doc);
assertEqual(
    trim($text),
    "Hello World\nNice day.",
    "Should extract body text while ignoring scripts"
);

$headerText = "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nConnection: keep-alive\r\n";
$headers = $audit->testParseHeaders($headerText);
assertEqual(
    $headers['Content-Type'] ?? null,
    "text/html",
    "Should parse Content-Type header"
);
assertEqual(
    $headers['Connection'] ?? null,
    "keep-alive",
    "Should parse Connection header"
);

// 3. Test LeadModel CSV import
echo "\n--- Testing LeadModel CSV Import ---\n";
class TestLeadModel extends LeadModel {
    public array $createdLeads = [];
    public function __construct() {
        // Skip DB connection
    }
    public function create(array $data): int {
        $this->createdLeads[] = $data;
        return count($this->createdLeads);
    }
}

// Write a temp CSV file to test
$csvContent = "Business Name,Website URL,Contact Name,Contact Email,Contact Phone,Notes,Source\n";
$csvContent .= "Acme Corp,https://acme.org,John Doe,john@acme.org,123456,Some notes,Web Search\n";
$csvContent .= "Invalid Lead,,No Website,email@test.com,,,\n"; // Missing website URL
$csvContent .= "Another Corp,https://another.org,Jane Doe,,,,,\n";

$tmpPath = tempnam(sys_get_temp_dir(), 'csv_test');
file_put_contents($tmpPath, $csvContent);

$leadModel = new TestLeadModel();
$importResult = $leadModel->importFromCsv($tmpPath);

unlink($tmpPath);

assertEqual($importResult['imported'], 2, "Should import 2 valid rows");
assertEqual(count($importResult['errors']), 1, "Should have 1 validation error row");
assertEqual(count($leadModel->createdLeads), 2, "Should create 2 leads in the database store");

// Check the imported data structure
assertEqual($leadModel->createdLeads[0]['business_name'], "Acme Corp", "First lead business name");
assertEqual($leadModel->createdLeads[0]['website_url'], "https://acme.org", "First lead website URL");
assertEqual($leadModel->createdLeads[0]['contact_name'], "John Doe", "First lead contact name");
assertEqual($leadModel->createdLeads[1]['business_name'], "Another Corp", "Second lead business name");
assertEqual($leadModel->createdLeads[1]['contact_email'], "", "Second lead contact email (empty)");

if ($success) {
    echo "\n🎉 ALL TESTS PASSED!\n";
    exit(0);
} else {
    echo "\n❌ SOME TESTS FAILED!\n";
    exit(1);
}

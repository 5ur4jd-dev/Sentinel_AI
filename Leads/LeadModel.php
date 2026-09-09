<?php

require_once __DIR__ . '/../Db/Database.php';

class LeadModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getAll(): array
    {
        $statement = $this->db->query("SELECT * FROM leads WHERE status <> 'Archived' ORDER BY created_at DESC");
        return $statement->fetchAll();
    }

    public function getByWebsite(string $websiteUrl): ?array
    {
        $statement = $this->db->prepare("SELECT * FROM leads WHERE lower(website_url) = lower(:website_url) AND status <> 'Archived' LIMIT 1");
        $statement->execute([':website_url' => $websiteUrl]);
        $lead = $statement->fetch();
        return $lead ?: null;
    }

    public function getById(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM leads WHERE id = :id');
        $statement->execute([':id' => $id]);
        $lead = $statement->fetch();
        return $lead ?: null;
    }

    public function create(array $data): int
    {
        $statement = $this->db->prepare(
            'INSERT INTO leads (business_name, website_url, contact_name, contact_email, contact_phone, notes, source, status, industry, location, score, qualification_details, handover_status, handover_summary) VALUES (:business_name, :website_url, :contact_name, :contact_email, :contact_phone, :notes, :source, :status, :industry, :location, :score, :qualification_details, :handover_status, :handover_summary)'
        );
        $statement->execute([
            ':business_name' => $data['business_name'],
            ':website_url' => $data['website_url'],
            ':contact_name' => $data['contact_name'] ?? null,
            ':contact_email' => $data['contact_email'] ?? null,
            ':contact_phone' => $data['contact_phone'] ?? null,
            ':notes' => $data['notes'] ?? null,
            ':source' => $data['source'] ?? null,
            ':status' => $data['status'] ?? 'New',
            ':industry' => $data['industry'] ?? null,
            ':location' => $data['location'] ?? null,
            ':score' => $data['score'] ?? 0,
            ':qualification_details' => isset($data['qualification_details']) ? (is_array($data['qualification_details']) ? json_encode($data['qualification_details'], JSON_UNESCAPED_UNICODE) : $data['qualification_details']) : null,
            ':handover_status' => $data['handover_status'] ?? 'None',
            ':handover_summary' => $data['handover_summary'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateQualification(int $id, int $score, array $details, string $status): bool
    {
        $statement = $this->db->prepare('UPDATE leads SET score = :score, qualification_details = :details, status = :status WHERE id = :id');
        return $statement->execute([
            ':score' => $score,
            ':details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            ':status' => $status,
            ':id' => $id
        ]);
    }

    public function updateHandover(int $id, string $status, string $summary): bool
    {
        $statement = $this->db->prepare('UPDATE leads SET handover_status = :status, handover_summary = :summary WHERE id = :id');
        return $statement->execute([
            ':status' => $status,
            ':summary' => $summary,
            ':id' => $id
        ]);
    }

    public function addMessage(int $leadId, string $sender, string $message): int
    {
        $statement = $this->db->prepare('INSERT INTO conversations (lead_id, sender, message) VALUES (:lead_id, :sender, :message)');
        $statement->execute([
            ':lead_id' => $leadId,
            ':sender' => $sender,
            ':message' => $message
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function getConversations(int $leadId): array
    {
        $statement = $this->db->prepare('SELECT * FROM conversations WHERE lead_id = :lead_id ORDER BY created_at ASC');
        $statement->execute([':lead_id' => $leadId]);
        return $statement->fetchAll();
    }

    public function updateStatus(int $id, string $status): bool
    {
        $statement = $this->db->prepare('UPDATE leads SET status = :status WHERE id = :id');
        return $statement->execute([':status' => $status, ':id' => $id]);
    }

    public function importFromCsv(string $tmpPath): array
    {
        $results = [
            'imported' => 0,
            'errors' => [],
        ];

        if (!file_exists($tmpPath) || !is_readable($tmpPath)) {
            $results['errors'][] = 'Uploaded file is not readable.';
            return $results;
        }

        $handle = fopen($tmpPath, 'r');
        if ($handle === false) {
            $results['errors'][] = 'Unable to parse the CSV file.';
            return $results;
        }

        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        if ($headers === false) {
            $results['errors'][] = 'CSV file is empty.';
            fclose($handle);
            return $results;
        }

        $headerMap = array_change_key_case(array_flip($headers), CASE_LOWER);
        $rowNumber = 1;

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $rowNumber++;
            if (count($row) === 0 || trim(implode('', $row)) === '') {
                continue;
            }

            $businessNameKey = $headerMap['business name'] ?? $headerMap['business_name'] ?? null;
            $websiteUrlKey = $headerMap['website url'] ?? $headerMap['website_url'] ?? null;
            $contactNameKey = $headerMap['contact name'] ?? $headerMap['contact_name'] ?? null;
            $contactEmailKey = $headerMap['contact email'] ?? $headerMap['contact_email'] ?? null;
            $contactPhoneKey = $headerMap['contact phone'] ?? $headerMap['contact_phone'] ?? null;
            $notesKey = $headerMap['notes'] ?? null;
            $sourceKey = $headerMap['source'] ?? null;

            $businessName = ($businessNameKey !== null && isset($row[$businessNameKey])) ? trim($row[$businessNameKey]) : '';
            $websiteUrl = ($websiteUrlKey !== null && isset($row[$websiteUrlKey])) ? trim($row[$websiteUrlKey]) : '';
            $contactName = ($contactNameKey !== null && isset($row[$contactNameKey])) ? trim($row[$contactNameKey]) : '';
            $contactEmail = ($contactEmailKey !== null && isset($row[$contactEmailKey])) ? trim($row[$contactEmailKey]) : '';
            $contactPhone = ($contactPhoneKey !== null && isset($row[$contactPhoneKey])) ? trim($row[$contactPhoneKey]) : '';
            $notes = ($notesKey !== null && isset($row[$notesKey])) ? trim($row[$notesKey]) : '';
            $source = ($sourceKey !== null && isset($row[$sourceKey])) ? trim($row[$sourceKey]) : '';

            if ($businessName === '' || $websiteUrl === '') {
                $results['errors'][] = "Row $rowNumber: missing business name or website URL.";
                continue;
            }

            if (!filter_var($websiteUrl, FILTER_VALIDATE_URL)) {
                $results['errors'][] = "Row $rowNumber: invalid website URL.";
                continue;
            }

            $this->create([
                'business_name' => $businessName,
                'website_url' => $websiteUrl,
                'contact_name' => $contactName,
                'contact_email' => $contactEmail,
                'contact_phone' => $contactPhone,
                'notes' => $notes,
                'source' => $source,
                'status' => 'New',
            ]);
            $results['imported']++;
        }

        fclose($handle);
        return $results;
    }

    public function delete(int $id): bool
    {
        $statement = $this->db->prepare('DELETE FROM leads WHERE id = :id');
        return $statement->execute([':id' => $id]);
    }
}

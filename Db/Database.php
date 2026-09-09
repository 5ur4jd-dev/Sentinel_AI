<?php

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../config.php';
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['DB_HOST'], $config['DB_NAME']);
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            try {
                self::$instance = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], $options);
            } catch (PDOException $e) {
                // MySQL unavailable, fall back to SQLite
                $sqlitePath = __DIR__ . '/../storage/database.sqlite';
                $dbDir = dirname($sqlitePath);
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0777, true);
                }
                $dbExists = file_exists($sqlitePath);
                self::$instance = new PDO('sqlite:' . $sqlitePath, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                if (!$dbExists) {
                    self::initializeSqliteSchema(self::$instance);
                }
            }
            self::migrateLegacyData(self::$instance);
        }

        return self::$instance;
    }

    private static function migrateLegacyData(PDO $db): void
    {
        // These records were created by the old prompt-only discovery flow.
        // They have no search evidence, so they must not appear as active leads.
        $db->exec("UPDATE leads SET status = 'Archived', source = 'Legacy unverified discovery' WHERE source LIKE 'Discovery Engine (%'");
    }

    private static function initializeSqliteSchema(PDO $db): void
    {
        $queries = [
            "CREATE TABLE IF NOT EXISTS leads (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                business_name TEXT NOT NULL,
                website_url TEXT NOT NULL,
                contact_name TEXT,
                contact_email TEXT,
                contact_phone TEXT,
                notes TEXT,
                source TEXT,
                status TEXT NOT NULL DEFAULT 'New',
                industry TEXT,
                location TEXT,
                score INTEGER DEFAULT 0,
                qualification_details TEXT,
                handover_status TEXT NOT NULL DEFAULT 'None',
                handover_summary TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS audits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_id INTEGER NOT NULL UNIQUE,
                website_snapshot TEXT,
                audit_json TEXT,
                summary TEXT,
                status TEXT NOT NULL DEFAULT 'Pending',
                error_message TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS outreach_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_id INTEGER NOT NULL UNIQUE,
                tone TEXT NOT NULL DEFAULT 'formal',
                message TEXT,
                status TEXT NOT NULL DEFAULT 'AI Generated',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS conversations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_id INTEGER NOT NULL,
                sender TEXT NOT NULL,
                message TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
            )",
            "CREATE TABLE IF NOT EXISTS ai_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lead_id INTEGER,
                provider TEXT NOT NULL,
                endpoint TEXT NOT NULL,
                prompt TEXT NOT NULL,
                response TEXT,
                success INTEGER NOT NULL DEFAULT 0,
                error_message TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ];

        foreach ($queries as $query) {
            $db->exec($query);
        }
    }
}

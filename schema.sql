-- Sentinel AI Autonomous SDR Schema

CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_name VARCHAR(255) NOT NULL,
    website_url VARCHAR(2083) NOT NULL,
    contact_name VARCHAR(255),
    contact_email VARCHAR(255),
    contact_phone VARCHAR(50),
    notes TEXT,
    source VARCHAR(255),
    status ENUM('New','Analyzing','Analyzed','Outreach Drafted','Outreach Sent','In Conversation','Qualified','Archived') NOT NULL DEFAULT 'New',
    industry VARCHAR(255),
    location VARCHAR(255),
    score INT DEFAULT 0,
    qualification_details TEXT,
    handover_status ENUM('None','Pending','Completed') NOT NULL DEFAULT 'None',
    handover_summary TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS audits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    website_snapshot LONGTEXT,
    audit_json LONGTEXT,
    summary TEXT,
    status ENUM('Pending','Complete','Error') NOT NULL DEFAULT 'Pending',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    UNIQUE KEY uq_audits_lead_id (lead_id)
);

CREATE TABLE IF NOT EXISTS outreach_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    tone ENUM('formal','casual','friendly') NOT NULL DEFAULT 'formal',
    message TEXT,
    status ENUM('AI Generated','Owner Edited','Owner Approved','Sent') NOT NULL DEFAULT 'AI Generated',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    UNIQUE KEY uq_outreach_lead_id (lead_id)
);

CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    sender ENUM('prospect','sdr') NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ai_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT,
    provider VARCHAR(50) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    prompt LONGTEXT NOT NULL,
    response LONGTEXT,
    success TINYINT(1) NOT NULL DEFAULT 0,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Useful for local development and prevents the discovery provider from
-- creating duplicate records for the same website.
CREATE INDEX idx_leads_website_url ON leads (website_url(255));

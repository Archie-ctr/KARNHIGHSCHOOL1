-- ============================================================
-- ICT OFFICER PORTAL MIGRATION
-- Creates tables for IT asset management, support tickets,
-- network devices, and maintenance logs.
--
-- ACCESS RESTRICTION NOTE:
-- The ict_officer role MUST NOT have SELECT access to:
--   - assessment_scores, annual_results (academic marks)
--   - student_health_records (health data)
--   - library_transactions.fine_amount (financial detail)
--   - payments, fee_structures, expense_requests (finance)
-- These restrictions are enforced at the PHP portal level —
-- portal/ict/ pages never query those tables.
-- ============================================================

-- 1. ICT Assets ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ict_assets (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  asset_id        VARCHAR(40)  NOT NULL UNIQUE,   -- e.g. KHS-PC-001
  asset_type      VARCHAR(40)  NOT NULL,
  -- Desktop|Laptop|Printer|Projector|Server|Router|Switch|
  -- Access Point|UPS|Monitor|Tablet|Camera|Other
  brand           VARCHAR(80)  NULL,
  model           VARCHAR(100) NULL,
  serial_number   VARCHAR(80)  NULL,
  purchase_date   DATE         NULL,
  purchase_price  DECIMAL(10,2) NULL,
  warranty_expiry DATE         NULL,
  supplier        VARCHAR(120) NULL,
  assigned_to_type VARCHAR(20) NULL,   -- user|room|lab|store
  assigned_to_id  INT UNSIGNED NULL,   -- user.id if user-assigned
  assigned_to_name VARCHAR(150) NULL,  -- free-text fallback
  location        VARCHAR(120) NULL,   -- room/building
  condition_status VARCHAR(30) NOT NULL DEFAULT 'Good',
  -- Good|Fair|Needs Repair|Damaged|Decommissioned
  os_installed    VARCHAR(80)  NULL,
  software_notes  TEXT         NULL,
  ip_address      VARCHAR(45)  NULL,
  mac_address     VARCHAR(20)  NULL,
  notes           TEXT         NULL,
  status          VARCHAR(20)  NOT NULL DEFAULT 'active',
  -- active|in_repair|decommissioned|lost|reserved
  added_by        INT UNSIGNED NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NULL     ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ict_type   (asset_type),
  INDEX idx_ict_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Asset Maintenance Log ───────────────────────────────────
CREATE TABLE IF NOT EXISTS ict_maintenance_log (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  asset_id        INT UNSIGNED NOT NULL,
  maintenance_type VARCHAR(40) NOT NULL,
  -- repair|service|upgrade|replacement|inspection|cleaning
  description     TEXT         NOT NULL,
  performed_by    VARCHAR(120) NULL,   -- technician name / vendor
  performed_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cost            DECIMAL(8,2) NULL,
  next_service_date DATE       NULL,
  status          VARCHAR(20)  NOT NULL DEFAULT 'completed',
  logged_by       INT UNSIGNED NOT NULL,
  FOREIGN KEY (asset_id) REFERENCES ict_assets(id) ON DELETE CASCADE,
  INDEX idx_maint_asset (asset_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Support Tickets ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ict_tickets (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_ref      VARCHAR(20)  NOT NULL UNIQUE,  -- e.g. TKT-2026-0001
  category        VARCHAR(40)  NOT NULL,
  -- Hardware|Software|Network|Account|Email|Printer|Other
  subject         VARCHAR(200) NOT NULL,
  description     TEXT         NOT NULL,
  priority        VARCHAR(10)  NOT NULL DEFAULT 'medium',
  -- low|medium|high|critical
  reported_by     INT UNSIGNED NOT NULL,  -- users.id
  reporter_name   VARCHAR(120) NULL,      -- denormalized for speed
  reporter_role   VARCHAR(40)  NULL,
  assigned_to     INT UNSIGNED NULL,      -- ict_officer users.id
  related_asset_id INT UNSIGNED NULL,
  status          VARCHAR(20)  NOT NULL DEFAULT 'open',
  -- open|in_progress|pending_parts|resolved|closed
  resolution_notes TEXT        NULL,
  opened_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NULL     ON UPDATE CURRENT_TIMESTAMP,
  resolved_at     DATETIME     NULL,
  closed_at       DATETIME     NULL,
  INDEX idx_tkt_status   (status),
  INDEX idx_tkt_priority (priority),
  INDEX idx_tkt_reporter (reported_by),
  FOREIGN KEY (related_asset_id) REFERENCES ict_assets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Ticket Comments ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ict_ticket_comments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id   INT UNSIGNED NOT NULL,
  comment     TEXT         NOT NULL,
  is_internal TINYINT(1)   NOT NULL DEFAULT 0,  -- internal note vs public reply
  added_by    INT UNSIGNED NOT NULL,
  added_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES ict_tickets(id) ON DELETE CASCADE,
  INDEX idx_tc_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Network Devices ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ict_network_devices (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_name     VARCHAR(120) NOT NULL,
  device_type     VARCHAR(40)  NOT NULL,
  -- Router|Switch|Access Point|Firewall|Server|Modem|Other
  ip_address      VARCHAR(45)  NULL,
  mac_address     VARCHAR(20)  NULL,
  location        VARCHAR(120) NULL,
  brand           VARCHAR(80)  NULL,
  model           VARCHAR(100) NULL,
  firmware_version VARCHAR(50) NULL,
  ssid            VARCHAR(100) NULL,   -- for Wi-Fi APs
  status          VARCHAR(20)  NOT NULL DEFAULT 'online',
  -- online|offline|degraded|maintenance|unknown
  last_seen       DATETIME     NULL,
  uptime_note     VARCHAR(200) NULL,
  notes           TEXT         NULL,
  added_by        INT UNSIGNED NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_nd_type   (device_type),
  INDEX idx_nd_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Password Reset Requests (user support) ──────────────────
CREATE TABLE IF NOT EXISTS ict_password_requests (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  reason      VARCHAR(200) NULL,
  requested_by INT UNSIGNED NOT NULL,  -- who submitted the request (could be same)
  handled_by  INT UNSIGNED NULL,
  status      VARCHAR(20)  NOT NULL DEFAULT 'pending',
  -- pending|completed|rejected
  notes       TEXT         NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  handled_at  DATETIME     NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_pr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. ticket_ref is generated in PHP (format: TKT-YYYY-NNNN)

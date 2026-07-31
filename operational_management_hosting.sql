-- =====================================================================
-- قاعدة بيانات الملف التشغيلي — هيئة الهلال الأحمر السعودي
-- نسخة الاستضافة (InfinityFree): اختر قاعدتك في phpMyAdmin ثم استورد هذا الملف
-- =====================================================================


-- =============================
-- المستخدمون
-- =============================

CREATE TABLE IF NOT EXISTS `system_users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(60) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(120) NULL,
  `department` VARCHAR(120) NULL DEFAULT 'الكل',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `operational_users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(60) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `department` VARCHAR(120) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================
-- الجداول العامة
-- =============================

CREATE TABLE IF NOT EXISTS `operational_entries` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `report_year` SMALLINT NULL,
  `report_month` TINYINT NULL,
  `department` VARCHAR(150) NULL,
  `section_name` VARCHAR(150) NULL,
  `title` VARCHAR(255) NULL,
  `value_1` LONGTEXT NULL,
  `notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `operational_notes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `department` VARCHAR(150) NOT NULL,
  `note_text` TEXT NULL,
  `message` TEXT NULL,
  `department_reply` TEXT NULL,
  `replied_at` DATETIME NULL,
  `is_read` TINYINT(1) NULL DEFAULT 0,
  `recommendation_status` VARCHAR(50) NULL DEFAULT 'لا',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================
-- إدارة القطاعات
-- =============================

CREATE TABLE IF NOT EXISTS `operational_sectors_daily` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `department` VARCHAR(120) NOT NULL DEFAULT 'إدارة القطاعات',
  `data_json` LONGTEXT NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================
-- إدارة الشؤون الطبية
-- =============================

CREATE TABLE IF NOT EXISTS `operational_medical_daily` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `department` VARCHAR(120) NULL,
  `report_date` DATE NULL,
  `trauma_path` TEXT NULL,
  `ecg` TEXT NULL,
  `aspirin` TEXT NULL,
  `cath_cases` TEXT NULL,
  `stroke` TEXT NULL,
  `occupational_health` TEXT NULL,
  `cpr` TEXT NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `operational_medical_cardiac` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `daily_id` INT UNSIGNED NULL,
  `team_type` VARCHAR(150) NULL,
  `center` VARCHAR(150) NULL,
  `protocol_applied` VARCHAR(150) NULL,
  `teams_count` VARCHAR(50) NULL,
  `rosc` VARCHAR(150) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `operational_medical_trauma` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `daily_id` INT UNSIGNED NULL,
  `center` VARCHAR(150) NULL,
  `case_classification` VARCHAR(200) NULL,
  `hospital` VARCHAR(200) NULL,
  `reason` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `medical_oxygen` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `large_total` VARCHAR(50) NULL, `large_filled` VARCHAR(50) NULL, `large_empty` VARCHAR(50) NULL,
  `small_total` VARCHAR(50) NULL, `small_filled` VARCHAR(50) NULL, `small_empty` VARCHAR(50) NULL,
  `total_requests_month` VARCHAR(50) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `medical_temperatures` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `location_type` VARCHAR(80) NULL,
  `location_name` VARCHAR(200) NULL,
  `temp_c` VARCHAR(50) NULL,
  `humidity_pct` VARCHAR(50) NULL,
  `notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `medical_tech_support` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `new_count` VARCHAR(50) NULL, `in_progress` VARCHAR(50) NULL, `done` VARCHAR(50) NULL,
  `total_month` VARCHAR(50) NULL,
  `ticket_num` VARCHAR(100) NULL,
  `device_class` VARCHAR(150) NULL,
  `action_taken` TEXT NULL,
  `ticket_status` VARCHAR(100) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `medical_recommendations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `rec_main` TEXT NULL, `rec_risks` TEXT NULL, `rec_actions` TEXT NULL, `rec_notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================
-- تشغيل وصيانة الأسطول
-- =============================

CREATE TABLE IF NOT EXISTS `fleet_daily_reports` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `department` VARCHAR(120) NULL,
  `data_json` LONGTEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fleet_distribution` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `amb_total` VARCHAR(50) NULL, `service_total` VARCHAR(50) NULL, `spec_total` VARCHAR(50) NULL,
  `fourwd_total` VARCHAR(50) NULL, `broken_amb` VARCHAR(50) NULL, `broken_fourwd` VARCHAR(50) NULL,
  `broken_total` VARCHAR(50) NULL, `maint_done` VARCHAR(50) NULL, `maint_active` VARCHAR(50) NULL,
  `outside_total` VARCHAR(50) NULL, `outside_riyadh` VARCHAR(50) NULL, `outside_mecca` VARCHAR(50) NULL,
  `backup_total` VARCHAR(50) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fleet_maintenance` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `veh_type` VARCHAR(100) NULL, `veh_num` VARCHAR(100) NULL, `veh_class` VARCHAR(100) NULL,
  `description` TEXT NULL, `location` VARCHAR(200) NULL, `action_taken` TEXT NULL,
  `request_date` VARCHAR(50) NULL, `days_out` VARCHAR(50) NULL, `readiness` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fleet_service_vehicles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `veh_type` VARCHAR(100) NULL, `veh_num` VARCHAR(100) NULL, `veh_class` VARCHAR(100) NULL,
  `description` TEXT NULL, `location` VARCHAR(200) NULL, `action_taken` TEXT NULL,
  `request_date` VARCHAR(50) NULL, `days_out` VARCHAR(50) NULL, `readiness` VARCHAR(100) NULL,
  `notes` TEXT NULL, `status` VARCHAR(100) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fleet_tech_support` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ticket_num` VARCHAR(100) NULL, `department` VARCHAR(150) NULL, `description` TEXT NULL,
  `veh_card` VARCHAR(100) NULL, `model` VARCHAR(100) NULL, `frame_num` VARCHAR(100) NULL,
  `submit_time` VARCHAR(80) NULL, `status` VARCHAR(100) NULL, `close_time` VARCHAR(80) NULL,
  `rating` VARCHAR(50) NULL, `request_type` VARCHAR(150) NULL, `action_taken` TEXT NULL,
  `completion_method` VARCHAR(150) NULL, `reviewed_by` VARCHAR(150) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fleet_budget` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `requests_count` VARCHAR(50) NULL, `actual_budget` VARCHAR(100) NULL,
  `total_budget` VARCHAR(100) NULL, `spending` VARCHAR(100) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fleet_recommendations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `rec_main` TEXT NULL, `rec_risks` TEXT NULL, `rec_actions` TEXT NULL, `rec_notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================
-- تشغيل وصيانة المرافق
-- =============================

CREATE TABLE IF NOT EXISTS `ops_statistics` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `total` VARCHAR(50) NULL, `active` VARCHAR(50) NULL, `done` VARCHAR(50) NULL,
  `date_from` VARCHAR(50) NULL, `date_to` VARCHAR(50) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ops_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ticket_num` VARCHAR(100) NULL, `location` VARCHAR(200) NULL, `request_date` VARCHAR(50) NULL,
  `status` VARCHAR(100) NULL, `duration` VARCHAR(80) NULL, `completion_date` VARCHAR(50) NULL,
  `notes` TEXT NULL, `request_type` VARCHAR(150) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ops_recommendations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `rec_main` TEXT NULL, `rec_risks` TEXT NULL, `rec_actions` TEXT NULL, `rec_notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================
-- الخدمات التقنية
-- =============================

CREATE TABLE IF NOT EXISTS `tech_support_devices` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `device_type` VARCHAR(150) NULL, `fault` TEXT NULL, `location` VARCHAR(200) NULL,
  `action_taken` TEXT NULL, `notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tech_support_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `classification` VARCHAR(150) NULL, `requester` VARCHAR(150) NULL, `department` VARCHAR(150) NULL,
  `description` TEXT NULL, `status` VARCHAR(100) NULL, `request_date` VARCHAR(50) NULL,
  `notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tech_communication_lines` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `line_name` VARCHAR(200) NULL, `line_status` VARCHAR(100) NULL, `notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tech_operational_devices` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `device_name` VARCHAR(200) NULL, `total_count` VARCHAR(50) NULL, `working` VARCHAR(50) NULL,
  `backup` VARCHAR(50) NULL, `broken` VARCHAR(50) NULL, `operational_pct` VARCHAR(50) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tech_recommendations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `rec_main` TEXT NULL, `rec_risks` TEXT NULL, `rec_actions` TEXT NULL, `rec_notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================
-- إدارة التطوع
-- =============================

CREATE TABLE IF NOT EXISTS `volunteer_stats` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `total_volunteers` VARCHAR(50) NULL, `participation_rate` VARCHAR(50) NULL,
  `total_hours` VARCHAR(50) NULL, `efficiency_pct` VARCHAR(50) NULL,
  `jan` VARCHAR(50) NULL, `feb` VARCHAR(50) NULL, `mar` VARCHAR(50) NULL, `apr` VARCHAR(50) NULL,
  `may` VARCHAR(50) NULL, `jun` VARCHAR(50) NULL, `jul` VARCHAR(50) NULL, `aug` VARCHAR(50) NULL,
  `sep` VARCHAR(50) NULL, `oct` VARCHAR(50) NULL, `nov` VARCHAR(50) NULL, `dec_m` VARCHAR(50) NULL,
  `total_participants` VARCHAR(50) NULL, `avg_rate` VARCHAR(50) NULL, `period_total` VARCHAR(50) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `volunteer_activities` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `program_type` VARCHAR(150) NULL, `program_name` VARCHAR(200) NULL, `total` VARCHAR(50) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `volunteer_diversity` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ambulance` VARCHAR(50) NULL, `organizing` VARCHAR(50) NULL, `humanitarian` VARCHAR(50) NULL,
  `environment` VARCHAR(50) NULL, `media` VARCHAR(50) NULL, `administrative` VARCHAR(50) NULL,
  `total_pct` VARCHAR(50) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `volunteer_recommendations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `rec_main` TEXT NULL, `rec_risks` TEXT NULL, `rec_actions` TEXT NULL, `rec_notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================
-- إدارة الالتزام
-- =============================

CREATE TABLE IF NOT EXISTS `compliance_complaints` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `day` VARCHAR(50) NULL, `report_date` VARCHAR(50) NULL, `period` VARCHAR(80) NULL,
  `receive_date` VARCHAR(50) NULL, `ticket_num` VARCHAR(100) NULL, `category` VARCHAR(150) NULL,
  `sub_category` VARCHAR(150) NULL, `center` VARCHAR(150) NULL, `complainant` VARCHAR(150) NULL,
  `ticket_status` VARCHAR(100) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `compliance_violations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `subject` TEXT NULL, `receive_date` VARCHAR(50) NULL, `violation_status` VARCHAR(100) NULL,
  `raise_date` VARCHAR(50) NULL, `notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `compliance_recommendations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `rec_main` TEXT NULL, `rec_risks` TEXT NULL, `rec_actions` TEXT NULL, `rec_notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================
-- الإدارة القانونية
-- =============================

CREATE TABLE IF NOT EXISTS `legal_department_reports` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `report_year` SMALLINT NULL,
  `report_month` TINYINT NULL,
  `department` VARCHAR(150) NULL DEFAULT 'الإدارة القانونية',
  `total_transactions` INT NULL DEFAULT 0,
  `in_progress_transactions` INT NULL DEFAULT 0,
  `closed_or_returned_transactions` INT NULL DEFAULT 0,
  `data_json` LONGTEXT NULL,
  `notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `legal_department_transactions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `report_id` INT UNSIGNED NULL,
  `transaction_number` VARCHAR(100) NULL,
  `violation_subject` TEXT NULL,
  `last_update_date` VARCHAR(50) NULL,
  `update_text` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================
-- الاتصال المؤسسي / التموين / صوت الموظف
-- =============================

CREATE TABLE IF NOT EXISTS `corporate_communication_reports` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `department` VARCHAR(120) NULL,
  `data_json` LONGTEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `supply_daily_reports` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `department` VARCHAR(120) NULL,
  `data_json` LONGTEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_voice_reports` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `department` VARCHAR(120) NULL,
  `month_year` VARCHAR(20) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_voice_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `report_id` INT UNSIGNED NULL,
  `item_type` VARCHAR(150) NULL,
  `statement_text` TEXT NULL,
  `status` VARCHAR(100) NULL,
  `protection_notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- بيانات أولية: أدمن + مستخدم لكل قسم
-- كلمات المرور نصية مؤقتاً (النظام يدعمها) — غيّرها من صفحة المستخدمين
-- =====================================================================

INSERT INTO `system_users` (`username`,`password`,`full_name`,`department`,`is_active`) VALUES
('admin', 'admin123', 'مدير النظام', 'الكل', 1)
ON DUPLICATE KEY UPDATE `username`=`username`;

INSERT INTO `operational_users` (`full_name`,`username`,`password`,`department`,`is_active`) VALUES
('مستخدم الشؤون الطبية',   'medical01',    '1234', 'إدارة الشؤون الطبية', 1),
('مستخدم القطاعات',        'sectors01',    '1234', 'إدارة القطاعات', 1),
('مستخدم الطوارئ',         'emergency01',  '1234', 'إدارة الطوارئ', 1),
('مستخدم التطوع',          'volunteer01',  '1234', 'إدارة التطوع', 1),
('مستخدم التموين',         'supply01',     '1234', 'التموين الطبي والمستودعات', 1),
('مستخدم الأسطول',         'fleet01',      '1234', 'تشغيل وصيانة الأسطول', 1),
('مستخدم المرافق',         'facilities01', '1234', 'تشغيل وصيانة المرافق', 1),
('مستخدم الاتصال المؤسسي', 'comm01',       '1234', 'الاتصال المؤسسي', 1),
('مستخدم الخدمات التقنية', 'it01',         '1234', 'الوضع التشغيلي للخدمات التقنية', 1),
('مستخدم الالتزام',        'compliance01', '1234', 'إدارة الالتزام', 1),
('مستخدم القانونية',       'legal01',      '1234', 'الإدارة القانونية', 1),
('مستخدم صوت الموظف',      'employee01',   '1234', 'صوت الموظف', 1)
ON DUPLICATE KEY UPDATE `username`=`username`;

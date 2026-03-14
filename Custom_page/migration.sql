-- =========================================================
-- Migration: แยก hardsoft_logs → hardware_logs + software_logs
-- และอัปเดต master_equipment / master_tasks
-- =========================================================

-- 1. สร้าง hardware_logs
CREATE TABLE IF NOT EXISTS `hardware_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_name` varchar(255) DEFAULT NULL,
  `month` int(11) DEFAULT NULL,
  `year` int(11) DEFAULT 2026,
  `task_1` tinyint(1) DEFAULT NULL COMMENT 'Clean dust (6M)',
  `task_2` tinyint(1) DEFAULT NULL COMMENT 'Check cables and connections (6M)',
  `task_3` tinyint(1) DEFAULT NULL COMMENT 'Check battery health and lifespan (6M)',
  `task_4` tinyint(1) DEFAULT NULL COMMENT 'Check for hardware issues and software updates (6M)',
  `task_5` tinyint(1) DEFAULT NULL COMMENT 'Run disk cleanup and defragmentation (Y)',
  `task_6` tinyint(1) DEFAULT NULL COMMENT 'Clear temporary files, optimize disk (Y)',
  `task_7` tinyint(1) DEFAULT NULL COMMENT 'Update network device firmware (Y)',
  `last_updated` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `sub_by` varchar(100) DEFAULT NULL,
  `sub_at` datetime DEFAULT NULL,
  `app_by` varchar(100) DEFAULT NULL,
  `app_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. สร้าง software_logs
CREATE TABLE IF NOT EXISTS `software_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_name` varchar(255) DEFAULT NULL,
  `month` int(11) DEFAULT NULL,
  `year` int(11) DEFAULT 2026,
  `task_8` tinyint(1) DEFAULT NULL COMMENT 'Review and Check application updates (6M)',
  `task_9` tinyint(1) DEFAULT NULL COMMENT 'Window Update (6M)',
  `last_updated` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `sub_by` varchar(100) DEFAULT NULL,
  `sub_at` datetime DEFAULT NULL,
  `app_by` varchar(100) DEFAULT NULL,
  `app_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Copy ข้อมูลจาก hardsoft_logs → hardware_logs (task_1-7)
INSERT INTO hardware_logs (equipment_name, month, year, task_1, task_2, task_3, task_4, task_5, task_6, task_7, last_updated)
SELECT equipment_name, month, year, task_1, task_2, task_3, task_4, task_5, task_6, task_7, last_updated
FROM hardsoft_logs;

-- 4. Copy ข้อมูลจาก hardsoft_logs → software_logs (task_8-9)
INSERT INTO software_logs (equipment_name, month, year, task_8, task_9, last_updated)
SELECT equipment_name, month, year, task_8, task_9, last_updated
FROM hardsoft_logs;

-- 5. อัปเดต master_equipment: เปลี่ยน hardsoft → hardware และ software (duplicate rows)
-- เพิ่ม hardware
INSERT INTO master_equipment (system_type, equipment_name)
SELECT 'hardware', equipment_name FROM master_equipment WHERE system_type = 'hardsoft';

-- เพิ่ม software
INSERT INTO master_equipment (system_type, equipment_name)
SELECT 'software', equipment_name FROM master_equipment WHERE system_type = 'hardsoft';

-- 6. อัปเดต master_tasks
UPDATE master_tasks SET system_type = 'hardware' WHERE system_type = 'hardsoft' AND category = 'hardware';
UPDATE master_tasks SET system_type = 'software' WHERE system_type = 'hardsoft' AND category = 'software';

-- 7. ลบ hardsoft ออกจาก master_equipment (เก่า)
DELETE FROM master_equipment WHERE system_type = 'hardsoft';

-- หมายเหตุ: hardsoft_logs ยังคงอยู่ ลบได้ภายหลังเมื่อยืนยันข้อมูลถูกต้องแล้ว
-- DROP TABLE hardsoft_logs;

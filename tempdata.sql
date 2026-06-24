SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- 1. XÓA CÁC BẢNG NẾU ĐÃ TỒN TẠI (ĐÚNG THỨ TỰ RÀNG BUỘC)
-- ============================================================================
DROP TABLE IF EXISTS `books_catalog`;
DROP TABLE IF EXISTS `resources`;
DROP TABLE IF EXISTS `combined_topic_clos`;
DROP TABLE IF EXISTS `combined_topics`;
DROP TABLE IF EXISTS `practical_topic_clos`;
DROP TABLE IF EXISTS `practical_topics`;
DROP TABLE IF EXISTS `theory_topic_clos`;
DROP TABLE IF EXISTS `theory_topics`;
DROP TABLE IF EXISTS `self_study_clos`;
DROP TABLE IF EXISTS `self_study_activities`;
DROP TABLE IF EXISTS `assessment_tool_relation`;
DROP TABLE IF EXISTS `assessment_tools`;
DROP TABLE IF EXISTS `assessment_clos`;
DROP TABLE IF EXISTS `assessments`;
DROP TABLE IF EXISTS `clos`;
DROP TABLE IF EXISTS `plo_pi_relation`;
DROP TABLE IF EXISTS `pi`;
DROP TABLE IF EXISTS `plo`;
DROP TABLE IF EXISTS `module_relationships`;
DROP TABLE IF EXISTS `module_departments`;
DROP TABLE IF EXISTS `course_coordinators`;
DROP TABLE IF EXISTS `modules`;
DROP TABLE IF EXISTS `major_objectives`;
DROP TABLE IF EXISTS `education_programs`;
DROP TABLE IF EXISTS `facilities`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `knowledge_blocks`;
DROP TABLE IF EXISTS `majors`;
DROP TABLE IF EXISTS `assessment_forms`;
DROP TABLE IF EXISTS `faculties_list`;
DROP TABLE IF EXISTS `departments_list`;
DROP TABLE IF EXISTS `lecturers`;

-- ============================================================================
-- 2. CẤU TRÚC CÁC BẢNG CSDL (DATABASE SCHEMA)
-- ============================================================================

CREATE TABLE `assessment_forms` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `majors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `education_programs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `major_id` INT NOT NULL,
  `year` YEAR NOT NULL,
  FOREIGN KEY (`major_id`) REFERENCES `majors`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_major_year` (`major_id`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `major_objectives` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `education_program_id` INT NOT NULL,
  `general_objective` TEXT NULL,
  `po_content` TEXT NULL,
  `plo_content` TEXT NULL,
  FOREIGN KEY (`education_program_id`) REFERENCES `education_programs`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_program_objectives` (`education_program_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `knowledge_blocks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `major_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `parent_id` INT NULL,
  FOREIGN KEY (`major_id`) REFERENCES `majors`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_id`) REFERENCES `knowledge_blocks`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `courses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `major_id` INT NOT NULL,
  `block_id` INT NULL DEFAULT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(255) NOT NULL,
  `total_hours` INT DEFAULT 0,
  `theory_hours` INT DEFAULT 0,
  `practical_hours` INT DEFAULT 0,
  `sort_order` INT DEFAULT 0,
  FOREIGN KEY (`major_id`) REFERENCES `majors`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`block_id`) REFERENCES `knowledge_blocks`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `faculties_list` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `departments_list` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `modules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT NULL,
  `education_program_id` INT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(255) NOT NULL,
  `type` ENUM('Không', 'Bắt buộc', 'Điều kiện', 'Tự chọn') NOT NULL DEFAULT 'Không',
  `teaching_mode` ENUM('Học trên lớp', 'Học trực tuyến', 'Kết hợp') NOT NULL DEFAULT 'Học trên lớp',
  `credits` INT NOT NULL DEFAULT 0,
  `credits_theory` INT NOT NULL DEFAULT 0,
  `credits_practice` INT NOT NULL DEFAULT 0,
  `total_hours` INT NOT NULL DEFAULT 0,
  `theory_hours` INT NOT NULL DEFAULT 0,
  `practical_hours` INT NOT NULL DEFAULT 0,
  `self_study_hours` INT NOT NULL DEFAULT 0,
  `target_programs` TEXT NULL,
  `expected_semester` VARCHAR(50) NULL,
  `expected_year` VARCHAR(50) NULL,
  `prerequisite_modules` TEXT NULL,
  `parallel_modules` TEXT NULL,
  `previous_modules` TEXT NULL,
  `department_in_charge` VARCHAR(255) NULL,
  `coordinating_board` VARCHAR(255) NULL,
  `faculty_in_charge` VARCHAR(255) NULL,
  `description` TEXT NULL,
  `objectives` TEXT NULL,
  `grading_scale` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `faculty_id` INT NULL,
  FOREIGN KEY (`faculty_id`) REFERENCES `faculties_list`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`education_program_id`) REFERENCES `education_programs`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `module_relationships` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module_id` INT NOT NULL,
  `related_course_id` INT NOT NULL,
  `relation_type` ENUM('Tiên quyết', 'Song hành', 'Học trước') NOT NULL,
  FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`related_course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_relation` (`module_id`, `related_course_id`, `relation_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `module_departments` (
  `module_id` INT NOT NULL,
  `department_id` INT NOT NULL,
  PRIMARY KEY (`module_id`, `department_id`),
  FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`department_id`) REFERENCES `departments_list`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `lecturers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `course_coordinators` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module_id` INT NOT NULL,
  `lecturer_id` INT NOT NULL,
  FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_module_lecturer` (`module_id`, `lecturer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `plo` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `content` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `content` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `plo_pi_relation` (
  `plo_id` INT NOT NULL,
  `pi_id` INT NOT NULL,
  PRIMARY KEY (`plo_id`, `pi_id`),
  FOREIGN KEY (`plo_id`) REFERENCES `plo`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`pi_id`) REFERENCES `pi`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module_id` INT NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `content` TEXT NOT NULL,
  `domain` VARCHAR(255) NULL,
  `bloom_level` VARCHAR(255) NULL,
  `contribution_level` VARCHAR(50) NULL,
  `plo_id` INT NULL,
  `pi_id` INT NULL,
  FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`plo_id`) REFERENCES `plo`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`pi_id`) REFERENCES `pi`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `unique_module_clo` (`module_id`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `assessments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module_id` INT NOT NULL,
  `type` ENUM('Chuyên cần', 'Kiểm tra thường xuyên', 'Thi kết thúc') NOT NULL,
  `component` VARCHAR(255) NULL,
  `clos_text` TEXT NULL,
  `form` VARCHAR(255) NOT NULL,
  `weight` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `assessment_form_id` INT NULL,
  FOREIGN KEY (`assessment_form_id`) REFERENCES `assessment_forms`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `assessment_tools` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `assessment_form` ENUM('Chuyên cần', 'Kiểm tra thường xuyên', 'Thi kết thúc') NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  UNIQUE KEY `unique_assessment_tool` (`assessment_form`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `assessment_tool_relation` (
  `assessment_id` INT NOT NULL,
  `assessment_tool_id` INT NOT NULL,
  PRIMARY KEY (`assessment_id`, `assessment_tool_id`),
  FOREIGN KEY (`assessment_id`) REFERENCES `assessments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assessment_tool_id`) REFERENCES `assessment_tools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `assessment_clos` (
  `assessment_id` INT NOT NULL,
  `clo_id` INT NOT NULL,
  PRIMARY KEY (`assessment_id`, `clo_id`),
  FOREIGN KEY (`assessment_id`) REFERENCES `assessments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`clo_id`) REFERENCES `clos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `facilities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `self_study_activities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module_id` INT NOT NULL,
  `activity_name` TEXT NOT NULL,
  `clos_text` TEXT NULL,
  `duration_hours` INT DEFAULT 0,
  `method` TEXT NULL,
  `assessment_method` TEXT NULL,
  `evidence` TEXT NULL,
  FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `self_study_clos` (
  `self_study_activity_id` INT NOT NULL,
  `clo_id` INT NOT NULL,
  PRIMARY KEY (`self_study_activity_id`, `clo_id`),
  FOREIGN KEY (`self_study_activity_id`) REFERENCES `self_study_activities`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`clo_id`) REFERENCES `clos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `theory_topics` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module_id` INT NOT NULL,
  `parent_id` INT NULL,
  `chapter` VARCHAR(100) NULL,
  `title` TEXT NOT NULL,
  `delivery_mode` ENUM('in_person', 'online', 'hybrid') NULL,
  `method` VARCHAR(255) NULL,
  `class_hours` INT DEFAULT 0,
  `self_study_hours` INT DEFAULT 0,
  `online_hours` INT DEFAULT 0,
  `teaching_method` VARCHAR(255) NULL,
  `clos_text` TEXT NULL,
  FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_id`) REFERENCES `theory_topics`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `theory_topic_clos` (
  `theory_topic_id` INT NOT NULL,
  `clo_id` INT NOT NULL,
  PRIMARY KEY (`theory_topic_id`, `clo_id`),
  FOREIGN KEY (`theory_topic_id`) REFERENCES `theory_topics`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`clo_id`) REFERENCES `clos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `practical_topics` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module_id` INT NOT NULL,
  `parent_id` INT NULL,
  `topic` VARCHAR(255) NULL,
  `content` TEXT NOT NULL,
  `delivery_mode` ENUM('in_person', 'online', 'hybrid') NULL,
  `method` VARCHAR(255) NULL,
  `lab_hours` INT DEFAULT 0,
  `online_hours` INT DEFAULT 0,
  `teaching_method` VARCHAR(255) NULL,
  `clos_text` TEXT NULL,
  `facility_id` INT NULL,
  FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_id`) REFERENCES `practical_topics`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`facility_id`) REFERENCES `facilities`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `practical_topic_clos` (
  `practical_topic_id` INT NOT NULL,
  `clo_id` INT NOT NULL,
  PRIMARY KEY (`practical_topic_id`, `clo_id`),
  FOREIGN KEY (`practical_topic_id`) REFERENCES `practical_topics`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`clo_id`) REFERENCES `clos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `combined_topics` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module_id` INT NOT NULL,
  `parent_id` INT NULL,
  `sort_order` INT DEFAULT 1,
  `content` TEXT NOT NULL,
  `delivery_mode` ENUM('in_person', 'online', 'hybrid') NULL,
  `method` VARCHAR(255) NULL,
  `theory_hours` INT DEFAULT 0,
  `practical_hours` INT DEFAULT 0,
  `online_hours` INT DEFAULT 0,
  `self_study_hours` INT DEFAULT 0,
  `teaching_method` VARCHAR(255) NULL,
  `clos_text` TEXT NULL,
  `facility_id` INT NULL,
  FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_id`) REFERENCES `combined_topics`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`facility_id`) REFERENCES `facilities`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `combined_topic_clos` (
  `combined_topic_id` INT NOT NULL,
  `clo_id` INT NOT NULL,
  PRIMARY KEY (`combined_topic_id`, `clo_id`),
  FOREIGN KEY (`combined_topic_id`) REFERENCES `combined_topics`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`clo_id`) REFERENCES `clos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `books_catalog` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `editor` VARCHAR(255) NULL,
  `publisher` VARCHAR(255) NULL,
  `year` VARCHAR(50) NULL,
  `identifier` VARCHAR(100) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `resources` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module_id` INT NOT NULL,
  `resource_type` ENUM('Tài liệu giảng dạy', 'Tài liệu tự học') NOT NULL,
  `sort_order` INT DEFAULT 1,
  `title` VARCHAR(255) NOT NULL,
  `editor` VARCHAR(255) NULL,
  `publisher` VARCHAR(255) NULL,
  `year` VARCHAR(50) NULL,
  `identifier` VARCHAR(100) NULL,
  `book_id` INT NULL,
  FOREIGN KEY (`book_id`) REFERENCES `books_catalog`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- 3. CHÈN 10 MẪU DỮ LIỆU TEST CHO TỪNG BẢNG (ĐÚNG RÀNG BUỘC FK)
-- ============================================================================

-- Bảng 1: assessment_forms
INSERT INTO `assessment_forms` (`id`, `name`) VALUES
(1, 'Chuyên cần'), (2, 'Kiểm tra thường xuyên'), (3, 'Bài tập nhóm'), (4, 'OSCE/OSPE'), (5, 'Thi viết'),
(6, 'Thi trắc nghiệm'), (7, 'Báo cáo tiểu luận'), (8, 'Thuyết trình'), (9, 'Đánh giá đồ án'), (10, 'Nhật ký thực hành');

-- Bảng 2: majors
INSERT INTO `majors` (`id`, `name`) VALUES
(1, 'Y khoa'), (2, 'Dược học'), (3, 'Điều dưỡng'), (4, 'Y học dự phòng'), (5, 'Kỹ thuật xét nghiệm y học'),
(6, 'Kỹ thuật hình ảnh y học'), (7, 'Răng - Hàm - Mặt'), (8, 'Y học cổ truyền'), (9, 'Phục hồi chức năng'), (10, 'Y tế công cộng');

-- Bảng 3: education_programs
INSERT INTO `education_programs` (`id`, `major_id`, `year`) VALUES
(1, 1, 2026), (2, 2, 2026), (3, 3, 2026), (4, 4, 2026), (5, 5, 2026),
(6, 6, 2026), (7, 7, 2026), (8, 8, 2026), (9, 9, 2026), (10, 10, 2026);

-- Bảng 4: major_objectives
INSERT INTO `major_objectives` (`id`, `education_program_id`, `general_objective`, `po_content`, `plo_content`) VALUES
(1, 1, 'Mục tiêu đào tạo Y khoa chất lượng cao', 'PO1, PO2 Y khoa', 'PLO1, PLO2 Y khoa'),
(2, 2, 'Mục tiêu Dược học chuẩn quốc gia', 'PO1, PO2 Dược', 'PLO1, PLO2 Dược'),
(3, 3, 'Đạt năng lực chăm sóc toàn diện', 'PO1, PO2 Điều dưỡng', 'PLO1, PLO2 Điều dưỡng'),
(4, 4, 'Bảo vệ sức khỏe cộng đồng chủ động', 'PO Dự phòng', 'PLO Dự phòng'),
(5, 5, 'Làm chủ các thiết bị xét nghiệm tiên tiến', 'PO Xét nghiệm', 'PLO Xét nghiệm'),
(6, 6, 'Hình ảnh chẩn đoán chuẩn xác', 'PO Hình ảnh', 'PLO Hình ảnh'),
(7, 7, 'Bảo tồn và phục hồi chức năng răng hàm mặt', 'PO Răng Hàm Mặt', 'PLO Răng Hàm Mặt'),
(8, 8, 'Kế thừa và phát triển y học cổ truyền', 'PO Cổ truyền', 'PLO Cổ truyền'),
(9, 9, 'Phục hồi chức năng tối ưu cho bệnh nhân', 'PO Phục hồi', 'PLO Phục hồi'),
(10, 10, 'Quản lý chính sách và dịch tễ y tế', 'PO Công cộng', 'PLO Công cộng');

-- Bảng 5: knowledge_blocks
INSERT INTO `knowledge_blocks` (`id`, `major_id`, `name`, `parent_id`) VALUES
(1, 1, 'Kiến thức đại cương khối ngành', NULL),
(2, 1, 'Kiến thức cơ sở ngành Y khoa', NULL),
(3, 1, 'Kiến thức chuyên ngành Y lâm sàng', NULL),
(4, 2, 'Kiến thức cơ sở ngành Dược học', NULL),
(5, 2, 'Kiến thức chuyên ngành Dược lâm sàng', NULL),
(6, 3, 'Kiến thức cơ sở ngành Điều dưỡng', NULL),
(7, 3, 'Kiến thức chuyên ngành chăm sóc', NULL),
(8, 4, 'Kiến thức dịch tễ cơ sở', NULL),
(9, 5, 'Kiến thức kỹ thuật xét nghiệm cốt lõi', NULL),
(10, 7, 'Kiến thức nha khoa cơ sở', NULL);

-- Bảng 6: courses
INSERT INTO `courses` (`id`, `major_id`, `block_id`, `code`, `name`, `total_hours`, `theory_hours`, `practical_hours`, `sort_order`) VALUES
(1, 1, 2, 'C001', 'Giải phẫu học đại cương', 45, 30, 15, 1),
(2, 1, 2, 'C002', 'Sinh lý học đại cương', 45, 30, 15, 2),
(3, 1, 3, 'C003', 'Bệnh học cơ sở', 45, 35, 10, 3),
(4, 1, 3, 'C004', 'Kỹ năng khám lâm sàng', 60, 25, 35, 4),
(5, 2, 4, 'C005', 'Hóa dược cơ bản', 45, 30, 15, 5),
(6, 2, 5, 'C006', 'Dược lý lâm sàng', 60, 45, 15, 6),
(7, 2, 5, 'C007', 'Quản lý cung ứng thuốc', 30, 20, 10, 7),
(8, 3, 6, 'C008', 'Điều dưỡng cơ bản', 60, 25, 35, 8),
(9, 3, 7, 'C009', 'Chăm sóc người bệnh nội khoa', 60, 30, 30, 9),
(10, 1, 1, 'C010', 'Tin học ứng dụng y học', 45, 20, 25, 10);

-- Bảng 7: faculties_list
INSERT INTO `faculties_list` (`id`, `name`) VALUES
(1, 'Khoa Y'), (2, 'Khoa Dược'), (3, 'Khoa Điều dưỡng'), (4, 'Khoa Khoa học cơ bản'), (5, 'Khoa Y tế công cộng'),
(6, 'Khoa Y học cổ truyền'), (7, 'Khoa Răng Hàm Mặt'), (8, 'Khoa Kỹ thuật y học'), (9, 'Khoa Sau đại học'), (10, 'Khoa Đào tạo quốc tế');

-- Bảng 8: departments_list
INSERT INTO `departments_list` (`id`, `name`) VALUES
(1, 'Bộ môn Giải phẫu'), (2, 'Bộ môn Sinh lý'), (3, 'Bộ môn Bệnh học'), (4, 'Bộ môn Nội'), (5, 'Bộ môn Hóa dược'),
(6, 'Bộ môn Dược lý'), (7, 'Bộ môn Quản lý Dược'), (8, 'Bộ môn Điều dưỡng cơ bản'), (9, 'Bộ môn Điều dưỡng nội'), (10, 'Trung tâm CNTT');

-- Bảng 9: modules
INSERT INTO `modules` (`id`, `course_id`, `education_program_id`, `code`, `name`, `type`, `teaching_mode`, `credits`, `credits_theory`, `credits_practice`, `total_hours`, `theory_hours`, `practical_hours`, `self_study_hours`, `target_programs`, `expected_semester`, `expected_year`, `prerequisite_modules`, `parallel_modules`, `previous_modules`, `department_in_charge`, `coordinating_board`, `faculty_in_charge`, `description`, `objectives`, `grading_scale`, `faculty_id`) VALUES
(1, 1, 1, 'M001', 'Giải phẫu học đại cương', 'Bắt buộc', 'Học trên lớp', 3, 2, 1, 45, 30, 15, 60, 'Y khoa năm 1', 'Kỳ I', '2026', '', '', '', 'Bộ môn Giải phẫu', 'Ban cơ sở', 'Khoa Y', 'Mô tả cấu trúc cơ thể', 'Mục tiêu nắm vững hình thái', 'Thang điểm 10', 1),
(2, 2, 1, 'M002', 'Sinh lý học đại cương', 'Bắt buộc', 'Học trên lớp', 3, 2, 1, 45, 30, 15, 60, 'Y khoa năm 1', 'Kỳ II', '2026', '', '', '', 'Bộ môn Sinh lý', 'Ban cơ sở', 'Khoa Y', 'Mô tả cơ chế chức năng', 'Mục tiêu giải thích cơ chế sinh lý', 'Thang điểm 10', 1),
(3, 3, 1, 'M003', 'Bệnh học cơ sở', 'Bắt buộc', 'Học trên lớp', 3, 2, 1, 45, 35, 10, 70, 'Y khoa năm 2', 'Kỳ I', '2026', '', '', '', 'Bộ môn Bệnh học', 'Ban tiền lâm sàng', 'Khoa Y', 'Mô tả tổn thương tế bào', 'Mục tiêu nhận biết bệnh lý', 'Thang điểm 10', 1),
(4, 4, 1, 'M004', 'Kỹ năng khám lâm sàng', 'Bắt buộc', 'Học trên lớp', 4, 2, 2, 60, 25, 35, 80, 'Y khoa năm 3', 'Kỳ II', '2026', '', '', '', 'Bộ môn Nội', 'Ban lâm sàng', 'Khoa Y', 'Mô tả kỹ thuật khám bệnh', 'Mục tiêu thao tác chuẩn xác', 'Thang điểm 10', 1),
(5, 5, 2, 'M005', 'Hóa dược cơ bản', 'Bắt buộc', 'Kết hợp', 3, 2, 1, 45, 30, 15, 60, 'Dược năm 2', 'Kỳ I', '2026', '', '', '', 'Bộ môn Hóa dược', 'Ban Dược', 'Khoa Dược', 'Mô tả cấu trúc hóa học thuốc', 'Mục tiêu liên hệ cấu trúc - tác dụng', 'Thang điểm 10', 2),
(6, 6, 2, 'M006', 'Dược lý lâm sàng', 'Bắt buộc', 'Kết hợp', 4, 3, 1, 60, 45, 15, 90, 'Dược năm 4', 'Kỳ II', '2026', '', '', '', 'Bộ môn Dược lý', 'Ban Dược', 'Khoa Dược', 'Mô tả cơ chế tác dụng thuốc', 'Mục tiêu tối ưu hóa phác đồ điều trị', 'Thang điểm 10', 2),
(7, 7, 2, 'M007', 'Quản lý cung ứng thuốc', 'Tự chọn', 'Học trực tuyến', 2, 1, 1, 30, 20, 10, 45, 'Dược năm 4', 'Kỳ I', '2026', '', '', '', 'Bộ môn Quản lý Dược', 'Ban Dược', 'Khoa Dược', 'Mô tả chuỗi cung ứng', 'Mục tiêu lập kế hoạch dược phẩm', 'Thang điểm 10', 2),
(8, 8, 3, 'M008', 'Điều dưỡng cơ bản', 'Bắt buộc', 'Học trên lớp', 4, 2, 2, 60, 25, 35, 80, 'Điều dưỡng năm 1', 'Kỳ II', '2026', '', '', '', 'Bộ môn Điều dưỡng cơ bản', 'Ban ĐD', 'Khoa Điều dưỡng', 'Mô tả kỹ năng điều dưỡng gốc', 'Mục tiêu thực hiện quy trình an toàn', 'Thang điểm 10', 3),
(9, 9, 3, 'M009', 'Chăm sóc người bệnh nội khoa', 'Bắt buộc', 'Học trên lớp', 4, 2, 2, 60, 30, 30, 90, 'Điều dưỡng năm 3', 'Kỳ I', '2026', '', '', '', 'Bộ môn Điều dưỡng nội', 'Ban ĐD', 'Khoa Điều dưỡng', 'Mô tả lập kế hoạch CSNB', 'Mục tiêu kiểm soát biến chứng nội khoa', 'Thang điểm 10', 3),
(10, 10, 1, 'M010', 'Tin học ứng dụng y học', 'Tự chọn', 'Học trực tuyến', 3, 1, 2, 45, 20, 25, 60, 'Khối sức khỏe', 'Kỳ II', '2026', '', '', '', 'Trung tâm CNTT', 'Ban liên khoa', 'Khoa KHCB', 'Mô tả công cụ số trong y văn', 'Mục tiêu xử lý số liệu y sinh', 'Thang điểm 10', 4);

-- Bảng 10: module_relationships
INSERT INTO `module_relationships` (`id`, `module_id`, `related_course_id`, `relation_type`) VALUES
(1, 2, 1, 'Học trước'), (2, 3, 2, 'Học trước'), (3, 4, 3, 'Song hành'), (4, 6, 5, 'Học trước'), (5, 9, 8, 'Học trước'),
(6, 4, 1, 'Học trước'), (7, 4, 2, 'Học trước'), (8, 6, 2, 'Học trước'), (9, 9, 2, 'Học trước'), (10, 7, 5, 'Học trước');

-- Bảng 11: module_departments
INSERT INTO `module_departments` (`module_id`, `department_id`) VALUES
(1, 1), (2, 2), (3, 3), (4, 4), (5, 5), (6, 6), (7, 7), (8, 8), (9, 9), (10, 10);

-- Bảng 12: lecturers
INSERT INTO `lecturers` (`id`, `name`, `email`, `phone`) VALUES
(1, 'ThS. Nguyễn Văn A', 'a@school.edu.vn', '0901234567'),
(2, 'TS. Trần Thị B', 'b@school.edu.vn', '0901234568'),
(3, 'ThS. Phạm Minh C', 'c@school.edu.vn', '0901234569'),
(4, 'PGS.TS. Lê Hoàng D', 'd@school.edu.vn', '0901234570'),
(5, 'GS.TS. Vũ Hải E', 'e@school.edu.vn', '0901234571'),
(6, 'TS. Đặng Thành F', 'f@school.edu.vn', '0901234572'),
(7, 'ThS. Hoàng Thị G', 'g@school.edu.vn', '0901234573'),
(8, 'ThS. Đỗ Văn H', 'h@school.edu.vn', '0901234574'),
(9, 'TS. Bùi Minh I', 'i@school.edu.vn', '0901234575'),
(10, 'PGS.TS. Ngô Tiến K', 'k@school.edu.vn', '0901234576');

-- Bảng 13: course_coordinators
INSERT INTO `course_coordinators` (`id`, `module_id`, `lecturer_id`) VALUES
(1, 1, 1), (2, 1, 2), (3, 2, 2), (4, 2, 3), (5, 3, 4), (6, 4, 5), (7, 5, 6), (8, 6, 7), (9, 7, 8), (10, 8, 9);

-- Bảng 14: plo
INSERT INTO `plo` (`id`, `code`, `content`) VALUES
(1, 'PLO1', 'Năng lực vận dụng hệ thống kiến thức khoa học cơ bản vào hành nghề'),
(2, 'PLO2', 'Thành thạo kỹ năng thao tác thực hành chuyên môn y dược lâm sàng'),
(3, 'PLO3', 'Áp dụng các chuẩn mực đạo đức, pháp luật vào môi trường y tế'),
(4, 'PLO4', 'Phát triển kỹ năng giao tiếp, tư vấn và làm việc đa ngành hiệu quả'),
(5, 'PLO5', 'Khả năng phân tích, nghiên cứu khoa học và tự học suốt đời'),
(6, 'PLO6', 'Năng lực quản lý hành chính, tổ chức và điều phối trạm/khoa y tế'),
(7, 'PLO7', 'Ứng dụng tốt công nghệ thông tin và ngoại ngữ vào khai thác tài liệu'),
(8, 'PLO8', 'Tư duy phản biện độc lập khi giải quyết các vấn đề lâm sàng phức tạp'),
(9, 'PLO9', 'Trách nhiệm phục vụ cộng đồng và ý thức an sinh xã hội cao'),
(10, 'PLO10', 'Năng lực linh hoạt thích ứng các thay đổi trong môi trường công tác');

-- Bảng 15: pi
INSERT INTO `pi` (`id`, `code`, `content`) VALUES
(1, 'PI1.1', 'Giải thích chi tiết cấu trúc giải phẫu hình thái người bình thường'),
(2, 'PI1.2', 'Phân tích các cơ chế chuyển hóa sinh lý bệnh lý ở cấp độ tế bào'),
(3, 'PI2.1', 'Thực hiện chuẩn xác quy trình hỏi bệnh sử và khám thực thể sàng lọc'),
(4, 'PI2.2', 'Kê đơn, tính toán liều dùng dược phẩm an toàn, chống tương tác thuốc'),
(5, 'PI3.1', 'Đảm bảo quyền riêng tư và cam kết bảo mật hồ sơ bệnh án nghiêm ngặt'),
(6, 'PI4.1', 'Tương tác thông tin hai chiều chuẩn xác giữa bác sĩ - điều dưỡng - người bệnh'),
(7, 'PI5.1', 'Sử dụng thuần thục công cụ PubMed để tra cứu giải pháp lâm sàng'),
(8, 'PI6.1', 'Xây dựng kế hoạch cung ứng trang thiết bị vật tư y tế cục bộ'),
(9, 'PI7.1', 'Vận hành thành thạo phần mềm HIS quản lý tổng thể bệnh viện'),
(10, 'PI8.1', 'Nhận diện chuẩn và đưa ra phác đồ can thiệp dịch tễ cộng đồng kịp thời');

-- Bảng 16: plo_pi_relation
INSERT INTO `plo_pi_relation` (`plo_id`, `pi_id`) VALUES
(1, 1), (1, 2), (2, 3), (2, 4), (3, 5), (4, 6), (5, 7), (6, 8), (7, 9), (8, 10);

-- Bảng 17: clos
INSERT INTO `clos` (`id`, `module_id`, `code`, `content`, `domain`, `bloom_level`, `contribution_level`, `plo_id`, `pi_id`) VALUES
(1, 1, 'CLO1', 'Trình bày rõ ràng cấu trúc giải phẫu đại cương cơ thể', 'Kiến thức', '2. Hiểu', 'H', 1, 1),
(2, 1, 'CLO2', 'Xác định chính xác vị trí cơ quan trên mô hình 3D', 'Kỹ năng', '3. Áp dụng', 'M', 2, 3),
(3, 2, 'CLO1', 'Giải thích cơ chế điều hòa sinh lý tim mạch và hô hấp', 'Kiến thức', '2. Hiểu', 'H', 1, 1),
(4, 2, 'CLO2', 'Đo đạc thành thạo huyết áp và ghi điện tâm đồ cơ bản', 'Kỹ năng', '3. Áp dụng', 'M', 2, 3),
(5, 3, 'CLO1', 'Phân tích tổn thương đại thể và vi thể mô bệnh học', 'Kiến thức', '4. Phân tích', 'H', 1, 2),
(6, 4, 'CLO1', 'Thực hiện thành thạo kỹ năng hỏi bệnh sử người bệnh', 'Kỹ năng', '3. Áp dụng', 'H', 2, 3),
(7, 5, 'CLO1', 'Nhận diện công thức cấu tạo hóa học nhóm kháng sinh', 'Kiến thức', '2. Hiểu', 'M', 1, 1),
(8, 6, 'CLO1', 'Tính toán liều và đề xuất thuốc điều trị phù hợp ca bệnh', 'Kiến thức', '3. Áp dụng', 'H', 2, 4),
(9, 8, 'CLO1', 'Thực hiện đúng kỹ thuật tiêm truyền vô khuẩn tuyệt đối', 'Kỹ năng', '3. Áp dụng', 'H', 2, 3),
(10, 10, 'CLO1', 'Ứng dụng Excel để tính toán thống kê mô tả y sinh', 'Kỹ năng', '3. Áp dụng', 'M', 7, 9);

-- Bảng 18: assessments
INSERT INTO `assessments` (`id`, `module_id`, `type`, `component`, `clos_text`, `form`, `weight`, `assessment_form_id`) VALUES
(1, 1, 'Chuyên cần', 'Điểm chuyên cần lớp lý thuyết', 'CLO1', 'Điểm danh ngẫu nhiên', 10.00, 1),
(2, 1, 'Kiểm tra thường xuyên', 'Bài kiểm tra trắc nghiệm giữa kỳ', 'CLO1', 'Trắc nghiệm giấy', 30.00, 2),
(3, 1, 'Thi kết thúc', 'Thi thực hành chạy trạm giải phẫu', 'CLO2', 'Thi thực hành mô hình', 60.00, 4),
(4, 2, 'Chuyên cần', 'Điểm chuyên cần thực hành sinh lý', 'CLO3', 'Đánh giá thái độ chuyên cần', 10.00, 1),
(5, 2, 'Kiểm tra thường xuyên', 'Báo cáo thực hành chuỗi tiêu bản', 'CLO4', 'Nộp báo cáo lab', 30.00, 2),
(6, 2, 'Thi kết thúc', 'Thi trắc nghiệm tổng hợp hết môn', 'CLO3, CLO4', 'Trắc nghiệm máy tính', 60.00, 6),
(7, 3, 'Kiểm tra thường xuyên', 'Tiểu luận phân tích ca bệnh lý', 'CLO5', 'Viết bài tiểu luận', 40.00, 7),
(8, 3, 'Thi kết thúc', 'Thi tự luận lý thuyết cuối kỳ', 'CLO5', 'Thi viết tự luận', 60.00, 5),
(9, 4, 'Kiểm tra thường xuyên', 'Đánh giá trạm kỹ năng tiền lâm sàng', 'CLO6', 'Chạy trạm kiểm tra kỹ năng', 40.00, 4),
(10, 4, 'Thi kết thúc', 'Thi lâm sàng trực tiếp trên người bệnh', 'CLO6', 'Vấn đáp bệnh án tại viện', 60.00, 5);

-- Bảng 19: assessment_tools
INSERT INTO `assessment_tools` (`id`, `assessment_form`, `name`) VALUES
(1, 'Chuyên cần', 'Bảng điểm danh tích hợp QR code'),
(2, 'Chuyên cần', 'Thang đo thái độ chuyên cần chuyên nghiệp'),
(3, 'Kiểm tra thường xuyên', 'Bộ câu hỏi MCQ 20 câu kiểm tra nhanh'),
(4, 'Kiểm tra thường xuyên', 'Bảng kiểm (Checklist) kỹ năng thực hành lab'),
(5, 'Kiểm tra thường xuyên', 'Rubric chấm điểm báo cáo/bài tập nhóm'),
(6, 'Thi kết thúc', 'Đề thi tự luận tích hợp 3 tình huống'),
(7, 'Thi kết thúc', 'Ngân hàng câu hỏi trắc nghiệm 100 câu'),
(8, 'Thi kết thúc', 'Bộ kịch bản chạy trạm đánh giá OSCE'),
(9, 'Chuyên cần', 'Nhật ký đăng nhập tương tác LMS'),
(10, 'Thi kết thúc', 'Thang đánh giá hội đồng đồ án luận văn');

-- Bảng 20: assessment_tool_relation
INSERT INTO `assessment_tool_relation` (`assessment_id`, `assessment_tool_id`) VALUES
(1, 1), (2, 3), (3, 8), (4, 2), (5, 4), (6, 7), (7, 5), (8, 6), (9, 8), (10, 6);

-- Bảng 21: assessment_clos
INSERT INTO `assessment_clos` (`assessment_id`, `clo_id`) VALUES
(1, 1), (2, 1), (3, 2), (4, 3), (5, 4), (6, 3), (6, 4), (7, 5), (8, 5), (9, 6);

-- Bảng 22: facilities
INSERT INTO `facilities` (`id`, `name`) VALUES
(1, 'Phòng thực hành Giải phẫu hình thái'), (2, 'Phòng mô phỏng lâm sàng Nội khoa thông minh'), (3, 'Phòng thực hành máy tính y học trung tâm'),
(4, 'Phòng thực hành Sinh lý - Sinh lý bệnh lý'), (5, 'Phòng thí nghiệm bào chế và Hóa dược'), (6, 'Phòng huấn luyện kỹ năng Điều dưỡng gốc 1'),
(7, 'Giảng đường lớn chất lượng cao A1'), (8, 'Giảng đường tương tác thông minh B3'), (9, 'Phòng mô phỏng thực hành Răng Hàm Mặt'), (10, 'Bệnh viện đa khoa thực hành giả định');

-- Bảng 23: self_study_activities
INSERT INTO `self_study_activities` (`id`, `module_id`, `activity_name`, `clos_text`, `duration_hours`, `method`, `assessment_method`, `evidence`) VALUES
(1, 1, 'Nghiên cứu trước atlas giải phẫu hệ xương', 'CLO1', 5, 'Đọc tài liệu', 'Quiz tự chọn trên phần mềm', 'Kết quả tích lũy LMS'),
(2, 1, 'Xem video diễn giải không gian 3D tim mạch', 'CLO2', 4, 'Xem clip bài giảng số', 'Trả lời câu hỏi đính kèm video', 'Bản ghi log hệ thống'),
(3, 2, 'Vẽ sơ đồ tư duy cơ chế phản xạ điều hòa áp lực', 'CLO3', 6, 'Vẽ mindmap cá nhân', 'Đánh giá sơ đồ nộp', 'File ảnh/sơ đồ tư duy nộp'),
(4, 2, 'Phân tích tài liệu ca bệnh suy van tim', 'CLO4', 8, 'Thảo luận nhóm trực tuyến', 'Chấm báo cáo thu hoạch nhóm', 'File văn bản tổng hợp'),
(5, 3, 'Nhận diện ảnh tiêu bản ung thư đại tràng', 'CLO5', 6, 'Xem tiêu bản số trực tuyến', 'Làm bài test hình ảnh nhanh', 'Bảng điểm trắc nghiệm số'),
(6, 4, 'Đóng vai tập huấn hỏi bệnh sử theo đôi', 'CLO6', 10, 'Thực hành tương tác đôi', 'Bảng kiểm đồng đẳng tự đánh giá', 'Video ghi lại buổi tập đóng vai'),
(7, 5, 'Tra cứu công thức và nhóm chức hóa dược gốc', 'CLO7', 5, 'Khai thác dữ liệu PubChem', 'Chấm đề cương tóm tắt cá nhân', 'Bản báo cáo viết tay/đánh máy'),
(8, 6, 'Tính toán liều dùng trên bệnh nhân suy thận', 'CLO8', 8, 'Giải toán ca bệnh lâm sàng', 'Chấm điểm lời giải bài tập', 'Tệp lời giải PDF tải lên hệ thống'),
(9, 8, 'Học thuộc lòng bảng kiểm quy trình thay băng gạc', 'CLO9', 4, 'Học qua thẻ Flashcard quy trình', 'Kiểm tra vấn đáp đầu buổi sau', 'Phiếu kiểm tra đầu giờ tích hợp'),
(10, 10, 'Xây dựng chuỗi lệnh truy vấn y văn trên PubMed', 'CLO10', 6, 'Thực hành truy vấn số', 'Đánh giá tính chuẩn xác của lệnh', 'Danh mục chuỗi câu lệnh kèm kết quả');

-- Bảng 24: self_study_clos
INSERT INTO `self_study_clos` (`self_study_activity_id`, `clo_id`) VALUES
(1, 1), (2, 2), (3, 3), (4, 4), (5, 5), (6, 6), (7, 7), (8, 8), (9, 9), (10, 10);

-- Bảng 25: theory_topics
INSERT INTO `theory_topics` (`id`, `module_id`, `parent_id`, `chapter`, `title`, `delivery_mode`, `method`, `class_hours`, `self_study_hours`, `online_hours`, `teaching_method`, `clos_text`) VALUES
(1, 1, NULL, 'Chương I', 'Tổng quan về cấu trúc giải phẫu người đại cương', 'in_person', 'Thuyết giảng tương tác', 4, 8, 0, 'Sử dụng slide kết hợp bản đồ tư duy cơ thể', 'CLO1'),
(2, 1, 1, NULL, 'Bài 1: Chi tiết hệ xương đầu mặt sọ', 'in_person', 'Học theo vấn đề', 2, 4, 0, 'Phân tích trực tiếp trên mô hình xương sọ thật', 'CLO1, CLO2'),
(3, 1, 1, NULL, 'Bài 2: Cấu trúc cột sống và lồng ngực', 'online', 'Bài giảng số hóa', 0, 4, 2, 'Học thông qua clip mô phỏng chuyển động cơ thể', 'CLO1'),
(4, 2, NULL, 'Chương I', 'Sinh lý học điện thế màng tế bào và sợi cơ', 'in_person', 'Thuyết giảng kết hợp đặt câu hỏi', 4, 8, 0, 'Giảng giải sơ đồ hóa luồng ion qua màng sinh chất', 'CLO3'),
(5, 2, 4, NULL, 'Bài 1: Cơ chế phân tử của hiện tượng co cơ vân', 'hybrid', 'Dạy học hỗn hợp', 2, 4, 2, 'Lớp học đảo ngược yêu cầu xem clip lý thuyết trước', 'CLO3, CLO4'),
(6, 3, NULL, 'Chương I', 'Cơ chế thích nghi, tổn thương tổn hại tế bào', 'in_person', 'Thuyết giảng trực quan', 3, 6, 0, 'Phân tích so sánh song song mô bình thường và mô bệnh', 'CLO5'),
(7, 4, NULL, 'Chương I', 'Nghệ thuật tiếp cận tâm lý và hỏi bệnh sử', 'in_person', 'Thảo luận nhóm ca bệnh', 2, 4, 0, 'Đóng vai bác sĩ - bệnh nhân xử lý các ca khó tính', 'CLO6'),
(8, 5, NULL, 'Chương I', 'Đại cương mối quan hệ cấu trúc cấu tạo hóa dược', 'online', 'Học trực tuyến đồng bộ', 0, 4, 4, 'Học qua MS Teams thảo luận vẽ công thức hóa học', 'CLO7'),
(9, 6, NULL, 'Chương I', 'Nguyên lý sử dụng kháng sinh trong nhiễm khuẩn', 'in_person', 'Thuyết giảng nêu vấn đề', 4, 8, 0, 'Dựa trên bằng chứng lâm sàng thực tế để phân loại', 'CLO8'),
(10, 8, NULL, 'Chương I', 'Lịch sử phát triển và chuẩn đạo đức ngành ĐD', 'in_person', 'Tọa đàm gợi mở', 2, 4, 0, 'Phân tích các tình huống tiến thoái lưỡng nan đạo đức', 'CLO9');

-- Bảng 26: theory_topic_clos
INSERT INTO `theory_topic_clos` (`theory_topic_id`, `clo_id`) VALUES
(1, 1), (2, 1), (2, 2), (3, 1), (4, 3), (5, 3), (5, 4), (6, 5), (7, 6), (8, 7);

-- Bảng 27: practical_topics
INSERT INTO `practical_topics` (`id`, `module_id`, `parent_id`, `topic`, `content`, `delivery_mode`, `method`, `lab_hours`, `online_hours`, `teaching_method`, `clos_text`, `facility_id`) VALUES
(1, 1, NULL, 'Bài thực hành số 1', 'Nhận diện phân loại toàn bộ hệ xương sọ người', 'in_person', 'Luyện tập tiêu bản', 3, 0, 'Cầm tay chỉ việc hướng dẫn từng hốc mốc giải phẫu', 'CLO2', 1),
(2, 1, 1, 'Nâng cao chi tiết', 'Phân tích sâu các lỗ nền sọ nơi dây thần kinh đi qua', 'in_person', 'Quan sát phẫu tích', 2, 0, 'Luyện tập nhóm nhỏ tự kiểm tra chéo kiến thức', 'CLO2', 1),
(3, 2, NULL, 'Bài thực hành số 2', 'Thực hành thao tác ghi nhận sóng điện tâm đồ ECG', 'in_person', 'Vận hành thiết bị', 3, 0, 'Mắc điện cực thực tế trên bạn học, đọc thông số', 'CLO4', 4),
(4, 3, NULL, 'Bài thực hành số 3', 'Quan sát dưới kính hiển vi tiêu bản viêm thừa cấp', 'in_person', 'Soi kính hiển vi', 3, 0, 'Hướng dẫn tìm vùng hoại tử rụng lông chuyển mô', 'CLO5', 4),
(5, 4, NULL, 'Bài thực hành số 4', 'Nghe tim tìm tiếng thổi bệnh lý trên robot lâm sàng', 'in_person', 'Mô phỏng lâm sàng', 4, 0, 'Điều chỉnh robot phát tần số âm bệnh lý để nhận diện', 'CLO6', 2),
(6, 5, NULL, 'Bài thực hành số 5', 'Định lượng thành phần hoạt chất chính trong thuốc', 'in_person', 'Chuẩn độ hóa học', 3, 0, 'Sử dụng buret chuẩn độ trung hòa tìm điểm đổi màu', 'CLO7', 5),
(7, 6, NULL, 'Bài thực hành số 6', 'Tính liều thuốc tối ưu dựa trên cân nặng trẻ em', 'online', 'Bài tập nhóm trực tuyến', 0, 3, 'Thảo luận trực tiếp qua phòng chia nhỏ trên Zoom', 'CLO8', 3),
(8, 8, NULL, 'Bài thực hành số 7', 'Thực hiện quy trình thay băng gạc vô khuẩn vết thương', 'in_person', 'Thao tác mô hình', 4, 0, 'Giảng viên chấm điểm từng bước mở khay kẹp bông', 'CLO9', 6),
(9, 9, NULL, 'Bài thực hành số 8', 'Lập kế hoạch chăm sóc chuẩn cho người bệnh tăng HA', 'in_person', 'Đi buồng thực tế viện', 4, 0, 'Tiếp cận người bệnh thật tại giường bệnh nội khoa', 'CLO9', 2),
(10, 10, NULL, 'Bài thực hành số 9', 'Nhập liệu xây dựng kho dữ liệu sạch trên phần mềm', 'online', 'Tự thao tác có hướng dẫn', 0, 3, 'Cung cấp video quay sẵn màn hình từng bước click chuột', 'CLO10', 3);

-- Bảng 28: practical_topic_clos
INSERT INTO `practical_topic_clos` (`practical_topic_id`, `clo_id`) VALUES
(1, 2), (2, 2), (3, 4), (4, 5), (5, 6), (6, 7), (7, 8), (8, 9), (9, 9), (10, 10);

-- Bảng 29: combined_topics
INSERT INTO `combined_topics` (`id`, `module_id`, `parent_id`, `sort_order`, `content`, `delivery_mode`, `method`, `theory_hours`, `practical_hours`, `online_hours`, `self_study_hours`, `teaching_method`, `clos_text`, `facility_id`) VALUES
(1, 1, NULL, 1, 'Học tích hợp trọn vẹn mô đun hệ tuần hoàn', 'in_person', 'Dạy học tích hợp lý thuyết - hành', 2, 2, 0, 4, 'Giảng lý thuyết nửa buổi, nửa buổi xem mô hình van tim', 'CLO1, CLO2', 1),
(2, 1, 1, 2, 'Chi tiết giải phẫu mạch vành nuôi cơ tim', 'in_person', 'Thực hành phẫu tích', 1, 1, 0, 2, 'Hướng dẫn rạch mổ thực tế trên tim động vật mẫu', 'CLO2', 1),
(3, 2, NULL, 1, 'Học tích hợp chức năng cơ học hệ hô hấp', 'hybrid', 'Tích hợp hỗn hợp', 2, 2, 2, 4, 'Học lý thuyết trực tuyến trước khi đo hô hấp ký tại phòng lab', 'CLO3, CLO4', 4),
(4, 3, NULL, 1, 'Tích hợp chẩn đoán bệnh lý xơ gan tiến triển', 'in_person', 'Tích hợp ca bệnh', 2, 1, 0, 3, 'Phân tích slide bệnh học song hành kết quả sinh thiết thực tế', 'CLO5', 4),
(5, 4, NULL, 1, 'Tích hợp kỹ thuật khám phản xạ 12 dây thần kinh', 'in_person', 'Thực hành lâm sàng cặp', 2, 2, 0, 4, 'Hướng dẫn dùng búa phản xạ thao tác trực tiếp luân phiên', 'CLO6', 2),
(6, 5, NULL, 1, 'Tích hợp quy trình tổng hợp và định tính aspirin', 'in_person', 'Tích hợp phòng thí nghiệm', 1, 3, 0, 4, 'Kết hợp lý thuyết phản ứng este hóa với đo điểm nóng chảy', 'CLO7', 5),
(7, 6, NULL, 1, 'Tích hợp phác đồ cấp cứu sốc phản vệ dược phẩm', 'online', 'Tình huống giả định trực tuyến', 2, 0, 2, 4, 'Đưa tình huống khẩn cấp qua Teams bắt nhóm phản ứng nhanh', 'CLO8', 3),
(8, 8, NULL, 1, 'Tích hợp quy trình đặt thông tiểu nam và nữ giới', 'in_person', 'Tích hợp mô hình mô phỏng', 1, 3, 0, 4, 'Xem giảng viên thao tác mẫu từng bước trước khi chia cụm tập', 'CLO9', 6),
(9, 9, NULL, 1, 'Tích hợp chăm sóc toàn diện ca đái tháo đường cấp', 'in_person', 'Tích hợp lâm sàng khoa', 2, 2, 0, 4, 'Thực hành đo đường huyết mao mạch nhanh, tính liều tiêm', 'CLO9', 2),
(10, 10, NULL, 1, 'Tích hợp biểu diễn trực quan hóa dữ liệu nghiên cứu', 'online', 'Tích hợp số trực tuyến', 1, 2, 3, 3, 'Hướng dẫn tạo biểu đồ bảng biểu chuyên nghiệp qua Google Form', 'CLO10', 3);

-- Bảng 30: combined_topic_clos
INSERT INTO `combined_topic_clos` (`combined_topic_id`, `clo_id`) VALUES
(1, 1), (1, 2), (2, 2), (3, 3), (3, 4), (4, 5), (5, 6), (6, 7), (7, 8), (8, 9);

-- Bảng 31: books_catalog
INSERT INTO `books_catalog` (`id`, `title`, `editor`, `publisher`, `year`, `identifier`) VALUES
(1, 'Giải phẫu người toàn tập', 'Nguyễn Quang Quyền', 'NXB Y học', '2020', '978-604-66-1234-1'),
(2, 'Sinh lý học y khoa nâng cao', 'Phạm Long Tuấn', 'NXB Y học', '2021', '978-604-66-5678-2'),
(3, 'Bệnh học đại cương cơ sở', 'Nguyễn Vượng', 'NXB Y học', '2019', '978-604-66-9012-3'),
(4, 'Triệu chứng học nội khoa tập 1', 'Nguyễn Gia Bình', 'NXB Y học', '2022', '978-604-66-3456-4'),
(5, 'Hóa dược học cơ bản tập 1', 'Trần Đức Hậu', 'NXB Giáo dục', '2018', '978-604-01-7890-5'),
(6, 'Dược lý học lâm sàng ứng dụng', 'Đào Văn Phan', 'NXB Y học', '2023', '978-604-66-2345-6'),
(7, 'Quản lý và Kinh tế dược hiện đại', 'Nguyễn Thị Thái Hằng', 'NXB Giáo dục', '2021', '978-604-01-1122-3'),
(8, 'Điều dưỡng cơ bản và quy trình kỹ thuật', 'Trần Thị Thuận', 'NXB Y học', '2020', '978-604-66-4433-1'),
(9, 'Bài giảng Chăm sóc toàn diện bệnh nội khoa', 'Bùi Khánh Hội', 'NXB Y học', '2022', '978-604-66-5566-2'),
(10, 'Tin học ứng dụng trong nghiên cứu Y sinh', 'Lê Hoài Bắc', 'NXB Khoa học Kỹ thuật', '2021', '978-604-99-8877-1');

-- Bảng 32: resources
INSERT INTO `resources` (`id`, `module_id`, `resource_type`, `sort_order`, `title`, `editor`, `publisher`, `year`, `identifier`, `book_id`) VALUES
(1, 1, 'Tài liệu giảng dạy', 1, 'Giáo trình Giải phẫu chính thống khoa', 'Nguyễn Quang Quyền', 'NXB Y học', '2020', 'ISBN-GP-01', 1),
(2, 2, 'Tài liệu giảng dạy', 1, 'Giáo trình Sinh lý học tập 1', 'Phạm Long Tuấn', 'NXB Y học', '2021', 'ISBN-SL-02', 2),
(3, 3, 'Tài liệu giảng dạy', 1, 'Bài giảng Bệnh học mô phôi thực hành', 'Nguyễn Vượng', 'NXB Y học', '2019', 'ISBN-BH-03', 3),
(4, 4, 'Tài liệu giảng dạy', 1, 'Cẩm nang khám lâm sàng Nội khoa đại học', 'Nguyễn Gia Bình', 'NXB Y học', '2022', 'ISBN-LS-04', 4),
(5, 5, 'Tài liệu giảng dạy', 1, 'Giáo trình Hóa dược đại cương nâng cao', 'Trần Đức Hậu', 'NXB Giáo dục', '2018', 'ISBN-HD-05', 5),
(6, 6, 'Tài liệu tự học', 1, 'Hướng dẫn sử dụng thuốc quốc gia tập 2', 'Đào Văn Phan', 'NXB Y học', '2023', 'ISBN-DL-06', 6),
(7, 7, 'Tài liệu giảng dạy', 1, 'Giáo trình Cung ứng và phân phối thuốc', 'Nguyễn Thị Thái Hằng', 'NXB Giáo dục', '2021', 'ISBN-QL-07', 7),
(8, 8, 'Tài liệu giảng dạy', 1, 'Quy trình thực hành Điều dưỡng 1 cơ sở', 'Trần Thị Thuận', 'NXB Y học', '2020', 'ISBN-DD-08', 8),
(9, 9, 'Tài liệu tự học', 1, 'Tài liệu tham khảo Chăm sóc tích cực ICU', 'Bùi Khánh Hội', 'NXB Y học', '2022', 'ISBN-CS-09', 9),
(10, 10, 'Tài liệu giảng dạy', 1, 'Hướng dẫn thực hành Tin học ứng dụng SPSS', 'Lê Hoài Bắc', 'NXB KH-KT', '2021', 'ISBN-TH-10', 10);
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- CẬP NHẬT LẠI MẪU DỮ LIỆU ĐỂ KHỚP VỚI YÊU CẦU TEST (10 MẪU CHO MỖI BẢNG MỚI)
-- ============================================================================

TRUNCATE TABLE `assessment_forms`;
INSERT INTO `assessment_forms` (`id`, `name`) VALUES
(1, 'Chuyên cần'), (2, 'Kiểm tra thường xuyên'), (3, 'Thi kết thúc'),
(4, 'Bài tập nhóm'), (5, 'OSCE/OSPE'), (6, 'Thi viết'),
(7, 'Thi trắc nghiệm'), (8, 'Báo cáo tiểu luận'), (9, 'Thuyết trình'), (10, 'Nhật ký thực hành');

TRUNCATE TABLE `assessment_tools`;
INSERT INTO `assessment_tools` (`id`, `assessment_form`, `name`) VALUES
(1, 'Chuyên cần', 'Điểm danh'),
(2, 'Chuyên cần', 'Hỏi đáp'),
(3, 'Chuyên cần', 'Quan sát thái độ học tập'),
(4, 'Kiểm tra thường xuyên', 'Bài kiểm tra ngắn'),
(5, 'Kiểm tra thường xuyên', 'Bài tập cá nhân'),
(6, 'Kiểm tra thường xuyên', 'Bài tập nhóm'),
(7, 'Kiểm tra thường xuyên', 'Rubric'),
(8, 'Kiểm tra thường xuyên', 'Logbook'),
(9, 'Kiểm tra thường xuyên', 'OSCE/OSPE'),
(10, 'Thi kết thúc', 'Thi viết'),
(11, 'Thi kết thúc', 'Thi trắc nghiệm'),
(12, 'Thi kết thúc', 'Thi vấn đáp'),
(13, 'Thi kết thúc', 'Ngân hàng câu hỏi'),
(14, 'Thi kết thúc', 'Rubric');

TRUNCATE TABLE `plo_pi_relation`;
TRUNCATE TABLE `pi`;
TRUNCATE TABLE `plo`;

INSERT INTO `plo` (`id`, `code`, `content`) VALUES
(1, 'PLO1', 'Nội dung chuẩn đầu ra chương trình PLO1'),
(2, 'PLO2', 'Nội dung chuẩn đầu ra chương trình PLO2'),
(3, 'PLO3', 'Nội dung chuẩn đầu ra chương trình PLO3'),
(4, 'PLO4', 'Nội dung chuẩn đầu ra chương trình PLO4'),
(5, 'PLO5', 'Nội dung chuẩn đầu ra chương trình PLO5'),
(6, 'PLO6', 'Nội dung chuẩn đầu ra chương trình PLO6'),
(7, 'PLO7', 'Nội dung chuẩn đầu ra chương trình PLO7'),
(8, 'PLO8', 'Nội dung chuẩn đầu ra chương trình PLO8'),
(9, 'PLO9', 'Nội dung chuẩn đầu ra chương trình PLO9'),
(10, 'PLO10', 'Nội dung chuẩn đầu ra chương trình PLO10');

INSERT INTO `pi` (`id`, `code`, `content`) VALUES
(1, 'PI1.1', 'Chỉ số hiệu diễn PI1.1'),
(2, 'PI1.2', 'Chỉ số hiệu diễn PI1.2'),
(3, 'PI1.3', 'Chỉ số hiệu diễn PI1.3'),
(4, 'PI2.1', 'Chỉ số hiệu diễn PI2.1'),
(5, 'PI2.2', 'Chỉ số hiệu diễn PI2.2'),
(6, 'PI2.3', 'Chỉ số hiệu diễn PI2.3'),
(7, 'PI3.1', 'Chỉ số hiệu diễn PI3.1'),
(8, 'PI3.2', 'Chỉ số hiệu diễn PI3.2'),
(9, 'PI3.3', 'Chỉ số hiệu diễn PI3.3'),
(10, 'PI4.1', 'Chỉ số hiệu diễn PI4.1');

INSERT INTO `plo_pi_relation` (`plo_id`, `pi_id`) VALUES
(1, 1), (1, 2), (1, 3),
(2, 4), (2, 5), (2, 6),
(3, 7), (3, 8), (3, 9),
(4, 10);

SET FOREIGN_KEY_CHECKS = 1;
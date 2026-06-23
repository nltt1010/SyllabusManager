SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

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
DROP TABLE IF EXISTS `assessment_form_relation`;
DROP TABLE IF EXISTS `assessment_tool_relation`;
DROP TABLE IF EXISTS `assessment_clos`;
DROP TABLE IF EXISTS `assessment_tools`;
DROP TABLE IF EXISTS `clo_plos`;
DROP TABLE IF EXISTS `assessments`;
DROP TABLE IF EXISTS `pis`;
DROP TABLE IF EXISTS `plos`;
DROP TABLE IF EXISTS `clos`;
DROP TABLE IF EXISTS `module_relationships`;
DROP TABLE IF EXISTS `course_coordinators`;
DROP TABLE IF EXISTS `lecturers`;
DROP TABLE IF EXISTS `modules`;
DROP TABLE IF EXISTS `facilities`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `knowledge_blocks`;
DROP TABLE IF EXISTS `education_programs`;
DROP TABLE IF EXISTS `majors`;
DROP TABLE IF EXISTS `assessment_forms`;
DROP TABLE IF EXISTS `faculties_list`;
DROP TABLE IF EXISTS `departments_list`;
DROP TABLE IF EXISTS `module_departments`;

CREATE TABLE `assessment_forms` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  UNIQUE KEY `unique_assessment_form_name` (`name`)
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

CREATE TABLE `facilities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `modules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(255) NOT NULL,
  `type` ENUM('Không', 'Bắt buộc', 'Điều kiện', 'Tự chọn') NOT NULL DEFAULT 'Không',
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
  `objective_general` TEXT NULL,
  `objective_po` TEXT NULL,
  `objective_plo` TEXT NULL,
  `grading_scale` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `faculty_id` INT NULL,
  FOREIGN KEY (`faculty_id`) REFERENCES `faculties_list`(`id`) ON DELETE SET NULL,
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

CREATE TABLE `clos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module_id` INT NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `content` TEXT NOT NULL,
  `domain` VARCHAR(255) NULL,
  `bloom_level` VARCHAR(255) NULL,
  `contribution_level` VARCHAR(10) NULL,
  FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_module_clo` (`module_id`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `plos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL,
  `content` TEXT NOT NULL,
  UNIQUE KEY `unique_plo_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pis` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `plo_id` INT NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `content` TEXT NOT NULL,
  FOREIGN KEY (`plo_id`) REFERENCES `plos`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_plo_pi_code` (`plo_id`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clo_plos` (
  `clo_id` INT NOT NULL,
  `plo_id` INT NOT NULL,
  PRIMARY KEY (`clo_id`, `plo_id`),
  FOREIGN KEY (`clo_id`) REFERENCES `clos`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`plo_id`) REFERENCES `plos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `assessments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module_id` INT NOT NULL,
  `component` ENUM('Chuyên cần', 'Kiểm tra thường xuyên', 'Thi kết thúc') NOT NULL,
  `weight` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_module_assessment_component` (`module_id`, `component`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `assessment_tools` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  UNIQUE KEY `unique_assessment_tool_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `assessment_form_relation` (
  `assessment_id` INT NOT NULL,
  `assessment_form_id` INT NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 1,
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`assessment_id`, `assessment_form_id`),
  FOREIGN KEY (`assessment_id`) REFERENCES `assessments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assessment_form_id`) REFERENCES `assessment_forms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `assessment_clos` (
  `assessment_id` INT NOT NULL,
  `clo_id` INT NOT NULL,
  PRIMARY KEY (`assessment_id`, `clo_id`),
  FOREIGN KEY (`assessment_id`) REFERENCES `assessments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`clo_id`) REFERENCES `clos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `assessment_tool_relation` (
  `assessment_id` INT NOT NULL,
  `tool_id` INT NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 1,
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`assessment_id`, `tool_id`),
  FOREIGN KEY (`assessment_id`) REFERENCES `assessments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tool_id`) REFERENCES `assessment_tools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `self_study_activities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module_id` INT NOT NULL,
  `activity_name` TEXT NOT NULL,
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
  `delivery_mode` ENUM('Học trên lớp', 'Học trực tuyến') NOT NULL DEFAULT 'Học trên lớp',
  `teaching_method` VARCHAR(255) NULL,
  `class_hours` INT DEFAULT 0,
  `online_hours` INT DEFAULT 0,
  `self_study_hours` INT DEFAULT 0,
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
  `topic` VARCHAR(255) NULL,
  `content` TEXT NOT NULL,
  `delivery_mode` ENUM('Học trên lớp', 'Học trực tuyến') NOT NULL DEFAULT 'Học trên lớp',
  `teaching_method` VARCHAR(255) NULL,
  `lab_hours` INT DEFAULT 0,
  `online_hours` INT DEFAULT 0,
  `facility_id` INT NULL,
  FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE,
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
  `sort_order` INT DEFAULT 1,
  `content` TEXT NOT NULL,
  `delivery_mode` ENUM('Học trên lớp', 'Học trực tuyến') NOT NULL DEFAULT 'Học trên lớp',
  `teaching_method` VARCHAR(255) NULL,
  `theory_hours` INT DEFAULT 0,
  `practical_hours` INT DEFAULT 0,
  `online_hours` INT DEFAULT 0,
  `self_study_hours` INT DEFAULT 0,
  `facility_id` INT NULL,
  FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`facility_id`) REFERENCES `facilities`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `combined_topic_clos` (
  `combined_topic_id` INT NOT NULL,
  `clo_id` INT NOT NULL,
  PRIMARY KEY (`combined_topic_id`, `clo_id`),
  FOREIGN KEY (`combined_topic_id`) REFERENCES `combined_topics`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`clo_id`) REFERENCES `clos`(`id`) ON DELETE CASCADE
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

CREATE TABLE `books_catalog` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `editor` VARCHAR(255) NULL,
  `publisher` VARCHAR(255) NULL,
  `year` VARCHAR(50) NULL,
  `identifier` VARCHAR(100) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `faculties_list` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `departments_list` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `lecturers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `course_coordinators` (
  `module_id` INT NOT NULL,
  `lecturer_id` INT NOT NULL,
  PRIMARY KEY (`module_id`, `lecturer_id`),
  FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `majors` (`id`, `name`) VALUES
(1, 'Y khoa'),
(2, 'Dược học'),
(3, 'Điều dưỡng');

INSERT INTO `education_programs` (`id`, `major_id`, `year`) VALUES
(1, 1, 2026),
(2, 2, 2026),
(3, 3, 2026);

INSERT INTO `knowledge_blocks` (`id`, `major_id`, `name`, `parent_id`) VALUES
(1, 1, 'Kiến thức giáo dục đại cương', NULL),
(2, 1, 'Kiến thức cơ sở ngành Y', NULL),
(3, 1, 'Kiến thức chuyên ngành Y khoa', NULL),
(4, 2, 'Kiến thức cơ sở ngành Dược', NULL),
(5, 2, 'Kiến thức chuyên ngành Dược', NULL),
(6, 3, 'Kiến thức cơ sở ngành Điều dưỡng', NULL),
(7, 3, 'Kiến thức chuyên ngành Điều dưỡng', NULL);

INSERT INTO `facilities` (`id`, `name`) VALUES
(1, 'Phòng thực hành Giải phẫu'),
(2, 'Phòng mô phỏng lâm sàng'),
(3, 'Phòng máy tính y học'),
(4, 'Phòng thực hành Sinh lý'),
(5, 'Phòng thực hành Dược'),
(6, 'Phòng kỹ năng Điều dưỡng');

INSERT INTO `assessment_forms` (`id`, `name`) VALUES
(1, 'Điểm danh'),
(2, 'Hỏi đáp'),
(3, 'Bài tập tình huống'),
(4, 'Thuyết trình nhóm'),
(5, 'Trắc nghiệm'),
(6, 'OSCE/OSPE');

INSERT INTO `faculties_list` (`id`, `name`) VALUES
(1, 'Khoa Y'),
(2, 'Khoa Dược'),
(3, 'Khoa Điều dưỡng'),
(4, 'Khoa Khoa học cơ bản');

INSERT INTO `departments_list` (`id`, `name`) VALUES
(1, 'Bộ môn Giải phẫu'),
(2, 'Bộ môn Sinh lý'),
(3, 'Bộ môn Bệnh học'),
(4, 'Bộ môn Nội'),
(5, 'Bộ môn Hóa dược'),
(6, 'Bộ môn Dược lý'),
(7, 'Bộ môn Quản lý Dược'),
(8, 'Bộ môn Điều dưỡng cơ bản'),
(9, 'Bộ môn Điều dưỡng nội'),
(10, 'Trung tâm Công nghệ thông tin');

INSERT INTO `lecturers` (`id`, `name`) VALUES
(1, 'TS. Nguyễn Văn An'),
(2, 'TS. Trần Thị Bích'),
(3, 'TS. Lê Quang Huy'),
(4, 'BSCKII. Phạm Minh Hoàng'),
(5, 'BSCKII. Võ Thị Lan'),
(6, 'BSCKII. Đặng Ngọc Sơn'),
(7, 'PGS.TS. Bùi Thu Hà'),
(8, 'TS. Nguyễn Hoài Phương'),
(9, 'ThS. Trương Quốc Bảo'),
(10, 'TS. Phan Thị Thảo'),
(11, 'TS. Trần Đức Long'),
(12, 'ThS. Lê Thị Mỹ Linh'),
(13, 'ThS. Nguyễn Hải Yến'),
(14, 'ThS. Phạm Thu Trang'),
(15, 'TS. Hoàng Minh Châu'),
(16, 'BSCKII. Lê Thanh Nhân'),
(17, 'PGS.TS. Trần Văn Đức'),
(18, 'PGS.TS. Nguyễn Thị Thanh'),
(19, 'ThS. Đỗ Thị Bích Vân'),
(20, 'TS. Vũ Quốc Đạt');

INSERT INTO `courses` (`id`, `major_id`, `block_id`, `code`, `name`, `total_hours`, `theory_hours`, `practical_hours`, `sort_order`) VALUES
(1, 1, 2, 'TEST001', 'Giải phẫu học đại cương', 45, 30, 15, 1),
(2, 1, 2, 'TEST002', 'Sinh lý học đại cương', 45, 30, 15, 2),
(3, 1, 3, 'TEST003', 'Bệnh học cơ sở', 45, 35, 10, 3),
(4, 1, 3, 'TEST004', 'Kỹ năng khám lâm sàng', 60, 25, 35, 4),
(5, 2, 4, 'TEST005', 'Hóa dược cơ bản', 45, 30, 15, 5),
(6, 2, 5, 'TEST006', 'Dược lý lâm sàng', 60, 45, 15, 6),
(7, 2, 5, 'TEST007', 'Quản lý cung ứng thuốc', 30, 20, 10, 7),
(8, 3, 6, 'TEST008', 'Điều dưỡng cơ bản', 60, 25, 35, 8),
(9, 3, 7, 'TEST009', 'Chăm sóc người bệnh nội khoa', 60, 30, 30, 9),
(10, 1, 1, 'TEST010', 'Tin học ứng dụng y học', 45, 20, 25, 10);

INSERT INTO `modules` (`id`, `course_id`, `code`, `name`, `type`, `credits`, `credits_theory`, `credits_practice`, `total_hours`, `theory_hours`, `practical_hours`, `self_study_hours`, `target_programs`, `expected_semester`, `expected_year`, `prerequisite_modules`, `parallel_modules`, `previous_modules`, `department_in_charge`, `coordinating_board`, `faculty_in_charge`, `description`, `objective_general`, `objective_po`, `objective_plo`, `grading_scale`) VALUES
(1, 1, 'TEST001', 'Giải phẫu học đại cương', 'Bắt buộc', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Y khoa năm 1', 'Học kỳ I', '2026-2027', '', '', '', 'Bộ môn Giải phẫu', 'Ban y học cơ sở', 'Khoa Y', 'Học phần cung cấp kiến thức nền tảng về cấu trúc cơ thể người.', 'Mô tả và xác định được các cấu trúc giải phẫu cơ bản.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(2, 2, 'TEST002', 'Sinh lý học đại cương', 'Bắt buộc', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên khối sức khỏe năm 1', 'Học kỳ II', '2026-2027', '', '', '', 'Bộ môn Sinh lý', 'Ban y học cơ sở', 'Khoa Y', 'Học phần trình bày hoạt động chức năng của cơ thể bình thường.', 'Giải thích được các cơ chế điều hòa sinh lý cơ bản.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(3, 3, 'TEST003', 'Bệnh học cơ sở', 'Bắt buộc', 3, 2, 1, 45, 35, 10, 70, 'Sinh viên Y khoa năm 2', 'Học kỳ I', '2026-2027', '', '', '', 'Bộ môn Bệnh học', 'Ban tiền lâm sàng', 'Khoa Y', 'Học phần giới thiệu cơ chế bệnh sinh và tổn thương mô học cơ bản.', 'Phân tích được mối liên hệ giữa tổn thương và biểu hiện bệnh.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(4, 4, 'TEST004', 'Kỹ năng khám lâm sàng', 'Bắt buộc', 4, 2, 2, 60, 25, 35, 80, 'Sinh viên Y khoa năm 3', 'Học kỳ II', '2026-2027', '', '', '', 'Bộ môn Nội', 'Ban lâm sàng', 'Khoa Y', 'Học phần rèn luyện kỹ năng hỏi bệnh và khám bệnh cơ bản.', 'Thực hiện đúng quy trình khám lâm sàng cơ bản.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(5, 5, 'TEST005', 'Hóa dược cơ bản', 'Bắt buộc', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Dược năm 2', 'Học kỳ I', '2026-2027', '', '', '', 'Bộ môn Hóa dược', 'Ban đào tạo Dược', 'Khoa Dược', 'Học phần cung cấp kiến thức về cấu trúc và tính chất hóa học của thuốc.', 'Phân tích được liên quan cấu trúc - tác dụng của thuốc.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(6, 6, 'TEST006', 'Dược lý lâm sàng','Bắt buộc', 4, 3, 1, 60, 45, 15, 90, 'Sinh viên Dược năm 4', 'Học kỳ II', '2026-2027', '', '', '', 'Bộ môn Dược lý', 'Ban đào tạo Dược', 'Khoa Dược', 'Học phần hướng dẫn sử dụng thuốc hợp lý, an toàn và hiệu quả.', 'Đề xuất được lựa chọn thuốc phù hợp tình huống lâm sàng.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(7, 7, 'TEST007', 'Quản lý cung ứng thuốc', 'Tự chọn', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Dược năm 4', 'Học kỳ I', '2026-2027', '', '', '', 'Bộ môn Quản lý Dược', 'Ban đào tạo Dược', 'Khoa Dược', 'Học phần giới thiệu quy trình mua sắm, bảo quản và phân phối thuốc.', 'Lập được kế hoạch cung ứng thuốc ở đơn vị y tế.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(8, 8, 'TEST008', 'Điều dưỡng cơ bản', 'Bắt buộc', 4, 2, 2, 60, 25, 35, 80, 'Sinh viên Điều dưỡng năm 1', 'Học kỳ II', '2026-2027', '', '', '', 'Bộ môn Điều dưỡng cơ bản', 'Ban Điều dưỡng', 'Khoa Điều dưỡng', 'Học phần rèn luyện kỹ năng chăm sóc cơ bản và kiểm soát nhiễm khuẩn.', 'Thực hiện được các quy trình chăm sóc an toàn.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(9, 9, 'TEST009', 'Chăm sóc người bệnh nội khoa', 'Bắt buộc', 4, 2, 2, 60, 30, 30, 90, 'Sinh viên Điều dưỡng năm 3', 'Học kỳ I', '2026-2027', '', '', '', 'Bộ môn Điều dưỡng nội', 'Ban Điều dưỡng', 'Khoa Điều dưỡng', 'Học phần hướng dẫn chăm sóc người bệnh mắc bệnh nội khoa thường gặp.', 'Xây dựng được kế hoạch chăm sóc người bệnh nội khoa.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(10, 10, 'TEST010', 'Tin học ứng dụng y học', 'Tự chọn', 3, 1, 2, 45, 20, 25, 60, 'Sinh viên khối sức khỏe', 'Học kỳ II', '2026-2027', '', '', '', 'Trung tâm Công nghệ thông tin', 'Ban liên khoa', 'Khoa Khoa học cơ bản', 'Học phần rèn luyện kỹ năng nhập liệu, xử lý dữ liệu và tra cứu y văn.', 'Sử dụng được công cụ số trong học tập và nghiên cứu y học.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10');

INSERT INTO `module_relationships` (`module_id`, `related_course_id`, `relation_type`) VALUES
(2, 1, 'Học trước'), (3, 2, 'Học trước'), (4, 3, 'Song hành'), (6, 5, 'Học trước'), (9, 8, 'Học trước');

INSERT INTO `clos` (`module_id`, `code`, `content`, `domain`, `bloom_level`, `contribution_level`) VALUES
(1, 'CLO1', 'Trình bày được kiến thức cốt lõi của học phần.', 'Kiến thức', '2. Hiểu', 'I'), (1, 'CLO2', 'Thực hiện được kỹ năng cơ bản liên quan học phần.', 'Kỹ năng', '3. Làm chính xác', 'R'), (1, 'CLO3', 'Thể hiện thái độ học tập nghiêm túc và an toàn.', 'Thái độ', '3. Nội tâm hóa', 'M'),
(2, 'CLO1', 'Trình bày được kiến thức cốt lõi của học phần.', 'Kiến thức', '2. Hiểu', 'I'), (2, 'CLO2', 'Thực hiện được kỹ năng cơ bản liên quan học phần.', 'Kỹ năng', '3. Làm chính xác', 'R'), (2, 'CLO3', 'Thể hiện thái độ học tập nghiêm túc và an toàn.', 'Thái độ', '3. Nội tâm hóa', 'M'),
(3, 'CLO1', 'Trình bày được kiến thức cốt lõi của học phần.', 'Kiến thức', '2. Hiểu', 'I'), (3, 'CLO2', 'Thực hiện được kỹ năng cơ bản liên quan học phần.', 'Kỹ năng', '3. Làm chính xác', 'R'), (3, 'CLO3', 'Thể hiện thái độ học tập nghiêm túc và an toàn.', 'Thái độ', '3. Nội tâm hóa', 'M'),
(4, 'CLO1', 'Trình bày được kiến thức cốt lõi của học phần.', 'Kiến thức', '2. Hiểu', 'I'), (4, 'CLO2', 'Thực hiện được kỹ năng cơ bản liên quan học phần.', 'Kỹ năng', '3. Làm chính xác', 'R'), (4, 'CLO3', 'Thể hiện thái độ học tập nghiêm túc và an toàn.', 'Thái độ', '3. Nội tâm hóa', 'M'),
(5, 'CLO1', 'Trình bày được kiến thức cốt lõi của học phần.', 'Kiến thức', '2. Hiểu', 'I'), (5, 'CLO2', 'Thực hiện được kỹ năng cơ bản liên quan học phần.', 'Kỹ năng', '3. Làm chính xác', 'R'), (5, 'CLO3', 'Thể hiện thái độ học tập nghiêm túc và an toàn.', 'Thái độ', '3. Nội tâm hóa', 'M'),
(6, 'CLO1', 'Trình bày được kiến thức cốt lõi của học phần.', 'Kiến thức', '2. Hiểu', 'I'), (6, 'CLO2', 'Thực hiện được kỹ năng cơ bản liên quan học phần.', 'Kỹ năng', '3. Làm chính xác', 'R'), (6, 'CLO3', 'Thể hiện thái độ học tập nghiêm túc và an toàn.', 'Thái độ', '3. Nội tâm hóa', 'M'),
(7, 'CLO1', 'Trình bày được kiến thức cốt lõi của học phần.', 'Kiến thức', '2. Hiểu', 'I'), (7, 'CLO2', 'Thực hiện được kỹ năng cơ bản liên quan học phần.', 'Kỹ năng', '3. Làm chính xác', 'R'), (7, 'CLO3', 'Thể hiện thái độ học tập nghiêm túc và an toàn.', 'Thái độ', '3. Nội tâm hóa', 'M'),
(8, 'CLO1', 'Trình bày được kiến thức cốt lõi của học phần.', 'Kiến thức', '2. Hiểu', 'I'), (8, 'CLO2', 'Thực hiện được kỹ năng cơ bản liên quan học phần.', 'Kỹ năng', '3. Làm chính xác', 'R'), (8, 'CLO3', 'Thể hiện thái độ học tập nghiêm túc và an toàn.', 'Thái độ', '3. Nội tâm hóa', 'M'),
(9, 'CLO1', 'Trình bày được kiến thức cốt lõi của học phần.', 'Kiến thức', '2. Hiểu', 'I'), (9, 'CLO2', 'Thực hiện được kỹ năng cơ bản liên quan học phần.', 'Kỹ năng', '3. Làm chính xác', 'R'), (9, 'CLO3', 'Thể hiện thái độ học tập nghiêm túc và an toàn.', 'Thái độ', '3. Nội tâm hóa', 'M'),
(10, 'CLO1', 'Trình bày được kiến thức cốt lõi của học phần.', 'Kiến thức', '2. Hiểu', 'I'), (10, 'CLO2', 'Thực hiện được kỹ năng cơ bản liên quan học phần.', 'Kỹ năng', '3. Làm chính xác', 'R'), (10, 'CLO3', 'Thể hiện thái độ học tập nghiêm túc và an toàn.', 'Thái độ', '3. Nội tâm hóa', 'M');

INSERT INTO `plos` (`id`, `code`, `content`) VALUES
(1, 'PLO1', 'Đạt được chuẩn đầu ra chương trình về nền tảng kiến thức chuyên môn.'),
(2, 'PLO2', 'Đạt được chuẩn đầu ra chương trình về kỹ năng nghề nghiệp và thực hành.'),
(3, 'PLO3', 'Đạt được chuẩn đầu ra chương trình về thái độ, an toàn và trách nhiệm nghề nghiệp.');

INSERT INTO `pis` (`id`, `plo_id`, `code`, `content`) VALUES
(1, 1, 'PI1.1', 'Trình bày và vận dụng được các kiến thức nền tảng theo chuẩn PLO1.'),
(2, 1, 'PI1.2', 'Phân tích và giải thích được các vấn đề chuyên môn theo chuẩn PLO1.'),
(3, 1, 'PI1.3', 'Tích hợp kiến thức nền tảng vào bối cảnh học tập và thực hành theo chuẩn PLO1.'),
(4, 2, 'PI2.1', 'Thực hiện được kỹ năng chuyên môn cơ bản theo chuẩn PLO2.'),
(5, 2, 'PI2.2', 'Phối hợp và giải quyết tình huống nghề nghiệp theo chuẩn PLO2.'),
(6, 2, 'PI2.3', 'Ứng dụng công cụ và quy trình thực hành phù hợp theo chuẩn PLO2.'),
(7, 3, 'PI3.1', 'Thể hiện thái độ học tập và hành nghề nghiêm túc theo chuẩn PLO3.'),
(8, 3, 'PI3.2', 'Tuân thủ nguyên tắc đạo đức, an toàn và trách nhiệm nghề nghiệp theo chuẩn PLO3.'),
(9, 3, 'PI3.3', 'Phát triển năng lực tự học và cải tiến bản thân theo chuẩn PLO3.');

INSERT INTO `clo_plos` (`clo_id`, `plo_id`)
SELECT `id`, 1 FROM `clos` WHERE `code` = 'CLO1'
UNION ALL
SELECT `id`, 2 FROM `clos` WHERE `code` = 'CLO2'
UNION ALL
SELECT `id`, 3 FROM `clos` WHERE `code` = 'CLO2'
UNION ALL
SELECT `id`, 3 FROM `clos` WHERE `code` = 'CLO3';

INSERT INTO `assessment_tools` (`id`, `name`, `description`) VALUES
(1, 'Danh sách lớp', 'Ghi nhận chuyên cần và sự hiện diện của người học.'),
(2, 'Phiếu quan sát trên lớp', 'Theo dõi mức độ tham gia, thái độ và chuẩn bị bài.'),
(3, 'Rubric', 'Bảng tiêu chí chấm điểm cho bài tập hoặc trình bày.'),
(4, 'Checklist đánh giá', 'Bảng kiểm theo dõi mức độ hoàn thành yêu cầu.'),
(5, 'Ngân hàng câu hỏi', 'Bộ câu hỏi dùng cho đánh giá trắc nghiệm hoặc tự luận.'),
(6, 'Đáp án và thang điểm', 'Tiêu chí và barem chấm cho thi kết thúc.');

INSERT INTO `assessments` (`module_id`, `component`, `weight`) VALUES
(1,'Chuyên cần',10),(1,'Kiểm tra thường xuyên',30),(1,'Thi kết thúc',60),
(2,'Chuyên cần',10),(2,'Kiểm tra thường xuyên',30),(2,'Thi kết thúc',60),
(3,'Chuyên cần',10),(3,'Kiểm tra thường xuyên',30),(3,'Thi kết thúc',60),
(4,'Chuyên cần',10),(4,'Kiểm tra thường xuyên',30),(4,'Thi kết thúc',60),
(5,'Chuyên cần',10),(5,'Kiểm tra thường xuyên',30),(5,'Thi kết thúc',60),
(6,'Chuyên cần',10),(6,'Kiểm tra thường xuyên',30),(6,'Thi kết thúc',60),
(7,'Chuyên cần',10),(7,'Kiểm tra thường xuyên',30),(7,'Thi kết thúc',60),
(8,'Chuyên cần',10),(8,'Kiểm tra thường xuyên',30),(8,'Thi kết thúc',60),
(9,'Chuyên cần',10),(9,'Kiểm tra thường xuyên',30),(9,'Thi kết thúc',60),
(10,'Chuyên cần',10),(10,'Kiểm tra thường xuyên',30),(10,'Thi kết thúc',60);

INSERT INTO `assessment_form_relation` (`assessment_id`, `assessment_form_id`, `sort_order`, `note`)
SELECT a.id, 1, 1, NULL FROM assessments a WHERE a.component = 'Chuyên cần'
UNION ALL
SELECT a.id, 2, 2, NULL FROM assessments a WHERE a.component = 'Chuyên cần'
UNION ALL
SELECT a.id, 3, 1, NULL FROM assessments a WHERE a.component = 'Kiểm tra thường xuyên'
UNION ALL
SELECT a.id, 4, 2, NULL FROM assessments a WHERE a.component = 'Kiểm tra thường xuyên'
UNION ALL
SELECT a.id, 5, 1, NULL FROM assessments a WHERE a.component = 'Thi kết thúc'
UNION ALL
SELECT a.id, 6, 2, NULL FROM assessments a WHERE a.component = 'Thi kết thúc';

INSERT INTO `assessment_clos` (`assessment_id`, `clo_id`)
SELECT a.id, c.id
FROM assessments a
JOIN clos c ON c.module_id = a.module_id
WHERE (a.component = 'Chuyên cần' AND c.code = 'CLO3')
   OR (a.component = 'Kiểm tra thường xuyên' AND c.code IN ('CLO1', 'CLO2'))
   OR (a.component = 'Thi kết thúc' AND c.code IN ('CLO1', 'CLO2'));

INSERT INTO `assessment_tool_relation` (`assessment_id`, `tool_id`, `sort_order`, `note`)
SELECT a.id, 1, 1, NULL FROM assessments a WHERE a.component = 'Chuyên cần'
UNION ALL
SELECT a.id, 2, 2, 'Theo dõi mức độ tham gia trên lớp' FROM assessments a WHERE a.component = 'Chuyên cần'
UNION ALL
SELECT a.id, 3, 1, NULL FROM assessments a WHERE a.component = 'Kiểm tra thường xuyên'
UNION ALL
SELECT a.id, 4, 2, NULL FROM assessments a WHERE a.component = 'Kiểm tra thường xuyên'
UNION ALL
SELECT a.id, 5, 1, NULL FROM assessments a WHERE a.component = 'Thi kết thúc'
UNION ALL
SELECT a.id, 6, 2, NULL FROM assessments a WHERE a.component = 'Thi kết thúc';

INSERT INTO `self_study_activities` (`module_id`, `activity_name`, `duration_hours`, `method`, `assessment_method`, `evidence`) VALUES
(1,'Đọc tài liệu trước buổi học',8,'Đọc giáo trình và ghi chú','Quiz đầu giờ','Phiếu trả lời'),(1,'Làm bài tập tự học',10,'Làm bài tập trên LMS','Chấm bài nộp','File bài tập'),
(2,'Đọc tài liệu trước buổi học',8,'Đọc giáo trình và ghi chú','Quiz đầu giờ','Phiếu trả lời'),(2,'Làm bài tập tự học',10,'Làm bài tập trên LMS','Chấm bài nộp','File bài tập'),
(3,'Đọc tài liệu trước buổi học',8,'Đọc giáo trình và ghi chú','Quiz đầu giờ','Phiếu trả lời'),(3,'Làm bài tập tự học',10,'Làm bài tập trên LMS','Chấm bài nộp','File bài tập'),
(4,'Đọc tài liệu trước buổi học',8,'Đọc giáo trình và ghi chú','Quiz đầu giờ','Phiếu trả lời'),(4,'Làm bài tập tự học',10,'Làm bài tập trên LMS','Chấm bài nộp','File bài tập'),
(5,'Đọc tài liệu trước buổi học',8,'Đọc giáo trình và ghi chú','Quiz đầu giờ','Phiếu trả lời'),(5,'Làm bài tập tự học',10,'Làm bài tập trên LMS','Chấm bài nộp','File bài tập'),
(6,'Đọc tài liệu trước buổi học',8,'Đọc giáo trình và ghi chú','Quiz đầu giờ','Phiếu trả lời'),(6,'Làm bài tập tự học',10,'Làm bài tập trên LMS','Chấm bài nộp','File bài tập'),
(7,'Đọc tài liệu trước buổi học',8,'Đọc giáo trình và ghi chú','Quiz đầu giờ','Phiếu trả lời'),(7,'Làm bài tập tự học',10,'Làm bài tập trên LMS','Chấm bài nộp','File bài tập'),
(8,'Đọc tài liệu trước buổi học',8,'Đọc giáo trình và ghi chú','Quiz đầu giờ','Phiếu trả lời'),(8,'Làm bài tập tự học',10,'Làm bài tập trên LMS','Chấm bài nộp','File bài tập'),
(9,'Đọc tài liệu trước buổi học',8,'Đọc giáo trình và ghi chú','Quiz đầu giờ','Phiếu trả lời'),(9,'Làm bài tập tự học',10,'Làm bài tập trên LMS','Chấm bài nộp','File bài tập'),
(10,'Đọc tài liệu trước buổi học',8,'Đọc giáo trình và ghi chú','Quiz đầu giờ','Phiếu trả lời'),(10,'Làm bài tập tự học',10,'Làm bài tập trên LMS','Chấm bài nộp','File bài tập');

INSERT INTO `self_study_clos` (`self_study_activity_id`, `clo_id`)
SELECT s.id, c.id FROM self_study_activities s JOIN clos c ON c.module_id = s.module_id AND c.code IN ('CLO1', 'CLO2');

INSERT INTO `theory_topics` (`id`, `module_id`, `parent_id`, `chapter`, `title`, `delivery_mode`, `teaching_method`, `class_hours`, `online_hours`, `self_study_hours`) VALUES
(1,1,NULL,'Chương 1','Tổng quan học phần','Học trên lớp','Thuyết trình kết hợp hỏi đáp',4,0,8),(2,1,1,'Bài 1','Nội dung cốt lõi 1','Học trực tuyến','Thảo luận trên LMS',0,6,10),
(3,2,NULL,'Chương 1','Tổng quan học phần','Học trên lớp','Thuyết trình kết hợp hỏi đáp',4,0,8),(4,2,3,'Bài 1','Nội dung cốt lõi 1','Học trực tuyến','Thảo luận trên LMS',0,6,10),
(5,3,NULL,'Chương 1','Tổng quan học phần','Học trên lớp','Thuyết trình kết hợp hỏi đáp',4,0,8),(6,3,5,'Bài 1','Nội dung cốt lõi 1','Học trực tuyến','Thảo luận trên LMS',0,6,10),
(7,4,NULL,'Chương 1','Tổng quan học phần','Học trên lớp','Thuyết trình kết hợp hỏi đáp',4,0,8),(8,4,7,'Bài 1','Nội dung cốt lõi 1','Học trực tuyến','Thảo luận trên LMS',0,6,10),
(9,5,NULL,'Chương 1','Tổng quan học phần','Học trên lớp','Thuyết trình kết hợp hỏi đáp',4,0,8),(10,5,9,'Bài 1','Nội dung cốt lõi 1','Học trực tuyến','Thảo luận trên LMS',0,6,10),
(11,6,NULL,'Chương 1','Tổng quan học phần','Học trên lớp','Thuyết trình kết hợp hỏi đáp',4,0,8),(12,6,11,'Bài 1','Nội dung cốt lõi 1','Học trực tuyến','Thảo luận trên LMS',0,6,10),
(13,7,NULL,'Chương 1','Tổng quan học phần','Học trên lớp','Thuyết trình kết hợp hỏi đáp',4,0,8),(14,7,13,'Bài 1','Nội dung cốt lõi 1','Học trực tuyến','Thảo luận trên LMS',0,6,10),
(15,8,NULL,'Chương 1','Tổng quan học phần','Học trên lớp','Thuyết trình kết hợp hỏi đáp',4,0,8),(16,8,15,'Bài 1','Nội dung cốt lõi 1','Học trực tuyến','Thảo luận trên LMS',0,6,10),
(17,9,NULL,'Chương 1','Tổng quan học phần','Học trên lớp','Thuyết trình kết hợp hỏi đáp',4,0,8),(18,9,17,'Bài 1','Nội dung cốt lõi 1','Học trực tuyến','Thảo luận trên LMS',0,6,10),
(19,10,NULL,'Chương 1','Tổng quan học phần','Học trên lớp','Thuyết trình kết hợp hỏi đáp',4,0,8),(20,10,19,'Bài 1','Nội dung cốt lõi 1','Học trực tuyến','Thảo luận trên LMS',0,6,10);

INSERT INTO `theory_topic_clos` (`theory_topic_id`, `clo_id`)
SELECT t.id, c.id FROM theory_topics t JOIN clos c ON c.module_id = t.module_id AND c.code IN ('CLO1', 'CLO2');

INSERT INTO `practical_topics` (`module_id`, `topic`, `content`, `delivery_mode`, `teaching_method`, `lab_hours`, `facility_id`) VALUES
(1,'Thực hành 1','Nhận diện cấu trúc/mô hình chính','Học trên lớp','Thực hành có hướng dẫn',4,1),(1,'Thực hành 2','Hoàn thành bảng kiểm kỹ năng','Học trực tuyến','Bài tập thực hành trên LMS',5,2),
(2,'Thực hành 1','Đo và phân tích thông số sinh lý','Học trên lớp','Thực hành có hướng dẫn',4,4),(2,'Thực hành 2','Hoàn thành bảng kiểm kỹ năng','Học trực tuyến','Bài tập thực hành trên LMS',5,2),
(3,'Thực hành 1','Quan sát tiêu bản và mô tả tổn thương','Học trên lớp','Thực hành có hướng dẫn',4,1),(3,'Thực hành 2','Hoàn thành bảng kiểm kỹ năng','Học trực tuyến','Bài tập thực hành trên LMS',5,2),
(4,'Thực hành 1','Khám hệ cơ quan theo quy trình','Học trên lớp','Thực hành có hướng dẫn',4,2),(4,'Thực hành 2','Hoàn thành bảng kiểm kỹ năng','Học trực tuyến','Bài tập thực hành trên LMS',5,2),
(5,'Thực hành 1','Phân tích cấu trúc hóa học mẫu','Học trên lớp','Thực hành có hướng dẫn',4,5),(5,'Thực hành 2','Hoàn thành bảng kiểm kỹ năng','Học trực tuyến','Bài tập thực hành trên LMS',5,5),
(6,'Thực hành 1','Phân tích đơn thuốc và tương tác thuốc','Học trên lớp','Thực hành có hướng dẫn',4,5),(6,'Thực hành 2','Hoàn thành bảng kiểm kỹ năng','Học trực tuyến','Bài tập thực hành trên LMS',5,5),
(7,'Thực hành 1','Lập kế hoạch cung ứng thuốc','Học trên lớp','Thực hành có hướng dẫn',4,5),(7,'Thực hành 2','Hoàn thành bảng kiểm kỹ năng','Học trực tuyến','Bài tập thực hành trên LMS',5,5),
(8,'Thực hành 1','Thực hiện kỹ thuật chăm sóc cơ bản','Học trên lớp','Thực hành có hướng dẫn',4,6),(8,'Thực hành 2','Hoàn thành bảng kiểm kỹ năng','Học trực tuyến','Bài tập thực hành trên LMS',5,6),
(9,'Thực hành 1','Lập kế hoạch chăm sóc người bệnh','Học trên lớp','Thực hành có hướng dẫn',4,6),(9,'Thực hành 2','Hoàn thành bảng kiểm kỹ năng','Học trực tuyến','Bài tập thực hành trên LMS',5,6),
(10,'Thực hành 1','Nhập và xử lý bộ dữ liệu mẫu','Học trên lớp','Thực hành có hướng dẫn',4,3),(10,'Thực hành 2','Hoàn thành báo cáo dữ liệu','Học trực tuyến','Bài tập thực hành trên LMS',5,3);

INSERT INTO `practical_topic_clos` (`practical_topic_id`, `clo_id`)
SELECT p.id, c.id FROM practical_topics p JOIN clos c ON c.module_id = p.module_id AND c.code IN ('CLO2', 'CLO3');

INSERT INTO `combined_topics` (`module_id`, `sort_order`, `content`, `delivery_mode`, `teaching_method`, `theory_hours`, `practical_hours`, `self_study_hours`, `facility_id`) VALUES
(1,1,'Tích hợp lý thuyết và thực hành tình huống 1','Học trên lớp','Dạy học theo tình huống',2,3,5,1),(1,2,'Tổng kết học phần và phản hồi','Học trực tuyến','Thảo luận và phản hồi trên LMS',1,2,4,2),
(2,1,'Tích hợp lý thuyết và thực hành tình huống 1','Học trên lớp','Dạy học theo tình huống',2,3,5,4),(2,2,'Tổng kết học phần và phản hồi','Học trực tuyến','Thảo luận và phản hồi trên LMS',1,2,4,2),
(3,1,'Tích hợp lý thuyết và thực hành tình huống 1','Học trên lớp','Dạy học theo tình huống',2,3,5,1),(3,2,'Tổng kết học phần và phản hồi','Học trực tuyến','Thảo luận và phản hồi trên LMS',1,2,4,2),
(4,1,'Tích hợp lý thuyết và thực hành tình huống 1','Học trên lớp','Dạy học theo tình huống',2,3,5,2),(4,2,'Tổng kết học phần và phản hồi','Học trực tuyến','Thảo luận và phản hồi trên LMS',1,2,4,2),
(5,1,'Tích hợp lý thuyết và thực hành tình huống 1','Học trên lớp','Dạy học theo tình huống',2,3,5,5),(5,2,'Tổng kết học phần và phản hồi','Học trực tuyến','Thảo luận và phản hồi trên LMS',1,2,4,5),
(6,1,'Tích hợp lý thuyết và thực hành tình huống 1','Học trên lớp','Dạy học theo tình huống',2,3,5,5),(6,2,'Tổng kết học phần và phản hồi','Học trực tuyến','Thảo luận và phản hồi trên LMS',1,2,4,5),
(7,1,'Tích hợp lý thuyết và thực hành tình huống 1','Học trên lớp','Dạy học theo tình huống',2,3,5,5),(7,2,'Tổng kết học phần và phản hồi','Học trực tuyến','Thảo luận và phản hồi trên LMS',1,2,4,5),
(8,1,'Tích hợp lý thuyết và thực hành tình huống 1','Học trên lớp','Dạy học theo tình huống',2,3,5,6),(8,2,'Tổng kết học phần và phản hồi','Học trực tuyến','Thảo luận và phản hồi trên LMS',1,2,4,6),
(9,1,'Tích hợp lý thuyết và thực hành tình huống 1','Học trên lớp','Dạy học theo tình huống',2,3,5,6),(9,2,'Tổng kết học phần và phản hồi','Học trực tuyến','Thảo luận và phản hồi trên LMS',1,2,4,6),
(10,1,'Tích hợp lý thuyết và thực hành tình huống 1','Học trên lớp','Dạy học theo tình huống',2,3,5,3),(10,2,'Tổng kết học phần và phản hồi','Học trực tuyến','Thảo luận và phản hồi trên LMS',1,2,4,3);

INSERT INTO `combined_topic_clos` (`combined_topic_id`, `clo_id`)
SELECT cb.id, c.id FROM combined_topics cb JOIN clos c ON c.module_id = cb.module_id;

INSERT INTO `resources` (`module_id`, `resource_type`, `sort_order`, `title`, `editor`, `publisher`, `year`, `identifier`) VALUES
(1,'Tài liệu giảng dạy',1,'Giáo trình Giải phẫu học đại cương','Nguyễn Văn A','NXB Y học','2025', 1),
(1,'Tài liệu tự học',1,'Atlas thực hành giải phẫu','Trần Thị B','NXB Y học','2024', NULL),
(2,'Tài liệu giảng dạy',1,'Giáo trình Sinh lý học đại cương','Nguyễn Văn A','NXB Y học','2025', 2),
(2,'Tài liệu tự học',1,'Bài tập sinh lý học','Trần Thị B','NXB Y học','2024', NULL),
(3,'Tài liệu giảng dạy',1,'Giáo trình Bệnh học cơ sở','Nguyễn Văn A','NXB Y học','2025', 3),
(3,'Tài liệu tự học',1,'Tập tình huống bệnh học','Trần Thị B','NXB Y học','2024', NULL),
(4,'Tài liệu giảng dạy',1,'Giáo trình Kỹ năng khám lâm sàng','Nguyễn Văn A','NXB Y học','2025', 4),
(4,'Tài liệu tự học',1,'Bảng kiểm khám lâm sàng','Trần Thị B','NXB Y học','2024', NULL),
(5,'Tài liệu giảng dạy',1,'Giáo trình Hóa dược cơ bản','Nguyễn Văn A','NXB Y học','2025', 5),
(5,'Tài liệu tự học',1,'Bài tập hóa dược','Trần Thị B','NXB Y học','2024', NULL),
(6,'Tài liệu giảng dạy',1,'Giáo trình Dược lý lâm sàng','Nguyễn Văn A','NXB Y học','2025', 6),
(6,'Tài liệu tự học',1,'Case study dược lý','Trần Thị B','NXB Y học','2024', NULL),
(7,'Tài liệu giảng dạy',1,'Giáo trình Quản lý cung ứng thuốc','Nguyễn Văn A','NXB Y học','2025', 7),
(7,'Tài liệu tự học',1,'Bài tập quản lý tồn kho thuốc','Trần Thị B','NXB Y học','2024', NULL),
(8,'Tài liệu giảng dạy',1,'Giáo trình Điều dưỡng cơ bản','Nguyễn Văn A','NXB Y học','2025', 8),
(8,'Tài liệu tự học',1,'Bảng kiểm kỹ thuật điều dưỡng','Trần Thị B','NXB Y học','2024', NULL),
(9,'Tài liệu giảng dạy',1,'Giáo trình Chăm sóc nội khoa','Nguyễn Văn A','NXB Y học','2025', 9),
(9,'Tài liệu tự học',1,'Kế hoạch chăm sóc mẫu','Trần Thị B','NXB Y học','2024', NULL),
(10,'Tài liệu giảng dạy',1,'Giáo trình Tin học ứng dụng y học','Nguyễn Văn A','NXB Y học','2025', 10),
(10,'Tài liệu tự học',1,'Bài tập xử lý dữ liệu y học','Trần Thị B','NXB Y học','2024', NULL);

INSERT INTO `books_catalog` (`id`, `title`, `editor`, `publisher`, `year`, `identifier`) VALUES
(1,'Giáo trình Giải phẫu học đại cương','Nguyễn Văn A','NXB Y học','2025', 1),
(2,'Giáo trình Sinh lý học đại cương','Nguyễn Văn A','NXB Y học','2025', 2),
(3,'Giáo trình Bệnh học cơ sở','Nguyễn Văn A','NXB Y học','2025', 3),
(4,'Giáo trình Kỹ năng khám lâm sàng','Nguyễn Văn A','NXB Y học','2025', 4),
(5,'Giáo trình Hóa dược cơ bản','Nguyễn Văn A','NXB Y học','2025', 5),
(6,'Giáo trình Dược lý lâm sàng','Nguyễn Văn A','NXB Y học','2025', 6),
(7,'Giáo trình Quản lý cung ứng thuốc','Nguyễn Văn A','NXB Y học','2025', 7),
(8,'Giáo trình Điều dưỡng cơ bản','Nguyễn Văn A','NXB Y học','2025', 8),
(9,'Giáo trình Chăm sóc nội khoa','Nguyễn Văn A','NXB Y học','2025', 9),
(10,'Giáo trình Tin học ứng dụng y học','Nguyễn Văn A','NXB Y học','2025', 10);
INSERT INTO `knowledge_blocks` (`id`, `major_id`, `name`, `parent_id`) VALUES
(8, 1, 'Giải phẫu - Mô phôi chuyên sâu', 2),
(9, 1, 'Sinh lý bệnh - Miễn dịch và Vi sinh', 2);
INSERT INTO `knowledge_blocks` (`id`, `major_id`, `name`, `parent_id`) VALUES
(10, 1, 'Khối các học phần Ngoại khoa', 3),
(11, 1, 'Khối các học phần Sản - Nhi', 3),
(12, 1, 'Khối các học phần Chuyên khoa lẻ', 3);
INSERT INTO `knowledge_blocks` (`id`, `major_id`, `name`, `parent_id`) VALUES
(13, 2, 'Sinh học ứng dụng và Dược liệu', 4),
(14, 2, 'Hóa học chuyên ngành Dược', 4);
INSERT INTO `knowledge_blocks` (`id`, `major_id`, `name`, `parent_id`) VALUES
(15, 2, 'Dược lâm sàng và Điều trị học', 5),
(16, 2, 'Công nghệ Dược và Kiểm nghiệm thuốc', 5),
(17, 2, 'Kinh tế và Quản lý Dược', 5);
INSERT INTO `knowledge_blocks` (`id`, `major_id`, `name`, `parent_id`) VALUES
(18, 3, 'Khoa học hành vi và Sức khỏe cộng đồng', 6),
(19, 3, 'Kiến thức hỗ trợ điều trị', 6);
INSERT INTO `knowledge_blocks` (`id`, `major_id`, `name`, `parent_id`) VALUES
(20, 3, 'Điều dưỡng chuyên sâu Ngoại - Sản - Nhi', 7),
(21, 3, 'Điều dưỡng chuyên khoa và Hồi sức cấp cứu', 7),
(22, 3, 'Quản lý và Nghiên cứu khoa học Điều dưỡng', 7);

INSERT INTO `courses` (`id`, `major_id`, `block_id`, `code`, `name`, `total_hours`, `theory_hours`, `practical_hours`, `sort_order`) VALUES
(11, 1, 1, 'MED011', 'Đạo đức Y học và Tâm lý y pháp', 30, 20, 10, 11),
(12, 1, 1, 'MED012', 'Xác suất thống kê trong Y sinh', 45, 30, 15, 12),
(13, 1, 8, 'MED013', 'Mô phôi học người', 45, 30, 15, 13),
(14, 1, 8, 'MED014', 'Giải phẫu bệnh chuyên biệt', 60, 40, 20, 14),
(15, 1, 9, 'MED015', 'Sinh lý bệnh ứng dụng lâm sàng', 45, 30, 15, 15),
(16, 1, 9, 'MED016', 'Vi sinh vật y học và Ký sinh trùng', 60, 40, 20, 16),
(17, 1, 9, 'MED017', 'Miễn dịch học đại cương', 30, 20, 10, 17),
(18, 1, 9, 'MED018', 'Dược lý học cơ sở Y khoa', 60, 45, 15, 18),
(19, 1, 10, 'MED019', 'Bệnh học Ngoại khoa toàn thân', 60, 45, 15, 19),
(20, 1, 10, 'MED020', 'Phẫu thuật thực hành cơ bản', 45, 15, 30, 20),
(21, 1, 10, 'MED021', 'Chấn thương chỉnh hình đại cương', 45, 30, 15, 21),
(22, 1, 11, 'MED022', 'Sản khoa và Phụ khoa lâm sàng', 60, 40, 20, 22),
(23, 1, 11, 'MED023', 'Nhi khoa lý thuyết và Thực hành', 60, 40, 20, 23),
(24, 1, 11, 'MED024', 'Sơ sinh học cơ bản', 30, 20, 10, 24),
(25, 1, 12, 'MED025', 'Tai Mũi Họng đại cương', 30, 20, 10, 25),
(26, 1, 12, 'MED026', 'Răng Hàm Mặt cơ sở', 30, 20, 10, 26),
(27, 1, 12, 'MED027', 'Mắt và Nhãn khoa lâm sàng', 30, 20, 10, 27),
(28, 1, 12, 'MED028', 'Da liễu và Bệnh lây truyền qua đường tình dục', 30, 20, 10, 28),
(29, 1, 12, 'MED029', 'Thần kinh học lâm sàng', 45, 30, 15, 29),
(30, 1, 12, 'MED030', 'Tâm thần học và Sức khỏe tâm thần', 45, 30, 15, 30),
(31, 2, 13, 'PHAR031', 'Thực vật dược phân loại', 45, 30, 15, 31),
(32, 2, 13, 'PHAR032', 'Dược liệu học I', 60, 40, 20, 32),
(33, 2, 13, 'PHAR032B', 'Dược liệu học II', 45, 30, 15, 33),
(34, 2, 13, 'PHAR034', 'Sinh học phân tử và Di truyền', 30, 20, 10, 34),
(35, 2, 14, 'PHAR035', 'Hóa phân tích định lượng', 60, 30, 30, 35),
(36, 2, 14, 'PHAR036', 'Hóa hữu cơ nâng cao ngành Dược', 45, 30, 15, 36),
(37, 2, 14, 'PHAR037', 'Hóa sinh dược phẩm', 45, 30, 15, 37),
(38, 2, 14, 'PHAR038', 'Độc chất học đại cương', 30, 20, 10, 38),
(39, 2, 15, 'PHAR039', 'Dược lâm sàng nâng cao', 45, 30, 15, 39),
(40, 2, 15, 'PHAR040', 'Dược động học lâm sàng', 30, 20, 10, 40),
(41, 2, 15, 'PHAR041', 'Dược lý bệnh học và Điều trị học I', 60, 45, 15, 41),
(42, 2, 15, 'PHAR042', 'Dược lý bệnh học và Điều trị học II', 45, 30, 15, 42),
(43, 2, 16, 'PHAR043', 'Bào chế và Sinh dược học I', 60, 40, 20, 43),
(44, 2, 16, 'PHAR044', 'Bào chế và Sinh dược học II', 45, 30, 15, 44),
(45, 2, 16, 'PHAR045', 'Kiểm nghiệm thuốc và Mỹ phẩm', 60, 30, 30, 45),
(46, 2, 16, 'PHAR046', 'Công nghệ sinh học trong Sản xuất thuốc', 45, 30, 15, 46),
(47, 2, 17, 'PHAR047', 'Kinh tế Dược đại cương', 30, 20, 10, 47),
(48, 2, 17, 'PHAR048', 'Pháp luật và Thực hành tốt Nhà thuốc (GPP)', 30, 30, 0, 48),
(49, 2, 17, 'PHAR049', 'Marketing Dược và Kỹ năng giao tiếp', 45, 30, 15, 49),
(50, 2, 17, 'PHAR050', 'Quản trị chuỗi cung ứng dược phẩm', 45, 30, 15, 50),
(51, 3, 18, 'NUR051', 'Tâm lý học và Giao tiếp trong Điều dưỡng', 30, 20, 10, 51),
(52, 3, 18, 'NUR052', 'Giáo dục sức khỏe và Thực hành nâng cao', 30, 20, 10, 52),
(53, 3, 18, 'NUR053', 'Dịch tễ học cơ bản cho Điều dưỡng', 30, 20, 10, 53),
(54, 3, 18, 'NUR054', 'Điều dưỡng môi trường và Kiểm soát bệnh', 45, 30, 15, 54),
(55, 3, 19, 'NUR055', 'Dược lý đại cương cho Điều dưỡng', 30, 20, 10, 55),
(56, 3, 19, 'NUR056', 'Dinh dưỡng và Tiết chế lâm sàng', 30, 20, 10, 56),
(57, 3, 19, 'NUR057', 'Xét nghiệm cận lâm sàng ứng dụng', 30, 20, 10, 57),
(58, 3, 19, 'NUR058', 'Vi sinh - Ký sinh trùng cơ sở', 30, 20, 10, 58),
(59, 3, 20, 'NUR059', 'Chăm sóc người bệnh Ngoại khoa toàn diện', 60, 30, 30, 59),
(60, 3, 20, 'NUR060', 'Chăm sóc sức khỏe Phụ nữ và Bà mẹ', 45, 20, 25, 60),
(61, 3, 20, 'NUR061', 'Chăm sóc sức khỏe Trẻ em và Sơ sinh', 45, 20, 25, 61),
(62, 3, 21, 'NUR062', 'Điều dưỡng Hồi sức cấp cứu và Chống độc', 60, 30, 30, 62),
(63, 3, 21, 'NUR063', 'Chăm sóc người bệnh chuyên khoa lẻ', 45, 25, 20, 63),
(64, 3, 21, 'NUR064', 'Điều dưỡng chăm sóc Người cao tuổi', 30, 20, 10, 64),
(65, 3, 21, 'NUR065', 'Chăm sóc giảm nhẹ và Bệnh nan y', 30, 20, 10, 65),
(66, 3, 21, 'NUR066', 'Điều dưỡng Sức khỏe Tâm thần', 30, 20, 10, 66),
(67, 3, 22, 'NUR067', 'Quản lý Điều dưỡng và Tổ chức bệnh viện', 30, 20, 10, 67),
(68, 3, 22, 'NUR068', 'Nghiên cứu khoa học và Thực hành dựa vào bằng chứng', 45, 25, 20, 68),
(69, 3, 22, 'NUR069', 'Pháp luật và Đạo đức nghề nghiệp Điều dưỡng', 30, 30, 0, 69),
(70, 3, 22, 'NUR070', 'Lãnh đạo và Kỹ năng làm việc nhóm', 30, 20, 10, 70);

INSERT INTO `courses` (`id`, `major_id`, `block_id`, `code`, `name`, `total_hours`, `theory_hours`, `practical_hours`, `sort_order`) VALUES
(71, 1, 1, 'MED071', 'Tiếng Anh chuyên ngành Y khoa', 45, 30, 15, 71),
(72, 1, 1, 'MED072', 'Kỹ năng giao tiếp trong y tế', 30, 20, 10, 72),
(73, 1, 2, 'MED073', 'Lịch sử Y học thế giới và Việt Nam', 30, 30, 0, 73),
(74, 1, 2, 'MED074', 'Ứng dụng trí tuệ nhân tạo trong Y tế', 45, 15, 30, 74),
(75, 1, 3, 'MED075', 'Dinh dưỡng tiết chế bệnh lý', 30, 20, 10, 75),
(76, 1, 3, 'MED076', 'Y học hạt nhân cơ bản', 30, 20, 10, 76),
(77, 1, 10, 'MED077', 'Phẫu thuật nội soi đại cương', 45, 15, 30, 77),
(78, 1, 11, 'MED078', 'Sức khỏe sinh sản vị thành niên', 30, 20, 10, 78),
(79, 1, 12, 'MED079', 'Ung thư học lâm sàng đại cương', 45, 30, 15, 79),
(80, 1, 12, 'MED080', 'Y học gia đình và Chăm sóc ban đầu', 45, 30, 15, 80),
(81, 2, 4, 'PHAR081', 'Pháp chế Dược nâng cao', 30, 30, 0, 81),
(82, 2, 4, 'PHAR082', 'Phương pháp nghiên cứu khoa học Dược', 45, 30, 15, 82),
(83, 2, 13, 'PHAR083', 'Dược liệu biển và Động vật làm thuốc', 30, 20, 10, 83),
(84, 2, 14, 'PHAR084', 'Hóa học các hợp chất thiên nhiên', 45, 30, 15, 84),
(85, 2, 15, 'PHAR085', 'Thông tin thuốc và Cảnh giác Dược', 45, 30, 15, 85),
(86, 2, 15, 'PHAR086', 'Độc học lâm sàng nâng cao', 30, 20, 10, 86),
(87, 2, 16, 'PHAR087', 'Đảm bảo chất lượng trong sản xuất thuốc', 45, 30, 15, 87),
(88, 2, 16, 'PHAR088', 'Mỹ phẩm học và Thực phẩm chức năng', 45, 30, 15, 88),
(89, 2, 17, 'PHAR089', 'Quản trị doanh nghiệp Dược', 30, 20, 10, 89),
(90, 2, 17, 'PHAR090', 'Dược xã hội học', 30, 30, 0, 90),
(91, 3, 6, 'NUR091', 'Đạo đức và Luật pháp trong thực hành Điều dưỡng', 30, 30, 0, 91),
(92, 3, 6, 'NUR092', 'Lịch sử và Xu hướng phát triển Điều dưỡng', 30, 30, 0, 92),
(93, 3, 18, 'NUR093', 'Điều dưỡng dựa vào bằng chứng', 45, 30, 15, 93),
(94, 3, 19, 'NUR094', 'Giải phẫu sinh lý ứng dụng trong Điều dưỡng', 45, 30, 15, 94),
(95, 3, 20, 'NUR095', 'Chăm sóc toàn diện người bệnh bỏng', 30, 20, 10, 95),
(96, 3, 20, 'NUR096', 'Chăm sóc sức khỏe phụ nữ mãn kinh', 30, 20, 10, 96),
(97, 3, 21, 'NUR097', 'Điều dưỡng kiểm soát nhiễm khuẩn bệnh viện', 45, 20, 25, 97),
(98, 3, 21, 'NUR098', 'Quản lý vết thương và Chăm sóc giảm nhẹ', 45, 20, 25, 98),
(99, 3, 22, 'NUR099', 'Ứng dụng Tin học trong Quản lý Điều dưỡng', 45, 15, 30, 99),
(100, 3, 22, 'NUR100', 'Kiểm định và Đảm bảo chất lượng Điều dưỡng', 30, 20, 10, 100);
INSERT INTO `modules` (`id`, `course_id`, `code`, `name`, `type`, `credits`, `credits_theory`, `credits_practice`, `total_hours`, `theory_hours`, `practical_hours`, `self_study_hours`, `target_programs`, `expected_semester`, `expected_year`, `department_in_charge`, `faculty_in_charge`, `objective_general`, `objective_po`, `objective_plo`, `grading_scale`) VALUES
(11, 11, 'MED011', 'Đạo đức Y học và Tâm lý y pháp', 'Bắt buộc', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Y khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Tâm thần', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(12, 12, 'MED012', 'Xác suất thống kê trong Y sinh', 'Bắt buộc', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Y khoa', 'Học kỳ II', '2026-2027', 'Trung tâm Công nghệ thông tin', 'Khoa Khoa học cơ bản', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(13, 13, 'MED013', 'Mô phôi học người', 'Bắt buộc', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Y khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Giải phẫu', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(14, 14, 'MED014', 'Giải phẫu bệnh chuyên biệt', 'Bắt buộc', 4, 3, 1, 60, 40, 20, 90, 'Sinh viên Y khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Bệnh học', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(15, 15, 'MED015', 'Sinh lý bệnh ứng dụng lâm sàng', 'Bắt buộc', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Y khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Sinh lý', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(16, 16, 'MED016', 'Vi sinh vật y học và Ký sinh trùng', 'Bắt buộc', 4, 3, 1, 60, 40, 20, 90, 'Sinh viên Y khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Bệnh học', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(17, 19, 'MED019', 'Bệnh học Ngoại khoa toàn thân', 'Bắt buộc', 4, 3, 1, 60, 45, 15, 90, 'Sinh viên Y khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Nội', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(18, 22, 'MED022', 'Sản khoa và Phụ khoa lâm sàng', 'Bắt buộc', 4, 3, 1, 60, 40, 20, 90, 'Sinh viên Y khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Nội', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(19, 23, 'MED023', 'Nhi khoa lý thuyết và Thực hành', 'Bắt buộc', 4, 3, 1, 60, 40, 20, 90, 'Sinh viên Y khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Nội', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(20, 29, 'MED029', 'Thần kinh học lâm sàng', 'Bắt buộc', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Y khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Nội', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(21, 17, 'MED017', 'Miễn dịch học đại cương', 'Tự chọn', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Y khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Sinh lý', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(22, 18, 'MED018', 'Dược lý học cơ sở Y khoa', 'Tự chọn', 4, 3, 1, 60, 45, 15, 90, 'Sinh viên Y khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Dược lý', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(23, 21, 'MED021', 'Chấn thương chỉnh hình đại cương', 'Tự chọn', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Y khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Nội', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(24, 25, 'MED025', 'Tai Mũi Họng đại cương', 'Tự chọn', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Y khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Nội', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(25, 26, 'MED026', 'Răng Hàm Mặt cơ sở', 'Tự chọn', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Y khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Nội', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(26, 75, 'MED075', 'Dinh dưỡng tiết chế bệnh lý', 'Tự chọn', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Y khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Sinh lý', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(27, 76, 'MED076', 'Y học hạt nhân cơ bản', 'Tự chọn', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Y khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Bệnh học', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(28, 77, 'MED077', 'Phẫu thuật nội soi đại cương', 'Tự chọn', 3, 1, 2, 45, 15, 30, 60, 'Sinh viên Y khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Nội', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(29, 78, 'MED078', 'Sức khỏe sinh sản vị thành niên', 'Tự chọn', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Y khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Nội', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(30, 79, 'MED079', 'Ung thư học lâm sàng đại cương', 'Tự chọn', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Y khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Bệnh học', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(31, 20, 'MED020', 'Phẫu thuật thực hành cơ bản', 'Điều kiện', 3, 1, 2, 45, 15, 30, 60, 'Sinh viên Y khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Giải phẫu', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(32, 24, 'MED024', 'Sơ sinh học cơ bản', 'Điều kiện', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Y khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Nội', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(33, 27, 'MED027', 'Mắt và Nhãn khoa lâm sàng', 'Điều kiện', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Y khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Nội', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(34, 28, 'MED028', 'Da liễu và Bệnh lây truyền qua đường tình dục', 'Điều kiện', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Y khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Bệnh học', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(35, 30, 'MED030', 'Tâm thần học và Sức khỏe tâm thần', 'Điều kiện', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Y khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Tâm thần', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(36, 71, 'MED071', 'Tiếng Anh chuyên ngành Y khoa', 'Điều kiện', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Y khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Ngoại ngữ', 'Khoa Khoa học cơ bản', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(37, 72, 'MED072', 'Kỹ năng giao tiếp trong y tế', 'Điều kiện', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Y khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Tâm thần', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(38, 73, 'MED073', 'Lịch sử Y học thế giới và Việt Nam', 'Điều kiện', 2, 2, 0, 30, 30, 0, 30, 'Sinh viên Y khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Lý luận chính trị', 'Khoa Khoa học cơ bản', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(39, 74, 'MED074', 'Ứng dụng trí tuệ nhân tạo trong Y tế', 'Điều kiện', 3, 1, 2, 45, 15, 30, 60, 'Sinh viên Y khoa', 'Học kỳ II', '2026-2027', 'Trung tâm Công nghệ thông tin', 'Khoa Khoa học cơ bản', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(40, 80, 'MED080', 'Y học gia đình và Chăm sóc ban đầu', 'Điều kiện', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Y khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Nội', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(41, 31, 'PHAR031', 'Thực vật dược phân loại', 'Bắt buộc', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Dược khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Dược liệu', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(42, 32, 'PHAR032', 'Dược liệu học I', 'Bắt buộc', 4, 3, 1, 60, 40, 20, 90, 'Sinh viên Dược khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Dược liệu', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(43, 33, 'PHAR033', 'Dược liệu học II', 'Bắt buộc', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Dược khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Dược liệu', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(44, 35, 'PHAR035', 'Hóa phân tích định lượng', 'Bắt buộc', 4, 2, 2, 60, 30, 30, 90, 'Sinh viên Dược khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Hóa dược', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(45, 36, 'PHAR036', 'Hóa hữu cơ nâng cao ngành Dược', 'Bắt buộc', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Dược khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Hóa dược', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(46, 41, 'PHAR041', 'Dược lý bệnh học và Điều trị học I', 'Bắt buộc', 4, 3, 1, 60, 45, 15, 90, 'Sinh viên Dược khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Dược lý', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(47, 42, 'PHAR042', 'Dược lý bệnh học và Điều trị học II', 'Bắt buộc', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Dược khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Dược lý', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(48, 43, 'PHAR043', 'Bào chế và Sinh dược học I', 'Bắt buộc', 4, 3, 1, 60, 40, 20, 90, 'Sinh viên Dược khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Hóa dược', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(49, 44, 'PHAR044', 'Bào chế và Sinh dược học II', 'Bắt buộc', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Dược khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Hóa dược', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(50, 45, 'PHAR045', 'Kiểm nghiệm thuốc và Mỹ phẩm', 'Bắt buộc', 4, 2, 2, 60, 30, 30, 90, 'Sinh viên Dược khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Hóa dược', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(51, 34, 'PHAR034', 'Sinh học phân tử và Di truyền', 'Tự chọn', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Dược khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Dược liệu', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(52, 37, 'PHAR037', 'Hóa sinh dược phẩm', 'Tự chọn', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Dược khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Hóa dược', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(53, 39, 'PHAR039', 'Dược lâm sàng nâng cao', 'Tự chọn', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Dược khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Dược lý', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(54, 46, 'PHAR046', 'Công nghệ sinh học trong Sản xuất thuốc', 'Tự chọn', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Dược khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Hóa dược', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(55, 47, 'PHAR047', 'Kinh tế Dược đại cương', 'Tự chọn', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Dược khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Quản lý Dược', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(56, 49, 'PHAR049', 'Marketing Dược và Kỹ năng giao tiếp', 'Tự chọn', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Dược khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Quản lý Dược', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(57, 83, 'PHAR083', 'Dược liệu biển và Động vật làm thuốc', 'Tự chọn', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Dược khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Dược liệu', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(58, 84, 'PHAR084', 'Hóa học các hợp chất thiên nhiên', 'Tự chọn', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Dược khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Hóa dược', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(59, 88, 'PHAR088', 'Mỹ phẩm học và Thực phẩm chức năng', 'Tự chọn', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Dược khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Hóa dược', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(60, 89, 'PHAR089', 'Quản trị doanh nghiệp Dược', 'Tự chọn', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Dược khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Quản lý Dược', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(61, 38, 'PHAR038', 'Độc chất học đại cương', 'Điều kiện', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Dược khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Dược lý', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(62, 40, 'PHAR040', 'Dược động học lâm sàng', 'Điều kiện', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Dược khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Dược lý', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(63, 48, 'PHAR048', 'Pháp luật và Thực hành tốt Nhà thuốc (GPP)', 'Điều kiện', 2, 2, 0, 30, 30, 0, 30, 'Sinh viên Dược khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Quản lý Dược', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(64, 50, 'PHAR050', 'Quản trị chuỗi cung ứng dược phẩm', 'Điều kiện', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Dược khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Quản lý Dược', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(65, 81, 'PHAR081', 'Pháp chế Dược nâng cao', 'Điều kiện', 2, 2, 0, 30, 30, 0, 30, 'Sinh viên Dược khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Quản lý Dược', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(66, 82, 'PHAR082', 'Phương pháp nghiên cứu khoa học Dược', 'Điều kiện', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Dược khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Quản lý Dược', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(67, 85, 'PHAR085', 'Thông tin thuốc và Cảnh giác Dược', 'Điều kiện', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Dược khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Dược lý', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(68, 86, 'PHAR086', 'Độc học lâm sàng nâng cao', 'Điều kiện', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Dược khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Dược lý', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(69, 87, 'PHAR087', 'Đảm bảo chất lượng trong sản xuất thuốc', 'Điều kiện', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Dược khoa', 'Học kỳ I', '2026-2027', 'Bộ môn Hóa dược', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(70, 90, 'PHAR090', 'Dược xã hội học', 'Điều kiện', 2, 2, 0, 30, 30, 0, 30, 'Sinh viên Dược khoa', 'Học kỳ II', '2026-2027', 'Bộ môn Quản lý Dược', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(71, 51, 'NUR051', 'Tâm lý học và Giao tiếp trong Điều dưỡng', 'Bắt buộc', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Điều dưỡng', 'Học kỳ I', '2026-2027', 'Bộ môn Điều dưỡng cơ bản', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(72, 52, 'NUR052', 'Giáo dục sức khỏe và Thực hành nâng cao', 'Bắt buộc', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Điều dưỡng', 'Học kỳ II', '2026-2027', 'Bộ môn Điều dưỡng cơ bản', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(73, 54, 'NUR054', 'Điều dưỡng môi trường và Kiểm soát bệnh', 'Bắt buộc', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Điều dưỡng', 'Học kỳ II', '2026-2027', 'Bộ môn Điều dưỡng cơ bản', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(74, 59, 'NUR059', 'Chăm sóc người bệnh Ngoại khoa toàn diện', 'Bắt buộc', 4, 2, 2, 60, 30, 30, 90, 'Sinh viên Điều dưỡng', 'Học kỳ I', '2026-2027', 'Bộ môn Điều dưỡng nội', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(75, 60, 'NUR060', 'Chăm sóc sức khỏe Phụ nữ và Bà mẹ', 'Bắt buộc', 3, 1, 2, 45, 20, 25, 60, 'Sinh viên Điều dưỡng', 'Học kỳ I', '2026-2027', 'Bộ môn Điều dưỡng nội', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(76, 61, 'NUR061', 'Chăm sóc sức khỏe Trẻ em và Sơ sinh', 'Bắt buộc', 3, 1, 2, 45, 20, 25, 60, 'Sinh viên Điều dưỡng', 'Học kỳ II', '2026-2027', 'Bộ môn Điều dưỡng nội', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(77, 62, 'NUR062', 'Điều dưỡng Hồi sức cấp cứu và Chống độc', 'Bắt buộc', 4, 2, 2, 60, 30, 30, 90, 'Sinh viên Điều dưỡng', 'Học kỳ I', '2026-2027', 'Bộ môn Điều dưỡng nội', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(78, 63, 'NUR063', 'Chăm sóc người bệnh chuyên khoa lẻ', 'Bắt buộc', 3, 1, 2, 45, 25, 20, 60, 'Sinh viên Điều dưỡng', 'Học kỳ II', '2026-2027', 'Bộ môn Điều dưỡng nội', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(79, 66, 'NUR066', 'Điều dưỡng Sức khỏe Tâm thần', 'Bắt buộc', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Điều dưỡng', 'Học kỳ II', '2026-2027', 'Bộ môn Điều dưỡng cơ bản', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(80, 67, 'NUR067', 'Quản lý Điều dưỡng và Tổ chức bệnh viện', 'Bắt buộc', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Điều dưỡng', 'Học kỳ I', '2026-2027', 'Bộ môn Điều dưỡng cơ bản', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(81, 53, 'NUR053', 'Dịch tễ học cơ bản cho Điều dưỡng', 'Tự chọn', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Điều dưỡng', 'Học kỳ I', '2026-2027', 'Bộ môn Điều dưỡng cơ bản', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(82, 56, 'NUR056', 'Dinh dưỡng và Tiết chế lâm sàng', 'Tự chọn', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Điều dưỡng', 'Học kỳ II', '2026-2027', 'Bộ môn Sinh lý', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(83, 57, 'NUR057', 'Xét nghiệm cận lâm sàng ứng dụng', 'Tự chọn', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Điều dưỡng', 'Học kỳ I', '2026-2027', 'Bộ môn Bệnh học', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(84, 64, 'NUR064', 'Điều dưỡng chăm sóc Người cao tuổi', 'Tự chọn', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Điều dưỡng', 'Học kỳ II', '2026-2027', 'Bộ môn Điều dưỡng nội', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(85, 65, 'NUR065', 'Chăm sóc giảm nhẹ và Bệnh nan y', 'Tự chọn', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Điều dưỡng', 'Học kỳ I', '2026-2027', 'Bộ môn Điều dưỡng nội', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(86, 95, 'NUR095', 'Chăm sóc toàn diện người bệnh bỏng', 'Tự chọn', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Điều dưỡng', 'Học kỳ I', '2026-2027', 'Bộ môn Điều dưỡng nội', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(87, 96, 'NUR096', 'Chăm sóc sức khỏe phụ nữ mãn kinh', 'Tự chọn', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Điều dưỡng', 'Học kỳ II', '2026-2027', 'Bộ môn Điều dưỡng nội', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(88, 97, 'NUR097', 'Điều dưỡng kiểm soát nhiễm khuẩn bệnh viện', 'Tự chọn', 3, 1, 2, 45, 20, 25, 60, 'Sinh viên Điều dưỡng', 'Học kỳ I', '2026-2027', 'Bộ môn Điều dưỡng cơ bản', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(89, 98, 'NUR098', 'Quản lý vết thương và Chăm sóc giảm nhẹ', 'Tự chọn', 3, 1, 2, 45, 20, 25, 60, 'Sinh viên Điều dưỡng', 'Học kỳ II', '2026-2027', 'Bộ môn Điều dưỡng nội', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(90, 99, 'NUR099', 'Ứng dụng Tin học trong Quản lý Điều dưỡng', 'Tự chọn', 3, 1, 2, 45, 15, 30, 60, 'Sinh viên Điều dưỡng', 'Học kỳ I', '2026-2027', 'Trung tâm Công nghệ thông tin', 'Khoa Khoa học cơ bản', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(91, 55, 'NUR055', 'Dược lý đại cương cho Điều dưỡng', 'Điều kiện', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Điều dưỡng', 'Học kỳ I', '2026-2027', 'Bộ môn Dược lý', 'Khoa Dược', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(92, 58, 'NUR058', 'Vi sinh - Ký sinh trùng cơ sở', 'Điều kiện', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Điều dưỡng', 'Học kỳ II', '2026-2027', 'Bộ môn Bệnh học', 'Khoa Y', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(93, 68, 'NUR068', 'Nghiên cứu khoa học và Thực hành dựa vào bằng chứng', 'Điều kiện', 3, 2, 1, 45, 25, 20, 60, 'Sinh viên Điều dưỡng', 'Học kỳ I', '2026-2027', 'Bộ môn Điều dưỡng cơ bản', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(94, 69, 'NUR069', 'Pháp luật và Đạo đức nghề nghiệp Điều dưỡng', 'Điều kiện', 2, 2, 0, 30, 30, 0, 30, 'Sinh viên Điều dưỡng', 'Học kỳ II', '2026-2027', 'Bộ môn Điều dưỡng cơ bản', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(95, 70, 'NUR070', 'Lãnh đạo và Kỹ năng làm việc nhóm', 'Điều kiện', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Điều dưỡng', 'Học kỳ II', '2026-2027', 'Bộ môn Điều dưỡng cơ bản', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(96, 91, 'NUR091', 'Đạo đức và Luật pháp trong thực hành Điều dưỡng', 'Điều kiện', 2, 2, 0, 30, 30, 0, 30, 'Sinh viên Điều dưỡng', 'Học kỳ I', '2026-2027', 'Bộ môn Điều dưỡng cơ bản', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(97, 92, 'NUR092', 'Lịch sử và Xu hướng phát triển Điều dưỡng', 'Điều kiện', 2, 2, 0, 30, 30, 0, 30, 'Sinh viên Điều dưỡng', 'Học kỳ II', '2026-2027', 'Bộ môn Điều dưỡng cơ bản', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(98, 93, 'NUR093', 'Điều dưỡng dựa vào bằng chứng', 'Điều kiện', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Điều dưỡng', 'Học kỳ I', '2026-2027', 'Bộ môn Điều dưỡng cơ bản', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(99, 94, 'NUR094', 'Giải phẫu sinh lý ứng dụng trong Điều dưỡng', 'Điều kiện', 3, 2, 1, 45, 30, 15, 60, 'Sinh viên Điều dưỡng', 'Học kỳ II', '2026-2027', 'Bộ môn Điều dưỡng cơ bản', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10'),
(100, 100, 'NUR100', 'Kiểm định và Đảm bảo chất lượng Điều dưỡng', 'Điều kiện', 2, 1, 1, 30, 20, 10, 45, 'Sinh viên Điều dưỡng', 'Học kỳ II', '2026-2027', 'Bộ môn Điều dưỡng cơ bản', 'Khoa Điều dưỡng', 'Hoàn thành học phần theo định hướng đào tạo và chuẩn đầu ra của ngành.', 'Đạt được các mục tiêu cụ thể về kiến thức, kỹ năng và thái độ nghề nghiệp của học phần.', 'PLO1, PLO2, PLO3', 'Thang điểm 10');

INSERT INTO `course_coordinators` (`module_id`, `lecturer_id`)
SELECT `id`,
  CASE
    WHEN `id` BETWEEN 11 AND 40 THEN MOD(`id` - 11, 6) + 1
    WHEN `id` BETWEEN 41 AND 70 THEN MOD(`id` - 41, 4) + 7
    WHEN `id` BETWEEN 71 AND 100 THEN MOD(`id` - 71, 4) + 12
    WHEN `id` BETWEEN 1 AND 4 THEN `id`
    WHEN `id` BETWEEN 5 AND 7 THEN `id` + 2
    WHEN `id` BETWEEN 8 AND 9 THEN `id` + 4
    WHEN `id` = 10 THEN 11
  END
FROM `modules`;

INSERT INTO `course_coordinators` (`module_id`, `lecturer_id`)
SELECT `id`,
  CASE
    WHEN `id` IN (1, 2, 3, 4) OR `id` BETWEEN 11 AND 40 THEN 17
    WHEN `id` IN (5, 6, 7) OR `id` BETWEEN 41 AND 70 THEN 18
    WHEN `id` IN (8, 9) OR `id` BETWEEN 71 AND 100 THEN 19
    WHEN `id` = 10 THEN 20
  END
FROM `modules`;

SET FOREIGN_KEY_CHECKS = 1;

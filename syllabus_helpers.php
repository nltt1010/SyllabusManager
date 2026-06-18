<?php

function syllabus_table_exists(PDO $pdo, string $table): bool
{
    $quoted = $pdo->quote($table);
    $stmt = $pdo->query("SHOW TABLES LIKE {$quoted}");
    return (bool)$stmt->fetchColumn();
}

function syllabus_column_exists(PDO $pdo, string $table, string $column): bool
{
    if (!syllabus_table_exists($pdo, $table)) {
        return false;
    }

    $quoted = $pdo->quote($column);
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE {$quoted}");
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function syllabus_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!syllabus_column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

function syllabus_drop_column(PDO $pdo, string $table, string $column): void
{
    if (syllabus_column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
    }
}

function syllabus_seed_assessment_tools(PDO $pdo): void
{
    $defaults = [
        'Chuyên cần' => ['Điểm danh', 'Quan sát thái độ', 'Checklist', 'Thảo luận trên lớp'],
        'Kiểm tra thường xuyên' => ['Quiz', 'Bài tập', 'Trình bày nhóm', 'Báo cáo'],
        'Thi kết thúc' => ['Thi viết', 'Vấn đáp', 'OSCE/OSPE', 'Thực hành'],
    ];

    $stmtExists = $pdo->prepare('SELECT id FROM assessment_tools WHERE assessment_type = ? AND name = ? LIMIT 1');
    $stmtInsert = $pdo->prepare('INSERT INTO assessment_tools (assessment_type, name) VALUES (?, ?)');

    foreach ($defaults as $type => $names) {
        foreach ($names as $name) {
            $stmtExists->execute([$type, $name]);
            if (!$stmtExists->fetchColumn()) {
                $stmtInsert->execute([$type, $name]);
            }
        }
    }
}

function syllabus_slug_text(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = strtr($value, [
        'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a',
        'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a',
        'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',
        'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
        'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
        'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o',
        'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
        'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u',
        'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',
        'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',
        'đ' => 'd',
    ]);
    $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);
    return trim((string)$value);
}

function syllabus_relation_bucket(string $value): string
{
    $slug = syllabus_slug_text($value);
    if (str_contains($slug, 'tien quyet')) {
        return 'prerequisite';
    }
    if (str_contains($slug, 'song hanh')) {
        return 'parallel';
    }
    if (str_contains($slug, 'hoc truoc')) {
        return 'previous';
    }
    return '';
}

function syllabus_module_type_label(string $value): string
{
    $slug = syllabus_slug_text($value);
    if (str_contains($slug, 'bat buoc')) {
        return 'Bắt buộc';
    }
    if (str_contains($slug, 'dieu kien')) {
        return 'Điều kiện';
    }
    if (str_contains($slug, 'tu chon')) {
        return 'Tự chọn';
    }
    if (str_contains($slug, 'khong')) {
        return 'Không';
    }
    return trim($value);
}

function syllabus_delivery_mode_label(string $value): string
{
    $slug = syllabus_slug_text($value);
    if (str_contains($slug, 'hoc tren lop')) {
        return 'Học trên lớp';
    }
    if (str_contains($slug, 'truc tuyen')) {
        return 'Học trực tuyến';
    }
    if (str_contains($slug, 'ket hop')) {
        return 'Kết hợp';
    }
    return trim($value);
}

function syllabus_assessment_bucket(string $value): string
{
    $slug = syllabus_slug_text($value);
    if (str_contains($slug, 'chuyen can')) {
        return 'Chuyên cần';
    }
    if (str_contains($slug, 'thuong xuyen')) {
        return 'Kiểm tra thường xuyên';
    }
    if (str_contains($slug, 'ket thuc') || str_contains($slug, 'cuoi')) {
        return 'Thi kết thúc';
    }
    return $value;
}

function syllabus_default_grading_scale(): string
{
    return 'Học phần được lượng giá theo thang điểm 10.';
}

function syllabus_assessment_tool_label(string $value): string
{
    $slug = syllabus_slug_text($value);
    if (str_contains($slug, 'diem danh')) {
        return 'Điểm danh';
    }
    if (str_contains($slug, 'quan sat thai do')) {
        return 'Quan sát thái độ';
    }
    if (str_contains($slug, 'thao luan tren lop')) {
        return 'Thảo luận trên lớp';
    }
    if (str_contains($slug, 'bai tap')) {
        return 'Bài tập';
    }
    if (str_contains($slug, 'trinh bay nhom')) {
        return 'Trình bày nhóm';
    }
    if (str_contains($slug, 'bao cao')) {
        return 'Báo cáo';
    }
    if (str_contains($slug, 'thi viet')) {
        return 'Thi viết';
    }
    if (str_contains($slug, 'van dap')) {
        return 'Vấn đáp';
    }
    if (str_contains($slug, 'thuc hanh')) {
        return 'Thực hành';
    }
    return trim($value);
}

function syllabus_normalize_table_column(PDO $pdo, string $table, string $primaryKey, string $column, callable $normalizer): void
{
    if (!syllabus_table_exists($pdo, $table) || !syllabus_column_exists($pdo, $table, $column)) {
        return;
    }

    $stmt = $pdo->query("SELECT `{$primaryKey}` AS row_id, `{$column}` AS row_value FROM `{$table}`");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        return;
    }

    $stmtUpdate = $pdo->prepare("UPDATE `{$table}` SET `{$column}` = ? WHERE `{$primaryKey}` = ?");
    foreach ($rows as $row) {
        $currentValue = (string)($row['row_value'] ?? '');
        if ($currentValue === '') {
            continue;
        }

        $normalizedValue = trim((string)$normalizer($currentValue));
        if ($normalizedValue !== '' && $normalizedValue !== $currentValue) {
            $stmtUpdate->execute([$normalizedValue, (int)$row['row_id']]);
        }
    }
}

function syllabus_normalize_assessment_tools(PDO $pdo): void
{
    if (!syllabus_table_exists($pdo, 'assessment_tools')) {
        return;
    }

    $rows = $pdo->query('SELECT id, assessment_type, name FROM assessment_tools ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        return;
    }

    $groups = [];
    foreach ($rows as $row) {
        $normalizedType = syllabus_assessment_bucket((string)($row['assessment_type'] ?? ''));
        $normalizedName = syllabus_assessment_tool_label((string)($row['name'] ?? ''));
        $key = $normalizedType . "\n" . $normalizedName;
        $groups[$key][] = [
            'id' => (int)$row['id'],
            'assessment_type' => (string)($row['assessment_type'] ?? ''),
            'name' => (string)($row['name'] ?? ''),
            'normalized_type' => $normalizedType,
            'normalized_name' => $normalizedName,
        ];
    }

    $stmtToolUpdate = $pdo->prepare('UPDATE assessment_tools SET assessment_type = ?, name = ? WHERE id = ?');
    $stmtRelationMove = $pdo->prepare('
        INSERT IGNORE INTO assessment_tool_relation (assessment_id, assessment_tool_id)
        SELECT assessment_id, ? FROM assessment_tool_relation WHERE assessment_tool_id = ?
    ');
    $stmtToolDelete = $pdo->prepare('DELETE FROM assessment_tools WHERE id = ?');

    foreach ($groups as $items) {
        $canonical = null;
        foreach ($items as $item) {
            if ($item['assessment_type'] === $item['normalized_type'] && $item['name'] === $item['normalized_name']) {
                $canonical = $item;
                break;
            }
        }
        if ($canonical === null) {
            $canonical = $items[0];
            $stmtToolUpdate->execute([
                $canonical['normalized_type'],
                $canonical['normalized_name'],
                $canonical['id'],
            ]);
        }

        foreach ($items as $item) {
            if ($item['id'] === $canonical['id']) {
                continue;
            }
            $stmtRelationMove->execute([$canonical['id'], $item['id']]);
            $stmtToolDelete->execute([$item['id']]);
        }
    }
}

function syllabus_normalize_dictionary_data(PDO $pdo): void
{
    $pdo->beginTransaction();
    try {
        syllabus_normalize_table_column($pdo, 'courses', 'id', 'module_type', 'syllabus_module_type_label');
        syllabus_normalize_table_column($pdo, 'modules', 'id', 'type', 'syllabus_module_type_label');
        syllabus_normalize_table_column($pdo, 'modules', 'id', 'delivery_mode', 'syllabus_delivery_mode_label');
        syllabus_normalize_table_column($pdo, 'module_relationships', 'id', 'relation_type', function (string $value): string {
            $bucket = syllabus_relation_bucket($value);
            return match ($bucket) {
                'prerequisite' => 'Tiên quyết',
                'parallel' => 'Song hành',
                'previous' => 'Học trước',
                default => trim($value),
            };
        });
        syllabus_normalize_table_column($pdo, 'resources', 'id', 'resource_type', function (string $value): string {
            $slug = syllabus_slug_text($value);
            if (str_contains($slug, 'giang day')) {
                return 'Tài liệu giảng dạy';
            }
            if (str_contains($slug, 'tu hoc')) {
                return 'Tài liệu tự học';
            }
            return trim($value);
        });
        syllabus_normalize_table_column($pdo, 'theory_topics', 'id', 'method', 'syllabus_delivery_mode_label');
        syllabus_normalize_table_column($pdo, 'practical_topics', 'id', 'method', 'syllabus_delivery_mode_label');
        syllabus_normalize_table_column($pdo, 'combined_topics', 'id', 'method', 'syllabus_delivery_mode_label');
        syllabus_normalize_assessment_tools($pdo);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function syllabus_ensure_default_programs(PDO $pdo): void
{
    if (!syllabus_table_exists($pdo, 'education_programs') || !syllabus_table_exists($pdo, 'majors')) {
        return;
    }

    $year = date('Y');
    $majors = $pdo->query('SELECT id FROM majors ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $stmtExists = $pdo->prepare('SELECT id FROM education_programs WHERE major_id = ? AND year = ? LIMIT 1');
    $stmtInsert = $pdo->prepare('INSERT INTO education_programs (major_id, year) VALUES (?, ?)');

    foreach ($majors as $majorId) {
        $stmtExists->execute([(int)$majorId, $year]);
        if (!$stmtExists->fetchColumn()) {
            $stmtInsert->execute([(int)$majorId, $year]);
        }
    }
}

function syllabus_sync_course_programs(PDO $pdo): void
{
    if (!syllabus_column_exists($pdo, 'courses', 'education_program_id')) {
        return;
    }

    $courses = $pdo->query('SELECT id, major_id, education_program_id FROM courses')->fetchAll(PDO::FETCH_ASSOC);
    $stmtFind = $pdo->prepare('SELECT id FROM education_programs WHERE major_id = ? ORDER BY year DESC, id DESC LIMIT 1');
    $stmtUpdate = $pdo->prepare('UPDATE courses SET education_program_id = ? WHERE id = ?');

    foreach ($courses as $course) {
        if (!empty($course['education_program_id']) || empty($course['major_id'])) {
            continue;
        }

        $stmtFind->execute([(int)$course['major_id']]);
        $programId = $stmtFind->fetchColumn();
        if ($programId) {
            $stmtUpdate->execute([(int)$programId, (int)$course['id']]);
        }
    }
}

function syllabus_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `education_programs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `major_id` INT NOT NULL,
            `year` VARCHAR(20) NOT NULL,
            UNIQUE KEY `uniq_education_program` (`major_id`, `year`),
            CONSTRAINT `fk_education_program_major`
                FOREIGN KEY (`major_id`) REFERENCES `majors`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `major_objectives` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `education_program_id` INT NOT NULL,
            `sort_order` INT NOT NULL DEFAULT 1,
            `general_objective` TEXT NULL,
            `po_content` TEXT NULL,
            `plo_content` TEXT NULL,
            CONSTRAINT `fk_major_objective_program`
                FOREIGN KEY (`education_program_id`) REFERENCES `education_programs`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `lecturers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) NULL,
            `phone` VARCHAR(100) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `course_coordinators` (
            `course_id` INT NOT NULL,
            `lecturer_id` INT NOT NULL,
            `sort_order` INT NOT NULL DEFAULT 1,
            PRIMARY KEY (`course_id`, `lecturer_id`),
            CONSTRAINT `fk_course_coordinator_course`
                FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_course_coordinator_lecturer`
                FOREIGN KEY (`lecturer_id`) REFERENCES `lecturers`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `assessment_tools` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `assessment_type` VARCHAR(100) NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            UNIQUE KEY `uniq_assessment_tool` (`assessment_type`, `name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `assessment_tool_relation` (
            `assessment_id` INT NOT NULL,
            `assessment_tool_id` INT NOT NULL,
            PRIMARY KEY (`assessment_id`, `assessment_tool_id`),
            CONSTRAINT `fk_assessment_tool_relation_assessment`
                FOREIGN KEY (`assessment_id`) REFERENCES `assessments`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_assessment_tool_relation_tool`
                FOREIGN KEY (`assessment_tool_id`) REFERENCES `assessment_tools`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    syllabus_add_column($pdo, 'courses', 'education_program_id', 'INT NULL AFTER `major_id`');
    syllabus_add_column($pdo, 'courses', 'module_type', 'VARCHAR(100) NULL AFTER `name`');
    syllabus_add_column($pdo, 'courses', 'credits', 'INT NOT NULL DEFAULT 0 AFTER `name`');
    syllabus_add_column($pdo, 'courses', 'credits_theory', 'INT NOT NULL DEFAULT 0 AFTER `credits`');
    syllabus_add_column($pdo, 'courses', 'credits_practice', 'INT NOT NULL DEFAULT 0 AFTER `credits_theory`');
    syllabus_add_column($pdo, 'courses', 'expected_semester', 'VARCHAR(100) NULL AFTER `sort_order`');
    syllabus_add_column($pdo, 'courses', 'expected_year', 'VARCHAR(100) NULL AFTER `expected_semester`');
    syllabus_add_column($pdo, 'courses', 'prerequisite_course_ids', 'TEXT NULL AFTER `expected_year`');
    syllabus_add_column($pdo, 'courses', 'parallel_course_ids', 'TEXT NULL AFTER `prerequisite_course_ids`');
    syllabus_add_column($pdo, 'courses', 'previous_course_ids', 'TEXT NULL AFTER `parallel_course_ids`');
    syllabus_add_column($pdo, 'courses', 'faculty_id', 'INT NULL AFTER `previous_course_ids`');
    syllabus_add_column($pdo, 'courses', 'grading_scale', 'TEXT NULL AFTER `faculty_id`');
    $pdo->exec('ALTER TABLE `courses` MODIFY COLUMN `module_type` VARCHAR(100) NULL');

    syllabus_add_column($pdo, 'modules', 'education_program_id', 'INT NULL AFTER `course_id`');
    syllabus_add_column($pdo, 'modules', 'delivery_mode', 'VARCHAR(100) NULL AFTER `faculty_in_charge`');
    $pdo->exec('ALTER TABLE `modules` MODIFY COLUMN `type` VARCHAR(100) NULL');

    if (syllabus_column_exists($pdo, 'clos', 'description') && !syllabus_column_exists($pdo, 'clos', 'content')) {
        $pdo->exec('ALTER TABLE `clos` CHANGE COLUMN `description` `content` TEXT NOT NULL');
    }
    syllabus_add_column($pdo, 'clos', 'content', 'TEXT NULL');
    syllabus_add_column($pdo, 'clos', 'contribution_level', 'VARCHAR(100) NULL AFTER `bloom_level`');
    syllabus_add_column($pdo, 'clos', 'pi_id', 'VARCHAR(100) NULL AFTER `contribution_level`');
    syllabus_add_column($pdo, 'clos', 'plo_pi', 'VARCHAR(255) NULL AFTER `pi_id`');

    syllabus_add_column($pdo, 'assessments', 'assessment_category', 'VARCHAR(100) NULL AFTER `type`');
    syllabus_add_column($pdo, 'assessments', 'tool_notes', 'TEXT NULL AFTER `tool`');
    syllabus_add_column($pdo, 'assessments', 'clos_text', 'TEXT NULL AFTER `component`');
    $pdo->exec('ALTER TABLE `assessments` MODIFY COLUMN `type` VARCHAR(100) NULL');

    $pdo->exec('ALTER TABLE `module_relationships` MODIFY COLUMN `relation_type` VARCHAR(100) NOT NULL');

    syllabus_add_column($pdo, 'self_study_activities', 'clos_text', 'TEXT NULL AFTER `activity_name`');

    syllabus_add_column($pdo, 'theory_topics', 'clos_text', 'TEXT NULL AFTER `title`');
    syllabus_add_column($pdo, 'theory_topics', 'online_hours', 'INT NOT NULL DEFAULT 0 AFTER `class_hours`');
    syllabus_add_column($pdo, 'theory_topics', 'teaching_method', 'TEXT NULL AFTER `method`');
    syllabus_add_column($pdo, 'theory_topics', 'parent_id', 'INT NULL AFTER `module_id`');
    if (syllabus_column_exists($pdo, 'theory_topics', 'textbook_info')) {
        syllabus_drop_column($pdo, 'theory_topics', 'textbook_info');
    }

    syllabus_add_column($pdo, 'practical_topics', 'clos_text', 'TEXT NULL AFTER `content`');
    syllabus_add_column($pdo, 'practical_topics', 'online_hours', 'INT NOT NULL DEFAULT 0 AFTER `lab_hours`');
    syllabus_add_column($pdo, 'practical_topics', 'teaching_method', 'TEXT NULL AFTER `method`');

    syllabus_add_column($pdo, 'combined_topics', 'clos_text', 'TEXT NULL AFTER `content`');
    syllabus_add_column($pdo, 'combined_topics', 'online_hours', 'INT NOT NULL DEFAULT 0 AFTER `practical_hours`');
    syllabus_add_column($pdo, 'combined_topics', 'teaching_method', 'TEXT NULL AFTER `method`');

    syllabus_ensure_default_programs($pdo);
    syllabus_sync_course_programs($pdo);
    syllabus_seed_assessment_tools($pdo);
    syllabus_normalize_dictionary_data($pdo);

    $done = true;
}

function syllabus_parse_id_list($value): array
{
    if (is_array($value)) {
        $tokens = $value;
    } else {
        $tokens = preg_split('/[,\s;]+/', (string)$value);
    }

    $ids = [];
    foreach ($tokens as $token) {
        $token = trim((string)$token);
        if ($token !== '' && ctype_digit($token)) {
            $ids[] = (int)$token;
        }
    }

    return array_values(array_unique($ids));
}

function syllabus_csv_from_ids(array $ids): string
{
    return implode(',', array_values(array_unique(array_map('intval', $ids))));
}

function syllabus_relation_text_from_ids(PDO $pdo, array $ids): string
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (empty($ids)) {
        return '';
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT code, name FROM courses WHERE id IN ({$placeholders}) ORDER BY code");
    $stmt->execute($ids);
    $parts = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $parts[] = trim(($row['code'] ?? '') . ' - ' . ($row['name'] ?? ''));
    }

    return implode(', ', array_filter($parts));
}

function syllabus_find_latest_module_by_course(PDO $pdo, int $courseId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM modules WHERE course_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$courseId]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);
    return $module ?: null;
}

function syllabus_get_course_coordinator_names(PDO $pdo, int $courseId): array
{
    $stmt = $pdo->prepare("
        SELECT l.name
        FROM course_coordinators cc
        INNER JOIN lecturers l ON l.id = cc.lecturer_id
        WHERE cc.course_id = ?
        ORDER BY cc.sort_order ASC, l.name ASC
    ");
    $stmt->execute([$courseId]);
    return array_values(array_filter($stmt->fetchAll(PDO::FETCH_COLUMN)));
}

function syllabus_get_course_framework(PDO $pdo, int $courseId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            c.*,
            ep.year AS program_year,
            ep.id AS education_program_id_resolved,
            m.id AS major_id_resolved,
            m.name AS major_name,
            f.name AS faculty_name
        FROM courses c
        LEFT JOIN education_programs ep ON ep.id = c.education_program_id
        LEFT JOIN majors m ON m.id = COALESCE(ep.major_id, c.major_id)
        LEFT JOIN faculties_list f ON f.id = c.faculty_id
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$course) {
        return null;
    }

    $latestModule = syllabus_find_latest_module_by_course($pdo, $courseId);

    $course['education_program_id'] = (int)($course['education_program_id'] ?: $course['education_program_id_resolved'] ?: 0);
    $course['major_id'] = (int)($course['major_id_resolved'] ?: $course['major_id'] ?: 0);
    $course['major_name'] = $course['major_name'] ?: ($latestModule['target_programs'] ?? '');
    $course['faculty_name'] = $course['faculty_name'] ?: ($latestModule['faculty_in_charge'] ?? '');
    $course['module_type'] = syllabus_module_type_label($course['module_type'] ?: ($latestModule['type'] ?? ''));
    $course['credits'] = (int)($course['credits'] ?: ($latestModule['credits'] ?? 0));
    $course['credits_theory'] = (int)($course['credits_theory'] ?: ($latestModule['credits_theory'] ?? 0));
    $course['credits_practice'] = (int)($course['credits_practice'] ?: ($latestModule['credits_practice'] ?? 0));
    $course['expected_semester'] = $course['expected_semester'] ?: ($latestModule['expected_semester'] ?? '');
    $course['expected_year'] = $course['expected_year'] ?: ($latestModule['expected_year'] ?? '');
    $course['grading_scale'] = $course['grading_scale'] ?: ($latestModule['grading_scale'] ?? syllabus_default_grading_scale());
    $course['program_year'] = $course['program_year'] ?: ($course['expected_year'] ?: date('Y'));

    $course['prerequisite_ids'] = syllabus_parse_id_list($course['prerequisite_course_ids'] ?? '');
    $course['parallel_ids'] = syllabus_parse_id_list($course['parallel_course_ids'] ?? '');
    $course['previous_ids'] = syllabus_parse_id_list($course['previous_course_ids'] ?? '');

    if (empty($course['prerequisite_ids']) && $latestModule) {
        $stmtRel = $pdo->prepare('SELECT related_course_id, relation_type FROM module_relationships WHERE module_id = ?');
        $stmtRel->execute([(int)$latestModule['id']]);
        foreach ($stmtRel->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $bucket = syllabus_relation_bucket((string)($row['relation_type'] ?? ''));
            if ($bucket === 'prerequisite') {
                $course['prerequisite_ids'][] = (int)$row['related_course_id'];
            }
            if ($bucket === 'parallel') {
                $course['parallel_ids'][] = (int)$row['related_course_id'];
            }
            if ($bucket === 'previous') {
                $course['previous_ids'][] = (int)$row['related_course_id'];
            }
        }
    }

    $course['prerequisite_ids'] = array_values(array_unique(array_map('intval', $course['prerequisite_ids'])));
    $course['parallel_ids'] = array_values(array_unique(array_map('intval', $course['parallel_ids'])));
    $course['previous_ids'] = array_values(array_unique(array_map('intval', $course['previous_ids'])));
    $course['prerequisite_text'] = syllabus_relation_text_from_ids($pdo, $course['prerequisite_ids']);
    $course['parallel_text'] = syllabus_relation_text_from_ids($pdo, $course['parallel_ids']);
    $course['previous_text'] = syllabus_relation_text_from_ids($pdo, $course['previous_ids']);

    $course['coordinator_names'] = syllabus_get_course_coordinator_names($pdo, $courseId);
    if (empty($course['coordinator_names']) && !empty($latestModule['coordinating_board'])) {
        $parts = preg_split('/\s*,\s*/', trim((string)$latestModule['coordinating_board']));
        $course['coordinator_names'] = array_values(array_filter($parts));
    }

    return $course;
}

function syllabus_get_course_options(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT
            c.id,
            c.code,
            c.name,
            COALESCE(ep.id, c.education_program_id) AS education_program_id,
            ep.year AS program_year,
            m.id AS major_id,
            m.name AS major_name
        FROM courses c
        LEFT JOIN education_programs ep ON ep.id = c.education_program_id
        LEFT JOIN majors m ON m.id = COALESCE(ep.major_id, c.major_id)
        ORDER BY ep.year DESC, m.name ASC, c.code ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function syllabus_get_program_options(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT ep.id, ep.year, ep.major_id, m.name AS major_name
        FROM education_programs ep
        INNER JOIN majors m ON m.id = ep.major_id
        ORDER BY ep.year DESC, m.name ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function syllabus_get_lecturer_options(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, name FROM lecturers ORDER BY name ASC');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function syllabus_get_assessment_tool_options(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, assessment_type, name FROM assessment_tools ORDER BY assessment_type, name');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['assessment_type'] = syllabus_assessment_bucket((string)($row['assessment_type'] ?? ''));
        $row['name'] = syllabus_assessment_tool_label((string)($row['name'] ?? ''));
    }
    unset($row);
    return $rows;
}

function syllabus_get_module_bundle(PDO $pdo, int $moduleId): array
{
    $stmt = $pdo->prepare('
        SELECT m.*, c.name AS course_name, c.code AS course_code
        FROM modules m
        LEFT JOIN courses c ON c.id = m.course_id
        WHERE m.id = ?
        LIMIT 1
    ');
    $stmt->execute([$moduleId]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$module) {
        return [];
    }

    $framework = !empty($module['course_id']) ? syllabus_get_course_framework($pdo, (int)$module['course_id']) : null;

    $module['credits'] = (int)($module['credits'] ?? 0);
    $module['credits_theory'] = (int)($module['credits_theory'] ?? 0);
    $module['credits_practice'] = (int)($module['credits_practice'] ?? 0);
    $module['total_hours'] = (int)($module['total_hours'] ?? (($module['theory_hours'] ?? 0) + ($module['practical_hours'] ?? 0)));
    $module['theory_hours'] = (int)($module['theory_hours'] ?? 0);
    $module['practical_hours'] = (int)($module['practical_hours'] ?? 0);
    $module['self_study_hours'] = (int)($module['self_study_hours'] ?? 0);
    $module['target_programs'] = $module['target_programs'] ?: ($framework['major_name'] ?? '');
    $module['expected_semester'] = $module['expected_semester'] ?: ($framework['expected_semester'] ?? '');
    $module['expected_year'] = $module['expected_year'] ?: ($framework['expected_year'] ?? '');
    $module['faculty_in_charge'] = $module['faculty_in_charge'] ?: ($framework['faculty_name'] ?? '');
    $module['grading_scale'] = $module['grading_scale'] ?: ($framework['grading_scale'] ?? '');
    $module['type'] = syllabus_module_type_label($module['type'] ?: ($framework['module_type'] ?? ''));
    $module['delivery_mode'] = syllabus_delivery_mode_label($module['delivery_mode'] ?: 'Học trên lớp');
    $module['coordinator_names'] = $framework['coordinator_names'] ?? [];

    $moduleRelationRows = $pdo->prepare('SELECT related_course_id, relation_type FROM module_relationships WHERE module_id = ?');
    $moduleRelationRows->execute([$moduleId]);
    $mappedRelationIds = [
        'prerequisite' => [],
        'parallel' => [],
        'previous' => [],
    ];
    foreach ($moduleRelationRows->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $bucket = syllabus_relation_bucket((string)($row['relation_type'] ?? ''));
        if ($bucket !== '') {
            $mappedRelationIds[$bucket][] = (int)$row['related_course_id'];
        }
    }

    $relationTypes = [
        'prerequisite' => $framework['prerequisite_ids'] ?? [],
        'parallel' => $framework['parallel_ids'] ?? [],
        'previous' => $framework['previous_ids'] ?? [],
    ];

    foreach ($relationTypes as $key => $fallbackIds) {
        $ids = !empty($mappedRelationIds[$key]) ? $mappedRelationIds[$key] : $fallbackIds;
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $module[$key . '_ids'] = $ids;
        $module[$key . '_text'] = syllabus_relation_text_from_ids($pdo, $ids);
    }

    $stmtDep = $pdo->prepare("
        SELECT GROUP_CONCAT(d.name SEPARATOR ', ')
        FROM module_departments md
        INNER JOIN departments_list d ON d.id = md.department_id
        WHERE md.module_id = ?
    ");
    $stmtDep->execute([$moduleId]);
    $module['department_in_charge_text'] = $stmtDep->fetchColumn() ?: ($module['department_in_charge'] ?? '');

    $closContentExpr = syllabus_column_exists($pdo, 'clos', 'content')
        ? 'COALESCE(content, "")'
        : 'COALESCE(description, "")';
    $stmt = $pdo->prepare("
        SELECT
            id,
            module_id,
            code,
            {$closContentExpr} AS content,
            domain,
            bloom_level,
            contribution_level,
            pi_id,
            plo_pi
        FROM clos
        WHERE module_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$moduleId]);
    $clos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT
            a.*,
            GROUP_CONCAT(DISTINCT c.code ORDER BY c.code SEPARATOR ', ') AS clos_codes,
            GROUP_CONCAT(DISTINCT at.name ORDER BY at.name SEPARATOR ', ') AS tool_names
        FROM assessments a
        LEFT JOIN assessment_clos ac ON ac.assessment_id = a.id
        LEFT JOIN clos c ON c.id = ac.clo_id
        LEFT JOIN assessment_tool_relation atr ON atr.assessment_id = a.id
        LEFT JOIN assessment_tools at ON at.id = atr.assessment_tool_id
        WHERE a.module_id = ?
        GROUP BY a.id
        ORDER BY a.id ASC
    ");
    $stmt->execute([$moduleId]);
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($assessments as &$assessment) {
        $assessment['assessment_category'] = syllabus_assessment_bucket((string)($assessment['assessment_category'] ?: $assessment['type']));
        $assessment['type'] = syllabus_assessment_bucket((string)$assessment['type']);
    }
    unset($assessment);

    $stmt = $pdo->prepare("
        SELECT
            s.*,
            GROUP_CONCAT(DISTINCT c.code ORDER BY c.code SEPARATOR ', ') AS clos_codes
        FROM self_study_activities s
        LEFT JOIN self_study_clos sc ON sc.self_study_activity_id = s.id
        LEFT JOIN clos c ON c.id = sc.clo_id
        WHERE s.module_id = ?
        GROUP BY s.id
        ORDER BY s.id ASC
    ");
    $stmt->execute([$moduleId]);
    $selfStudyActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT
            t.*,
            parent.title AS parent_title,
            GROUP_CONCAT(DISTINCT c.code ORDER BY c.code SEPARATOR ', ') AS clos_codes
        FROM theory_topics t
        LEFT JOIN theory_topics parent ON parent.id = t.parent_id
        LEFT JOIN theory_topic_clos tc ON tc.theory_topic_id = t.id
        LEFT JOIN clos c ON c.id = tc.clo_id
        WHERE t.module_id = ?
        GROUP BY t.id
        ORDER BY t.id ASC
    ");
    $stmt->execute([$moduleId]);
    $theoryTopics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($theoryTopics as &$topic) {
        $topic['method'] = syllabus_delivery_mode_label((string)($topic['method'] ?? ''));
    }
    unset($topic);

    $stmt = $pdo->prepare("
        SELECT
            p.*,
            f.name AS facility_name,
            GROUP_CONCAT(DISTINCT c.code ORDER BY c.code SEPARATOR ', ') AS clos_codes
        FROM practical_topics p
        LEFT JOIN facilities f ON f.id = p.facility_id
        LEFT JOIN practical_topic_clos pc ON pc.practical_topic_id = p.id
        LEFT JOIN clos c ON c.id = pc.clo_id
        WHERE p.module_id = ?
        GROUP BY p.id
        ORDER BY p.id ASC
    ");
    $stmt->execute([$moduleId]);
    $practicalTopics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($practicalTopics as &$topic) {
        $topic['method'] = syllabus_delivery_mode_label((string)($topic['method'] ?? ''));
    }
    unset($topic);

    $stmt = $pdo->prepare("
        SELECT
            cb.*,
            f.name AS facility_name,
            GROUP_CONCAT(DISTINCT c.code ORDER BY c.code SEPARATOR ', ') AS clos_codes
        FROM combined_topics cb
        LEFT JOIN facilities f ON f.id = cb.facility_id
        LEFT JOIN combined_topic_clos cbc ON cbc.combined_topic_id = cb.id
        LEFT JOIN clos c ON c.id = cbc.clo_id
        WHERE cb.module_id = ?
        GROUP BY cb.id
        ORDER BY cb.sort_order ASC, cb.id ASC
    ");
    $stmt->execute([$moduleId]);
    $combinedTopics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($combinedTopics as &$topic) {
        $topic['method'] = syllabus_delivery_mode_label((string)($topic['method'] ?? ''));
    }
    unset($topic);

    $stmt = $pdo->prepare('SELECT * FROM resources WHERE module_id = ? ORDER BY resource_type ASC, sort_order ASC, id ASC');
    $stmt->execute([$moduleId]);
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'module' => $module,
        'clos' => $clos,
        'assessments' => $assessments,
        'selfStudyActivities' => $selfStudyActivities,
        'theoryTopics' => $theoryTopics,
        'practicalTopics' => $practicalTopics,
        'combinedTopics' => $combinedTopics,
        'resources' => $resources,
        'framework' => $framework,
    ];
}

<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

try {
    $allowedTables = [
        'modules',
        'assessments',
        'self_study_activities',
        'theory_topics',
        'practical_topics',
        'combined_topics',
    ];

    $ensureColumn = function (string $table, string $column, string $definition) use ($pdo, $allowedTables) {
        if (!in_array($table, $allowedTables, true)) {
            throw new Exception('Bang khong hop le khi bo sung cot.');
        }

        $quotedColumn = $pdo->quote($column);
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE {$quotedColumn}");
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    };

    $ensureColumn('modules', 'objective_specific', 'TEXT NULL');
    $ensureColumn('modules', 'program_year', 'VARCHAR(20) NULL');

    $ensureColumn('assessments', 'clos_text', 'TEXT NULL');
    $ensureColumn('assessments', 'plo_pi', 'TEXT NULL');
    $ensureColumn('assessments', 'contribution', 'VARCHAR(50) NULL');
    $ensureColumn('assessments', 'form', 'TEXT NULL');
    $ensureColumn('assessments', 'tool', 'TEXT NULL');

    $ensureColumn('self_study_activities', 'clos_text', 'TEXT NULL');

    $ensureColumn('theory_topics', 'method', 'TEXT NULL');
    $ensureColumn('theory_topics', 'hours_online', 'INT NULL DEFAULT 0');
    $ensureColumn('theory_topics', 'pedagogy', 'TEXT NULL');
    $ensureColumn('theory_topics', 'textbook_info', 'TEXT NULL');
    $ensureColumn('theory_topics', 'clos_text', 'TEXT NULL');

    $ensureColumn('practical_topics', 'method', 'TEXT NULL');
    $ensureColumn('practical_topics', 'hours_online', 'INT NULL DEFAULT 0');
    $ensureColumn('practical_topics', 'pedagogy', 'TEXT NULL');
    $ensureColumn('practical_topics', 'clos_text', 'TEXT NULL');

    $ensureColumn('combined_topics', 'method', 'TEXT NULL');
    $ensureColumn('combined_topics', 'hours_online', 'INT NULL DEFAULT 0');
    $ensureColumn('combined_topics', 'pedagogy', 'TEXT NULL');
    $ensureColumn('combined_topics', 'clos_text', 'TEXT NULL');

    $normalizePostedList = function ($value): array {
        if (is_array($value)) {
            return array_values(array_filter(array_map(
                static fn($item) => trim((string)$item),
                $value
            ), static fn($item) => $item !== ''));
        }

        $value = trim((string)$value);
        if ($value === '') {
            return [];
        }

        return [$value];
    };

    $normalizeTextList = function ($value): array {
        if (is_array($value)) {
            return array_values(array_filter(array_map(
                static fn($item) => trim((string)$item),
                $value
            ), static fn($item) => $item !== ''));
        }

        $value = trim((string)$value);
        if ($value === '') {
            return [];
        }

        $parts = preg_split('/[,;\n|]+/u', $value) ?: [];
        return array_values(array_filter(array_map(
            static fn($item) => trim((string)$item),
            $parts
        ), static fn($item) => $item !== ''));
    };

    $normalizeIntList = function ($value) use ($normalizePostedList): array {
        $items = $normalizePostedList($value);
        $ints = [];
        foreach ($items as $item) {
            if (is_numeric($item)) {
                $item = (int)$item;
                if ($item > 0) {
                    $ints[] = $item;
                }
            }
        }
        return array_values(array_unique($ints));
    };

    $decodeJsonArray = function (string $field): array {
        $decoded = json_decode($_POST[$field] ?? '[]', true);
        return is_array($decoded) ? $decoded : [];
    };

    $getFirstId = function (string $sql, array $params = []) use ($pdo) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value !== false ? (int)$value : null;
    };

    $getEnumValues = function (string $table, string $column) use ($pdo) {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));
        $columnInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$columnInfo || !preg_match("/^enum\\((.*)\\)$/i", $columnInfo['Type'], $matches)) {
            return [];
        }
        return str_getcsv($matches[1], ',', "'");
    };

    $lookupIdByName = function (string $table, string $name) use ($pdo) {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $allowedTables = ['faculties_list', 'assessment_forms', 'assessment_tools'];
        if (!in_array($table, $allowedTables, true)) {
            throw new Exception('Bang danh muc khong hop le.');
        }

        $stmt = $pdo->prepare("SELECT id FROM `{$table}` WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (int)$value : null;
    };

    $getNameMapByIds = function (string $table, array $ids) use ($pdo) {
        if (empty($ids)) {
            return [];
        }

        $allowedTables = ['departments_list', 'lecturers'];
        if (!in_array($table, $allowedTables, true)) {
            throw new Exception('Bang danh muc khong hop le.');
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id, name FROM `{$table}` WHERE id IN ({$placeholders})");
        $stmt->execute($ids);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int)$row['id']] = $row['name'];
        }
        return $map;
    };

    $getCourseMapByIds = function (array $ids) use ($pdo) {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id, code, name FROM courses WHERE id IN ({$placeholders})");
        $stmt->execute($ids);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int)$row['id']] = $row;
        }
        return $map;
    };

    $getFacilityId = function ($name) use ($pdo) {
        $name = trim((string)$name);
        if ($name === '') {
            return null;
        }

        $stmt = $pdo->prepare('SELECT id FROM facilities WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }

        $stmt = $pdo->prepare('INSERT INTO facilities (name) VALUES (?)');
        $stmt->execute([$name]);
        return (int)$pdo->lastInsertId();
    };

    $normalizeModuleType = function (string $value): string {
        $value = trim($value);
        if ($value === '') {
            return 'Không';
        }
        if (mb_stripos($value, 'Bắt buộc') !== false) {
            return 'Bắt buộc';
        }
        if (mb_stripos($value, 'Điều kiện') !== false) {
            return 'Điều kiện';
        }
        if (mb_stripos($value, 'Tự chọn') !== false) {
            return 'Tự chọn';
        }
        return 'Không';
    };

    $normalizeAssessmentComponent = function (string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (mb_stripos($value, 'Chuyên cần') !== false) {
            return 'Chuyên cần';
        }
        if (mb_stripos($value, 'Kiểm tra thường xuyên') !== false) {
            return 'Kiểm tra thường xuyên';
        }
        if (mb_stripos($value, 'Thi kết thúc') !== false) {
            return 'Thi kết thúc';
        }
        return $value;
    };

    $determineDeliveryMode = function (string $method, int $onlineHours, int $inPersonHours): string {
        $method = mb_strtolower(trim($method), 'UTF-8');
        if ($onlineHours > 0 && $inPersonHours === 0) {
            return 'Học trực tuyến';
        }
        if ($method !== '' && mb_stripos($method, 'trực tuyến') !== false && mb_stripos($method, 'trên lớp') === false) {
            return 'Học trực tuyến';
        }
        return 'Học trên lớp';
    };

    $moduleTypeEnumValues = $getEnumValues('modules', 'type');
    $assessmentComponentEnumValues = $getEnumValues('assessments', 'component');
    $relationTypeEnumValues = $getEnumValues('module_relationships', 'relation_type');
    $resourceTypeEnumValues = $getEnumValues('resources', 'resource_type');
    $deliveryModeEnumValues = $getEnumValues('theory_topics', 'delivery_mode');

    $moduleTypeStorageMap = [
        'Không' => $moduleTypeEnumValues[0] ?? 'Không',
        'Bắt buộc' => $moduleTypeEnumValues[1] ?? 'Bắt buộc',
        'Điều kiện' => $moduleTypeEnumValues[2] ?? 'Điều kiện',
        'Tự chọn' => $moduleTypeEnumValues[3] ?? 'Tự chọn',
    ];
    $assessmentComponentStorageMap = [
        'Chuyên cần' => $assessmentComponentEnumValues[0] ?? 'Chuyên cần',
        'Kiểm tra thường xuyên' => $assessmentComponentEnumValues[1] ?? 'Kiểm tra thường xuyên',
        'Thi kết thúc' => $assessmentComponentEnumValues[2] ?? 'Thi kết thúc',
    ];
    $relationTypeStorageMap = [
        'Tiên quyết' => $relationTypeEnumValues[0] ?? 'Tiên quyết',
        'Song hành' => $relationTypeEnumValues[1] ?? 'Song hành',
        'Học trước' => $relationTypeEnumValues[2] ?? 'Học trước',
    ];
    $resourceTypeStorageMap = [
        'Tài liệu giảng dạy' => $resourceTypeEnumValues[0] ?? 'Tài liệu giảng dạy',
        'Tài liệu tự học' => $resourceTypeEnumValues[1] ?? 'Tài liệu tự học',
    ];
    $deliveryModeStorageMap = [
        'Học trên lớp' => $deliveryModeEnumValues[0] ?? 'Học trên lớp',
        'Học trực tuyến' => $deliveryModeEnumValues[1] ?? 'Học trực tuyến',
    ];

    $normalizeCloCodes = function ($value, string $fallback = ''): array {
        $source = strtoupper(trim((string)$value));
        if ($source === '') {
            $source = strtoupper(trim($fallback));
        }
        if ($source === '') {
            return [];
        }

        if (preg_match_all('/CLO\s*\d+/i', $source, $matches)) {
            $codes = array_map(
                static fn($code) => preg_replace('/\s+/', '', strtoupper($code)),
                $matches[0]
            );
        } else {
            $codes = preg_split('/[\s,;\/+\|]+/u', $source) ?: [];
            $codes = array_map(static fn($code) => strtoupper(trim($code)), $codes);
        }

        return array_values(array_unique(array_filter($codes)));
    };

    $bookTitleFromValue = function ($value) use ($pdo) {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        if (ctype_digit($value)) {
            $stmt = $pdo->prepare('SELECT title FROM books_catalog WHERE id = ?');
            $stmt->execute([(int)$value]);
            $title = $stmt->fetchColumn();
            return $title ?: '';
        }

        return $value;
    };

    $courseId = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
    $selectedMajorId = !empty($_POST['major_id']) ? (int)$_POST['major_id'] : null;
    $programYear = trim((string)($_POST['year'] ?? ''));
    $code = strtoupper(trim((string)($_POST['code'] ?? '')));
    $name = trim((string)($_POST['name'] ?? ''));

    $type = $normalizeModuleType((string)($_POST['module_type'] ?? $_POST['type'] ?? ''));
    $storedType = $moduleTypeStorageMap[$type] ?? $type;
    $credits = (int)($_POST['credits'] ?? 0);
    $creditsTheory = (int)($_POST['credits_theory'] ?? 0);
    $creditsPractice = (int)($_POST['credits_practice'] ?? 0);
    $totalHours = (int)($_POST['total_hours'] ?? 0);
    $theoryHours = (int)($_POST['theory_hours'] ?? 0);
    $practicalHours = (int)($_POST['practical_hours'] ?? 0);
    $selfStudyHours = (int)($_POST['self_study_hours'] ?? 0);

    $targetPrograms = trim((string)($_POST['target_programs'] ?? ''));
    $expectedSemester = trim((string)($_POST['expected_semester'] ?? ''));
    $expectedYear = trim((string)($_POST['expected_year'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $objectiveGeneral = trim((string)($_POST['objective_general'] ?? ''));
    $objectiveSpecific = trim((string)($_POST['objective_specific'] ?? ''));
    $objectivePlo = trim((string)($_POST['objective_plo'] ?? ''));
    $gradingScale = trim((string)($_POST['grading_scale'] ?? ''));

    $prerequisiteIds = $normalizeIntList($_POST['prerequisite_modules'] ?? []);
    $parallelIds = $normalizeIntList($_POST['parallel_modules'] ?? []);
    $previousIds = $normalizeIntList($_POST['previous_modules'] ?? []);
    $departmentIds = $normalizeIntList($_POST['department_in_charge'] ?? []);
    $coordinatorIds = $normalizeIntList($_POST['coordinating_board'] ?? []);
    $facultySelections = $normalizePostedList($_POST['faculty_in_charge'] ?? []);

    if ($code === '' || $name === '') {
        throw new Exception('Mã học phần và tên học phần không được để trống.');
    }

    $courseMap = $getCourseMapByIds(array_merge($prerequisiteIds, $parallelIds, $previousIds));
    $departmentNameMap = $getNameMapByIds('departments_list', $departmentIds);
    $lecturerNameMap = $getNameMapByIds('lecturers', $coordinatorIds);

    $courseCodesFromIds = function (array $ids) use ($courseMap) {
        $codes = [];
        foreach ($ids as $id) {
            if (!empty($courseMap[$id]['code'])) {
                $codes[] = $courseMap[$id]['code'];
            }
        }
        return array_values(array_unique($codes));
    };

    $namesFromIds = function (array $ids, array $nameMap) {
        $names = [];
        foreach ($ids as $id) {
            if (!empty($nameMap[$id])) {
                $names[] = $nameMap[$id];
            }
        }
        return array_values(array_unique($names));
    };

    $prerequisiteText = implode(', ', $courseCodesFromIds($prerequisiteIds));
    $parallelText = implode(', ', $courseCodesFromIds($parallelIds));
    $previousText = implode(', ', $courseCodesFromIds($previousIds));
    $departmentText = implode(', ', $namesFromIds($departmentIds, $departmentNameMap));
    $coordinatingBoardText = implode(', ', $namesFromIds($coordinatorIds, $lecturerNameMap));
    $facultyText = implode(', ', $facultySelections);
    $facultyId = !empty($facultySelections) ? $lookupIdByName('faculties_list', $facultySelections[0]) : null;

    $resolveMajorId = function (?int $preferredMajorId) use ($getFirstId) {
        if (!empty($preferredMajorId)) {
            return $preferredMajorId;
        }

        $majorId = $getFirstId('SELECT id FROM majors ORDER BY id ASC LIMIT 1');
        if (!$majorId) {
            throw new Exception('Chưa có ngành học nào trong hệ thống.');
        }
        return $majorId;
    };

    $resolveBlockId = function (int $majorId) use ($getFirstId) {
        return $getFirstId('SELECT id FROM knowledge_blocks WHERE major_id = ? ORDER BY id ASC LIMIT 1', [$majorId]);
    };

    $findCourseByCode = function (string $courseCode) use ($pdo) {
        $stmt = $pdo->prepare('SELECT * FROM courses WHERE code = ? LIMIT 1');
        $stmt->execute([$courseCode]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    };

    $selectedCourse = null;
    if ($courseId) {
        $stmt = $pdo->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
        $stmt->execute([$courseId]);
        $selectedCourse = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$selectedCourse) {
            throw new Exception('Học phần nền được chọn không tồn tại.');
        }
    } else {
        $selectedCourse = $findCourseByCode($code);
        if ($selectedCourse) {
            $courseId = (int)$selectedCourse['id'];
        }
    }

    if ($selectedCourse) {
        $courseMajorId = $resolveMajorId($selectedMajorId ?: (int)$selectedCourse['major_id']);
        $blockId = $resolveBlockId($courseMajorId);
        $stmt = $pdo->prepare('
            UPDATE courses
            SET major_id = ?, block_id = ?, code = ?, name = ?, total_hours = ?, theory_hours = ?, practical_hours = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $courseMajorId,
            $blockId,
            $code,
            $name,
            $totalHours,
            $theoryHours,
            $practicalHours,
            $courseId,
        ]);
    } else {
        $courseMajorId = $resolveMajorId($selectedMajorId);
        $blockId = $resolveBlockId($courseMajorId);
        $sortOrder = $getFirstId('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM courses WHERE major_id = ?', [$courseMajorId]) ?? 1;

        $stmt = $pdo->prepare('
            INSERT INTO courses (major_id, block_id, sort_order, code, name, total_hours, theory_hours, practical_hours)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $courseMajorId,
            $blockId,
            $sortOrder,
            $code,
            $name,
            $totalHours,
            $theoryHours,
            $practicalHours,
        ]);
        $courseId = (int)$pdo->lastInsertId();
    }

    $findExistingModuleId = function (?int $linkedCourseId, string $moduleCode) use ($pdo) {
        if ($linkedCourseId) {
            $stmt = $pdo->prepare('SELECT id FROM modules WHERE course_id = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$linkedCourseId]);
            $moduleId = $stmt->fetchColumn();
            if ($moduleId !== false) {
                return (int)$moduleId;
            }
        }

        $stmt = $pdo->prepare('SELECT id FROM modules WHERE code = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$moduleCode]);
        $moduleId = $stmt->fetchColumn();
        return $moduleId !== false ? (int)$moduleId : null;
    };

    $moduleId = $findExistingModuleId($courseId, $code);
    if ($moduleId) {
        $stmt = $pdo->prepare('SELECT id FROM modules WHERE code = ? AND id <> ? LIMIT 1');
        $stmt->execute([$code, $moduleId]);
        if ($stmt->fetchColumn()) {
            throw new Exception('Mã học phần đã được dùng cho đề cương khác.');
        }
    }

    $pdo->beginTransaction();

    $deleteByModule = function (string $table) use ($pdo) {
        $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE module_id = ?");
        return $stmt;
    };

    if ($moduleId) {
        $stmt = $pdo->prepare('
            UPDATE modules SET
                course_id = ?, code = ?, name = ?, type = ?,
                credits = ?, credits_theory = ?, credits_practice = ?,
                total_hours = ?, theory_hours = ?, practical_hours = ?, self_study_hours = ?,
                target_programs = ?, expected_semester = ?, expected_year = ?,
                prerequisite_modules = ?, parallel_modules = ?, previous_modules = ?,
                department_in_charge = ?, coordinating_board = ?, faculty_in_charge = ?,
                description = ?, objective_general = ?, objective_po = ?, objective_plo = ?, objective_specific = ?,
                grading_scale = ?, faculty_id = ?, program_year = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $courseId,
            $code,
            $name,
            $storedType,
            $credits,
            $creditsTheory,
            $creditsPractice,
            $totalHours,
            $theoryHours,
            $practicalHours,
            $selfStudyHours,
            $targetPrograms,
            $expectedSemester,
            $expectedYear,
            $prerequisiteText,
            $parallelText,
            $previousText,
            $departmentText,
            $coordinatingBoardText,
            $facultyText,
            $description,
            $objectiveGeneral,
            $objectiveSpecific,
            $objectivePlo,
            $objectiveSpecific,
            $gradingScale,
            $facultyId,
            $programYear,
            $moduleId,
        ]);

        foreach ([
            'resources',
            'assessments',
            'self_study_activities',
            'theory_topics',
            'practical_topics',
            'combined_topics',
            'module_relationships',
            'module_departments',
            'course_coordinators',
        ] as $table) {
            $stmtDelete = $deleteByModule($table);
            $stmtDelete->execute([$moduleId]);
        }

        $stmt = $pdo->prepare('DELETE FROM clos WHERE module_id = ?');
        $stmt->execute([$moduleId]);
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO modules (
                course_id, code, name, type,
                credits, credits_theory, credits_practice,
                total_hours, theory_hours, practical_hours, self_study_hours,
                target_programs, expected_semester, expected_year,
                prerequisite_modules, parallel_modules, previous_modules,
                department_in_charge, coordinating_board, faculty_in_charge,
                description, objective_general, objective_po, objective_plo, objective_specific,
                grading_scale, faculty_id, program_year
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?
            )
        ');
        $stmt->execute([
            $courseId,
            $code,
            $name,
            $storedType,
            $credits,
            $creditsTheory,
            $creditsPractice,
            $totalHours,
            $theoryHours,
            $practicalHours,
            $selfStudyHours,
            $targetPrograms,
            $expectedSemester,
            $expectedYear,
            $prerequisiteText,
            $parallelText,
            $previousText,
            $departmentText,
            $coordinatingBoardText,
            $facultyText,
            $description,
            $objectiveGeneral,
            $objectiveSpecific,
            $objectivePlo,
            $objectiveSpecific,
            $gradingScale,
            $facultyId,
            $programYear,
        ]);
        $moduleId = (int)$pdo->lastInsertId();
    }

    $stmtRelation = $pdo->prepare('INSERT INTO module_relationships (module_id, related_course_id, relation_type) VALUES (?, ?, ?)');
    foreach ($prerequisiteIds as $relatedCourseId) {
        $stmtRelation->execute([$moduleId, $relatedCourseId, $relationTypeStorageMap['Tiên quyết'] ?? 'Tiên quyết']);
    }
    foreach ($parallelIds as $relatedCourseId) {
        $stmtRelation->execute([$moduleId, $relatedCourseId, $relationTypeStorageMap['Song hành'] ?? 'Song hành']);
    }
    foreach ($previousIds as $relatedCourseId) {
        $stmtRelation->execute([$moduleId, $relatedCourseId, $relationTypeStorageMap['Học trước'] ?? 'Học trước']);
    }

    if (!empty($departmentIds)) {
        $stmtDepartment = $pdo->prepare('INSERT INTO module_departments (module_id, department_id) VALUES (?, ?)');
        foreach ($departmentIds as $departmentId) {
            $stmtDepartment->execute([$moduleId, $departmentId]);
        }
    }

    if (!empty($coordinatorIds)) {
        $stmtCoordinator = $pdo->prepare('INSERT INTO course_coordinators (module_id, lecturer_id) VALUES (?, ?)');
        foreach ($coordinatorIds as $lecturerId) {
            $stmtCoordinator->execute([$moduleId, $lecturerId]);
        }
    }

    $closRows = $decodeJsonArray('clos_json');
    $assessmentRows = $decodeJsonArray('assessments_json');
    $selfStudyRows = $decodeJsonArray('self_study_json');
    $theoryRows = $decodeJsonArray('theory_json');
    $practicalRows = $decodeJsonArray('practical_json');
    $combinedRows = $decodeJsonArray('combined_json');
    $teachResources = $decodeJsonArray('res_teach_json');
    $selfResources = $decodeJsonArray('res_self_json');

    $cloIdMap = [];
    $cloExactMap = [];
    $cloTokenMap = [];

    $stmtClo = $pdo->prepare('
        INSERT INTO clos (module_id, code, content, domain, bloom_level, contribution_level)
        VALUES (?, ?, ?, ?, ?, ?)
    ');

    foreach ($closRows as $index => $row) {
        $displayCode = trim((string)($row['code'] ?? ''));
        $descriptionText = trim((string)($row['description'] ?? ''));
        if ($displayCode === '' && $descriptionText === '') {
            continue;
        }
        if ($displayCode === '') {
            $displayCode = 'CLO' . ($index + 1);
        }

        $stmtClo->execute([
            $moduleId,
            $displayCode,
            $descriptionText,
            trim((string)($row['domain'] ?? '')),
            trim((string)($row['bloom'] ?? '')),
            trim((string)($row['contribution'] ?? '')) ?: null,
        ]);

        $cloId = (int)$pdo->lastInsertId();
        $tokens = $normalizeCloCodes($displayCode, 'CLO' . ($index + 1));
        $exactKey = implode(', ', $tokens);

        $cloIdMap[strtoupper($displayCode)] = $cloId;
        if ($exactKey !== '') {
            $cloExactMap[$exactKey] = $cloId;
        }
        foreach ($tokens as $token) {
            $cloTokenMap[$token] ??= [];
            $cloTokenMap[$token][] = $cloId;
        }
    }

    $resolveCloIds = function (string $cloCodesString) use ($normalizeCloCodes, $cloExactMap, $cloIdMap, $cloTokenMap) {
        $codes = $normalizeCloCodes($cloCodesString);
        if (empty($codes)) {
            return [];
        }

        $exactKey = implode(', ', $codes);
        $resolved = [];

        if ($exactKey !== '' && isset($cloExactMap[$exactKey])) {
            $resolved[] = $cloExactMap[$exactKey];
        } elseif (isset($cloIdMap[strtoupper(trim($cloCodesString))])) {
            $resolved[] = $cloIdMap[strtoupper(trim($cloCodesString))];
        } else {
            foreach ($codes as $code) {
                foreach ($cloTokenMap[$code] ?? [] as $cloId) {
                    $resolved[] = $cloId;
                }
            }
        }

        return array_values(array_unique($resolved));
    };

    $linkClosToEntity = function (string $tableName, string $foreignKeyName, int $entityId, string $cloCodesString) use ($pdo, $resolveCloIds) {
        $cloIds = $resolveCloIds($cloCodesString);
        if (empty($cloIds)) {
            return;
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO `{$tableName}` (`{$foreignKeyName}`, `clo_id`) VALUES (?, ?)");
        foreach ($cloIds as $cloId) {
            $stmt->execute([$entityId, $cloId]);
        }
    };

    $ploIdByCode = [];
    foreach ($pdo->query('SELECT id, code FROM plos')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ploIdByCode[strtoupper(trim($row['code']))] = (int)$row['id'];
    }

    $linkPloCodesToClos = function (array $ploCodes, string $cloCodesString) use ($pdo, $resolveCloIds, $ploIdByCode) {
        $cloIds = $resolveCloIds($cloCodesString);
        if (empty($cloIds) || empty($ploCodes)) {
            return;
        }

        $stmt = $pdo->prepare('INSERT IGNORE INTO clo_plos (clo_id, plo_id) VALUES (?, ?)');
        foreach ($ploCodes as $ploCode) {
            $ploKey = strtoupper(trim($ploCode));
            $ploId = $ploIdByCode[$ploKey] ?? null;
            if (!$ploId) {
                continue;
            }
            foreach ($cloIds as $cloId) {
                $stmt->execute([$cloId, $ploId]);
            }
        }
    };

    $groupedAssessments = [];
    foreach ($assessmentRows as $row) {
        $component = $normalizeAssessmentComponent((string)($row['form'] ?? $row['component'] ?? ''));
        $closText = implode(', ', $normalizeCloCodes((string)($row['clos'] ?? '')));
        $toolNames = $normalizeTextList($row['tool'] ?? '');
        $ploCodes = $normalizeTextList($row['plo'] ?? '');
        $piCodes = $normalizeTextList($row['pi'] ?? '');
        $contribution = trim((string)($row['contribution'] ?? ''));
        $weight = (float)($row['weight'] ?? 0);

        $ploPiText = trim((string)($row['plo_pi'] ?? ''));
        if ($ploPiText === '') {
            $ploPiText = implode(' / ', array_filter([
                implode(', ', $ploCodes),
                implode(', ', $piCodes),
            ]));
        }

        if (
            $component === '' &&
            $closText === '' &&
            empty($toolNames) &&
            $ploPiText === '' &&
            $contribution === '' &&
            $weight <= 0
        ) {
            continue;
        }

        if ($component === '') {
            continue;
        }

        if (!isset($groupedAssessments[$component])) {
            $groupedAssessments[$component] = [
                'clos' => [],
                'plo_pi' => [],
                'contribution' => [],
                'tools' => [],
                'weight' => 0.0,
                'plo_codes' => [],
            ];
        }

        if ($closText !== '') {
            $groupedAssessments[$component]['clos'] = array_values(array_unique(array_merge(
                $groupedAssessments[$component]['clos'],
                $normalizeCloCodes($closText)
            )));
        }

        if ($ploPiText !== '') {
            $groupedAssessments[$component]['plo_pi'][] = $ploPiText;
        }

        if ($contribution !== '') {
            $groupedAssessments[$component]['contribution'][] = $contribution;
        }

        if (!empty($toolNames)) {
            $groupedAssessments[$component]['tools'] = array_values(array_unique(array_merge(
                $groupedAssessments[$component]['tools'],
                $toolNames
            )));
        }

        if (!empty($ploCodes)) {
            $groupedAssessments[$component]['plo_codes'] = array_values(array_unique(array_merge(
                $groupedAssessments[$component]['plo_codes'],
                $ploCodes
            )));
        }

        $groupedAssessments[$component]['weight'] += $weight;
    }

    $stmtAssessment = $pdo->prepare('
        INSERT INTO assessments (module_id, component, weight, clos_text, plo_pi, contribution, form, tool)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');

    $defaultAssessmentForms = [
        'Chuyên cần' => ['Điểm danh', 'Hỏi đáp'],
        'Kiểm tra thường xuyên' => ['Bài tập tình huống', 'Thuyết trình nhóm'],
        'Thi kết thúc' => ['Trắc nghiệm', 'OSCE/OSPE'],
    ];

    foreach ($groupedAssessments as $component => $row) {
        $storedComponent = $assessmentComponentStorageMap[$component] ?? $component;
        $closText = implode(', ', $row['clos']);
        $ploPiText = implode(' | ', array_values(array_unique($row['plo_pi'])));
        $contributionText = implode(', ', array_values(array_unique($row['contribution'])));
        $toolText = implode(', ', $row['tools']);
        $weight = round($row['weight'], 2);

        $stmtAssessment->execute([
            $moduleId,
            $storedComponent,
            $weight,
            $closText,
            $ploPiText,
            $contributionText,
            $component,
            $toolText,
        ]);

        $assessmentId = (int)$pdo->lastInsertId();
        if ($closText !== '') {
            $linkClosToEntity('assessment_clos', 'assessment_id', $assessmentId, $closText);
            $linkPloCodesToClos($row['plo_codes'], $closText);
        }

        $stmtToolRelation = $pdo->prepare('
            INSERT IGNORE INTO assessment_tool_relation (assessment_id, tool_id, sort_order, note)
            VALUES (?, ?, ?, NULL)
        ');
        foreach (array_values($row['tools']) as $toolIndex => $toolName) {
            $toolId = $lookupIdByName('assessment_tools', $toolName);
            if (!$toolId) {
                $stmtInsertTool = $pdo->prepare('INSERT INTO assessment_tools (name, description) VALUES (?, NULL)');
                $stmtInsertTool->execute([$toolName]);
                $toolId = (int)$pdo->lastInsertId();
            }
            $stmtToolRelation->execute([$assessmentId, $toolId, $toolIndex + 1]);
        }

        $stmtFormRelation = $pdo->prepare('
            INSERT IGNORE INTO assessment_form_relation (assessment_id, assessment_form_id, sort_order, note)
            VALUES (?, ?, ?, NULL)
        ');
        foreach (($defaultAssessmentForms[$component] ?? []) as $formIndex => $formName) {
            $formId = $lookupIdByName('assessment_forms', $formName);
            if ($formId) {
                $stmtFormRelation->execute([$assessmentId, $formId, $formIndex + 1]);
            }
        }
    }

    $stmtSelfStudy = $pdo->prepare('
        INSERT INTO self_study_activities (module_id, activity_name, duration_hours, method, assessment_method, evidence, clos_text)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');

    foreach ($selfStudyRows as $row) {
        $activityName = trim((string)($row['name'] ?? ''));
        $closText = trim((string)($row['clos'] ?? ''));
        $hours = (int)($row['hours'] ?? 0);
        $method = trim((string)($row['method'] ?? ''));
        $assessmentMethod = trim((string)($row['assess'] ?? ''));
        $evidence = trim((string)($row['evidence'] ?? ''));

        if ($activityName === '' && $closText === '' && $hours === 0 && $method === '' && $assessmentMethod === '' && $evidence === '') {
            continue;
        }

        $stmtSelfStudy->execute([
            $moduleId,
            $activityName,
            $hours,
            $method,
            $assessmentMethod,
            $evidence,
            $closText,
        ]);

        $selfStudyId = (int)$pdo->lastInsertId();
        if ($closText !== '') {
            $linkClosToEntity('self_study_clos', 'self_study_activity_id', $selfStudyId, $closText);
        }
    }

    $stmtTheory = $pdo->prepare('
        INSERT INTO theory_topics (
            module_id, parent_id, chapter, title, delivery_mode, teaching_method,
            class_hours, online_hours, self_study_hours,
            method, hours_online, pedagogy, textbook_info, clos_text
        ) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    foreach ($theoryRows as $row) {
        $chapter = trim((string)($row['chapter'] ?? ''));
        $title = trim((string)($row['title'] ?? ''));
        $method = trim((string)($row['method'] ?? ''));
        $hoursClass = (int)($row['hours_class'] ?? 0);
        $hoursSelf = (int)($row['hours_self'] ?? 0);
        $hoursOnline = (int)($row['hours_online'] ?? 0);
        $pedagogy = trim((string)($row['pedagogy'] ?? ''));
        $closText = trim((string)($row['clos'] ?? ''));
        $book = trim((string)($row['book'] ?? ''));

        if ($title === '' && $chapter === '' && $method === '' && $hoursClass === 0 && $hoursSelf === 0 && $hoursOnline === 0 && $pedagogy === '' && $closText === '' && $book === '') {
            continue;
        }

        $deliveryMode = $determineDeliveryMode($method, $hoursOnline, $hoursClass);
        $storedDeliveryMode = $deliveryModeStorageMap[$deliveryMode] ?? $deliveryMode;
        $stmtTheory->execute([
            $moduleId,
            $chapter,
            $title,
            $storedDeliveryMode,
            $pedagogy,
            $hoursClass,
            $hoursOnline,
            $hoursSelf,
            $method,
            $hoursOnline,
            $pedagogy,
            $book,
            $closText,
        ]);

        $theoryId = (int)$pdo->lastInsertId();
        if ($closText !== '') {
            $linkClosToEntity('theory_topic_clos', 'theory_topic_id', $theoryId, $closText);
        }
    }

    $stmtPractical = $pdo->prepare('
        INSERT INTO practical_topics (
            module_id, topic, content, delivery_mode, teaching_method,
            lab_hours, online_hours, facility_id,
            method, hours_online, pedagogy, clos_text
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    foreach ($practicalRows as $row) {
        $topic = trim((string)($row['topic'] ?? ''));
        $content = trim((string)($row['content'] ?? ''));
        $method = trim((string)($row['method'] ?? ''));
        $hoursLab = (int)($row['hours_lab'] ?? 0);
        $hoursOnline = (int)($row['hours_online'] ?? 0);
        $pedagogy = trim((string)($row['pedagogy'] ?? ''));
        $closText = trim((string)($row['clos'] ?? ''));
        $facilityId = $getFacilityId($row['facility'] ?? '');

        if ($topic === '' && $content === '' && $method === '' && $hoursLab === 0 && $hoursOnline === 0 && $pedagogy === '' && $closText === '' && !$facilityId) {
            continue;
        }

        $deliveryMode = $determineDeliveryMode($method, $hoursOnline, $hoursLab);
        $storedDeliveryMode = $deliveryModeStorageMap[$deliveryMode] ?? $deliveryMode;
        $stmtPractical->execute([
            $moduleId,
            $topic,
            $content,
            $storedDeliveryMode,
            $pedagogy,
            $hoursLab,
            $hoursOnline,
            $facilityId,
            $method,
            $hoursOnline,
            $pedagogy,
            $closText,
        ]);

        $practicalId = (int)$pdo->lastInsertId();
        if ($closText !== '') {
            $linkClosToEntity('practical_topic_clos', 'practical_topic_id', $practicalId, $closText);
        }
    }

    $stmtCombined = $pdo->prepare('
        INSERT INTO combined_topics (
            module_id, sort_order, content, delivery_mode, teaching_method,
            theory_hours, practical_hours, online_hours, self_study_hours, facility_id,
            method, hours_online, pedagogy, clos_text
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    foreach ($combinedRows as $index => $row) {
        $sortOrder = (int)($row['stt'] ?? ($index + 1));
        $content = trim((string)($row['content'] ?? ''));
        $method = trim((string)($row['method'] ?? ''));
        $hoursTheory = (int)($row['hours_theory'] ?? 0);
        $hoursPractice = (int)($row['hours_practice'] ?? 0);
        $hoursOnline = (int)($row['hours_online'] ?? 0);
        $hoursSelf = (int)($row['hours_self'] ?? 0);
        $pedagogy = trim((string)($row['pedagogy'] ?? ''));
        $closText = trim((string)($row['clos'] ?? ''));
        $facilityId = $getFacilityId($row['facility'] ?? '');

        if ($content === '' && $method === '' && $hoursTheory === 0 && $hoursPractice === 0 && $hoursOnline === 0 && $hoursSelf === 0 && $pedagogy === '' && $closText === '' && !$facilityId) {
            continue;
        }

        $deliveryMode = $determineDeliveryMode($method, $hoursOnline, $hoursTheory + $hoursPractice);
        $storedDeliveryMode = $deliveryModeStorageMap[$deliveryMode] ?? $deliveryMode;
        $stmtCombined->execute([
            $moduleId,
            $sortOrder,
            $content,
            $storedDeliveryMode,
            $pedagogy,
            $hoursTheory,
            $hoursPractice,
            $hoursOnline,
            $hoursSelf,
            $facilityId,
            $method,
            $hoursOnline,
            $pedagogy,
            $closText,
        ]);

        $combinedId = (int)$pdo->lastInsertId();
        if ($closText !== '') {
            $linkClosToEntity('combined_topic_clos', 'combined_topic_id', $combinedId, $closText);
        }
    }

    $stmtResource = $pdo->prepare('
        INSERT INTO resources (module_id, resource_type, sort_order, title, editor, publisher, year, identifier, book_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    $insertResources = function (array $resources, string $resourceType) use ($stmtResource, $pdo, $bookTitleFromValue, $moduleId) {
        foreach ($resources as $index => $resource) {
            $title = trim((string)($resource['title'] ?? ''));
            if ($title === '' || mb_strpos($title, '-- Chọn') === 0) {
                continue;
            }

            $identifier = trim((string)($resource['isbn'] ?? $resource['identifier'] ?? ''));
            $bookId = null;

            $stmt = $pdo->prepare('SELECT id FROM books_catalog WHERE title = ? LIMIT 1');
            $stmt->execute([$title]);
            $existingBookId = $stmt->fetchColumn();
            if ($existingBookId !== false) {
                $bookId = (int)$existingBookId;
            }

            $stmtResource->execute([
                $moduleId,
                $resourceType,
                $index + 1,
                $bookTitleFromValue($title),
                trim((string)($resource['editor'] ?? '')),
                trim((string)($resource['publisher'] ?? '')),
                trim((string)($resource['year'] ?? '')),
                $identifier,
                $bookId,
            ]);
        }
    };

    $insertResources($teachResources, $resourceTypeStorageMap['Tài liệu giảng dạy'] ?? 'Tài liệu giảng dạy');
    $insertResources($selfResources, $resourceTypeStorageMap['Tài liệu tự học'] ?? 'Tài liệu tự học');

    $pdo->commit();

    header('Location: view.php?id=' . $moduleId);
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die('Lỗi hệ thống trong quá trình lưu dữ liệu: ' . $e->getMessage());
}
?>

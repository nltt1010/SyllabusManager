<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

function normalize_clo_codes(string $value): array
{
    $value = strtoupper(trim($value));
    if ($value === '') {
        return [];
    }

    if (preg_match_all('/CLO\s*\d+/i', $value, $matches)) {
        return array_values(array_unique(array_map(
            fn($item) => preg_replace('/\s+/', '', strtoupper($item)),
            $matches[0]
        )));
    }

    $parts = preg_split('/[\s,;\/+\|]+/u', $value);
    return array_values(array_unique(array_filter(array_map(
        fn($item) => strtoupper(trim((string)$item)),
        $parts ?: []
    ))));
}

function resolve_department_names(PDO $pdo, array $ids): string
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (empty($ids)) {
        return '';
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT name FROM departments_list WHERE id IN ({$placeholders}) ORDER BY name");
    $stmt->execute($ids);
    return implode(', ', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function resolve_facility_id(PDO $pdo, string $name): ?int
{
    $name = trim($name);
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
}

function resolve_lecturer_id(PDO $pdo, string $name): ?int
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id FROM lecturers WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int)$id;
    }

    $stmt = $pdo->prepare('INSERT INTO lecturers (name) VALUES (?)');
    $stmt->execute([$name]);
    return (int)$pdo->lastInsertId();
}

function resolve_assessment_tool(PDO $pdo, string $category, string $rawValue): ?array
{
    $category = syllabus_assessment_bucket($category);
    $rawValue = syllabus_assessment_tool_label($rawValue);
    $rawValue = trim($rawValue);
    if ($rawValue === '') {
        return null;
    }

    if (ctype_digit($rawValue)) {
        $stmt = $pdo->prepare('SELECT id, name FROM assessment_tools WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$rawValue]);
        $tool = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($tool) {
            return ['id' => (int)$tool['id'], 'name' => $tool['name']];
        }
    }

    $stmt = $pdo->prepare('SELECT id, name FROM assessment_tools WHERE assessment_type = ? AND name = ? LIMIT 1');
    $stmt->execute([$category, $rawValue]);
    $tool = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($tool) {
        return ['id' => (int)$tool['id'], 'name' => $tool['name']];
    }

    $stmt = $pdo->prepare('INSERT INTO assessment_tools (assessment_type, name) VALUES (?, ?)');
    $stmt->execute([$category, $rawValue]);
    return ['id' => (int)$pdo->lastInsertId(), 'name' => $rawValue];
}

try {
    $courseId = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
    if ($courseId <= 0) {
        throw new RuntimeException('Vui lòng chọn học phần khung trước khi lưu.');
    }

    $framework = syllabus_get_course_framework($pdo, $courseId);
    if (!$framework) {
        throw new RuntimeException('Không tìm thấy dữ liệu khung của học phần đã chọn.');
    }

    $name = trim((string)($framework['name'] ?? ''));
    $code = trim((string)($framework['code'] ?? ''));
    if ($name === '' || $code === '') {
        throw new RuntimeException('Dữ liệu khung của học phần chưa đầy đủ mã hoặc tên.');
    }

    $educationProgramId = (int)($framework['education_program_id'] ?? ($_POST['education_program_id'] ?? 0));
    $departmentIds = syllabus_parse_id_list($_POST['department_in_charge'] ?? []);
    $coordinatorNames = array_values(array_unique(array_filter(array_map('trim', $_POST['coordinator_names'] ?? []))));
    $departmentText = resolve_department_names($pdo, $departmentIds);
    $coordinatingBoard = implode(', ', $coordinatorNames);

    $description = trim((string)($_POST['description'] ?? ''));
    $objectives = trim((string)($_POST['objectives'] ?? ''));
    $selfStudyHours = max(0, (int)($_POST['self_study_hours'] ?? 0));
    $deliveryMode = trim((string)($_POST['delivery_mode'] ?? 'Học trên lớp'));

    $clos = json_decode($_POST['clos_json'] ?? '[]', true) ?: [];
    $assessments = json_decode($_POST['assessments_json'] ?? '[]', true) ?: [];
    $selfStudyItems = json_decode($_POST['self_study_json'] ?? '[]', true) ?: [];
    $theoryItems = json_decode($_POST['theory_json'] ?? '[]', true) ?: [];
    $practicalItems = json_decode($_POST['practical_json'] ?? '[]', true) ?: [];
    $combinedItems = json_decode($_POST['combined_json'] ?? '[]', true) ?: [];
    $resourceTeachItems = json_decode($_POST['res_teach_json'] ?? '[]', true) ?: [];
    $resourceSelfItems = json_decode($_POST['res_self_json'] ?? '[]', true) ?: [];

    $pdo->beginTransaction();

    $stmtModule = $pdo->prepare('
        INSERT INTO modules (
            course_id,
            education_program_id,
            code,
            name,
            type,
            credits,
            credits_theory,
            credits_practice,
            total_hours,
            theory_hours,
            practical_hours,
            self_study_hours,
            target_programs,
            expected_semester,
            expected_year,
            prerequisite_modules,
            parallel_modules,
            previous_modules,
            department_in_charge,
            coordinating_board,
            faculty_in_charge,
            delivery_mode,
            description,
            objectives,
            grading_scale
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmtModule->execute([
        $courseId,
        $educationProgramId ?: null,
        $code,
        $name,
        $framework['module_type'] ?? '',
        (int)($framework['credits'] ?? 0),
        (int)($framework['credits_theory'] ?? 0),
        (int)($framework['credits_practice'] ?? 0),
        (int)($framework['total_hours'] ?? 0),
        (int)($framework['theory_hours'] ?? 0),
        (int)($framework['practical_hours'] ?? 0),
        $selfStudyHours,
        $framework['major_name'] ?? '',
        $framework['expected_semester'] ?? '',
        $framework['expected_year'] ?? '',
        $framework['prerequisite_text'] ?? '',
        $framework['parallel_text'] ?? '',
        $framework['previous_text'] ?? '',
        $departmentText,
        $coordinatingBoard,
        $framework['faculty_name'] ?? '',
        $deliveryMode,
        $description,
        $objectives,
        $framework['grading_scale'] ?? 'Học phần được lượng giá theo thang điểm 10.'
    ]);
    $moduleId = (int)$pdo->lastInsertId();

    $pdo->prepare('DELETE FROM course_coordinators WHERE course_id = ?')->execute([$courseId]);
    $stmtCoordinator = $pdo->prepare('INSERT INTO course_coordinators (course_id, lecturer_id, sort_order) VALUES (?, ?, ?)');
    foreach ($coordinatorNames as $index => $nameItem) {
        $lecturerId = resolve_lecturer_id($pdo, $nameItem);
        if ($lecturerId) {
            $stmtCoordinator->execute([$courseId, $lecturerId, $index + 1]);
        }
    }

    $pdo->prepare('DELETE FROM module_departments WHERE module_id = ?')->execute([$moduleId]);
    if (!empty($departmentIds)) {
        $stmtDepartment = $pdo->prepare('INSERT INTO module_departments (module_id, department_id) VALUES (?, ?)');
        foreach ($departmentIds as $departmentId) {
            $stmtDepartment->execute([$moduleId, $departmentId]);
        }
    }

    $stmtRel = $pdo->prepare('INSERT INTO module_relationships (module_id, related_course_id, relation_type) VALUES (?, ?, ?)');
    foreach (($framework['prerequisite_ids'] ?? []) as $relatedCourseId) {
        $stmtRel->execute([$moduleId, (int)$relatedCourseId, 'Tien quyet']);
    }
    foreach (($framework['parallel_ids'] ?? []) as $relatedCourseId) {
        $stmtRel->execute([$moduleId, (int)$relatedCourseId, 'Song hanh']);
    }
    foreach (($framework['previous_ids'] ?? []) as $relatedCourseId) {
        $stmtRel->execute([$moduleId, (int)$relatedCourseId, 'Hoc truoc']);
    }

    $cloIdByCode = [];
    $stmtClo = $pdo->prepare('
        INSERT INTO clos (
            module_id,
            code,
            content,
            domain,
            bloom_level,
            contribution_level,
            pi_id,
            plo_pi
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');

    foreach ($clos as $index => $clo) {
        $codeValue = trim((string)($clo['code'] ?? ''));
        $contentValue = trim((string)($clo['content'] ?? ''));
        if ($codeValue === '' && $contentValue === '') {
            continue;
        }

        if ($codeValue === '') {
            $codeValue = 'CLO' . ($index + 1);
        }

        $stmtClo->execute([
            $moduleId,
            $codeValue,
            $contentValue,
            trim((string)($clo['domain'] ?? '')),
            trim((string)($clo['bloom'] ?? '')),
            trim((string)($clo['contribution_level'] ?? '')),
            trim((string)($clo['pi_id'] ?? '')),
            trim((string)($clo['plo_pi'] ?? '')),
        ]);
        $cloIdByCode[strtoupper($codeValue)] = (int)$pdo->lastInsertId();
    }

    $linkClos = function (string $relationTable, string $foreignKey, int $entityId, string $codes) use ($pdo, $cloIdByCode) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO {$relationTable} ({$foreignKey}, clo_id) VALUES (?, ?)");
        foreach (normalize_clo_codes($codes) as $code) {
            if (!empty($cloIdByCode[$code])) {
                $stmt->execute([$entityId, $cloIdByCode[$code]]);
            }
        }
    };

    $stmtAssessment = $pdo->prepare('
        INSERT INTO assessments (
            module_id,
            type,
            assessment_category,
            component,
            clos_text,
            plo_pi,
            form,
            tool,
            tool_notes,
            weight
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmtToolRelation = $pdo->prepare('INSERT INTO assessment_tool_relation (assessment_id, assessment_tool_id) VALUES (?, ?)');

    foreach ($assessments as $assessment) {
        $category = syllabus_assessment_bucket((string)($assessment['category'] ?? ''));
        $closText = trim((string)($assessment['clos'] ?? ''));
        $ploPi = trim((string)($assessment['plo_pi'] ?? ''));
        $toolValues = $assessment['tools'] ?? [];
        $weight = max(0, (float)($assessment['weight'] ?? 0));

        if ($category === '' && $closText === '' && $ploPi === '' && empty($toolValues) && $weight <= 0) {
            continue;
        }

        $resolvedTools = [];
        foreach ((array)$toolValues as $toolValue) {
            $tool = resolve_assessment_tool($pdo, $category ?: 'Chuyên cần', (string)$toolValue);
            if ($tool) {
                $resolvedTools[$tool['id']] = $tool['name'];
            }
        }

        $toolText = implode(', ', array_values($resolvedTools));

        $stmtAssessment->execute([
            $moduleId,
            $category,
            $category,
            $category,
            $closText,
            $ploPi,
            $category,
            $toolText,
            $toolText,
            $weight,
        ]);

        $assessmentId = (int)$pdo->lastInsertId();
        foreach (array_keys($resolvedTools) as $toolId) {
            $stmtToolRelation->execute([$assessmentId, $toolId]);
        }
        $linkClos('assessment_clos', 'assessment_id', $assessmentId, $closText);
    }

    $stmtSelfStudy = $pdo->prepare('
        INSERT INTO self_study_activities (
            module_id,
            activity_name,
            clos_text,
            duration_hours,
            method,
            assessment_method,
            evidence
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    foreach ($selfStudyItems as $item) {
        $nameValue = trim((string)($item['name'] ?? ''));
        $closText = trim((string)($item['clos'] ?? ''));
        if ($nameValue === '' && $closText === '') {
            continue;
        }

        $stmtSelfStudy->execute([
            $moduleId,
            $nameValue,
            $closText,
            max(0, (int)($item['hours'] ?? 0)),
            trim((string)($item['method'] ?? '')),
            trim((string)($item['assess'] ?? '')),
            trim((string)($item['evidence'] ?? '')),
        ]);
        $linkClos('self_study_clos', 'self_study_activity_id', (int)$pdo->lastInsertId(), $closText);
    }

    $stmtTheory = $pdo->prepare('
        INSERT INTO theory_topics (
            module_id,
            parent_id,
            chapter,
            title,
            method,
            teaching_method,
            class_hours,
            online_hours,
            self_study_hours,
            clos_text
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $theoryIdMap = [];
    $pendingTheoryParents = [];
    foreach ($theoryItems as $item) {
        $chapter = trim((string)($item['chapter'] ?? ''));
        $title = trim((string)($item['title'] ?? ''));
        if ($chapter === '' && $title === '') {
            continue;
        }

        $stmtTheory->execute([
            $moduleId,
            null,
            $chapter,
            $title,
            trim((string)($item['delivery_mode'] ?? '')),
            trim((string)($item['teaching_method'] ?? '')),
            max(0, (int)($item['hours_class'] ?? 0)),
            max(0, (int)($item['hours_online'] ?? 0)),
            max(0, (int)($item['hours_self'] ?? 0)),
            trim((string)($item['clos'] ?? '')),
        ]);
        $theoryId = (int)$pdo->lastInsertId();
        $theoryIdMap[(string)($item['row_key'] ?? '')] = $theoryId;
        $pendingTheoryParents[] = ['id' => $theoryId, 'parent_key' => (string)($item['parent_key'] ?? ''), 'clos' => (string)($item['clos'] ?? '')];
    }

    $stmtTheoryParent = $pdo->prepare('UPDATE theory_topics SET parent_id = ? WHERE id = ?');
    foreach ($pendingTheoryParents as $item) {
        if ($item['parent_key'] !== '' && !empty($theoryIdMap[$item['parent_key']])) {
            $stmtTheoryParent->execute([$theoryIdMap[$item['parent_key']], $item['id']]);
        }
        $linkClos('theory_topic_clos', 'theory_topic_id', $item['id'], $item['clos']);
    }

    $stmtPractical = $pdo->prepare('
        INSERT INTO practical_topics (
            module_id,
            topic,
            content,
            method,
            teaching_method,
            lab_hours,
            online_hours,
            clos_text,
            facility_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    foreach ($practicalItems as $item) {
        $topic = trim((string)($item['topic'] ?? ''));
        $content = trim((string)($item['content'] ?? ''));
        if ($topic === '' && $content === '') {
            continue;
        }

        $stmtPractical->execute([
            $moduleId,
            $topic,
            $content,
            trim((string)($item['delivery_mode'] ?? '')),
            trim((string)($item['teaching_method'] ?? '')),
            max(0, (int)($item['hours_lab'] ?? 0)),
            max(0, (int)($item['hours_online'] ?? 0)),
            trim((string)($item['clos'] ?? '')),
            resolve_facility_id($pdo, (string)($item['facility'] ?? '')),
        ]);
        $linkClos('practical_topic_clos', 'practical_topic_id', (int)$pdo->lastInsertId(), (string)($item['clos'] ?? ''));
    }

    $stmtCombined = $pdo->prepare('
        INSERT INTO combined_topics (
            module_id,
            sort_order,
            content,
            method,
            teaching_method,
            theory_hours,
            practical_hours,
            online_hours,
            self_study_hours,
            clos_text,
            facility_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    foreach ($combinedItems as $item) {
        $content = trim((string)($item['content'] ?? ''));
        if ($content === '') {
            continue;
        }

        $stmtCombined->execute([
            $moduleId,
            max(1, (int)($item['stt'] ?? 1)),
            $content,
            trim((string)($item['delivery_mode'] ?? '')),
            trim((string)($item['teaching_method'] ?? '')),
            max(0, (int)($item['hours_theory'] ?? 0)),
            max(0, (int)($item['hours_practice'] ?? 0)),
            max(0, (int)($item['hours_online'] ?? 0)),
            max(0, (int)($item['hours_self'] ?? 0)),
            trim((string)($item['clos'] ?? '')),
            resolve_facility_id($pdo, (string)($item['facility'] ?? '')),
        ]);
        $linkClos('combined_topic_clos', 'combined_topic_id', (int)$pdo->lastInsertId(), (string)($item['clos'] ?? ''));
    }

    $stmtResource = $pdo->prepare('
        INSERT INTO resources (
            module_id,
            resource_type,
            sort_order,
            title,
            editor,
            publisher,
            year,
            identifier,
            book_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    $insertResources = function (array $items, string $resourceType) use ($stmtResource, $moduleId) {
        foreach ($items as $index => $item) {
            $bookId = !empty($item['book_id']) && ctype_digit((string)$item['book_id']) ? (int)$item['book_id'] : null;
            $title = trim((string)($item['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $stmtResource->execute([
                $moduleId,
                $resourceType,
                $index + 1,
                $title,
                trim((string)($item['editor'] ?? '')),
                trim((string)($item['publisher'] ?? '')),
                trim((string)($item['year'] ?? '')),
                trim((string)($item['identifier'] ?? '')),
                $bookId,
            ]);
        }
    };

    $insertResources($resourceTeachItems, 'Tài liệu giảng dạy');
    $insertResources($resourceSelfItems, 'Tài liệu tự học');

    $pdo->commit();
    header('Location: view.php?id=' . $moduleId);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo 'Lỗi hệ thống trong quá trình lưu dữ liệu: ' . h($e->getMessage());
}

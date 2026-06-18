<?php
require 'db.php';

header('Content-Type: application/json; charset=UTF-8');

$courseId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
if ($courseId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing course_id'], JSON_UNESCAPED_UNICODE);
    exit;
}

$framework = syllabus_get_course_framework($pdo, $courseId);
if (!$framework) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'id' => (int)$framework['id'],
    'education_program_id' => (int)($framework['education_program_id'] ?? 0),
    'program_year' => $framework['program_year'] ?? '',
    'major_id' => (int)($framework['major_id'] ?? 0),
    'major_name' => $framework['major_name'] ?? '',
    'code' => $framework['code'] ?? '',
    'name' => $framework['name'] ?? '',
    'module_type' => $framework['module_type'] ?? '',
    'credits' => (int)($framework['credits'] ?? 0),
    'credits_theory' => (int)($framework['credits_theory'] ?? 0),
    'credits_practice' => (int)($framework['credits_practice'] ?? 0),
    'total_hours' => (int)($framework['total_hours'] ?? 0),
    'theory_hours' => (int)($framework['theory_hours'] ?? 0),
    'practical_hours' => (int)($framework['practical_hours'] ?? 0),
    'expected_semester' => $framework['expected_semester'] ?? '',
    'expected_year' => $framework['expected_year'] ?? '',
    'grading_scale' => $framework['grading_scale'] ?? '',
    'faculty_name' => $framework['faculty_name'] ?? '',
    'prerequisite_ids' => $framework['prerequisite_ids'] ?? [],
    'parallel_ids' => $framework['parallel_ids'] ?? [],
    'previous_ids' => $framework['previous_ids'] ?? [],
    'prerequisite_text' => $framework['prerequisite_text'] ?? '',
    'parallel_text' => $framework['parallel_text'] ?? '',
    'previous_text' => $framework['previous_text'] ?? '',
    'coordinator_names' => $framework['coordinator_names'] ?? [],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

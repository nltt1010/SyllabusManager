<?php
require 'db.php';

header('Content-Type: application/json');

$course_id = $_GET['id'] ?? null;

if (!$course_id) {
    echo json_encode(['error' => 'Missing course_id']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT c.*, m.name as major_name FROM courses c ' .
    'LEFT JOIN majors m ON c.major_id = m.id ' .
    'WHERE c.id = ?'
);
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($course ?: ['error' => 'Not found']);
?>
<?php
require 'db.php';

if (!function_exists('h')) {
    function h($text)
    {
        return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
    }
}

$errorMsg = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add'])) {
            $majorId = !empty($_POST['major_id']) ? (int)$_POST['major_id'] : 0;
            $year = trim((string)($_POST['program_year'] ?? ''));
            $blockId = !empty($_POST['block_id']) ? (int)$_POST['block_id'] : null;
            $code = strtoupper(trim((string)($_POST['code'] ?? '')));
            $name = trim((string)($_POST['name'] ?? ''));
            $moduleType = trim((string)($_POST['module_type'] ?? ''));
            $credits = (int)($_POST['credits'] ?? 0);
            $creditsTheory = (int)($_POST['credits_theory'] ?? 0);
            $creditsPractice = (int)($_POST['credits_practice'] ?? 0);
            $totalHours = (int)($_POST['total_hours'] ?? 0);
            $theoryHours = (int)($_POST['theory_hours'] ?? 0);
            $practicalHours = (int)($_POST['practical_hours'] ?? 0);
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $expectedSemester = trim((string)($_POST['expected_semester'] ?? ''));
            $expectedYear = trim((string)($_POST['expected_year'] ?? ''));
            $gradingScale = trim((string)($_POST['grading_scale'] ?? ''));
            $facultyId = !empty($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : null;
            $prerequisiteIds = syllabus_parse_id_list($_POST['prerequisite_course_ids'] ?? []);
            $parallelIds = syllabus_parse_id_list($_POST['parallel_course_ids'] ?? []);
            $previousIds = syllabus_parse_id_list($_POST['previous_course_ids'] ?? []);

            if ($majorId <= 0 || $year === '' || $code === '' || $name === '') {
                throw new RuntimeException('Vui lòng chọn năm, ngành và nhập đầy đủ mã học phần, tên học phần.');
            }

            if ($creditsTheory + $creditsPractice !== $credits && $credits > 0) {
                throw new RuntimeException('Tổng số tín chỉ phải bằng LT + TH.');
            }

            if ($theoryHours + $practicalHours !== $totalHours && $totalHours > 0) {
                throw new RuntimeException('Tổng số tiết phải bằng LT + TH.');
            }

            $pdo->beginTransaction();

            $stmtProgram = $pdo->prepare('SELECT id FROM education_programs WHERE major_id = ? AND year = ? LIMIT 1');
            $stmtProgram->execute([$majorId, $year]);
            $programId = (int)$stmtProgram->fetchColumn();
            if ($programId <= 0) {
                $stmtInsertProgram = $pdo->prepare('INSERT INTO education_programs (major_id, year) VALUES (?, ?)');
                $stmtInsertProgram->execute([$majorId, $year]);
                $programId = (int)$pdo->lastInsertId();

                $stmtObj = $pdo->prepare('INSERT INTO major_objectives (education_program_id, sort_order) VALUES (?, ?)');
                for ($i = 1; $i <= 3; $i++) {
                    $stmtObj->execute([$programId, $i]);
                }
            }

            $stmt = $pdo->prepare('
                INSERT INTO courses (
                    major_id,
                    education_program_id,
                    block_id,
                    code,
                    name,
                    module_type,
                    credits,
                    credits_theory,
                    credits_practice,
                    total_hours,
                    theory_hours,
                    practical_hours,
                    sort_order,
                    expected_semester,
                    expected_year,
                    prerequisite_course_ids,
                    parallel_course_ids,
                    previous_course_ids,
                    faculty_id,
                    grading_scale
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $majorId,
                $programId,
                $blockId,
                $code,
                $name,
                $moduleType,
                $credits,
                $creditsTheory,
                $creditsPractice,
                $totalHours,
                $theoryHours,
                $practicalHours,
                $sortOrder,
                $expectedSemester,
                $expectedYear,
                syllabus_csv_from_ids($prerequisiteIds),
                syllabus_csv_from_ids($parallelIds),
                syllabus_csv_from_ids($previousIds),
                $facultyId,
                $gradingScale !== '' ? $gradingScale : syllabus_default_grading_scale()
            ]);

            $pdo->commit();
            header('Location: courses.php');
            exit;
        }

        if (isset($_POST['delete'])) {
            $stmt = $pdo->prepare('DELETE FROM courses WHERE id = ?');
            $stmt->execute([(int)$_POST['delete']]);
            header('Location: courses.php');
            exit;
        }
    }

    $majors = $pdo->query('SELECT id, name FROM majors ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $blocks = $pdo->query('SELECT id, major_id, name FROM knowledge_blocks ORDER BY major_id, name')->fetchAll(PDO::FETCH_ASSOC);
    $faculties = $pdo->query('SELECT id, name FROM faculties_list ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $coursesForOptions = $pdo->query('SELECT id, code, name FROM courses ORDER BY code')->fetchAll(PDO::FETCH_ASSOC);

    $courses = $pdo->query('
        SELECT
            c.*,
            ep.year AS program_year,
            m.name AS major_name,
            b.name AS block_name,
            f.name AS faculty_name
        FROM courses c
        LEFT JOIN education_programs ep ON ep.id = c.education_program_id
        LEFT JOIN majors m ON m.id = COALESCE(ep.major_id, c.major_id)
        LEFT JOIN knowledge_blocks b ON b.id = c.block_id
        LEFT JOIN faculties_list f ON f.id = c.faculty_id
        ORDER BY ep.year DESC, m.name ASC, c.sort_order ASC, c.code ASC
    ')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errorMsg = $e->getMessage();
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Quản lý học phần khung</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; padding-top: 30px; padding-bottom: 50px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .syllabus-container { background: #ffffff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 40px; }
        .main-title { font-weight: 700; color: #1a446c; text-transform: uppercase; margin-bottom: 30px; border-bottom: 3px solid #1a446c; padding-bottom: 10px; }
        .section-title { background: #1a446c; color: #ffffff; padding: 10px 15px; font-weight: 600; text-transform: uppercase; margin-top: 35px; margin-bottom: 20px; border-radius: 4px; }
        .table th { background-color: #f8f9fa; color: #333; font-weight: 600; text-align: center; vertical-align: middle; font-size: 14px; }
        .form-label { font-weight: 600; }
    </style>
</head>
<body>
<div class="container syllabus-container">
    <p>
        <a href="majors.php">Ngành</a> |
        <a href="blocks.php">Khối kiến thức</a> |
        <a href="index.php">Đề cương chi tiết học phần</a>
    </p>
    <h2 class="text-center main-title">Quản lý học phần khung</h2>

    <?php if ($errorMsg !== ''): ?>
        <div class="alert alert-danger"><?= h($errorMsg) ?></div>
    <?php endif; ?>

    <div class="section-title">Thêm học phần khung</div>
    <form method="post">
        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Năm khung</label>
                <input type="text" name="program_year" class="form-control" value="<?= h(date('Y')) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Ngành</label>
                <select name="major_id" id="majorSelect" class="form-select" required onchange="filterBlocks(this.value);">
                    <option value="">-- Chọn ngành --</option>
                    <?php foreach ($majors as $major): ?>
                        <option value="<?= h($major['id']) ?>"><?= h($major['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Khối kiến thức</label>
                <select name="block_id" id="blockSelect" class="form-select">
                    <option value="">-- Chọn khối --</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Khoa phụ trách</label>
                <select name="faculty_id" class="form-select select2-basic">
                    <option value="">-- Chọn khoa --</option>
                    <?php foreach ($faculties as $faculty): ?>
                        <option value="<?= h($faculty['id']) ?>"><?= h($faculty['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">STT</label>
                <input type="number" name="sort_order" class="form-control" value="0" min="0">
            </div>
            <div class="col-md-2">
                <label class="form-label">Mã học phần</label>
                <input type="text" name="code" class="form-control" required>
            </div>
            <div class="col-md-5">
                <label class="form-label">Tên học phần</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tính chất học phần</label>
                <select name="module_type" class="form-select">
                    <option value="">-- Chọn tính chất --</option>
                    <option value="Bắt buộc">Bắt buộc</option>
                    <option value="Điều kiện">Điều kiện</option>
                    <option value="Tự chọn">Tự chọn</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Tổng tín chỉ</label>
                <input type="number" name="credits" class="form-control" value="0" min="0">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tín chỉ LT</label>
                <input type="number" name="credits_theory" class="form-control" value="0" min="0">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tín chỉ TH</label>
                <input type="number" name="credits_practice" class="form-control" value="0" min="0">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tổng tiết</label>
                <input type="number" name="total_hours" class="form-control" value="0" min="0">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tiết LT</label>
                <input type="number" name="theory_hours" class="form-control" value="0" min="0">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tiết TH</label>
                <input type="number" name="practical_hours" class="form-control" value="0" min="0">
            </div>

            <div class="col-md-3">
                <label class="form-label">Học kỳ dự kiến</label>
                <input type="text" name="expected_semester" class="form-control" placeholder="Học kỳ I">
            </div>
            <div class="col-md-3">
                <label class="form-label">Năm học hiển thị</label>
                <input type="text" name="expected_year" class="form-control" placeholder="2026-2027">
            </div>
            <div class="col-md-6">
                <label class="form-label">Thang điểm lượng giá mặc định</label>
                <textarea name="grading_scale" class="form-control" rows="1"><?= h(syllabus_default_grading_scale()) ?></textarea>
            </div>

            <div class="col-md-4">
                <label class="form-label">Học phần tiên quyết</label>
                <select name="prerequisite_course_ids[]" class="form-select select2-multi" multiple>
                    <?php foreach ($coursesForOptions as $course): ?>
                        <option value="<?= h($course['id']) ?>"><?= h($course['code']) ?> - <?= h($course['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Học phần song hành</label>
                <select name="parallel_course_ids[]" class="form-select select2-multi" multiple>
                    <?php foreach ($coursesForOptions as $course): ?>
                        <option value="<?= h($course['id']) ?>"><?= h($course['code']) ?> - <?= h($course['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Học phần học trước</label>
                <select name="previous_course_ids[]" class="form-select select2-multi" multiple>
                    <?php foreach ($coursesForOptions as $course): ?>
                        <option value="<?= h($course['id']) ?>"><?= h($course['code']) ?> - <?= h($course['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12">
                <button class="btn btn-primary" type="submit" name="add" value="1">Thêm học phần</button>
            </div>
        </div>
    </form>

    <div class="section-title">Danh sách học phần khung</div>
    <div class="mb-3">
        <input type="text" id="searchCourses" class="form-control" placeholder="Tìm theo năm, ngành, mã, tên, khoa...">
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle" id="courseTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nam</th>
                    <th>Ngành</th>
                    <th>Khối</th>
                    <th>Mã</th>
                    <th>Tên</th>
                    <th>Tính chất</th>
                    <th>Tín chỉ</th>
                    <th>Tiết</th>
                    <th>Khoa</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody id="courseTableBody">
                <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><?= h($course['id']) ?></td>
                        <td><?= h($course['program_year'] ?: '-') ?></td>
                        <td><?= h($course['major_name'] ?: '-') ?></td>
                        <td><?= h($course['block_name'] ?: '-') ?></td>
                        <td><?= h($course['code']) ?></td>
                        <td><?= h($course['name']) ?></td>
                        <td><?= h(syllabus_module_type_label($course['module_type'] ?: '-')) ?></td>
                        <td class="text-center"><?= h($course['credits']) ?></td>
                        <td class="text-center"><?= h($course['total_hours']) ?></td>
                        <td><?= h($course['faculty_name'] ?: '-') ?></td>
                        <td class="text-center">
                            <a href="index.php?course_id=<?= h($course['id']) ?>" class="btn btn-sm btn-success">Tạo đề cương</a>
                            <form method="post" style="display:inline" onsubmit="return confirm('Xóa học phần khung này?');">
                                <button type="submit" name="delete" value="<?= h($course['id']) ?>" class="btn btn-sm btn-outline-danger">Xóa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
const allBlocks = <?= json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function filterBlocks(majorId) {
    const select = document.getElementById('blockSelect');
    select.innerHTML = '<option value="">-- Chọn khối --</option>';
    allBlocks
        .filter(block => String(block.major_id) === String(majorId))
        .forEach(block => {
            const option = document.createElement('option');
            option.value = block.id;
            option.textContent = block.name;
            select.appendChild(option);
        });
}

$(function () {
    $('.select2-basic').select2({ width: '100%' });
    $('.select2-multi').select2({ width: '100%' });
});

document.getElementById('searchCourses').addEventListener('input', function () {
    const keyword = this.value.toLowerCase().trim();
    document.querySelectorAll('#courseTableBody tr').forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(keyword) ? '' : 'none';
    });
});
</script>
</body>
</html>

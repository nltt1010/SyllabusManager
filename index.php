<?php
require 'db.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Lấy danh sách học phần nền từ bảng courses
$courses = $pdo->query('SELECT id, code, name, total_hours, theory_hours, practical_hours FROM courses ORDER BY code')->fetchAll(PDO::FETCH_ASSOC);

// 2. Lấy danh mục Cơ sở thực hành
$facilitiesList = $pdo->query('SELECT name FROM facilities ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);

// 3. Lấy danh mục Sách / Giáo trình từ bảng catalog
$booksCatalog = $pdo->query('SELECT * FROM books_catalog ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

// 4. Lấy danh mục Khoa phụ trách
$facultiesList = $pdo->query('SELECT name FROM faculties_list ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);

$lecturersList = $pdo->query('SELECT id, name FROM lecturers ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$coordinatorsData = $pdo->query('SELECT module_id, lecturer_id FROM course_coordinators')->fetchAll(PDO::FETCH_ASSOC);
$moduleCoordinatorsMap = [];
foreach ($coordinatorsData as $row) {
    // Ép kiểu ID về int để đồng bộ kiểu dữ liệu với JavaScript
    $moduleCoordinatorsMap[$row['module_id']][] = (int)$row['lecturer_id'];
}

$majors = $pdo->query('SELECT * FROM majors ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

// - Lay danh muc PLO/PI neu CSDL hien co bang danh muc, neu khong se dung danh muc mac dinh tren giao dien.
try {
    $ploRows = $pdo->query('SELECT * FROM plos ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $ploRows = [];
}

// - Lay danh muc cong cu danh gia neu CSDL hien co bang danh muc, neu khong se dung danh muc mac dinh tren giao dien.
try {
    $assessmentToolsCatalog = $pdo->query('SELECT * FROM assessment_tools ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $assessmentToolsCatalog = [];
}

// 5. Lấy danh mục Bộ môn
try {
    $departmentsList = $pdo->query('SELECT id, name FROM departments_list ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $departmentsList = [];
}

// Xử lý nếu được truyền course_id từ trang quản lý sang để auto-fill
$selectedCourse = null;
$course_id = $_GET['course_id'] ?? null;
if($course_id){
    $stmt = $pdo->prepare('SELECT c.*, m.name as major_name FROM courses c LEFT JOIN majors m ON c.major_id=m.id WHERE c.id=?');
    $stmt->execute([$course_id]);
    $selectedCourse = $stmt->fetch(PDO::FETCH_ASSOC);
}

// function h($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xây dựng Đề cương chi tiết học phần</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { background-color: #f4f6f9; padding-top: 30px; padding-bottom: 50px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .syllabus-container { background: #ffffff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 40px; }
        .main-title { font-weight: 700; color: #1a446c; text-transform: uppercase; margin-bottom: 30px; border-bottom: 3px solid #1a446c; padding-bottom: 10px; }
        .section-title { background: #1a446c; color: #ffffff; padding: 10px 15px; font-weight: 600; text-transform: uppercase; margin-top: 35px; margin-bottom: 20px; border-radius: 4px; }
        .sub-section-header { display: flex; justify-content: space-between; align-items: center; margin-top: 25px; margin-bottom: 15px; border-left: 4px solid #3498db; padding-left: 10px; }
        .sub-section-title { font-weight: 600; color: #2c3e50; margin: 0; }
        .table th { background-color: #f8f9fa; color: #333; font-weight: 600; text-align: center; vertical-align: middle; font-size: 14px; }
        .form-helper { font-size: 12px; color: #6c757d; display: block; margin-top: 4px; }

        /* Bảng 6.1 */
        #theoryTopicTable tbody tr.is-chapter { background-color: #eaf0fb; }
        #theoryTopicTable tbody tr.is-chapter td { vertical-align: middle; }
        #theoryTopicTable tbody tr.is-intro { background-color: #fff8e1; }
        #theoryTopicTable tbody tr.is-intro td { vertical-align: middle; font-style: italic; }
        #theoryTopicTable tbody tr.is-lesson td:first-child { padding-left: 24px; }
    </style>
</head>
<body>

<div class="container syllabus-container">
    <p><a href="list.php">Xem danh sách học phần</a> | <a href="courses.php">Quay về danh sách học phần CTĐT</a></p>
    <h2 class="text-center main-title">Xây dựng Đề cương chi tiết học phần</h2>

    <form action="save.php" method="POST" onsubmit="return gatherJsonData();" onkeydown="return event.key != 'Enter';" autocomplete="off">

        <div class="section-title">1. THÔNG TIN HỌC PHẦN</div>

        <div class="row g-3">

            <div class="col-md-2">
                <label for="year" class="form-label fw-bold">Chọn hoặc nhập năm:</label>
                <select name="year" id="year" class="form-select">
                    <option value="">-- Chọn năm --</option>
                    <?php for ($y = date('Y') + 5; $y >= 2000; $y--): ?>
                        <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>>
                            <?= $y ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="col-md-5">
                <label class="form-label fw-bold">Chọn ngành từ hệ thống:</label>
                <select id="majorSelect" name="major_id" class="form-select">
                    <option value="">-- Chọn ngành --</option>
                    <?php foreach($majors as $m): ?>
                        <option value="<?= $m['id'] ?>">
                            <?= htmlspecialchars($m['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-5">
                <label class="form-label fw-bold">Chọn học phần nền từ hệ thống:</label>
                <select id="courseSelect" name="course_id" class="form-select" onchange="extractCourseName();">
                    <option value="">-- Chọn học phần --</option>
                    <?php foreach($courses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($selectedCourse && $selectedCourse['id']==$c['id']) ? 'selected' : '' ?>>
                            <?= h($c['code']) ?> - <?= h($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Tên học phần:</label>
                <input type="text" id="courseName" name="name" class="form-control" required>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold">Mã học phần:</label>
                <input type="text" id="code" name="code" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Tính chất học phần:</label>
                <select name="module_type" class="form-select select2-simple">
                    <option value="">-- Không/ Trống --</option>
                    <option value="Bắt buộc">Bắt buộc</option>
                    <option value="Điều kiện">Điều kiện</option>
                    <option value="Tự chọn">Tự chọn</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold">Tổng số tín chỉ (Tổng / LT / TH):</label>
                <div class="input-group">
                    <input type="number" id="credits" name="credits" class="form-control bg-light" placeholder="Tổng số TC" readonly min="0">
                    <input type="number" id="credits_theory" name="credits_theory" class="form-control" placeholder="Lý thuyết" min="0" oninput="calculateTotalCredits();">
                    <input type="number" id="credits_practice" name="credits_practice" class="form-control" placeholder="Thực hành" min="0" oninput="calculateTotalCredits();">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Phân bộ thời gian tiết (Tổng / LT / TH):</label>
                <div class="input-group">
                    <input type="number" id="total_hours" name="total_hours" class="form-control bg-light" placeholder="Tổng tiết" readonly min="0">
                    <!-- - Cho nhap LT truc tiep; So gio tu hoc se tu dong lay LT nhan 2. -->
                    <input type="number" id="theory_hours" name="theory_hours" class="form-control" placeholder="Lý thuyết" min="0" oninput="calculateTotalHours();">
                    <input type="number" id="practical_hours" name="practical_hours" class="form-control" placeholder="Thực hành" min="0" oninput="calculateTotalHours();">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Số giờ tự học (tiết):</label>
                <!-- - So gio tu hoc tu dong bang so gio ly thuyet nhan 2, khong cho sua truc tiep. -->
                <input type="number" name="self_study_hours" class="form-control bg-light" value="0" min="0" readonly>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold">Đối tượng người học (dự kiến):</label>
                <input type="text" name="target_programs" class="form-control" placeholder="Nhập các đối tượng, cách nhau bằng dấu phẩy (,)">
                <span class="form-helper">Ví dụ: Sinh viên Y chính quy năm 1, Sinh viên Dược năm 1</span>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Học kỳ và năm dự kiến học:</label>
                <div class="input-group">
                    <input type="text" name="expected_semester" class="form-control" placeholder="Học kỳ (Ví dụ: Học kỳ I)">
                    <input type="text" name="expected_year" class="form-control" placeholder="Năm học (Ví dụ: 2026-2027)">
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold">Học phần tiên quyết:</label>
                <select name="prerequisite_modules[]" class="form-select select2-course" multiple="multiple" data-placeholder="-- Chọn học phần --">
                    <?php foreach($courses as $c): ?>
                        <option value="<?= h($c['id']) ?>" data-code="<?= h($c['code']) ?>"><?= h($c['code']) ?> - <?= h($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Học phần song hành:</label>
                <select name="parallel_modules[]" class="form-select select2-course" multiple="multiple" data-placeholder="-- Chọn học phần --">
                    <?php foreach($courses as $c): ?>
                        <option value="<?= h($c['id']) ?>" data-code="<?= h($c['code']) ?>"><?= h($c['code']) ?> - <?= h($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Học phần học trước:</label>
                <select name="previous_modules[]" class="form-select select2-course" multiple="multiple" data-placeholder="-- Chọn học phần --">
                    <?php foreach($courses as $c): ?>
                        <option value="<?= h($c['id']) ?>" data-code="<?= h($c['code']) ?>"><?= h($c['code']) ?> - <?= h($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold">Bộ môn tham gia giảng dạy:</label>
                <select name="department_in_charge[]" class="form-select select2-multiple" multiple="multiple" data-placeholder="-- Chọn Bộ môn giảng dạy --">
                    <?php foreach($departmentsList as $dep): ?>
                        <option value="<?= h($dep['id']) ?>"><?= h($dep['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Ban điều phối học phần:</label>
                <select name="coordinating_board[]" class="form-select select2-multiple" multiple="multiple" data-placeholder="-- Chọn ban điều phối --">
                    <?php foreach($lecturersList as $lecturer): ?>
                        <?php 
                            // Kiểm tra xem giảng viên này trước đó có được gán vào môn này không
                            $selected = (isset($selectedCoordinators) && in_array($lecturer['id'], $selectedCoordinators)) ? 'selected' : ''; 
                            
                        ?>
                        <option value="<?= h($lecturer['id']) ?>" <?= $selected ?>><?= h($lecturer['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Khoa phụ trách:</label>
                <select name="faculty_in_charge" class="form-select select2-enable" multiple="multiple" data-placeholder="-- Chọn Khoa phụ trách --">
                    <!-- <option value="">-- Chọn Khoa phụ trách --</option> -->
                    <?php foreach($facultiesList as $fac): ?>
                        <option value="<?= h($fac) ?>"><?= h($fac) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="section-title">2. MÔ TẢ HỌC PHẦN</div>
        <div class="mb-3">
            <textarea name="description" class="form-control" rows="4" placeholder="Nhập tóm tắt mô tả nội dung cốt lõi của học phần..."></textarea>
        </div>

        <div class="section-title">3. MỤC TIÊU VÀ CHUẨN ĐẦU RA HỌC PHẦN</div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label fw-bold">Mục tiêu chung</label>
                <textarea name="objective_general" class="form-control" rows="5" placeholder="Nhập mục tiêu chung của học phần..."></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Mục tiêu cụ thể (PO)</label>
                <textarea name="objective_specific" class="form-control" rows="5" placeholder="Nhập mục tiêu cụ thể (PO)..."></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Chuẩn đầu ra chương trình đào tạo (PLO)</label>
                <textarea name="objective_plo" class="form-control" rows="5" placeholder="Nhập chuẩn đầu ra chương trình đào tạo (PLO)..."></textarea>
            </div>
        </div>


        <div class="section-title">4. CHUẨN ĐẦU RA HỌC PHẦN (BLOOM)</div>
        <div class="sub-section-header">
            <div class="sub-section-title">Chuẩn đầu ra học phần (Bloom)</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addCloRow();">+ Thêm dòng CLO</button>
        </div>
        <table class="table table-bordered align-middle" id="cloTable">
            <thead>
                <tr>
                    <th style="width: 10%;">TT</th>
                    <th style="width: 25%;">Lĩnh vực</th>
                    <th style="width: 25%;">Mức độ Bloom Taxonomy</th>
                    <th style="width: 32%;">Chuẩn đầu ra học phần (Nội dung)</th>
                    <th style="width: 8%;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                </tbody>
        </table>
        <input type="hidden" id="clos_json" name="clos_json">

        <div class="section-title">5. PHƯƠNG PHÁP KIỂM TRA, LƯỢNG GIÁ HỌC PHẦN</div>

        <div class="sub-section-header">
            <div class="sub-section-title">5.1. Thang điểm lượng giá</div>
        </div>
        <div class="mb-3">
            <textarea name="grading_scale" class="form-control" rows="2" placeholder="Nhập thông tin quy định thang điểm lý thuyết / thực hành (Dạng chữ hoặc số)..."></textarea>
        </div>

        <div class="sub-section-header">
            <div class="sub-section-title">5.2. Phương pháp kiểm tra lượng giá</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addAssessmentRow();">+ Thêm thành phần lượng giá</button>
        </div>
        <table class="table table-bordered align-middle" id="assessmentTable">
            <thead>
                <tr>
                    <th style="width: 13%;">CLOs</th>
                    <th style="width: 13%;">PLO</th>
                    <th style="width: 13%;">PI</th>
                    <th style="width: 14%;">Mức độ đóng góp</th> 
                    <th style="width: 16%;">Hình thức đánh giá</th>
                    <th style="width: 18%;">Công cụ đánh giá</th>
                    <th style="width: 10%;">Trọng số (%)</th>
                    <th style="width: 5%;">Hành động</th>
                </tr>
            </thead>
            <tbody>
                </tbody>
        </table>
        <input type="hidden" id="assessments_json" name="assessments_json">

        <div class="sub-section-header">
            <div class="sub-section-title">5.3. Phương pháp lượng giá hoạt động tự học</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addSelfStudyRow();">+ Thêm hoạt động tự học</button>
        </div>
        <table class="table table-bordered align-middle" id="selfStudyTable">
            <thead>
                <tr>
                    <th>Hoạt động tự học</th>
                    <th style="width: 15%;">Chuẩn đầu ra liên quan(CLOs)</th>
                    <th style="width: 12%; display:none;">Thời lượng (giờ)</th>
                    <th>Phương pháp tự học</th>
                    <th>Cách thức đánh giá</th>
                    <th>Minh chứng</th>
                    <th style="width: 8%;">Hành động</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td colspan="4" class="text-end">Tổng thời lượng tự học:</td>
                    <td colspan="2">
                        <input type="text" id="self_study_total_display" class="form-control fw-bold" readonly placeholder="0">
                    </td>
                </tr>
            </tfoot>
        </table>
        <input type="hidden" id="self_study_json" name="self_study_json">


        <div class="section-title">6. NỘI DUNG HỌC PHẦN VÀ PHƯƠNG PHÁP DẠY-HỌC</div>

        <div class="sub-section-header">
            <div class="sub-section-title">6.1. Lý thuyết</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addTheoryRow();">+ Thêm bài giảng lý thuyết</button>
        </div>
        <table class="table table-bordered align-middle" id="theoryTopicTable">
            <thead>
                <tr>
                    <th style="width: 10%;">Chương/Bài</th>
                    <th style="width: 20%;">Bài giảng/ Nội dung lý thuyết</th>
                    <th style="width: 10%;">Hình thức giảng dạy</th>
                    <th style="width: 7%;">Số tiết trên lớp</th>
                    <th style="width: 7%;">Số tiết tự học</th>
                    <th style="width: 7%;">Số tiết trực tuyến</th>
                    <th style="width: 12%;">Phương pháp dạy học</th>
                    <th style="width: 14%;">Chuẩn đầu ra liên quan (CLOs)</th>
                    <th style="width: 5%;">Xóa</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <input type="hidden" id="theory_json" name="theory_json">

        <div class="sub-section-header">
            <div class="sub-section-title">6.2. Thực hành</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addPracticalRow();">+ Thêm nội dung thực hành</button>
        </div>
        <table class="table table-bordered align-middle" id="practicalTopicTable">
            <thead>
                <tr>
                    <th style="width: 12%;">Chủ đề</th>
                    <th>Nội dung thực hành/ Kỹ năng</th>
                    <th style="width: 10%;">Hình thức dạy học</th>
                    <th style="width: 7%;">Số tiết TH</th>
                    <th style="width: 7%;">Số tiết trực tuyến</th>
                    <th style="width: 10%;">Phương pháp dạy học</th>
                    <th style="width: 10%;">CLOs</th>
                    <th style="width: 12%;">Cơ sở thực hành</th>
                    <th style="width: 5%;">Xóa</th>
                </tr>
            </thead>
            <tbody>
                </tbody>
        </table>
        <input type="hidden" id="practical_json" name="practical_json">

        <div class="sub-section-header">
            <div class="sub-section-title">6.3. Lý thuyết và thực hành (chung)</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addCombinedRow();">+ Thêm chủ đề tích hợp chung</button>
        </div>
        <table class="table table-bordered align-middle" id="combinedTopicTable">
            <thead>
                <tr>
                    <th style="width: 5%;">STT</th>
                    <th>Nội dung chính</th>
                    <th style="width: 10%;">Hình thức dạy học</th>
                    <th style="width: 7%;">Số tiết LT</th>
                    <th style="width: 7%;">Số tiết TH</th>
                    <th style="width: 7%;">Số tiết trực tuyến</th>
                    <th style="width: 7%;">Tự học</th>
                    <th style="width: 10%;">Phương pháp dạy học</th>
                    <th style="width: 10%;">CLOs</th>
                    <th style="width: 12%;">Cơ sở thực hành</th>
                    <th style="width: 5%;">Xóa</th>
                </tr>
            </thead>
            <tbody>
                </tbody>
        </table>
        <input type="hidden" id="combined_json" name="combined_json">


        <div class="section-title">7. TÀI LIỆU DẠY HỌC</div>

        <div class="sub-section-header">
            <div class="sub-section-title">7.1. Tài liệu giảng dạy</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addResourceRow('resourceTeachTable');">+ Thêm tài liệu giảng dạy</button>
        </div>
        <table class="table table-bordered align-middle" id="resourceTeachTable">
            <thead>
                <tr>
                    <th style="width: 6%;">STT</th>
                    <th style="width: 25%;">Tên giáo trình (Chọn từ thư viện)</th>
                    <th>Chủ biên</th>
                    <th>Nhà xuất bản</th>
                    <th style="width: 10%;">Năm xuất bản</th>
                    <th>Số định danh cá biệt tại thư viện</th>
                    <th style="width: 6%;">Xóa</th>
                </tr>
            </thead>
            <tbody>
                </tbody>
        </table>
        <input type="hidden" id="res_teach_json" name="res_teach_json">

        <div class="sub-section-header">
            <div class="sub-section-title">7.2. Tài liệu tự học</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addResourceRow('resourceSelfTable');">+ Thêm tài liệu tự học</button>
        </div>
        <table class="table table-bordered align-middle" id="resourceSelfTable">
            <thead>
                <tr>
                    <th style="width: 6%;">STT</th>
                    <th style="width: 25%;">Tên giáo trình (Chọn từ thư viện)</th>
                    <th>Chủ biên</th>
                    <th>Nhà xuất bản</th>
                    <th style="width: 10%;">Năm xuất bản</th>
                    <th>Số định danh cá biệt tại thư viện</th>
                    <th style="width: 6%;">Xóa</th>
                </tr>
            </thead>
            <tbody>
                </tbody>
        </table>
        <input type="hidden" id="res_self_json" name="res_self_json">


        <div class="text-center mt-5">
            <button type="submit" class="btn btn-lg btn-success px-5 py-3 fw-bold">Lưu Toàn Bộ Đề Cương Chi Tiết</button>
        </div>

    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
const dbFacilities = <?php echo json_encode($facilitiesList); ?>;
const dbBooks = <?php echo json_encode($booksCatalog); ?>;
const dbCoursesList = <?php echo json_encode($courses); ?>;
const dbMajors = <?php echo json_encode($majors); ?>;
const dbPloRows = <?php echo json_encode($ploRows); ?>;
const dbAssessmentToolsCatalog = <?php echo json_encode($assessmentToolsCatalog); ?>;
const verbsDictionary = {
    "Kiến thức": {
        "1. Nhớ": "Liệt kê, kể tên, định nghĩa, mô tả, nêu, chỉ ra, nhận biết, trình bày, phân loại",
        "2. Hiểu": "Giải thích, tóm tắt, so sánh, phân biệt, minh họa, trình bày lại bằng lời, mô tả ý nghĩa, phân tích sơ bộ",
        "3. Vận dụng": "Thực hiện, áp dụng, sử dụng, giải quyết, minh họa bằng ví dụ, tiến hành, áp dụng quy trình, xử trí",
        "4. Phân tích": "Phân tích, phân loại, đối chiếu, so sánh, chứng minh, suy luận, chỉ ra nguyên nhân – kết quả, lập luận, giải thích mối liên hệ",
        "5. Đánh giá": "Đánh giá, bình luận, phê bình, so sánh ưu/nhược điểm, đưa ra kết luận, lựa chọn, phản biện, bảo vệ quan điểm, đề xuất, quyết định",
        "6. Sáng tạo": "Thiết kế, xây dựng, phát triển, đề xuất giải pháp, sáng tạo, lập kế hoạch, vận dụng, mô hình hóa, soạn thảo, phát minh, tổng hợp ý tưởng"
    },
    "Kỹ năng": {
        "1. Bắt chước": "Quan sát, tuân thủ, làm theo, sao chép, bắt chước, nhắc lại, lặp lại, tái tạo, mô phỏng, lựa chọn, nhận thấy",
        "2. Làm được": "Làm, thực hiện, thi hành, tái hiện lại, trình diễn",
        "3. Làm chính xác": "kiểm tra, làm, thực hiện, thực hiện đầy đủ, hoàn thiện, điều khiển, kiểm soát, trình diễn, sử dụng/làm thành thạo, chỉ rõ, phân biệt, xây dựng, tích hợp, phán đoán, lựa chọn",
        "4. Thành thạo": "thích ứng, thực hiện thành thạo, phối hợp, thiết lập, xây dựng, sắp xếp, sáng tạo, Giải quyết, chẩn đoán, điều trị, thích nghi, kết hợp, phối hợp, tích hợp, hình thành, phát triển, làm chủ, điều chỉnh, sửa đổi, thích nghi",
        "5. Thành bản năng": "thiết kế, phát triển, phát minh, hỗ trợ, sửa chữa, trình diễn, hướng dẫn, quản lý, xác định"
    },
    "Thái độ": {
        "1. Tiếp nhận": "Công nhận, nhận biết, chấp nhận, ý thức được, hỏi, để ý, mô tả, quan sát, tuân thủ, nhận định, lắng nghe, nhìn nhận",
        "2. Đáp ứng": "Hành xử, phản ứng, tuân theo, tuân thủ, làm cho đúng, phối hợp, xem xét, dò xét, lựa chọn, đóng góp, tình nguyện",
        "3. Nội tâm hóa": "Thích ứng, cân bằng, phản kháng, phê bình, đối chiếu, so sánh, phân biệt, bảo vệ, biện hộ, thuyết phục, tìm kiếm, thừa nhận, tán thành, đề nghị",
        "4. Tổ chức": "Thay đổi, điều chỉnh, tổ chức, so sánh, đánh giá, phát triển, tích hợp, sắp xếp, hình thành, thiết lập, kết nối, trung thành, gắn kết",
        "5. Hình thành phẩm chất": "Thực hiện, biểu lộ, biện hộ, ảnh hưởng, đề xuất, đại diện, xác nhận giá trị, biện giải, thôi thúc, duy trì, gìn giữ, kiên nhẫn, ủng hộ, cống hiến, điều chỉnh, duy trì, thể hiện, thực hành, cam kết"
    }
};

function h(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function normalizeListField(value) {
    if (Array.isArray(value)) return value.filter(Boolean);
    if (value && typeof value === 'object') return Object.values(value).filter(Boolean);
    return String(value || '')
        .split(/[,;\n|]+/)
        .map(item => item.trim())
        .filter(Boolean);
}

function getPloPiCatalog() {
    const majorId = document.getElementById('majorSelect')?.value || '';
    const fromRows = dbPloRows
        .filter(row => !majorId || String(row.major_id || row.majorId || '') === String(majorId) || !row.major_id)
        .map(row => ({
            plo: row.code || row.name || row.plo || row.title || '',
            pi: normalizeListField(row.pi || row.pis || row.indicators || row.pi_list || row.description)
        }))
        .filter(item => item.plo);

    if (fromRows.length > 0) return fromRows;

    const selectedMajor = dbMajors.find(m => String(m.id) === String(majorId));
    if (selectedMajor) {
        const parsed = normalizeListField(selectedMajor.plo || selectedMajor.plos || selectedMajor.objective_plo);
        if (parsed.length > 0) {
            return parsed.map((plo, index) => ({
                plo,
                pi: [`PI${index + 1}.1`, `PI${index + 1}.2`, `PI${index + 1}.3`]
            }));
        }
    }

    return defaultPloPiCatalog;
}

function buildPloOptions(selected = '') {
    return '<option value="">-- Chọn PLO --</option>' + getPloPiCatalog()
        .map(item => `<option value="${h(item.plo)}" ${item.plo === selected ? 'selected' : ''}>${h(item.plo)}</option>`)
        .join('');
}

function buildPiOptions(plo, selected = '') {
    const selectedList = Array.isArray(selected) ? selected : normalizeListField(selected);
    const catalog = getPloPiCatalog();
    const found = catalog.find(item => item.plo === plo) || catalog[0] || { pi: [] };
    return '<option value="">-- Chọn PI --</option>' + normalizeListField(found.pi)
        .map(pi => `<option value="${h(pi)}" ${selectedList.includes(pi) ? 'selected' : ''}>${h(pi)}</option>`)
        .join('');
}

function buildCloOptions(selectedValues = []) {
    const selected = Array.isArray(selectedValues) ? selectedValues : normalizeListField(selectedValues);
    const codes = [];
    document.querySelectorAll('#cloTable tbody tr').forEach((tr, idx) => {
        const code = tr.querySelector('.c-code')?.value.trim() || `CLO${idx + 1}`;
        normalizeCloCodes(code, idx + 1).forEach(item => {
            if (!codes.includes(item)) codes.push(item);
        });
    });
    return codes.map(code => `<option value="${h(code)}" ${selected.includes(code) ? 'selected' : ''}>${h(code)}</option>`).join('');
}

function initSelect2Within(root) {
    $(root).find('.select2-simple').select2({ width: '100%' });
    $(root).find('.select2-multiple').select2({ width: '100%' });
}

// Phần này tạm nữa có db thay vào -------------------------------------------------------------------------------------------
const assessmentMethods = ["Chuyên cần", "Kiểm tra thường xuyên", "Thi kết thúc"];

const defaultPloPiCatalog = [
    { plo: 'PLO1', pi: ['PI1.1', 'PI1.2', 'PI1.3'] },
    { plo: 'PLO2', pi: ['PI2.1', 'PI2.2', 'PI2.3'] },
    { plo: 'PLO3', pi: ['PI3.1', 'PI3.2', 'PI3.3'] }
];
const assessmentToolsByMethod = {
    "Chuyên cần": ["Điểm danh", "Hỏi đáp", "Quan sát thái độ học tập"],
    "Kiểm tra thường xuyên": ["Bài kiểm tra ngắn", "Bài tập cá nhân", "Bài tập nhóm", "Rubric", "Logbook", "OSCE/OSPE"],
    "Thi kết thúc": ["Thi viết", "Thi trắc nghiệm", "Thi vấn đáp", "Ngân hàng câu hỏi", "Rubric"]
};
//-----------------------------------------------------------------------------------------------------------------------------
const bloomDictionary = {
    "Kiến thức": [
        "1. Nhớ",
        "2. Hiểu",
        "3. Vận dụng",
        "4. Phân tích",
        "5. Đánh giá",
        "6. Sáng tạo"
    ],
    "Kỹ năng": [
        "1. Bắt chước",
        "2. Làm được",
        "3. Làm chính xác",
        "4. Thành thạo",
        "5. Thành bản năng"
    ],
    "Thái độ": [
        "1. Tiếp nhận",
        "2. Đáp ứng",
        "3. Nội tâm hóa",
        "4. Tổ chức",
        "5. Hình thành phẩm chất"
    ]
};

function extractCourseName() {
    const courseId = document.getElementById('courseSelect').value;
    if(!courseId) {
        document.getElementById('courseName').value = '';
        document.getElementById('code').value = '';
        document.getElementById('total_hours').value = '';
        document.getElementById('theory_hours').value = '';
        document.getElementById('practical_hours').value = '';
        document.getElementById('credits').value = '';
        document.getElementById('credits_theory').value = '';
        document.getElementById('credits_practice').value = '';
        return;
    }
    const target = dbCoursesList.find(x => x.id == courseId);
    if(target) {
        document.getElementById('courseName').value = target.name;
        document.getElementById('code').value = target.code;
        document.getElementById('theory_hours').value = target.theory_hours || 0;
        document.getElementById('practical_hours').value = target.practical_hours;

        calculateTotalHours();

        document.getElementById('credits_theory').value = Math.round((target.theory_hours || 0) / 15) || 0;
        document.getElementById('credits_practice').value = Math.round(target.practical_hours / 30) || 0;
        calculateTotalCredits();
    }
}

function calculateTotalCredits() {
    const lt = parseFloat(document.getElementById('credits_theory').value) || 0;
    const th = parseFloat(document.getElementById('credits_practice').value) || 0;
    document.getElementById('credits').value = lt + th;
}

function calculateTotalHours() {
    const lt = parseInt(document.getElementById('theory_hours').value) || 0;
    const th = parseInt(document.getElementById('practical_hours').value) || 0;
    document.getElementById('total_hours').value = lt + th;
    syncSelfStudyTotal();
}

function syncSelfStudyTotal() {
    const selfHours = document.querySelector('input[name="self_study_hours"]');
    const totalDisplay = document.getElementById('self_study_total_display');
    const theoryHours = parseInt(document.getElementById('theory_hours')?.value) || 0;
    if (selfHours && totalDisplay) {
        selfHours.value = theoryHours * 2;
        totalDisplay.value = parseInt(selfHours.value) || 0;
    }
}

function syncTheoryHoursFromTable() {
    document.querySelectorAll('#theoryTopicTable tbody tr').forEach(tr => {
        if (tr.classList.contains('is-intro')) return;
        const classInput = tr.querySelector('.t-class');
        if (!classInput || classInput.disabled) return;
        const hasClassValue = String(classInput.value).trim() !== '';
        const classHours = hasClassValue ? (parseFloat(classInput.value) || 0) : 0;
        const selfInput = tr.querySelector('.t-self');
        if (selfInput && !selfInput.disabled) selfInput.value = hasClassValue ? classHours * 2 : '';
    });
    calculateTotalHours();
}

let cloIndex = 0;
let assessmentRowIndex = 0;

function updateVerbsHint(selectEl) {
    const tr = selectEl.closest('tr');
    const textarea = tr.querySelector('.c-desc');
    const hintEl = tr.querySelector('.verbs-hint');
    
    let combinedHints = [];
    let placeholderHints = [];
    
    tr.querySelectorAll('.sel-bloom-item').forEach(sel => {
        if (!sel.disabled && sel.value) {
            const domain = sel.getAttribute('data-domain');
            const bloomLevel = sel.value;
            
            // Lấy từ gợi ý từ Dictionary
            if (verbsDictionary[domain] && verbsDictionary[domain][bloomLevel]) {
                // Tách lấy chữ "Nhớ", "Hiểu" từ chuỗi "1. Nhớ", "2. Hiểu"
                const levelName = bloomLevel.includes('. ') ? bloomLevel.split('. ')[1] : bloomLevel;
                const words = verbsDictionary[domain][bloomLevel];
                
                // Chuỗi hiển thị HTML có xuống dòng bằng <br>
                combinedHints.push(`<strong>[${levelName}]:</strong> ${words}`);
                // Chuỗi hiển thị cho placeholder (dùng dấu phẩy phân cách vì placeholder không nhận xuống dòng)
                placeholderHints.push(words);
            }
        }
    });
    
    // Cập nhật lên giao diện
    if (combinedHints.length > 0) {
        // Hiển thị chữ gợi ý dưới ô nhập liệu, mỗi nhóm nằm trên 1 dòng nhờ dấu <br>
        hintEl.innerHTML = combinedHints.join('<br>');
        
        // Cập nhật thuộc tính placeholder của textarea
        textarea.setAttribute('placeholder', 'Gợi ý từ: ' + placeholderHints.join(', '));
    } else {
        hintEl.innerHTML = "";
        textarea.setAttribute('placeholder', 'Nhập mô tả...');
    }
}

function addCloRow() {
    cloIndex++;
    const tbody = document.querySelector('#cloTable tbody');
    const tr = document.createElement('tr');
    const rowId = cloIndex;
    const uid = 'clo_' + cloIndex + '_' + Date.now();

    const domainKeys = ['Kiến thức', 'Kỹ năng', 'Thái độ'];
    let domainHtml = '';
    let bloomHtml = '';

    domainKeys.forEach((domain, idx) => {
        const cbId = uid + '_d' + idx;
        domainHtml += `
            <div class="form-check mb-1">
                <!-- - CLO linh vuc chi duoc chon mot nen dung radio thay cho checkbox. -->
                <input class="form-check-input chk-domain" type="radio" value="${domain}" id="${cbId}"
                       name="clo_domain_${rowId}"
                       onchange="toggleBloomSelect(this, '${domain}', '${uid}', ${idx})">
                <label class="form-check-label" for="${cbId}">${domain}</label>
            </div>`;
        bloomHtml += `
            <div class="mb-1">
                <select class="form-select form-select-sm sel-bloom-item" id="${uid}_b${idx}" disabled
                        name="clo_bloom_${rowId}[]" data-domain="${domain}"
                        onchange="updateVerbsHint(this)"
                        style="opacity: 0.45; font-size: 13px;">
                    <option value="">-- Chọn lĩnh vực trước --</option>
                </select>
            </div>`;
    });

    tr.innerHTML = `
        <td>
            <input type="hidden" name="clo_row_ids[]" value="${rowId}">
            <!-- - Doi ma CLO se cap nhat cac select CLO o bang khac. -->
            <input type="text" class="form-control c-code text-center fw-bold" name="clo_code[]" value="" placeholder="CLO1 hoặc CLO1, CLO2" oninput="syncCloToTables();">
        </td>
        <td>${domainHtml}</td>
        <td>${bloomHtml}</td>
        <td>
            <textarea class="form-control c-desc" name="clo_description[]" rows="3" placeholder="Nhập mô tả..."></textarea>
            <small class="form-helper verbs-hint d-block mt-1 text-primary fw-semibold" style="min-height: 18px;"></small>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-danger" onclick="removeCloRow(this)">Xóa</button>
        </td>
    `;
    tbody.appendChild(tr);
    
    reindexCloTable();
    syncCloToTables();
}

function removeCloRow(btn) {
    btn.closest('tr').remove();
    reindexCloTable();
    syncCloToTables();
}

function reindexCloTable() {
    let currentIdx = 0;
    document.querySelectorAll('#cloTable tbody tr').forEach((tr) => {
        currentIdx++;
        const inputCode = tr.querySelector('.c-code');
        if(inputCode && inputCode.value.trim() === "") {
            inputCode.value = `CLO${currentIdx}`;
        }
    });
}

function normalizeCloCodes(value, fallbackIndex) {
    const raw = String(value || '').trim();
    const source = raw === '' ? `CLO${fallbackIndex}` : raw;
    const matches = source.toUpperCase().match(/CLO\s*\d+/g) || [];

    if (matches.length > 0) {
        return [...new Set(matches.map(code => code.replace(/\s+/g, '')))];
    }

    return source
        .split(/[\s,;+/|]+/)
        .map(code => code.trim().toUpperCase())
        .filter(Boolean)
        .filter((code, index, arr) => arr.indexOf(code) === index);
}

function toggleBloomSelect(checkbox, domain, uid, idx) {
    // - Khi chon mot linh vuc CLO, tat cac muc Bloom cua linh vuc khac trong cung dong.
    const tr = checkbox.closest('tr');
    tr.querySelectorAll('.sel-bloom-item').forEach(otherSel => {
        if (otherSel.id !== uid + '_b' + idx) {
            otherSel.innerHTML = '<option value="">-- Chọn lĩnh vực trước --</option>';
            otherSel.value = '';
            otherSel.disabled = true;
            otherSel.style.opacity = '0.45';
        }
    });
    const sel = document.getElementById(uid + '_b' + idx);
    if (checkbox.checked) {
        let opts = '<option value="">-- Chọn mức độ --</option>';
        if (bloomDictionary[domain]) {
            bloomDictionary[domain].forEach(item => {
                opts += `<option value="${item}">${item}</option>`;
            });
        }
        sel.innerHTML = opts;
        sel.disabled = false;
        sel.style.opacity = '1';
    } else {
        sel.innerHTML = '<option value="">-- Chọn lĩnh vực trước --</option>';
        sel.value = '';
        sel.disabled = true;
        sel.style.opacity = '0.45';
        // Khi bỏ chọn checkbox Lĩnh vực, cập nhật lại gợi ý từ ngữ
        updateVerbsHint(sel);
    }
}

function addAssessmentRow() {
    const tbody = document.querySelector('#assessmentTable tbody');
    const tr = document.createElement('tr');
    assessmentRowIndex++;
    const rowId = assessmentRowIndex;

    let methodOptions = assessmentMethods.map(m => `<option value="${m}">${m}</option>`).join('');

    tr.innerHTML = `
        <td>
            <input type="hidden" name="assessment_row_ids[]" value="${rowId}">
            <input type="text" class="form-control a-clos" name="assessment_clos[]">
        </td>
        <!-- - PLO chon tu danh muc PLO cua nganh, PI doi theo PLO. -->
        <td><select class="form-select a-plo" name="assessment_plo[]" onchange="onAssessmentPloChange(this)">${buildPloOptions()}</select></td>
        <!-- - PI cho chon nhieu va danh sach PI duoc nap theo PLO dang chon. -->
        <td><select class="form-select a-pi select2-multiple" name="assessment_pi_${rowId}[]" multiple="multiple">${buildPiOptions('')}</select></td>
        <td>  
            <select class="form-select a-contribution" name="assessment_contribution[]">
                <option value="">-- Chọn mức độ -- </option>
                <option value="I">I – Giới thiệu</option>
                <option value="R">R – Củng cố</option>
                <option value="M">M – Thành thạo</option>
                <option value="A">A – Đánh giá</option>
            </select>
        </td>
        <td>
            <!-- - Hinh thuc danh gia chi duoc chon mot. -->
            <select class="form-select a-form" name="assessment_form_${rowId}" onchange="onAssessmentFormChange(this)">
                <option value="">-- Chọn hình thức --</option>
                ${methodOptions}
            </select>
        </td>
        <td><select class="form-select a-tool select2-multiple" name="assessment_tool_${rowId}[]" multiple="multiple"></select></td>
        <td><input type="number" class="form-control a-weight" name="assessment_weight[]" value="0" min="0" max="100" oninput="onWeightInput(this)"></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();">Xóa</button></td>
    `;
    tbody.appendChild(tr);
    $(tr.querySelector('.a-pi')).select2({ width: '100%', placeholder: "Chọn PI" });
    $(tr.querySelector('.a-tool')).select2({ width: '100%', placeholder: "Chọn công cụ đánh giá" });
    onAssessmentFormChange(tr.querySelector('.a-form'));
}

// - Cap nhat PI theo PLO va cong cu danh gia theo hinh thuc danh gia.
function onAssessmentPloChange(selectEl) {
    const tr = selectEl.closest('tr');
    const piSelect = tr.querySelector('.a-pi');
    // - Doi PLO thi xoa PI cu va nap lai danh muc PI tuong ung voi PLO moi.
    $(piSelect).empty().append(buildPiOptions(selectEl.value)).val(null).trigger('change');
}

function refreshAssessmentPloPiOptions() {
    document.querySelectorAll('#assessmentTable tbody tr').forEach(tr => {
        const ploSelect = tr.querySelector('.a-plo');
        const piSelect = tr.querySelector('.a-pi');
        if (!ploSelect || !piSelect) return;
        const oldPlo = ploSelect.value;
        const oldPi = $(piSelect).val() || [];
        ploSelect.innerHTML = buildPloOptions(oldPlo);
        $(piSelect).empty().append(buildPiOptions(ploSelect.value, oldPi)).trigger('change');
    });
}

function getAssessmentToolsForMethod(method) {
    const fromCatalog = dbAssessmentToolsCatalog
        .filter(row => !method || [row.form, row.method, row.assessment_form, row.type].filter(Boolean).includes(method))
        .map(row => row.name || row.title || row.tool || row.label)
        .filter(Boolean);
    const base = fromCatalog.length > 0 ? fromCatalog : (assessmentToolsByMethod[method] || []);
    return [...new Set(base)];
}

function onAssessmentFormChange(selectEl) {
    const tr = selectEl.closest('tr');
    const toolSelect = tr.querySelector('.a-tool');
    const selected = $(toolSelect).val() || [];
    const options = getAssessmentToolsForMethod(selectEl.value)
        .map(tool => `<option value="${h(tool)}" ${selected.includes(tool) ? 'selected' : ''}>${h(tool)}</option>`)
        .join('');
    $(toolSelect).empty().append(options).trigger('change');
    onWeightInput(tr.querySelector('.a-weight'));
}

function syncCoordinatingBoard() {
    const selectedLecturerIds = $('#coordinatingBoardSelect').val() || [];
    return selectedLecturerIds;
}

function calcTotalWeight() {
    let total = 0;
    document.querySelectorAll('#assessmentTable tbody .a-weight').forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    return total;
}

function onWeightInput(input) {
    const total = calcTotalWeight();
    if (total > 100) {
        input.value = Math.max(0, parseFloat(input.value) - (total - 100));
        alert('Tổng trọng số của tất cả CLOs là 100%');
    }
}

function addSelfStudyRow() {
    const tbody = document.querySelector('#selfStudyTable tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" class="form-control ss-activity" name="self_study_name[]" placeholder="Tự nhập hoạt động"></td>
        <td><input type="text" class="form-control ss-clos" name="self_study_clos[]" placeholder="Tự nhập CLOs"></td>
        <td style="display:none;"><input type="number" class="form-control ss-duration" name="self_study_duration[]" value="0" min="0"></td>
        <td><input type="text" class="form-control ss-method" name="self_study_method[]" placeholder="Phương pháp tự học"></td>
        <td><input type="text" class="form-control ss-assess" name="self_study_assess[]" placeholder="Cách thức đánh giá"></td>
        <td><input type="text" class="form-control ss-evidence" name="self_study_evidence[]" placeholder="Minh chứng"></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();">Xóa</button></td>
    `;
    tbody.appendChild(tr);
}

function removeTheoryRow(btn) {
    btn.closest('tr').remove();
    reindexTheoryChaptersAndLessons();
    syncTheoryHoursFromTable();
}

function addTheoryRow(type = 'Bài') {
    const tbody = document.querySelector('#theoryTopicTable tbody');
    const tr = document.createElement('tr');

    const isChapter = type === 'Chương';

    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm t-type" onchange="onTheoryTypeChange(this)">
                <option value="Chương" ${isChapter ? 'selected' : ''}>Chương</option>
                <option value="Bài"    ${!isChapter ? 'selected' : ''}>Bài</option>
            </select>
            <input type="text" class="form-control t-chapter-label text-center fw-bold mt-1 bg-light" name="theory_chapter[]" readonly>
        </td>
        <td>
            <textarea class="form-control t-title" name="theory_title[]" rows="2" placeholder="${isChapter ? 'Tên chương...' : 'Nội dung bài giảng lý thuyết...'}"></textarea>
        </td>
        <!-- - Cac cot tuy chon mac dinh rong de PDF co the bo cot khong co du lieu. -->
        <td class="t-extra"><input type="text" class="form-control t-method" name="theory_method[]" value="" ${isChapter ? 'disabled' : ''}></td>
        <!-- - Cot so tiet tren lop co the de trong; khi nhap se cap nhat tong ly thuyet va tu hoc. -->
        <td class="t-extra"><input type="number" class="form-control t-class" name="theory_class_hours[]" value="" min="0" oninput="syncTheoryHoursFromTable()" ${isChapter ? 'disabled' : ''}></td>
        <td class="t-extra"><input type="number" class="form-control t-self bg-light" name="theory_self_hours[]" value="" min="0" readonly ${isChapter ? 'disabled' : ''}></td>
        <td class="t-extra"><input type="number" class="form-control t-online" name="theory_online_hours[]" value="" min="0" ${isChapter ? 'disabled' : ''}></td>
        <td class="t-extra"><input type="text" class="form-control t-pedagogy" name="theory_pedagogy[]" placeholder="Tự nhập phương pháp..." ${isChapter ? 'disabled' : ''}></td>
        <!-- - CLO bang 6.1 chon nhieu CLO lay tu muc 4. -->
        <td class="t-extra text-center"><select class="form-select t-clos select2-multiple" name="theory_clos_${Date.now()}[]" multiple="multiple" ${isChapter ? 'disabled' : ''}>${buildCloOptions()}</select></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="removeTheoryRow(this)">Xóa</button></td>
    `;
    tbody.appendChild(tr);

    $(tr.querySelectorAll('.select2-multiple')).select2({ width: '100%', placeholder: "Chọn CLO" });

    applyTheoryRowStyle(tr, type);
    reindexTheoryChaptersAndLessons();
    syncTheoryHoursFromTable();
}

function onTheoryTypeChange(sel) {
    const tr = sel.closest('tr');
    const type = sel.value;
    const isChapter = type === 'Chương';

    tr.querySelectorAll('.t-extra input, .t-extra select').forEach(el => {
        el.disabled = isChapter;
        if (isChapter) {
            if (el.tagName === 'SELECT') {
                $(el).val(null).trigger('change');
            } else {
                el.value = '';
            }
        }
    });
    tr.querySelector('.t-title').placeholder = isChapter ? 'Tên chương...' : 'Nội dung bài giảng lý thuyết...';

    applyTheoryRowStyle(tr, type);
    reindexTheoryChaptersAndLessons();
    syncTheoryHoursFromTable();
}

function applyTheoryRowStyle(tr, type) {
    tr.classList.remove('is-chapter', 'is-lesson');
    if (type === 'Chương') {
        tr.classList.add('is-chapter');
        tr.querySelectorAll('.t-extra').forEach(td => td.style.opacity = '0.35');
    } else {
        tr.classList.add('is-lesson');
        tr.querySelectorAll('.t-extra').forEach(td => td.style.opacity = '1');
    }
}

function reindexTheoryChaptersAndLessons() {
    let chapterCount = 0;
    let lessonCount  = 0;

    document.querySelectorAll('#theoryTopicTable tbody tr').forEach(tr => {
        const typeSelect = tr.querySelector('.t-type');
        if (!typeSelect) return; // dòng giới thiệu (Bài 0) không có select

        const type = typeSelect.value;
        if (type === 'Chương') {
            chapterCount++;
            lessonCount = 0; // reset bài khi sang chương mới
            tr.querySelector('.t-chapter-label').value = `Chương ${chapterCount}`;
        } else {
            lessonCount++;
            tr.querySelector('.t-chapter-label').value = `Bài ${lessonCount}`;
        }
    });
}

// -------------------------------------------------------------
// LOGIC XỬ LÝ BẢNG ĐỘNG 5.2: THỰC HÀNH
// -------------------------------------------------------------
function addPracticalRow() {
    const tbody = document.querySelector('#practicalTopicTable tbody');
    const tr = document.createElement('tr');

    let facilityOptions = `<option value="">-- Chọn cơ sở thực hành --</option>`;
    dbFacilities.forEach(f => { facilityOptions += `<option value="${f}">${f}</option>`; });
    facilityOptions += `<option value="Option 1">Option 1</option><option value="Option 2">Option 2</option><option value="Option 3">Option 3</option>`;

    tr.innerHTML = `
        <td><input type="text" class="form-control p-topic" name="practical_topic[]" placeholder="Tự nhập chủ đề"></td>
        <td><textarea class="form-control p-content" name="practical_content[]" rows="1" placeholder="Nội dung thực hành"></textarea></td>
        <td><input type="text" class="form-control p-method" name="practical_method[]" placeholder="Hình thức dạy"></td>
        <td><input type="number" class="form-control p-hours" name="practical_hours[]" value="0" min="0"></td>
        <td><input type="number" class="form-control p-online" name="practical_online_hours[]" value="0" min="0"></td>
        <td><input type="text" class="form-control p-pedagogy" name="practical_pedagogy[]" placeholder="Phương pháp dạy họcs"></td>
        <td><input type="text" class="form-control p-clos" name="practical_clos[]" placeholder="Tự nhập CLOs"></td>
        <td><select class="form-select p-facility select2-searchable" name="practical_facility[]">${facilityOptions}</select></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();">Xóa</button></td>
    `;
    tbody.appendChild(tr);
    $(tr.querySelectorAll('.select2-searchable')).select2({ width: '100%', tags: true });
}

// -------------------------------------------------------------
// LOGIC XỬ LÝ BẢNG ĐỘNG 5.3: LÝ THUYẾT VÀ THỰC HÀNH (CHUNG)
// -------------------------------------------------------------
function addCombinedRow() {
    const tbody = document.querySelector('#combinedTopicTable tbody');
    const tr = document.createElement('tr');

    let facilityOptions = `<option value="">-- Chọn cơ sở --</option>`;
    dbFacilities.forEach(f => { facilityOptions += `<option value="${f}">${f}</option>`; });
    facilityOptions += `<option value="Option 1">Option 1</option><option value="Option 2">Option 2</option><option value="Option 3">Option 3</option>`;

    tr.innerHTML = `
        <td class="text-center fw-bold combined-stt"></td>
        <td><textarea class="form-control c-content" name="combined_content[]" rows="1" placeholder="Nội dung chính"></textarea></td>
        <td><input type="text" class="form-control c-method" name="combined_method[]" placeholder="Hình thức dạy"></td>
        <td><input type="number" class="form-control c-lt text-center" name="combined_theory_hours[]" value="0" min="0"></td>
        <td><input type="number" class="form-control c-th text-center" name="combined_practical_hours[]" value="0" min="0"></td>
        <td><input type="number" class="form-control c-online text-center" name="combined_online_hours[]" value="0" min="0"></td>
        <td><input type="number" class="form-control c-sh text-center" name="combined_self_hours[]" value="0" min="0"></td>
        <td><input type="text" class="form-control c-pedagogy" name="combined_pedagogy[]" placeholder="Phương pháp dạy học"></td>
        <td><input type="text" class="form-control c-clos" name="combined_clos[]" placeholder="Tự nhập CLOs"></td>
        <td><select class="form-select c-facility select2-searchable" name="combined_facility[]">${facilityOptions}</select></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="removeCombinedRow(this);">Xóa</button></td>
    `;
    tbody.appendChild(tr);
    $(tr.querySelectorAll('.select2-searchable')).select2({ width: '100%', tags: true });
    reindexCombinedTable();
}

function removeCombinedRow(btn) {
    btn.closest('tr').remove();
    reindexCombinedTable();
}

function reindexCombinedTable() {
    document.querySelectorAll('#combinedTopicTable tbody tr').forEach((tr, index) => {
        tr.querySelector('.combined-stt').innerText = index + 1;
    });
}

// -------------------------------------------------------------
// LOGIC XỬ LÝ BẢNG ĐỘNG MỤC 6: TÀI LIỆU DẠY VÀ HỌC (6.1 & 6.2)
// -------------------------------------------------------------
function addResourceRow(tableId) {
    const tbody = document.querySelector(`#${tableId} tbody`);
    const tr = document.createElement('tr');
    const fieldPrefix = tableId === 'resourceTeachTable' ? 'res_teach' : 'res_self';

    let bookOptions = `<option value="">-- Chọn hoặc tìm giáo trình có sẵn --</option>`;
    dbBooks.forEach(b => {
        bookOptions += `<option value="${b.id}" data-editor="${h(b.editor)}" data-publisher="${h(b.publisher)}" data-year="${h(b.year)}" data-isbn="${h(b.identifier)}">${h(b.title)}</option>`;
    });
    bookOptions += `<option value="991" data-editor="Chủ biên mẫu 1" data-publisher="NXB Y Học" data-year="2025" data-isbn="ISBN-001">Option 1</option>`;
    bookOptions += `<option value="992" data-editor="Chủ biên mẫu 2" data-publisher="NXB Giáo Dục" data-year="2026" data-isbn="ISBN-002">Option 2</option>`;
    bookOptions += `<option value="993" data-editor="Chủ biên mẫu 3" data-publisher="NXB Khoa Học" data-year="2026" data-isbn="ISBN-003">Option 3</option>`;

    tr.innerHTML = `
        <td class="text-center fw-bold res-stt"></td>
        <td><select class="form-select book-title-select" name="${fieldPrefix}_book_id[]" onchange="autoFillBookDetails(this);">${bookOptions}</select></td>
        <td><input type="text" class="form-control book-editor" name="${fieldPrefix}_editor[]" readonly placeholder="Chủ biên"></td>
        <td><input type="text" class="form-control book-publisher" name="${fieldPrefix}_publisher[]" readonly placeholder="Nhà xuất bản"></td>
        <td><input type="text" class="form-control book-year" name="${fieldPrefix}_year[]" readonly placeholder="Năm"></td>
        <td><input type="text" class="form-control book-isbn" name="${fieldPrefix}_isbn[]" readonly placeholder="Mã định danh"></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="removeResourceRow('${tableId}', this);">Xóa</button></td>
    `;
    tbody.appendChild(tr);
    $(tr.querySelector('.book-title-select')).select2({ width: '100%', tags: true });
    reindexResourceTable(tableId);
}

function autoFillBookDetails(selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    const tr = selectEl.closest('tr');
    if(!opt.value) {
        tr.querySelector('.book-editor').value = '';
        tr.querySelector('.book-publisher').value = '';
        tr.querySelector('.book-year').value = '';
        tr.querySelector('.book-isbn').value = '';
        return;
    }
    tr.querySelector('.book-editor').value = opt.getAttribute('data-editor') || 'Tự nhập';
    tr.querySelector('.book-publisher').value = opt.getAttribute('data-publisher') || 'Tự nhập';
    tr.querySelector('.book-year').value = opt.getAttribute('data-year') || 'Tự nhập';
    tr.querySelector('.book-isbn').value = opt.getAttribute('data-isbn') || 'Tự nhập';
}

function removeResourceRow(tableId, btn) {
    btn.closest('tr').remove();
    reindexResourceTable(tableId);
}

function reindexResourceTable(tableId) {
    document.querySelectorAll(`#${tableId} tbody tr`).forEach((tr, index) => {
        tr.querySelector('.res-stt').innerText = index + 1;
    });
}

// -------------------------------------------------------------
// ĐÓNG GÓI TOÀN BỘ DỮ LIỆU ĐỘNG THÀNH CHUỖI JSON TRƯỚC KHI SUBMIT
// -------------------------------------------------------------
function gatherJsonData() {
    console.log("%c--- BẮT ĐẦU KIỂM TRA TOÀN BỘ GIÁ TRỊ TRONG FORM TRƯỚC KHI LƯU ---", "color: #1a446c; font-weight: bold; font-size: 14px;");
    // - Cap nhat gia tri Ban dieu phoi truoc khi dong goi va submit.
    syncCoordinatingBoard();

    // 1. Thông tin học phần (year, major)
    year = document.getElementById('year')?.value || '', // new
    majorSelect = document.getElementById('majorSelect')?.value || '', // new
    console.log(year, majorSelect)

    // 3. Mục tiêu (chia làm 3 cột)
    objective_general = document.getElementsByName('objective_general')[0]?.value || '',
    objective_specific = document.getElementsByName('objective_specific')[0]?.value || '',
    objective_plo = document.getElementsByName('objective_plo')[0]?.value || '',

    console.log("objective_general: ", objective_general)
    console.log("objective_specific: ", objective_specific)
    console.log("objective_plo: ", objective_plo)

            


    // 1. In và kiểm tra các giá trị thuộc tính text/select cơ bản
    let basicData = {
        course_id: document.getElementById('courseSelect')?.value || '',
        name: document.getElementById('courseName')?.value.toUpperCase() || '',
        code: document.getElementById('code')?.value || '',
        module_type: document.getElementsByName('module_type')[0]?.value || '',
        credits: document.getElementById('credits')?.value || 0,
        credits_theory: document.getElementById('credits_theory')?.value || 0,
        credits_practice: document.getElementById('credits_practice')?.value || 0,
        total_hours: document.getElementById('total_hours')?.value || 0,
        theory_hours: document.getElementById('theory_hours')?.value || 0,
        practical_hours: document.getElementById('practical_hours')?.value || 0,
        self_study_hours: document.getElementsByName('self_study_hours')[0]?.value || 0,
        target_programs: document.getElementsByName('target_programs')[0]?.value || '',
        expected_semester: document.getElementsByName('expected_semester')[0]?.value || '',
        expected_year: document.getElementsByName('expected_year')[0]?.value || '',
        department_in_charge: $(document.getElementsByName('department_in_charge[]')).val() || [],
        coordinating_board: $(document.getElementsByName('coordinating_board[]')).val() || [],
        faculty_in_charge: document.getElementsByName('faculty_in_charge')[0]?.value || '',
        description: document.getElementsByName('description')[0]?.value || '',
        
        objective_general: document.getElementsByName('objective_general')[0]?.value || '', // new
        objective_specific: document.getElementsByName('objective_specific')[0]?.value || '', // new
        objective_plo: document.getElementsByName('objective_plo')[0]?.value || '', // new
        grading_scale: document.getElementsByName('grading_scale')[0]?.value || ''
    };

   

    console.log("1. Dữ liệu thông tin học phần cơ bản:");
    console.table(basicData);

    // =============================================================
    // 2. THU THẬP CHUẨN ĐẦU RA (CLOs) - ĐÃ CHUẨN HÓA CHUỖI AN TOÀN
    // =============================================================
    let clos = [];
    document.querySelectorAll('#cloTable tbody tr').forEach((row, index) => {
        const codeInput = row.querySelector('.c-code');
        // Nếu người dùng không nhập gì, tự động gán số thứ tự tự động để tránh rỗng dữ liệu
        let codeVal = codeInput ? codeInput.value.trim() : '';
        if (codeVal === '') {
            codeVal = 'CLO' + (index + 1);
        }
        const descTextarea = row.querySelector('.c-desc');
        const descVal = descTextarea ? descTextarea.value.trim() : '';

        // Lấy danh sách Lĩnh vực được tích chọn
        let domains = [];
        row.querySelectorAll('.chk-domain:checked').forEach(chk => {
            domains.push(chk.value);
        });

        // Lấy danh sách Mức độ Bloom tương ứng (Chỉ lấy các ô select đang mở hiển thị)
        let blooms = [];
        row.querySelectorAll('.sel-bloom-item').forEach(sel => {
            if (!sel.disabled && sel.value && sel.value.trim() !== '') {
                // Chỉ lấy phần text ngắn gọn, tránh các ký tự xuống dòng gây lỗi câu lệnh SQL
                blooms.push(sel.value.replace(/[\r\n\t]/g, '').trim());
            }
        });

        // Điều kiện giữ lại hàng: Phải có thông tin mã ký hiệu hoặc mô tả chuẩn đầu ra
        if (codeVal !== '' || descVal !== '') {
            clos.push({
                code: codeVal,
                domain: domains.join(', '),
                bloom: blooms.join(', '),
                description: descVal
            });
        }
    });
    
    // Gán dữ liệu sạch sau khi xử lý chuỗi vào ô input ẩn để chuẩn bị truyền đi
    document.getElementById('clos_json').value = JSON.stringify(clos);
    console.log("2. Mảng Chuẩn đầu ra (CLO) đã xử lý chuỗi an toàn:", clos);

    // 3. Thu thập Thành phần lượng giá (Assessments)
    let assessments = [];
    document.querySelectorAll('#assessmentTable tbody tr').forEach((tr) => {
        const aClosInput = tr.querySelector('.a-clos');
        const aClosVal = aClosInput ? aClosInput.value.trim() : '';
        
        // - Luu PLO va PI thanh chuoi PLO/PI de tuong thich voi cot plo_pi hien co.
        const ploVal = tr.querySelector('.a-plo')?.value || '';
        const piVal = ($(tr.querySelector('.a-pi')).val() || []).join(', ');
        const ploPiVal = [ploVal, piVal].filter(Boolean).join(' / ');

        const contribution = tr.querySelector('.a-contribution')?.value || '';
        console.log("contribution: ", contribution);

        const toolVal = ($(tr.querySelector('.a-tool')).val() || []).join(', ');

        const weightInput = tr.querySelector('.a-weight');
        const weightVal = weightInput ? weightInput.value : 0;

        const formVal = tr.querySelector('.a-form')?.value || '';

        if (aClosVal !== '' || formVal !== '') {
            assessments.push({
                clos: aClosVal,
                plo: ploVal,
                pi: piVal,
                plo_pi: ploPiVal,
                contribution: contribution, // new
                form: formVal,
                tool: toolVal,
                weight: weightVal
            });
        }
    });
    document.getElementById('assessments_json').value = JSON.stringify(assessments);
    console.log("3. Mảng Thành phần đánh giá đã đóng gói JSON:", assessments);

    // - Kiem tra tong trong so Chuyen can + Kiem tra thuong xuyen khong nho hon 50% tong Thi ket thuc.
    const nonFinalWeight = assessments
        .filter(a => a.form !== 'Thi kết thúc')
        .reduce((sum, a) => sum + (parseFloat(a.weight) || 0), 0);
    const finalWeight = assessments
        .filter(a => a.form === 'Thi kết thúc')
        .reduce((sum, a) => sum + (parseFloat(a.weight) || 0), 0);
    if (finalWeight > 0 && nonFinalWeight < finalWeight * 0.5) {
        alert('Tổng trọng số Chuyên cần + Kiểm tra thường xuyên không được nhỏ hơn 50% tổng trọng số Thi kết thúc.');
        return false;
    }

    // 4. Thu thập Hoạt động tự học
    let selfStudy = [];
    document.querySelectorAll('#selfStudyTable tbody tr').forEach(tr => {
        const activityVal = tr.querySelector('.ss-activity')?.value.trim() || '';
        const closVal = tr.querySelector('.ss-clos')?.value.trim() || '';
        
        if (activityVal !== '' || closVal !== '') {
            selfStudy.push({
                name: activityVal,
                clos: closVal,
                hours: tr.querySelector('.ss-duration')?.value || 0,
                method: tr.querySelector('.ss-method')?.value.trim() || '',
                assess: tr.querySelector('.ss-assess')?.value.trim() || '',
                evidence: tr.querySelector('.ss-evidence')?.value.trim() || ''
            });
        }
    });
    document.getElementById('self_study_json').value = JSON.stringify(selfStudy);
    console.log("4. Mảng Hoạt động tự học đã đóng gói JSON:", selfStudy);

    // 5. Thu thập Tiến độ Lý thuyết    
    let theory = [];
    let theoryRows = Array.from(document.querySelectorAll('#theoryTopicTable tbody tr'));

    theoryRows.forEach(tr => {
        if (tr.classList.contains('is-intro')) {
            theory.push({
                chapter: 'Bài 0',
                title: 'Giới thiệu học phần',
                type: 'intro',
                method: '',
                hours_class: null,
                hours_self: null,
                hours_online: null,
                pedagogy: '',
                clos: '',
                book: ''
            });
            return;
        }

        const titleVal = tr.querySelector('.t-title')?.value.trim() || '';
        if (titleVal === '') return;

        const typeSelect = tr.querySelector('.t-type');
        const type = typeSelect ? typeSelect.value : null;

        theory.push({
            chapter: tr.querySelector('.t-chapter-label')?.value || '',
            title: titleVal,
            type: type || 'intro',
            method: tr.querySelector('.t-method')?.value || '',
            hours_class: parseFloat(tr.querySelector('.t-class')?.value) || 0,
            hours_self: parseFloat(tr.querySelector('.t-self')?.value) || 0,
            hours_online: parseFloat(tr.querySelector('.t-online')?.value) || 0,
            pedagogy: tr.querySelector('.t-pedagogy')?.value || '',
            // - Lay nhieu CLO tu select multiple va bo cot sach/giao trinh.
            clos: ($(tr.querySelector('.t-clos')).val() || []).join(', '),
            book: ''
        });
    });

    // Tính tổng tiết cho mỗi Chương từ các Bài bên dưới
    for (let i = 0; i < theory.length; i++) {
        if (theory[i].type === 'Chương') {
            let sumClass = 0, sumSelf = 0, sumOnline = 0;
            // Duyệt các dòng tiếp theo cho đến khi gặp Chương khác
            for (let j = i + 1; j < theory.length; j++) {
                if (theory[j].type === 'Chương') break;
                sumClass  += theory[j].hours_class;
                sumSelf   += theory[j].hours_self;
                sumOnline += theory[j].hours_online;
            }
            theory[i].hours_class  = sumClass;
            theory[i].hours_self   = sumSelf;
            theory[i].hours_online = sumOnline;
        }
    }

    document.getElementById('theory_json').value = JSON.stringify(theory);
    console.log("5. Mảng Bài giảng lý thuyết đã đóng gói JSON:", theory);

    // 6. Thu thập Tiến độ Thực hành
    let practical = [];
    document.querySelectorAll('#practicalTopicTable tbody tr').forEach(tr => {
        const topicVal = tr.querySelector('.p-topic')?.value.trim() || '';
        if (topicVal !== '') {
            practical.push({
                topic: topicVal,
                content: tr.querySelector('.p-content')?.value.trim() || '',
                method: tr.querySelector('.p-method')?.value || '',
                hours_lab: tr.querySelector('.p-hours')?.value || 0,
                hours_online: tr.querySelector('.p-online')?.value || 0,
                pedagogy: tr.querySelector('.p-pedagogy')?.value || '',
                clos: tr.querySelector('.p-clos')?.value || '',
                facility: $(tr.querySelector('.p-facility')).val() || ''
            });
        }
    });
    document.getElementById('practical_json').value = JSON.stringify(practical);
    console.log("6. Mảng Bài giảng thực hành đã đóng gói JSON:", practical);

    // 7. Thu thập Tiến độ Tích hợp (Chung)
    let combined = [];
    document.querySelectorAll('#combinedTopicTable tbody tr').forEach((tr, index) => {
        const contentVal = tr.querySelector('.c-content')?.value.trim() || '';
        if (contentVal !== '') {
            combined.push({
                stt: index + 1,
                content: contentVal,
                method: tr.querySelector('.c-method')?.value || '',
                hours_theory: tr.querySelector('.c-lt')?.value || 0,
                hours_practice: tr.querySelector('.c-th')?.value || 0,
                hours_online: tr.querySelector('.c-online')?.value || 0,
                hours_self: tr.querySelector('.c-sh')?.value || 0,
                pedagogy: tr.querySelector('.c-pedagogy')?.value || '',
                clos: tr.querySelector('.c-clos')?.value || '',
                facility: $(tr.querySelector('.c-facility')).val() || ''
            });
        }
    });
    document.getElementById('combined_json').value = JSON.stringify(combined);
    console.log("7. Mảng Chủ đề tích hợp đã đóng gói JSON:", combined);

    // 8. Thu thập Tài liệu giảng dạy
    let resTeach = [];
    document.querySelectorAll('#resourceTeachTable tbody tr').forEach(tr => {
        const titleText = $(tr.querySelector('.book-title-select')).find('option:selected').text();
        const selectVal = $(tr.querySelector('.book-title-select')).val();
        if (selectVal) {
            resTeach.push({
                title: titleText,
                editor: tr.querySelector('.book-editor')?.value || '',
                publisher: tr.querySelector('.book-publisher')?.value || '',
                year: tr.querySelector('.book-year')?.value || '',
                isbn: tr.querySelector('.book-isbn')?.value || ''
            });
        }
    });
    document.getElementById('res_teach_json').value = JSON.stringify(resTeach);
    console.log("8. Mảng Tài liệu giảng dạy đã đóng gói JSON:", resTeach);

    // 9. Thu thập Tài liệu tự học
    let resSelf = [];
    document.querySelectorAll('#resourceSelfTable tbody tr').forEach(tr => {
        const titleText = $(tr.querySelector('.book-title-select')).find('option:selected').text();
        const selectVal = $(tr.querySelector('.book-title-select')).val();
        if (selectVal) {
            resSelf.push({
                title: titleText,
                editor: tr.querySelector('.book-editor')?.value || '',
                publisher: tr.querySelector('.book-publisher')?.value || '',
                year: tr.querySelector('.book-year')?.value || '',
                isbn: tr.querySelector('.book-isbn')?.value || ''
            });
        }
    });
    document.getElementById('res_self_json').value = JSON.stringify(resSelf);
    console.log("9. Mảng Tài liệu tự học đã đóng gói JSON:", resSelf);

    console.log("%c--- KIỂM TRA HOÀN TẤT. DỮ LIỆU HỢP LỆ VÀ ĐÃ ĐƯỢC CHUYỂN ĐI! ---", "color: #27ae60; font-weight: bold; font-size: 14px;");
    return true;
}

// KHỞI TẠO CÁC CẤU HÌNH BAN ĐẦU KHI TRANG TẢI XONG
$(document).ready(function() {
    $('#courseSelect').select2({
        placeholder: '(Chọn học phần nền từ hệ thống)',
        allowClear: true,
        width: '100%'
    });

    $('#majorSelect').select2({
        placeholder: '(Chọn ngành nền từ hệ thống)',
        allowClear: true,
        width: '100%'
    }).on('change', function() {
        // - Doi nganh thi nap lai danh muc PLO/PI cho bang 4.2.
        refreshAssessmentPloPiOptions();
    });

    $('.select2-enable').select2({ width: '100%' });
    $('.select2-multiple').select2({ width: '100%' });
    // - Ban dieu phoi co cach chon nhieu giong cac select hoc phan lien quan va luu lai thanh chuoi.
    $('#coordinatingBoardSelect').on('change', syncCoordinatingBoard);

    function formatCourseSelection(state) {
        if (!state.id) { return state.text; }
        var code = $(state.element).data('code');
        if (code) { return code; }
        return state.text;
    }

    $('.select2-course').select2({
        width: '100%',
        templateSelection: formatCourseSelection
    });

    // Nạp sẵn cấu trúc rỗng ban đầu cho form chuyên nghiệp
    addCloRow();
    // addAssessmentRow();
    // addSelfStudyRow();

    // Dòng mặc định: Bài 0 - Giới thiệu học phần
    (function addIntroRow() {
        const tbody = document.querySelector('#theoryTopicTable tbody');
        const tr = document.createElement('tr');
        tr.classList.add('is-intro');
        tr.innerHTML = `
            <td class="text-center fw-bold text-secondary" style="font-size:13px;">
                Bài 0<br><small>(Giới thiệu)</small>
            </td>
            <!-- - Dieu chinh colspan sau khi bo cot Ten sach/Giao trinh. -->
            <td colspan="7" class="text-muted fst-italic ps-3">
                Giới thiệu học phần &nbsp;–&nbsp; 0 tiết
                <input type="hidden" name="theory_chapter[]" value="Bài 0">
                <input type="hidden" name="theory_title[]" value="Giới thiệu học phần">
                <input type="hidden" name="theory_class_hours[]" value="0">
                <input type="hidden" name="theory_self_hours[]" value="0">
                <input type="hidden" name="theory_online_hours[]" value="0">
                <input type="hidden" name="theory_pedagogy[]" value="">
                <input type="hidden" name="theory_clos[]" value="">
                <input type="hidden" name="theory_method[]" value="">
            </td>
            <td class="text-center text-muted" style="font-size:12px;">Mặc định</td>
        `;
        tbody.appendChild(tr);
    })();

    addTheoryRow(); 
    addPracticalRow();
    addCombinedRow();
    addResourceRow('resourceTeachTable');
    addResourceRow('resourceSelfTable');

    <?php if($selectedCourse): ?>
        extractCourseName();
    <?php endif; ?>

    // - Bang 6.1 chi tu tinh tu hoc tung dong, khong ghi de o LT phan dau.
    syncTheoryHoursFromTable();
    syncSelfStudyTotal();
    syncCoordinatingBoard();
    document.querySelector('input[name="self_study_hours"]').addEventListener('input', syncSelfStudyTotal);
});

function syncCloToTables() {
    const cloCodes = [];
    document.querySelectorAll('#cloTable tbody tr').forEach((tr, idx) => {
        const code = tr.querySelector('.c-code')?.value.trim() || `CLO${idx + 1}`;
        cloCodes.push(code);
    });

    // Sync bảng 5.2
    const assessTbody = document.querySelector('#assessmentTable tbody');
    const methodOptions = assessmentMethods.map(m => `<option value="${m}">${m}</option>`).join('');
    cloCodes.forEach((code, i) => {
        if (i < assessTbody.rows.length) {
            const cloInput = assessTbody.rows[i].querySelector('.a-clos');
            // - Tu dong nap lai dung CLO theo tung hang cua bang Bloom vao bang 5.2.
            if (cloInput) cloInput.value = code;
        } else {
            assessmentRowIndex++;
            const rowId = assessmentRowIndex;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <input type="hidden" name="assessment_row_ids[]" value="${rowId}">
                    <input type="text" class="form-control a-clos" name="assessment_clos[]" value="${code}">
                </td>
                <!-- - PLO/PI dong bo cho dong assessment tu sinh theo CLO. -->
                <td><select class="form-select a-plo" name="assessment_plo[]" onchange="onAssessmentPloChange(this)">${buildPloOptions()}</select></td>
                <!-- - PI dong bo la select2 multiple va thay doi theo PLO. -->
                <td><select class="form-select a-pi select2-multiple" name="assessment_pi_${rowId}[]" multiple="multiple">${buildPiOptions('')}</select></td>
                <td>
                    <select class="form-select a-contribution" name="assessment_contribution[]">
                        <option value="">-- Chọn mức độ --</option>
                        <option value="I">I – Giới thiệu</option>
                        <option value="R">R – Củng cố</option>
                        <option value="M">M – Thành thạo</option>
                        <option value="A">A – Đánh giá</option>
                    </select>
                </td>
                <td>
                    <select class="form-select a-form" name="assessment_form_${rowId}" onchange="onAssessmentFormChange(this)">
                        <option value="">-- Chọn hình thức --</option>
                        ${methodOptions}
                    </select>
                </td>
                <td><select class="form-select a-tool select2-multiple" name="assessment_tool_${rowId}[]" multiple="multiple"></select></td>
                <td><input type="number" class="form-control a-weight" name="assessment_weight[]" value="0" min="0" max="100" oninput="onWeightInput(this)"></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();">Xóa</button></td>
            `;
            assessTbody.appendChild(tr);
            $(tr.querySelector('.a-pi')).select2({ width: '100%', placeholder: "Chọn PI" });
            $(tr.querySelector('.a-tool')).select2({ width: '100%', placeholder: "Chọn công cụ đánh giá" });
            onAssessmentFormChange(tr.querySelector('.a-form'));
        }
    });
    while (assessTbody.rows.length > cloCodes.length) assessTbody.deleteRow(assessTbody.rows.length - 1);

    // Sync bảng 5.3
    const ssTbody = document.querySelector('#selfStudyTable tbody');
    cloCodes.forEach((code, i) => {
        if (i < ssTbody.rows.length) {
            const cloInput = ssTbody.rows[i].querySelector('.ss-clos');
            // - Tu dong nap lai dung CLO theo tung hang cua bang Bloom vao bang 5.3.
            if (cloInput) cloInput.value = code;
        } else {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="text" class="form-control ss-activity" name="self_study_name[]" placeholder="Tự nhập hoạt động"></td>
                <td><input type="text" class="form-control ss-clos text-center" name="self_study_clos[]" value="${code}"></td>
                <td style="display:none;"><input type="number" class="form-control ss-duration" name="self_study_duration[]" value="0" min="0"></td>
                <td><input type="text" class="form-control ss-method" name="self_study_method[]" placeholder="Phương pháp tự học"></td>
                <td><input type="text" class="form-control ss-assess" name="self_study_assess[]" placeholder="Cách thức đánh giá"></td>
                <td><input type="text" class="form-control ss-evidence" name="self_study_evidence[]" placeholder="Minh chứng"></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();">Xóa</button></td>
            `;
            ssTbody.appendChild(tr);
        }
    });
    while (ssTbody.rows.length > cloCodes.length) ssTbody.deleteRow(ssTbody.rows.length - 1);

    // - Cap nhat danh sach CLO cho cac select multiple o bang 6.1.
    document.querySelectorAll('#theoryTopicTable .t-clos').forEach(select => {
        const selected = $(select).val() || [];
        $(select).html(buildCloOptions(selected)).trigger('change');
    });
}

</script>
</body>
</html>

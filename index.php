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
    </style>
</head>
<body>

<div class="container syllabus-container">
    <p><a href="list.php">Xem danh sách học phần</a> | <a href="courses.php">Quay về danh sách học phần CTĐT</a></p>
    <h2 class="text-center main-title">Xây dựng Đề cương chi tiết học phần</h2>

    <form action="save.php" method="POST" onsubmit="return gatherJsonData();" onkeydown="return event.key != 'Enter';" autocomplete="off">

        <div class="section-title">1. THÔNG TIN HỌC PHẦN</div>

        <div class="row g-3">
            <div class="col-md-6">
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
            <div class="col-md-6">
                <label class="form-label fw-bold">Tên học phần:</label>
                <input type="text" id="courseName" name="name" class="form-control" required>
            </div>

            <div class="col-md-6">
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
                    <input type="number" id="theory_hours" name="theory_hours" class="form-control" placeholder="Lý thuyết" min="0" oninput="calculateTotalHours();">
                    <input type="number" id="practical_hours" name="practical_hours" class="form-control" placeholder="Thực hành" min="0" oninput="calculateTotalHours();">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Số giờ tự học (tiết):</label>
                <input type="number" name="self_study_hours" class="form-control" value="0" min="0">
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
                <input type="text" name="coordinating_board" class="form-control" placeholder="Cách nhau bằng dấu phẩy nếu nhiều ban">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Khoa phụ trách:</label>
                <select name="faculty_in_charge" class="form-select select2-enable">
                    <option value="">-- Chọn Khoa phụ trách --</option>
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

        <div class="sub-section-header">
            <div class="sub-section-title">3.1. Mục tiêu</div>
        </div>
        <div class="mb-3">
            <textarea name="objectives" class="form-control" rows="3" placeholder="Nhập các mục tiêu tổng quát của học phần..."></textarea>
        </div>

        <div class="sub-section-header">
            <div class="sub-section-title">3.2. Chuẩn đầu ra học phần (Bloom)</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addCloRow();">+ Thêm dòng CLO</button>
        </div>
        <table class="table table-bordered align-middle" id="cloTable">
            <thead>
                <tr>
                    <th style="width: 25%;">Lĩnh vực</th>
                    <th style="width: 25%;">Mức độ Bloom Taxonomy</th>
                    <th style="width: 10%;">TT</th>
                    <th style="width: 32%;">Chuẩn đầu ra học phần (Mô tả)</th>
                    <th style="width: 8%;">Hành động</th>
                </tr>
            </thead>
            <tbody>
                </tbody>
        </table>
        <input type="hidden" id="clos_json" name="clos_json">

        <div class="section-title">4. PHƯƠNG PHÁP KIỂM TRA, LƯỢNG GIÁ HỌC PHẦN</div>

        <div class="sub-section-header">
            <div class="sub-section-title">4.1. Thang điểm lượng giá</div>
        </div>
        <div class="mb-3">
            <textarea name="grading_scale" class="form-control" rows="2" placeholder="Nhập thông tin quy định thang điểm lý thuyết / thực hành (Dạng chữ hoặc số)..."></textarea>
        </div>

        <div class="sub-section-header">
            <div class="sub-section-title">4.2. Phương pháp kiểm tra lượng giá</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addAssessmentRow();">+ Thêm thành phần lượng giá</button>
        </div>
        <table class="table table-bordered align-middle" id="assessmentTable">
            <thead>
                <tr>
                    <th style="width: 15%;">CLOs</th>
                    <th style="width: 15%;">PLO/PI liên quan</th>
                    <th style="width: 30%;">Hình thức đánh giá (Chọn nhiều)</th>
                    <th style="width: 22%;">Công cụ đánh giá</th>
                    <th style="width: 10%;">Trọng số (%)</th>
                    <th style="width: 8%;">Hành động</th>
                </tr>
            </thead>
            <tbody>
                </tbody>
        </table>
        <input type="hidden" id="assessments_json" name="assessments_json">

        <div class="sub-section-header">
            <div class="sub-section-title">4.3. Phương pháp lượng giá hoạt động tự học</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addSelfStudyRow();">+ Thêm hoạt động tự học</button>
        </div>
        <table class="table table-bordered align-middle" id="selfStudyTable">
            <thead>
                <tr>
                    <th>Hoạt động tự học</th>
                    <th style="width: 15%;">Mục tiêu/Chuẩn đầu ra liên quan(CLOs)</th>
                    <th style="width: 12%;">Thời lượng (giờ)</th>
                    <th>Phương pháp tự học</th>
                    <th>Cách thức đánh giá</th>
                    <th>Minh chứng</th>
                    <th style="width: 8%;">Hành động</th>
                </tr>
            </thead>
            <tbody>
                </tbody>
        </table>
        <input type="hidden" id="self_study_json" name="self_study_json">


        <div class="section-title">5. NỘI DUNG HỌC PHẦN VÀ PHƯƠNG PHÁP DẠY-HỌC</div>

        <div class="sub-section-header">
            <div class="sub-section-title">5.1. Lý thuyết</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addTheoryRow();">+ Thêm bài giảng lý thuyết</button>
        </div>
        <table class="table table-bordered align-middle" id="theoryTopicTable">
            <thead>
                <tr>
                    <th style="width: 15%;">Chương/Bài</th>
                    <th style="width: 22%;">Bài giảng/ Nội dung lý thuyết</th>
                    <th style="width: 15%;">Hình thức giảng dạy</th>
                    <th style="width: 10%;">Số tiết trên lớp</th>
                    <th style="width: 10%;">Số tiết tự học</th>
                    <th style="width: 12%;">Chuẩn đầu ra liên quan(CLOs)</th>
                    <th>Tên sách/giáo trình, chương trình được sử dụng</th>
                    <th style="width: 6%;">Xóa</th>
                </tr>
            </thead>
            <tbody>
                </tbody>
        </table>
        <input type="hidden" id="theory_json" name="theory_json">

        <div class="sub-section-header">
            <div class="sub-section-title">5.2. Thực hành</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addPracticalRow();">+ Thêm nội dung thực hành</button>
        </div>
        <table class="table table-bordered align-middle" id="practicalTopicTable">
            <thead>
                <tr>
                    <th style="width: 15%;">Chủ đề</th>
                    <th>Nội dung thực hành/ Kỹ năng</th>
                    <th style="width: 15%;">Hình thức dạy học</th>
                    <th style="width: 10%;">Số tiết TH</th>
                    <th style="width: 15%;">Chuẩn đầu ra liên quan(CLOs)</th>
                    <th style="width: 18%;">Cơ sở thực hành</th>
                    <th style="width: 6%;">Xóa</th>
                </tr>
            </thead>
            <tbody>
                </tbody>
        </table>
        <input type="hidden" id="practical_json" name="practical_json">

        <div class="sub-section-header">
            <div class="sub-section-title">5.3. Lý thuyết và thực hành (chung)</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addCombinedRow();">+ Thêm chủ đề tích hợp chung</button>
        </div>
        <table class="table table-bordered align-middle" id="combinedTopicTable">
            <thead>
                <tr>
                    <th style="width: 6%;">STT</th>
                    <th>Nội dung chính</th>
                    <th style="width: 15%;">Hình thức dạy học</th>
                    <th style="width: 8%;">Số tiết LT</th>
                    <th style="width: 8%;">Số tiết TH</th>
                    <th style="width: 8%;">Số tiết tự học</th>
                    <th style="width: 12%;">Chuẩn đầu ra liên quan(CLOs)</th>
                    <th style="width: 18%;">Cơ sở thực hành</th>
                    <th style="width: 6%;">Xóa</th>
                </tr>
            </thead>
            <tbody>
                </tbody>
        </table>
        <input type="hidden" id="combined_json" name="combined_json">


        <div class="section-title">6. TÀI LIỆU DẠY HỌC</div>

        <div class="sub-section-header">
            <div class="sub-section-title">6.1. Tài liệu giảng dạy</div>
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
            <div class="sub-section-title">6.2. Tài liệu tự học</div>
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
// Các biến cấu trúc danh mục đổ ra từ PHP phục vụ render thẻ select trong các bảng động
const dbFacilities = <?php echo json_encode($facilitiesList); ?>;
const dbBooks = <?php echo json_encode($booksCatalog); ?>;
const dbCoursesList = <?php echo json_encode($courses); ?>;
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

// Danh sách Hình thức lượng giá cho mục 4.2 (Hỗ trợ Multi-select)
const assessmentMethods = ["Chuyên cần", "Thi viết", "Thi kết thúc", "Kiểm tra thường", "Logbook", "OCSE", "Tự học", "Đánh giá thái độ"];

// Định nghĩa từ điển Bloom Taxonomy mở rộng
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

// Hàm tự động fill thông tin giờ học khi chọn học phần từ hệ thống
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
        document.getElementById('theory_hours').value = target.theory_hours;
        document.getElementById('practical_hours').value = target.practical_hours;

        calculateTotalHours();

        document.getElementById('credits_theory').value = Math.round(target.theory_hours / 15) || 0;
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
}

let cloIndex = 0;
let assessmentRowIndex = 0;

function updateVerbsHint(selectEl) {
    // Tìm hàng (tr) hiện tại của phần tử đang tương tác
    const tr = selectEl.closest('tr');
    const textarea = tr.querySelector('.c-desc');
    const hintEl = tr.querySelector('.verbs-hint');
    
    let combinedHints = [];
    let placeholderHints = [];
    
    // Quét qua tất cả các ô select Bloom trong hàng này
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
                <input class="form-check-input chk-domain" type="checkbox" value="${domain}" id="${cbId}"
                       name="clo_domain_${rowId}[]"
                       onchange="toggleBloomSelect(this, '${domain}', '${uid}', ${idx})">
                <label class="form-check-label" for="${cbId}">${domain}</label>
            </div>`;
        // BỔ SUNG: Thêm thuộc tính data-domain và sự kiện onchange="updateVerbsHint(this)"
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

    // QUAN TRỌNG: Thêm một thẻ nhỏ <small class="verbs-hint"> dưới textarea để hiển thị từ gợi ý trực quan
    tr.innerHTML = `
        <td>${domainHtml}</td>
        <td>${bloomHtml}</td>
        <td>
            <input type="hidden" name="clo_row_ids[]" value="${rowId}">
            <input type="text" class="form-control c-code text-center fw-bold" name="clo_code[]" value="" placeholder="CLO1 hoặc CLO1, CLO2">
        </td>
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
}

function removeCloRow(btn) {
    btn.closest('tr').remove();
    reindexCloTable();
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
        <td><input type="text" class="form-control a-plo" name="assessment_plo_pi[]"></td>
        <td>
            <select class="form-select a-form select2-multiple" name="assessment_form_${rowId}[]" multiple="multiple">
                ${methodOptions}
            </select>
        </td>
        <td><input type="text" class="form-control a-tool" name="assessment_tool[]" placeholder="Người dùng tự nhập công cụ"></td>
        <td><input type="number" class="form-control a-weight" name="assessment_weight[]" value="0" min="0" max="100"></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();">Xóa</button></td>
    `;
    tbody.appendChild(tr);
    $(tr.querySelector('.select2-multiple')).select2({
        width: '100%',
        placeholder: "Chọn một hoặc nhiều hình thức"
    });
}

function addSelfStudyRow() {
    const tbody = document.querySelector('#selfStudyTable tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" class="form-control ss-activity" name="self_study_name[]" placeholder="Tự nhập hoạt động"></td>
        <td><input type="text" class="form-control ss-clos" name="self_study_clos[]" placeholder="Tự nhập CLOs"></td>
        <td><input type="number" class="form-control ss-duration" name="self_study_duration[]" value="0" min="0"></td>
        <td><input type="text" class="form-control ss-method" name="self_study_method[]" placeholder="Phương pháp tự học"></td>
        <td><input type="text" class="form-control ss-assess" name="self_study_assess[]" placeholder="Cách thức đánh giá"></td>
        <td><input type="text" class="form-control ss-evidence" name="self_study_evidence[]" placeholder="Minh chứng"></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();">Xóa</button></td>
    `;
    tbody.appendChild(tr);
}

function addTheoryRow() {
    const tbody = document.querySelector('#theoryTopicTable tbody');
    const tr = document.createElement('tr');

    // Tạo danh sách Sách/Giáo trình hỗ trợ tìm kiếm/gõ thêm
    let textbookOptions = `<option value="">-- Chọn giáo trình --</option>`;
    if (typeof dbBooks !== 'undefined' && dbBooks.length > 0) {
        dbBooks.forEach(b => { textbookOptions += `<option value="${b.title}">${b.title}</option>`; });
    }
    textbookOptions += `<option value="Option 1">Option 1</option><option value="Option 2">Option 2</option><option value="Option 3">Option 3</option>`;

    tr.innerHTML = `
        <td>
            <select class="form-select t-type select2-simple" onchange="reindexTheoryChaptersAndLessons()">
                <option value="Chương">Chương</option>
                <option value="Bài">Bài</option>
            </select>
            <input type="text" class="form-control t-chapter-label text-center fw-bold mt-1 bg-light" name="theory_chapter[]" readonly>
        </td>
        <td>
            <textarea class="form-control t-title" name="theory_title[]" rows="2" placeholder="Người dùng tự nhập nội dung bài giảng lý thuyết..."></textarea>
        </td>
        <td><input type="text" class="form-control t-method" name="theory_method[]" value="Học trên lớp"></td>
        <td><input type="number" class="form-control t-class" name="theory_class_hours[]" value="0" min="0"></td>
        <td><input type="number" class="form-control t-self" name="theory_self_hours[]" value="0" min="0"></td>
        <td><input type="text" class="form-control t-clos" name="theory_clos[]" placeholder="Tự nhập CLOs"></td>
        <td><select class="form-select t-textbook select2-searchable" name="theory_book[]">${textbookOptions}</select></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="removeTheoryRow(this)">Xóa</button></td>
    `;
    tbody.appendChild(tr);

    // Khởi tạo select2
    $(tr.querySelectorAll('.select2-simple')).select2({ width: '100%' });
    $(tr.querySelectorAll('.select2-searchable')).select2({ width: '100%', tags: true });

    reindexTheoryChaptersAndLessons();
}

function removeTheoryRow(btn) {
    btn.closest('tr').remove();
    reindexTheoryChaptersAndLessons();
}

// Hàm xử lý đếm số độc lập: Chương chạy từ 1, 2, 3... Bài chạy từ 1, 2, 3... riêng biệt
function reindexTheoryChaptersAndLessons() {
    let chapterCount = 0;
    let lessonCount = 0;

    document.querySelectorAll('#theoryTopicTable tbody tr').forEach(tr => {
        const typeSelect = tr.querySelector('.t-type');
        if (!typeSelect) return;

        const type = typeSelect.value;
        if (type === "Chương") {
            chapterCount++;
            tr.querySelector('.t-chapter-label').value = `Chương ${chapterCount}`;
        } else if (type === "Bài") {
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
        <td><input type="number" class="form-control c-lt" name="combined_theory_hours[]" value="0" min="0"></td>
        <td><input type="number" class="form-control c-th" name="combined_practical_hours[]" value="0" min="0"></td>
        <td><input type="number" class="form-control c-sh" name="combined_self_hours[]" value="0" min="0"></td>
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
        coordinating_board: document.getElementsByName('coordinating_board')[0]?.value || '',
        faculty_in_charge: document.getElementsByName('faculty_in_charge')[0]?.value || '',
        description: document.getElementsByName('description')[0]?.value || '',
        objectives: document.getElementsByName('objectives')[0]?.value || '',
        grading_scale: document.getElementsByName('grading_scale')[0]?.value || ''
    };
    console.log("1. Dữ liệu thông tin học phần cơ bản:");
    console.table(basicData);

    // 2. Thu thập Chuẩn đầu ra (CLOs)
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
        
        const ploInput = tr.querySelector('.a-plo');
        const ploVal = ploInput ? ploInput.value.trim() : '';

        const toolInput = tr.querySelector('.a-tool');
        const toolVal = toolInput ? toolInput.value.trim() : '';

        const weightInput = tr.querySelector('.a-weight');
        const weightVal = weightInput ? weightInput.value : 0;

        const formVal = ($(tr.querySelector('.a-form')).val() || []).join(', ');

        if (aClosVal !== '' || formVal !== '') {
            assessments.push({
                clos: aClosVal,
                plo_pi: ploVal,
                form: formVal,
                tool: toolVal,
                weight: weightVal
            });
        }
    });
    document.getElementById('assessments_json').value = JSON.stringify(assessments);
    console.log("3. Mảng Thành phần đánh giá đã đóng gói JSON:", assessments);

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
    document.querySelectorAll('#theoryTopicTable tbody tr').forEach(tr => {
        const titleVal = tr.querySelector('.t-title')?.value.trim() || '';
        if (titleVal !== '') {
            theory.push({
                chapter: tr.querySelector('.t-chapter-label')?.value || '',
                title: titleVal,
                method: tr.querySelector('.t-method')?.value || '',
                hours_class: tr.querySelector('.t-class')?.value || 0,
                hours_self: tr.querySelector('.t-self')?.value || 0,
                clos: tr.querySelector('.t-clos')?.value || '',
                book: $(tr.querySelector('.t-textbook')).val() || ''
            });
        }
    });
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
                hours_self: tr.querySelector('.c-sh')?.value || 0,
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

    $('.select2-enable').select2({ width: '100%' });
    $('.select2-multiple').select2({ width: '100%' });

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
    addAssessmentRow();
    addSelfStudyRow();
    addTheoryRow();
    addPracticalRow();
    addCombinedRow();
    addResourceRow('resourceTeachTable');
    addResourceRow('resourceSelfTable');

    <?php if($selectedCourse): ?>
        extractCourseName();
    <?php endif; ?>
});

</script>
</body>
</html>

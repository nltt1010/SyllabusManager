<?php
require 'db.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

$programOptions = syllabus_get_program_options($pdo);
$courseOptions = syllabus_get_course_options($pdo);
$lecturerOptions = syllabus_get_lecturer_options($pdo);
$assessmentToolOptions = syllabus_get_assessment_tool_options($pdo);
$facilitiesList = $pdo->query('SELECT name FROM facilities ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
$booksCatalog = $pdo->query('SELECT * FROM books_catalog ORDER BY title')->fetchAll(PDO::FETCH_ASSOC);
$departmentsList = $pdo->query('SELECT id, name FROM departments_list ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

$selectedCourseId = !empty($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$selectedFramework = $selectedCourseId > 0 ? syllabus_get_course_framework($pdo, $selectedCourseId) : null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xây dựng Đề cương chi tiết học phần</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; padding-top: 30px; padding-bottom: 50px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .syllabus-container { background: #ffffff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 40px; }
        .main-title { font-weight: 700; color: #1a446c; text-transform: uppercase; margin-bottom: 30px; border-bottom: 3px solid #1a446c; padding-bottom: 10px; }
        .section-title { background: #1a446c; color: #ffffff; padding: 10px 15px; font-weight: 600; text-transform: uppercase; margin-top: 35px; margin-bottom: 20px; border-radius: 4px; }
        .sub-section-header { display: flex; justify-content: space-between; align-items: center; margin-top: 25px; margin-bottom: 15px; border-left: 4px solid #3498db; padding-left: 10px; }
        .sub-section-title { font-weight: 600; color: #2c3e50; margin: 0; }
        .table th { background-color: #f8f9fa; color: #333; font-weight: 600; text-align: center; vertical-align: middle; font-size: 14px; }
        .readonly-note { font-size: 12px; color: #6c757d; display: block; margin-top: 4px; }
    </style>
</head>
<body>
<div class="container syllabus-container">
    <p><a href="list.php">Danh sách đề cương</a> | <a href="courses.php">Quản lý học phần khung</a></p>
    <h2 class="text-center main-title">Xây dựng Đề cương chi tiết học phần</h2>

    <form action="save.php" method="POST" onsubmit="return gatherJsonData();" autocomplete="off">
        <input type="hidden" name="education_program_id" id="education_program_id" value="<?= h($selectedFramework['education_program_id'] ?? '') ?>">
        <input type="hidden" id="clos_json" name="clos_json">
        <input type="hidden" id="assessments_json" name="assessments_json">
        <input type="hidden" id="self_study_json" name="self_study_json">
        <input type="hidden" id="theory_json" name="theory_json">
        <input type="hidden" id="practical_json" name="practical_json">
        <input type="hidden" id="combined_json" name="combined_json">
        <input type="hidden" id="res_teach_json" name="res_teach_json">
        <input type="hidden" id="res_self_json" name="res_self_json">

        <div class="section-title">1. Thông tin học phần</div>
        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label fw-bold">Năm khung</label>
                <select id="programYearSelect" class="form-select"></select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Ngành</label>
                <select id="programMajorSelect" class="form-select"></select>
                <span class="readonly-note">Ngành được chọn theo khung chương trình.</span>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">Học phần nền</label>
                <select id="courseSelect" name="course_id" class="form-select" required></select>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold">Tên học phần</label>
                <input type="text" id="courseName" name="name" class="form-control bg-light" readonly required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Mã học phần</label>
                <input type="text" id="courseCode" name="code" class="form-control bg-light" readonly required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Tính chất học phần</label>
                <input type="text" id="moduleTypeDisplay" class="form-control bg-light" readonly>
                <input type="hidden" id="moduleType" name="module_type">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold">Tổng tín chỉ (Tổng / LT / TH)</label>
                <div class="input-group">
                    <input type="number" id="credits" name="credits" class="form-control bg-light" readonly>
                    <input type="number" id="credits_theory" name="credits_theory" class="form-control bg-light" readonly>
                    <input type="number" id="credits_practice" name="credits_practice" class="form-control bg-light" readonly>
                </div>
                <span class="readonly-note">Dữ liệu lấy từ khung, không cho chỉnh sửa.</span>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Phân bố tiết (Tổng / LT / TH)</label>
                <div class="input-group">
                    <input type="number" id="total_hours" name="total_hours" class="form-control bg-light" readonly>
                    <input type="number" id="theory_hours" name="theory_hours" class="form-control bg-light" readonly>
                    <input type="number" id="practical_hours" name="practical_hours" class="form-control bg-light" readonly>
                </div>
                <span class="readonly-note">Dữ liệu lấy từ khung, không cho chỉnh sửa.</span>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Số giờ tự học</label>
                <input type="number" name="self_study_hours" class="form-control" value="0" min="0">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold">Đối tượng người học</label>
                <input type="text" id="targetPrograms" name="target_programs" class="form-control bg-light" readonly>
                <span class="readonly-note">Chỉ hiển thị ngành theo năm khung đã chọn.</span>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Học kỳ dự kiến</label>
                <input type="text" id="expectedSemester" name="expected_semester" class="form-control bg-light" readonly>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Năm học hiển thị</label>
                <input type="text" id="expectedYear" name="expected_year" class="form-control bg-light" readonly>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold">Học phần tiên quyết</label>
                <textarea id="prerequisiteText" class="form-control bg-light" rows="2" readonly></textarea>
                <div id="prerequisiteHidden"></div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Học phần song hành</label>
                <textarea id="parallelText" class="form-control bg-light" rows="2" readonly></textarea>
                <div id="parallelHidden"></div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Học phần học trước</label>
                <textarea id="previousText" class="form-control bg-light" rows="2" readonly></textarea>
                <div id="previousHidden"></div>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold">Bộ môn tham gia giảng dạy</label>
                <select name="department_in_charge[]" class="form-select select2-multiple" multiple>
                    <?php foreach ($departmentsList as $department): ?>
                        <option value="<?= h($department['id']) ?>"><?= h($department['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Ban điều phối học phần</label>
                <select name="coordinator_names[]" id="coordinatorSelect" class="form-select" multiple></select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Khoa phụ trách</label>
                <input type="text" id="facultyName" name="faculty_in_charge" class="form-control bg-light" readonly>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold">Hình thức giảng dạy chung</label>
                <select name="delivery_mode" class="form-select">
                    <option value="Học trên lớp">Học trên lớp</option>
                    <option value="Học trực tuyến">Học trực tuyến</option>
                    <option value="Kết hợp">Kết hợp</option>
                </select>
            </div>
        </div>

        <div class="section-title">2. Mô tả học phần</div>
        <textarea name="description" class="form-control" rows="4" placeholder="Nhập mô tả học phần..."></textarea>

        <div class="section-title">3. Mục tiêu và chuẩn đầu ra học phần</div>
        <div class="sub-section-header">
            <div class="sub-section-title">3.1. Mục tiêu học phần</div>
        </div>
        <textarea name="objectives" class="form-control" rows="3" placeholder="Nhập mục tiêu học phần..."></textarea>

        <div class="sub-section-header">
            <div class="sub-section-title">3.2. CLO</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addCloRow();">+ Thêm CLO</button>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle" id="cloTable">
                <thead>
                    <tr>
                        <th style="width: 8%;">Mã</th>
                        <th style="width: 10%;">Lĩnh vực</th>
                        <th style="width: 10%;">Bloom</th>
                        <th style="width: 10%;">Mức đóng góp</th>
                        <th style="width: 10%;">PI ID</th>
                        <th style="width: 12%;">PLO/PI</th>
                        <th>Nội dung</th>
                        <th style="width: 6%;">Xóa</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="section-title">4. Phương pháp kiểm tra, lượng giá học phần</div>
        <div class="sub-section-header">
            <div class="sub-section-title">4.1. Thang điểm lượng giá</div>
        </div>
        <textarea name="grading_scale" id="gradingScale" class="form-control bg-light" rows="2" readonly></textarea>

        <div class="sub-section-header">
            <div class="sub-section-title">4.2. Thành phần đánh giá</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addAssessmentRow();">+ Thêm thành phần</button>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle" id="assessmentTable">
                <thead>
                    <tr>
                        <th style="width: 16%;">Loại đánh giá</th>
                        <th style="width: 15%;">CLOs</th>
                        <th style="width: 15%;">PLO/PI</th>
                        <th style="width: 34%;">Công cụ đánh giá</th>
                        <th style="width: 10%;">Trọng số (%)</th>
                        <th style="width: 10%;">Xóa</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="sub-section-header">
            <div class="sub-section-title">4.3. Lượng giá hoạt động tự học</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addSelfStudyRow();">+ Thêm hoạt động</button>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle" id="selfStudyTable">
                <thead>
                    <tr>
                        <th>Hoạt động</th>
                        <th style="width: 12%;">CLOs</th>
                        <th style="width: 10%;">Thời lượng</th>
                        <th>Phương pháp</th>
                        <th>Cách đánh giá</th>
                        <th>Minh chứng</th>
                        <th style="width: 6%;">Xóa</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="section-title">5. Nội dung học phần và phương pháp dạy học</div>
        <div class="sub-section-header">
            <div class="sub-section-title">5.1. Lý thuyết</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addTheoryRow();">+ Thêm bài</button>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle" id="theoryTopicTable">
                <thead>
                    <tr>
                        <th style="width: 10%;">Chương/Bài</th>
                        <th style="width: 12%;">Thuộc chương</th>
                        <th>Nội dung</th>
                        <th style="width: 10%;">Hình thức</th>
                        <th style="width: 12%;">PP dạy học</th>
                        <th style="width: 7%;">Trên lớp</th>
                        <th style="width: 7%;">Online</th>
                        <th style="width: 7%;">Tự học</th>
                        <th style="width: 10%;">CLOs</th>
                        <th style="width: 6%;">Xóa</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="sub-section-header">
            <div class="sub-section-title">5.2. Thực hành</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addPracticalRow();">+ Thêm nội dung</button>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle" id="practicalTopicTable">
                <thead>
                    <tr>
                        <th style="width: 10%;">Chủ đề</th>
                        <th>Nội dung</th>
                        <th style="width: 10%;">Hình thức</th>
                        <th style="width: 12%;">PP dạy học</th>
                        <th style="width: 7%;">Tiết TH</th>
                        <th style="width: 7%;">Online</th>
                        <th style="width: 10%;">CLOs</th>
                        <th style="width: 14%;">Cơ sở TH</th>
                        <th style="width: 6%;">Xóa</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="sub-section-header">
            <div class="sub-section-title">5.3. Tích hợp LT và TH</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addCombinedRow();">+ Thêm chủ đề</button>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle" id="combinedTopicTable">
                <thead>
                    <tr>
                        <th style="width: 6%;">STT</th>
                        <th>Nội dung</th>
                        <th style="width: 10%;">Hình thức</th>
                        <th style="width: 12%;">PP dạy học</th>
                        <th style="width: 6%;">LT</th>
                        <th style="width: 6%;">TH</th>
                        <th style="width: 6%;">Online</th>
                        <th style="width: 6%;">Tự học</th>
                        <th style="width: 10%;">CLOs</th>
                        <th style="width: 12%;">Cơ sở TH</th>
                        <th style="width: 6%;">Xóa</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="section-title">6. Tài liệu dạy và học</div>
        <div class="sub-section-header">
            <div class="sub-section-title">6.1. Tài liệu giảng dạy</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addResourceRow('resourceTeachTable');">+ Thêm tài liệu</button>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle" id="resourceTeachTable">
                <thead>
                    <tr>
                        <th style="width: 6%;">STT</th>
                        <th>Tên tài liệu</th>
                        <th style="width: 16%;">Chủ biên</th>
                        <th style="width: 16%;">Nhà xuất bản</th>
                        <th style="width: 10%;">Năm</th>
                        <th style="width: 14%;">Định danh</th>
                        <th style="width: 6%;">Xóa</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="sub-section-header">
            <div class="sub-section-title">6.2. Tài liệu tự học</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="addResourceRow('resourceSelfTable');">+ Thêm tài liệu</button>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle" id="resourceSelfTable">
                <thead>
                    <tr>
                        <th style="width: 6%;">STT</th>
                        <th>Tên tài liệu</th>
                        <th style="width: 16%;">Chủ biên</th>
                        <th style="width: 16%;">Nhà xuất bản</th>
                        <th style="width: 10%;">Năm</th>
                        <th style="width: 14%;">Định danh</th>
                        <th style="width: 6%;">Xóa</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-success btn-lg">Lưu đề cương</button>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
const programOptions = <?= json_encode($programOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const courseOptions = <?= json_encode($courseOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const lecturerOptions = <?= json_encode($lecturerOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const assessmentToolOptions = <?= json_encode($assessmentToolOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const facilityOptions = <?= json_encode(array_values($facilitiesList), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const booksCatalog = <?= json_encode($booksCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const selectedCourseId = <?= json_encode($selectedCourseId) ?>;
const selectedFramework = <?= json_encode($selectedFramework, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

let theoryRowCounter = 0;
let combinedRowCounter = 0;

function uniqueYears() {
    return [...new Set(programOptions.map(item => item.year))].filter(Boolean).sort().reverse();
}

function initProgramSelectors() {
    const yearSelect = document.getElementById('programYearSelect');
    const majorSelect = document.getElementById('programMajorSelect');

    yearSelect.innerHTML = '<option value="">-- Chọn năm --</option>';
    uniqueYears().forEach(year => {
        const option = document.createElement('option');
        option.value = year;
        option.textContent = year;
        yearSelect.appendChild(option);
    });

    yearSelect.addEventListener('change', () => {
        populateMajorOptions(yearSelect.value);
        populateCourseOptions(yearSelect.value, majorSelect.value);
        resetFrameworkFields();
    });

    majorSelect.addEventListener('change', () => {
        populateCourseOptions(yearSelect.value, majorSelect.value);
        resetFrameworkFields();
    });
}

function populateMajorOptions(year, selectedMajorId = '') {
    const majorSelect = document.getElementById('programMajorSelect');
    const majorMap = new Map();
    majorSelect.innerHTML = '<option value="">-- Chọn ngành --</option>';
    programOptions
        .filter(item => String(item.year) === String(year))
        .forEach(item => {
            if (majorMap.has(String(item.major_id))) {
                return;
            }
            majorMap.set(String(item.major_id), item);
            const option = document.createElement('option');
            option.value = item.major_id;
            option.textContent = item.major_name;
            option.dataset.programId = item.id;
            majorSelect.appendChild(option);
        });
    if (selectedMajorId) {
        majorSelect.value = selectedMajorId;
    }
}

function populateCourseOptions(year, majorId, selectedId = '') {
    const courseSelect = document.getElementById('courseSelect');
    courseSelect.innerHTML = '<option value="">-- Chọn học phần --</option>';
    courseOptions
        .filter(item => String(item.program_year) === String(year) && String(item.major_id) === String(majorId))
        .forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = `${item.code} - ${item.name}`;
            courseSelect.appendChild(option);
        });
    if (selectedId) {
        courseSelect.value = selectedId;
    } else {
        courseSelect.value = '';
    }
}

function resetFrameworkFields() {
    [
        'education_program_id', 'courseName', 'courseCode', 'moduleType', 'moduleTypeDisplay',
        'credits', 'credits_theory', 'credits_practice', 'total_hours', 'theory_hours', 'practical_hours',
        'targetPrograms', 'expectedSemester', 'expectedYear', 'facultyName', 'gradingScale'
    ].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.value = '';
        }
    });
    document.getElementById('prerequisiteText').value = '';
    document.getElementById('parallelText').value = '';
    document.getElementById('previousText').value = '';
    document.getElementById('prerequisiteHidden').innerHTML = '';
    document.getElementById('parallelHidden').innerHTML = '';
    document.getElementById('previousHidden').innerHTML = '';
    $('#coordinatorSelect').val(null).trigger('change');
}

function fillHiddenRelationInputs(containerId, inputName, ids) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    ids.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `${inputName}[]`;
        input.value = id;
        container.appendChild(input);
    });
}

async function loadCourseFramework(courseId) {
    if (!courseId) {
        resetFrameworkFields();
        return;
    }

    let data;
    try {
        const response = await fetch(`get_course.php?id=${encodeURIComponent(courseId)}`);
        data = await response.json();
        if (!response.ok || data.error) {
            throw new Error(data.error || 'Khong the tai du lieu hoc phan.');
        }
    } catch (error) {
        resetFrameworkFields();
        alert(error.message || 'Khong the tai du lieu hoc phan.');
        return;
    }

    document.getElementById('education_program_id').value = data.education_program_id || '';
    document.getElementById('courseName').value = data.name || '';
    document.getElementById('courseCode').value = data.code || '';
    document.getElementById('moduleType').value = data.module_type || '';
    document.getElementById('moduleTypeDisplay').value = data.module_type || '';
    document.getElementById('credits').value = data.credits || 0;
    document.getElementById('credits_theory').value = data.credits_theory || 0;
    document.getElementById('credits_practice').value = data.credits_practice || 0;
    document.getElementById('total_hours').value = data.total_hours || 0;
    document.getElementById('theory_hours').value = data.theory_hours || 0;
    document.getElementById('practical_hours').value = data.practical_hours || 0;
    document.getElementById('targetPrograms').value = data.major_name || '';
    document.getElementById('expectedSemester').value = data.expected_semester || '';
    document.getElementById('expectedYear').value = data.expected_year || '';
    document.getElementById('facultyName').value = data.faculty_name || '';
    document.getElementById('gradingScale').value = data.grading_scale || '';
    document.getElementById('prerequisiteText').value = data.prerequisite_text || '';
    document.getElementById('parallelText').value = data.parallel_text || '';
    document.getElementById('previousText').value = data.previous_text || '';

    fillHiddenRelationInputs('prerequisiteHidden', 'prerequisite_modules', data.prerequisite_ids || []);
    fillHiddenRelationInputs('parallelHidden', 'parallel_modules', data.parallel_ids || []);
    fillHiddenRelationInputs('previousHidden', 'previous_modules', data.previous_ids || []);

    const coordinatorValues = (data.coordinator_names || []).slice();
    ensureTagValues('#coordinatorSelect', coordinatorValues);
    $('#coordinatorSelect').val(coordinatorValues).trigger('change');
}

function ensureTagValues(selector, values) {
    const select = document.querySelector(selector);
    values.forEach(value => {
        if (![...select.options].some(opt => opt.value === value)) {
            const option = new Option(value, value, true, true);
            select.add(option);
        }
    });
}

function deliveryOptions(selected = '') {
    const values = ['Học trên lớp', 'Học trực tuyến', 'Kết hợp'];
    return values.map(value => `<option value="${value}" ${value === selected ? 'selected' : ''}>${value}</option>`).join('');
}

function facilitySelectOptions(selected = '') {
    let options = '<option value="">-- Chọn cơ sở --</option>';
    facilityOptions.forEach(name => {
        const isSelected = name === selected ? 'selected' : '';
        options += `<option value="${escapeHtml(name)}" ${isSelected}>${escapeHtml(name)}</option>`;
    });
    return options;
}

function bookSelectOptions() {
    let options = '<option value="">-- Chọn tài liệu --</option>';
    booksCatalog.forEach(book => {
        options += `<option value="${book.id}" data-editor="${escapeHtml(book.editor || '')}" data-publisher="${escapeHtml(book.publisher || '')}" data-year="${escapeHtml(book.year || '')}" data-identifier="${escapeHtml(book.identifier || '')}">${escapeHtml(book.title || '')}</option>`;
    });
    return options;
}

function escapeHtml(text) {
    return String(text ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function addCloRow() {
    const tbody = document.querySelector('#cloTable tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" class="form-control clo-code" placeholder="CLO1"></td>
        <td><input type="text" class="form-control clo-domain" placeholder="Nhận thức"></td>
        <td><input type="text" class="form-control clo-bloom" placeholder="Mức Bloom"></td>
        <td><input type="text" class="form-control clo-contribution" placeholder="Mức đóng góp"></td>
        <td><input type="text" class="form-control clo-pi-id" placeholder="PI1"></td>
        <td><input type="text" class="form-control clo-plo-pi" placeholder="PLO/PI"></td>
        <td><textarea class="form-control clo-content" rows="2" placeholder="Nội dung CLO"></textarea></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();">Xóa</button></td>
    `;
    tbody.appendChild(tr);
}

function assessmentTypeOptions(selected = '') {
    const values = ['Chuyên cần', 'Kiểm tra thường xuyên', 'Thi kết thúc'];
    return values.map(value => `<option value="${value}" ${value === selected ? 'selected' : ''}>${value}</option>`).join('');
}

function toolOptionsForType(type, selectedValues = []) {
    return assessmentToolOptions
        .filter(item => item.assessment_type === type)
        .map(item => `<option value="${item.id}" ${selectedValues.includes(String(item.id)) ? 'selected' : ''}>${escapeHtml(item.name)}</option>`)
        .join('');
}

function refreshAssessmentToolSelect(selectEl, type) {
    const values = ($(selectEl).val() || []).map(String);
    selectEl.innerHTML = toolOptionsForType(type, values);
    values.forEach(value => {
        if (![...selectEl.options].some(opt => opt.value === value)) {
            selectEl.add(new Option(value, value, true, true));
        }
    });
    $(selectEl).trigger('change.select2');
}

function addAssessmentRow() {
    const tbody = document.querySelector('#assessmentTable tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><select class="form-select assessment-type">${assessmentTypeOptions()}</select></td>
        <td><input type="text" class="form-control assessment-clos" placeholder="CLO1, CLO2"></td>
        <td><input type="text" class="form-control assessment-plo-pi" placeholder="PLO/PI"></td>
        <td><select class="form-select assessment-tools" multiple></select></td>
        <td><input type="number" class="form-control assessment-weight" value="0" min="0" max="100"></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();">Xóa</button></td>
    `;
    tbody.appendChild(tr);
    const typeSelect = tr.querySelector('.assessment-type');
    const toolSelect = tr.querySelector('.assessment-tools');
    refreshAssessmentToolSelect(toolSelect, typeSelect.value);
    $(toolSelect).select2({ width: '100%', tags: true });
    typeSelect.addEventListener('change', () => refreshAssessmentToolSelect(toolSelect, typeSelect.value));
}

function addSelfStudyRow() {
    const tbody = document.querySelector('#selfStudyTable tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><textarea class="form-control ss-activity" rows="2"></textarea></td>
        <td><input type="text" class="form-control ss-clos"></td>
        <td><input type="number" class="form-control ss-hours" value="0" min="0"></td>
        <td><textarea class="form-control ss-method" rows="2"></textarea></td>
        <td><textarea class="form-control ss-assess" rows="2"></textarea></td>
        <td><textarea class="form-control ss-evidence" rows="2"></textarea></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();">Xóa</button></td>
    `;
    tbody.appendChild(tr);
}

function refreshTheoryParentOptions() {
    const rows = [...document.querySelectorAll('#theoryTopicTable tbody tr')];
    const items = rows.map((row, index) => ({
        key: row.dataset.rowKey,
        label: `${index + 1}. ${(row.querySelector('.theory-chapter').value || 'Mục mới')}`
    }));

    rows.forEach(row => {
        const select = row.querySelector('.theory-parent');
        const current = select.value;
        select.innerHTML = '<option value="">-- Gốc --</option>';
        items.forEach(item => {
            if (item.key === row.dataset.rowKey) {
                return;
            }
            const option = document.createElement('option');
            option.value = item.key;
            option.textContent = item.label;
            select.appendChild(option);
        });
        select.value = current;
    });
}

function addTheoryRow() {
    theoryRowCounter += 1;
    const tbody = document.querySelector('#theoryTopicTable tbody');
    const tr = document.createElement('tr');
    tr.dataset.rowKey = `theory_${theoryRowCounter}`;
    tr.innerHTML = `
        <td><input type="text" class="form-control theory-chapter" placeholder="Chương 1"></td>
        <td><select class="form-select theory-parent"></select></td>
        <td><textarea class="form-control theory-title" rows="2"></textarea></td>
        <td><select class="form-select theory-mode">${deliveryOptions()}</select></td>
        <td><input type="text" class="form-control theory-method"></td>
        <td><input type="number" class="form-control theory-class" value="0" min="0"></td>
        <td><input type="number" class="form-control theory-online" value="0" min="0"></td>
        <td><input type="number" class="form-control theory-self" value="0" min="0"></td>
        <td><input type="text" class="form-control theory-clos"></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="removeTheoryRow(this);">Xóa</button></td>
    `;
    tbody.appendChild(tr);
    tr.querySelector('.theory-chapter').addEventListener('input', refreshTheoryParentOptions);
    refreshTheoryParentOptions();
}

function removeTheoryRow(btn) {
    btn.closest('tr').remove();
    refreshTheoryParentOptions();
}

function addPracticalRow() {
    const tbody = document.querySelector('#practicalTopicTable tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" class="form-control practical-topic"></td>
        <td><textarea class="form-control practical-content" rows="2"></textarea></td>
        <td><select class="form-select practical-mode">${deliveryOptions()}</select></td>
        <td><input type="text" class="form-control practical-method"></td>
        <td><input type="number" class="form-control practical-hours" value="0" min="0"></td>
        <td><input type="number" class="form-control practical-online" value="0" min="0"></td>
        <td><input type="text" class="form-control practical-clos"></td>
        <td><select class="form-select practical-facility">${facilitySelectOptions()}</select></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();">Xóa</button></td>
    `;
    tbody.appendChild(tr);
    $(tr.querySelector('.practical-facility')).select2({ width: '100%', tags: true });
}

function addCombinedRow() {
    combinedRowCounter += 1;
    const tbody = document.querySelector('#combinedTopicTable tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="text-center combined-stt"></td>
        <td><textarea class="form-control combined-content" rows="2"></textarea></td>
        <td><select class="form-select combined-mode">${deliveryOptions('Kết hợp')}</select></td>
        <td><input type="text" class="form-control combined-method"></td>
        <td><input type="number" class="form-control combined-lt" value="0" min="0"></td>
        <td><input type="number" class="form-control combined-th" value="0" min="0"></td>
        <td><input type="number" class="form-control combined-online" value="0" min="0"></td>
        <td><input type="number" class="form-control combined-self" value="0" min="0"></td>
        <td><input type="text" class="form-control combined-clos"></td>
        <td><select class="form-select combined-facility">${facilitySelectOptions()}</select></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="removeCombinedRow(this);">Xóa</button></td>
    `;
    tbody.appendChild(tr);
    $(tr.querySelector('.combined-facility')).select2({ width: '100%', tags: true });
    reindexCombinedRows();
}

function removeCombinedRow(btn) {
    btn.closest('tr').remove();
    reindexCombinedRows();
}

function reindexCombinedRows() {
    document.querySelectorAll('#combinedTopicTable tbody tr').forEach((row, index) => {
        row.querySelector('.combined-stt').textContent = index + 1;
    });
}

function addResourceRow(tableId) {
    const tbody = document.querySelector(`#${tableId} tbody`);
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="text-center resource-stt"></td>
        <td><select class="form-select resource-book">${bookSelectOptions()}</select></td>
        <td><input type="text" class="form-control resource-editor" readonly></td>
        <td><input type="text" class="form-control resource-publisher" readonly></td>
        <td><input type="text" class="form-control resource-year" readonly></td>
        <td><input type="text" class="form-control resource-identifier" readonly></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="removeResourceRow('${tableId}', this);">Xóa</button></td>
    `;
    tbody.appendChild(tr);
    const select = tr.querySelector('.resource-book');
    select.addEventListener('change', () => fillBookFields(select));
    $(select).select2({ width: '100%' });
    reindexResourceRows(tableId);
}

function fillBookFields(select) {
    const option = select.options[select.selectedIndex];
    const row = select.closest('tr');
    row.querySelector('.resource-editor').value = option.getAttribute('data-editor') || '';
    row.querySelector('.resource-publisher').value = option.getAttribute('data-publisher') || '';
    row.querySelector('.resource-year').value = option.getAttribute('data-year') || '';
    row.querySelector('.resource-identifier').value = option.getAttribute('data-identifier') || '';
}

function removeResourceRow(tableId, btn) {
    btn.closest('tr').remove();
    reindexResourceRows(tableId);
}

function reindexResourceRows(tableId) {
    document.querySelectorAll(`#${tableId} tbody tr`).forEach((row, index) => {
        row.querySelector('.resource-stt').textContent = index + 1;
    });
}

function gatherJsonData() {
    const clos = [...document.querySelectorAll('#cloTable tbody tr')].map((row, index) => ({
        code: row.querySelector('.clo-code').value.trim() || `CLO${index + 1}`,
        domain: row.querySelector('.clo-domain').value.trim(),
        bloom: row.querySelector('.clo-bloom').value.trim(),
        contribution_level: row.querySelector('.clo-contribution').value.trim(),
        pi_id: row.querySelector('.clo-pi-id').value.trim(),
        plo_pi: row.querySelector('.clo-plo-pi').value.trim(),
        content: row.querySelector('.clo-content').value.trim(),
    })).filter(item => item.code || item.content);
    document.getElementById('clos_json').value = JSON.stringify(clos);

    const assessments = [...document.querySelectorAll('#assessmentTable tbody tr')].map(row => ({
        category: row.querySelector('.assessment-type').value,
        clos: row.querySelector('.assessment-clos').value.trim(),
        plo_pi: row.querySelector('.assessment-plo-pi').value.trim(),
        tools: ($(row.querySelector('.assessment-tools')).val() || []),
        weight: row.querySelector('.assessment-weight').value || 0,
    })).filter(item => item.clos || item.plo_pi || item.tools.length || Number(item.weight) > 0);
    document.getElementById('assessments_json').value = JSON.stringify(assessments);

    const selfStudy = [...document.querySelectorAll('#selfStudyTable tbody tr')].map(row => ({
        name: row.querySelector('.ss-activity').value.trim(),
        clos: row.querySelector('.ss-clos').value.trim(),
        hours: row.querySelector('.ss-hours').value || 0,
        method: row.querySelector('.ss-method').value.trim(),
        assess: row.querySelector('.ss-assess').value.trim(),
        evidence: row.querySelector('.ss-evidence').value.trim(),
    })).filter(item => item.name || item.clos || Number(item.hours) > 0);
    document.getElementById('self_study_json').value = JSON.stringify(selfStudy);

    const theory = [...document.querySelectorAll('#theoryTopicTable tbody tr')].map(row => ({
        row_key: row.dataset.rowKey,
        parent_key: row.querySelector('.theory-parent').value,
        chapter: row.querySelector('.theory-chapter').value.trim(),
        title: row.querySelector('.theory-title').value.trim(),
        delivery_mode: row.querySelector('.theory-mode').value,
        teaching_method: row.querySelector('.theory-method').value.trim(),
        hours_class: row.querySelector('.theory-class').value || 0,
        hours_online: row.querySelector('.theory-online').value || 0,
        hours_self: row.querySelector('.theory-self').value || 0,
        clos: row.querySelector('.theory-clos').value.trim(),
    })).filter(item => item.chapter || item.title);
    document.getElementById('theory_json').value = JSON.stringify(theory);

    const practical = [...document.querySelectorAll('#practicalTopicTable tbody tr')].map(row => ({
        topic: row.querySelector('.practical-topic').value.trim(),
        content: row.querySelector('.practical-content').value.trim(),
        delivery_mode: row.querySelector('.practical-mode').value,
        teaching_method: row.querySelector('.practical-method').value.trim(),
        hours_lab: row.querySelector('.practical-hours').value || 0,
        hours_online: row.querySelector('.practical-online').value || 0,
        clos: row.querySelector('.practical-clos').value.trim(),
        facility: $(row.querySelector('.practical-facility')).val() || '',
    })).filter(item => item.topic || item.content);
    document.getElementById('practical_json').value = JSON.stringify(practical);

    const combined = [...document.querySelectorAll('#combinedTopicTable tbody tr')].map((row, index) => ({
        stt: index + 1,
        content: row.querySelector('.combined-content').value.trim(),
        delivery_mode: row.querySelector('.combined-mode').value,
        teaching_method: row.querySelector('.combined-method').value.trim(),
        hours_theory: row.querySelector('.combined-lt').value || 0,
        hours_practice: row.querySelector('.combined-th').value || 0,
        hours_online: row.querySelector('.combined-online').value || 0,
        hours_self: row.querySelector('.combined-self').value || 0,
        clos: row.querySelector('.combined-clos').value.trim(),
        facility: $(row.querySelector('.combined-facility')).val() || '',
    })).filter(item => item.content);
    document.getElementById('combined_json').value = JSON.stringify(combined);

    const toResources = (selector) => [...document.querySelectorAll(selector)].map(row => {
        const select = row.querySelector('.resource-book');
        const text = select.options[select.selectedIndex]?.text || '';
        const value = select.value;
        return {
            book_id: value,
            title: value ? text : '',
            editor: row.querySelector('.resource-editor').value.trim(),
            publisher: row.querySelector('.resource-publisher').value.trim(),
            year: row.querySelector('.resource-year').value.trim(),
            identifier: row.querySelector('.resource-identifier').value.trim(),
        };
    }).filter(item => item.book_id);

    document.getElementById('res_teach_json').value = JSON.stringify(toResources('#resourceTeachTable tbody tr'));
    document.getElementById('res_self_json').value = JSON.stringify(toResources('#resourceSelfTable tbody tr'));
    return true;
}

$(function () {
    initProgramSelectors();
    $('.select2-multiple').select2({ width: '100%' });

    lecturerOptions.forEach(item => {
        const option = new Option(item.name, item.name, false, false);
        document.getElementById('coordinatorSelect').add(option);
    });
    $('#coordinatorSelect').select2({ width: '100%', tags: true });
    addCloRow();
    addAssessmentRow();
    addSelfStudyRow();
    addTheoryRow();
    addPracticalRow();
    addCombinedRow();
    addResourceRow('resourceTeachTable');
    addResourceRow('resourceSelfTable');

    document.getElementById('courseSelect').addEventListener('change', function () {
        loadCourseFramework(this.value);
    });

    if (selectedFramework) {
        document.getElementById('programYearSelect').value = selectedFramework.program_year || selectedFramework.expected_year || '';
        populateMajorOptions(document.getElementById('programYearSelect').value, selectedFramework.major_id || '');
        populateCourseOptions(document.getElementById('programYearSelect').value, selectedFramework.major_id || '', selectedFramework.id || selectedCourseId);
        loadCourseFramework(selectedFramework.id || selectedCourseId);
    }
});
</script>
</body>
</html>

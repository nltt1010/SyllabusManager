<?php
require 'db.php';
ini_set('display_errors', 1); 
error_reporting(E_ALL);


$id = $_GET['id'] ?? null;
if (!$id) {
    die("Không tìm thấy mã đề cương học phần hợp lệ.");
}

// 1. Lấy thông tin cơ bản của Đề cương học phần (bảng modules)
$stmt = $pdo->prepare("SELECT m.*, c.name as course_name FROM modules m LEFT JOIN courses c ON m.course_id = c.id WHERE m.id = ?");
$stmt->execute([$id]);
$module = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$module) {
    die("Đề cương không tồn tại trên hệ thống.");
}

// 2. Lấy dữ liệu Chuẩn đầu ra (CLOs)
$stmtClo = $pdo->prepare("SELECT * FROM clos WHERE module_id = ? ORDER BY id ASC");
$stmtClo->execute([$id]);
$clos = $stmtClo->fetchAll(PDO::FETCH_ASSOC);

// 3. Lấy dữ liệu Phương thức đánh giá & gom nhóm chuỗi CLO liên quan
$stmtAssess = $pdo->prepare("
    SELECT a.*, GROUP_CONCAT(c.code SEPARATOR ', ') as clos_codes
    FROM assessments a
    LEFT JOIN assessment_clos ac ON a.id = ac.assessment_id
    LEFT JOIN clos c ON ac.clo_id = c.id
    WHERE a.module_id = ?
    GROUP BY a.id ORDER BY a.id ASC
");
$stmtAssess->execute([$id]);
$assessments = $stmtAssess->fetchAll(PDO::FETCH_ASSOC);

// 4. Lấy dữ liệu Hoạt động tự học
$stmtSelfStudy = $pdo->prepare("
    SELECT s.*, GROUP_CONCAT(c.code SEPARATOR ', ') as clos_codes
    FROM self_study_activities s
    LEFT JOIN self_study_clos sc ON s.id = sc.self_study_activity_id
    LEFT JOIN clos c ON sc.clo_id = c.id
    WHERE s.module_id = ?
    GROUP BY s.id
    ORDER BY s.id ASC
");
$stmtSelfStudy->execute([$id]);
$selfStudyActivities = $stmtSelfStudy->fetchAll(PDO::FETCH_ASSOC);

// 5. Lấy tiến độ Lý thuyết
$stmtTheory = $pdo->prepare("
    SELECT t.*, GROUP_CONCAT(c.code SEPARATOR ', ') as clos_codes
    FROM theory_topics t
    LEFT JOIN theory_topic_clos tc ON t.id = tc.theory_topic_id
    LEFT JOIN clos c ON tc.clo_id = c.id
    WHERE t.module_id = ?
    GROUP BY t.id ORDER BY t.id ASC
");
$stmtTheory->execute([$id]);
$theoryTopics = $stmtTheory->fetchAll(PDO::FETCH_ASSOC);

// 6. Lấy tiến độ Thực hành
$stmtPractical = $pdo->prepare("
    SELECT p.*, f.name as facility_name, GROUP_CONCAT(c.code SEPARATOR ', ') as clos_codes
    FROM practical_topics p
    LEFT JOIN practical_topic_clos pc ON p.id = pc.practical_topic_id
    LEFT JOIN clos c ON pc.clo_id = c.id
    LEFT JOIN facilities f ON p.facility_id = f.id
    WHERE p.module_id = ?
    GROUP BY p.id
    ORDER BY p.id ASC
");
$stmtPractical->execute([$id]);
$practicalTopics = $stmtPractical->fetchAll(PDO::FETCH_ASSOC);

// 7. Lấy tiến độ Tích hợp chung (Lý thuyết và Thực hành chung)
$stmtCombined = $pdo->prepare("
    SELECT cb.*, f.name as facility_name, GROUP_CONCAT(c.code SEPARATOR ', ') as clos_codes
    FROM combined_topics cb
    LEFT JOIN combined_topic_clos cbc ON cb.id = cbc.combined_topic_id
    LEFT JOIN clos c ON cbc.clo_id = c.id
    LEFT JOIN facilities f ON cb.facility_id = f.id
    WHERE cb.module_id = ?
    GROUP BY cb.id ORDER BY cb.id ASC
");
$stmtCombined->execute([$id]);
$combinedTopics = $stmtCombined->fetchAll(PDO::FETCH_ASSOC);

// 8. Lấy Tài liệu dạy và học
$stmtRes = $pdo->prepare("SELECT * FROM resources WHERE module_id = ? ORDER BY resource_type ASC, sort_order ASC");
$stmtRes->execute([$id]);
$resources = $stmtRes->fetchAll(PDO::FETCH_ASSOC);

// Fallback an toàn cho các cột mới (chưa migrate DB cũ sẽ không có)
$module['total_hours']      = $module['total_hours']      ?? (($module['theory_hours'] ?? 0) + ($module['practical_hours'] ?? 0));
$module['credits_theory']   = $module['credits_theory']   ?? 0;
$module['credits_practice'] = $module['credits_practice'] ?? 0;

// Lấy danh sách Học phần tiên quyết từ bảng liên kết
$stmt = $pdo->prepare("SELECT GROUP_CONCAT(c.code SEPARATOR ', ') FROM module_relationships mr JOIN courses c ON mr.related_course_id = c.id WHERE mr.module_id = ? AND mr.relation_type = 'Tiên quyết'");
$stmt->execute([$id]);
$module['prerequisite_modules_text'] = $stmt->fetchColumn() ?: ($module['prerequisite_modules'] ?? '');

// Lấy danh sách Học phần song hành
$stmt = $pdo->prepare("SELECT GROUP_CONCAT(c.code SEPARATOR ', ') FROM module_relationships mr JOIN courses c ON mr.related_course_id = c.id WHERE mr.module_id = ? AND mr.relation_type = 'Song hành'");
$stmt->execute([$id]);
$module['parallel_modules_text'] = $stmt->fetchColumn() ?: ($module['parallel_modules'] ?? '');

// Lấy danh sách Học phần học trước
$stmt = $pdo->prepare("SELECT GROUP_CONCAT(c.code SEPARATOR ', ') FROM module_relationships mr JOIN courses c ON mr.related_course_id = c.id WHERE mr.module_id = ? AND mr.relation_type = 'Học trước'");
$stmt->execute([$id]);
$module['previous_modules_text'] = $stmt->fetchColumn() ?: ($module['previous_modules'] ?? '');

// Lấy danh sách Bộ môn
$stmt = $pdo->prepare("SELECT GROUP_CONCAT(d.name SEPARATOR ', ') FROM module_departments md JOIN departments_list d ON md.department_id = d.id WHERE md.module_id = ?");
$stmt->execute([$id]);
$module['department_in_charge_text'] = $stmt->fetchColumn() ?: ($module['department_in_charge'] ?? '');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chi tiết Đề cương học phần: <?= h($module['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; padding-top: 30px; padding-bottom: 50px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .syllabus-container { background: #ffffff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 40px; }
        .main-title { font-weight: 700; color: #1a446c; text-transform: uppercase; margin-bottom: 30px; border-bottom: 3px solid #1a446c; padding-bottom: 10px; }
        .section-title { background: #1a446c; color: #ffffff; padding: 10px 15px; font-weight: 600; text-transform: uppercase; margin-top: 35px; margin-bottom: 20px; border-radius: 4px; }
        .sub-section-header { display: flex; justify-content: space-between; align-items: center; margin-top: 25px; margin-bottom: 15px; border-left: 4px solid #3498db; padding-left: 10px; }
        .sub-section-title { font-weight: 600; color: #2c3e50; margin: 0; }
        .table th { background-color: #f8f9fa; color: #333; font-weight: 600; text-align: center; vertical-align: middle; font-size: 14px; }
        .info-label { font-weight: bold; color: #34495e; }
        .hours-box { display: flex; gap: 0; border: 1px solid #dee2e6; border-radius: 6px; overflow: hidden; }
        .hours-box-item { flex: 1; padding: 6px 10px; text-align: center; border-right: 1px solid #dee2e6; }
        .hours-box-item:last-child { border-right: none; }
        .hours-box-item .label { font-size: 11px; color: #6c757d; display: block; }
        .hours-box-item .value { font-size: 15px; font-weight: 700; color: #1a446c; display: block; }
        .hours-box-item.total { background-color: #f0f4f8; }
    </style>
</head>
<body>

<div class="container syllabus-container">
    <p><a href="list.php">Xem danh sách học phần</a> | <a href="index.php">Thêm mới đề cương</a></p>
    <h2 class="text-center main-title">Chi tiết Đề cương chi tiết học phần</h2>

    <a href="course_detail_export.php?id=<?= $module['id'] ?>" class="btn btn-success">
        Xuất PDF
    </a>

    <div class="section-title">1. THÔNG TIN HỌC PHẦN</div>
    <div class="row g-3">
        <div class="col-md-6"><span class="info-label">Học phần nền:</span> <?= h($module['course_name'] ?? 'Không chọn') ?></div>
        <div class="col-md-6"><span class="info-label">Tên học phần:</span> <?= h($module['name']) ?></div>
        <div class="col-md-6"><span class="info-label">Mã học phần:</span> <?= h($module['code']) ?></div>
        <div class="col-md-6"><span class="info-label">Tính chất học phần:</span> <?= h($module['type']) ?></div>

        <div class="col-md-4">
            <span class="info-label d-block mb-1">Số tín chỉ (Tổng / LT / TH):</span>
            <div class="hours-box">
                <div class="hours-box-item total">
                    <span class="label">Tổng số TC</span>
                    <span class="value"><?= h($module['credits']) ?></span>
                </div>
                <div class="hours-box-item">
                    <span class="label">Lý thuyết</span>
                    <span class="value"><?= h($module['credits_theory']) ?></span>
                </div>
                <div class="hours-box-item">
                    <span class="label">Thực hành</span>
                    <span class="value"><?= h($module['credits_practice']) ?></span>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <span class="info-label d-block mb-1">Phân bổ thời gian tiết (Tổng / LT / TH):</span>
            <div class="hours-box">
                <div class="hours-box-item total">
                    <span class="label">Tổng tiết</span>
                    <span class="value"><?= h($module['total_hours']) ?></span>
                </div>
                <div class="hours-box-item">
                    <span class="label">Lý thuyết</span>
                    <span class="value"><?= h($module['theory_hours']) ?></span>
                </div>
                <div class="hours-box-item">
                    <span class="label">Thực hành</span>
                    <span class="value"><?= h($module['practical_hours']) ?></span>
                </div>
            </div>
        </div>

        <div class="col-md-4"><span class="info-label">Số giờ tự học (tiết):</span> <?= h($module['self_study_hours']) ?> tiết</div>

        <div class="col-md-8"><span class="info-label">Đối tượng người học:</span> <?= h($module['target_programs']) ?></div>
        <div class="col-md-6"><span class="info-label">Học kỳ dự kiến:</span> <?= h($module['expected_semester']) ?></div>
        <div class="col-md-6"><span class="info-label">Năm học dự kiến:</span> <?= h($module['expected_year']) ?></div>

        <div class="col-md-4"><span class="info-label">Học phần tiên quyết:</span> <?= h($module['prerequisite_modules_text']) ?: 'Không' ?></div>
        <div class="col-md-4"><span class="info-label">Học phần song hành:</span> <?= h($module['parallel_modules_text']) ?: 'Không' ?></div>
        <div class="col-md-4"><span class="info-label">Học phần học trước:</span> <?= h($module['previous_modules_text']) ?: 'Không' ?></div>

        <div class="col-md-4"><span class="info-label">Bộ môn phụ trách:</span> <?= h($module['department_in_charge_text']) ?: 'Không' ?></div>
        <div class="col-md-4"><span class="info-label">Ban điều phối học phần:</span> <?= h($module['coordinating_board']) ?></div>
        <div class="col-md-4"><span class="info-label">Khoa phụ trách:</span> <?= h($module['faculty_in_charge']) ?></div>
    </div>

    <div class="section-title">2. MÔ TẢ HỌC PHẦN</div>
    <div class="p-3 bg-light border rounded"><?= nl2br(h($module['description'])) ?></div>

    <div class="section-title">3. MỤC TIÊU VÀ CHUẨN ĐẦU RA HỌC PHẦN</div>
    <div class="sub-section-header"><div class="sub-section-title">3.1. Mục tiêu chung</div></div>
    <div class="p-3 bg-light border rounded mb-3"><?= nl2br(h($module['objectives'])) ?></div>

    <div class="sub-section-header"><div class="sub-section-title">3.2. Chuẩn đầu ra học phần (Bloom)</div></div>
    <table class="table table-bordered align-middle">
        <thead>
            <tr>
                <th style="width: 25%;">Lĩnh vực</th>
                <th style="width: 25%;">Mức độ Bloom Taxonomy</th>
                <th style="width: 10%;">TT</th>
                <th style="width: 40%;">Chuẩn đầu ra đại học</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($clos)): foreach($clos as $c): ?>
                <tr>
                    <td class="text-center">
                        <?php
                        if (!empty($c['domain'])) {
                            // Tách chuỗi theo dấu phẩy và khoảng trắng
                            $domains_arr = preg_split('/,\s*/', $c['domain']);
                            // Lọc các phần tử an toàn qua hàm h() và nối lại bằng thẻ <br>
                            echo implode('<br>', array_map('h', $domains_arr));
                        }
                        ?>
                    </td>

                    <td class="text-center">
                        <?php
                        if (!empty($c['bloom_level'])) {
                            // Tách chuỗi theo dấu phẩy và khoảng trắng
                            $blooms_arr = preg_split('/,\s*/', $c['bloom_level']);
                            // Lọc các phần tử an toàn qua hàm h() và nối lại bằng thẻ <br>
                            echo implode('<br>', array_map('h', $blooms_arr));
                        }
                        ?>
                    </td>
                    <td class="text-center"><?= h($c['code']) ?></td>
                    <td><?= nl2br(h($c['description'])) ?></td>


                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="4" class="text-center text-muted">Chưa cấu hình dữ liệu CLO</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="section-title">4. PHƯƠNG PHÁP KIỂM TRA, LƯỢNG GIÁ HỌC PHẦN</div>
    <div class="sub-section-header"><div class="sub-section-title">4.1. Thang điểm lượng giá</div></div>
    <div class="p-3 bg-light border rounded mb-3"><?= h($module['grading_scale']) ?></div>

    <div class="section">
        <div class="sub-section-header"><div class="sub-section-title">4.2. Phương pháp kiểm tra lượng giá</div></div>
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th style="width: 15%; text-align: center;">CLOs</th>
                    <th style="width: 15%; text-align: center;">PLO/PI liên quan</th>
                    <th style="width: 30%;">Hình thức đánh giá</th>
                    <th style="width: 28%;">Công cụ đánh giá</th>
                    <th style="width: 12%; text-align: center;">Trọng số</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($assessments)): ?>
                    <?php foreach($assessments as $a): ?>
                        <tr>
                            <td class="text-center"><?= h($a['clos_codes'] ?: '---') ?></td>
                            <td class="text-center fw-semibold"><?= h($a['plo_pi']) ?></td>
                            <td><?= h($a['form']) ?></td>
                            <td><?= h($a['tool']) ?></td>
                            <td class="text-center"><?= h($a['weight']) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">Chưa có phương pháp đánh giá nào.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="section">
        <div class="sub-section-header"><div class="sub-section-title">4.3. Lượng giá hoạt động tự học</div></div>
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th style="width: 25%;">Hoạt động tự học</th>
                    <th style="width: 15%;">Mục tiêu/Chuẩn đầu ra liên quan (CLOs)</th>
                    <th style="width: 10%;">Thời lượng (giờ)</th>
                    <th style="width: 20%;">Phương pháp tự học</th>
                    <th style="width: 15%;">Cách thức đánh giá</th>
                    <th style="width: 15%;">Minh chứng</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($selfStudyActivities)): ?>
                    <?php foreach ($selfStudyActivities as $s_act): ?>
                        <tr>
                            <td class="fw-semibold"><?= h($s_act['activity_name']) ?></td>
                            <td class="text-center"><?= h($s_act['clos_codes'] ?: '---') ?></td>
                            <td class="text-center"><?= h($s_act['duration_hours']) ?></td>
                            <td><?= h($s_act['method']) ?></td>
                            <td><?= h($s_act['assessment_method']) ?></td>
                            <td><?= h($s_act['evidence']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">Chưa thiết lập nội dung lượng giá hoạt động tự học.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section-title">5. NỘI DUNG HỌC PHẦN VÀ TIẾN ĐỘ GIẢNG DẠY</div>
    <div class="sub-section-header"><div class="sub-section-title">5.1. Tiến độ bài giảng Lý thuyết</div></div>
    <table class="table table-bordered align-middle">
        <thead>
            <tr>
                <th style="width: 15%;">Chương/Bài</th>
                <th>Nội dung lý thuyết</th>
                <th>Hình thức dạy</th>
                <th style="width: 10%;">Tiết trên lớp</th>
                <th style="width: 10%;">Tiết tự học</th>
                <th>CLOs đạt được</th>
                <th>Tài liệu học tập liên quan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($theoryTopics)): foreach($theoryTopics as $t): ?>
                <tr>
                    <td class="text-center fw-bold"><?= h($t['chapter']) ?></td>
                    <td><?= h($t['title']) ?></td>
                    <td><?= h($t['method']) ?></td>
                    <td class="text-center"><?= h($t['class_hours']) ?></td>
                    <td class="text-center"><?= h($t['self_study_hours']) ?></td>
                    <td class="text-center"><?= h($t['clos_codes']) ?></td>
                    <td><?= h($t['textbook_info']) ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="7" class="text-center text-muted">Chưa thiết lập bài giảng lý thuyết</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="sub-section-header"><div class="sub-section-title">5.2. Tiến độ Thực hành</div></div>
    <table class="table table-bordered align-middle">
        <thead>
            <tr>
                <th style="width: 15%;">Chủ đề</th>
                <th>Nội dung chi tiết/ Kỹ năng</th>
                <th>Hình thức tổ chức</th>
                <th style="width: 10%;">Số tiết TH</th>
                <th>CLOs đạt được</th>
                <th>Cơ sở thực hành</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($practicalTopics)): foreach($practicalTopics as $p): ?>
                <tr>
                    <td class="text-center fw-bold"><?= h($p['topic']) ?></td>
                    <td><?= h($p['content']) ?></td>
                    <td><?= h($p['method']) ?></td>
                    <td class="text-center"><?= h($p['lab_hours']) ?></td>
                    <td class="text-center"><?= h($p['clos_codes']) ?></td>
                    <td><?= h($p['facility_name'] ?? 'Chưa bố trí') ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6" class="text-center text-muted">Chưa thiết lập nội dung thực hành</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="sub-section-header"><div class="sub-section-title">5.3. Lý thuyết & Thực hành tích hợp (chung)</div></div>
    <table class="table table-bordered align-middle">
        <thead>
            <tr>
                <th style="width: 8%;">STT</th>
                <th>Nội dung chính tích hợp</th>
                <th>Hình thức dạy học</th>
                <th style="width: 10%;">Tiết LT</th>
                <th style="width: 10%;">Tiết TH</th>
                <th style="width: 10%;">Tiết tự học</th>
                <th>CLOs đạt được</th>
                <th>Cơ sở thực hành</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($combinedTopics)): $stt=1; foreach($combinedTopics as $cb): ?>
                <tr>
                    <td class="text-center"><?= $stt++ ?></td>
                    <td><?= h($cb['content']) ?></td>
                    <td><?= h($cb['method']) ?></td>
                    <td class="text-center"><?= h($cb['theory_hours']) ?></td>
                    <td class="text-center"><?= h($cb['practical_hours']) ?></td>
                    <td class="text-center"><?= h($cb['self_study_hours']) ?></td>
                    <td class="text-center"><?= h($cb['clos_codes']) ?></td>
                    <td><?= h($cb['facility_name'] ?? 'Chưa bố trí') ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="8" class="text-center text-muted">Chưa cấu hình nội dung tích hợp chung</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="section-title">6. TÀI LIỆU DẠY VÀ HỌC</div>

    <div class="sub-section-header">
        <div class="sub-section-title">6.1. Tài liệu giảng dạy</div>
    </div>
    <table class="table table-bordered align-middle">
        <thead>
            <tr>
                <th style="width: 6%;">STT</th>
                <th style="width: 35%;">Tên giáo trình / Tài liệu</th>
                <th>Chủ biên</th>
                <th>Nhà xuất bản</th>
                <th style="width: 12%;">Năm xuất bản</th>
                <th>Số định danh cá biệt tại thư viện</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $hasTeachRes = false;
            $sttTeach = 1;
            foreach ($resources as $r) {
                // Kiểm tra loại tài liệu dựa vào cơ sở dữ liệu đã lưu ở save.php
                if ($r['resource_type'] === 'Tài liệu giảng dạy') {
                    $hasTeachRes = true;
                    ?>
                    <tr>
                        <td class="text-center"><?= $sttTeach++ ?></td>
                        <td class="fw-bold text-dark"><?= h($r['title']) ?></td>
                        <td><?= h($r['editor']) ?></td>
                        <td><?= h($r['publisher']) ?></td>
                        <td class="text-center"><?= h($r['year']) ?></td>
                        <td class="text-secondary fw-semibold"><?= h($r['identifier']) ?></td>
                    </tr>
                    <?php
                }
            }
            if (!$hasTeachRes): ?>
                <tr><td colspan="6" class="text-center text-muted">Chưa thiết lập danh mục tài liệu giảng dạy</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="sub-section-header">
        <div class="sub-section-title">6.2. Tài liệu tự học</div>
    </div>
    <table class="table table-bordered align-middle">
        <thead>
            <tr>
                <th style="width: 6%;">STT</th>
                <th style="width: 35%;">Tên giáo trình / Tài liệu</th>
                <th>Chủ biên</th>
                <th>Nhà xuất bản</th>
                <th style="width: 12%;">Năm xuất bản</th>
                <th>Số định danh cá biệt tại thư viện</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $hasSelfRes = false;
            $sttSelf = 1;
            foreach ($resources as $r) {
                // Kiểm tra loại tài liệu dựa vào cơ sở dữ liệu đã lưu ở save.php
                if ($r['resource_type'] === 'Tài liệu tự học') {
                    $hasSelfRes = true;
                    ?>
                    <tr>
                        <td class="text-center"><?= $sttSelf++ ?></td>
                        <td class="fw-bold text-dark"><?= h($r['title']) ?></td>
                        <td><?= h($r['editor']) ?></td>
                        <td><?= h($r['publisher']) ?></td>
                        <td class="text-center"><?= h($r['year']) ?></td>
                        <td class="text-secondary fw-semibold"><?= h($r['identifier']) ?></td>
                    </tr>
                    <?php
                }
            }
            if (!$hasSelfRes): ?>
                <tr><td colspan="6" class="text-center text-muted">Chưa thiết lập danh mục tài liệu tự học</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>

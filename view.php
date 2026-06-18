<?php
require 'db.php';

$id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('Không tìm thấy đề cương hợp lệ.');
}

$bundle = syllabus_get_module_bundle($pdo, $id);
if (empty($bundle)) {
    die('Đề cương không tồn tại.');
}

$module = $bundle['module'];
$clos = $bundle['clos'];
$assessments = $bundle['assessments'];
$selfStudyActivities = $bundle['selfStudyActivities'];
$theoryTopics = $bundle['theoryTopics'];
$practicalTopics = $bundle['practicalTopics'];
$combinedTopics = $bundle['combinedTopics'];
$resources = $bundle['resources'];

$teachResources = array_values(array_filter($resources, function ($item) {
    return str_contains(syllabus_slug_text((string)($item['resource_type'] ?? '')), 'giang day');
}));
$selfResources = array_values(array_filter($resources, function ($item) {
    return str_contains(syllabus_slug_text((string)($item['resource_type'] ?? '')), 'tu hoc');
}));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chi tiết đề cương: <?= h($module['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; padding-top: 30px; padding-bottom: 50px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .syllabus-container { background: #ffffff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 40px; }
        .main-title { font-weight: 700; color: #1a446c; text-transform: uppercase; margin-bottom: 30px; border-bottom: 3px solid #1a446c; padding-bottom: 10px; }
        .section-title { background: #1a446c; color: #ffffff; padding: 10px 15px; font-weight: 600; text-transform: uppercase; margin-top: 35px; margin-bottom: 20px; border-radius: 4px; }
        .sub-section-title { font-weight: 600; color: #2c3e50; margin: 20px 0 10px; }
        .table th { background-color: #f8f9fa; color: #333; font-weight: 600; text-align: center; vertical-align: middle; font-size: 14px; }
        .info-label { font-weight: 700; color: #34495e; }
    </style>
</head>
<body>
<div class="container syllabus-container">
    <p><a href="list.php">Danh sách đề cương</a> | <a href="index.php?course_id=<?= h($module['course_id']) ?>">Tạo đề cương mới cho học phần này</a></p>
    <h2 class="text-center main-title">Chi tiết đề cương học phần</h2>

    <a href="course_detail_export.php?id=<?= h($module['id']) ?>" class="btn btn-success">Xuất PDF</a>

    <div class="section-title">1. Thông tin học phần</div>
    <div class="row g-3">
        <div class="col-md-6"><span class="info-label">Học phần nền:</span> <?= h($module['course_name'] ?? '-') ?></div>
        <div class="col-md-3"><span class="info-label">Mã học phần:</span> <?= h($module['code']) ?></div>
        <div class="col-md-3"><span class="info-label">Tính chất:</span> <?= h($module['type']) ?></div>

        <div class="col-md-6"><span class="info-label">Tên học phần:</span> <?= h($module['name']) ?></div>
        <div class="col-md-3"><span class="info-label">Tín chỉ:</span> <?= h($module['credits']) ?> (LT <?= h($module['credits_theory']) ?> / TH <?= h($module['credits_practice']) ?>)</div>
        <div class="col-md-3"><span class="info-label">Tiết:</span> <?= h($module['total_hours']) ?> (LT <?= h($module['theory_hours']) ?> / TH <?= h($module['practical_hours']) ?>)</div>

        <div class="col-md-3"><span class="info-label">Tự học:</span> <?= h($module['self_study_hours']) ?></div>
        <div class="col-md-3"><span class="info-label">Học kỳ:</span> <?= h($module['expected_semester']) ?></div>
        <div class="col-md-3"><span class="info-label">Năm học:</span> <?= h($module['expected_year']) ?></div>
        <div class="col-md-3"><span class="info-label">Hình thức chung:</span> <?= h($module['delivery_mode']) ?></div>

        <div class="col-md-6"><span class="info-label">Đối tượng người học:</span> <?= h($module['target_programs']) ?></div>
        <div class="col-md-6"><span class="info-label">Khoa phụ trách:</span> <?= h($module['faculty_in_charge']) ?></div>

        <div class="col-md-4"><span class="info-label">Học phần tiên quyết:</span> <?= h($module['prerequisite_text'] ?: 'Không') ?></div>
        <div class="col-md-4"><span class="info-label">Học phần song hành:</span> <?= h($module['parallel_text'] ?: 'Không') ?></div>
        <div class="col-md-4"><span class="info-label">Học phần học trước:</span> <?= h($module['previous_text'] ?: 'Không') ?></div>

        <div class="col-md-4"><span class="info-label">Bộ môn tham gia:</span> <?= h($module['department_in_charge_text'] ?: 'Không') ?></div>
        <div class="col-md-8"><span class="info-label">Ban điều phối:</span> <?= h(implode(', ', $module['coordinator_names'] ?? []) ?: ($module['coordinating_board'] ?? '')) ?></div>
    </div>

    <div class="section-title">2. Mô tả học phần</div>
    <div class="p-3 bg-light border rounded"><?= nl2br(h($module['description'])) ?></div>

    <div class="section-title">3. Mục tiêu và chuẩn đầu ra học phần</div>
    <div class="sub-section-title">3.1. Mục tiêu học phần</div>
    <div class="p-3 bg-light border rounded"><?= nl2br(h($module['objectives'])) ?></div>

    <div class="sub-section-title">3.2. CLO</div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th>Mã</th>
                    <th>Lĩnh vực</th>
                    <th>Bloom</th>
                    <th>Mức đóng góp</th>
                    <th>PI ID</th>
                    <th>PLO/PI</th>
                    <th>Nội dung</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($clos)): ?>
                    <?php foreach ($clos as $clo): ?>
                        <tr>
                            <td class="text-center"><?= h($clo['code']) ?></td>
                            <td><?= h($clo['domain']) ?></td>
                            <td><?= h($clo['bloom_level']) ?></td>
                            <td><?= h($clo['contribution_level']) ?></td>
                            <td><?= h($clo['pi_id']) ?></td>
                            <td><?= h($clo['plo_pi']) ?></td>
                            <td><?= nl2br(h($clo['content'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted">Chưa có CLO.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section-title">4. Phương pháp kiểm tra, lượng giá</div>
    <div class="sub-section-title">4.1. Thang điểm lượng giá</div>
    <div class="p-3 bg-light border rounded"><?= nl2br(h($module['grading_scale'])) ?></div>

    <div class="sub-section-title">4.2. Thành phần đánh giá</div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th>Loại đánh giá</th>
                    <th>CLOs</th>
                    <th>PLO/PI</th>
                    <th>Công cụ</th>
                    <th>Trọng số</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($assessments)): ?>
                    <?php foreach ($assessments as $assessment): ?>
                        <tr>
                            <td><?= h($assessment['assessment_category'] ?: $assessment['type']) ?></td>
                            <td><?= h($assessment['clos_codes'] ?: $assessment['clos_text']) ?></td>
                            <td><?= h($assessment['plo_pi']) ?></td>
                            <td><?= h($assessment['tool_names'] ?: $assessment['tool']) ?></td>
                            <td class="text-center"><?= h($assessment['weight']) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center text-muted">Chưa có thành phần đánh giá.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="sub-section-title">4.3. Lượng giá hoạt động tự học</div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th>Hoạt động</th>
                    <th>CLOs</th>
                    <th>Thời lượng</th>
                    <th>Phương pháp</th>
                    <th>Cách đánh giá</th>
                    <th>Minh chứng</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($selfStudyActivities)): ?>
                    <?php foreach ($selfStudyActivities as $item): ?>
                        <tr>
                            <td><?= h($item['activity_name']) ?></td>
                            <td><?= h($item['clos_codes'] ?: $item['clos_text']) ?></td>
                            <td class="text-center"><?= h($item['duration_hours']) ?></td>
                            <td><?= h($item['method']) ?></td>
                            <td><?= h($item['assessment_method']) ?></td>
                            <td><?= h($item['evidence']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted">Chưa có hoạt động tự học.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section-title">5. Nội dung học phần và phương pháp dạy học</div>
    <div class="sub-section-title">5.1. Lý thuyết</div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th>Chương/Bài</th>
                    <th>Thuộc chương</th>
                    <th>Nội dung</th>
                    <th>Hình thức</th>
                    <th>PP dạy học</th>
                    <th>Trên lớp</th>
                    <th>Online</th>
                    <th>Tự học</th>
                    <th>CLOs</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($theoryTopics)): ?>
                    <?php foreach ($theoryTopics as $topic): ?>
                        <tr>
                            <td><?= h($topic['chapter']) ?></td>
                            <td><?= h($topic['parent_title']) ?></td>
                            <td><?= h($topic['title']) ?></td>
                            <td><?= h($topic['method']) ?></td>
                            <td><?= h($topic['teaching_method']) ?></td>
                            <td class="text-center"><?= h($topic['class_hours']) ?></td>
                            <td class="text-center"><?= h($topic['online_hours']) ?></td>
                            <td class="text-center"><?= h($topic['self_study_hours']) ?></td>
                            <td><?= h($topic['clos_codes'] ?: $topic['clos_text']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="text-center text-muted">Chưa có nội dung lý thuyết.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="sub-section-title">5.2. Thực hành</div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th>Chủ đề</th>
                    <th>Nội dung</th>
                    <th>Hình thức</th>
                    <th>PP dạy học</th>
                    <th>Tiết TH</th>
                    <th>Online</th>
                    <th>CLOs</th>
                    <th>Cơ sở TH</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($practicalTopics)): ?>
                    <?php foreach ($practicalTopics as $topic): ?>
                        <tr>
                            <td><?= h($topic['topic']) ?></td>
                            <td><?= h($topic['content']) ?></td>
                            <td><?= h($topic['method']) ?></td>
                            <td><?= h($topic['teaching_method']) ?></td>
                            <td class="text-center"><?= h($topic['lab_hours']) ?></td>
                            <td class="text-center"><?= h($topic['online_hours']) ?></td>
                            <td><?= h($topic['clos_codes'] ?: $topic['clos_text']) ?></td>
                            <td><?= h($topic['facility_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted">Chưa có nội dung thực hành.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="sub-section-title">5.3. Tích hợp LT và TH</div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Nội dung</th>
                    <th>Hình thức</th>
                    <th>PP dạy học</th>
                    <th>LT</th>
                    <th>TH</th>
                    <th>Online</th>
                    <th>Tự học</th>
                    <th>CLOs</th>
                    <th>Cơ sở TH</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($combinedTopics)): ?>
                    <?php foreach ($combinedTopics as $index => $topic): ?>
                        <tr>
                            <td class="text-center"><?= $index + 1 ?></td>
                            <td><?= h($topic['content']) ?></td>
                            <td><?= h($topic['method']) ?></td>
                            <td><?= h($topic['teaching_method']) ?></td>
                            <td class="text-center"><?= h($topic['theory_hours']) ?></td>
                            <td class="text-center"><?= h($topic['practical_hours']) ?></td>
                            <td class="text-center"><?= h($topic['online_hours']) ?></td>
                            <td class="text-center"><?= h($topic['self_study_hours']) ?></td>
                            <td><?= h($topic['clos_codes'] ?: $topic['clos_text']) ?></td>
                            <td><?= h($topic['facility_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="10" class="text-center text-muted">Chưa có nội dung tích hợp.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section-title">6. Tài liệu dạy và học</div>
    <div class="sub-section-title">6.1. Tài liệu giảng dạy</div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Tên tài liệu</th>
                    <th>Chủ biên</th>
                    <th>Nhà xuất bản</th>
                    <th>Năm</th>
                    <th>Định danh</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($teachResources)): ?>
                    <?php foreach ($teachResources as $index => $resource): ?>
                        <tr>
                            <td class="text-center"><?= $index + 1 ?></td>
                            <td><?= h($resource['title']) ?></td>
                            <td><?= h($resource['editor']) ?></td>
                            <td><?= h($resource['publisher']) ?></td>
                            <td class="text-center"><?= h($resource['year']) ?></td>
                            <td><?= h($resource['identifier']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted">Chưa có tài liệu giảng dạy.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="sub-section-title">6.2. Tài liệu tự học</div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Tên tài liệu</th>
                    <th>Chủ biên</th>
                    <th>Nhà xuất bản</th>
                    <th>Năm</th>
                    <th>Định danh</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($selfResources)): ?>
                    <?php foreach ($selfResources as $index => $resource): ?>
                        <tr>
                            <td class="text-center"><?= $index + 1 ?></td>
                            <td><?= h($resource['title']) ?></td>
                            <td><?= h($resource['editor']) ?></td>
                            <td><?= h($resource['publisher']) ?></td>
                            <td class="text-center"><?= h($resource['year']) ?></td>
                            <td><?= h($resource['identifier']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted">Chưa có tài liệu tự học.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>

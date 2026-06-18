<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

$majorId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
if ($majorId <= 0) {
    die('Không tìm thấy mã ngành hợp lệ.');
}

$stmt = $pdo->prepare('SELECT * FROM majors WHERE id = ? LIMIT 1');
$stmt->execute([$majorId]);
$major = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$major) {
    die('Ngành không tồn tại.');
}

$stmt = $pdo->prepare("
    SELECT DISTINCT m.id
    FROM modules m
    INNER JOIN courses c ON c.id = m.course_id
    LEFT JOIN education_programs ep ON ep.id = c.education_program_id
    WHERE COALESCE(ep.major_id, c.major_id) = ?
    ORDER BY c.sort_order ASC, c.code ASC, m.id ASC
");
$stmt->execute([$majorId]);
$moduleIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

function major_pdf_text(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

ob_start();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: "timesnewroman", Times, serif; font-size: 11pt; line-height: 1.35; color: #000; }
        .cover { text-align: center; padding-top: 120px; page-break-after: always; }
        .cover h1 { font-size: 18pt; margin-bottom: 18px; text-transform: uppercase; }
        .cover h2 { font-size: 16pt; margin-bottom: 12px; text-transform: uppercase; }
        h1 { font-size: 13pt; margin: 18px 0 10px; text-transform: uppercase; }
        h2 { font-size: 11pt; margin: 12px 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; table-layout: fixed; }
        th, td { border: 1px solid #000; padding: 5px; vertical-align: top; }
        th { background: #f2f2f2; text-align: center; }
        .info td { border: none; padding: 4px 0; }
        .module-title { text-align: center; font-weight: bold; font-size: 14pt; margin-bottom: 16px; text-transform: uppercase; }
        .center { text-align: center; }
        .page-break { page-break-after: always; }
        .indent { text-indent: 28px; }
    </style>
</head>
<body>
    <div class="cover">
        <h1>Quyển Đề Cương Chi Tiết</h1>
        <h2>Các học phần ngành <?= major_pdf_text($major['name']) ?></h2>
        <div>Cần Thơ, năm <?= date('Y') ?></div>
    </div>

    <?php if (empty($moduleIds)): ?>
        <p>Chưa có đề cương nào thuộc ngành này.</p>
    <?php endif; ?>

    <?php foreach ($moduleIds as $index => $moduleId): ?>
        <?php
        $bundle = syllabus_get_module_bundle($pdo, $moduleId);
        if (empty($bundle)) {
            continue;
        }
        $module = $bundle['module'];
        $clos = $bundle['clos'];
        $assessments = $bundle['assessments'];
        $selfStudyActivities = $bundle['selfStudyActivities'];
        $theoryTopics = $bundle['theoryTopics'];
        $practicalTopics = $bundle['practicalTopics'];
        $combinedTopics = $bundle['combinedTopics'];
        $resources = $bundle['resources'];
        $teachResources = array_values(array_filter($resources, fn($item) => str_contains(syllabus_slug_text((string)($item['resource_type'] ?? '')), 'giang day')));
        $selfResources = array_values(array_filter($resources, fn($item) => str_contains(syllabus_slug_text((string)($item['resource_type'] ?? '')), 'tu hoc')));
        ?>
        <div class="module-title">Đề cương chi tiết học phần<br><?= major_pdf_text(mb_strtoupper($module['name'], 'UTF-8')) ?></div>

        <h1>1. Thông tin học phần</h1>
        <table class="info">
            <tr><td>Học phần nền: <?= major_pdf_text($module['course_name'] ?? '-') ?></td></tr>
            <tr><td>Mã học phần: <?= major_pdf_text($module['code']) ?></td></tr>
            <tr><td>Tính chất học phần: <?= major_pdf_text($module['type']) ?></td></tr>
            <tr><td>Tổng tín chỉ: <?= major_pdf_text((string)$module['credits']) ?> (LT <?= major_pdf_text((string)$module['credits_theory']) ?> / TH <?= major_pdf_text((string)$module['credits_practice']) ?>)</td></tr>
            <tr><td>Phân bổ thời gian: <?= major_pdf_text((string)$module['total_hours']) ?> (LT <?= major_pdf_text((string)$module['theory_hours']) ?> / TH <?= major_pdf_text((string)$module['practical_hours']) ?>)</td></tr>
            <tr><td>Số giờ tự học: <?= major_pdf_text((string)$module['self_study_hours']) ?></td></tr>
            <tr><td>Đối tượng người học: <?= major_pdf_text($module['target_programs']) ?></td></tr>
            <tr><td>Học kỳ / Năm học: <?= major_pdf_text($module['expected_semester']) ?> / <?= major_pdf_text($module['expected_year']) ?></td></tr>
            <tr><td>Học phần tiên quyết: <?= major_pdf_text($module['prerequisite_text'] ?: 'Không') ?></td></tr>
            <tr><td>Học phần song hành: <?= major_pdf_text($module['parallel_text'] ?: 'Không') ?></td></tr>
            <tr><td>Học phần học trước: <?= major_pdf_text($module['previous_text'] ?: 'Không') ?></td></tr>
            <tr><td>Bộ môn tham gia: <?= major_pdf_text($module['department_in_charge_text'] ?: 'Không') ?></td></tr>
            <tr><td>Ban điều phối: <?= major_pdf_text(implode(', ', $module['coordinator_names'] ?? []) ?: ($module['coordinating_board'] ?? '')) ?></td></tr>
            <tr><td>Khoa phụ trách: <?= major_pdf_text($module['faculty_in_charge']) ?></td></tr>
            <tr><td>Hình thức giảng dạy chung: <?= major_pdf_text($module['delivery_mode']) ?></td></tr>
        </table>

        <h1>2. Mô tả học phần</h1>
        <p class="indent"><?= nl2br(major_pdf_text($module['description'])) ?></p>

        <h1>3. Mục tiêu và chuẩn đầu ra học phần</h1>
        <h2>3.1. Mục tiêu học phần</h2>
        <p class="indent"><?= nl2br(major_pdf_text($module['objectives'])) ?></p>
        <h2>3.2. CLO</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Mã</th>
                    <th style="width: 12%;">Lĩnh vực</th>
                    <th style="width: 12%;">Bloom</th>
                    <th style="width: 12%;">Đóng góp</th>
                    <th style="width: 10%;">PI ID</th>
                    <th style="width: 14%;">PLO/PI</th>
                    <th>Nội dung</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($clos)): ?>
                    <?php foreach ($clos as $clo): ?>
                        <tr>
                            <td class="center"><?= major_pdf_text($clo['code']) ?></td>
                            <td><?= major_pdf_text($clo['domain']) ?></td>
                            <td><?= major_pdf_text($clo['bloom_level']) ?></td>
                            <td><?= major_pdf_text($clo['contribution_level']) ?></td>
                            <td><?= major_pdf_text($clo['pi_id']) ?></td>
                            <td><?= major_pdf_text($clo['plo_pi']) ?></td>
                            <td><?= nl2br(major_pdf_text($clo['content'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="center">Chưa có CLO.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h1>4. Phương pháp kiểm tra, lượng giá</h1>
        <h2>4.1. Thang điểm lượng giá</h2>
        <p class="indent"><?= nl2br(major_pdf_text($module['grading_scale'])) ?></p>

        <h2>4.2. Thành phần đánh giá</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 18%;">Loại</th>
                    <th style="width: 18%;">CLOs</th>
                    <th style="width: 16%;">PLO/PI</th>
                    <th>Công cụ</th>
                    <th style="width: 10%;">Trọng số</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($assessments)): ?>
                    <?php foreach ($assessments as $assessment): ?>
                        <tr>
                            <td><?= major_pdf_text($assessment['assessment_category'] ?: $assessment['type']) ?></td>
                            <td><?= major_pdf_text($assessment['clos_codes'] ?: $assessment['clos_text']) ?></td>
                            <td><?= major_pdf_text($assessment['plo_pi']) ?></td>
                            <td><?= major_pdf_text($assessment['tool_names'] ?: $assessment['tool']) ?></td>
                            <td class="center"><?= major_pdf_text((string)$assessment['weight']) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="center">Chưa có thành phần đánh giá.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h2>4.3. Lượng giá hoạt động tự học</h2>
        <table>
            <thead>
                <tr>
                    <th>Hoạt động</th>
                    <th style="width: 16%;">CLOs</th>
                    <th style="width: 10%;">Thời lượng</th>
                    <th style="width: 18%;">Phương pháp</th>
                    <th style="width: 18%;">Cách đánh giá</th>
                    <th style="width: 18%;">Minh chứng</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($selfStudyActivities)): ?>
                    <?php foreach ($selfStudyActivities as $item): ?>
                        <tr>
                            <td><?= major_pdf_text($item['activity_name']) ?></td>
                            <td><?= major_pdf_text($item['clos_codes'] ?: $item['clos_text']) ?></td>
                            <td class="center"><?= major_pdf_text((string)$item['duration_hours']) ?></td>
                            <td><?= major_pdf_text($item['method']) ?></td>
                            <td><?= major_pdf_text($item['assessment_method']) ?></td>
                            <td><?= major_pdf_text($item['evidence']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="center">Chưa có hoạt động tự học.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h1>5. Nội dung học phần và phương pháp dạy học</h1>
        <h2>5.1. Lý thuyết</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Chương/Bài</th>
                    <th style="width: 12%;">Thuộc chương</th>
                    <th>Nội dung</th>
                    <th style="width: 10%;">Hình thức</th>
                    <th style="width: 12%;">PP dạy học</th>
                    <th style="width: 8%;">Lớp</th>
                    <th style="width: 8%;">Online</th>
                    <th style="width: 8%;">Tự học</th>
                    <th style="width: 10%;">CLOs</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($theoryTopics)): ?>
                    <?php foreach ($theoryTopics as $topic): ?>
                        <tr>
                            <td><?= major_pdf_text($topic['chapter']) ?></td>
                            <td><?= major_pdf_text($topic['parent_title']) ?></td>
                            <td><?= major_pdf_text($topic['title']) ?></td>
                            <td><?= major_pdf_text($topic['method']) ?></td>
                            <td><?= major_pdf_text($topic['teaching_method']) ?></td>
                            <td class="center"><?= major_pdf_text((string)$topic['class_hours']) ?></td>
                            <td class="center"><?= major_pdf_text((string)$topic['online_hours']) ?></td>
                            <td class="center"><?= major_pdf_text((string)$topic['self_study_hours']) ?></td>
                            <td><?= major_pdf_text($topic['clos_codes'] ?: $topic['clos_text']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="center">Chưa có nội dung lý thuyết.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h2>5.2. Thực hành</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 12%;">Chủ đề</th>
                    <th>Nội dung</th>
                    <th style="width: 10%;">Hình thức</th>
                    <th style="width: 12%;">PP dạy học</th>
                    <th style="width: 8%;">TH</th>
                    <th style="width: 8%;">Online</th>
                    <th style="width: 12%;">CLOs</th>
                    <th style="width: 16%;">Cơ sở TH</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($practicalTopics)): ?>
                    <?php foreach ($practicalTopics as $topic): ?>
                        <tr>
                            <td><?= major_pdf_text($topic['topic']) ?></td>
                            <td><?= major_pdf_text($topic['content']) ?></td>
                            <td><?= major_pdf_text($topic['method']) ?></td>
                            <td><?= major_pdf_text($topic['teaching_method']) ?></td>
                            <td class="center"><?= major_pdf_text((string)$topic['lab_hours']) ?></td>
                            <td class="center"><?= major_pdf_text((string)$topic['online_hours']) ?></td>
                            <td><?= major_pdf_text($topic['clos_codes'] ?: $topic['clos_text']) ?></td>
                            <td><?= major_pdf_text($topic['facility_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="center">Chưa có nội dung thực hành.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h2>5.3. Tích hợp LT và TH</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 6%;">STT</th>
                    <th>Nội dung</th>
                    <th style="width: 10%;">Hình thức</th>
                    <th style="width: 12%;">PP dạy học</th>
                    <th style="width: 7%;">LT</th>
                    <th style="width: 7%;">TH</th>
                    <th style="width: 7%;">Online</th>
                    <th style="width: 7%;">Tự học</th>
                    <th style="width: 10%;">CLOs</th>
                    <th style="width: 14%;">Cơ sở TH</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($combinedTopics)): ?>
                    <?php foreach ($combinedTopics as $topicIndex => $topic): ?>
                        <tr>
                            <td class="center"><?= $topicIndex + 1 ?></td>
                            <td><?= major_pdf_text($topic['content']) ?></td>
                            <td><?= major_pdf_text($topic['method']) ?></td>
                            <td><?= major_pdf_text($topic['teaching_method']) ?></td>
                            <td class="center"><?= major_pdf_text((string)$topic['theory_hours']) ?></td>
                            <td class="center"><?= major_pdf_text((string)$topic['practical_hours']) ?></td>
                            <td class="center"><?= major_pdf_text((string)$topic['online_hours']) ?></td>
                            <td class="center"><?= major_pdf_text((string)$topic['self_study_hours']) ?></td>
                            <td><?= major_pdf_text($topic['clos_codes'] ?: $topic['clos_text']) ?></td>
                            <td><?= major_pdf_text($topic['facility_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="10" class="center">Chưa có nội dung tích hợp.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h1>6. Tài liệu dạy và học</h1>
        <h2>6.1. Tài liệu giảng dạy</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 6%;">STT</th>
                    <th>Tên tài liệu</th>
                    <th style="width: 16%;">Chủ biên</th>
                    <th style="width: 16%;">Nhà xuất bản</th>
                    <th style="width: 10%;">Nam</th>
                    <th style="width: 16%;">Định danh</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($teachResources)): ?>
                    <?php foreach ($teachResources as $resourceIndex => $resource): ?>
                        <tr>
                            <td class="center"><?= $resourceIndex + 1 ?></td>
                            <td><?= major_pdf_text($resource['title']) ?></td>
                            <td><?= major_pdf_text($resource['editor']) ?></td>
                            <td><?= major_pdf_text($resource['publisher']) ?></td>
                            <td class="center"><?= major_pdf_text($resource['year']) ?></td>
                            <td><?= major_pdf_text($resource['identifier']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="center">Chưa có tài liệu giảng dạy.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h2>6.2. Tài liệu tự học</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 6%;">STT</th>
                    <th>Tên tài liệu</th>
                    <th style="width: 16%;">Chủ biên</th>
                    <th style="width: 16%;">Nhà xuất bản</th>
                    <th style="width: 10%;">Nam</th>
                    <th style="width: 16%;">Định danh</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($selfResources)): ?>
                    <?php foreach ($selfResources as $resourceIndex => $resource): ?>
                        <tr>
                            <td class="center"><?= $resourceIndex + 1 ?></td>
                            <td><?= major_pdf_text($resource['title']) ?></td>
                            <td><?= major_pdf_text($resource['editor']) ?></td>
                            <td><?= major_pdf_text($resource['publisher']) ?></td>
                            <td class="center"><?= major_pdf_text($resource['year']) ?></td>
                            <td><?= major_pdf_text($resource['identifier']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="center">Chưa có tài liệu tự học.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($index < count($moduleIds) - 1): ?>
            <div class="page-break"></div>
        <?php endif; ?>
    <?php endforeach; ?>
</body>
</html>
<?php
$html = ob_get_clean();

$safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', syllabus_slug_text((string)$major['name']));
$filename = 'Quyen_De_Cuong_Nganh_' . trim($safeName, '_') . '.pdf';

$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_top' => 20,
    'margin_bottom' => 20,
    'margin_left' => 20,
    'margin_right' => 20,
    'default_font' => 'timesnewroman',
]);

$mpdf->SetTitle('Quyển đề cương ngành ' . ($major['name'] ?? ''));
$mpdf->WriteHTML($html);
$mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);

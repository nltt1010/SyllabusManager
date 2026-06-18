<?php

mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

// Nâng hạn mức tài nguyên tối đa để gộp lượng dữ liệu lớn toàn ngành
@ini_set('memory_limit', '1024M');
@set_time_limit(300);

// Tắt hiển thị lỗi ẩn để tránh làm hỏng tệp tin PDF nhị phân đầu ra
error_reporting(0);
ini_set('display_errors', 0);

// =====================================================================
// 1. CÁC HÀM TIỆN ÍCH & TRUY VẤN DỮ LIỆU ĐỒNG BỘ COURSE_DETAIL
// =====================================================================
function s(?string $value): string {
    return trim((string)($value ?? ''));
}

function upper(string $value): string {
    return mb_strtoupper($value, 'UTF-8');
}

function fetchModuleDetailsForMajor(PDO $pdo, int $moduleId): array {
    $stmt = $pdo->prepare("
        SELECT m.*, c.name AS course_name, c.code AS course_code
        FROM modules m 
        LEFT JOIN courses c ON m.course_id = c.id 
        WHERE m.id = ?
    ");
    $stmt->execute([$moduleId]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$module) return [];

    $module['total_hours']      = $module['total_hours'] ?? (($module['theory_hours'] ?? 0) + ($module['practical_hours'] ?? 0));
    $module['credits_theory']   = $module['credits_theory'] ?? 0;
    $module['credits_practice'] = $module['credits_practice'] ?? 0;

    // Lấy Học phần tiên quyết
    $stmt = $pdo->prepare("SELECT GROUP_CONCAT(c.code SEPARATOR ', ') FROM module_relationships mr JOIN courses c ON mr.related_course_id = c.id WHERE mr.module_id = ? AND mr.relation_type = 'Tiên quyết'");
    $stmt->execute([$moduleId]);
    $module['prerequisite_modules_text'] = $stmt->fetchColumn() ?: s($module['prerequisite_modules']);

    // Lấy Học phần song hành
    $stmt = $pdo->prepare("SELECT GROUP_CONCAT(c.code SEPARATOR ', ') FROM module_relationships mr JOIN courses c ON mr.related_course_id = c.id WHERE mr.module_id = ? AND mr.relation_type = 'Song hành'");
    $stmt->execute([$moduleId]);
    $module['parallel_modules_text'] = $stmt->fetchColumn() ?: s($module['parallel_modules']);

    // Lấy Học phần học trước
    $stmt = $pdo->prepare("SELECT GROUP_CONCAT(c.code SEPARATOR ', ') FROM module_relationships mr JOIN courses c ON mr.related_course_id = c.id WHERE mr.module_id = ? AND mr.relation_type = 'Học trước'");
    $stmt->execute([$moduleId]);
    $module['previous_modules_text'] = $stmt->fetchColumn() ?: s($module['previous_modules']);

    // Lấy Bộ môn
    $stmt = $pdo->prepare("SELECT GROUP_CONCAT(d.name SEPARATOR ', ') FROM module_departments md JOIN departments_list d ON md.department_id = d.id WHERE md.module_id = ?");
    $stmt->execute([$moduleId]);
    $module['department_in_charge_text'] = $stmt->fetchColumn() ?: s($module['department_in_charge']);

    // Chuẩn đầu ra CLO
    $stmt = $pdo->prepare("SELECT * FROM clos WHERE module_id = ? ORDER BY id ASC");
    $stmt->execute([$moduleId]);
    $clos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Phương pháp đánh giá
    $stmt = $pdo->prepare("SELECT a.*, GROUP_CONCAT(c.code SEPARATOR ', ') AS clos_codes FROM assessments a LEFT JOIN assessment_clos ac ON a.id = ac.assessment_id LEFT JOIN clos c ON ac.clo_id = c.id WHERE a.module_id = ? GROUP BY a.id ORDER BY a.id ASC");
    $stmt->execute([$moduleId]);
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Hoạt động tự học
    $stmt = $pdo->prepare("SELECT s.*, GROUP_CONCAT(c.code SEPARATOR ', ') AS clos_codes FROM self_study_activities s LEFT JOIN self_study_clos sc ON s.id = sc.self_study_activity_id LEFT JOIN clos c ON sc.clo_id = c.id WHERE s.module_id = ? GROUP BY s.id ORDER BY s.id ASC");
    $stmt->execute([$moduleId]);
    $selfStudyActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tiến độ Lý thuyết
    $stmt = $pdo->prepare("SELECT t.*, GROUP_CONCAT(c.code SEPARATOR ', ') AS clos_codes FROM theory_topics t LEFT JOIN theory_topic_clos tc ON t.id = tc.theory_topic_id LEFT JOIN clos c ON tc.clo_id = c.id WHERE t.module_id = ? GROUP BY t.id ORDER BY t.id ASC");
    $stmt->execute([$moduleId]);
    $theoryTopics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tiến độ Thực hành
    $stmt = $pdo->prepare("SELECT p.*, f.name AS facility_name, GROUP_CONCAT(c.code SEPARATOR ', ') AS clos_codes FROM practical_topics p LEFT JOIN practical_topic_clos pc ON p.id = pc.practical_topic_id LEFT JOIN clos c ON pc.clo_id = c.id LEFT JOIN facilities f ON p.facility_id = f.id WHERE p.module_id = ? GROUP BY p.id ORDER BY p.id ASC");
    $stmt->execute([$moduleId]);
    $practicalTopics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tiến độ Tích hợp
    $stmt = $pdo->prepare("SELECT cb.*, f.name AS facility_name, GROUP_CONCAT(c.code SEPARATOR ', ') AS clos_codes FROM combined_topics cb LEFT JOIN combined_topic_clos cbc ON cb.id = cbc.combined_topic_id LEFT JOIN clos c ON cbc.clo_id = c.id LEFT JOIN facilities f ON cb.facility_id = f.id WHERE cb.module_id = ? GROUP BY cb.id ORDER BY cb.id ASC");
    $stmt->execute([$moduleId]);
    $combinedTopics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tài liệu dạy học
    $stmt = $pdo->prepare("SELECT * FROM resources WHERE module_id = ? ORDER BY resource_type ASC, sort_order ASC");
    $stmt->execute([$moduleId]);
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return compact('module', 'clos', 'assessments', 'theoryTopics', 'practicalTopics', 'combinedTopics', 'selfStudyActivities', 'resources');
}

// Lấy mã ngành học cần kết xuất
$majorId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$majorId) {
    die("Không tìm thấy mã ngành đào tạo hợp lệ.");
}

$stmt = $pdo->prepare('SELECT * FROM majors WHERE id = ?');
$stmt->execute([$majorId]);
$major = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$major) {
    die("Ngành đào tạo không tồn tại.");
}

// Lấy sơ đồ cây danh mục khối kiến thức
$stmt = $pdo->prepare('SELECT id, name, parent_id FROM knowledge_blocks WHERE major_id = ? ORDER BY id ASC');
$stmt->execute([$majorId]);
$blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách học phần thuộc ngành
$stmt = $pdo->prepare('
    SELECT m.id AS module_id, m.type AS module_type, c.name AS course_name, c.code AS course_code, c.block_id
    FROM modules m 
    INNER JOIN courses c ON m.course_id = c.id 
    WHERE c.major_id = ? 
    ORDER BY c.sort_order ASC, c.code ASC
');
$stmt->execute([$majorId]);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Thuật toán dựng cấu trúc cây khối kiến thức phẳng
$indexedBlocks = [];
foreach ($blocks as $b) {
    // Đã bổ sung 'Điều kiện' vào danh sách khởi tạo
    $b['modules'] = ['Bắt buộc' => [], 'Điều kiện' => [], 'Tự chọn' => []];
    $indexedBlocks[(int)$b['id']] = $b;
}


$unassigned = ['Bắt buộc' => [], 'Điều kiện' => [], 'Tự chọn' => []];
foreach ($modules as $m) {
    $bId = $m['block_id'] !== null ? (int)$m['block_id'] : 0;
    
    $type = in_array($m['module_type'], ['Bắt buộc', 'Điều kiện', 'Tự chọn']) ? $m['module_type'] : 'Tự chọn';
    
    if ($bId > 0 && isset($indexedBlocks[$bId])) {
        $indexedBlocks[$bId]['modules'][$type][] = $m;
    } else {
        $unassigned[$type][] = $m;
    }
}

$tree = [];
foreach ($indexedBlocks as $id => $block) {
    $pId = $block['parent_id'] !== null ? (int)$block['parent_id'] : 0;
    if ($pId > 0 && isset($indexedBlocks[$pId])) {
        $indexedBlocks[$pId]['children'][] = $id;
    } else {
        $tree[] = $id;
    }
}

$renderPlan = [];
$traverse = function($blockIds, $currentDepth = 1, $prefix = '') use (&$traverse, &$indexedBlocks, &$renderPlan) {
    $index = 1;
    foreach ($blockIds as $id) {
        $block = $indexedBlocks[$id];
        $currPrefix = ($prefix === '') ? (string)$index : $prefix . '.' . $index;
        
        $renderPlan[] = ['kind' => 'title', 'text' => $currPrefix . '. ' . s($block['name']), 'level' => $currentDepth];

        if (!empty($block['modules']['Bắt buộc'])) {
            $renderPlan[] = ['kind' => 'subtitle', 'text' => '*Nhóm học phần bắt buộc', 'level' => $currentDepth + 1];
            foreach ($block['modules']['Bắt buộc'] as $m) {
                $renderPlan[] = ['kind' => 'module', 'id' => (int)$m['module_id'], 'name' => s($m['course_name'])];
            }
        }
        if (!empty($block['modules']['Điều kiện'])) {
            $renderPlan[] = ['kind' => 'subtitle', 'text' => '*Nhóm học phần điều kiện', 'level' => $currentDepth + 1];
            foreach ($block['modules']['Điều kiện'] as $m) {
                $renderPlan[] = ['kind' => 'module', 'id' => (int)$m['module_id'], 'name' => s($m['course_name'])];
            }
        }
        if (!empty($block['modules']['Tự chọn'])) {
            $renderPlan[] = ['kind' => 'subtitle', 'text' => '*Nhóm học phần tự chọn', 'level' => $currentDepth + 1];
            foreach ($block['modules']['Tự chọn'] as $m) {
                $renderPlan[] = ['kind' => 'module', 'id' => (int)$m['module_id'], 'name' => s($m['course_name'])];
            }
        }

        if (!empty($block['children'])) {
            $traverse($block['children'], $currentDepth + 1, $currPrefix);
        }
        $index++;
    }
};
$traverse($tree, 1, '');

if (!empty($unassigned['Bắt buộc']) || !empty($unassigned['Tự chọn'])) {
    $renderPlan[] = ['kind' => 'title', 'text' => 'Học phần chưa phân phối khối kiến thức', 'level' => 1];
    if (!empty($unassigned['Bắt buộc'])) {
        $renderPlan[] = ['kind' => 'subtitle', 'text' => '*Nhóm học phần bắt buộc', 'level' => 2];
        foreach ($unassigned['Bắt buộc'] as $m) {
            $renderPlan[] = ['kind' => 'module', 'id' => (int)$m['module_id'], 'name' => s($m['course_name'])];
        }
    }
    if (!empty($unassigned['Điều kiện'])) {
        $renderPlan[] = ['kind' => 'subtitle', 'text' => '*Nhóm học phần điều kiện', 'level' => 2];
        foreach ($unassigned['Điều kiện'] as $m) {
            $renderPlan[] = ['kind' => 'module', 'id' => (int)$m['module_id'], 'name' => s($m['course_name'])];
        }
    }
    if (!empty($unassigned['Tự chọn'])) {
        $renderPlan[] = ['kind' => 'subtitle', 'text' => '*Nhóm học phần tự chọn', 'level' => 2];
        foreach ($unassigned['Tự chọn'] as $m) {
            $renderPlan[] = ['kind' => 'module', 'id' => (int)$m['module_id'], 'name' => s($m['course_name'])];
        }
    }
}

// ====================s=================================================
// 2. DỰNG LAYOUT VĂN BẢN HÀNH CHÍNH HTML & CSS (ĐỒNG BỘ COURSE_DETAIL)
// =====================================================================
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
    body { font-family: "timesnewroman", Times, serif; font-size: 12pt; line-height: 1.4; color: #000; }
    
    /* KHỬ BORDER TUYỆT ĐỐI CHO BẢNG HEADER TRANG BÌA */
    .cover-header-table { 
        width: 100%; 
        border-collapse: collapse !important; 
        border: none !important;
        margin-top: 0px; 
    }
    .cover-header-table tr, .cover-header-table td {
        border: none !important;
        padding: 0 !important;
    }
    .cover-ministry { font-size: 16pt; font-weight: bold; margin-bottom: 5px; text-align: center; }
    .cover-school { font-size: 16pt; font-weight: bold; text-transform: uppercase; text-align: center; }
    .cover-line { width: 180px; border-bottom: 2px solid #000; margin: 10px auto 0 auto; }
    
    /* KHỐI NỘI DUNG CHÍNH Ở GIỮA TRANG BÌA */
    .cover-center-content {
        text-align: center;
        width: 100%;
    }
    .cover-main-title { 
        font-size: 15pt; 
        font-weight: bold; 
        text-transform: uppercase; 
        line-height: 1.5;
        margin-bottom: 25px; /* Gộp sát với khối thông tin ngành */
        text-align: center;
    }
    .cover-info-block { 
        font-size: 15pt; 
        font-weight: bold; 
        text-align: center; 
        line-height: 2.2; 
    }
    
    /* KHỐI CHÂN TRANG SÁT ĐÁY BÌA */
    .cover-footer { 
        font-size: 13pt; 
        font-weight: bold; 
        text-transform: uppercase;
        text-align: center;
    }

    /* Bố cục tiêu đề cơ bản trong học phần (Giống hệt course_detail_export) */
    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
    .header-table td { border: none !important; padding: 0 !important; vertical-align: middle; }
    .cell-logo { width: 70px; }
    .cell-text { text-align: center; }
    .cell-balance { width: 70px; }
    .school-title { font-size: 12pt; font-weight: bold; margin-bottom: 4px; text-transform: uppercase; }
    .school-name { font-size: 13pt; font-weight: bold; text-transform: uppercase; }
    .header-line { width: 150px; border-bottom: 1px solid #000; margin: 6px auto 0 auto; }
    
    /* Cấu trúc bảng biểu và phân cấp text chi tiết học phần */
    .main-title { text-align: center; font-size: 14pt; font-weight: bold; margin: 25px 0; line-height: 1.3; }
    h1 { font-size: 12pt; font-weight: bold; margin-top: 25px; margin-bottom: 10px; text-transform: uppercase; }
    h2 { font-size: 12pt; font-weight: bold; margin-top: 15px; margin-bottom: 8px; }
    p { margin: 0 0 8px 0; text-align: justify; }
    .indent { text-indent: 35px; }
    
    table { width: 100%; border-collapse: collapse; margin-bottom: 15px; table-layout: fixed; }
    th { font-size: 11pt; font-weight: bold; border: 1px solid #000000; padding: 6px 4px; text-align: center; background-color: #f2f2f2; }
    td { font-size: 11pt; border: 1px solid #000000; padding: 6px 5px; vertical-align: top; }
    table.info-table td { border: none; padding: 4px 0; font-size: 12pt; }
    .text-center { text-align: center; }
    .bold { font-weight: bold; }
    .italic { font-style: italic; }
    .page-break { page-break-after: always; }

    /* ĐỊNH DẠNG HỆ THỐNG MỤC LỤC ĐỒNG BỘ TIMES NEW ROMAN - KHÔNG IN ĐẬM */
    .mpdf_toc {
        font-family: "timesnewroman", Times, serif !important;
    }
    .toc-heading-pdf {
        font-family: "timesnewroman", Times, serif !important;
        font-size: 14pt !important;
        font-weight: bold !important;
        text-align: center !important;
        text-transform: uppercase !important;
        margin-top: 5px !important;
        margin-bottom: 30px !important;
    }
    div.mpdf_toc_level_0 {
        font-family: "timesnewroman", Times, serif !important;
        font-weight: normal !important; 
        font-size: 11pt;
        margin-bottom: 6px;
    }
    div.mpdf_toc_level_1 {
        font-family: "timesnewroman", Times, serif !important;
        font-weight: normal !important;
        font-style: normal !important;
        font-size: 11pt;
        margin-bottom: 4px;
    }
    div.mpdf_toc_level_2 {
        font-family: "timesnewroman", Times, serif !important;
        font-weight: normal !important;
        font-size: 11pt;
        margin-bottom: 4px;
    }
</style>
</head>
<body>

    <table class="cover-header-table">
        <tr>
            <td style="width: 155px; text-align: left; vertical-align: middle;">
                <?php if (file_exists(__DIR__ . '/Ministry_of_Health_Logo.png')): ?>
                    <img src="<?= __DIR__ . '/Ministry_of_Health_Logo.png' ?>" style="width: 160px; height: auto; display: block;">
                <?php else: ?>
                    &nbsp;
                <?php endif; ?>
            </td>
            
            <td style="text-align: center; vertical-align: middle; white-space: nowrap;">
                <div class="cover-ministry">BỘ GIÁO DỤC VÀ ĐÀO TẠO</div>
                <div class="cover-school">TRƯỜNG ĐẠI HỌC Y DƯỢC CẦN THƠ</div>
                <div class="cover-line"></div>
            </td>
            
            <td style="width: 155px;">&nbsp;</td>
        </tr>
    </table>


    <div style="font-size: 12pt; line-height: 1.0;">
        <br><br><br><br><br><br><br><br><br><br><br><br>
    </div>


    <div class="cover-center-content">
        <div class="cover-main-title">
            QUYỂN ĐỀ CƯƠNG CHI TIẾT<br>CÁC HỌC PHẦN CHUYÊN NGÀNH
        </div>

        <div class="cover-info-block">
            TÊN NGÀNH: <?= htmlspecialchars(upper(s($major['name']))) ?><br>
            MÃ NGÀNH: <?= htmlspecialchars(s($major['code'] ?: '7720101')) ?><br>
            TRÌNH ĐỘ: ĐẠI HỌC
        </div>
    </div>


    <div style="font-size: 12pt; line-height: 1.0;">
        <br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
    </div>


    <div class="cover-footer">
        CẦN THƠ, NĂM <?= date('Y') ?>
    </div>


    <tocpagebreak 
        links="true" 
        toc-bookmarkText="Mục lục tổng quan"
        resetpagenumber="1" 
        pagenumberstyle="1"
        toc-heading="MỤC LỤC"
        toc-prehtml="&lt;div class=&quot;toc-heading-pdf&quot;&gt;MỤC LỤC&lt;/div&gt;"
    />

    <?php
    foreach ($renderPlan as $item) {
        if ($item['kind'] === 'title') {
            // Đăng ký thẻ mục lục cấp 1 vào hệ thống mPDF
            echo '<tocentry content="' . htmlspecialchars(s($item['text'])) . '" level="0" />';
            continue;
        }
        if ($item['kind'] === 'subtitle') {
            // Đăng ký thẻ mục lục cấp 2 vào hệ thống mPDF
            echo '<tocentry content="' . htmlspecialchars(s($item['text'])) . '" level="1" />';
            continue;
        }

        if ($item['kind'] === 'module') {
            $data = fetchModuleDetailsForMajor($pdo, $item['id']);
            if (empty($data)) continue;

            $module = $data['module'];
            $clos = $data['clos'];
            $assessments = $data['assessments'];
            $theoryTopics = $data['theoryTopics'];
            $practicalTopics = $data['practicalTopics'];
            $combinedTopics = $data['combinedTopics'];
            $selfStudyActivities = $data['selfStudyActivities'];
            $resources = $data['resources'];

            // Đăng ký tên học phần vào mục lục cấp 3 (Không kèm mã học phần, không dùng dấu chấm tròn)
            echo '<tocentry content="' . htmlspecialchars(s($module['course_name'])) . '" level="2" />';
            ?>
            
            <table class="header-table">
                <tr>
                    <td class="cell-logo">
                        <?php if (file_exists(__DIR__ . '/CTUMP_Logo.png')): ?>
                            <img src="<?= __DIR__ . '/CTUMP_Logo.png' ?>" style="width: 70px; height: 70px; display: block;">
                        <?php else: ?>
                            &nbsp;
                        <?php endif; ?>
                    </td>
                    
                    <td class="cell-text">
                        <div class="school-title">BỘ Y TẾ</div>
                        <div class="school-name">TRƯỜNG ĐẠI HỌC Y DƯỢC CẦN THƠ</div>
                        <div class="header-line"></div>
                    </td>
                    
                    <td class="cell-balance">&nbsp;</td>
                </tr>
            </table>

            <div class="main-title">
                ĐỀ CƯƠNG CHI TIẾT HỌC PHẦN<br>
                <?= htmlspecialchars(upper(s($module['course_name']))) ?>
            </div>

            <h1>1. THÔNG TIN HỌC PHẦN</h1>
            <table class="info-table">
                <tr>
                    <td width="42%">Mã học phần: <?= htmlspecialchars(s($module['course_code'])) ?></td>
                    <td width="29%"></td>
                    <td width="29%"></td>
                </tr>
                <tr>
                    <td colspan="3">Học phần bắt buộc/ điều kiện/ tự chọn: <?= htmlspecialchars(s($module['type'])) ?></td>
                </tr>
                <tr>
                    <td>Tổng số tín chỉ: <?= htmlspecialchars(s($module['credits'])) ?></td>
                    <td>Lý thuyết: <?= htmlspecialchars(s($module['credits_theory'])) ?></td>
                    <td>Thực hành: <?= htmlspecialchars(s($module['credits_practice'])) ?></td>
                </tr>
                <tr>
                    <td>Phân bổ thời gian (tiết): <?= htmlspecialchars(s($module['total_hours'])) ?></td>
                    <td>Lý thuyết: <?= htmlspecialchars(s($module['theory_hours'])) ?></td>
                    <td>Thực hành: <?= htmlspecialchars(s($module['practical_hours'])) ?></td>
                </tr>
                <tr><td colspan="3">Số giờ tự học (tiết): <?= htmlspecialchars(s($module['self_study_hours'])) ?></td></tr>
                <tr><td colspan="3">Đối tượng người học (dự kiến): <?= htmlspecialchars(s($module['target_programs'])) ?></td></tr>
                <tr><td colspan="3">Học kỳ và năm dự kiến học: <?= htmlspecialchars(s($module['expected_semester'])) ?> - <?= htmlspecialchars(s($module['expected_year'])) ?></td></tr>
                <tr><td colspan="3">Học phần tiên quyết: <?= (s($module['prerequisite_modules_text']) ?: 'Không') ?></td></tr>
                <tr><td colspan="3">Học phần song hành: <?= (s($module['parallel_modules_text']) ?: 'Không') ?></td></tr>
                <tr><td colspan="3">Học phần học trước: <?= (s($module['previous_modules_text']) ?: 'Không') ?></td></tr>
                <tr><td colspan="3">Bộ môn tham gia giảng dạy: <?= htmlspecialchars(s($module['department_in_charge_text'])) ?></td></tr>
                <tr><td colspan="3">Ban điều phối học phần: <?= htmlspecialchars(s($module['coordinating_board'])) ?></td></tr>
                <tr><td colspan="3">Khoa phụ trách: <?= htmlspecialchars(s($module['faculty_in_charge'])) ?></td></tr>
            </table>

            <h1>2. MÔ TẢ HỌC PHẦN</h1>
            <p class="indent"><?= htmlspecialchars(s($module['description'])) ?></p>

            <h1>3. MỤC TIÊU VÀ CHUẨN ĐẦU RA HỌC PHẦN</h1>
            <h2>3.1. Mục tiêu</h2>
            <?php 
            foreach (preg_split('/\r\n|\r|\n/', trim(s($module['objectives']))) as $line) {
                if (trim($line) === '') continue;
                echo '<p class="indent">' . htmlspecialchars(trim($line)) . '</p>';
            }
            ?>
            
            <h2>3.2. Chuẩn đầu ra học phần (Bloom)</h2>
            <table>
                <thead>
                    <tr>
                        <th width="15%">Lĩnh vực</th>
                        <th width="23%">Mức độ Bloom Taxonomy</th>
                        <th width="12%">TT</th>
                        <th width="50%">Chuẩn đầu ra học phần</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($clos)): ?>
                        <?php foreach ($clos as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars(s($c['domain'])) ?></td>
                                <td><?= htmlspecialchars(s($c['bloom_level'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars(s($c['code'])) ?></td>
                                <td><?= htmlspecialchars(s($c['description'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center">Chưa cấu hình dữ liệu CLO</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h1>4. PHƯƠNG PHÁP KIỂM TRA, LƯỢNG GIÁ HỌC PHẦN</h1>
            <h2>4.1. Thang điểm lượng giá</h2>
            <?php 
            $scale = s($module['grading_scale']) ?: 'Học phần được lượng giá theo thang điểm 10.';
            foreach (preg_split('/\r\n|\r|\n/', trim($scale)) as $line) {
                if (trim($line) === '') continue;
                echo '<p class="indent">' . htmlspecialchars(trim($line)) . '</p>';
            }
            ?>

            <h2>4.2. Phương pháp kiểm tra lượng giá</h2>
            <table>
                <thead>
                    <tr>
                        <th width="18%">CLOs</th>
                        <th width="15%">PLO/PI liên quan</th>
                        <th width="27%">Hình thức đánh giá</th>
                        <th width="28%">Công cụ đánh giá</th>
                        <th width="12%">Trọng số</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($assessments)): ?>
                        <?php foreach ($assessments as $a): ?>
                            <tr>
                                <td class="text-center"><?= htmlspecialchars(s($a['clos_codes'] ?: '---')) ?></td>
                                <td class="text-center"><?= htmlspecialchars(s($a['plo_pi'])) ?></td>
                                <td><?= htmlspecialchars(s($a['form'])) ?></td>
                                <td><?= htmlspecialchars(s($a['tool'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars(s($a['weight'])) ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center">Chưa có phương pháp đánh giá nào.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2>4.3. Lượng giá hoạt động tự học</h2>
            <table>
                <thead>
                    <tr>
                        <th width="26%">Hoạt động tự học</th>
                        <th width="14%">CLOs liên quan</th>
                        <th width="10%">Thời lượng (giờ)</th>
                        <th width="20%">Phương pháp tự học</th>
                        <th width="18%">Cách thức đánh giá</th>
                        <th width="12%">Minh chứng</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($selfStudyActivities)): ?>
                        <?php foreach ($selfStudyActivities as $s_act): ?>
                            <tr>
                                <td><?= htmlspecialchars(s($s_act['activity_name'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars(s($s_act['clos_codes'] ?: '---')) ?></td>
                                <td class="text-center"><?= htmlspecialchars(s($s_act['duration_hours'])) ?></td>
                                <td><?= htmlspecialchars(s($s_act['method'])) ?></td>
                                <td><?= htmlspecialchars(s($s_act['assessment_method'])) ?></td>
                                <td><?= htmlspecialchars(s($s_act['evidence'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center">Chưa thiết lập nội dung lượng giá hoạt động tự học.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h1>5. NỘI DUNG HỌC PHẦN VÀ PHƯƠNG PHÁP DẠY - HỌC</h1>
            <h2>5.1. Lý thuyết</h2>
            <table>
                <thead>
                    <tr>
                        <th width="13%">Chương/Bài</th>
                        <th width="27%">Nội dung lý thuyết</th>
                        <th width="14%">Hình thức dạy</th>
                        <th width="8%">Tiết lớp</th>
                        <th width="8%">Tiết tự học</th>
                        <th width="10%">CLOs</th>
                        <th width="20%">Tài liệu liên quan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($theoryTopics)): ?>
                        <?php foreach ($theoryTopics as $t): ?>
                            <tr>
                                <td class="text-center"><?= htmlspecialchars(s($t['chapter'])) ?></td>
                                <td><?= htmlspecialchars(s($t['title'])) ?></td>
                                <td><?= htmlspecialchars(s($t['method'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars(s($t['class_hours'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars(s($t['self_study_hours'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars(s($t['clos_codes'])) ?></td>
                                <td><?= htmlspecialchars(s($t['textbook_info'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center">Chưa thiết lập bài giảng lý thuyết</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2>5.2. Thực hành</h2>
            <table>
                <thead>
                    <tr>
                        <th width="10%">Chủ đề</th>
                        <th width="35%">Nội dung chi tiết / Kỹ năng</th>
                        <th width="15%">Hình thức tổ chức</th>
                        <th width="9%">Số tiết TH</th>
                        <th width="13%">CLOs đạt</th>
                        <th width="20%">Cơ sở TH</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($practicalTopics)): ?>
                        <?php foreach ($practicalTopics as $p): ?>
                            <tr>
                                <td class="text-center"><?= htmlspecialchars(s($p['topic'])) ?></td>
                                <td><?= htmlspecialchars(s($p['content'])) ?></td>
                                <td><?= htmlspecialchars(s($p['method'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars(s($p['lab_hours'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars(s($p['clos_codes'])) ?></td>
                                <td><?= htmlspecialchars(s($p['facility_name'] ?? 'Chưa bố trí')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center">Chưa thiết lập nội dung thực hành</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2>5.3. Lý thuyết và Thực hành tích hợp (chung)</h2>
            <table>
                <thead>
                    <tr>
                        <th width="6%">TT</th>
                        <th width="26%">Nội dung chính tích hợp</th>
                        <th width="16%">Hình thức dạy học</th>
                        <th width="7%">Tiết LT</th>
                        <th width="7%">Tiết TH</th>
                        <th width="8%">Tự học</th>
                        <th width="10%">CLOs</th>
                        <th width="18%">Cơ sở thực hành</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($combinedTopics)): ?>
                        <?php $TT = 1; foreach ($combinedTopics as $cb): ?>
                            <tr>
                                <td class="text-center"><?= $TT++ ?></td>
                                <td><?= htmlspecialchars(s($cb['content'])) ?></td>
                                <td><?= htmlspecialchars(s($cb['method'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars(s($cb['theory_hours'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars(s($cb['practical_hours'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars(s($cb['self_study_hours'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars(s($cb['clos_codes'])) ?></td>
                                <td><?= htmlspecialchars(s($cb['facility_name'] ?? 'Chưa bố trí')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center">Chưa cấu hình nội dung tích hợp chung</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h1>6. TÀI LIỆU DẠY VÀ HỌC</h1>
            <?php 
            $teachRes = array_filter($resources, fn($r) => $r['resource_type'] === 'Tài liệu giảng dạy');
            $selfRes  = array_filter($resources, fn($r) => $r['resource_type'] === 'Tài liệu tự học');
            ?>

            <h2>6.1. Tài liệu giảng dạy</h2>
            <table>
                <thead>
                    <tr>
                        <th width="5%">TT</th>
                        <th width="35%">Tên giáo trình / Tài liệu</th>
                        <th width="18%">Chủ biên</th>
                        <th width="18%">Nhà xuất bản</th>
                        <th width="10%">Năm XB</th>
                        <th width="14%">Số định danh cá biệt tại thư viện</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($teachRes)): ?>
                        <?php $i = 1; foreach ($teachRes as $r): ?>
                            <tr>
                                <td class="text-center"><?= $i++ ?></td>
                                <td><?= htmlspecialchars(s($r['title'])) ?></td>
                                <td><?= htmlspecialchars(s($r['editor'])) ?></td>
                                <td><?= htmlspecialchars(s($r['publisher'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars(s($r['year'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars(s($r['identifier'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center">Chưa thiết lập danh mục tài liệu giảng dạy</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2>6.2. Tài liệu tự học</h2>
            <table>
                <thead>
                    <tr>
                        <th width="5%">TT</th>
                        <th width="38%">Tên giáo trình / Tài liệu</th>
                        <th width="18%">Chủ biên</th>
                        <th width="18%">Nhà xuất bản</th>
                        <th width="7%">Năm XB</th>
                        <th width="14%">Định danh TV</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($selfRes)): ?>
                        <?php $i = 1; foreach ($selfRes as $r): ?>
                            <tr>
                                <td class="text-center"><?= $i++ ?></td>
                                <td><?= htmlspecialchars(s($r['title'])) ?></td>
                                <td><?= htmlspecialchars(s($r['editor'])) ?></td>
                                <td><?= htmlspecialchars(s($r['publisher'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars(s($r['year'])) ?></td>
                                <td><?= htmlspecialchars(s($r['identifier'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center">Chưa thiết lập danh mục tài liệu tự học</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="page-break"></div>
            <?php
        }
    }
    ?>

</body>
</html>
<?php
// =====================================================================
// ĐOẠN CUỐI FILE: XỬ LÝ ĐẦU RA (TẢI THẲNG PDF KHÔNG QUA XEM TRƯỚC)
// =====================================================================
$htmlContent = ob_get_clean();

function transliterateVietnameseToAscii(string $value): string
{
    return strtr($value, [
        'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a',
        'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a',
        'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',
        'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
        'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
        'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o',
        'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
        'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u',
        'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',
        'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',
        'đ' => 'd',
        'À' => 'A', 'Á' => 'A', 'Ạ' => 'A', 'Ả' => 'A', 'Ã' => 'A',
        'Â' => 'A', 'Ầ' => 'A', 'Ấ' => 'A', 'Ậ' => 'A', 'Ẩ' => 'A', 'Ẫ' => 'A',
        'Ă' => 'A', 'Ằ' => 'A', 'Ắ' => 'A', 'Ặ' => 'A', 'Ẳ' => 'A', 'Ẵ' => 'A',
        'È' => 'E', 'É' => 'E', 'Ẹ' => 'E', 'Ẻ' => 'E', 'Ẽ' => 'E',
        'Ê' => 'E', 'Ề' => 'E', 'Ế' => 'E', 'Ệ' => 'E', 'Ể' => 'E', 'Ễ' => 'E',
        'Ì' => 'I', 'Í' => 'I', 'Ị' => 'I', 'Ỉ' => 'I', 'Ĩ' => 'I',
        'Ò' => 'O', 'Ó' => 'O', 'Ọ' => 'O', 'Ỏ' => 'O', 'Õ' => 'O',
        'Ô' => 'O', 'Ồ' => 'O', 'Ố' => 'O', 'Ộ' => 'O', 'Ổ' => 'O', 'Ỗ' => 'O',
        'Ơ' => 'O', 'Ờ' => 'O', 'Ớ' => 'O', 'Ợ' => 'O', 'Ở' => 'O', 'Ỡ' => 'O',
        'Ù' => 'U', 'Ú' => 'U', 'Ụ' => 'U', 'Ủ' => 'U', 'Ũ' => 'U',
        'Ư' => 'U', 'Ừ' => 'U', 'Ứ' => 'U', 'Ự' => 'U', 'Ử' => 'U', 'Ữ' => 'U',
        'Ỳ' => 'Y', 'Ý' => 'Y', 'Ỵ' => 'Y', 'Ỷ' => 'Y', 'Ỹ' => 'Y',
        'Đ' => 'D',
    ]);
}

// 1. Chỉ định và tự động tạo thư mục tạm riêng cho mPDF
$customTempDir = __DIR__ . '/vendor/mpdf/mpdf/tmp';
if (!file_exists($customTempDir)) {
    @mkdir($customTempDir, 0777, true);
}

$majorNameNoSign = transliterateVietnameseToAscii($major['name']);

$cleanMajorName = preg_replace('/[^A-Za-z0-9]/', '_', $majorNameNoSign);


$cleanMajorName = preg_replace('/_+/', '_', $cleanMajorName);
$cleanMajorName = trim($cleanMajorName, '_'); 

$fileNameOutput = 'Quyen_De_Cuong_Nganh_' . $cleanMajorName . '.pdf';

try {
    // 2. Cấu hình mPDF chuẩn văn bản hành chính Việt Nam
    $mpdf = new \Mpdf\Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4',
        'margin_top'    => 25,
        'margin_bottom' => 25,
        'margin_left'   => 30,
        'margin_right'  => 20,
        'default_font'  => 'times',       // Dùng font lõi có sẵn chống crash hiển thị
        'tempDir'       => $customTempDir  // Chỉ định thư mục tạm tường minh
    ]);

    // 3. Bật nén dữ liệu bảng biểu thu gọn RAM chống quá tải hệ thống
    $mpdf->packTableData = true;

    $mpdf->SetTitle('Quyển Đề Cương Ngành - ' . s($major['name']));
    
    // 4. Đổ dữ liệu HTML vào mPDF
    $mpdf->WriteHTML($htmlContent);

    // 5. Xóa toàn bộ ký tự rác ngầm phát sinh trong buffer tránh hỏng tệp tin PDF
    if (ob_get_length()) ob_end_clean();

    // 6. Thiết lập Header ép trình duyệt kích hoạt tải tệp tin về ngay lập tức
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $fileNameOutput . '"');
    header('Cache-Control: max-age=0');

    $mpdf->Output($fileNameOutput, \Mpdf\Output\Destination::DOWNLOAD);
    exit;

} catch (\Exception $e) {
    // Nếu có lỗi bẻ thẻ HTML sai quy cách hoặc cấu hình, in chi tiết lỗi ra màn hình
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    die("Lỗi kết xuất mPDF hệ thống: " . $e->getMessage());
}
exit;
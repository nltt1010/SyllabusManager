<?php
// declare(encoding='UTF-8');

mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

// debug
// $stmt = $pdo->query("SHOW TABLES");
// var_dump($stmt->fetchAll());
// exit;

// echo '<pre>';

// $stmt = $pdo->query("DESCRIBE module_relationships");
// var_dump($stmt->fetchAll(PDO::FETCH_ASSOC));
// exit;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Table  as TableStyle;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\SimpleType\Jc;

ini_set('display_errors', 0); // debug

// =====================================================================
// 1. LẤY ID VÀ TRUY VẤN CƠ SỞ DỮ LIỆU
// =====================================================================
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    http_response_code(400);
    die("Không tìm thấy mã đề cương học phần hợp lệ.");
}

// 1.1 Thông tin cơ bản học phần
$stmt = $pdo->prepare("
    SELECT m.*, c.name AS course_name
    FROM modules m
    LEFT JOIN courses c ON m.course_id = c.id
    WHERE m.id = ?
");
$stmt->execute([$id]);
$module = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$module) {
    http_response_code(404);
    die("Đề cương không tồn tại trên hệ thống.");
}

// Fallback an toàn cho các cột có thể chưa migrate
$module['total_hours']          = $module['total_hours']          ?? (($module['theory_hours'] ?? 0) + ($module['practical_hours'] ?? 0));
$module['credits_theory']       = $module['credits_theory']       ?? 0;
$module['credits_practice']     = $module['credits_practice']     ?? 0;

// Lấy danh sách Học phần tiên quyết từ bảng liên kết
$stmt = $pdo->prepare("SELECT GROUP_CONCAT(c.code SEPARATOR ', ') FROM module_relationships mr JOIN courses c ON mr.related_course_id = c.id WHERE mr.module_id = ? AND mr.relation_type = 'Tiên quyết'");
$stmt->execute([$id]);
$module['prerequisite_modules_text'] = $stmt->fetchColumn() ?: ($module['prerequisite_modules'] ?? '');

// debug
// $stmt = $pdo->prepare("
// SELECT GROUP_CONCAT(c.code SEPARATOR ', ')
// FROM module_relationships mr
// JOIN courses c
// ON mr.related_course_id = c.id
// WHERE mr.module_id = ?
// AND mr.relation_type = 'Tiên quyết'
// ");
// $stmt->execute([$id]);
// echo '<pre>';
// $row = $stmt->fetch(PDO::FETCH_ASSOC);
// var_dump($row);
// exit;

// Lấy danh sách Học phần song hành
$stmt = $pdo->prepare("SELECT GROUP_CONCAT(c.code SEPARATOR ', ') FROM module_relationships mr JOIN courses c ON mr.related_course_id = c.id WHERE mr.module_id = ? AND mr.relation_type = 'Song hành'");
$stmt->execute([$id]);
$module['parallel_modules_text'] = $stmt->fetchColumn() ?: ($module['parallel_modules'] ?? '');

// // Lấy danh sách Học phần học trước
$stmt = $pdo->prepare("SELECT GROUP_CONCAT(c.code SEPARATOR ', ') FROM module_relationships mr JOIN courses c ON mr.related_course_id = c.id WHERE mr.module_id = ? AND mr.relation_type = 'Học trước'");
$stmt->execute([$id]);
$module['previous_modules_text'] = $stmt->fetchColumn() ?: ($module['previous_modules'] ?? '');

// // Lấy danh sách Bộ môn
$stmt = $pdo->prepare("SELECT GROUP_CONCAT(d.name SEPARATOR ', ') FROM module_departments md JOIN departments_list d ON md.department_id = d.id WHERE md.module_id = ?");
$stmt->execute([$id]);
$module['department_in_charge_text'] = $stmt->fetchColumn() ?: ($module['department_in_charge'] ?? '');

// 1.2 Chuẩn đầu ra CLO
$stmt = $pdo->prepare("SELECT * FROM clos WHERE module_id = ? ORDER BY id ASC");
$stmt->execute([$id]);
$clos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 1.3 Phương pháp đánh giá
$stmt = $pdo->prepare("
    SELECT a.*, GROUP_CONCAT(c.code SEPARATOR ', ') AS clos_codes
    FROM assessments a
    LEFT JOIN assessment_clos ac ON a.id = ac.assessment_id
    LEFT JOIN clos c ON ac.clo_id = c.id
    WHERE a.module_id = ?
    GROUP BY a.id ORDER BY a.id ASC
");
$stmt->execute([$id]);
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 1.4 Hoạt động tự học
$stmt = $pdo->prepare("
    SELECT s.*, GROUP_CONCAT(c.code SEPARATOR ', ') AS clos_codes
    FROM self_study_activities s
    LEFT JOIN self_study_clos sc ON s.id = sc.self_study_activity_id
    LEFT JOIN clos c ON sc.clo_id = c.id
    WHERE s.module_id = ?
    GROUP BY s.id ORDER BY s.id ASC
");
$stmt->execute([$id]);
$selfStudyActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 1.5 Tiến độ Lý thuyết
$stmt = $pdo->prepare("
    SELECT t.*, GROUP_CONCAT(c.code SEPARATOR ', ') AS clos_codes
    FROM theory_topics t
    LEFT JOIN theory_topic_clos tc ON t.id = tc.theory_topic_id
    LEFT JOIN clos c ON tc.clo_id = c.id
    WHERE t.module_id = ?
    GROUP BY t.id ORDER BY t.id ASC
");
$stmt->execute([$id]);
$theoryTopics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 1.6 Tiến độ Thực hành
$stmt = $pdo->prepare("
    SELECT p.*, f.name AS facility_name, GROUP_CONCAT(c.code SEPARATOR ', ') AS clos_codes
    FROM practical_topics p
    LEFT JOIN practical_topic_clos pc ON p.id = pc.practical_topic_id
    LEFT JOIN clos c ON pc.clo_id = c.id
    LEFT JOIN facilities f ON p.facility_id = f.id
    WHERE p.module_id = ?
    GROUP BY p.id ORDER BY p.id ASC
");
$stmt->execute([$id]);
$practicalTopics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 1.7 Tích hợp Lý thuyết & Thực hành
$stmt = $pdo->prepare("
    SELECT cb.*, f.name AS facility_name, GROUP_CONCAT(c.code SEPARATOR ', ') AS clos_codes
    FROM combined_topics cb
    LEFT JOIN combined_topic_clos cbc ON cb.id = cbc.combined_topic_id
    LEFT JOIN clos c ON cbc.clo_id = c.id
    LEFT JOIN facilities f ON cb.facility_id = f.id
    WHERE cb.module_id = ?
    GROUP BY cb.id ORDER BY cb.id ASC
");
$stmt->execute([$id]);
$combinedTopics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 1.8 Tài liệu dạy và học
$stmt = $pdo->prepare("SELECT * FROM resources WHERE module_id = ? ORDER BY resource_type ASC, sort_order ASC");
$stmt->execute([$id]);
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);


// =====================================================================
// 2. HELPER FUNCTIONS
// =====================================================================

/**
 * Trả về chuỗi an toàn, không null
 */
function s(?string $val): string {
    return trim($val ?? '');
}

/**
 * Thêm một hàng tiêu đề (header) vào bảng
 */
function addTableHeader(object $table, array $headers, array $widths, array $styles): void {
    $row = $table->addRow(500);
    foreach ($headers as $i => $text) {
        $cell = $row->addCell($widths[$i], $styles['headerCell']);
        $cell->addText($text, $styles['headerFont'], $styles['headerPara']);
    }
}

/**
 * Thêm một hàng dữ liệu vào bảng
 */
function addTableRow(object $table, array $values, array $widths, array $styles, array $aligns = []): void {
    $row = $table->addRow();
    foreach ($values as $i => $val) {
        $cell = $row->addCell($widths[$i], $styles['dataCell']);
        $paraAlign = isset($aligns[$i]) ? ['alignment' => $aligns[$i]] : $styles['dataPara'];
        $cell->addText(s($val), $styles['dataFont'], $paraAlign);
    }
}

// =====================================================================
// 3. TẠO TÀI LIỆU WORD
// =====================================================================
$phpWord = new PhpWord();
$phpWord->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language('vi-VN'));
\PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);
\PhpOffice\PhpWord\Settings::setDefaultFontName('Times New Roman');
\PhpOffice\PhpWord\Settings::setDefaultFontSize(12);

// --- Định nghĩa font & kích cỡ ---
$fontName   = 'Times New Roman';
$fontSize   = 12;
$fontSizeSm = 11;

// --- Styles tái sử dụng ---
$titleFont    = ['name' => $fontName, 'size' => 14, 'bold' => true];
$heading1Font = ['name' => $fontName, 'size' => $fontSize, 'bold' => true];
$heading2Font = ['name' => $fontName, 'size' => $fontSize, 'bold' => true];
$normalFont   = ['name' => $fontName, 'size' => $fontSize];
$smallFont    = ['name' => $fontName, 'size' => $fontSizeSm];

$centerPara   = ['alignment' => Jc::CENTER, 'spaceAfter' => 80];
$leftPara     = ['alignment' => Jc::START,  'spaceAfter' => 80];
$leftParaSm   = ['alignment' => Jc::START,  'spaceAfter' => 40];

// --- Table styles ---
$tableStyle = [
    'borderSize' => 6,
    'borderColor' => '000000',
    'cellMargin' => 80,
    'width' => 100 * 50,
    'unit' => TblWidth::PERCENT,
];

$tStyles = [
    'headerCell' => [],
    'headerFont' => ['name' => $fontName, 'size' => $fontSizeSm, 'bold' => true],
    'headerPara' => ['alignment' => Jc::CENTER, 'spaceAfter' => 40],
    'dataCell'   => [],
    'dataFont'   => ['name' => $fontName, 'size' => $fontSizeSm],
    'dataPara'   => ['alignment' => Jc::START, 'spaceAfter' => 40],
];

// --- Section (trang A4, lề theo chuẩn văn bản VN) ---
$sectionStyle = [
    'pageSizeW'    => 11906, // A4 width in twips
    'pageSizeH'    => 16838, // A4 height in twips
    'marginTop'    => 1418,  // ~2.5cm
    'marginBottom' => 1134,  // ~2cm
    'marginLeft'   => 1701,  // ~3cm
    'marginRight'  => 1134,  // ~2cm
    'headerHeight' => 710,
    'footerHeight' => 710,
];
$section = $phpWord->addSection($sectionStyle);

// =====================================================================
// PHẦN HEADER: Logo + Tên trường
// =====================================================================
// $header = $section->addHeader();
// $headerTable = $header->addTable(['borderSize' => 0, 'cellMargin' => 40]);
// $hRow = $headerTable->addRow();
// // Cột trái: Bộ Y tế / Trường
// $hCell = $hRow->addCell(4500, ['borderSize' => 0]);
// $hCell->addText('BỘ Y TẾ', ['name' => $fontName, 'size' => 11, 'bold' => true], ['alignment' => Jc::CENTER]);
// $hCell->addText('TRƯỜNG ĐẠI HỌC Y DƯỢC CẦN THƠ', ['name' => $fontName, 'size' => 11, 'bold' => true], ['alignment' => Jc::CENTER]);
// // Cột phải: để trống hoặc thêm mã số
// $hRow->addCell(4500, ['borderSize' => 0]);

$headerTable = $section->addTable([
    'borderSize'  => 0,
    'borderColor' => 'FFFFFF',
    'cellMargin'  => 0,
]);

$row = $headerTable->addRow();

$cellStyle = [
    'borderTopSize'    => 0,
    'borderBottomSize' => 0,
    'borderLeftSize'   => 0,
    'borderRightSize'  => 0,
    'borderSize'  => 0,
    'borderColor' => 'FFFFFF',
    'cellMargin'  => 0,
];

// Logo
// $logoCell->addText(' ');
$logoCell = $row->addCell(2000, $cellStyle);

if (file_exists(__DIR__ . '/logo.png')) {
    $logoCell->addImage(
        __DIR__ . '/logo.png',
        [
            'width' => 70,
            'height' => 70,
            'alignment' => Jc::CENTER,
        ]
    );
}

// Tên trường
$textCell = $row->addCell(8500, $cellStyle);

$textCell->addText(
    'BỘ Y TẾ',
    ['name' => $fontName, 'size' => 12, 'bold' => false],
    ['alignment' => Jc::CENTER]
);

$textCell->addText(
    'TRƯỜNG ĐẠI HỌC Y DƯỢC CẦN THƠ',
    ['name' => $fontName, 'size' => 14, 'bold' => true],
    ['alignment' => Jc::CENTER]
);

$section->addTextBreak(1);

// =====================================================================
// TIÊU ĐỀ CHÍNH
// =====================================================================
$section->addTextBreak(1);
$section->addText('ĐỀ CƯƠNG CHI TIẾT HỌC PHẦN', $titleFont, $centerPara);
$section->addText(mb_strtoupper(s($module['name'])), $titleFont, $centerPara);
$section->addTextBreak(1);

// =====================================================================
// MỤC 1: THÔNG TIN HỌC PHẦN
// =====================================================================
$section->addText(
    '1. THÔNG TIN HỌC PHẦN',
    $heading1Font,
    array_merge($leftPara, ['spaceAfter' => 120, 'bold' => true])
);

$infoTable = $section->addTable([
    'borderSize'  => 0,
    'borderColor' => 'FFFFFF',
    'cellMargin'  => 60,
]);

$cellStyle = [
    'valign' => 'center',
];

// =====================================================
// Mã học phần
// =====================================================
$r = $infoTable->addRow();

$r->addCell(4200, $cellStyle)->addText(
    'Mã học phần: ' . s($module['code']),
    $normalFont,
    $leftParaSm
);

$r->addCell(2900, $cellStyle);
$r->addCell(2900, $cellStyle);

// =====================================================
// Loại học phần
// =====================================================
$r = $infoTable->addRow();

$r->addCell(4200, $cellStyle)->addText(
    'Học phần bắt buộc/ điều kiện/ tự chọn: ',
    $normalFont,
    $leftParaSm
);

$r->addCell(
    5800,
    array_merge($cellStyle, ['gridSpan' => 2])
)->addText(
    s($module['type']),
    $normalFont,
    $leftParaSm
);

// =====================================================
// Tín chỉ
// =====================================================
$r = $infoTable->addRow();

$r->addCell(4200, $cellStyle)->addText(
    'Tổng số tín chỉ: ' . s($module['credits']),
    $normalFont,
    $leftParaSm
);

$r->addCell(2900, $cellStyle)->addText(
    'Lý thuyết: ' . s($module['credits_theory']),
    $normalFont,
    $leftParaSm
);

$r->addCell(2900, $cellStyle)->addText(
    'Thực hành: ' . s($module['credits_practice']),
    $normalFont,
    $leftParaSm
);

// =====================================================
// Phân bổ thời gian
// =====================================================
$r = $infoTable->addRow();

$r->addCell(4200, $cellStyle)->addText(
    'Phân bổ thời gian (tiết): ' . s($module['total_hours']),
    $normalFont,
    $leftParaSm
);

$r->addCell(2900, $cellStyle)->addText(
    'Lý thuyết: ' . s($module['theory_hours']),
    $normalFont,
    $leftParaSm
);

$r->addCell(2900, $cellStyle)->addText(
    'Thực hành: ' . s($module['practical_hours']),
    $normalFont,
    $leftParaSm
);

// =====================================================
// Tự học
// =====================================================
$r = $infoTable->addRow();

$r->addCell(
    10000,
    [
        'gridSpan' => 3,
        'valign' => 'center'
    ]
)->addText(
    'Số giờ tự học (tiết): ' . s($module['self_study_hours']),
    $normalFont,
    $leftParaSm
);

// =====================================================
// Đối tượng người học
// =====================================================
$r = $infoTable->addRow();

$r->addCell(
    10000,
    [
        'gridSpan' => 3,
        'valign' => 'center'
    ]
)->addText(
    'Đối tượng người học (dự kiến): ' . s($module['target_programs']),
    $normalFont,
    $leftParaSm
);

// =====================================================
// Học kỳ & năm học
// =====================================================
$r = $infoTable->addRow();

$r->addCell(
    10000,
    [
        'gridSpan' => 3,
        'valign' => 'center'
    ]
)->addText(
    'Học kỳ và năm dự kiến học: HK ' .
    s($module['expected_semester']) .
    ' - ' .
    s($module['expected_year']),
    $normalFont,
    $leftParaSm
);

// =====================================================
// Học phần tiên quyết
// =====================================================
$r = $infoTable->addRow();

$r->addCell(
    10000,
    [
        'gridSpan' => 3,
        'valign' => 'center'
    ]
)->addText(
    'Học phần tiên quyết: ' .
    (s($module['prerequisite_modules_text']) ?: 'Không'),
    $normalFont,
    $leftParaSm
);

// =====================================================
// Học phần song hành
// =====================================================
$r = $infoTable->addRow();

$r->addCell(
    10000,
    [
        'gridSpan' => 3,
        'valign' => 'center'
    ]
)->addText(
    'Học phần song hành: ' .
    (s($module['parallel_modules_text']) ?: 'Không'),
    $normalFont,
    $leftParaSm
);

// =====================================================
// Học phần học trước
// =====================================================
$r = $infoTable->addRow();

$r->addCell(
    10000,
    [
        'gridSpan' => 3,
        'valign' => 'center'
    ]
)->addText(
    'Học phần học trước: ' .
    (s($module['previous_modules_text']) ?: 'Không'),
    $normalFont,
    $leftParaSm
);

// =====================================================
// Bộ môn giảng dạy
// =====================================================
$r = $infoTable->addRow();

$r->addCell(
    10000,
    [
        'gridSpan' => 3,
        'valign' => 'center'
    ]
)->addText(
    'Bộ môn tham gia giảng dạy: ' .
    s($module['department_in_charge_text']),
    $normalFont,
    $leftParaSm
);

// =====================================================
// Ban điều phối
// =====================================================
$r = $infoTable->addRow();

$r->addCell(
    10000,
    [
        'gridSpan' => 3,
        'valign' => 'center'
    ]
)->addText(
    'Ban điều phối học phần: ' .
    s($module['coordinating_board']),
    $normalFont,
    $leftParaSm
);

// =====================================================
// Khoa phụ trách
// =====================================================
$r = $infoTable->addRow();

$r->addCell(
    10000,
    [
        'gridSpan' => 3,
        'valign' => 'center'
    ]
)->addText(
    'Khoa phụ trách: ' .
    s($module['faculty_in_charge']),
    $normalFont,
    $leftParaSm
);

$section->addTextBreak(1);

// =====================================================================
// MỤC 2: MÔ TẢ HỌC PHẦN
// =====================================================================
$section->addText(
    '2. MÔ TẢ HỌC PHẦN',
    $heading1Font,
    $leftPara
);

$description = trim(s($module['description']));

// thêm 2 ký tự em-space để thụt đầu dòng
$description = "  " . $description;

$section->addText(
    $description,
    $normalFont,
    [
        'alignment' => Jc::BOTH,
        'spaceAfter' => 120,
    ]
);

$section->addTextBreak(1);

// =====================================================================
// MỤC 3: MỤC TIÊU VÀ CHUẨN ĐẦU RA HỌC PHẦN
// =====================================================================
$section->addText('3. MỤC TIÊU VÀ CHUẨN ĐẦU RA HỌC PHẦN', $heading1Font, array_merge($leftPara, ['spaceAfter' => 80]));

$section->addText('3.1. Mục tiêu', $heading2Font, $leftPara);
foreach (preg_split('/\r\n|\r|\n/', trim(s($module['objectives']))) as $line) {

    if (trim($line) === '') {
        continue;
    }

    $section->addText(
        '  ' . trim($line), // thụt đầu dòng
        $normalFont,
        [
            'alignment' => Jc::BOTH,
            'spaceAfter' => 40,
        ]
    );
}

$section->addTextBreak(1);

$section->addText('3.2. Chuẩn đầu ra học phần (Bloom)', $heading2Font, $leftPara);

// Bảng CLO
$cloTable = $section->addTable($tableStyle);
addTableHeader($cloTable,
    ['Lĩnh vực', 'Mức độ Bloom Taxonomy', 'TT', 'Chuẩn đầu ra học phần'],
    [2800, 2800, 800, 3600],
    $tStyles
);

if (!empty($clos)) {
    foreach ($clos as $c) {
        $row = $cloTable->addRow();
        $row->addCell(2800)->addText(s($c['domain']),       $tStyles['dataFont'], $tStyles['dataPara']);
        $row->addCell(2800)->addText(s($c['bloom_level']),  $tStyles['dataFont'], $tStyles['dataPara']);
        $row->addCell(800) ->addText(s($c['code']),         $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(3600)->addText(s($c['description']),  $tStyles['dataFont'], $tStyles['dataPara']);
    }
} else {
    $row = $cloTable->addRow();
    $row->addCell(10000, ['gridSpan' => 4])->addText('Chưa cấu hình dữ liệu CLO', $tStyles['dataFont'], ['alignment' => Jc::CENTER]);
}
$section->addTextBreak(1);

// =====================================================================
// MỤC 4: PHƯƠNG PHÁP KIỂM TRA, LƯỢNG GIÁ
// =====================================================================
$section->addText('4. PHƯƠNG PHÁP KIỂM TRA, LƯỢNG GIÁ HỌC PHẦN', $heading1Font, array_merge($leftPara, ['spaceAfter' => 80]));

// 4.1 Thang điểm
$section->addText('4.1. Thang điểm lượng giá', $heading2Font, $leftPara);
$text = s($module['grading_scale']) ?: 'Học phần được lượng giá theo thang điểm 10.';
foreach (preg_split('/\r\n|\r|\n/', trim($text)) as $line) {
    if (trim($line) === '') {
        continue;
    }
    $section->addText(
        '  ' . trim($line),
        $normalFont,
        [
            'alignment' => Jc::BOTH,
            'spaceAfter' => 20,
        ]
    );
}
$section->addTextBreak(1);

// 4.2 Phương pháp kiểm tra lượng giá
$section->addText('4.2. Phương pháp kiểm tra lượng giá', $heading2Font, $leftPara);

$assessTable = $section->addTable($tableStyle);
addTableHeader($assessTable,
    ['CLOs', 'PLO/PI liên quan', 'Hình thức đánh giá', 'Công cụ đánh giá', 'Trọng số (%)'],
    [1800, 1800, 2800, 2800, 900],
    $tStyles
);

if (!empty($assessments)) {
    foreach ($assessments as $a) {
        $row = $assessTable->addRow();
        $row->addCell(1800)->addText(s($a['clos_codes'] ?: '---'), $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(1800)->addText(s($a['plo_pi']),   $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(2800)->addText(s($a['form']),     $tStyles['dataFont'], $tStyles['dataPara']);
        $row->addCell(2800)->addText(s($a['tool']),     $tStyles['dataFont'], $tStyles['dataPara']);
        $row->addCell(900) ->addText(s($a['weight']) . '%', $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
    }
} else {
    $row = $assessTable->addRow();
    $row->addCell(10100, ['gridSpan' => 5])->addText('Chưa có phương pháp đánh giá nào.', $tStyles['dataFont'], ['alignment' => Jc::CENTER]);
}
$section->addTextBreak(1);

// 4.3 Lượng giá hoạt động tự học
$section->addText('4.3. Lượng giá hoạt động tự học', $heading2Font, $leftPara);

$selfTable = $section->addTable($tableStyle);
addTableHeader($selfTable,
    ['Hoạt động tự học', 'CLOs liên quan', 'Thời lượng (giờ)', 'Phương pháp tự học', 'Cách thức đánh giá', 'Minh chứng'],
    [2600, 1400, 1000, 2000, 1800, 1200],
    $tStyles
);

if (!empty($selfStudyActivities)) {
    foreach ($selfStudyActivities as $s_act) {
        $row = $selfTable->addRow();
        $row->addCell(2600)->addText(s($s_act['activity_name']),    $tStyles['dataFont'], $tStyles['dataPara']);
        $row->addCell(1400)->addText(s($s_act['clos_codes'] ?: '---'), $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(1000)->addText(s($s_act['duration_hours']),   $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(2000)->addText(s($s_act['method']),           $tStyles['dataFont'], $tStyles['dataPara']);
        $row->addCell(1800)->addText(s($s_act['assessment_method']),$tStyles['dataFont'], $tStyles['dataPara']);
        $row->addCell(1200)->addText(s($s_act['evidence']),         $tStyles['dataFont'], $tStyles['dataPara']);
    }
} else {
    $row = $selfTable->addRow();
    $row->addCell(10000, ['gridSpan' => 6])->addText('Chưa thiết lập nội dung lượng giá hoạt động tự học.', $tStyles['dataFont'], ['alignment' => Jc::CENTER]);
}
$section->addTextBreak(1);

// =====================================================================
// MỤC 5: NỘI DUNG HỌC PHẦN VÀ TIẾN ĐỘ GIẢNG DẠY
// =====================================================================
$section->addText('5. NỘI DUNG HỌC PHẦN VÀ PHƯƠNG PHÁP DẠY - HỌC', $heading1Font, array_merge($leftPara, ['spaceAfter' => 80]));

// 5.1 Lý thuyết
$section->addText('5.1. Lý thuyết', $heading2Font, $leftPara);

$theoryTable = $section->addTable($tableStyle);
addTableHeader($theoryTable,
    ['Chương/Bài', 'Nội dung lý thuyết', 'Hình thức dạy', 'Tiết trên lớp', 'Tiết tự học', 'CLOs đạt được', 'Tài liệu liên quan'],
    [1200, 3000, 1500, 800, 800, 1000, 1700],
    $tStyles
);

if (!empty($theoryTopics)) {
    foreach ($theoryTopics as $t) {
        $row = $theoryTable->addRow();
        $row->addCell(1200)->addText(s($t['chapter']),      $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(3000)->addText(s($t['title']),        $tStyles['dataFont'], $tStyles['dataPara']);
        $row->addCell(1500)->addText(s($t['method']),       $tStyles['dataFont'], $tStyles['dataPara']);
        $row->addCell(800) ->addText(s($t['class_hours']), $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(800) ->addText(s($t['self_study_hours']), $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(1000)->addText(s($t['clos_codes']),   $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(1700)->addText(s($t['textbook_info']),$tStyles['dataFont'], $tStyles['dataPara']);
    }
} else {
    $row = $theoryTable->addRow();
    $row->addCell(11000, ['gridSpan' => 7])->addText('Chưa thiết lập bài giảng lý thuyết', $tStyles['dataFont'], ['alignment' => Jc::CENTER]);
}
$section->addTextBreak(1);

// 5.2 Thực hành
$section->addText('5.2. Thực hành', $heading2Font, $leftPara);

$practTable = $section->addTable($tableStyle);
addTableHeader($practTable,
    ['Chủ đề', 'Nội dung chi tiết / Kỹ năng', 'Hình thức tổ chức', 'Số tiết TH', 'CLOs đạt được', 'Cơ sở thực hành'],
    [1500, 3500, 1800, 900, 1200, 1100],
    $tStyles
);

if (!empty($practicalTopics)) {
    foreach ($practicalTopics as $p) {
        $row = $practTable->addRow();
        $row->addCell(1500)->addText(s($p['topic']),           $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(3500)->addText(s($p['content']),         $tStyles['dataFont'], $tStyles['dataPara']);
        $row->addCell(1800)->addText(s($p['method']),          $tStyles['dataFont'], $tStyles['dataPara']);
        $row->addCell(900) ->addText(s($p['lab_hours']),       $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(1200)->addText(s($p['clos_codes']),      $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(1100)->addText(s($p['facility_name'] ?? 'Chưa bố trí'), $tStyles['dataFont'], $tStyles['dataPara']);
    }
} else {
    $row = $practTable->addRow();
    $row->addCell(10000, ['gridSpan' => 6])->addText('Chưa thiết lập nội dung thực hành', $tStyles['dataFont'], ['alignment' => Jc::CENTER]);
}
$section->addTextBreak(1);

// 5.3 Tích hợp Lý thuyết & Thực hành
$section->addText('5.3. Lý thuyết và Thực hành tích hợp (chung)', $heading2Font, $leftPara);

$combTable = $section->addTable($tableStyle);
addTableHeader($combTable,
    ['STT', 'Nội dung chính tích hợp', 'Hình thức dạy học', 'Tiết LT', 'Tiết TH', 'Tiết tự học', 'CLOs đạt được', 'Cơ sở thực hành'],
    [600, 2800, 1800, 700, 700, 900, 1000, 1500],
    $tStyles
);

if (!empty($combinedTopics)) {
    $stt = 1;
    foreach ($combinedTopics as $cb) {
        $row = $combTable->addRow();
        $row->addCell(600) ->addText((string)$stt++,              $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(2800)->addText(s($cb['content']),           $tStyles['dataFont'], $tStyles['dataPara']);
        $row->addCell(1800)->addText(s($cb['method']),            $tStyles['dataFont'], $tStyles['dataPara']);
        $row->addCell(700) ->addText(s($cb['theory_hours']),      $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(700) ->addText(s($cb['practical_hours']),   $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(900) ->addText(s($cb['self_study_hours']),  $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(1000)->addText(s($cb['clos_codes']),        $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(1500)->addText(s($cb['facility_name'] ?? 'Chưa bố trí'), $tStyles['dataFont'], $tStyles['dataPara']);
    }
} else {
    $row = $combTable->addRow();
    $row->addCell(11000, ['gridSpan' => 8])->addText('Chưa cấu hình nội dung tích hợp chung', $tStyles['dataFont'], ['alignment' => Jc::CENTER]);
}
$section->addTextBreak(1);

// =====================================================================
// MỤC 6: TÀI LIỆU DẠY VÀ HỌC
// =====================================================================
$section->addText('6. TÀI LIỆU DẠY VÀ HỌC', $heading1Font, array_merge($leftPara, ['spaceAfter' => 80]));

// Tách 2 loại tài liệu
$teachRes = array_filter($resources, fn($r) => $r['resource_type'] === 'Tài liệu giảng dạy');
$selfRes  = array_filter($resources, fn($r) => $r['resource_type'] === 'Tài liệu tự học');

$resourceCols    = ['STT', 'Tên giáo trình / Tài liệu', 'Chủ biên', 'Nhà xuất bản', 'Năm XB', 'Số định danh thư viện'];
$resourceWidths  = [500, 3800, 1800, 1800, 700, 1400];

// 6.1 Tài liệu giảng dạy
$section->addText('6.1. Tài liệu giảng dạy', $heading2Font, $leftPara);
$teachTable = $section->addTable($tableStyle);
addTableHeader($teachTable, $resourceCols, $resourceWidths, $tStyles);

if (!empty($teachRes)) {
    $i = 1;
    foreach ($teachRes as $r) {
        $row = $teachTable->addRow();
        $row->addCell(500) ->addText((string)$i++,         $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(3800)->addText(s($r['title']),        $tStyles['dataFont'], $tStyles['dataPara']);
        $row->addCell(1800)->addText(s($r['editor']),       $tStyles['dataFont'], $tStyles['dataPara']);
        $row->addCell(1800)->addText(s($r['publisher']),    $tStyles['dataFont'], $tStyles['dataPara']);
        $row->addCell(700) ->addText(s($r['year']),         $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(1400)->addText(s($r['identifier']),   $tStyles['dataFont'], $tStyles['dataPara']);
    }
} else {
    $row = $teachTable->addRow();
    $row->addCell(10000, ['gridSpan' => 6])->addText('Chưa thiết lập danh mục tài liệu giảng dạy', $tStyles['dataFont'], ['alignment' => Jc::CENTER]);
}
$section->addTextBreak(1);

// 6.2 Tài liệu tự học
$section->addText('6.2. Tài liệu tự học', $heading2Font, $leftPara);
$selfResTable = $section->addTable($tableStyle);
addTableHeader($selfResTable, $resourceCols, $resourceWidths, $tStyles);

if (!empty($selfRes)) {
    $i = 1;
    foreach ($selfRes as $r) {
        $row = $selfResTable->addRow();
        $row->addCell(500) ->addText((string)$i++,         $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(3800)->addText(s($r['title']),        $tStyles['dataFont'], $tStyles['dataPara']);
        $row->addCell(1800)->addText(s($r['editor']),       $tStyles['dataFont'], $tStyles['dataPara']);
        $row->addCell(1800)->addText(s($r['publisher']),    $tStyles['dataFont'], $tStyles['dataPara']);
        $row->addCell(700) ->addText(s($r['year']),         $tStyles['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        $row->addCell(1400)->addText(s($r['identifier']),   $tStyles['dataFont'], $tStyles['dataPara']);
    }
} else {
    $row = $selfResTable->addRow();
    $row->addCell(10000, ['gridSpan' => 6])->addText('Chưa thiết lập danh mục tài liệu tự học', $tStyles['dataFont'], ['alignment' => Jc::CENTER]);
}

// =====================================================================
// 4. GỬI FILE VỀ BROWSER
// =====================================================================
$safeCode     = preg_replace('/[^A-Za-z0-9_\-]/', '_', s($module['code']));
$filename     = 'DeCuong_' . $safeCode . '.docx';

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit("Khong the xuat file Word vi PHP extension 'zip' chua duoc bat. Hay bo comment 'extension=zip' trong php.ini va khoi dong lai web server.");
}

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save('php://output');
exit;

<?php

mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\Style\TOC as TocStyle;

ini_set('display_errors', 0);
@set_time_limit(180);

function s(?string $value): string
{
    return trim((string) ($value ?? ''));
}

function upper(string $value): string
{
    return mb_strtoupper($value, 'UTF-8');
}

function normalizeMajorName(string $value): string
{
    return mb_strtolower(trim($value), 'UTF-8');
}

function resolveMajorMetadata(string $majorName): array
{
    $map = [
        'y khoa' => [
            'english_name' => 'General Medicine',
            'code' => '7720101',
        ],
        'dược học' => [
            'english_name' => 'Pharmacy',
            'code' => '7720201',
        ],
        'điều dưỡng' => [
            'english_name' => 'Nursing',
            'code' => '7720301',
        ],
    ];

    return $map[normalizeMajorName($majorName)] ?? [
        'english_name' => '',
        'code' => '',
    ];
}

function addTableHeader(object $table, array $headers, array $widths, array $styles): void
{
    $row = $table->addRow(500);
    foreach ($headers as $index => $text) {
        $cell = $row->addCell($widths[$index], $styles['headerCell']);
        $cell->addText($text, $styles['headerFont'], $styles['headerPara']);
    }
}

function fetchMajor(PDO $pdo, int $majorId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM majors WHERE id = ?');
    $stmt->execute([$majorId]);

    $major = $stmt->fetch(PDO::FETCH_ASSOC);

    return $major ?: null;
}

function fetchMajorModuleRows(PDO $pdo, int $majorId): array
{
    $stmt = $pdo->prepare(
        'SELECT
            m.id AS module_id,
            m.type AS module_type,
            c.id AS course_id,
            c.code AS course_code,
            c.name AS course_name,
            c.block_id,
            c.sort_order
        FROM modules m
        JOIN courses c ON m.course_id = c.id
        WHERE c.major_id = ?'
    );
    $stmt->execute([$majorId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    usort(
        $rows,
        static function (array $left, array $right): int {
            $leftBlockId = $left['block_id'] !== null ? (int) $left['block_id'] : PHP_INT_MAX;
            $rightBlockId = $right['block_id'] !== null ? (int) $right['block_id'] : PHP_INT_MAX;
            if ($leftBlockId !== $rightBlockId) {
                return $leftBlockId <=> $rightBlockId;
            }

            $leftSort = (int) ($left['sort_order'] ?? PHP_INT_MAX);
            $rightSort = (int) ($right['sort_order'] ?? PHP_INT_MAX);
            if ($leftSort !== $rightSort) {
                return $leftSort <=> $rightSort;
            }

            $leftCode = s($left['course_code']);
            $rightCode = s($right['course_code']);
            if ($leftCode !== $rightCode) {
                return strcmp($leftCode, $rightCode);
            }

            return ((int) $left['module_id']) <=> ((int) $right['module_id']);
        }
    );

    return $rows;
}

function groupModuleRowsByType(array $rows): array
{
    $grouped = [
        'Bắt buộc' => [
            'label' => '* Học phần bắt buộc',
            'rows' => [],
        ],
        'Tự chọn' => [
            'label' => '* Học phần tự chọn',
            'rows' => [],
        ],
    ];

    foreach ($rows as $row) {
        $type = s($row['module_type']);
        if ($type !== 'Bắt buộc') {
            $type = 'Tự chọn';
        }

        $grouped[$type]['rows'][] = $row;
    }

    return array_filter(
        $grouped,
        static fn(array $group): bool => !empty($group['rows'])
    );
}

function getTocDepthForType(string $type): int
{
    return match ($type) {
        'Bắt buộc' => 1,
        'Tự chọn' => 2,
        default => 2,
    };
}

function fetchModulePayload(PDO $pdo, int $moduleId): array
{
    $stmt = $pdo->prepare(
        'SELECT m.*, c.name AS course_name
         FROM modules m
         LEFT JOIN courses c ON m.course_id = c.id
         WHERE m.id = ?'
    );
    $stmt->execute([$moduleId]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$module) {
        throw new RuntimeException('Đề cương học phần không tồn tại.');
    }

    $module['total_hours'] = $module['total_hours'] ?? (($module['theory_hours'] ?? 0) + ($module['practical_hours'] ?? 0));
    $module['credits_theory'] = $module['credits_theory'] ?? 0;
    $module['credits_practice'] = $module['credits_practice'] ?? 0;

    $stmt = $pdo->prepare(
        "SELECT GROUP_CONCAT(c.code SEPARATOR ', ')
         FROM module_relationships mr
         JOIN courses c ON mr.related_course_id = c.id
         WHERE mr.module_id = ? AND mr.relation_type = 'Tiên quyết'"
    );
    $stmt->execute([$moduleId]);
    $module['prerequisite_modules_text'] = $stmt->fetchColumn() ?: ($module['prerequisite_modules'] ?? '');

    $stmt = $pdo->prepare(
        "SELECT GROUP_CONCAT(c.code SEPARATOR ', ')
         FROM module_relationships mr
         JOIN courses c ON mr.related_course_id = c.id
         WHERE mr.module_id = ? AND mr.relation_type = 'Song hành'"
    );
    $stmt->execute([$moduleId]);
    $module['parallel_modules_text'] = $stmt->fetchColumn() ?: ($module['parallel_modules'] ?? '');

    $stmt = $pdo->prepare(
        "SELECT GROUP_CONCAT(c.code SEPARATOR ', ')
         FROM module_relationships mr
         JOIN courses c ON mr.related_course_id = c.id
         WHERE mr.module_id = ? AND mr.relation_type = 'Học trước'"
    );
    $stmt->execute([$moduleId]);
    $module['previous_modules_text'] = $stmt->fetchColumn() ?: ($module['previous_modules'] ?? '');

    $stmt = $pdo->prepare(
        'SELECT GROUP_CONCAT(d.name SEPARATOR ", ")
         FROM module_departments md
         JOIN departments_list d ON md.department_id = d.id
         WHERE md.module_id = ?'
    );
    $stmt->execute([$moduleId]);
    $module['department_in_charge_text'] = $stmt->fetchColumn() ?: ($module['department_in_charge'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM clos WHERE module_id = ? ORDER BY id ASC');
    $stmt->execute([$moduleId]);
    $clos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        'SELECT a.*, GROUP_CONCAT(c.code SEPARATOR ", ") AS clos_codes
         FROM assessments a
         LEFT JOIN assessment_clos ac ON a.id = ac.assessment_id
         LEFT JOIN clos c ON ac.clo_id = c.id
         WHERE a.module_id = ?
         GROUP BY a.id
         ORDER BY a.id ASC'
    );
    $stmt->execute([$moduleId]);
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        'SELECT s.*, GROUP_CONCAT(c.code SEPARATOR ", ") AS clos_codes
         FROM self_study_activities s
         LEFT JOIN self_study_clos sc ON s.id = sc.self_study_activity_id
         LEFT JOIN clos c ON sc.clo_id = c.id
         WHERE s.module_id = ?
         GROUP BY s.id
         ORDER BY s.id ASC'
    );
    $stmt->execute([$moduleId]);
    $selfStudyActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        'SELECT t.*, GROUP_CONCAT(c.code SEPARATOR ", ") AS clos_codes
         FROM theory_topics t
         LEFT JOIN theory_topic_clos tc ON t.id = tc.theory_topic_id
         LEFT JOIN clos c ON tc.clo_id = c.id
         WHERE t.module_id = ?
         GROUP BY t.id
         ORDER BY t.id ASC'
    );
    $stmt->execute([$moduleId]);
    $theoryTopics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        'SELECT p.*, f.name AS facility_name, GROUP_CONCAT(c.code SEPARATOR ", ") AS clos_codes
         FROM practical_topics p
         LEFT JOIN practical_topic_clos pc ON p.id = pc.practical_topic_id
         LEFT JOIN clos c ON pc.clo_id = c.id
         LEFT JOIN facilities f ON p.facility_id = f.id
         WHERE p.module_id = ?
         GROUP BY p.id
         ORDER BY p.id ASC'
    );
    $stmt->execute([$moduleId]);
    $practicalTopics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        'SELECT cb.*, f.name AS facility_name, GROUP_CONCAT(c.code SEPARATOR ", ") AS clos_codes
         FROM combined_topics cb
         LEFT JOIN combined_topic_clos cbc ON cb.id = cbc.combined_topic_id
         LEFT JOIN clos c ON cbc.clo_id = c.id
         LEFT JOIN facilities f ON cb.facility_id = f.id
         WHERE cb.module_id = ?
         GROUP BY cb.id
         ORDER BY cb.id ASC'
    );
    $stmt->execute([$moduleId]);
    $combinedTopics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT * FROM resources WHERE module_id = ? ORDER BY resource_type ASC, sort_order ASC');
    $stmt->execute([$moduleId]);
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'module' => $module,
        'clos' => $clos,
        'assessments' => $assessments,
        'selfStudyActivities' => $selfStudyActivities,
        'theoryTopics' => $theoryTopics,
        'practicalTopics' => $practicalTopics,
        'combinedTopics' => $combinedTopics,
        'resources' => $resources,
    ];
}

function buildWordDocument(): array
{
    $phpWord = new PhpWord();
    $phpWord->getSettings()->setThemeFontLang(new Language('vi-VN'));
    // The export already updates TOC/page fields through Word automation.
    // Leaving updateFields enabled makes Word recalculate the layout again
    // when someone opens the file on another machine, which causes drift.
    $phpWord->getSettings()->setUpdateFields(false);
    Settings::setOutputEscapingEnabled(true);
    Settings::setDefaultFontName('Times New Roman');
    Settings::setDefaultFontSize(12);

    $fontName = 'Times New Roman';
    $fontSize = 12;
    $fontSizeSmall = 11;

    $tocParagraphStyle = [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineHeight' => 1.0,
        'indentation' => ['left' => 0, 'firstLine' => 0, 'hanging' => 0],
        'contextualSpacing' => true,
    ];

    foreach (['TOC1', 'TOC2', 'TOC3', 'TOC4'] as $tocStyleName) {
        $phpWord->addFontStyle(
            $tocStyleName,
            ['name' => $fontName, 'size' => $fontSize, 'bold' => false, 'color' => '000000'],
            $tocParagraphStyle
        );
    }

    $phpWord->addLinkStyle('Hyperlink', ['name' => $fontName, 'size' => $fontSize, 'color' => '000000']);

    $styles = [
        'fontName' => $fontName,
        'coverMetaFont' => ['name' => $fontName, 'size' => 14, 'bold' => true],
        'coverTitleFont' => ['name' => $fontName, 'size' => 24, 'bold' => true],
        'coverProgramFont' => ['name' => $fontName, 'size' => 14, 'bold' => true],
        'titleFont' => ['name' => $fontName, 'size' => 14, 'bold' => true],
        'heading1Font' => ['name' => $fontName, 'size' => $fontSize, 'bold' => true],
        'heading2Font' => ['name' => $fontName, 'size' => $fontSize, 'bold' => true],
        'normalFont' => ['name' => $fontName, 'size' => $fontSize],
        'smallFont' => ['name' => $fontName, 'size' => $fontSizeSmall],
        'schoolMinistryFont' => ['name' => $fontName, 'size' => 12, 'bold' => false],
        'schoolNameFont' => ['name' => $fontName, 'size' => 14, 'bold' => true],
        'centerPara' => ['alignment' => Jc::CENTER, 'spaceAfter' => 80],
        'leftPara' => ['alignment' => Jc::START, 'spaceAfter' => 80],
        'leftParaSmall' => ['alignment' => Jc::START, 'spaceAfter' => 40],
        'justifyPara' => ['alignment' => Jc::BOTH, 'spaceAfter' => 120],
        'noBorderCell' => [
            'borderTopSize' => 0,
            'borderBottomSize' => 0,
            'borderLeftSize' => 0,
            'borderRightSize' => 0,
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
            'cellMargin' => 0,
        ],
        'tableStyle' => [
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 80,
            'width' => 100 * 50,
            'unit' => TblWidth::PERCENT,
        ],
        'tableText' => [
            'headerCell' => [],
            'headerFont' => ['name' => $fontName, 'size' => $fontSizeSmall, 'bold' => true],
            'headerPara' => ['alignment' => Jc::CENTER, 'spaceAfter' => 40],
            'dataCell' => [],
            'dataFont' => ['name' => $fontName, 'size' => $fontSizeSmall],
            'dataPara' => ['alignment' => Jc::START, 'spaceAfter' => 40],
        ],
        'sectionStyle' => [
            'pageSizeW' => 12240,
            'pageSizeH' => 15840,
            'marginTop' => 1440,
            'marginRight' => 1440,
            'marginBottom' => 1440,
            'marginLeft' => 1620,
            'headerHeight' => 720,
            'footerHeight' => 720,
        ],
        'bodySectionStyle' => [
            'pageSizeW' => 12240,
            'pageSizeH' => 15840,
            'marginTop' => 1440,
            'marginRight' => 1440,
            'marginBottom' => 1440,
            'marginLeft' => 1620,
            'headerHeight' => 720,
            'footerHeight' => 720,
            'pageNumberingStart' => 1,
        ],
    ];

    for ($level = 1; $level <= 4; $level++) {
        $phpWord->addTitleStyle(
            $level,
            ['name' => $fontName, 'size' => 1, 'color' => 'FFFFFF'],
            ['spaceBefore' => 0, 'spaceAfter' => 0, 'alignment' => Jc::START]
        );
    }

    return [$phpWord, $styles];
}

function addMajorCoverPage(PhpWord $phpWord, array $major, array $styles): void
{
    $meta = resolveMajorMetadata(s($major['name']));

    $section = $phpWord->addSection($styles['sectionStyle']);

    $ministryTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'width' => 100 * 50,
        'unit' => TblWidth::PERCENT,
    ]);
    $row = $ministryTable->addRow();
    $row->addCell(3600, $styles['noBorderCell'])->addText('BỘ Y TẾ', $styles['coverMetaFont'], ['alignment' => Jc::START]);
    $row->addCell(6400, $styles['noBorderCell'])->addText('BỘ GIÁO DỤC VÀ ĐÀO TẠO', $styles['coverMetaFont'], ['alignment' => Jc::END]);

    $section->addText('TRƯỜNG ĐẠI HỌC Y DƯỢC CẦN THƠ', $styles['coverMetaFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 240]);
    $section->addTextBreak(6);
    $section->addText('ĐỀ CƯƠNG CHI TIẾT HỌC PHẦN', $styles['coverTitleFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 160]);
    $section->addText('TÊN NGÀNH: ' . upper(s($major['name'])), $styles['coverProgramFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 80]);

    if ($meta['english_name'] !== '') {
        $section->addText($meta['english_name'], $styles['coverProgramFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 80]);
    }

    $detailTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
        'width' => 100 * 50,
        'unit' => TblWidth::PERCENT,
    ]);
    $row = $detailTable->addRow();
    $row->addCell(3400, $styles['noBorderCell']);
    $detailCell = $row->addCell(6600, $styles['noBorderCell']);
    $detailCell->addText('MÃ NGÀNH: ' . s($meta['code']), $styles['coverProgramFont'], ['alignment' => Jc::START, 'spaceAfter' => 80]);
    $detailCell->addText('TRÌNH ĐỘ: ĐẠI HỌC', $styles['coverProgramFont'], ['alignment' => Jc::START, 'spaceAfter' => 80]);

    $section->addTextBreak(11);
    $section->addText('CẦN THƠ, NĂM ' . date('Y'), $styles['coverMetaFont'], ['alignment' => Jc::CENTER]);
}

function addTocSection(PhpWord $phpWord, array $styles, array $groupedModuleRows): void
{
    $section = $phpWord->addSection($styles['sectionStyle']);
    $section->addText('MỤC LỤC', $styles['coverMetaFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 120]);
    $section->addText('1. Kiến thức giáo dục đại cương', $styles['heading1Font'], ['alignment' => Jc::START, 'spaceAfter' => 20]);
    $section->addText('    1.1 Kiến thức chung', $styles['heading1Font'], ['alignment' => Jc::START, 'spaceAfter' => 20]);
    $section->addText('    1.2. Kiến thức cơ sở khối ngành', $styles['heading1Font'], ['alignment' => Jc::START, 'spaceAfter' => 20]);
    $section->addText('2. Kiến thức giáo dục chuyên nghiệp', $styles['heading1Font'], ['alignment' => Jc::START, 'spaceAfter' => 20]);
    $section->addText('    2.1. Kiến thức cơ sở của ngành', $styles['heading1Font'], ['alignment' => Jc::START, 'spaceAfter' => 20]);

    foreach ($groupedModuleRows as $type => $group) {
        $section->addText($group['label'], $styles['heading1Font'], ['alignment' => Jc::START, 'spaceAfter' => 20]);

        if (empty($group['rows'])) {
            continue;
        }

        $depth = getTocDepthForType($type);
        $section->addTOC(
            ['name' => $styles['fontName'], 'size' => 12],
            ['tabLeader' => TocStyle::TAB_LEADER_DOT, 'tabPos' => 9180, 'indent' => 0],
            $depth,
            $depth
        );
    }
}

function addMajorBodySection(PhpWord $phpWord, array $styles): object
{
    $section = $phpWord->addSection($styles['bodySectionStyle']);
    $footer = $section->addFooter();
    $footer->addPreserveText('{PAGE}', ['name' => $styles['fontName'], 'size' => 12], ['alignment' => Jc::END]);

    return $section;
}

function addHiddenTocTitle(object $section, string $text, int $level): void
{
    $section->addTitle($text, $level);
}

function addModuleBody(object $section, array $payload, array $styles, bool $withPageBreak): void
{
    $module = $payload['module'];
    $clos = $payload['clos'];
    $assessments = $payload['assessments'];
    $selfStudyActivities = $payload['selfStudyActivities'];
    $theoryTopics = $payload['theoryTopics'];
    $practicalTopics = $payload['practicalTopics'];
    $combinedTopics = $payload['combinedTopics'];
    $resources = $payload['resources'];

    $headerTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 0,
    ]);
    $row = $headerTable->addRow();

    $logoCell = $row->addCell(2000, $styles['noBorderCell']);
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

    $schoolCell = $row->addCell(8500, $styles['noBorderCell']);
    $schoolCell->addText('BỘ Y TẾ', $styles['schoolMinistryFont'], ['alignment' => Jc::CENTER]);
    $schoolCell->addText('TRƯỜNG ĐẠI HỌC Y DƯỢC CẦN THƠ', $styles['schoolNameFont'], ['alignment' => Jc::CENTER]);

    $section->addTextBreak(2);
    $section->addText('ĐỀ CƯƠNG CHI TIẾT HỌC PHẦN', $styles['titleFont'], $styles['centerPara']);
    $section->addText(upper(s($module['name'])), $styles['titleFont'], $styles['centerPara']);
    $section->addTextBreak(1);

    $section->addText('1. THÔNG TIN HỌC PHẦN', $styles['heading1Font'], ['alignment' => Jc::START, 'spaceAfter' => 120]);

    $infoTable = $section->addTable([
        'borderSize' => 0,
        'borderColor' => 'FFFFFF',
        'cellMargin' => 60,
    ]);
    $cellStyle = ['valign' => 'center'];

    $row = $infoTable->addRow();
    $row->addCell(4200, $cellStyle)->addText('Mã học phần: ' . s($module['code']), $styles['normalFont'], $styles['leftParaSmall']);
    $row->addCell(2900, $cellStyle);
    $row->addCell(2900, $cellStyle);

    $row = $infoTable->addRow();
    $row->addCell(4200, $cellStyle)->addText('Học phần bắt buộc/ điều kiện/ tự chọn: ', $styles['normalFont'], $styles['leftParaSmall']);
    $row->addCell(5800, array_merge($cellStyle, ['gridSpan' => 2]))->addText(s($module['type']), $styles['normalFont'], $styles['leftParaSmall']);

    $row = $infoTable->addRow();
    $row->addCell(4200, $cellStyle)->addText('Tổng số tín chỉ: ' . s($module['credits']), $styles['normalFont'], $styles['leftParaSmall']);
    $row->addCell(2900, $cellStyle)->addText('Lý thuyết: ' . s($module['credits_theory']), $styles['normalFont'], $styles['leftParaSmall']);
    $row->addCell(2900, $cellStyle)->addText('Thực hành: ' . s($module['credits_practice']), $styles['normalFont'], $styles['leftParaSmall']);

    $row = $infoTable->addRow();
    $row->addCell(4200, $cellStyle)->addText('Phân bổ thời gian (tiết): ' . s($module['total_hours']), $styles['normalFont'], $styles['leftParaSmall']);
    $row->addCell(2900, $cellStyle)->addText('Lý thuyết: ' . s($module['theory_hours']), $styles['normalFont'], $styles['leftParaSmall']);
    $row->addCell(2900, $cellStyle)->addText('Thực hành: ' . s($module['practical_hours']), $styles['normalFont'], $styles['leftParaSmall']);

    $fullWidthRows = [
        'Số giờ tự học (tiết): ' . s($module['self_study_hours']),
        'Đối tượng người học (dự kiến): ' . s($module['target_programs']),
        'Học kỳ và năm dự kiến học: HK ' . s($module['expected_semester']) . ' - ' . s($module['expected_year']),
        'Học phần tiên quyết: ' . (s($module['prerequisite_modules_text']) ?: 'Không'),
        'Học phần song hành: ' . (s($module['parallel_modules_text']) ?: 'Không'),
        'Học phần học trước: ' . (s($module['previous_modules_text']) ?: 'Không'),
        'Bộ môn tham gia giảng dạy: ' . s($module['department_in_charge_text']),
        'Ban điều phối học phần: ' . s($module['coordinating_board']),
        'Khoa phụ trách: ' . s($module['faculty_in_charge']),
    ];

    foreach ($fullWidthRows as $text) {
        $row = $infoTable->addRow();
        $row->addCell(10000, ['gridSpan' => 3, 'valign' => 'center'])->addText($text, $styles['normalFont'], $styles['leftParaSmall']);
    }

    $section->addTextBreak(1);
    $section->addText('2. MÔ TẢ HỌC PHẦN', $styles['heading1Font'], $styles['leftPara']);
    $section->addText("\u{2003}\u{2003}" . s($module['description']), $styles['normalFont'], $styles['justifyPara']);
    $section->addTextBreak(1);

    $section->addText('3. MỤC TIÊU VÀ CHUẨN ĐẦU RA HỌC PHẦN', $styles['heading1Font'], ['alignment' => Jc::START, 'spaceAfter' => 80]);
    $section->addText('3.1. Mục tiêu', $styles['heading2Font'], $styles['leftPara']);
    foreach (preg_split('/\r\n|\r|\n/', trim(s($module['objectives']))) as $line) {
        if (trim($line) === '') {
            continue;
        }
        $section->addText("\u{2003}\u{2003}" . trim($line), $styles['normalFont'], ['alignment' => Jc::BOTH, 'spaceAfter' => 40]);
    }

    $section->addTextBreak(1);
    $section->addText('3.2. Chuẩn đầu ra học phần (Bloom)', $styles['heading2Font'], $styles['leftPara']);
    $cloTable = $section->addTable($styles['tableStyle']);
    addTableHeader(
        $cloTable,
        ['Lĩnh vực', 'Mức độ Bloom Taxonomy', 'TT', 'Chuẩn đầu ra học phần'],
        [2800, 2800, 800, 3600],
        $styles['tableText']
    );

    if (!empty($clos)) {
        foreach ($clos as $clo) {
            $row = $cloTable->addRow();
            $row->addCell(2800)->addText(s($clo['domain']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
            $row->addCell(2800)->addText(s($clo['bloom_level']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
            $row->addCell(800)->addText(s($clo['code']), $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(3600)->addText(s($clo['description']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
        }
    } else {
        $row = $cloTable->addRow();
        $row->addCell(10000, ['gridSpan' => 4])->addText('Chưa cấu hình dữ liệu CLO', $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER]);
    }

    $section->addTextBreak(1);
    $section->addText('4. PHƯƠNG PHÁP KIỂM TRA, LƯỢNG GIÁ HỌC PHẦN', $styles['heading1Font'], ['alignment' => Jc::START, 'spaceAfter' => 80]);

    $section->addText('4.1. Thang điểm lượng giá', $styles['heading2Font'], $styles['leftPara']);
    $gradingScale = s($module['grading_scale']) ?: 'Học phần được lượng giá theo thang điểm 10.';
    foreach (preg_split('/\r\n|\r|\n/', trim($gradingScale)) as $line) {
        if (trim($line) === '') {
            continue;
        }
        $section->addText("\u{2003}\u{2003}" . trim($line), $styles['normalFont'], ['alignment' => Jc::BOTH, 'spaceAfter' => 20]);
    }

    $section->addTextBreak(1);
    $section->addText('4.2. Phương pháp kiểm tra lượng giá', $styles['heading2Font'], $styles['leftPara']);
    $assessTable = $section->addTable($styles['tableStyle']);
    addTableHeader(
        $assessTable,
        ['CLOs', 'PLO/PI liên quan', 'Hình thức đánh giá', 'Công cụ đánh giá', 'Trọng số (%)'],
        [1800, 1800, 2800, 2800, 900],
        $styles['tableText']
    );

    if (!empty($assessments)) {
        foreach ($assessments as $assessment) {
            $row = $assessTable->addRow();
            $row->addCell(1800)->addText(s($assessment['clos_codes'] ?: '---'), $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(1800)->addText(s($assessment['plo_pi']), $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(2800)->addText(s($assessment['form']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
            $row->addCell(2800)->addText(s($assessment['tool']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
            $row->addCell(900)->addText(s($assessment['weight']) . '%', $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
        }
    } else {
        $row = $assessTable->addRow();
        $row->addCell(10100, ['gridSpan' => 5])->addText('Chưa có phương pháp đánh giá nào.', $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER]);
    }

    $section->addTextBreak(1);
    $section->addText('4.3. Lượng giá hoạt động tự học', $styles['heading2Font'], $styles['leftPara']);
    $selfTable = $section->addTable($styles['tableStyle']);
    addTableHeader(
        $selfTable,
        ['Hoạt động tự học', 'CLOs liên quan', 'Thời lượng (giờ)', 'Phương pháp tự học', 'Cách thức đánh giá', 'Minh chứng'],
        [2600, 1400, 1000, 2000, 1800, 1200],
        $styles['tableText']
    );

    if (!empty($selfStudyActivities)) {
        foreach ($selfStudyActivities as $activity) {
            $row = $selfTable->addRow();
            $row->addCell(2600)->addText(s($activity['activity_name']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
            $row->addCell(1400)->addText(s($activity['clos_codes'] ?: '---'), $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(1000)->addText(s($activity['duration_hours']), $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(2000)->addText(s($activity['method']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
            $row->addCell(1800)->addText(s($activity['assessment_method']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
            $row->addCell(1200)->addText(s($activity['evidence']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
        }
    } else {
        $row = $selfTable->addRow();
        $row->addCell(10000, ['gridSpan' => 6])->addText('Chưa thiết lập nội dung lượng giá hoạt động tự học.', $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER]);
    }

    $section->addTextBreak(1);
    $section->addText('5. NỘI DUNG HỌC PHẦN VÀ PHƯƠNG PHÁP DẠY - HỌC', $styles['heading1Font'], ['alignment' => Jc::START, 'spaceAfter' => 80]);

    $section->addText('5.1. Lý thuyết', $styles['heading2Font'], $styles['leftPara']);
    $theoryTable = $section->addTable($styles['tableStyle']);
    addTableHeader(
        $theoryTable,
        ['Chương/Bài', 'Nội dung lý thuyết', 'Hình thức dạy', 'Tiết trên lớp', 'Tiết tự học', 'CLOs đạt được', 'Tài liệu liên quan'],
        [1200, 3000, 1500, 800, 800, 1000, 1700],
        $styles['tableText']
    );

    if (!empty($theoryTopics)) {
        foreach ($theoryTopics as $topic) {
            $row = $theoryTable->addRow();
            $row->addCell(1200)->addText(s($topic['chapter']), $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(3000)->addText(s($topic['title']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
            $row->addCell(1500)->addText(s($topic['method']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
            $row->addCell(800)->addText(s($topic['class_hours']), $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(800)->addText(s($topic['self_study_hours']), $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(1000)->addText(s($topic['clos_codes']), $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(1700)->addText(s($topic['textbook_info']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
        }
    } else {
        $row = $theoryTable->addRow();
        $row->addCell(11000, ['gridSpan' => 7])->addText('Chưa thiết lập bài giảng lý thuyết', $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER]);
    }

    $section->addTextBreak(1);
    $section->addText('5.2. Thực hành', $styles['heading2Font'], $styles['leftPara']);
    $practicalTable = $section->addTable($styles['tableStyle']);
    addTableHeader(
        $practicalTable,
        ['Chủ đề', 'Nội dung chi tiết / Kỹ năng', 'Hình thức tổ chức', 'Số tiết TH', 'CLOs đạt được', 'Cơ sở thực hành'],
        [1500, 3500, 1800, 900, 1200, 1100],
        $styles['tableText']
    );

    if (!empty($practicalTopics)) {
        foreach ($practicalTopics as $topic) {
            $row = $practicalTable->addRow();
            $row->addCell(1500)->addText(s($topic['topic']), $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(3500)->addText(s($topic['content']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
            $row->addCell(1800)->addText(s($topic['method']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
            $row->addCell(900)->addText(s($topic['lab_hours']), $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(1200)->addText(s($topic['clos_codes']), $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(1100)->addText(s($topic['facility_name'] ?? 'Chưa bố trí'), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
        }
    } else {
        $row = $practicalTable->addRow();
        $row->addCell(10000, ['gridSpan' => 6])->addText('Chưa thiết lập nội dung thực hành', $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER]);
    }

    $section->addTextBreak(1);
    $section->addText('5.3. Lý thuyết và Thực hành tích hợp (chung)', $styles['heading2Font'], $styles['leftPara']);
    $combinedTable = $section->addTable($styles['tableStyle']);
    addTableHeader(
        $combinedTable,
        ['STT', 'Nội dung chính tích hợp', 'Hình thức dạy học', 'Tiết LT', 'Tiết TH', 'Tiết tự học', 'CLOs đạt được', 'Cơ sở thực hành'],
        [600, 2800, 1800, 700, 700, 900, 1000, 1500],
        $styles['tableText']
    );

    if (!empty($combinedTopics)) {
        $stt = 1;
        foreach ($combinedTopics as $topic) {
            $row = $combinedTable->addRow();
            $row->addCell(600)->addText((string) $stt++, $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(2800)->addText(s($topic['content']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
            $row->addCell(1800)->addText(s($topic['method']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
            $row->addCell(700)->addText(s($topic['theory_hours']), $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(700)->addText(s($topic['practical_hours']), $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(900)->addText(s($topic['self_study_hours']), $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(1000)->addText(s($topic['clos_codes']), $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(1500)->addText(s($topic['facility_name'] ?? 'Chưa bố trí'), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
        }
    } else {
        $row = $combinedTable->addRow();
        $row->addCell(11000, ['gridSpan' => 8])->addText('Chưa cấu hình nội dung tích hợp chung', $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER]);
    }

    $section->addTextBreak(1);
    $section->addText('6. TÀI LIỆU DẠY VÀ HỌC', $styles['heading1Font'], ['alignment' => Jc::START, 'spaceAfter' => 80]);

    $teachingResources = array_filter($resources, static fn(array $row): bool => $row['resource_type'] === 'Tài liệu giảng dạy');
    $selfResources = array_filter($resources, static fn(array $row): bool => $row['resource_type'] === 'Tài liệu tự học');
    $resourceColumns = ['STT', 'Tên giáo trình / Tài liệu', 'Chủ biên', 'Nhà xuất bản', 'Năm XB', 'Số định danh thư viện'];
    $resourceWidths = [500, 3800, 1800, 1800, 700, 1400];

    $section->addText('6.1. Tài liệu giảng dạy', $styles['heading2Font'], $styles['leftPara']);
    $teachingTable = $section->addTable($styles['tableStyle']);
    addTableHeader($teachingTable, $resourceColumns, $resourceWidths, $styles['tableText']);

    if (!empty($teachingResources)) {
        $index = 1;
        foreach ($teachingResources as $resource) {
            $row = $teachingTable->addRow();
            $row->addCell(500)->addText((string) $index++, $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(3800)->addText(s($resource['title']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
            $row->addCell(1800)->addText(s($resource['editor']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
            $row->addCell(1800)->addText(s($resource['publisher']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
            $row->addCell(700)->addText(s($resource['year']), $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(1400)->addText(s($resource['identifier']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
        }
    } else {
        $row = $teachingTable->addRow();
        $row->addCell(10000, ['gridSpan' => 6])->addText('Chưa thiết lập danh mục tài liệu giảng dạy', $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER]);
    }

    $section->addTextBreak(1);
    $section->addText('6.2. Tài liệu tự học', $styles['heading2Font'], $styles['leftPara']);
    $selfTable = $section->addTable($styles['tableStyle']);
    addTableHeader($selfTable, $resourceColumns, $resourceWidths, $styles['tableText']);

    if (!empty($selfResources)) {
        $index = 1;
        foreach ($selfResources as $resource) {
            $row = $selfTable->addRow();
            $row->addCell(500)->addText((string) $index++, $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(3800)->addText(s($resource['title']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
            $row->addCell(1800)->addText(s($resource['editor']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
            $row->addCell(1800)->addText(s($resource['publisher']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
            $row->addCell(700)->addText(s($resource['year']), $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER, 'spaceAfter' => 40]);
            $row->addCell(1400)->addText(s($resource['identifier']), $styles['tableText']['dataFont'], $styles['tableText']['dataPara']);
        }
    } else {
        $row = $selfTable->addRow();
        $row->addCell(10000, ['gridSpan' => 6])->addText('Chưa thiết lập danh mục tài liệu tự học', $styles['tableText']['dataFont'], ['alignment' => Jc::CENTER]);
    }

    if ($withPageBreak) {
        $section->addPageBreak();
    }
}

function createTemporaryDocxPath(string $prefix): string
{
    $temporaryBase = tempnam(sys_get_temp_dir(), $prefix);
    if ($temporaryBase === false) {
        throw new RuntimeException('Không thể tạo tệp tạm để xuất Word.');
    }

    @unlink($temporaryBase);

    return $temporaryBase . '.docx';
}

function updateWordFields(string $documentPath): void
{
    $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . '_support' . DIRECTORY_SEPARATOR . 'update_word_fields.ps1';
    if (!is_file($scriptPath)) {
        throw new RuntimeException('Thiếu script cập nhật mục lục và số trang.');
    }

    $command = 'powershell -NoProfile -ExecutionPolicy Bypass -File '
        . escapeshellarg($scriptPath)
        . ' -DocumentPath '
        . escapeshellarg($documentPath);

    exec($command . ' 2>&1', $output, $exitCode);

    if ($exitCode !== 0) {
        $message = trim(implode("\n", $output));
        if ($message === '') {
            $message = 'Word không thể cập nhật mục lục và số trang tự động.';
        }

        throw new RuntimeException($message);
    }
}

function findWordChildElement(\DOMElement $parent, string $localName): ?\DOMElement
{
    foreach ($parent->childNodes as $childNode) {
        if ($childNode instanceof \DOMElement
            && $childNode->namespaceURI === 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'
            && $childNode->localName === $localName
        ) {
            return $childNode;
        }
    }

    return null;
}

function ensureWordChildElement(\DOMDocument $document, \DOMElement $parent, string $localName): \DOMElement
{
    $child = findWordChildElement($parent, $localName);
    if ($child instanceof \DOMElement) {
        return $child;
    }

    $child = $document->createElementNS(
        'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
        'w:' . $localName
    );
    $parent->appendChild($child);

    return $child;
}

function setWordAttribute(\DOMElement $element, string $name, string $value): void
{
    $element->setAttributeNS(
        'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
        'w:' . $name,
        $value
    );
}

function normalizeTocStylesInDocx(string $documentPath, string $fontName, int $fontSize): void
{
    $zip = new ZipArchive();
    if ($zip->open($documentPath) !== true) {
        throw new RuntimeException('Không thể mở tệp Word để chuẩn hóa mục lục.');
    }

    try {
        $stylesXml = $zip->getFromName('word/styles.xml');
        if ($stylesXml === false) {
            return;
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;

        if (!@$document->loadXML($stylesXml)) {
            throw new RuntimeException('Không thể đọc cấu hình style của tệp Word.');
        }

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $tocStyles = $xpath->query('//w:style[starts-with(@w:styleId, "TOC")]');
        if ($tocStyles === false) {
            return;
        }

        foreach ($tocStyles as $style) {
            if (!$style instanceof \DOMElement) {
                continue;
            }

            $paragraphProperties = ensureWordChildElement($document, $style, 'pPr');

            $spacing = ensureWordChildElement($document, $paragraphProperties, 'spacing');
            setWordAttribute($spacing, 'after', '0');
            setWordAttribute($spacing, 'line', '240');
            setWordAttribute($spacing, 'lineRule', 'auto');

            $indent = ensureWordChildElement($document, $paragraphProperties, 'ind');
            setWordAttribute($indent, 'left', '0');
            setWordAttribute($indent, 'firstLine', '0');
            setWordAttribute($indent, 'hanging', '0');

            ensureWordChildElement($document, $paragraphProperties, 'contextualSpacing');

            $runProperties = ensureWordChildElement($document, $style, 'rPr');

            $fonts = ensureWordChildElement($document, $runProperties, 'rFonts');
            setWordAttribute($fonts, 'ascii', $fontName);
            setWordAttribute($fonts, 'eastAsia', $fontName);
            setWordAttribute($fonts, 'hAnsi', $fontName);
            setWordAttribute($fonts, 'cs', $fontName);

            $color = ensureWordChildElement($document, $runProperties, 'color');
            setWordAttribute($color, 'val', '000000');

            $size = ensureWordChildElement($document, $runProperties, 'sz');
            setWordAttribute($size, 'val', (string) ($fontSize * 2));

            $sizeComplexScript = ensureWordChildElement($document, $runProperties, 'szCs');
            setWordAttribute($sizeComplexScript, 'val', (string) ($fontSize * 2));
        }

        $zip->addFromString('word/styles.xml', $document->saveXML());
    } finally {
        $zip->close();
    }
}

$majorId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($majorId <= 0) {
    http_response_code(400);
    exit('Không tìm thấy ngành đào tạo hợp lệ.');
}

$major = fetchMajor($pdo, $majorId);
if (!$major) {
    http_response_code(404);
    exit('Ngành đào tạo không tồn tại.');
}

$moduleRows = fetchMajorModuleRows($pdo, $majorId);
if (empty($moduleRows)) {
    http_response_code(404);
    exit('Ngành này chưa có đề cương học phần để xuất.');
}

$groupedModuleRows = groupModuleRowsByType($moduleRows);
$totalModules = count($moduleRows);

[$phpWord, $styles] = buildWordDocument();

addMajorCoverPage($phpWord, $major, $styles);
addTocSection($phpWord, $styles, $groupedModuleRows);
$bodySection = addMajorBodySection($phpWord, $styles);

$renderedModules = 0;
foreach ($groupedModuleRows as $type => $group) {
    foreach ($group['rows'] as $row) {
        $payload = fetchModulePayload($pdo, (int) $row['module_id']);
        addHiddenTocTitle($bodySection, s($payload['module']['name']), getTocDepthForType($type));

        $renderedModules++;
        addModuleBody($bodySection, $payload, $styles, $renderedModules < $totalModules);
    }
}

$safeMajorName = preg_replace('/[^A-Za-z0-9_\-]/', '_', s($major['name']));
$filename = 'Quyen_De_Cuong_' . $safeMajorName . '.docx';

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit("Khong the xuat file Word vi PHP extension 'zip' chua duoc bat. Hay bo comment 'extension=zip' trong php.ini va khoi dong lai web server.");
}

$temporaryDocxPath = createTemporaryDocxPath('major_word_');

try {
    $writer = IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save($temporaryDocxPath);
    updateWordFields($temporaryDocxPath);
    normalizeTocStylesInDocx($temporaryDocxPath, $styles['fontName'], 12);

    clearstatcache(true, $temporaryDocxPath);

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($temporaryDocxPath));
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    readfile($temporaryDocxPath);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit($e->getMessage());
} finally {
    if (is_file($temporaryDocxPath)) {
        @unlink($temporaryDocxPath);
    }
}

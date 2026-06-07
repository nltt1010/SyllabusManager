<?php
// declare(encoding='UTF-8');

mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

ini_set('display_errors', 0);

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


$module['total_hours']          = $module['total_hours']          ?? (($module['theory_hours'] ?? 0) + ($module['practical_hours'] ?? 0));
$module['credits_theory']       = $module['credits_theory']       ?? 0;
$module['credits_practice']     = $module['credits_practice']     ?? 0;

// Lấy danh sách Học phần tiên quyết từ bảng liên kết
$stmt = $pdo->prepare("SELECT GROUP_CONCAT(c.code SEPARATOR ', ') FROM module_relationships mr JOIN courses c ON mr.related_course_id = c.id WHERE mr.module_id = ? AND mr.relation_type = 'Tiên quyết'");
$stmt->execute([$id]);
$module['prerequisite_modules_text'] = $stmt->fetchColumn() ?: ($module['prerequisite_modules'] ?? '');

// Lấy danh sách Học phần song hành
$stmt = $pdo->prepare("SELECT GROUP_CONCAT(c.code SEPARATOR ', ') FROM module_relationships mr JOIN courses c ON mr.related_course_id = c.id WHERE mr.module_id = ? AND mr.relation_type = 'Song hành'");
$stmt->execute([$id]);
$module['parallel_modules_text'] = $stmt->fetchColumn() ?: ($module['parallel_modules'] ?? '');

// Lấy danh sách Học phần học 
$stmt = $pdo->prepare("SELECT GROUP_CONCAT(c.code SEPARATOR ', ') FROM module_relationships mr JOIN courses c ON mr.related_course_id = c.id WHERE mr.module_id = ? AND mr.relation_type = 'Học trước'");
$stmt->execute([$id]);
$module['previous_modules_text'] = $stmt->fetchColumn() ?: ($module['previous_modules'] ?? '');

// Lấy danh sách Bộ môn
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


// Helper an toàn chuỗi
function s(?string $val): string {
    return trim($val ?? '');
}

// =====================================================================
// 2. DỰNG LAYOUT VĂN BẢN BẰNG HTML & CSS CHI TIẾT
// =====================================================================
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: "timesnewroman", Times, serif; font-size: 12pt; line-height: 1.4; color: #000; }
        
        /* Định dạng khung Header dùng bảng 3 cột để giữ cân bằng */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        /* Ép buộc tất cả các ô trong bảng header KHÔNG ĐƯỢC CÓ VIỀN */
        .header-table td {
            border: none !important;
            padding: 0 !important;
            vertical-align: middle; /* Ép logo và chữ nằm trên cùng 1 dòng ngang */
        }
        .cell-logo {
            width: 70px; /* Cột bên trái chứa logo */
        }
        .cell-text {
            text-align: center; /* Cột giữa chứa văn bản căn giữa toàn trang */
        }
        .cell-balance {
            width: 70px; /* Cột ẩn bên phải để bù trừ không gian, giúp chữ căn giữa chuẩn 100% trang */
        }
        
        .school-title { 
            font-size: 11pt; 
            font-weight: bold; 
            margin-bottom: 4px; 
            text-transform: uppercase;
        }
        
        .school-name { 
            font-size: 13pt; 
            font-weight: bold; 
            text-transform: uppercase;
        }
        
        .header-line {
            width: 150px;
            border-bottom: 1px solid #000;
            margin: 6px auto 0 auto;
        }
        
        /* Giữ nguyên các class phía dưới của bạn: .main-title, h1, h2, table, td, th... */
        .main-title { text-align: center; font-size: 14pt; font-weight: bold; margin: 25px 0; line-height: 1.3; }
        h1 { font-size: 12pt; font-weight: bold; margin-top: 25px; margin-bottom: 10px; text-transform: uppercase; }
        h2 { font-size: 12pt; font-weight: bold; margin-top: 15px; margin-bottom: 8px; }
        p { margin: 0 0 8px 0; text-align: justify; }
        .indent { text-indent: 35px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; table-layout: fixed; }
        th { font-size: 11pt; font-weight: bold; border: 1px solid #000000; padding: 6px 4px; text-align: center; background-color: #f2f2f2; }
        td { font-size: 11pt; border: 1px solid #000000; padding: 6px 5px; vertical-align: top; }
        .text-center { text-align: center; }
        table.info-table td { border: none; padding: 4px 0; font-size: 12pt; }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td class="cell-logo">';
                if (file_exists(__DIR__ . '/CTUMP_Logo.png')) {
                    $html .= '<img src="' . __DIR__ . '/CTUMP_Logo.png" style="width: 70px; height: 70px; display: block;">';
                } else {
                    $html .= '&nbsp;';
                }
                $html .= '
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
        ' . mb_strtoupper(s($module['name'])) . '
    </div>

    <h1>1. THÔNG TIN HỌC PHẦN</h1>
    <table class="info-table">
        <tr>
            <td width="42%">Mã học phần: ' . s($module['code']) . '</td>
            <td width="29%"></td>
            <td width="29%"></td>
        </tr>
        <tr>
            <td colspan="3">Học phần bắt buộc/ điều kiện/ tự chọn: ' . s($module['type']) . '</td>
        </tr>
        <tr>
            <td>Tổng số tín chỉ: ' . s($module['credits']) . '</td>
            <td>Lý thuyết: ' . s($module['credits_theory']) . '</td>
            <td>Thực hành: ' . s($module['credits_practice']) . '</td>
        </tr>
        <tr>
            <td>Phân bổ thời gian (tiết): ' . s($module['total_hours']) . '</td>
            <td>Lý thuyết: ' . s($module['theory_hours']) . '</td>
            <td>Thực hành: ' . s($module['practical_hours']) . '</td>
        </tr>
        <tr><td colspan="3">Số giờ tự học (tiết): ' . s($module['self_study_hours']) . '</td></tr>
        <tr><td colspan="3">Đối tượng người học (dự kiến): ' . s($module['target_programs']) . '</td></tr>
        <tr><td colspan="3">Học kỳ và năm dự kiến học: HK ' . s($module['expected_semester']) . ' - ' . s($module['expected_year']) . '</td></tr>
        <tr><td colspan="3">Học phần tiên quyết: ' . (s($module['prerequisite_modules_text']) ?: 'Không') . '</td></tr>
        <tr><td colspan="3">Học phần song hành: ' . (s($module['parallel_modules_text']) ?: 'Không') . '</td></tr>
        <tr><td colspan="3">Học phần học trước: ' . (s($module['previous_modules_text']) ?: 'Không') . '</td></tr>
        <tr><td colspan="3">Bộ môn tham gia giảng dạy: ' . s($module['department_in_charge_text']) . '</td></tr>
        <tr><td colspan="3">Ban điều phối học phần: ' . s($module['coordinating_board']) . '</td></tr>
        <tr><td colspan="3">Khoa phụ trách: ' . s($module['faculty_in_charge']) . '</td></tr>
    </table>

    <h1>2. MÔ TẢ HỌC PHẦN</h1>
    <p class="indent">' . htmlspecialchars(s($module['description'])) . '</p>

    <h1>3. MỤC TIÊU VÀ CHUẨN ĐẦU RA HỌC PHẦN</h1>
    <h2>3.1. Mục tiêu</h2>';
    foreach (preg_split('/\r\n|\r|\n/', trim(s($module['objectives']))) as $line) {
        if (trim($line) === '') continue;
        $html .= '<p class="indent">' . htmlspecialchars(trim($line)) . '</p>';
    }
    $html .= '
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
        <tbody>';
        if (!empty($clos)) {
            foreach ($clos as $c) {
                // 1. Tách chuỗi Lĩnh vực, làm sạch từng phần tử rồi mới nối bằng <br>
                $domainArr = explode(',', s($c['domain']));
                $domainCleaned = array_map(function($d) {
                    return htmlspecialchars(trim($d));
                }, $domainArr);
                $domainHtml = implode('<br>', $domainCleaned);

                // 2. Tách chuỗi Mức độ, làm sạch số thứ tự, loại ký tự đặc biệt rồi mới nối bằng <br>
                $bloomArr = explode(',', s($c['bloom_level']));
                $bloomCleaned = array_map(function($b) {
                    $b = trim($b);
                    // Nếu chuỗi có dạng "1. Nhớ" thì tách lấy chữ "Nhớ"
                    $pureText = (strpos($b, '. ') !== false) ? explode('. ', $b)[1] : $b;
                    return htmlspecialchars($pureText);
                }, $bloomArr);
                $bloomHtml = implode('<br>', $bloomCleaned);

                // 3. Đưa vào hàng của bảng HTML (Bỏ htmlspecialchars ở ngoài biến $domainHtml và $bloomHtml)
                $html .= '<tr>
                    <td style="vertical-align: top;">' . $domainHtml . '</td>
                    <td class="text-center" style="vertical-align: top;">' . $bloomHtml . '</td>
                    <td class="text-center" style="vertical-align: top;">' . htmlspecialchars(s($c['code'])) . '</td>
                    <td style="vertical-align: top;">' . nl2br(htmlspecialchars(s($c['description']))) . '</td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="4" class="text-center">Chưa cấu hình dữ liệu CLO</td></tr>';
        }
        $html .= '</tbody>
    </table>

    <h1>4. PHƯƠNG PHÁP KIỂM TRA, LƯỢNG GIÁ HỌC PHẦN</h1>
    <h2>4.1. Thang điểm lượng giá</h2>';
    $scale = s($module['grading_scale']) ?: 'Học phần được lượng giá theo thang điểm 10.';
    foreach (preg_split('/\r\n|\r|\n/', trim($scale)) as $line) {
        if (trim($line) === '') continue;
        $html .= '<p class="indent">' . htmlspecialchars(trim($line)) . '</p>';
    }
    $html .= '
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
        <tbody>';
        if (!empty($assessments)) {
            foreach ($assessments as $a) {
                $html .= '<tr>
                    <td class="text-center">' . htmlspecialchars(s($a['clos_codes'] ?: '---')) . '</td>
                    <td class="text-center">' . htmlspecialchars(s($a['plo_pi'])) . '</td>
                    <td>' . htmlspecialchars(s($a['form'])) . '</td>
                    <td>' . htmlspecialchars(s($a['tool'])) . '</td>
                    <td class="text-center">' . htmlspecialchars(s($a['weight'])) . '%</td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="5" class="text-center">Chưa có phương pháp đánh giá nào.</td></tr>';
        }
        $html .= '</tbody>
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
        <tbody>';
        if (!empty($selfStudyActivities)) {
            foreach ($selfStudyActivities as $s_act) {
                $html .= '<tr>
                    <td>' . htmlspecialchars(s($s_act['activity_name'])) . '</td>
                    <td class="text-center">' . htmlspecialchars(s($s_act['clos_codes'] ?: '---')) . '</td>
                    <td class="text-center">' . htmlspecialchars(s($s_act['duration_hours'])) . '</td>
                    <td>' . htmlspecialchars(s($s_act['method'])) . '</td>
                    <td>' . htmlspecialchars(s($s_act['assessment_method'])) . '</td>
                    <td>' . htmlspecialchars(s($s_act['evidence'])) . '</td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="6" class="text-center">Chưa thiết lập nội dung lượng giá hoạt động tự học.</td></tr>';
        }
        $html .= '</tbody>
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
        <tbody>';
        if (!empty($theoryTopics)) {
            foreach ($theoryTopics as $t) {
                $html .= '<tr>
                    <td class="text-center">' . htmlspecialchars(s($t['chapter'])) . '</td>
                    <td>' . htmlspecialchars(s($t['title'])) . '</td>
                    <td>' . htmlspecialchars(s($t['method'])) . '</td>
                    <td class="text-center">' . htmlspecialchars(s($t['class_hours'])) . '</td>
                    <td class="text-center">' . htmlspecialchars(s($t['self_study_hours'])) . '</td>
                    <td class="text-center">' . htmlspecialchars(s($t['clos_codes'])) . '</td>
                    <td>' . htmlspecialchars(s($t['textbook_info'])) . '</td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="7" class="text-center">Chưa thiết lập bài giảng lý thuyết</td></tr>';
        }
        $html .= '</tbody>
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
        <tbody>';
        if (!empty($practicalTopics)) {
            foreach ($practicalTopics as $p) {
                $html .= '<tr>
                    <td class="text-center">' . htmlspecialchars(s($p['topic'])) . '</td>
                    <td>' . htmlspecialchars(s($p['content'])) . '</td>
                    <td>' . htmlspecialchars(s($p['method'])) . '</td>
                    <td class="text-center">' . htmlspecialchars(s($p['lab_hours'])) . '</td>
                    <td class="text-center">' . htmlspecialchars(s($p['clos_codes'])) . '</td>
                    <td>' . htmlspecialchars(s($p['facility_name'] ?? 'Chưa bố trí')) . '</td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="6" class="text-center">Chưa thiết lập nội dung thực hành</td></tr>';
        }
        $html .= '</tbody>
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
        <tbody>';
        if (!empty($combinedTopics)) {
            $TT = 1;
            foreach ($combinedTopics as $cb) {
                $html .= '<tr>
                    <td class="text-center">' . $TT++ . '</td>
                    <td>' . htmlspecialchars(s($cb['content'])) . '</td>
                    <td>' . htmlspecialchars(s($cb['method'])) . '</td>
                    <td class="text-center">' . htmlspecialchars(s($cb['theory_hours'])) . '</td>
                    <td class="text-center">' . htmlspecialchars(s($cb['practical_hours'])) . '</td>
                    <td class="text-center">' . htmlspecialchars(s($cb['self_study_hours'])) . '</td>
                    <td class="text-center">' . htmlspecialchars(s($cb['clos_codes'])) . '</td>
                    <td>' . htmlspecialchars(s($cb['facility_name'] ?? 'Chưa bố trí')) . '</td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="8" class="text-center">Chưa cấu hình nội dung tích hợp chung</td></tr>';
        }
        $html .= '</tbody>
    </table>

    <h1>6. TÀI LIỆU DẠY VÀ HỌC</h1>';
    $teachRes = array_filter($resources, fn($r) => $r['resource_type'] === 'Tài liệu giảng dạy');
    $selfRes  = array_filter($resources, fn($r) => $r['resource_type'] === 'Tài liệu tự học');
    
    // 6.1 Tài liệu giảng dạy
    $html .= '<h2>6.1. Tài liệu giảng dạy</h2>
    <table>
        <thead>
            <tr>
                <th width="5%">TT</th>
                <th width="35%">Tên giáo trình / Tài liệu</th>
                <th width="18%">Chủ biên</th>
                <th width="18%">Nhà xuất bản</th>
                <th width="10%">Năm XB</th>
                <th width="14%">Số định dang cá biệt tại thư viện</th>
            </tr>
        </thead>
        <tbody>';
        if (!empty($teachRes)) {
            $i = 1;
            foreach ($teachRes as $r) {
                $html .= '<tr>
                    <td class="text-center">' . $i++ . '</td>
                    <td>' . htmlspecialchars(s($r['title'])) . '</td>
                    <td>' . htmlspecialchars(s($r['editor'])) . '</td>
                    <td>' . htmlspecialchars(s($r['publisher'])) . '</td>
                    <td class="text-center">' . htmlspecialchars(s($r['year'])) . '</td>
                    <td class="text-center">' . htmlspecialchars(s($r['identifier'])) . '</td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="6" class="text-center">Chưa thiết lập danh mục tài liệu giảng dạy</td></tr>';
        }
        $html .= '</tbody>
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
        <tbody>';
        if (!empty($selfRes)) {
            $i = 1;
            foreach ($selfRes as $r) {
                $html .= '<tr>
                    <td class="text-center">' . $i++ . '</td>
                    <td>' . htmlspecialchars(s($r['title'])) . '</td>
                    <td>' . htmlspecialchars(s($r['editor'])) . '</td>
                    <td>' . htmlspecialchars(s($r['publisher'])) . '</td>
                    <td class="text-center">' . htmlspecialchars(s($r['year'])) . '</td>
                    <td>' . htmlspecialchars(s($r['identifier'])) . '</td>
                </tr>';
            }
        } else {
            $html .= '<tr><td colspan="6" class="text-center">Chưa thiết lập danh mục tài liệu tự học</td></tr>';
        }
        $html .= '</tbody>
    </table>

</body>
</html>';

// =====================================================================
// 3. KHỞI TẠO MPDF VÀ ĐẨY FILE PDF VỀ BROWSER TẢI XUỐNG
// =====================================================================
$safeCode = preg_replace('/[^A-Za-z0-9_\-]/', '_', s($module['code']));
$filename = 'DeCuong_' . $safeCode . '.pdf';

// Cấu hình mPDF chuẩn trang A4, lề văn bản hành chính VN (Trên 2.5cm, Dưới 2cm, Trái 3cm, Phải 2cm)
$mpdf = new \Mpdf\Mpdf([
    'mode'          => 'utf-8',
    'format'        => 'A4',
    'margin_top'    => 25,
    'margin_bottom' => 25   ,
    'margin_left'   => 30,
    'margin_right'  => 20,
    'default_font'  => 'timesnewroman' // Tự động load font Times New Roman chuẩn Unicode tiếng Việt
]);

$mpdf->SetTitle('Đề cương học phần ' . s($module['name']));
$mpdf->WriteHTML($html);

// Tạo header download file PDF trực tiếp về máy
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
exit;
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'db.php';

if (!function_exists('h')) {
    function h($text) {
        return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
    }
}

$error_msg = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add'])) {
            // 1. Kiểm tra và lọc sạch dữ liệu đầu vào bắt buộc
            $major_id = !empty($_POST['major_id']) ? (int)$_POST['major_id'] : null;
            $blk = !empty($_POST['block_id']) ? (int)$_POST['block_id'] : null;
            $code = trim($_POST['code'] ?? '');
            $name = trim($_POST['name'] ?? '');

            // Nếu thiếu một trong ba trường cốt lõi thì chặn lại và báo lỗi luôn
            if (empty($major_id) || empty($code) || empty($name)) {
                $error_msg = "Không thể thêm học phần! Vui lòng chọn Ngành học, nhập Mã học phần và Tên học phần.";
            } else {
                // 2. Chuyển đổi định dạng số cho các trường thời gian học
                $sort_order = !empty($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
                $total_hours = !empty($_POST['total_hours']) ? (int)$_POST['total_hours'] : 0;
                $theory_hours = !empty($_POST['theory_hours']) ? (int)$_POST['theory_hours'] : 0;
                $practical_hours = !empty($_POST['practical_hours']) ? (int)$_POST['practical_hours'] : 0;

                // 3. Thực thi câu lệnh chuẩn bị an toàn chống SQL Injection
                $stmt = $pdo->prepare(
                    'INSERT INTO courses (major_id, block_id, sort_order, code, name, total_hours, theory_hours, practical_hours) ' .
                    'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $stmt->execute([
                    $major_id, $blk, $sort_order, $code,
                    $name, $total_hours, $theory_hours, $practical_hours
                ]);

                header('Location: courses.php');
                exit;
            }
        }

        if (isset($_POST['delete'])) {
            $stmt = $pdo->prepare('DELETE FROM courses WHERE id = ?');
            $stmt->execute([$_POST['delete']]);
            header('Location: courses.php');
            exit;
        }
    }

    // Nạp lại danh sách dữ liệu hiển thị lên giao diện Table công khai
    $majors = $pdo->query('SELECT * FROM majors ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $blocks = $pdo->query('SELECT * FROM knowledge_blocks ORDER BY major_id')->fetchAll(PDO::FETCH_ASSOC);
    $courses = $pdo->query(
        'SELECT c.*, m.name as major_name, b.name as block_name FROM courses c ' .
        'LEFT JOIN majors m ON c.major_id = m.id ' .
        'LEFT JOIN knowledge_blocks b ON c.block_id = b.id ' .
        // 'ORDER BY m.name, c.sort_order, c.code'
        'ORDER BY c.id'
    )->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Nếu có lỗi phát sinh từ hệ thống CSDL (như sai tên cột, sai bảng hoặc dính khóa ngoại) thì in thẳng ra giao diện
    $error_msg = "Lỗi Database phát sinh: " . $e->getMessage();
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quản lý học phần</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .table th, .table td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .table th { background: #f2f2f2; }
        .row-input select, .row-input input { padding: 6px; margin-right: 5px; }
        .small { width: 70px; }
        .btn-action { padding: 4px 8px; font-size: 12px; text-decoration: none; border-radius: 4px; color: #fff; border: none; cursor: pointer; }
        .btn-edit { background: #198754; display: inline-block; margin-right: 5px; }
        .error-box { background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; margin-bottom: 20px; border-radius: 4px; }
        .row-input input { width: 150px; }
        .row-input input.small { width: 70px; }
        .row-input input[type="number"] { width: 80px; }

        body { background-color: #f4f6f9; padding-top: 30px; padding-bottom: 50px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .syllabus-container { background: #ffffff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 40px; }
        .main-title { font-weight: 700; color: #1a446c; text-transform: uppercase; margin-bottom: 30px; border-bottom: 3px solid #1a446c; padding-bottom: 10px; }
        .section-title { background: #1a446c; color: #ffffff; padding: 10px 15px; font-weight: 600; text-transform: uppercase; margin-top: 35px; margin-bottom: 20px; border-radius: 4px; }
        .sub-section-header { display: flex; justify-content: space-between; align-items: center; margin-top: 25px; margin-bottom: 15px; border-left: 4px solid #3498db; padding-left: 10px; }
        .sub-section-title { font-weight: 600; color: #2c3e50; margin: 0; }
        .table th { background-color: #f8f9fa; color: #333; font-weight: 600; text-align: center; vertical-align: middle; font-size: 14px; }
        .form-helper { font-size: 12px; color: #6c757d; display: block; margin-top: 4px; }

        .sortable {
            cursor: pointer;
            user-select: none;
            transition: background .2s;
        }

        .sortable:hover {
            background: #e9ecef;
        }

        .sortable span {
            display: inline-block;
            margin-left: 4px;
            color: #6c757d;
            font-size: 14px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <p>
            <a href="majors.php">Ngành</a> |
            <a href="blocks.php">Khối kiến thức</a> |
            <a href="index.php">Đề cương chi tiết học phần</a>
        </p>
        <h2 class="text-center main-title">Học phần</h2>

        <?php if (!empty($error_msg)): ?>
            <div class="error-box"><strong>Thông báo:</strong> <?= h($error_msg) ?></div>
        <?php endif; ?>

        <div class="section">
            <div class="section-title">Thêm học phần</div>
            <form method="post" class="row-input">
                <select name="major_id" id="majorSelect" required onchange="filterBlocks(this.value)">
                    <option value="">Chọn ngành</option>
                    <?php foreach ($majors as $m): ?>
                        <option value="<?= h($m['id']) ?>"><?= h($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="block_id" id="block_select">
                    <option value="">(Chọn khối)</option>
                </select>

                <input class="small" name="sort_order" placeholder="STT">
                <input name="code" placeholder="Mã" required>
                <input name="name" placeholder="Tên" required>
                <input class="small" name="total_hours" placeholder="Tổng tiết">
                <input class="small" name="theory_hours" placeholder="LT">
                <input class="small" name="practical_hours" placeholder="TH">
                <button class="button" name="add" type="submit">Thêm</button>
            </form>
        </div>

        <div class="section">
            <div class="section-title">Danh sách học phần</div>
            <div class="d-flex align-items-center">
                <p class="m-2"><button class="button" onclick="window.print()">In</button></p>

                <div>
                    <input
                        type="text"
                        id="searchCourses"
                        class="search_input"
                        placeholder="Tìm kiếm ..."
                    />
                </div>
            </div>

            <?php if ($courses): ?>
                <table class="table" id="courseTable">
                    <thead>
                        <tr>
                            <th onclick="sortTable(0)" class="sortable">
                                STT <span id="icon-0">⇅</span>
                            </th>
                            <th onclick="sortTable(1)" class="sortable">
                                Ngành <span id="icon-1">⇅</span>
                            </th>
                            <th onclick="sortTable(2)" class="sortable">
                                Khối <span id="icon-2">⇅</span>
                            </th>
                            <th onclick="sortTable(3)" class="sortable">
                                Mã <span id="icon-3">⇅</span>
                            </th>

                            <th onclick="sortTable(4)" class="sortable">
                                Tên <span id="icon-4">⇅</span>
                            </th>
                            <th>Tổng</th>
                            <th>LT</th>
                            <th>TH</th>
                            <th style="width:160px; text-align:center;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="courseTable">
                        <?php foreach ($courses as $c): ?>
                            <tr>
                                <td><?= h($c['id']) ?></td>
                                <td><?= h($c['major_name']) ?></td>
                                <td><?= h($c['block_name'] ?: '(Chưa phân khối)') ?></td>
                                <td><?= h($c['code']) ?></td>
                                <td><?= h($c['name']) ?></td>
                                <td><?= h($c['total_hours']) ?></td>
                                <td><?= h($c['theory_hours']) ?></td>
                                <td><?= h($c['practical_hours']) ?></td>
                                <td style="text-align: center;">
                                    <a href="index.php?course_id=<?= h($c['id']) ?>" class="btn-action btn-edit">Chỉnh sửa</a>
                                    <form method="post" style="display: inline; margin: 0;" onsubmit="return confirm('Xóa học phần này?');">
                                        <button class="button secondary" name="delete" value="<?= h($c['id']) ?>" type="submit">Xóa</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Chưa có học phần.</p>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        const allBlocks = <?php echo json_encode($blocks); ?>;
        function filterBlocks(selectedMajorId) {
            const blockSelect = document.getElementById('block_select');
            blockSelect.innerHTML = '<option value="">(Chọn khối)</option>';
            if (!selectedMajorId) return;
            const filteredBlocks = allBlocks.filter(block => block.major_id == selectedMajorId);
            filteredBlocks.forEach(block => {
                const option = document.createElement('option');
                option.value = block.id;
                option.text = block.name;
                blockSelect.appendChild(option);
            });
            // Re-initialize Select2 for the updated select
            $('#block_select').select2({
              placeholder: '(Chọn khối)',
              allowClear: true,
              width: '100%'
            });
        }

        $(document).ready(function() {
          $('#majorSelect').select2({
            placeholder: 'Chọn ngành',
            allowClear: true,
            width: '100%'
          });

          $('#block_select').select2({
            placeholder: '(Chọn khối)',
            allowClear: true,
            width: '100%'
          });
        });


        // sort
        const originalRows = Array.from(
            document.querySelector("#courseTable tbody").rows
        );

        let sortState = {};

        function sortTable(columnIndex) {
            const tbody = document.querySelector("#courseTable tbody");
            const rows = Array.from(tbody.rows);

            if (!(columnIndex in sortState)) {
                sortState[columnIndex] = 0;
            }

            sortState[columnIndex] = (sortState[columnIndex] + 1) % 3;
            const state = sortState[columnIndex];

            // Reset icon tất cả cột
            document.querySelectorAll('[id^="icon-"]').forEach(icon => {
                icon.textContent = '⇅';
            });

            // Cập nhật icon cột đang sort
            const icon = document.getElementById(`icon-${columnIndex}`);

            if (state === 1) {
                icon.textContent = '▲';
            } else if (state === 2) {
                icon.textContent = '▼';
            } else {
                icon.textContent = '⇅';
            }

            if (state === 0) {
                tbody.innerHTML = '';

                originalRows.forEach(row => {
                    tbody.appendChild(row);
                });

                return;
            }

            rows.sort((a, b) => {
                let x = a.cells[columnIndex].innerText.trim();
                let y = b.cells[columnIndex].innerText.trim();

                const nx = parseFloat(x);
                const ny = parseFloat(y);

                let result;

                if (!isNaN(nx) && !isNaN(ny)) {
                    result = nx - ny;
                } else {
                    result = x.localeCompare(y, 'vi');
                }

                return state === 1 ? result : -result;
            });

            tbody.innerHTML = '';

            rows.forEach(row => {
                tbody.appendChild(row);
            });
        }
    </script>

    <script>
        const searchInput = document.getElementById('searchCourses');
        searchInput.addEventListener('keyup', function() {
            const keyword = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#courseTable tr');

            // console.log(rows)
            
            rows.forEach(row => {
                const nganh = row.children[1]?.textContent.toLowerCase() || '';
                const khoi = row.children[2]?.textContent.toLowerCase() || '';
                const ma = row.children[3]?.textContent.toLowerCase() || '';
                const ten = row.children[4]?.textContent.toLowerCase() || '';
                const searchText = `${nganh} ${khoi} ${ma} ${ten}`;
                console.log(searchText)

                if (searchText.includes(keyword)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        })
    </script>
    <!-- <script src="./assistant.js"></script> -->
</body>
</html>

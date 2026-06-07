<?php
require 'db.php';

$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add'])) {
            $major_id = !empty($_POST['major_id']) ? (int)$_POST['major_id'] : null;
            $name = trim($_POST['name'] ?? '');
            $pid = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

            if (!$major_id) {
                throw new Exception('Vui long chon nganh truoc khi them khoi kien thuc.');
            }

            if ($name === '') {
                throw new Exception('Ten khoi kien thuc khong duoc de trong.');
            }

            $stmtMajor = $pdo->prepare('SELECT COUNT(*) FROM majors WHERE id = ?');
            $stmtMajor->execute([$major_id]);
            if ((int)$stmtMajor->fetchColumn() === 0) {
                throw new Exception('Nganh duoc chon khong ton tai.');
            }

            if ($pid !== null) {
                $stmtParent = $pdo->prepare('SELECT COUNT(*) FROM knowledge_blocks WHERE id = ? AND major_id = ?');
                $stmtParent->execute([$pid, $major_id]);
                if ((int)$stmtParent->fetchColumn() === 0) {
                    throw new Exception('Khoi cha phai thuoc cung nganh voi khoi dang them.');
                }
            }

            $stmt = $pdo->prepare('INSERT INTO knowledge_blocks (major_id, name, parent_id) VALUES (?, ?, ?)');
            $stmt->execute([$major_id, $name, $pid]);
            header('Location: blocks.php?added=1');
            exit;
        }

        if (isset($_POST['delete'])) {
            $stmt = $pdo->prepare('DELETE FROM knowledge_blocks WHERE id = ?');
            $stmt->execute([(int)$_POST['delete']]);
            header('Location: blocks.php?deleted=1');
            exit;
        }
    } catch (Exception $e) {
        $error_msg = $e->getMessage();
    }
}

if (isset($_GET['added'])) {
    $success_msg = 'Da them khoi kien thuc.';
} elseif (isset($_GET['deleted'])) {
    $success_msg = 'Da xoa khoi kien thuc.';
}

// Fetch data
$majors = $pdo->query('SELECT * FROM majors ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$blocks = $pdo->query(
    'SELECT b.*, m.name as major_name, p.name as parent_name FROM knowledge_blocks b ' .
    'JOIN majors m ON b.major_id = m.id ' .
    'LEFT JOIN knowledge_blocks p ON b.parent_id = p.id ' .
    // 'ORDER BY m.name, b.parent_id, b.name'
    'ORDER BY b.id'
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quản lý khối kiến thức</title>
    <link rel="stylesheet" href="assets/style.css">
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
    <div class="container">
        <p>
            <a href="majors.php">Quản lý ngành</a> |
            <a href="index.php">Về form module</a>
        </p>
        <h2 class="text-center main-title">Khối kiến thức</h2>
        

        <!-- <?php if ($error_msg): ?>
            <div class="alert alert-danger"><?= h($error_msg) ?></div>
        <?php endif; ?>
        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?= h($success_msg) ?></div>
        <?php endif; ?> -->

        <div class="section">
            <form method="post" class="row-input">
                <select name="major_id" id="majorSelect" required>
                    <option value="">Chọn ngành</option>
                    <?php foreach ($majors as $m): ?>
                        <option value="<?= h($m['id']) ?>"><?= h($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input name="name" placeholder="Tên khối" required>
                <select name="parent_id" id="parentSelect">
                    <option value="">(Không có cha)</option>
                    <?php foreach ($blocks as $b): ?>
                        <?php if (!$b['parent_id']): ?>
                            <option value="<?= h($b['id']) ?>" data-major-id="<?= h($b['major_id']) ?>">
                                <?= h($b['major_name']) ?> - <?= h($b['name']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <button class="button" name="add">Thêm</button>
            </form>
        </div>

        <div class="section">
            <div class="section-title">Danh sách khối</div>
            <?php if ($blocks): ?>
                <table class="table">
                    <tr>
                        <th>ID</th>
                        <th>Ngành</th>
                        <th>Tên</th>
                        <th>Thuộc khối</th>
                        <th></th>
                    </tr>
                    <?php foreach ($blocks as $b): ?>
                        <tr>
                            <td><?= h($b['id']) ?></td>
                            <td><?= h($b['major_name']) ?></td>
                            <td><?= h($b['name']) ?></td>
                            <td><?= h($b['parent_name'] ?: '') ?></td>
                            <td>
                                <form method="post" style="margin: 0;">
                                    <button class="button secondary" name="delete" value="<?= h($b['id']) ?>">
                                        Xóa
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>Chưa có khối kiến thức.</p>
            <?php endif; ?>
        </div>
    </div>
    <script>
        const majorSelect = document.getElementById('majorSelect');
        const parentSelect = document.getElementById('parentSelect');
        const parentOptions = Array.from(parentSelect.options).map(option => ({
            value: option.value,
            text: option.text,
            majorId: option.dataset.majorId || ''
        }));

        function filterParentBlocks() {
            const selectedMajorId = majorSelect.value;
            parentSelect.innerHTML = '';

            parentOptions.forEach(optionData => {
                if (optionData.value && optionData.majorId !== selectedMajorId) {
                    return;
                }

                const option = document.createElement('option');
                option.value = optionData.value;
                option.text = optionData.text;
                if (optionData.majorId) {
                    option.dataset.majorId = optionData.majorId;
                }
                parentSelect.appendChild(option);
            });
        }

        majorSelect.addEventListener('change', filterParentBlocks);
        filterParentBlocks();
    </script>
</body>
</html>

<?php
require 'db.php';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $stmt = $pdo->prepare('INSERT INTO majors (name) VALUES (?)');
        $stmt->execute([$_POST['name']]);
        header('Location: majors.php');
        exit;
    }
    if (isset($_POST['delete'])) {
        $stmt = $pdo->prepare('DELETE FROM majors WHERE id = ?');
        $stmt->execute([$_POST['delete']]);
        header('Location: majors.php');
        exit;
    }
}

// Fetch majors
$majors = $pdo->query('SELECT * FROM majors ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quản lý ngành đào tạo</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/majors.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
</head>
<body>
    <div class="container syllabus-container">
        <p><a href="index.php">Về form module</a></p>
        <h2 class="text-center main-title">Ngành đào tạo</h2>

        <div class="section">
            <div class="section-title">Thêm ngành</div>
            
            <form method="post" class="row-input">
                <input name="name" placeholder="Tên ngành" required>                    <button class="button" name="add">Thêm</button>
            </form>
        </div>

        <div class="section">
            <div class="section-title">DANH SÁCH NGÀNH</div>
            <div>
                <input
                    type="text"
                    id="searchMajor"
                    class="search_input"
                    placeholder="Tìm ngành ..."
                />
            </div>
            <?php if ($majors): ?>
                <table class="table table-bordered align-middle table-hover">
                    <thead class="">
                    <tr >
                        <th >ID</th>
                        <th>Tên</th>
                        <th>Thao tác</th>
                    </tr>
                    </thead>
                        <tbody id="majorTable">
                            <?php foreach ($majors as $m): ?>
                                <tr>
                                    <td><?= h($m['id']) ?></td>
                                    <td><?= h($m['name']) ?></td>
                                    <td>
                                        <div class="d-flex gap-2 justify-content-center">
                                            <a href="export_major_word.php?id=<?= h($m['id']) ?>" class="btn btn-success btn-sm">
                                                Xu&#7845;t PDF
                                            </a>
                                            <form method="post" style="margin: 0;">
                                                <button class="button danger button_del" name="delete" value="<?= h($m['id']) ?>">
                                                    Xóa
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
            <?php else: ?>
                <p>Chưa có ngành nào.</p>
            <?php endif; ?>
        </div>
    </div>
</body>

<script>
    const searchInput = document.getElementById('searchMajor');
    searchInput.addEventListener('keyup', function() {
        const keyword = this.value.toLowerCase();
        const rows = document.querySelectorAll('#majorTable tr');
        rows.forEach(row => {

            const majorName = row.children[1].textContent.toLowerCase();

            if (majorName.includes(keyword)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }

        });
    })
</script>

</html>

<?php
require 'db.php';
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Danh sách học phần</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .btn-link { text-decoration: none; color: #0066cc; cursor: pointer; }
        .btn-link:hover { text-decoration: underline; }

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
        <p><a href="index.php">Tạo học phần mới</a></p>
        <h2 class="text-center main-title">Danh sách học phần</h2>
        <div>
                    <input
                        type="text"
                        id="searchList"
                        class="search_input"
                        placeholder="Tìm kiếm ..."
                    />
                </div>

        <?php
        $stmt = $pdo->query(
            'SELECT id, code, credits, created_at, name FROM modules ORDER BY created_at DESC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            echo '<p>Chưa có học phần nào.</p>';
        } else {
            echo '<table class="table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>STT</th>';
            echo '<th style="width: 15%;">Mã học phần</th>';
            echo '<th style="width: 50%;">Tên học phần</th>';
            echo '<th style="width: 10%;">Tín chỉ</th>';
            echo '<th style="width: 15%;">Ngày tạo</th>';
            echo '<th style="width: 10%;">Thao tác</th>';
            echo '</tr>';
            echo '</thead>';

            echo '<tbody id="listTable">';            
            $stt = 0;
            foreach ($rows as $r) {
                $stt++;
                echo '<tr>';
                echo '<td>' . $stt . '</td>';
                echo '<td>' . h($r['code']) . '</td>';
                echo '<td>' . h($r['name']) . '</td>';
                echo '<td>' . h($r['credits']) . '</td>';
                echo '<td>' . h($r['created_at']) . '</td>';
                echo '<td><a href="view.php?id=' . h($r['id']) . '&edit=1" class="btn-link">Chi tiết</a></td>';
                echo '</tr>';
            }
            echo '</tbody>';  
            echo '</table>';
        }
        ?>
    </div>

    <script>
        const searchInput = document.getElementById('searchList');
        searchInput.addEventListener('keyup', function() {
            const keyword = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#listTable tr');
            rows.forEach(row => {
                const ma = row.children[1]?.textContent.toLowerCase() || '';
                const ten = row.children[2]?.textContent.toLowerCase() || '';
                const ngay = row.children[3]?.textContent.toLowerCase() || '';
                const searchText = `${ma} ${ten} ${ngay}`;
                
                if (searchText.includes(keyword)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        })
    </script>
</body>
</html>
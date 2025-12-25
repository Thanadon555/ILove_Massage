<?php
/**
 * สคริปต์สำหรับแก้ไขไฟล์ admin ทั้งหมด
 * แทนที่ Bootstrap script tags ด้วย admin-scripts.php template
 * 
 * วิธีใช้: เรียกไฟล์นี้ผ่าน browser หรือ command line
 * php fix_all_admin_files.php
 */

// ไฟล์ที่ต้องแก้ไข
$files_to_fix = [
    'manage_users.php',
    'manage_therapists.php',
    'manage_services.php',
    'manage_schedule.php',
    'manage_payments.php',
    'manage_holidays.php',
    'reports.php',
    'contact.php',
    'profile.php',
    'receipt.php',
    'dashboard.php',
    'generate_print_report.php',
    'index.php'
];

// Pattern ที่ต้องค้นหาและแทนที่
$patterns_to_replace = [
    // Bootstrap 5.1.3
    [
        'old' => '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>',
        'new' => '<?php include \'../templates/admin-scripts.php\'; ?>'
    ],
    // Bootstrap 5.3.0
    [
        'old' => '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>',
        'new' => '<?php include \'../templates/admin-scripts.php\'; ?>'
    ]
];

$results = [];
$total_fixed = 0;

foreach ($files_to_fix as $filename) {
    $filepath = __DIR__ . '/' . $filename;
    
    if (!file_exists($filepath)) {
        $results[] = "❌ ไม่พบไฟล์: $filename";
        continue;
    }
    
    $content = file_get_contents($filepath);
    $original_content = $content;
    $file_modified = false;
    
    foreach ($patterns_to_replace as $pattern) {
        if (strpos($content, $pattern['old']) !== false) {
            $content = str_replace($pattern['old'], $pattern['new'], $content);
            $file_modified = true;
        }
    }
    
    if ($file_modified) {
        // Backup original file
        $backup_file = $filepath . '.backup';
        file_put_contents($backup_file, $original_content);
        
        // Save modified file
        file_put_contents($filepath, $content);
        
        $results[] = "✅ แก้ไขสำเร็จ: $filename (สำรองไว้ที่ $filename.backup)";
        $total_fixed++;
    } else {
        $results[] = "ℹ️  ไม่ต้องแก้ไข: $filename (อาจแก้ไขแล้วหรือไม่มี pattern ที่ต้องแทนที่)";
    }
}

// แสดงผลลัพธ์
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผลการแก้ไขไฟล์ Admin</title>
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4361ee;
            padding-bottom: 10px;
        }
        .result {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            font-family: monospace;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .summary {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            border-left: 4px solid #4361ee;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4361ee;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #3f37c9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 ผลการแก้ไขไฟล์ Admin</h1>
        
        <?php foreach ($results as $result): ?>
            <?php
            $class = 'info';
            if (strpos($result, '✅') !== false) {
                $class = 'success';
            } elseif (strpos($result, '❌') !== false) {
                $class = 'error';
            }
            ?>
            <div class="result <?= $class ?>">
                <?= htmlspecialchars($result) ?>
            </div>
        <?php endforeach; ?>
        
        <div class="summary">
            <h3>📊 สรุปผลการแก้ไข</h3>
            <p><strong>ไฟล์ทั้งหมด:</strong> <?= count($files_to_fix) ?> ไฟล์</p>
            <p><strong>แก้ไขสำเร็จ:</strong> <?= $total_fixed ?> ไฟล์</p>
            <p><strong>ไม่ต้องแก้ไข:</strong> <?= count($files_to_fix) - $total_fixed ?> ไฟล์</p>
        </div>
        
        <h3>📝 ขั้นตอนถัดไป:</h3>
        <ol>
            <li>ทดสอบเปิดหน้า admin ต่างๆ</li>
            <li>ตรวจสอบว่าปุ่มทั้งหมดทำงานได้</li>
            <li>เปิด Browser Console (F12) เพื่อดู errors</li>
            <li>หากมีปัญหา สามารถ restore จากไฟล์ .backup ได้</li>
        </ol>
        
        <h3>⚠️ หมายเหตุ:</h3>
        <ul>
            <li>ไฟล์ต้นฉบับถูกสำรองไว้ที่ <code>*.backup</code></li>
            <li>ตรวจสอบว่าไฟล์ <code>admin/js/admin-functions.js</code> มีอยู่</li>
            <li>ตรวจสอบว่าไฟล์ <code>templates/admin-scripts.php</code> มีอยู่</li>
            <li>ตรวจสอบว่าไฟล์ <code>admin/css/admin-effects.css</code> มีอยู่</li>
        </ul>
        
        <a href="dashboard.php" class="btn">ไปที่ Dashboard</a>
        <a href="FIX_BUTTONS_README.md" class="btn" style="background: #28a745;">อ่านคู่มือ</a>
    </div>
</body>
</html>

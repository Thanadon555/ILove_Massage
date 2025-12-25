<?php
/**
 * PHP Version Check Component
 * 
 * This file checks if the PHP version meets the minimum requirement (7.1.0)
 * and handles version-related errors with Thai language messages.
 */

// Minimum required PHP version
define('MIN_PHP_VERSION', '7.1.0');

/**
 * Check if current PHP version meets minimum requirement
 * 
 * @return bool True if version is compatible, false otherwise
 */
function checkPHPVersion() {
    $currentVersion = phpversion();
    return version_compare($currentVersion, MIN_PHP_VERSION, '>=');
}

/**
 * Display error message in Thai when PHP version is incompatible
 * 
 * @return void
 */
function displayVersionError() {
    $currentVersion = phpversion();
    $requiredVersion = MIN_PHP_VERSION;
    
    // Log the error before displaying
    logVersionInfo(false);
    
    // Display Thai error message
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อผิดพลาด PHP Version</title>
    <style>
        body {
            font-family: "Sarabun", "Tahoma", sans-serif;
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .error-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 600px;
            text-align: center;
        }
        .error-icon {
            font-size: 64px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        h1 {
            color: #dc3545;
            font-size: 24px;
            margin-bottom: 20px;
        }
        .version-info {
            background: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
        }
        .version-info p {
            margin: 10px 0;
            font-size: 16px;
        }
        .version-label {
            font-weight: bold;
            color: #495057;
        }
        .version-value {
            color: #dc3545;
            font-weight: bold;
        }
        .help-text {
            color: #6c757d;
            font-size: 14px;
            margin-top: 20px;
            line-height: 1.6;
        }
        .contact-info {
            background: #e7f3ff;
            border-radius: 4px;
            padding: 15px;
            margin-top: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1>ระบบต้องการ PHP เวอร์ชัน 7.1 ขึ้นไป</h1>
        
        <div class="version-info">
            <p><span class="version-label">เวอร์ชันปัจจุบัน:</span> <span class="version-value">' . htmlspecialchars($currentVersion) . '</span></p>
            <p><span class="version-label">เวอร์ชันที่ต้องการ:</span> <span class="version-value">' . htmlspecialchars($requiredVersion) . '</span></p>
        </div>
        
        <div class="help-text">
            <p>ระบบจองนวดนี้ต้องการ PHP เวอร์ชัน 7.1.0 หรือสูงกว่าในการทำงาน</p>
            <p>กรุณาอัพเกรด PHP บนเซิร์ฟเวอร์ของคุณ หรือติดต่อผู้ดูแลระบบเพื่อขอความช่วยเหลือ</p>
        </div>
        
        <div class="contact-info">
            <strong>ต้องการความช่วยเหลือ?</strong><br>
            กรุณาติดต่อผู้ดูแลระบบหรือทีมสนับสนุนทางเทคนิค<br>
            พร้อมแจ้งข้อมูล PHP Version ที่แสดงด้านบน
        </div>
    </div>
</body>
</html>';
    
    exit();
}

/**
 * Log PHP version information to error log
 * 
 * @param bool $isCompatible Whether the version check passed
 * @return void
 */
function logVersionInfo($isCompatible = true) {
    $currentVersion = phpversion();
    $requiredVersion = MIN_PHP_VERSION;
    $timestamp = date('Y-m-d H:i:s');
    
    // Prepare log directory
    $logDir = __DIR__ . '/../logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/admin_errors.log';
    
    // Prepare log message
    if ($isCompatible) {
        $logMessage = sprintf(
            "[%s] PHP Version Check: PASSED - Current: %s, Required: %s\n",
            $timestamp,
            $currentVersion,
            $requiredVersion
        );
    } else {
        $logMessage = sprintf(
            "[%s] PHP Version Check: FAILED - Current: %s, Required: %s - Application execution stopped\n",
            $timestamp,
            $currentVersion,
            $requiredVersion
        );
    }
    
    // Write to log file
    error_log($logMessage, 3, $logFile);
}

// Perform version check automatically when this file is included
if (!checkPHPVersion()) {
    displayVersionError();
}

// Log successful version check (optional - can be commented out in production)
// logVersionInfo(true);

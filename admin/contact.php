<?php
// PHP Version Check - Must be first
require_once '../config/php_version_check.php';

session_start();
require_once '../config/database.php';
require_once 'includes/csrf.php';
require_once 'includes/validation.php';
require_once 'includes/db_helper.php';
require_once 'includes/error_logger.php';

// ตรวจสอบการล็อกอินและสิทธิ์ admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// สร้าง DatabaseHelper instance
$dbHelper = new DatabaseHelper($conn);

// ตัวแปรสำหรับเก็บข้อความ
$success = '';
$error = '';

// ตรวจสอบ session message จาก redirect
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// ตั้งค่าการกรอง
$filter_status = $_GET['status'] ?? '';
$filter_search = $_GET['search'] ?? '';

// สร้าง URL สำหรับล้างตัวกรอง
$clear_filters_url = 'contact.php';

// ฟังก์ชันส่งอีเมล
function sendEmailReply($to, $subject, $message, $customer_name)
{
    try {
        // Validate email parameters
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            logError('Invalid email address in sendEmailReply', ['to' => $to]);
            return false;
        }

        if (empty($subject) || empty($message)) {
            logError('Empty subject or message in sendEmailReply', [
                'to' => $to,
                'has_subject' => !empty($subject),
                'has_message' => !empty($message)
            ]);
            return false;
        }

        // สำหรับการพัฒนา ให้บันทึกข้อมูลอีเมลลงไฟล์แทนการส่งจริง
        $email_data = [
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'customer_name' => $customer_name,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // บันทึกลงไฟล์ log
        $log_message = "[" . date('Y-m-d H:i:s') . "] Email would be sent to: $to\n";
        $log_message .= "Subject: $subject\n";
        $log_message .= "Customer: $customer_name\n";
        $log_message .= "Message: " . substr($message, 0, 200) . (strlen($message) > 200 ? "..." : "") . "\n";
        $log_message .= "----------------------------------------\n";

        // สร้างโฟลเดอร์ logs ถ้ายังไม่มี
        if (!is_dir('../logs')) {
            if (!mkdir('../logs', 0755, true)) {
                logError('Failed to create logs directory', ['path' => '../logs']);
                return false;
            }
        }

        // ตรวจสอบว่าสามารถเขียนไฟล์ได้หรือไม่
        if (file_put_contents('../logs/email_log.txt', $log_message, FILE_APPEND) === false) {
            logError('Failed to write to email log file', ['path' => '../logs/email_log.txt']);
            return false;
        }

        // คืนค่า true เพื่อให้ระบบทำงานต่อได้
        return true;
    } catch (Exception $e) {
        logError('Exception in sendEmailReply: ' . $e->getMessage(), [
            'to' => $to ?? 'unknown',
            'trace' => $e->getTraceAsString()
        ]);
        return false;
    }
}

// ตรวจสอบการส่งฟอร์มติดต่อ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_contact'])) {
        try {
            // Validate CSRF token
            if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
                $error = 'Session หมดอายุ กรุณา refresh หน้าและลองใหม่';
                logError('CSRF token validation failed for send_contact', [
                    'user_id' => $_SESSION['user_id'] ?? 'unknown',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            } else {
                $subject = trim($_POST['subject'] ?? '');
                $message = trim($_POST['message'] ?? '');
                $customer_id = $_POST['customer_id'] ?? null;

                // Validation
                $validator = new Validator();
                $validator->required('subject', $subject, 'หัวข้อ');
                $validator->required('message', $message, 'ข้อความ');
                $validator->required('customer_id', $customer_id, 'ลูกค้า');

                if ($validator->hasErrors()) {
                    $error = implode('<br>', $validator->getErrors());
                    logError('Validation failed for send_contact', [
                        'errors' => $validator->getErrors(),
                        'customer_id' => $customer_id
                    ]);
                } else {
                    try {
                        // ตรวจสอบว่าลูกค้ามีอยู่จริง
                        $customer = $dbHelper->fetchOne(
                            "SELECT user_id, full_name, email, phone FROM users WHERE user_id = ? AND role = 'customer' AND is_active = 1",
                            [$customer_id],
                            "i"
                        );

                        if (!$customer) {
                            $error = 'ไม่พบข้อมูลลูกค้าที่ระบุ หรือบัญชีไม่ได้เปิดใช้งาน';
                            logError('Customer not found or inactive for send_contact', ['customer_id' => $customer_id]);
                        } else {
                            // บันทึกการติดต่อลงฐานข้อมูล
                            $stmt = $dbHelper->execute(
                                "INSERT INTO contacts (customer_id, customer_name, customer_email, customer_phone, subject, message, status) 
                                 VALUES (?, ?, ?, ?, ?, ?, 'completed')",
                                [$customer_id, $customer['full_name'], $customer['email'], $customer['phone'], $subject, $message],
                                "isssss"
                            );

                            $success = 'ส่งข้อความติดต่อเรียบร้อยแล้ว';
                            logError('Contact message sent successfully', [
                                'contact_id' => $dbHelper->getLastInsertId(),
                                'customer_id' => $customer_id,
                                'admin_id' => $_SESSION['user_id']
                            ]);
                        }
                    } catch (Exception $e) {
                        logError('Database error in send_contact: ' . $e->getMessage(), [
                            'customer_id' => $customer_id,
                            'trace' => $e->getTraceAsString()
                        ]);
                        $error = 'เกิดข้อผิดพลาดในการส่งข้อความ กรุณาตรวจสอบการเชื่อมต่อฐานข้อมูลและลองใหม่อีกครั้ง';
                    }
                }
            }
        } catch (Exception $e) {
            logError('Unexpected error in send_contact: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $error = 'เกิดข้อผิดพลาดที่ไม่คาดคิด กรุณาติดต่อผู้ดูแลระบบ';
        }
    }

    if (isset($_POST['update_contact_status'])) {
        try {
            // Validate CSRF token
            if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
                $error = 'Session หมดอายุ กรุณา refresh หน้าและลองใหม่';
                logError('CSRF token validation failed for update_contact_status', [
                    'user_id' => $_SESSION['user_id'] ?? 'unknown',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            } else {
                $contact_id = $_POST['contact_id'] ?? null;
                $status = $_POST['status'] ?? null;
                $admin_notes = trim($_POST['admin_notes'] ?? '');

                // Validation
                $validator = new Validator();
                $validator->required('contact_id', $contact_id, 'รหัสการติดต่อ');
                $validator->required('status', $status, 'สถานะ');

                // Validate status value
                $valid_statuses = ['pending', 'in_progress', 'completed'];
                if ($status && !in_array($status, $valid_statuses)) {
                    $error = 'สถานะที่เลือกไม่ถูกต้อง กรุณาเลือกสถานะที่ถูกต้อง';
                    logError('Invalid status value for update_contact_status', [
                        'status' => $status,
                        'contact_id' => $contact_id
                    ]);
                }

                if ($validator->hasErrors()) {
                    $error = implode('<br>', $validator->getErrors());
                    logError('Validation failed for update_contact_status', [
                        'errors' => $validator->getErrors(),
                        'contact_id' => $contact_id
                    ]);
                } elseif (empty($error)) {
                    try {
                        // ตรวจสอบว่า contact_id มีอยู่จริง
                        $existingContact = $dbHelper->fetchOne(
                            "SELECT contact_id, status, customer_name FROM contacts WHERE contact_id = ?",
                            [$contact_id],
                            "i"
                        );

                        if (!$existingContact) {
                            $error = 'ไม่พบข้อมูลการติดต่อที่ระบุ อาจถูกลบไปแล้ว';
                            logError('Contact not found for update_contact_status', ['contact_id' => $contact_id]);
                        } else {
                            // อัพเดทสถานะ
                            $dbHelper->execute(
                                "UPDATE contacts SET status = ?, admin_notes = ?, updated_at = NOW() WHERE contact_id = ?",
                                [$status, $admin_notes, $contact_id],
                                "ssi"
                            );

                            logError('Contact status updated successfully', [
                                'contact_id' => $contact_id,
                                'old_status' => $existingContact['status'],
                                'new_status' => $status,
                                'admin_id' => $_SESSION['user_id']
                            ]);
                            
                            // Redirect เพื่อ refresh หน้าและแสดงข้อมูลใหม่
                            $_SESSION['success_message'] = 'อัพเดทสถานะเรียบร้อยแล้ว';
                            $redirect_url = 'contact.php';
                            if (!empty($_GET)) {
                                $redirect_url .= '?' . http_build_query($_GET);
                            }
                            header('Location: ' . $redirect_url);
                            exit;
                        }
                    } catch (Exception $e) {
                        logError('Database error in update_contact_status: ' . $e->getMessage(), [
                            'contact_id' => $contact_id,
                            'trace' => $e->getTraceAsString()
                        ]);
                        $error = 'เกิดข้อผิดพลาดในการอัพเดทสถานะ กรุณาตรวจสอบการเชื่อมต่อฐานข้อมูลและลองใหม่อีกครั้ง';
                    }
                }
            }
        } catch (Exception $e) {
            logError('Unexpected error in update_contact_status: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $error = 'เกิดข้อผิดพลาดที่ไม่คาดคิด กรุณาติดต่อผู้ดูแลระบบ';
        }
    }

    if (isset($_POST['reply_contact'])) {
        try {
            // Validate CSRF token
            if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
                $error = 'Session หมดอายุ กรุณา refresh หน้าและลองใหม่';
                logError('CSRF token validation failed for reply_contact', [
                    'user_id' => $_SESSION['user_id'] ?? 'unknown',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            } else {
                $contact_id = $_POST['contact_id'] ?? null;
                $reply_subject = trim($_POST['subject'] ?? '');
                $reply_message = trim($_POST['message'] ?? '');
                $status_after_reply = $_POST['status_after_reply'] ?? 'completed';
                $admin_id = $_SESSION['user_id'];

                // Validation
                $validator = new Validator();
                
                // Validate contact_id
                $validator->required('contact_id', $contact_id, 'รหัสการติดต่อ');
                
                // Validate contact_id is numeric
                if ($contact_id && !is_numeric($contact_id)) {
                    $error = 'รหัสการติดต่อไม่ถูกต้อง กรุณาระบุรหัสที่เป็นตัวเลข';
                    logError('Invalid contact_id format for reply_contact', ['contact_id' => $contact_id]);
                }
                
                // Validate subject
                $validator->required('subject', $reply_subject, 'หัวข้อตอบกลับ');
                if (strlen($reply_subject) > 200) {
                    $error = 'หัวข้อตอบกลับต้องไม่เกิน 200 ตัวอักษร (ปัจจุบัน: ' . strlen($reply_subject) . ' ตัวอักษร)';
                    logError('Reply subject too long', [
                        'length' => strlen($reply_subject),
                        'contact_id' => $contact_id
                    ]);
                }
                
                // Validate message
                $validator->required('message', $reply_message, 'ข้อความตอบกลับ');
                if (strlen($reply_message) < 10) {
                    $error = 'ข้อความตอบกลับต้องมีอย่างน้อย 10 ตัวอักษร (ปัจจุบัน: ' . strlen($reply_message) . ' ตัวอักษร)';
                    logError('Reply message too short', [
                        'length' => strlen($reply_message),
                        'contact_id' => $contact_id
                    ]);
                }
                if (strlen($reply_message) > 5000) {
                    $error = 'ข้อความตอบกลับต้องไม่เกิน 5000 ตัวอักษร (ปัจจุบัน: ' . strlen($reply_message) . ' ตัวอักษร)';
                    logError('Reply message too long', [
                        'length' => strlen($reply_message),
                        'contact_id' => $contact_id
                    ]);
                }
                
                // Validate status_after_reply
                $valid_statuses = ['in_progress', 'completed'];
                if (!in_array($status_after_reply, $valid_statuses)) {
                    $error = 'สถานะหลังตอบกลับไม่ถูกต้อง กรุณาเลือก "กำลังดำเนินการ" หรือ "เสร็จสิ้น"';
                    logError('Invalid status_after_reply value', [
                        'status' => $status_after_reply,
                        'contact_id' => $contact_id
                    ]);
                }

                if ($validator->hasErrors()) {
                    $error = implode('<br>', $validator->getErrors());
                    logError('Validation failed for reply_contact', [
                        'errors' => $validator->getErrors(),
                        'contact_id' => $contact_id
                    ]);
                }
                
                if (empty($error)) {
                    try {
                        // ตรวจสอบว่า contact_id มีอยู่จริงในฐานข้อมูล
                        $contact = $dbHelper->fetchOne(
                            "SELECT * FROM contacts WHERE contact_id = ?",
                            [$contact_id],
                            "i"
                        );

                        if (!$contact) {
                            $error = 'ไม่พบข้อมูลการติดต่อที่ระบุ อาจถูกลบไปแล้ว';
                            logError('Contact not found for reply_contact', ['contact_id' => $contact_id]);
                        } else {
                            // ตรวจสอบว่าลูกค้ามีอีเมลหรือไม่
                            if (empty($contact['customer_email'])) {
                                $error = 'ไม่พบอีเมลของลูกค้า ไม่สามารถส่งข้อความตอบกลับได้';
                                logError('Customer email not found for reply_contact', [
                                    'contact_id' => $contact_id,
                                    'customer_id' => $contact['customer_id']
                                ]);
                            } else {
                                try {
                                    // ส่งอีเมลตอบกลับ
                                    $email_sent = sendEmailReply(
                                        $contact['customer_email'],
                                        $reply_subject,
                                        $reply_message,
                                        $contact['customer_name']
                                    );

                                    // บันทึกการตอบกลับในฐานข้อมูล (ใช้ฟิลด์ใหม่)
                                    $admin_notes = "ตอบกลับแล้ว: " . date('d/m/Y H:i') . "\n";
                                    $admin_notes .= "หัวข้อ: " . $reply_subject . "\n";
                                    $admin_notes .= "ข้อความ: " . substr($reply_message, 0, 200) . (strlen($reply_message) > 200 ? "..." : "") . "\n";
                                    $admin_notes .= "ส่งอีเมล: " . ($email_sent ? 'สำเร็จ' : 'ไม่สำเร็จ');

                                    $dbHelper->execute(
                                        "UPDATE contacts SET 
                                          status = ?, 
                                          admin_reply_subject = ?,
                                          admin_reply_message = ?,
                                          admin_notes = ?,
                                          replied_by = ?,
                                          replied_at = NOW(),
                                          email_sent = ?,
                                          updated_at = NOW() 
                                          WHERE contact_id = ?",
                                        [$status_after_reply, $reply_subject, $reply_message, $admin_notes, $admin_id, $email_sent, $contact_id],
                                        "ssssiii"
                                    );

                                    if ($email_sent) {
                                        $success_msg = 'ส่งข้อความตอบกลับเรียบร้อยแล้ว และส่งอีเมลถึงลูกค้าแล้ว';
                                    } else {
                                        $success_msg = 'บันทึกการตอบกลับเรียบร้อยแล้ว แต่ไม่สามารถส่งอีเมลได้ (ระบบอยู่ในโหมดพัฒนา)';
                                    }

                                    logError('Contact reply sent successfully', [
                                        'contact_id' => $contact_id,
                                        'admin_id' => $admin_id,
                                        'email_sent' => $email_sent,
                                        'status' => $status_after_reply
                                    ]);
                                    
                                    // Redirect เพื่อ refresh หน้าและแสดงข้อมูลใหม่
                                    $_SESSION['success_message'] = $success_msg;
                                    $redirect_url = 'contact.php';
                                    if (!empty($_GET)) {
                                        $redirect_url .= '?' . http_build_query($_GET);
                                    }
                                    header('Location: ' . $redirect_url);
                                    exit;
                                } catch (Exception $e) {
                                    logError('Error sending email or updating database in reply_contact: ' . $e->getMessage(), [
                                        'contact_id' => $contact_id,
                                        'trace' => $e->getTraceAsString()
                                    ]);
                                    $error = 'เกิดข้อผิดพลาดในการส่งอีเมลหรืออัพเดทฐานข้อมูล กรุณาลองใหม่อีกครั้ง';
                                }
                            }
                        }
                    } catch (Exception $e) {
                        logError('Database error in reply_contact: ' . $e->getMessage(), [
                            'contact_id' => $contact_id,
                            'trace' => $e->getTraceAsString()
                        ]);
                        $error = 'เกิดข้อผิดพลาดในการบันทึกการตอบกลับ กรุณาตรวจสอบการเชื่อมต่อฐานข้อมูลและลองใหม่อีกครั้ง';
                    }
                }
            }
        } catch (Exception $e) {
            logError('Unexpected error in reply_contact: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $error = 'เกิดข้อผิดพลาดที่ไม่คาดคิด กรุณาติดต่อผู้ดูแลระบบ';
        }
    }
}

// ตั้งค่า Pagination
$items_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// ดึงข้อมูลการติดต่อจากฐานข้อมูล (ใช้ฟิลด์ใหม่)
try {
    $where_conditions = [];
    $params = [];
    $types = "";

    if ($filter_status) {
        // Validate filter status
        $valid_filter_statuses = ['pending', 'in_progress', 'completed'];
        if (!in_array($filter_status, $valid_filter_statuses)) {
            logError('Invalid filter status value', ['status' => $filter_status]);
            $error = 'ค่าตัวกรองสถานะไม่ถูกต้อง';
            $filter_status = ''; // Reset to show all
        } else {
            $where_conditions[] = "c.status = ?";
            $params[] = $filter_status;
            $types .= "s";
        }
    }

    if ($filter_search) {
        // Sanitize search term
        $filter_search = strip_tags($filter_search);
        $where_conditions[] = "(c.customer_name LIKE ? OR c.subject LIKE ? OR c.message LIKE ? OR c.admin_reply_subject LIKE ? OR c.admin_reply_message LIKE ?)";
        $search_term = "%{$filter_search}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "sssss";
    }

    $where_sql = "";
    if (!empty($where_conditions)) {
        $where_sql = "WHERE " . implode(" AND ", $where_conditions);
    }

    // นับจำนวนทั้งหมดสำหรับ pagination
    $count_sql = "SELECT COUNT(*) as total FROM contacts c $where_sql";
    if (!empty($params)) {
        $count_result = $dbHelper->fetchOne($count_sql, $params, $types);
    } else {
        $count_result = $dbHelper->fetchOne($count_sql);
    }
    $total_items = $count_result['total'] ?? 0;
    $total_pages = ceil($total_items / $items_per_page);

    // ดึงข้อมูลพร้อม LIMIT
    $sql = "SELECT c.*, 
                   u.username as customer_username,
                   admin_user.full_name as admin_name,
                   DATE_FORMAT(c.created_at, '%d/%m/%Y %H:%i') as formatted_created_at,
                   DATE_FORMAT(c.updated_at, '%d/%m/%Y %H:%i') as formatted_updated_at,
                   DATE_FORMAT(c.replied_at, '%d/%m/%Y %H:%i') as formatted_replied_at
            FROM contacts c 
            LEFT JOIN users u ON c.customer_id = u.user_id 
            LEFT JOIN users admin_user ON c.replied_by = admin_user.user_id
            $where_sql 
            ORDER BY c.created_at DESC
            LIMIT $items_per_page OFFSET $offset";

    if (!empty($params)) {
        $contacts = $dbHelper->fetchAll($sql, $params, $types);
    } else {
        $contacts = $dbHelper->fetchAll($sql);
    }

} catch (Exception $e) {
    logError('Database error fetching contacts: ' . $e->getMessage(), [
        'filter_status' => $filter_status,
        'filter_search' => $filter_search,
        'trace' => $e->getTraceAsString()
    ]);
    $error = 'เกิดข้อผิดพลาดในการดึงข้อมูลการติดต่อ กรุณาตรวจสอบการเชื่อมต่อฐานข้อมูลและลองใหม่อีกครั้ง';
    $contacts = [];
    $total_items = 0;
    $total_pages = 0;
}

// ดึงข้อมูลลูกค้าสำหรับ dropdown
try {
    $customers = $dbHelper->fetchAll("SELECT user_id, full_name, email, phone FROM users WHERE role = 'customer' AND is_active = 1 ORDER BY full_name");
    
    if (empty($customers)) {
        logError('No active customers found in database', ['context' => 'contact.php dropdown']);
    }
} catch (Exception $e) {
    logError('Database error fetching customers: ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString()
    ]);
    // Don't show error to user here, just log it and use empty array
    $customers = [];
}

// นับสถิติ
try {
    $stats = $dbHelper->fetchOne("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM contacts
    ");
    
    // Ensure stats has default values if null
    if (!$stats) {
        $stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0];
        logError('No stats returned from database, using defaults', ['context' => 'contact.php stats']);
    }
} catch (Exception $e) {
    logError('Database error fetching contact stats: ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString()
    ]);
    $stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0];
}



// ฟังก์ชันแปลงชื่อเรื่องเป็นภาษาไทย
function getSubjectThai($subject)
{
    $subjects = [
        'booking' => '📅 ปัญหาการจองคิว',
        'payment' => '💳 ปัญหาการชำระเงิน',
        'service' => '💆 สอบถามเกี่ยวกับบริการ',
        'therapist' => '👨‍⚕️ สอบถามเกี่ยวกับหมอนวด',
        'other' => '📝 อื่นๆ'
    ];
    return $subjects[$subject] ?? '📝 อื่นๆ';
}

// ฟังก์ชันแปลงสถานะเป็นภาษาไทย
function getStatusThai($status)
{
    $statuses = [
        'pending' => '⏳ รอดำเนินการ',
        'in_progress' => '🔄 กำลังดำเนินการ',
        'completed' => '✅ เสร็จสิ้น'
    ];
    return $statuses[$status] ?? '⏳ รอดำเนินการ';
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการการติดต่อ - ระบบจัดการนวด</title>
    <?php include '../templates/admin-head.php'; ?>
</head>

<body>
    <?php include '../templates/navbar-admin.php'; ?>

    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="bi bi-envelope me-2"></i>
                จัดการการติดต่อ
            </h1>
        </div>

        <!-- แสดงข้อความแจ้งเตือน -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>ข้อผิดพลาด!</strong> <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <strong>สำเร็จ!</strong> <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- ตัวกรอง -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>ตัวกรองการติดต่อ</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">สถานะ</label>
                        <select name="status" class="form-select">
                            <option value="">ทั้งหมด</option>
                            <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>
                                รอดำเนินการ</option>
                            <option value="in_progress" <?= $filter_status == 'in_progress' ? 'selected' : '' ?>>
                                กำลังดำเนินการ</option>
                            <option value="completed" <?= $filter_status == 'completed' ? 'selected' : '' ?>>
                                เสร็จสิ้น</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ค้นหา</label>
                        <input type="text" name="search" class="form-control"
                            placeholder="ค้นหาชื่อลูกค้า, หัวข้อ, หรือข้อความ"
                            value="<?= htmlspecialchars($filter_search) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-search me-1"></i>กรอง
                        </button>
                        <a href="<?= $clear_filters_url ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-1"></i>ล้าง
                        </a>
                    </div>
                </form>

                <?php if ($filter_status || $filter_search): ?>
                    <div class="mt-3 p-3 bg-light rounded">
                        <h6><i class="bi bi-info-circle me-2"></i>ตัวกรองที่ใช้งานอยู่:</h6>
                        <?php if ($filter_status): ?>
                            <span class="badge bg-primary me-1 mb-1">
                                สถานะ: <?= $filter_status ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['status' => ''])) ?>"
                                    class="text-white text-decoration-none ms-1">×</a>
                            </span>
                        <?php endif; ?>
                        <?php if ($filter_search): ?>
                            <span class="badge bg-warning text-dark me-1 mb-1">
                                ค้นหา: <?= htmlspecialchars($filter_search) ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['search' => ''])) ?>"
                                    class="text-dark text-decoration-none ms-1">×</a>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- สถิติ -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card text-white bg-primary">
                    <div class="card-body text-center">
                        <div class="fs-1 mb-2">
                            <i class="bi bi-list-ul"></i>
                        </div>
                        <h2 class="card-title mb-0"><?= $stats['total'] ?></h2>
                        <p class="card-text mb-0">ทั้งหมด</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card text-white bg-warning">
                    <div class="card-body text-center">
                        <div class="fs-1 mb-2">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <h2 class="card-title mb-0"><?= $stats['pending'] ?></h2>
                        <p class="card-text mb-0">รอดำเนินการ</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card text-white bg-info">
                    <div class="card-body text-center">
                        <div class="fs-1 mb-2">
                            <i class="bi bi-arrow-repeat"></i>
                        </div>
                        <h2 class="card-title mb-0"><?= $stats['in_progress'] ?></h2>
                        <p class="card-text mb-0">กำลังดำเนินการ</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card text-white bg-success">
                    <div class="card-body text-center">
                        <div class="fs-1 mb-2">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <h2 class="card-title mb-0"><?= $stats['completed'] ?></h2>
                        <p class="card-text mb-0">เสร็จสิ้น</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ปุ่มส่งข้อความ -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-send me-2"></i>ส่งข้อความถึงลูกค้า</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <?= csrfField() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">ลูกค้า <span class="text-danger">*</span></label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">เลือกลูกค้า</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?= $customer['user_id'] ?>">
                                        <?= htmlspecialchars($customer['full_name']) ?>
                                        (<?= htmlspecialchars($customer['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">กรุณาเลือกลูกค้า</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">หัวข้อ <span class="text-danger">*</span></label>
                            <input type="text" name="subject" class="form-control" placeholder="หัวข้อข้อความ"
                                required>
                            <div class="invalid-feedback">กรุณากรอกหัวข้อ</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">ข้อความ <span class="text-danger">*</span></label>
                            <textarea name="message" class="form-control" rows="4"
                                placeholder="เขียนข้อความที่นี่..." required></textarea>
                            <div class="invalid-feedback">กรุณากรอกข้อความ</div>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="send_contact" class="btn btn-primary">
                                <i class="bi bi-send me-1"></i>ส่งข้อความ
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ตารางการติดต่อ -->
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-table me-2"></i>รายการการติดต่อจากลูกค้า</h5>
                <span class="badge bg-light text-dark">แสดง <?= count($contacts) ?> จาก <?= $total_items ?> รายการ</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($contacts)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-envelope-open fs-1 text-muted mb-3"></i>
                        <h5>ไม่พบข้อมูลการติดต่อ</h5>
                        <p class="text-muted">ไม่มีการติดต่อที่ตรงกับเงื่อนไขการกรอง</p>
                        <a href="<?= $clear_filters_url ?>" class="btn btn-primary">
                            <i class="bi bi-eye me-1"></i>ดูการติดต่อทั้งหมด
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ลูกค้า</th>
                                    <th>หัวข้อ</th>
                                    <th>ข้อความ</th>
                                    <th>วันที่ส่ง</th>
                                    <th>สถานะ</th>
                                    <th>ตอบกลับแล้ว</th>
                                    <th>การดำเนินการ</th>
                                </tr>
                            </thead>
                                    <tbody>
                                        <?php foreach ($contacts as $contact): ?>
                                            <tr>
                                                <!-- ข้อมูลลูกค้า -->
                                                <td data-label="ลูกค้า">
                                                    <strong><?= htmlspecialchars($contact['customer_name']) ?></strong><br>
                                                    <small
                                                        class="text-muted"><?= htmlspecialchars($contact['customer_email']) ?></small>
                                                </td>
                                                <td data-label="หัวข้อ"><?= getSubjectThai($contact['subject']) ?></td>
                                                <td data-label="ข้อความ">
                                                    <?php if (strlen($contact['message']) > 100): ?>
                                                        <div>
                                                            <?= htmlspecialchars(substr($contact['message'], 0, 100)) ?>...
                                                        </div>
                                                        <small class="text-muted">คลิก "ดูรายละเอียด" เพื่ออ่านเพิ่มเติม</small>
                                                    <?php else: ?>
                                                        <?= nl2br(htmlspecialchars($contact['message'])) ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="วันที่ส่ง"><?= $contact['formatted_created_at'] ?></td>
                                                <td data-label="สถานะ">
                                                    <span
                                                        class="badge 
                                                    <?= $contact['status'] == 'pending' ? 'bg-warning text-dark' :
                                                        ($contact['status'] == 'in_progress' ? 'bg-info' : 'bg-success') ?>">
                                                        <?= getStatusThai($contact['status']) ?>
                                                    </span>
                                                </td>
                                                <td data-label="ตอบกลับแล้ว">
                                                    <?php if ($contact['replied_at']): ?>
                                                        <span class="text-success">
                                                            <i class="bi bi-check-circle-fill me-1"></i>
                                                            <?= $contact['formatted_replied_at'] ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="การดำเนินการ">
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button"
                                                            class="btn btn-outline-primary dropdown-toggle"
                                                            data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="bi bi-gear me-1"></i>จัดการ
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <button class="dropdown-item" data-bs-toggle="modal"
                                                                    data-bs-target="#contactModal<?= $contact['contact_id'] ?>">
                                                                    <i class="bi bi-eye me-2"></i>ดูรายละเอียด
                                                                </button>
                                                            </li>
                                                            <li>
                                                                <button class="dropdown-item" data-bs-toggle="modal"
                                                                    data-bs-target="#statusModal<?= $contact['contact_id'] ?>">
                                                                    <i class="bi bi-arrow-repeat me-2"></i>เปลี่ยนสถานะ
                                                                </button>
                                                            </li>
                                                            <li>
                                                                <button class="dropdown-item" data-bs-toggle="modal"
                                                                    data-bs-target="#replyModal<?= $contact['contact_id'] ?>">
                                                                    <i class="bi bi-reply me-2"></i>ตอบกลับลูกค้า
                                                                </button>
                                                            </li>
                                                        </ul>
                                                    </div>

                                                    <!-- Modal ดูรายละเอียด -->
                                                    <div class="modal fade" id="contactModal<?= $contact['contact_id'] ?>"
                                                        tabindex="-1" aria-labelledby="contactModalLabel<?= $contact['contact_id'] ?>" aria-hidden="true">
                                                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-primary text-white">
                                                                    <h5 class="modal-title" id="contactModalLabel<?= $contact['contact_id'] ?>"><i class="bi bi-info-circle me-2"></i>รายละเอียดการติดต่อ
                                                                        #<?= $contact['contact_id'] ?></h5>
                                                                    <button type="button" class="btn-close btn-close-white"
                                                                        data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="mb-3">
                                                                        <h6>ข้อมูลลูกค้า:</h6>
                                                                        <p><strong>ชื่อ:</strong>
                                                                            <?= htmlspecialchars($contact['customer_name']) ?>
                                                                        </p>
                                                                        <p><strong>อีเมล:</strong>
                                                                            <?= htmlspecialchars($contact['customer_email']) ?>
                                                                        </p>
                                                                        <p><strong>โทรศัพท์:</strong>
                                                                            <?= htmlspecialchars($contact['customer_phone']) ?>
                                                                        </p>
                                                                        <?php if ($contact['customer_username']): ?>
                                                                            <p><strong>Username:</strong>
                                                                                <?= htmlspecialchars($contact['customer_username']) ?>
                                                                            </p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <h6>หัวข้อ:</h6>
                                                                        <p><?= getSubjectThai($contact['subject']) ?></p>
                                                                    </div>
                                                                    <div class="mb-3">
                                                                        <h6>ข้อความจากลูกค้า:</h6>
                                                                        <div class="p-3 bg-light rounded">
                                                                            <?= nl2br(htmlspecialchars($contact['message'])) ?>
                                                                        </div>
                                                                    </div>

                                                                    <?php if ($contact['admin_reply_message']): ?>
                                                                        <div class="mb-3">
                                                                            <h6>คำตอบกลับจากผู้ดูแล:</h6>
                                                                            <div class="p-3 bg-success bg-opacity-10 rounded">
                                                                                <p><strong>หัวข้อ:</strong>
                                                                                    <?= htmlspecialchars($contact['admin_reply_subject']) ?>
                                                                                </p>
                                                                                <div class="mb-2">
                                                                                    <?= nl2br(htmlspecialchars($contact['admin_reply_message'])) ?>
                                                                                </div>
                                                                                <?php if ($contact['admin_name']): ?>
                                                                                    <small class="text-muted">
                                                                                        <i class="bi bi-person me-1"></i>
                                                                                        ตอบกลับโดย:
                                                                                        <?= htmlspecialchars($contact['admin_name']) ?>
                                                                                    </small>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?>

                                                                    <?php if ($contact['admin_notes']): ?>
                                                                        <div class="mb-3">
                                                                            <h6>หมายเหตุจากผู้ดูแล:</h6>
                                                                            <div class="p-3 bg-warning bg-opacity-10 rounded">
                                                                                <?= nl2br(htmlspecialchars($contact['admin_notes'])) ?>
                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?>

                                                                    <div class="mb-3">
                                                                        <h6>ข้อมูลเพิ่มเติม:</h6>
                                                                        <p><strong>วันที่ส่ง:</strong>
                                                                            <?= $contact['formatted_created_at'] ?></p>
                                                                        <p><strong>อัพเดทล่าสุด:</strong>
                                                                            <?= $contact['formatted_updated_at'] ?></p>
                                                                        <?php if ($contact['replied_at']): ?>
                                                                            <p><strong>ตอบกลับเมื่อ:</strong>
                                                                                <?= $contact['formatted_replied_at'] ?></p>
                                                                        <?php endif; ?>
                                                                        <p><strong>สถานะ:</strong>
                                                                            <span
                                                                                class="badge 
                                                                                <?= $contact['status'] == 'pending' ? 'bg-warning text-dark' :
                                                                                    ($contact['status'] == 'in_progress' ? 'bg-info' : 'bg-success') ?>">
                                                                                <?= getStatusThai($contact['status']) ?>
                                                                            </span>
                                                                        </p>
                                                                        <?php if ($contact['email_sent']): ?>
                                                                            <p><strong>สถานะอีเมล:</strong>
                                                                                <span class="badge bg-success">ส่งอีเมลแล้ว</span>
                                                                            </p>
                                                                        <?php elseif ($contact['replied_at']): ?>
                                                                            <p><strong>สถานะอีเมล:</strong>
                                                                                <span class="badge bg-danger">ส่งอีเมลไม่สำเร็จ</span>
                                                                            </p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary"
                                                                        data-bs-dismiss="modal">ปิด</button>
                                                                    <button type="button" class="btn btn-primary"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#replyModal<?= $contact['contact_id'] ?>">
                                                                        <i class="bi bi-reply me-1"></i>ตอบกลับลูกค้า
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Modal เปลี่ยนสถานะ -->
                                                    <div class="modal fade" id="statusModal<?= $contact['contact_id'] ?>"
                                                        tabindex="-1" aria-labelledby="statusModalLabel<?= $contact['contact_id'] ?>" aria-hidden="true">
                                                        <div class="modal-dialog modal-dialog-centered">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-primary text-white">
                                                                    <h5 class="modal-title" id="statusModalLabel<?= $contact['contact_id'] ?>"><i class="bi bi-arrow-repeat me-2"></i>เปลี่ยนสถานะการติดต่อ
                                                                        #<?= $contact['contact_id'] ?></h5>
                                                                    <button type="button" class="btn-close btn-close-white"
                                                                        data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <form method="POST">
                                                                    <?= csrfField() ?>
                                                                    <input type="hidden" name="update_contact_status" value="1">
                                                                    <input type="hidden" name="contact_id"
                                                                        value="<?= $contact['contact_id'] ?>">
                                                                    <div class="modal-body">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">สถานะ</label>
                                                                            <select name="status" class="form-select" required>
                                                                                <option value="pending"
                                                                                    <?= $contact['status'] == 'pending' ? 'selected' : '' ?>>รอดำเนินการ</option>
                                                                                <option value="in_progress"
                                                                                    <?= $contact['status'] == 'in_progress' ? 'selected' : '' ?>>กำลังดำเนินการ</option>
                                                                                <option value="completed"
                                                                                    <?= $contact['status'] == 'completed' ? 'selected' : '' ?>>เสร็จสิ้น</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">หมายเหตุ
                                                                                (ไม่บังคับ)</label>
                                                                            <textarea name="admin_notes" class="form-control"
                                                                                rows="3"
                                                                                placeholder="บันทึกหมายเหตุเพิ่มเติม..."><?= htmlspecialchars($contact['admin_notes'] ?? '') ?></textarea>
                                                                        </div>
                                                                        <div class="alert alert-info">
                                                                            <i class="bi bi-info-circle me-2"></i>
                                                                            <strong>ข้อมูลการติดต่อ:</strong><br>
                                                                            ลูกค้า:
                                                                            <?= htmlspecialchars($contact['customer_name']) ?><br>
                                                                            หัวข้อ:
                                                                            <?= getSubjectThai($contact['subject']) ?><br>
                                                                            วันที่: <?= $contact['formatted_created_at'] ?>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary"
                                                                            data-bs-dismiss="modal">ปิด</button>
                                                                        <button type="submit"
                                                                            class="btn btn-primary">อัพเดทสถานะ</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Modal ตอบกลับลูกค้า -->
                                                    <div class="modal fade" id="replyModal<?= $contact['contact_id'] ?>"
                                                        tabindex="-1" aria-labelledby="replyModalLabel<?= $contact['contact_id'] ?>" aria-hidden="true">
                                                        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-primary text-white">
                                                                    <h5 class="modal-title" id="replyModalLabel<?= $contact['contact_id'] ?>"><i class="bi bi-reply me-2"></i>ตอบกลับลูกค้า
                                                                        #<?= $contact['contact_id'] ?></h5>
                                                                    <button type="button" class="btn-close btn-close-white"
                                                                        data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <form method="POST" class="needs-validation" novalidate>
                                                                    <?= csrfField() ?>
                                                                    <input type="hidden" name="reply_contact" value="1">
                                                                    <input type="hidden" name="contact_id"
                                                                        value="<?= $contact['contact_id'] ?>">
                                                                    <div class="modal-body">
                                                                        <div class="row">
                                                                            <!-- ฝั่งซ้าย: ข้อความจากลูกค้า -->
                                                                            <div class="col-md-5">
                                                                                <div class="p-3 bg-light rounded h-100">
                                                                                    <h6><i class="bi bi-person me-2"></i>ข้อความจากลูกค้า:</h6>
                                                                                    <p class="mb-2"><strong>ลูกค้า:</strong>
                                                                                        <?= htmlspecialchars($contact['customer_name']) ?>
                                                                                    </p>
                                                                                    <p class="mb-2"><strong>อีเมล:</strong>
                                                                                        <?= htmlspecialchars($contact['customer_email']) ?>
                                                                                    </p>
                                                                                    <p class="mb-2"><strong>หัวข้อ:</strong>
                                                                                        <?= getSubjectThai($contact['subject']) ?></p>
                                                                                    <p class="mb-2"><strong>ข้อความ:</strong></p>
                                                                                    <div class="border-start border-3 border-primary ps-3" style="max-height: 250px; overflow-y: auto;">
                                                                                        <?= nl2br(htmlspecialchars($contact['message'])) ?>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <!-- ฝั่งขวา: ฟอร์มตอบกลับ -->
                                                                            <div class="col-md-7">
                                                                                <h6><i class="bi bi-reply me-2"></i>ตอบกลับลูกค้า:</h6>
                                                                                <div class="mb-3">
                                                                                    <label class="form-label">ถึง</label>
                                                                                    <input type="text" class="form-control"
                                                                                        value="<?= htmlspecialchars($contact['customer_name']) ?> (<?= htmlspecialchars($contact['customer_email']) ?>)"
                                                                                        readonly>
                                                                                </div>
                                                                                <div class="mb-3">
                                                                                    <label class="form-label">หัวข้อตอบกลับ <span class="text-danger">*</span></label>
                                                                                    <input type="text" name="subject"
                                                                                        class="form-control"
                                                                                        value="Re: <?= htmlspecialchars($contact['subject']) ?>"
                                                                                        required>
                                                                                    <div class="invalid-feedback">กรุณากรอกหัวข้อตอบกลับ</div>
                                                                                </div>
                                                                                <div class="mb-3">
                                                                                    <label class="form-label">ข้อความตอบกลับ <span class="text-danger">*</span></label>
                                                                                    <textarea name="message" class="form-control"
                                                                                        rows="6"
                                                                                        style="min-height: 150px; resize: vertical;"
                                                                                        placeholder="เขียนข้อความตอบกลับที่นี่..."
                                                                                        required><?= $contact['admin_reply_message'] ?? '' ?></textarea>
                                                                                    <div class="invalid-feedback">กรุณากรอกข้อความตอบกลับ</div>
                                                                                </div>
                                                                                <div class="mb-3">
                                                                                    <label class="form-label">สถานะหลังตอบกลับ</label>
                                                                                    <select name="status_after_reply" class="form-select">
                                                                                        <option value="in_progress"
                                                                                            <?= $contact['status'] == 'in_progress' ? 'selected' : '' ?>>กำลังดำเนินการ
                                                                                        </option>
                                                                                        <option value="completed"
                                                                                            <?= $contact['status'] == 'completed' ? 'selected' : '' ?>>เสร็จสิ้น</option>
                                                                                    </select>
                                                                                    <small class="form-text text-muted">เลือกสถานะหลังจากส่งข้อความตอบกลับ</small>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary"
                                                                            data-bs-dismiss="modal">ปิด</button>
                                                                        <button type="submit" class="btn btn-success">
                                                                            <i class="bi bi-send me-1"></i>ส่งข้อความตอบกลับ
                                                                        </button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                    <?php endif; ?>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="card-footer">
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center mb-0">
                                    <!-- ปุ่มก่อนหน้า -->
                                    <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page - 1])) ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    
                                    <?php
                                    // แสดงหมายเลขหน้า
                                    $start_page = max(1, $current_page - 2);
                                    $end_page = min($total_pages, $current_page + 2);
                                    
                                    if ($start_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                                        </li>
                                        <?php if ($start_page > 2): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($end_page < $total_pages): ?>
                                        <?php if ($end_page < $total_pages - 1): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>"><?= $total_pages ?></a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <!-- ปุ่มถัดไป -->
                                    <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page + 1])) ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                            <div class="text-center mt-2">
                                <small class="text-muted">หน้า <?= $current_page ?> จาก <?= $total_pages ?> (ทั้งหมด <?= $total_items ?> รายการ)</small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include '../templates/footer-admin.php'; ?>
    <?php include '../templates/admin-scripts.php'; ?>
</body>

</html>
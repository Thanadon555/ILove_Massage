<?php
/**
 * Validation Component
 * Provides a Validator class for validating form inputs
 */

class Validator {
    private $errors = [];
    
    /**
     * Validate required field
     * 
     * @param string $field Field name for error tracking
     * @param mixed $value Value to validate
     * @param string $fieldName Display name for error message
     * @return bool True if valid, false otherwise
     */
    public function required($field, $value, $fieldName) {
        if (empty($value) && $value !== '0') {
            $this->errors[$field] = "$fieldName จำเป็นต้องกรอก";
            return false;
        }
        return true;
    }
    
    /**
     * Validate email format
     * 
     * @param string $field Field name for error tracking
     * @param string $value Email to validate
     * @param string $fieldName Display name for error message
     * @return bool True if valid, false otherwise
     */
    public function email($field, $value, $fieldName) {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "$fieldName ไม่ถูกต้อง";
            return false;
        }
        return true;
    }
    
    /**
     * Validate phone number (10 digits)
     * 
     * @param string $field Field name for error tracking
     * @param string $value Phone number to validate
     * @param string $fieldName Display name for error message
     * @return bool True if valid, false otherwise
     */
    public function phone($field, $value, $fieldName) {
        if (!empty($value) && !preg_match('/^[0-9]{10}$/', $value)) {
            $this->errors[$field] = "$fieldName ต้องเป็นตัวเลข 10 หลัก";
            return false;
        }
        return true;
    }
    
    /**
     * Validate number is within range
     * 
     * @param string $field Field name for error tracking
     * @param mixed $value Number to validate
     * @param int|float $min Minimum value
     * @param int|float $max Maximum value
     * @param string $fieldName Display name for error message
     * @return bool True if valid, false otherwise
     */
    public function numberRange($field, $value, $min, $max, $fieldName) {
        if (!is_numeric($value) || $value < $min || $value > $max) {
            $this->errors[$field] = "$fieldName ต้องอยู่ระหว่าง $min ถึง $max";
            return false;
        }
        return true;
    }
    
    /**
     * Validate file upload
     * 
     * @param string $field Field name for error tracking
     * @param array $file File array from $_FILES
     * @param array $allowedTypes Array of allowed MIME types
     * @param int $maxSize Maximum file size in bytes
     * @param string $fieldName Display name for error message
     * @return bool True if valid, false otherwise
     */
    public function file($field, $file, $allowedTypes, $maxSize, $fieldName) {
        // If no file uploaded, it's optional
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return true;
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[$field] = "เกิดข้อผิดพลาดในการอัพโหลด $fieldName";
            return false;
        }
        
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $fileType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($fileType, $allowedTypes)) {
            $this->errors[$field] = "ประเภทไฟล์ $fieldName ไม่ถูกต้อง";
            return false;
        }
        
        // Validate file size
        if ($file['size'] > $maxSize) {
            $maxSizeMB = round($maxSize / 1024 / 1024, 2);
            $this->errors[$field] = "ขนาดไฟล์ $fieldName ต้องไม่เกิน $maxSizeMB MB";
            return false;
        }
        
        return true;
    }
    
    /**
     * Get all validation errors
     * 
     * @return array Array of error messages
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Check if there are any validation errors
     * 
     * @return bool True if errors exist, false otherwise
     */
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    /**
     * Get error message for specific field
     * 
     * @param string $field Field name
     * @return string Error message or empty string
     */
    public function getError($field) {
        return $this->errors[$field] ?? '';
    }
    
    /**
     * Clear all errors
     */
    public function clearErrors() {
        $this->errors = [];
    }
    
    /**
     * Add a custom error message
     * 
     * @param string $field Field name for error tracking
     * @param string $message Error message
     */
    public function addError($field, $message) {
        $this->errors[$field] = $message;
    }
}
?>

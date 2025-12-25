<?php
/**
 * Error Logger Component
 * Provides functions for logging errors to file
 */

/**
 * Log error message to file
 * 
 * @param string $message Error message to log
 * @param array $context Additional context information (optional)
 * @return bool True if logged successfully, false otherwise
 */
function logError($message, $context = []) {
    // Define log directory and file
    $logDir = __DIR__ . '/../../logs';
    $logFile = $logDir . '/admin_errors.log';
    
    // Create logs directory if it doesn't exist
    if (!file_exists($logDir)) {
        if (!mkdir($logDir, 0755, true)) {
            error_log("Failed to create log directory: $logDir");
            return false;
        }
    }
    
    // Prepare log message
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    $logMessage = "[$timestamp] $message$contextStr\n";
    
    // Write to log file
    $result = error_log($logMessage, 3, $logFile);
    
    return $result !== false;
}

/**
 * Log exception to file
 * 
 * @param Exception $exception Exception object
 * @param array $context Additional context information (optional)
 * @return bool True if logged successfully, false otherwise
 */
function logException($exception, $context = []) {
    $message = sprintf(
        'Exception: %s | File: %s | Line: %d | Message: %s',
        get_class($exception),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getMessage()
    );
    
    return logError($message, $context);
}

/**
 * Log database error
 * 
 * @param string $query SQL query that caused the error
 * @param string $error Error message
 * @param array $context Additional context information (optional)
 * @return bool True if logged successfully, false otherwise
 */
function logDatabaseError($query, $error, $context = []) {
    $message = "Database Error | Query: $query | Error: $error";
    return logError($message, $context);
}

/**
 * Log security event
 * 
 * @param string $event Security event description
 * @param array $context Additional context information (optional)
 * @return bool True if logged successfully, false otherwise
 */
function logSecurityEvent($event, $context = []) {
    $message = "Security Event: $event";
    
    // Add user information if available
    if (isset($_SESSION['user_id'])) {
        $context['user_id'] = $_SESSION['user_id'];
    }
    if (isset($_SESSION['username'])) {
        $context['username'] = $_SESSION['username'];
    }
    
    // Add IP address
    $context['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    return logError($message, $context);
}
?>

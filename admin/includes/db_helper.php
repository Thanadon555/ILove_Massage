<?php
/**
 * Database Helper Component
 * Provides a DatabaseHelper class for secure database operations using prepared statements
 */

class DatabaseHelper {
    private $conn;
    
    /**
     * Constructor
     * 
     * @param mysqli $connection Database connection object
     */
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Execute prepared statement
     * 
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @param string $types Parameter types (i=integer, d=double, s=string, b=blob)
     * @return mysqli_stmt Statement object
     * @throws Exception If prepare or execute fails
     */
    public function execute($sql, $params = [], $types = '') {
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $this->conn->error);
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $result = $stmt->execute();
        
        if (!$result) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        return $stmt;
    }
    
    /**
     * Fetch single row
     * 
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @param string $types Parameter types
     * @return array|null Associative array or null if no result
     */
    public function fetchOne($sql, $params = [], $types = '') {
        $stmt = $this->execute($sql, $params, $types);
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    /**
     * Fetch all rows
     * 
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @param string $types Parameter types
     * @return array Array of associative arrays
     */
    public function fetchAll($sql, $params = [], $types = '') {
        $stmt = $this->execute($sql, $params, $types);
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }
    
    /**
     * Update record
     * 
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @param string $where WHERE clause with placeholders
     * @param array $whereParams Parameters for WHERE clause
     * @param string $whereTypes Parameter types for WHERE clause
     * @return mysqli_stmt Statement object
     */
    public function update($table, $data, $where, $whereParams, $whereTypes) {
        $sets = [];
        $params = [];
        $types = '';
        
        foreach ($data as $key => $value) {
            $sets[] = "`$key` = ?";
            $params[] = $value;
            $types .= $this->getParamType($value);
        }
        
        $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE $where";
        $params = array_merge($params, $whereParams);
        $types .= $whereTypes;
        
        return $this->execute($sql, $params, $types);
    }
    
    /**
     * Insert record
     * 
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @return mysqli_stmt Statement object
     */
    public function insert($table, $data) {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($data), '?');
        $params = array_values($data);
        $types = '';
        
        foreach ($params as $param) {
            $types .= $this->getParamType($param);
        }
        
        $sql = "INSERT INTO `$table` (" . implode(', ', array_map(function($col) {
            return "`$col`";
        }, $columns)) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        return $this->execute($sql, $params, $types);
    }
    
    /**
     * Delete record
     * 
     * @param string $table Table name
     * @param string $where WHERE clause with placeholders
     * @param array $params Parameters for WHERE clause
     * @param string $types Parameter types
     * @return mysqli_stmt Statement object
     */
    public function delete($table, $where, $params, $types) {
        $sql = "DELETE FROM `$table` WHERE $where";
        return $this->execute($sql, $params, $types);
    }
    
    /**
     * Get parameter type for bind_param
     * 
     * @param mixed $value Value to check
     * @return string Type character (i, d, or s)
     */
    private function getParamType($value) {
        if (is_int($value)) {
            return 'i';
        }
        if (is_float($value)) {
            return 'd';
        }
        return 's';
    }
    
    /**
     * Get last insert ID
     * 
     * @return int Last inserted ID
     */
    public function getLastInsertId() {
        return $this->conn->insert_id;
    }
    
    /**
     * Get affected rows count
     * 
     * @return int Number of affected rows
     */
    public function getAffectedRows() {
        return $this->conn->affected_rows;
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        $this->conn->begin_transaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        $this->conn->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        $this->conn->rollback();
    }
}
?>

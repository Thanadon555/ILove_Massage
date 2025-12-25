<?php
/**
 * PHP 7.1 Compatibility Checker
 * 
 * This component scans PHP files to detect syntax and features
 * that are incompatible with PHP 7.1
 */

class PHPCompatibilityChecker {
    
    private $results = [];
    private $scannedFiles = 0;
    private $incompatibleFiles = 0;
    
    /**
     * Scan directory recursively for PHP files
     * 
     * @param string $path Directory path to scan
     * @param array $excludeDirs Directories to exclude from scan
     * @return array List of PHP files found
     */
    public function scanDirectory($path, $excludeDirs = ['vendor', 'node_modules', '.git', 'uploads']) {
        $phpFiles = [];
        
        if (!is_dir($path)) {
            return $phpFiles;
        }
        
        $items = scandir($path);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            
            // Skip excluded directories
            if (is_dir($fullPath)) {
                $shouldExclude = false;
                foreach ($excludeDirs as $excludeDir) {
                    if (basename($fullPath) === $excludeDir) {
                        $shouldExclude = true;
                        break;
                    }
                }
                
                if (!$shouldExclude) {
                    $phpFiles = array_merge($phpFiles, $this->scanDirectory($fullPath, $excludeDirs));
                }
            } elseif (is_file($fullPath) && pathinfo($fullPath, PATHINFO_EXTENSION) === 'php') {
                $phpFiles[] = $fullPath;
            }
        }
        
        return $phpFiles;
    }
    
    /**
     * Check file syntax for PHP 7.1 compatibility
     * 
     * @param string $filepath Path to PHP file
     * @return array Analysis results
     */
    public function checkFileSyntax($filepath) {
        $result = [
            'file' => $filepath,
            'issues' => [],
            'is_compatible' => true
        ];
        
        if (!file_exists($filepath) || !is_readable($filepath)) {
            $result['issues'][] = [
                'line' => 0,
                'type' => 'file_error',
                'code' => '',
                'message' => 'File not readable',
                'suggestion' => 'Check file permissions'
            ];
            $result['is_compatible'] = false;
            return $result;
        }
        
        $content = file_get_contents($filepath);
        $issues = $this->detectIncompatibleFeatures($content);
        
        if (!empty($issues)) {
            $result['issues'] = $issues;
            $result['is_compatible'] = false;
        }
        
        return $result;
    }
    
    /**
     * Detect incompatible features using regex patterns
     * 
     * @param string $content File content to analyze
     * @return array List of detected issues
     */
    public function detectIncompatibleFeatures($content) {
        $issues = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $lineNumber => $line) {
            $lineNum = $lineNumber + 1;
            
            // Skip comments and strings to avoid false positives
            $trimmedLine = trim($line);
            if (empty($trimmedLine) || strpos($trimmedLine, '//') === 0 || strpos($trimmedLine, '#') === 0 || strpos($trimmedLine, '/*') === 0 || strpos($trimmedLine, '*') === 0) {
                continue;
            }
            
            // Detect typed properties (PHP 7.4+)
            if (preg_match('/\b(private|protected|public)\s+(string|int|float|bool|array|object|iterable)\s+\$\w+/', $line, $matches)) {
                $issues[] = [
                    'line' => $lineNum,
                    'type' => 'typed_property',
                    'code' => trim($line),
                    'message' => 'Typed properties require PHP 7.4+',
                    'suggestion' => 'Remove type hint from property declaration'
                ];
            }
            
            // Detect arrow functions (PHP 7.4+)
            if (preg_match('/fn\s*\([^)]*\)\s*=>/', $line, $matches)) {
                $issues[] = [
                    'line' => $lineNum,
                    'type' => 'arrow_function',
                    'code' => trim($line),
                    'message' => 'Arrow functions require PHP 7.4+',
                    'suggestion' => 'Use traditional function() syntax instead'
                ];
            }
            
            // Detect null coalescing assignment (PHP 7.4+)
            if (preg_match('/\$\w+\s*\?\?=/', $line, $matches)) {
                $issues[] = [
                    'line' => $lineNum,
                    'type' => 'null_coalescing_assignment',
                    'code' => trim($line),
                    'message' => 'Null coalescing assignment operator requires PHP 7.4+',
                    'suggestion' => 'Use: $var = $var ?? \'default\' instead'
                ];
            }
            
            // Detect nullsafe operator (PHP 8.0+)
            if (preg_match('/\$\w+\?->/', $line, $matches)) {
                $issues[] = [
                    'line' => $lineNum,
                    'type' => 'nullsafe_operator',
                    'code' => trim($line),
                    'message' => 'Nullsafe operator requires PHP 8.0+',
                    'suggestion' => 'Use null check: if ($obj !== null) { $obj->method(); }'
                ];
            }
            
            // Detect match expressions (PHP 8.0+)
            if (preg_match('/\bmatch\s*\(/', $line, $matches)) {
                $issues[] = [
                    'line' => $lineNum,
                    'type' => 'match_expression',
                    'code' => trim($line),
                    'message' => 'Match expressions require PHP 8.0+',
                    'suggestion' => 'Use switch statement instead'
                ];
            }
            
            // Detect named arguments (PHP 8.0+)
            // Look for function calls with named parameters: functionName(param: value)
            if (preg_match('/\w+\s*\(\s*\w+\s*:\s*[^,)]+/', $line, $matches)) {
                // Additional check to avoid false positives with ternary operators and strings
                if (!preg_match('/\?[^:]*:/', $line)) {
                    // Skip if the pattern is inside a string (single or double quotes)
                    $inString = false;
                    if (preg_match('/["\'].*\w+\s*:\s*.*["\']/', $line)) {
                        $inString = true;
                    }
                    
                    if (!$inString) {
                        $issues[] = [
                            'line' => $lineNum,
                            'type' => 'named_argument',
                            'code' => trim($line),
                            'message' => 'Named arguments require PHP 8.0+',
                            'suggestion' => 'Use positional arguments instead'
                        ];
                    }
                }
            }
            
            // Detect union types (PHP 8.0+)
            if (preg_match('/function\s+\w+\s*\([^)]*\b(string|int|float|bool|array|object)\s*\|\s*(string|int|float|bool|array|object|null)\b/', $line, $matches)) {
                $issues[] = [
                    'line' => $lineNum,
                    'type' => 'union_type',
                    'code' => trim($line),
                    'message' => 'Union types require PHP 8.0+',
                    'suggestion' => 'Use single type hint or remove type hint'
                ];
            }
            
            // Detect union types in return type declarations
            if (preg_match('/:\s*(string|int|float|bool|array|object)\s*\|\s*(string|int|float|bool|array|object|null)\s*\{/', $line, $matches)) {
                $issues[] = [
                    'line' => $lineNum,
                    'type' => 'union_return_type',
                    'code' => trim($line),
                    'message' => 'Union return types require PHP 8.0+',
                    'suggestion' => 'Use single return type or remove type hint'
                ];
            }
        }
        
        return $issues;
    }
    
    /**
     * Generate formatted report from scan results
     * 
     * @param array $results Scan results
     * @param string $format Output format: 'html' or 'text'
     * @return string Formatted report
     */
    public function generateReport($results, $format = 'html') {
        if ($format === 'html') {
            return $this->generateHTMLReport($results);
        } else {
            return $this->generateTextReport($results);
        }
    }
    
    /**
     * Generate HTML formatted report
     * 
     * @param array $results Scan results
     * @return string HTML report
     */
    private function generateHTMLReport($results) {
        $totalFiles = count($results);
        $incompatibleFiles = 0;
        $totalIssues = 0;
        
        foreach ($results as $result) {
            if (!$result['is_compatible']) {
                $incompatibleFiles++;
                $totalIssues += count($result['issues']);
            }
        }
        
        $compatibleFiles = $totalFiles - $incompatibleFiles;
        
        $html = '<div class="compatibility-report">';
        $html .= '<h3>PHP 7.1 Compatibility Report</h3>';
        $html .= '<div class="report-summary">';
        $html .= '<p><strong>Total Files Scanned:</strong> ' . $totalFiles . '</p>';
        $html .= '<p><strong>Compatible Files:</strong> <span class="text-success">' . $compatibleFiles . '</span></p>';
        $html .= '<p><strong>Incompatible Files:</strong> <span class="text-danger">' . $incompatibleFiles . '</span></p>';
        $html .= '<p><strong>Total Issues Found:</strong> <span class="text-warning">' . $totalIssues . '</span></p>';
        $html .= '</div>';
        
        if ($incompatibleFiles > 0) {
            $html .= '<h4 class="mt-4">Incompatibility Details</h4>';
            $html .= '<div class="table-responsive">';
            $html .= '<table class="table table-bordered table-striped">';
            $html .= '<thead><tr>';
            $html .= '<th>File</th>';
            $html .= '<th>Line</th>';
            $html .= '<th>Issue Type</th>';
            $html .= '<th>Code</th>';
            $html .= '<th>Message</th>';
            $html .= '<th>Suggestion</th>';
            $html .= '</tr></thead>';
            $html .= '<tbody>';
            
            foreach ($results as $result) {
                if (!$result['is_compatible']) {
                    foreach ($result['issues'] as $issue) {
                        $html .= '<tr>';
                        $html .= '<td><code>' . htmlspecialchars($result['file']) . '</code></td>';
                        $html .= '<td>' . $issue['line'] . '</td>';
                        $html .= '<td><span class="badge badge-warning">' . htmlspecialchars($issue['type']) . '</span></td>';
                        $html .= '<td><code>' . htmlspecialchars($issue['code']) . '</code></td>';
                        $html .= '<td>' . htmlspecialchars($issue['message']) . '</td>';
                        $html .= '<td>' . htmlspecialchars($issue['suggestion']) . '</td>';
                        $html .= '</tr>';
                    }
                }
            }
            
            $html .= '</tbody></table>';
            $html .= '</div>';
        } else {
            $html .= '<div class="alert alert-success mt-4">';
            $html .= '<strong>Success!</strong> All scanned files are compatible with PHP 7.1';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate text formatted report
     * 
     * @param array $results Scan results
     * @return string Text report
     */
    private function generateTextReport($results) {
        $totalFiles = count($results);
        $incompatibleFiles = 0;
        $totalIssues = 0;
        
        foreach ($results as $result) {
            if (!$result['is_compatible']) {
                $incompatibleFiles++;
                $totalIssues += count($result['issues']);
            }
        }
        
        $compatibleFiles = $totalFiles - $incompatibleFiles;
        
        $text = "=================================================\n";
        $text .= "PHP 7.1 Compatibility Report\n";
        $text .= "=================================================\n\n";
        $text .= "Total Files Scanned: $totalFiles\n";
        $text .= "Compatible Files: $compatibleFiles\n";
        $text .= "Incompatible Files: $incompatibleFiles\n";
        $text .= "Total Issues Found: $totalIssues\n\n";
        
        if ($incompatibleFiles > 0) {
            $text .= "=================================================\n";
            $text .= "Incompatibility Details\n";
            $text .= "=================================================\n\n";
            
            foreach ($results as $result) {
                if (!$result['is_compatible']) {
                    $text .= "File: " . $result['file'] . "\n";
                    $text .= str_repeat("-", 80) . "\n";
                    
                    foreach ($result['issues'] as $issue) {
                        $text .= "  Line " . $issue['line'] . ": [" . $issue['type'] . "]\n";
                        $text .= "  Code: " . $issue['code'] . "\n";
                        $text .= "  Message: " . $issue['message'] . "\n";
                        $text .= "  Suggestion: " . $issue['suggestion'] . "\n\n";
                    }
                }
            }
        } else {
            $text .= "SUCCESS! All scanned files are compatible with PHP 7.1\n";
        }
        
        return $text;
    }
    
    /**
     * Scan entire project and return results
     * 
     * @param string $rootPath Root directory to scan
     * @return array Scan results
     */
    public function scanProject($rootPath) {
        $this->results = [];
        $this->scannedFiles = 0;
        $this->incompatibleFiles = 0;
        
        $phpFiles = $this->scanDirectory($rootPath);
        
        foreach ($phpFiles as $file) {
            $result = $this->checkFileSyntax($file);
            $this->results[] = $result;
            $this->scannedFiles++;
            
            if (!$result['is_compatible']) {
                $this->incompatibleFiles++;
            }
        }
        
        return $this->results;
    }
    
    /**
     * Get scan statistics
     * 
     * @return array Statistics
     */
    public function getStatistics() {
        return [
            'scanned_files' => $this->scannedFiles,
            'incompatible_files' => $this->incompatibleFiles,
            'compatible_files' => $this->scannedFiles - $this->incompatibleFiles
        ];
    }
}

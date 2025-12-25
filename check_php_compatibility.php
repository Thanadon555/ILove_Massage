<?php
/**
 * Standalone PHP 7.1 Compatibility Checker
 * 
 * Command-line tool to scan PHP files for compatibility issues
 * 
 * Usage: php check_php_compatibility.php [options]
 * 
 * Options:
 *   --path=<directory>    Directory to scan (default: current directory)
 *   --exclude=<dirs>      Comma-separated list of directories to exclude
 *   --no-color            Disable color output
 *   --help                Show this help message
 */

// Include the compatibility checker class
require_once __DIR__ . '/admin/includes/php_compatibility_checker.php';

class CompatibilityCheckerCLI {
    
    private $checker;
    private $useColor = true;
    private $startTime;
    
    // ANSI color codes
    const COLOR_RESET = "\033[0m";
    const COLOR_RED = "\033[31m";
    const COLOR_GREEN = "\033[32m";
    const COLOR_YELLOW = "\033[33m";
    const COLOR_BLUE = "\033[34m";
    const COLOR_CYAN = "\033[36m";
    const COLOR_BOLD = "\033[1m";
    
    public function __construct() {
        $this->checker = new PHPCompatibilityChecker();
        $this->startTime = microtime(true);
        
        // Detect if color is supported
        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows - check if ANSICON or Windows 10+ with VT100 support
            $this->useColor = getenv('ANSICON') !== false || 
                             (function_exists('sapi_windows_vt100_support') && sapi_windows_vt100_support(STDOUT));
        }
    }
    
    /**
     * Colorize text for terminal output
     */
    private function colorize($text, $color) {
        if (!$this->useColor) {
            return $text;
        }
        return $color . $text . self::COLOR_RESET;
    }
    
    /**
     * Print header
     */
    private function printHeader() {
        echo "\n";
        echo $this->colorize("=================================================", self::COLOR_CYAN) . "\n";
        echo $this->colorize("  PHP 7.1 Compatibility Checker", self::COLOR_BOLD . self::COLOR_CYAN) . "\n";
        echo $this->colorize("=================================================", self::COLOR_CYAN) . "\n";
        echo "\n";
    }
    
    /**
     * Print help message
     */
    private function printHelp() {
        echo "Usage: php check_php_compatibility.php [options]\n\n";
        echo "Options:\n";
        echo "  --path=<directory>    Directory to scan (default: current directory)\n";
        echo "  --exclude=<dirs>      Comma-separated list of directories to exclude\n";
        echo "                        (default: vendor,node_modules,.git,uploads)\n";
        echo "  --no-color            Disable color output\n";
        echo "  --help                Show this help message\n\n";
        echo "Examples:\n";
        echo "  php check_php_compatibility.php\n";
        echo "  php check_php_compatibility.php --path=admin\n";
        echo "  php check_php_compatibility.php --exclude=vendor,tests\n";
        echo "  php check_php_compatibility.php --no-color\n\n";
    }
    
    /**
     * Parse command line arguments
     */
    private function parseArguments($argv) {
        $options = [
            'path' => '.',
            'exclude' => ['vendor', 'node_modules', '.git', 'uploads'],
            'help' => false
        ];
        
        foreach ($argv as $arg) {
            if (strpos($arg, '--path=') === 0) {
                $options['path'] = substr($arg, 7);
            } elseif (strpos($arg, '--exclude=') === 0) {
                $excludeStr = substr($arg, 10);
                $options['exclude'] = array_map('trim', explode(',', $excludeStr));
            } elseif ($arg === '--no-color') {
                $this->useColor = false;
            } elseif ($arg === '--help' || $arg === '-h') {
                $options['help'] = true;
            }
        }
        
        return $options;
    }
    
    /**
     * Print progress indicator
     */
    private function printProgress($current, $total, $file) {
        $percentage = ($current / $total) * 100;
        $barLength = 40;
        $filledLength = (int)($barLength * $current / $total);
        
        $bar = str_repeat('=', $filledLength) . str_repeat('-', $barLength - $filledLength);
        
        // Truncate filename if too long
        $displayFile = strlen($file) > 50 ? '...' . substr($file, -47) : $file;
        
        // Clear line and print progress
        echo "\r";
        echo $this->colorize(sprintf("[%s] %3d%%", $bar, $percentage), self::COLOR_CYAN);
        echo " " . $this->colorize("Scanning:", self::COLOR_BLUE) . " $displayFile";
        echo str_repeat(' ', 10); // Clear any remaining characters
        
        if ($current === $total) {
            echo "\n";
        }
    }
    
    /**
     * Print summary statistics
     */
    private function printSummary($results) {
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
        $elapsedTime = round(microtime(true) - $this->startTime, 2);
        
        echo "\n";
        echo $this->colorize("=================================================", self::COLOR_CYAN) . "\n";
        echo $this->colorize("  Scan Summary", self::COLOR_BOLD . self::COLOR_CYAN) . "\n";
        echo $this->colorize("=================================================", self::COLOR_CYAN) . "\n";
        echo "\n";
        echo "Total Files Scanned:   " . $this->colorize($totalFiles, self::COLOR_BLUE) . "\n";
        echo "Compatible Files:      " . $this->colorize($compatibleFiles, self::COLOR_GREEN) . "\n";
        echo "Incompatible Files:    " . $this->colorize($incompatibleFiles, $incompatibleFiles > 0 ? self::COLOR_RED : self::COLOR_GREEN) . "\n";
        echo "Total Issues Found:    " . $this->colorize($totalIssues, $totalIssues > 0 ? self::COLOR_YELLOW : self::COLOR_GREEN) . "\n";
        echo "Scan Time:             " . $this->colorize($elapsedTime . "s", self::COLOR_BLUE) . "\n";
        echo "\n";
    }
    
    /**
     * Print detailed issues
     */
    private function printIssues($results) {
        $hasIssues = false;
        
        foreach ($results as $result) {
            if (!$result['is_compatible']) {
                $hasIssues = true;
                break;
            }
        }
        
        if (!$hasIssues) {
            echo $this->colorize("✓ SUCCESS!", self::COLOR_BOLD . self::COLOR_GREEN) . " All scanned files are compatible with PHP 7.1\n\n";
            return;
        }
        
        echo $this->colorize("=================================================", self::COLOR_CYAN) . "\n";
        echo $this->colorize("  Incompatibility Details", self::COLOR_BOLD . self::COLOR_CYAN) . "\n";
        echo $this->colorize("=================================================", self::COLOR_CYAN) . "\n";
        echo "\n";
        
        foreach ($results as $result) {
            if (!$result['is_compatible']) {
                echo $this->colorize("File: " . $result['file'], self::COLOR_BOLD . self::COLOR_RED) . "\n";
                echo str_repeat("-", 80) . "\n";
                
                foreach ($result['issues'] as $issue) {
                    echo "  " . $this->colorize("Line " . $issue['line'], self::COLOR_YELLOW) . ": ";
                    echo $this->colorize("[" . $issue['type'] . "]", self::COLOR_RED) . "\n";
                    echo "  " . $this->colorize("Code:", self::COLOR_BLUE) . " " . $issue['code'] . "\n";
                    echo "  " . $this->colorize("Issue:", self::COLOR_YELLOW) . " " . $issue['message'] . "\n";
                    echo "  " . $this->colorize("Fix:", self::COLOR_GREEN) . " " . $issue['suggestion'] . "\n";
                    echo "\n";
                }
            }
        }
    }
    
    /**
     * Run the compatibility check
     */
    public function run($argv) {
        $options = $this->parseArguments($argv);
        
        if ($options['help']) {
            $this->printHelp();
            return 0;
        }
        
        $this->printHeader();
        
        $scanPath = realpath($options['path']);
        
        if (!$scanPath || !is_dir($scanPath)) {
            echo $this->colorize("Error: Invalid directory path: " . $options['path'], self::COLOR_RED) . "\n\n";
            return 1;
        }
        
        echo "Scanning directory: " . $this->colorize($scanPath, self::COLOR_BLUE) . "\n";
        echo "Excluded directories: " . $this->colorize(implode(', ', $options['exclude']), self::COLOR_YELLOW) . "\n";
        echo "\n";
        
        // Get list of files first
        echo $this->colorize("Discovering PHP files...", self::COLOR_CYAN) . "\n";
        $phpFiles = $this->checker->scanDirectory($scanPath, $options['exclude']);
        $totalFiles = count($phpFiles);
        
        if ($totalFiles === 0) {
            echo $this->colorize("No PHP files found in the specified directory.", self::COLOR_YELLOW) . "\n\n";
            return 0;
        }
        
        echo "Found " . $this->colorize($totalFiles, self::COLOR_GREEN) . " PHP files\n\n";
        
        // Scan files with progress indicator
        $results = [];
        $current = 0;
        
        foreach ($phpFiles as $file) {
            $current++;
            $this->printProgress($current, $totalFiles, $file);
            $results[] = $this->checker->checkFileSyntax($file);
        }
        
        // Print summary and details
        $this->printSummary($results);
        $this->printIssues($results);
        
        // Return exit code (0 = success, 1 = issues found)
        $hasIssues = false;
        foreach ($results as $result) {
            if (!$result['is_compatible']) {
                $hasIssues = true;
                break;
            }
        }
        
        return $hasIssues ? 1 : 0;
    }
}

// Run the CLI tool
if (php_sapi_name() === 'cli') {
    $cli = new CompatibilityCheckerCLI();
    $exitCode = $cli->run($argv);
    exit($exitCode);
} else {
    echo "This script must be run from the command line.\n";
    exit(1);
}

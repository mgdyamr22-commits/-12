<?php
/**
 * Almakhzoun Pro - Security CLI & Background Worker Task Runner
 * ----------------------------------------------------------------------------------
 * Handles heavy tasks asynchronously (File Scan, Malware Scan, Integrity Check, 
 * Permission Audit, Cleanup) to keep page load lightning fast.
 * 
 * PSR-12 Compliant, fully standalone, optimized for shared hosting/cPanel.
 * SPDX-License-Identifier: Apache-2.0
 */

// If triggered from browser or server inline, allow execution via constant
if (php_sapi_name() !== 'cli' && !defined('SEC_CLI_ALLOW')) {
    die("Access Denied. CLI only.");
}

require_once __DIR__ . '/SecurityCore.php';

// Parse CLI or inline parameters
if (php_sapi_name() === 'cli') {
    $options = getopt("", ["task:"]);
    $task = $options['task'] ?? '';
} else {
    $task = $_GET['task'] ?? $argv_task ?? '';
}

class SecurityCli {
    public static function run($task) {
        switch ($task) {
            case 'self-healing':
                self::log("Starting Self Healing...");
                SecurityCore::forceSelfHealing();
                self::log("Self Healing completed successfully.");
                break;
            case 'file-scan':
            case 'integrity-check':
                self::log("Starting File Integrity Check...");
                self::runIntegrityCheck();
                self::log("File Integrity Check completed.");
                break;
            case 'malware-scan':
                self::log("Starting Malware Scan...");
                self::runMalwareScan();
                self::log("Malware Scan completed.");
                break;
            case 'permission-audit':
                self::log("Starting Permission Audit...");
                self::runPermissionAudit();
                self::log("Permission Audit completed.");
                break;
            case 'cleanup':
                self::log("Starting Cleanup...");
                self::runCleanup();
                self::log("Cleanup completed.");
                break;
            default:
                self::log("Unknown task: $task");
                self::log("Available tasks: self-healing, file-scan, integrity-check, malware-scan, permission-audit, cleanup");
                break;
        }
    }

    private static function log($msg) {
        $logLine = "[" . date('Y-m-d H:i:s') . "] " . $msg;
        if (php_sapi_name() === 'cli') {
            echo $logLine . "\n";
        }
        
        // Always write background task logs to a file
        try {
            $logDir = dirname(dirname(__DIR__)) . '/storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            @file_put_contents($logDir . '/background_tasks.log', $logLine . "\n", FILE_APPEND);
        } catch (Exception $e) {}
    }

    private static function runIntegrityCheck() {
        $rootDir = dirname(dirname(__DIR__));
        $checksumFile = $rootDir . '/storage/file_checksums.json';
        $currentChecksums = [];
        
        // Scan directory recursively
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $path = $file->getPathname();
                // Exclude node_modules, cache, logs, uploads, git, dist
                if (strpos($path, 'node_modules') !== false ||
                    strpos($path, 'uploads') !== false ||
                    strpos($path, 'storage/logs') !== false ||
                    strpos($path, '.git') !== false ||
                    strpos($path, 'dist') !== false) {
                    continue;
                }
                
                $relPath = str_replace($rootDir . '/', '', $path);
                $currentChecksums[$relPath] = md5_file($path);
            }
        }

        if (file_exists($checksumFile)) {
            $oldChecksums = json_decode(file_get_contents($checksumFile), true);
            if (is_array($oldChecksums)) {
                $modified = [];
                $added = [];
                $deleted = [];

                foreach ($currentChecksums as $path => $md5) {
                    if (!isset($oldChecksums[$path])) {
                        $added[] = $path;
                    } elseif ($oldChecksums[$path] !== $md5) {
                        $modified[] = $path;
                    }
                }

                foreach ($oldChecksums as $path => $md5) {
                    if (!isset($currentChecksums[$path])) {
                        $deleted[] = $path;
                    }
                }

                if (!empty($modified) || !empty($added) || !empty($deleted)) {
                    $details = "رصد تغييرات في ملفات النظام: " . 
                        (!empty($modified) ? " تعديل (" . implode(', ', array_slice($modified, 0, 5)) . (count($modified) > 5 ? '...' : '') . ")" : "") .
                        (!empty($added) ? " إضافة (" . implode(', ', array_slice($added, 0, 5)) . (count($added) > 5 ? '...' : '') . ")" : "") .
                        (!empty($deleted) ? " حذف (" . implode(', ', array_slice($deleted, 0, 5)) . (count($deleted) > 5 ? '...' : '') . ")" : "");
                    self::log($details);
                    SecurityCore::logSecurityAlert("تعديل ملفات النظام", $details);
                }
            }
        }

        // Save new checksums
        @file_put_contents($checksumFile, json_encode($currentChecksums, JSON_PRETTY_PRINT));
    }

    private static function runMalwareScan() {
        $rootDir = dirname(dirname(__DIR__));
        $scanDirs = [
            $rootDir . '/uploads',
            $rootDir . '/storage'
        ];

        $maliciousSignatures = [
            '/eval\s*\(\s*base64_decode/i',
            '/shell_exec\s*\(/i',
            '/system\s*\(/i',
            '/passthru\s*\(/i',
            '/exec\s*\(/i',
            '/popen\s*\(/i',
            '/proc_open\s*\(/i',
            '/assert\s*\(/i',
            '/`[^`]+`/i', // Backticks
            '/<\?php/i' // PHP opening tag in non-php file or inside upload folder
        ];

        foreach ($scanDirs as $dir) {
            if (!is_dir($dir)) continue;
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $path = $file->getPathname();
                    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    $filename = basename($path);

                    // 1. Check double extensions
                    if (preg_match('/\.(php|phtml|php5|phar|asp|aspx|sh|exe|bat|cmd)\.[a-zA-Z0-9]+$/i', $filename)) {
                        $details = "تم العثور على ملف بامتداد ثنائي مشبوه: $path";
                        self::log($details);
                        SecurityCore::logSecurityAlert("ملف بامتداد مزدوج مشبوه", $details);
                        @unlink($path); // Auto-heal: delete malware
                        continue;
                    }

                    // 2. Scan PHP files in uploads
                    if (strpos($path, 'uploads') !== false && in_array($ext, ['php', 'phtml', 'php5', 'phar', 'htaccess'])) {
                        $details = "تم العثور على ملف برمجيات خبيثة تنفيذي في مجلد الرفع: $path";
                        self::log($details);
                        SecurityCore::logSecurityAlert("ملف تنفيذي غير مصرح به", $details);
                        @unlink($path); // Auto-heal
                        continue;
                    }

                    // 3. Scan contents for shell execution patterns
                    if ($file->getSize() < 5 * 1024 * 1024) { // Only scan files < 5MB
                        $content = @file_get_contents($path);
                        if ($content !== false) {
                            foreach ($maliciousSignatures as $sig) {
                                if (preg_match($sig, $content)) {
                                    $details = "تم العثور على شفرة برمجية مشبوهة في الملف: $path";
                                    self::log($details);
                                    SecurityCore::logSecurityAlert("شفرة برمجية مشبوهة", $details);
                                    @unlink($path); // Auto-heal
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    private static function runPermissionAudit() {
        $rootDir = dirname(dirname(__DIR__));
        $criticalPaths = [
            $rootDir . '/config/config.php' => 0644,
            $rootDir . '/modules/security/SecurityCore.php' => 0644,
            $rootDir . '/uploads' => 0755,
            $rootDir . '/storage' => 0755
        ];

        foreach ($criticalPaths as $path => $perms) {
            if (file_exists($path)) {
                $currentPerms = fileperms($path) & 0777;
                if ($currentPerms !== $perms) {
                    self::log("Fixing permissions for $path from " . sprintf('%o', $currentPerms) . " to " . sprintf('%o', $perms));
                    @chmod($path, $perms);
                }
            }
        }
    }

    private static function runCleanup() {
        $rootDir = dirname(dirname(__DIR__));
        // Rotate PHP warnings / fatals if they exceed 5MB
        $logFiles = [
            $rootDir . '/storage/logs/php_warnings.log',
            $rootDir . '/storage/logs/fatal_exceptions.log',
            $rootDir . '/storage/logs/waf_security.log'
        ];

        foreach ($logFiles as $file) {
            if (file_exists($file) && filesize($file) > 5 * 1024 * 1024) {
                self::log("Rotating log file: $file");
                @rename($file, $file . '.' . date('YmdHis') . '.bak');
                @file_put_contents($file, '');
            }
        }

        // Clean cache files older than 7 days
        $cacheDir = $rootDir . '/storage/cache';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && (time() - filemtime($file) > 7 * 24 * 3600)) {
                    @unlink($file);
                }
            }
        }
    }
}

if (php_sapi_name() === 'cli' || defined('SEC_CLI_ALLOW')) {
    SecurityCli::run($task);
}

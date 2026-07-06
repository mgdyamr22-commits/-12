<?php
/**
 * Almakhzoun Pro - Core Enterprise Security & Self-Healing Module (SecurityCore.php)
 * ----------------------------------------------------------------------------------
 * This module secures the entire ERP and customer showroom from crashes, damages, 
 * malicious code injection, SQLi, XSS, Session Hijacking, CSRF, and malicious file uploads.
 * It also automatically heals missing tables, default users, corrupt config files, and rotates logs.
 * 
 * PSR-12 Compliant, fully standalone, optimized for shared hosting/cPanel.
 * SPDX-License-Identifier: Apache-2.0
 */

class SecurityCore
{
    private static $pdo = null;
    private static $configPath = '';
    private static $lockPath = '';

    /**
     * Initializes the Security Core.
     * Must be called at the very top of application execution.
     */
    public static function init($pdoInstance = null)
    {
        self::$configPath = dirname(dirname(__DIR__)) . '/config/config.php';
        self::$lockPath = dirname(dirname(__DIR__)) . '/storage/install.lock';

        // 1. Run Web Application Firewall (WAF) / Intrusion Detection System (IDS)
        self::runWaf();

        // 2. Harden Sessions against Hijacking
        self::hardenSession();

        // 3. Set secure HTTP response headers
        self::setSecurityHeaders();

        // 4. Set global PHP exception and error handler for absolute crash resilience
        self::registerErrorHandler();

        // 5. Connect or use provided PDO
        if ($pdoInstance instanceof PDO) {
            self::$pdo = $pdoInstance;
        } else {
            self::$pdo = self::connectDatabase();
        }

        if (self::$pdo) {
            // 6. Perform Self-Healing Check (Database & Files integrity) - Caching Layer
            $cacheDir = dirname(self::$lockPath) . '/cache';
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0755, true);
            }
            $cacheFile = $cacheDir . '/self_healing_last_run.json';
            $runHealing = true;
            if (file_exists($cacheFile)) {
                $cacheData = json_decode(file_get_contents($cacheFile), true);
                if (isset($cacheData['last_run']) && (time() - $cacheData['last_run'] < 3600)) {
                    $runHealing = false;
                }
            }

            if ($runHealing) {
                $cliScript = __DIR__ . '/SecurityCli.php';
                if (php_sapi_name() !== 'cli' && function_exists('exec') && file_exists($cliScript)) {
                    // Start in the background asynchronously via CLI
                    $cmd = "php " . escapeshellarg($cliScript) . " --task=self-healing > /dev/null 2>&1 &";
                    @exec($cmd);
                    @file_put_contents($cacheFile, json_encode(['last_run' => time()]));
                } else {
                    // Fast fallback inline execution
                    self::runSelfHealing();
                    @file_put_contents($cacheFile, json_encode(['last_run' => time()]));
                }
            }
        }
    }

    /**
     * Connects to MySQL securely with optimal PDO options.
     */
    private static function connectDatabase()
    {
        if (!file_exists(self::$configPath)) {
            return null;
        }

        try {
            $config = require self::$configPath;
            if (!isset($config['db'])) {
                return null;
            }

            $dbConfig = $config['db'];
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset=utf8mb4";
            
            return new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
        } catch (Exception $e) {
            // Silently log or report, prevent raw dump
            return null;
        }
    }

    /**
     * Web Application Firewall (WAF) to detect and block common web application attacks.
     * Prevents XSS, SQLi, LFI/RFI, Command Injection, and Malicious Uploads.
     */
    private static function runWaf()
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        // 1. Global Scan for Malicious File Uploads (Zero-Day Shield)
        if (!empty($_FILES)) {
            $blockedExtensions = ['php', 'phtml', 'php5', 'phps', 'phar', 'exe', 'sh', 'pl', 'py', 'asp', 'aspx', 'jsp', 'cgi', 'cmd', 'bat', 'htaccess', 'svg'];
            foreach ($_FILES as $fileKey => $fileData) {
                if (is_array($fileData['name'])) {
                    // Multi-file upload array
                    foreach ($fileData['name'] as $idx => $name) {
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (in_array($ext, $blockedExtensions)) {
                            self::logAndBlockAttack("رفع ملف تنفيذي مشبوه", "تم حظر محاولة رفع ملف ذو امتداد خطير: $name في الحقل $fileKey");
                        }
                        // Check mime types
                        if (isset($fileData['type'][$idx])) {
                            $mime = strtolower($fileData['type'][$idx]);
                            if (strpos($mime, 'php') !== false || strpos($mime, 'application/x-') !== false) {
                                self::logAndBlockAttack("محتوى ملف مشبوه (MIME)", "تم حظر ملف بنوع MIME مشبوه: $mime");
                            }
                        }
                    }
                } else {
                    // Single file upload
                    $ext = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, $blockedExtensions)) {
                        self::logAndBlockAttack("رفع ملف تنفيذي مشبوه", "تم حظر محاولة رفع ملف ذو امتداد خطير: {$fileData['name']} في الحقل $fileKey");
                    }
                    if (isset($fileData['type'])) {
                        $mime = strtolower($fileData['type']);
                        if (strpos($mime, 'php') !== false || strpos($mime, 'application/x-') !== false) {
                            self::logAndBlockAttack("محتوى ملف مشبوه (MIME)", "تم حظر ملف بنوع MIME مشبوه: $mime");
                        }
                    }
                }
            }
        }

        // 2. Define Multi-Layered Attack Signatures
        $signatures = [
            'SQL Injection' => [
                '/union\s+(all\s+)?select/i',
                '/select\s+.*\s+from\s+information_schema/i',
                '/select\s+.*\s+from\s+mysql\./i',
                '/sysdatabases|sysobjects/i',
                '/concat\(.*char\(/i',
                '/(\x27|\x22)\s*(or|and)\s+.*=.*(\x27|\x22)?/i',
                '/benchmark\((.*)\,(.*)\)/i',
                '/sleep\((.*)\)/i',
                '/waitfor\s+delay\s+(\x27|\x22).+(\x27|\x22)/i',
                '/extractvalue\s*\(|updatexml\s*\(/i',
                '/into\s+outfile|into\s+dumpfile/i',
                '/or\s+\d+=\d+/i',
                '/or\s+(\x27|\x22).+=(\x27|\x22).+/i',
                '/exec\s+xp_cmdshell/i',
                '/\/\*!\d{5}\w+\*\//i' // SQL comments bypass tricks like /*!50000SELECT*/
            ],
            'Cross-Site Scripting (XSS)' => [
                '/<script\b[^>]*>/i',
                '/javascript\s*:/i',
                '/onerror\s*=/i',
                '/onload\s*=/i',
                '/onmouseover\s*=/i',
                '/onfocus\s*=/i',
                '/document\.cookie/i',
                '/window\.location/i',
                '/alert\(/i',
                '/eval\((.*)\)/i',
                '/src\s*=\s*(\x27|\x22)?\s*javascript\s*:/i',
                '/href\s*=\s*(\x27|\x22)?\s*javascript\s*:/i',
                '/<iframe|<object|<embed|<applet/i',
                '/String\.fromCharCode/i',
                '/prompt\s*\(|confirm\s*\(/i'
            ],
            'Path Traversal / LFI' => [
                '/\.\.\/\.\.\//',
                '/\.\.\\\\\.\.\\\\/',
                '/\.\.\//',
                '/\.\.\\\\/',
                '/etc\/passwd/i',
                '/boot\.ini/i',
                '/win\.ini/i',
                '/php:\/\/filter/i',
                '/php:\/\/input/i',
                '/phar:\/\//i',
                '/data:\/\/text\/plain/i'
            ],
            'Command Injection' => [
                '/system\s*\(|shell_exec\s*\(|passthru\s*\(|exec\s*\(|popen\s*\(|proc_open\s*\(/i',
                '/\;\s*(cat|id|whoami|uname|ping|curl|wget)\s+/i',
                '/\&\&\s*(cat|id|whoami|uname|ping|curl|wget)\s+/i',
                '/\|\s*(cat|id|whoami|uname|ping|curl|wget)\s+/i',
                '/\$\((cat|id|whoami|uname|ping|curl|wget)/i'
            ]
        ];

        // 3. Scan HTTP request parameters with Multi-Layered normalization
        $inputsToScan = [
            'GET' => $_GET,
            'POST' => $_POST,
            'COOKIE' => $_COOKIE,
            'URI' => ['uri' => $requestUri]
        ];

        // Detect if active session is an administrator to allow styling custom JS/CSS in parameters safely
        $isAdmin = false;
        if (session_status() !== PHP_SESSION_NONE || isset($_COOKIE[session_name()])) {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
        }

        foreach ($inputsToScan as $source => $inputData) {
            foreach ($inputData as $key => $value) {
                // Safeguard administrators' custom options
                if ($isAdmin && is_string($key) && (str_starts_with($key, 'showroom_custom_') || $key === 'notes' || $key === 'description')) {
                    continue;
                }

                if (is_array($value)) {
                    $value = json_encode($value);
                }

                if (!is_string($value) || empty($value)) {
                    continue;
                }

                // Normalization Layers
                $normResult = self::normalizeInputForWaf($value);
                $normalized = $normResult['normalized'];
                $noComments = $normResult['no_comments'];

                foreach ($signatures as $attackType => $patterns) {
                    foreach ($patterns as $pattern) {
                        // Check original, normalized, and stripped comments versions
                        if (preg_match($pattern, $value) || preg_match($pattern, $normalized) || preg_match($pattern, $noComments)) {
                            self::logAndBlockAttack($attackType, "تم رصد محاولة هجوم من نوع $attackType في مصدر $source ($key) بالقيمة المعطاة بعد الفحص متعدد الطبقات.");
                        }
                    }
                }
            }
        }
    }

    /**
     * Normalizes inputs for multiple decoding and bypass formats.
     */
    private static function normalizeInputForWaf($value) {
        if (!is_string($value)) {
            return ['original' => $value, 'normalized' => $value, 'no_comments' => $value];
        }

        $normalized = $value;

        // 1. Recursive URL Decode (up to 3 times for nested encodings)
        for ($i = 0; $i < 3; $i++) {
            $decoded = rawurldecode($normalized);
            if ($decoded === $normalized) {
                break;
            }
            $normalized = $decoded;
        }

        // 2. Decode Unicode escape sequences (e.g., %u0027, \u0027, \u{0027})
        $normalized = preg_replace_callback('/%u([0-9a-fA-F]{4})/', function ($matches) {
            return mb_convert_encoding(pack('H*', $matches[1]), 'UTF-8', 'UCS-2BE');
        }, $normalized);

        $normalized = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($matches) {
            return mb_convert_encoding(pack('H*', $matches[1]), 'UTF-8', 'UCS-2BE');
        }, $normalized);

        // 3. Decode Hex encodings (e.g., \x27, 0x27)
        $normalized = preg_replace_callback('/\\\\x([0-9a-fA-F]{2})/', function ($matches) {
            return chr(hexdec($matches[1]));
        }, $normalized);

        // 4. Handle HTML Entities (e.g., &lt;, &#x27;)
        $normalized = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 5. Detect and decode base64 payloads
        if (preg_match('/^[a-zA-Z0-9\/+]{12,}=*$/', trim($value))) {
            $decodedBase64 = @base64_decode(trim($value), true);
            if ($decodedBase64 !== false && preg_match('//u', $decodedBase64)) {
                $normalized .= ' [B64_DECODED: ' . $decodedBase64 . ']';
            }
        }

        // 6. Strip / Normalize SQL Comments to detect obfuscated words
        $noComments = preg_replace('/\/\*.*?\*\//s', '', $normalized);
        $noComments = preg_replace('/--.*?(\n|$)/s', ' ', $noComments);
        
        return [
            'original' => $value,
            'normalized' => $normalized,
            'no_comments' => $noComments
        ];
    }

    /**
     * Intercepts, logs, and blocks the attack displaying a high-tech secure shield screen.
     */
    private static function logAndBlockAttack($attackType, $details)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $refId = strtoupper(substr(md5(time() . rand(1000, 9999)), 0, 12));

        // Write to system_logs if possible
        if (self::$pdo) {
            try {
                $stmt = self::$pdo->prepare("INSERT INTO `system_logs` (`user_id`, `user_name`, `action`, `details`, `risk_level`, `ip`) VALUES ('WAF', 'جدار الحماية الموحد', ?, ?, 'critical', ?)");
                $stmt->execute([
                    "رصد هجوم " . $attackType,
                    $details . " [مرجع الأمان: " . $refId . "]",
                    $ip
                ]);
            } catch (Exception $e) {
                // Ignore DB logging error to continue shielding
            }
        }

        // Log to custom backup file if DB is down or unreachable
        try {
            $logDir = dirname(self::$lockPath) . '/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $logFile = $logDir . '/waf_security.log';
            $logEntry = sprintf("[%s] [ID: %s] [IP: %s] [ATTACK: %s] %s\n", date('Y-m-d H:i:s'), $refId, $ip, $attackType, $details);
            @file_put_contents($logFile, $logEntry, FILE_APPEND);
        } catch (Exception $ex) {
            // Fail silently
        }

        // Clean buffers and output Arabic WAF Blocked screen
        if (ob_get_level()) {
            ob_clean();
        }
        header("HTTP/1.1 403 Forbidden");
        die('<!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>تم حظر الطلب - جدار حماية المخزون برو</title>
            <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
            <style>
                body {
                    font-family: "Cairo", sans-serif;
                    background-color: #020617;
                    color: #f1f5f9;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    margin: 0;
                    padding: 24px;
                    box-sizing: border-box;
                    background-image: radial-gradient(circle at 50% 50%, #1e1b4b 0%, #020617 70%);
                }
                .shield-container {
                    background: rgba(15, 23, 42, 0.85);
                    border: 2px solid #ef4444;
                    box-shadow: 0 0 50px rgba(239, 68, 68, 0.25);
                    border-radius: 24px;
                    padding: 40px;
                    max-width: 650px;
                    text-align: center;
                    backdrop-filter: blur(12px);
                }
                .shield-icon {
                    width: 90px;
                    height: 90px;
                    background: rgba(239, 68, 68, 0.1);
                    border: 2px solid #ef4444;
                    border-radius: 50px;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    margin: 0 auto 24px auto;
                    color: #ef4444;
                    font-size: 40px;
                    animation: pulse 2s infinite;
                }
                h1 {
                    font-size: 26px;
                    font-weight: 900;
                    margin-top: 0;
                    color: #ef4444;
                }
                p {
                    font-size: 14px;
                    color: #94a3b8;
                    line-height: 1.8;
                }
                .meta-table {
                    background: #0b0f19;
                    border: 1px solid #1e293b;
                    border-radius: 12px;
                    padding: 16px;
                    margin: 24px 0;
                    text-align: right;
                }
                .meta-row {
                    display: flex;
                    justify-content: space-between;
                    border-bottom: 1px solid #1e293b;
                    padding: 8px 0;
                    font-size: 12px;
                }
                .meta-row:last-child {
                    border-bottom: none;
                }
                .meta-key {
                    color: #64748b;
                    font-weight: 600;
                }
                .meta-val {
                    color: #cbd5e1;
                    font-family: monospace;
                    font-weight: bold;
                }
                .footer-notice {
                    font-size: 11px;
                    color: #475569;
                    margin-top: 24px;
                }
                .btn {
                    display: inline-block;
                    background-color: #ef4444;
                    color: white;
                    text-decoration: none;
                    font-weight: bold;
                    font-size: 13px;
                    padding: 12px 28px;
                    border-radius: 10px;
                    margin-top: 16px;
                    transition: all 0.2s;
                }
                .btn:hover {
                    background-color: #dc2626;
                    box-shadow: 0 0 20px rgba(239, 68, 68, 0.4);
                }
                @keyframes pulse {
                    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
                    70% { transform: scale(1.05); box-shadow: 0 0 0 15px rgba(239, 68, 68, 0); }
                    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
                }
            </style>
        </head>
        <body>
            <div class="shield-container">
                <div class="shield-icon">🛡️</div>
                <h1>تم حظر النشاط بواسطة جدار حماية ALMAKHZOUN PRO</h1>
                <p>لقد رصد جدار الحماية الذكي المطور لحماية خادم "المخزون برو" نشاطاً غير مصرح به أو يحتوي على شفرات ضارة، وتم منع معالجة طلبك تلقائياً لحفظ الخادم والمخزون من التلف أو العبث.</p>
                
                <div class="meta-table">
                    <div class="meta-row">
                        <span class="meta-key">عنوان الآي بي الخاص بك (IP):</span>
                        <span class="meta-val">' . htmlspecialchars($ip) . '</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-key">نوع الهجوم المرصود:</span>
                        <span class="meta-val" style="color: #ef4444;">' . htmlspecialchars($attackType) . '</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-key">الرمز المرجعي للأمان:</span>
                        <span class="meta-val" style="color: #60a5fa;">' . htmlspecialchars($refId) . '</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-key">توقيت الحدث (خادم):</span>
                        <span class="meta-val">' . date('Y-m-d H:i:s') . '</span>
                    </div>
                </div>

                <p style="font-size: 12px; color: #64748b;">إذا كنت ترى أن هذا الإجراء تم بالخطأ، يرجى تزويد الدعم الفني بالرمز المرجعي للأمان الموضح أعلاه لمراجعة سجل العمليات.</p>
                <a href="index.php" class="btn">الرجوع للرئيسية الآمنة</a>
                
                <div class="footer-notice">
                    WAF Engine v2.5.0 • Powered by Almakhzoun Shield Technology
                </div>
            </div>
        </body>
        </html>');
    }

    /**
     * Session Hardening and Hijacking defense mechanism.
     */
    private static function hardenSession()
    {
        // 1. Ensure sessions run with secure and flexible cookie configurations for iframe navigation compatibility
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_samesite', 'Lax');
            @session_start();
        }

        // 2. Prevent Session Hijacking via User-Agent and IP subnet fingerprinting
        $fingerprint = md5(
            ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown_ua') . 
            self::getIpSubnet()
        );

        if (!isset($_SESSION['_fingerprint'])) {
            $_SESSION['_fingerprint'] = $fingerprint;
            $_SESSION['_created'] = time();
        } else {
            if ($_SESSION['_fingerprint'] !== $fingerprint) {
                // Suspicious session drift! Clear session and force re-login
                $_SESSION = [];
                @session_destroy();
                @session_start();
                $_SESSION['security_alert'] = "تم إنهاء الجلسة تلقائياً لإجراء وقائي: تغيير متصفح العمل أو الشبكة.";
            }
        }

        // 3. Prevent Session Fixation by rotating ID occasionally (every 15 minutes of activity)
        if (isset($_SESSION['_created']) && (time() - $_SESSION['_created'] > 900)) {
            @session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }
    }

    /**
     * Gets first 3 octets of IP to allow minor mobile network switching without logging out, 
     * but blocks complete geographical session hijacking.
     */
    private static function getIpSubnet()
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2];
        }
        return $ip;
    }

    /**
     * Standardizes critical secure Response Headers.
     */
    private static function setSecurityHeaders()
    {
        if (!headers_sent()) {
            header("X-Frame-Options: SAMEORIGIN");
            header("X-Content-Type-Options: nosniff");
            header("X-XSS-Protection: 1; mode=block");
            header("Referrer-Policy: strict-origin-when-cross-origin");
        }
    }

    /**
     * Global Exception and Error register handler to capture and prevent raw stack crashes.
     */
    private static function registerErrorHandler()
    {
        set_error_handler(function($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return;
            }
            // Proactive warning/notice shield: minor issues (like deprecated null arguments, undefined variables)
            // should not crash the page. We log them and let execution continue.
            $nonFatal = [E_DEPRECATED, E_USER_DEPRECATED, E_NOTICE, E_USER_NOTICE, E_WARNING, E_USER_WARNING];
            if (in_array($severity, $nonFatal)) {
                try {
                    $logDir = dirname(self::$lockPath) . '/logs';
                    if (!is_dir($logDir)) {
                        @mkdir($logDir, 0755, true);
                    }
                    $logFile = $logDir . '/php_warnings.log';
                    $logEntry = sprintf("[%s] [NON-FATAL] %s in %s on line %d\n", date('Y-m-d H:i:s'), $message, $file, $line);
                    @file_put_contents($logFile, $logEntry, FILE_APPEND);
                } catch (Exception $ex) {}
                return true; // Don't throw Exception, let PHP handle gracefully
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function($exception) {
            // Real-time SQL Auto-Healer:
            // If we encounter a "Column not found" or "Table not found" PDOException, we run self-healing immediately and auto-reload the page!
            if ($exception instanceof PDOException || strpos(get_class($exception), 'PDOException') !== false) {
                $msg = $exception->getMessage();
                if (stripos($msg, 'Unknown column') !== false || stripos($msg, 'Column not found') !== false || stripos($msg, 'Table') !== false) {
                    try {
                        self::runSelfHealing();
                        if (!headers_sent()) {
                            header("Location: " . $_SERVER['REQUEST_URI']);
                            exit;
                        } else {
                            echo '<script>window.location.reload();</script>';
                            exit;
                        }
                    } catch (Exception $healingError) {
                        // Healing failed, fallback to disaster card
                    }
                }
            }

            // Log to secure log file
            try {
                $logDir = dirname(self::$lockPath) . '/logs';
                if (!is_dir($logDir)) {
                    @mkdir($logDir, 0755, true);
                }
                $logFile = $logDir . '/fatal_exceptions.log';
                $logEntry = sprintf("[%s] [FATAL EXCEPTION] %s in %s on line %d\nStack: %s\n", 
                    date('Y-m-d H:i:s'), 
                    $exception->getMessage(), 
                    $exception->getFile(), 
                    $exception->getLine(), 
                    $exception->getTraceAsString()
                );
                @file_put_contents($logFile, $logEntry, FILE_APPEND);
            } catch (Exception $ex) {
                // Fail silently
            }

            // Output clean anti-crash disaster recovery panel
            if (ob_get_level()) {
                ob_clean();
            }
            header("HTTP/1.1 500 Internal Server Error");
            die('<!DOCTYPE html>
            <html lang="ar" dir="rtl">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>مركز استعادة النظام والصيانة الذاتية - Almakhzoun Pro</title>
                <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
                <style>
                    body {
                        font-family: "Cairo", sans-serif;
                        background-color: #0b1329;
                        color: #f1f5f9;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 100vh;
                        margin: 0;
                        padding: 24px;
                        box-sizing: border-box;
                    }
                    .disaster-card {
                        background: #111d37;
                        border: 1px solid #1e293b;
                        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
                        border-radius: 20px;
                        padding: 40px;
                        max-width: 600px;
                        text-align: center;
                    }
                    .error-icon {
                        font-size: 50px;
                        color: #fbbf24;
                        margin-bottom: 20px;
                        animation: pulse 2s infinite;
                    }
                    h1 {
                        font-size: 22px;
                        color: #fbbf24;
                        font-weight: 800;
                        margin-top: 0;
                    }
                    p {
                        font-size: 13px;
                        color: #94a3b8;
                        line-height: 1.8;
                    }
                    .info-box {
                        background: #070d1e;
                        border: 1px dashed #334155;
                        border-radius: 10px;
                        padding: 16px;
                        font-family: monospace;
                        font-size: 12px;
                        color: #fbbf24;
                        text-align: left;
                        direction: ltr;
                        overflow-x: auto;
                        margin: 20px 0;
                    }
                    .btn {
                        display: inline-block;
                        background-color: #3b82f6;
                        color: white;
                        text-decoration: none;
                        font-weight: bold;
                        font-size: 13px;
                        padding: 10px 24px;
                        border-radius: 8px;
                        margin-top: 12px;
                    }
                    .btn:hover {
                        background-color: #2563eb;
                    }
                </style>
            </head>
            <body>
                <div class="disaster-card">
                    <div class="error-icon">⚙️</div>
                    <h1>تم رصد مشكلة بنجاح وتفعيل بروتوكول الصيانة الذاتية والتعافي!</h1>
                    <p>المخزون برو يعمل بنظام الصيانة وحماية البيانات المدمج الذكي ضد الانهيار الفجائي. لقد اعترضنا بنجاح خطأ برمجياً وحفظنا النظام من الانهيار الكامل أو فقدان الجلسة.</p>
                    
                    <div class="info-box">
                        [RECOVERY ENG] Critical handler caught: ' . htmlspecialchars($exception->getMessage()) . '
                    </div>
                    
                    <p style="font-size:12px; color:#64748b;">قمنا بتسجيل تفاصيل الاستعادة والخطأ بأمان في السيرفر لمطوريك. يمكنك النقر أدناه لإعادة تشغيل النظام بنقرة واحدة آمنة.</p>
                    <a href="index.php" class="btn">إعادة إنعاش الصفحة بأمان 🔄</a>
                </div>
            </body>
            </html>');
        });
    }

    /**
     * Database Self-Healing system:
     * - Auto-recreate missing central tables on the fly.
     * - Ensure there is always a default super admin user (admin/admin123).
     * - Repair missing uploads, storage and config directories and block script execution.
     * - Clean up bloated log entries and records (Auto Log Rotation).
     */
    private static function runSelfHealing()
    {
        if (!self::$pdo) {
            return;
        }

        try {
            // Absolute Authoritative ERP Schemas corresponding to database.sql and index.php
            $tableSchemas = [
                'branches' => [
                    'id' => "VARCHAR(50) NOT NULL PRIMARY KEY",
                    'name' => "VARCHAR(100) NOT NULL UNIQUE",
                    'location' => "VARCHAR(150) NOT NULL",
                    'phone' => "VARCHAR(30) DEFAULT NULL",
                    'manager' => "VARCHAR(100) DEFAULT NULL",
                    'code' => "VARCHAR(50) DEFAULT NULL",
                    'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"
                ],
                'users' => [
                    'id' => "VARCHAR(50) NOT NULL PRIMARY KEY",
                    'username' => "VARCHAR(50) NOT NULL UNIQUE",
                    'password' => "VARCHAR(255) NOT NULL",
                    'name' => "VARCHAR(100) NOT NULL",
                    'email' => "VARCHAR(100) DEFAULT NULL",
                    'phone' => "VARCHAR(30) DEFAULT NULL",
                    'role' => "VARCHAR(50) NOT NULL DEFAULT 'representative'",
                    'avatar' => "LONGTEXT DEFAULT NULL",
                    'branch_id' => "VARCHAR(50) DEFAULT NULL",
                    'theme' => "VARCHAR(20) DEFAULT 'light'",
                    'lang' => "VARCHAR(10) DEFAULT 'ar'",
                    'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"
                ],
                'cars' => [
                    'id' => "VARCHAR(50) NOT NULL PRIMARY KEY",
                    'make' => "VARCHAR(100) NOT NULL",
                    'model' => "VARCHAR(100) NOT NULL",
                    'trim' => "VARCHAR(100) DEFAULT NULL",
                    'year' => "INT NOT NULL",
                    'color' => "VARCHAR(50) NOT NULL",
                    'interior_color' => "VARCHAR(50) DEFAULT NULL",
                    'body_type' => "VARCHAR(50) DEFAULT 'سيدان'",
                    'doors' => "INT DEFAULT 4",
                    'seats' => "INT DEFAULT 5",
                    'cylinders' => "INT DEFAULT 4",
                    'engine_power' => "INT DEFAULT 180",
                    'drive' => "VARCHAR(100) DEFAULT 'دفع أمامي FWD'",
                    'origin_country' => "VARCHAR(100) DEFAULT NULL",
                    'assembly_country' => "VARCHAR(100) DEFAULT NULL",
                    'entry_date' => "DATE DEFAULT NULL",
                    'exit_date' => "DATE DEFAULT NULL",
                    'purchase_date' => "DATE DEFAULT NULL",
                    'warranty' => "VARCHAR(255) DEFAULT 'ضمان الوكيل المعتمد الممتد'",
                    'warranty_duration' => "INT DEFAULT 5",
                    'previous_owner' => "VARCHAR(100) DEFAULT NULL",
                    'vin' => "VARCHAR(100) NOT NULL UNIQUE",
                    'vin_matching' => "VARCHAR(50) DEFAULT 'matching'",
                    'plate_number' => "VARCHAR(50) DEFAULT NULL",
                    'plate_type' => "VARCHAR(100) DEFAULT 'خصوصي - ملاكي'",
                    'serial_number' => "VARCHAR(100) DEFAULT NULL",
                    'registration_number' => "VARCHAR(100) DEFAULT NULL",
                    'vehicle_condition' => "VARCHAR(100) DEFAULT 'جديد (أصفار)'",
                    'price' => "DECIMAL(12,2) NOT NULL",
                    'cost_price' => "DECIMAL(12,2) DEFAULT '0.00'",
                    'tax' => "DECIMAL(12,2) DEFAULT '0.00'",
                    'discount' => "DECIMAL(12,2) DEFAULT '0.00'",
                    'final_price' => "DECIMAL(12,2) DEFAULT '0.00'",
                    'currency' => "VARCHAR(20) DEFAULT 'ر.س'",
                    'mileage' => "INT NOT NULL",
                    'transmission' => "VARCHAR(50) NOT NULL",
                    'engine_type' => "VARCHAR(50) NOT NULL",
                    'status' => "VARCHAR(50) NOT NULL DEFAULT 'available'",
                    'branch_id' => "VARCHAR(50) DEFAULT NULL",
                    'supplier' => "VARCHAR(100) DEFAULT NULL",
                    'ownership_type' => "VARCHAR(100) DEFAULT 'مباشر'",
                    'leasing_status' => "VARCHAR(50) DEFAULT 'not_leased'",
                    'customs_number' => "VARCHAR(100) DEFAULT NULL",
                    'rep_in_charge' => "VARCHAR(100) DEFAULT NULL",
                    'main_image' => "LONGTEXT DEFAULT NULL",
                    'gulf_specs' => "TINYINT(1) DEFAULT 1",
                    'american_specs' => "TINYINT(1) DEFAULT 0",
                    'european_specs' => "TINYINT(1) DEFAULT 0",
                    'fuel_consumption' => "VARCHAR(50) DEFAULT '14.5 كم/لتر'",
                    'navigation_system' => "TINYINT(1) DEFAULT 0",
                    'rear_camera' => "TINYINT(1) DEFAULT 1",
                    'camera_360' => "TINYINT(1) DEFAULT 0",
                    'radar' => "TINYINT(1) DEFAULT 0",
                    'front_sensors' => "TINYINT(1) DEFAULT 0",
                    'rear_sensors' => "TINYINT(1) DEFAULT 1",
                    'cruise_control' => "TINYINT(1) DEFAULT 1",
                    'adaptive_cruise' => "TINYINT(1) DEFAULT 0",
                    'lane_assist' => "TINYINT(1) DEFAULT 0",
                    'blind_spot' => "TINYINT(1) DEFAULT 0",
                    'apple_carplay' => "TINYINT(1) DEFAULT 1",
                    'android_auto' => "TINYINT(1) DEFAULT 1",
                    'sunroof' => "TINYINT(1) DEFAULT 0",
                    'panorama' => "TINYINT(1) DEFAULT 0",
                    'leather_seats' => "TINYINT(1) DEFAULT 0",
                    'heated_seats' => "TINYINT(1) DEFAULT 0",
                    'cooled_seats' => "TINYINT(1) DEFAULT 0",
                    'seat_memory' => "TINYINT(1) DEFAULT 0",
                    'push_button_start' => "TINYINT(1) DEFAULT 1",
                    'remote_start' => "TINYINT(1) DEFAULT 0",
                    'led_lights' => "TINYINT(1) DEFAULT 1",
                    'xenon_lights' => "TINYINT(1) DEFAULT 0",
                    'number_of_keys' => "INT DEFAULT 2",
                    'spare_tire' => "TINYINT(1) DEFAULT 1",
                    'catalog' => "TINYINT(1) DEFAULT 1",
                    'notes' => "TEXT DEFAULT NULL",
                    'attachments' => "TEXT DEFAULT NULL",
                    'card_file_path' => "VARCHAR(255) DEFAULT NULL",
                    'card_file_name' => "VARCHAR(255) DEFAULT NULL",
                    'card_file_type' => "VARCHAR(50) DEFAULT NULL",
                    'card_file_date' => "VARCHAR(50) DEFAULT NULL",
                    'sold_by_user_id' => "VARCHAR(50) DEFAULT NULL",
                    'recipient_type' => "VARCHAR(255) DEFAULT NULL",
                    'sale_amount' => "DECIMAL(12,2) DEFAULT NULL",
                    'sale_customer_name' => "VARCHAR(255) DEFAULT NULL",
                    'sale_customer_id' => "VARCHAR(100) DEFAULT NULL",
                    'sale_customer_nationality' => "VARCHAR(100) DEFAULT NULL",
                    'sale_customer_phone' => "VARCHAR(100) DEFAULT NULL",
                    'exit_notes' => "TEXT DEFAULT NULL",
                    'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
                    'sale_date' => "TIMESTAMP NULL DEFAULT NULL",
                    'updated_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
                ],
                'reservations' => [
                    'id' => "VARCHAR(50) NOT NULL PRIMARY KEY",
                    'car_id' => "VARCHAR(50) NOT NULL",
                    'customer_name' => "VARCHAR(150) NOT NULL",
                    'customer_phone' => "VARCHAR(50) NOT NULL",
                    'customer_national_id' => "VARCHAR(50) DEFAULT NULL",
                    'start_date' => "DATE NOT NULL",
                    'duration' => "INT NOT NULL DEFAULT 3",
                    'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
                    'created_by_user_id' => "VARCHAR(50) DEFAULT NULL",
                    'status' => "VARCHAR(50) NOT NULL DEFAULT 'active'",
                    'notes' => "TEXT DEFAULT NULL",
                    'attachments' => "TEXT DEFAULT NULL",
                    'cancelled_by_user_id' => "VARCHAR(50) DEFAULT NULL",
                    'cancelled_at' => "TIMESTAMP NULL DEFAULT NULL",
                    'updated_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
                ],
                'reservation_attachments' => [
                    'id' => "INT NOT NULL AUTO_INCREMENT PRIMARY KEY",
                    'reservation_id' => "VARCHAR(50) NOT NULL",
                    'file_name' => "VARCHAR(255) NOT NULL",
                    'file_path' => "VARCHAR(255) NOT NULL",
                    'file_type' => "VARCHAR(100) DEFAULT NULL",
                    'uploaded_by' => "VARCHAR(100) DEFAULT NULL",
                    'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"
                ],
                'system_logs' => [
                    'id' => "INT NOT NULL AUTO_INCREMENT PRIMARY KEY",
                    'user_id' => "VARCHAR(50) NOT NULL",
                    'user_name' => "VARCHAR(100) NOT NULL",
                    'action' => "VARCHAR(100) NOT NULL",
                    'details' => "TEXT NOT NULL",
                    'risk_level' => "VARCHAR(50) DEFAULT 'low'",
                    'ip' => "VARCHAR(45) DEFAULT NULL",
                    'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"
                ],
                'settings' => [
                    'id' => "INT NOT NULL AUTO_INCREMENT PRIMARY KEY",
                    'company_name' => "VARCHAR(150) NOT NULL",
                    'tax_number' => "VARCHAR(50) DEFAULT NULL",
                    'logo' => "LONGTEXT DEFAULT NULL",
                    'address' => "VARCHAR(255) DEFAULT NULL",
                    'phone' => "VARCHAR(50) DEFAULT NULL",
                    'currency' => "VARCHAR(20) DEFAULT 'ر.س'",
                    'email' => "VARCHAR(150) DEFAULT NULL",
                    'company_description' => "TEXT DEFAULT NULL",
                    'vision' => "TEXT DEFAULT NULL",
                    'mission' => "TEXT DEFAULT NULL",
                    'goals' => "TEXT DEFAULT NULL",
                    'website' => "VARCHAR(150) DEFAULT NULL",
                    'social_twitter' => "VARCHAR(255) DEFAULT NULL",
                    'social_facebook' => "VARCHAR(255) DEFAULT NULL",
                    'social_instagram' => "VARCHAR(255) DEFAULT NULL",
                    'social_linkedin' => "VARCHAR(255) DEFAULT NULL",
                    'cr_number' => "VARCHAR(100) DEFAULT NULL",
                    'contact_phone' => "VARCHAR(100) DEFAULT NULL",
                    'whatsapp_phone' => "VARCHAR(100) DEFAULT NULL",
                    'showroom_header_title' => "VARCHAR(255) DEFAULT NULL",
                    'showroom_header_subtitle' => "TEXT DEFAULT NULL",
                    'showroom_footer_text' => "TEXT DEFAULT NULL",
                    'showroom_theme' => "VARCHAR(50) DEFAULT 'indigo'",
                    'showroom_show_price' => "TINYINT(1) DEFAULT 1",
                    'showroom_show_filters' => "TINYINT(1) DEFAULT 1",
                    'showroom_facebook' => "VARCHAR(255) DEFAULT NULL",
                    'showroom_twitter' => "VARCHAR(255) DEFAULT NULL",
                    'showroom_instagram' => "VARCHAR(255) DEFAULT NULL",
                    'showroom_linkedin' => "VARCHAR(255) DEFAULT NULL",
                    'showroom_snapchat' => "VARCHAR(255) DEFAULT NULL",
                    'showroom_youtube' => "VARCHAR(255) DEFAULT NULL",
                    'showroom_custom_socials' => "TEXT DEFAULT NULL",
                    'showroom_banner_image' => "VARCHAR(255) DEFAULT NULL",
                    'showroom_banner_overlay_opacity' => "INT DEFAULT 50",
                    'showroom_banner_opacity' => "INT DEFAULT 25",
                    'showroom_banner_height' => "VARCHAR(50) DEFAULT 'medium'",
                    'showroom_banner_bg_size' => "VARCHAR(50) DEFAULT 'cover'",
                    'showroom_banner_title_color' => "VARCHAR(50) DEFAULT '#ffffff'",
                    'showroom_banner_subtitle_color' => "VARCHAR(50) DEFAULT '#cbd5e1'",
                    'showroom_banner_text_bg' => "TINYINT(1) DEFAULT 0",
                    'showroom_custom_pages' => "TEXT DEFAULT NULL",
                    'showroom_menu_links' => "TEXT DEFAULT NULL",
                    'showroom_custom_css' => "TEXT DEFAULT NULL",
                    'showroom_custom_js' => "TEXT DEFAULT NULL",
                    'updated_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
                ],
                'customer_orders' => [
                    'id' => "INT NOT NULL AUTO_INCREMENT PRIMARY KEY",
                    'car_id' => "VARCHAR(50) NOT NULL",
                    'customer_name' => "VARCHAR(100) NOT NULL",
                    'customer_phone' => "VARCHAR(50) NOT NULL",
                    'notes' => "TEXT DEFAULT NULL",
                    'status' => "VARCHAR(50) DEFAULT 'new'",
                    'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"
                ],
                'notifications' => [
                    'id' => "INT NOT NULL AUTO_INCREMENT PRIMARY KEY",
                    'operation_type' => "VARCHAR(50) NOT NULL",
                    'title' => "VARCHAR(150) NOT NULL",
                    'description' => "TEXT",
                    'user_id' => "VARCHAR(50) DEFAULT NULL",
                    'user_name' => "VARCHAR(100) DEFAULT NULL",
                    'branch_name' => "VARCHAR(100) DEFAULT NULL",
                    'car_id' => "VARCHAR(50) DEFAULT NULL",
                    'is_read' => "TINYINT(1) DEFAULT '0'",
                    'created_at' => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"
                ]
            ];

            // 1. Core Dynamic Table and Column Check
            foreach ($tableSchemas as $tableName => $columns) {
                try {
                    $testQuery = self::$pdo->query("SELECT 1 FROM `{$tableName}` LIMIT 1");
                } catch (Exception $e) {
                    // Table is missing! Self-heal and auto-create it dynamically
                    $colDefs = [];
                    foreach ($columns as $colName => $colDef) {
                        $colDefs[] = "`{$colName}` {$colDef}";
                    }
                    $createSql = "CREATE TABLE `{$tableName}` (" . implode(", ", $colDefs) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    self::$pdo->exec($createSql);
                }

                // Retrieve existing columns
                $existingColumns = [];
                try {
                    $columnsQuery = self::$pdo->query("SHOW COLUMNS FROM `{$tableName}`");
                    while ($col = $columnsQuery->fetch(PDO::FETCH_ASSOC)) {
                        $existingColumns[strtolower($col['Field'])] = true;
                    }
                } catch (Exception $e) {
                    continue; // Skip if metadata query fails
                }

                // Add missing columns dynamically (Schema Evolution)
                foreach ($columns as $colName => $colDef) {
                    if (!isset($existingColumns[strtolower($colName)])) {
                        try {
                            if (stripos($colDef, 'PRIMARY KEY') === false) {
                                self::$pdo->exec("ALTER TABLE `{$tableName}` ADD COLUMN `{$colName}` {$colDef}");
                            }
                        } catch (Exception $e) {
                            // Suppress errors to guarantee execution
                        }
                    }
                }
            }

            // Ensure the role column in users table is modified from enum to varchar(50) to prevent truncation errors
            try {
                self::$pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'representative'");
            } catch (Exception $ex) {
                // Ignore if it fails or if table does not exist yet
            }

            // 2. Self-Healing Seed Integrity (Users, Branches, Settings)
            // Ensure we have at least one branch in order to prevent foreign key or reference crashes
            $branchCount = self::$pdo->query("SELECT COUNT(*) FROM `branches`")->fetchColumn();
            if ($branchCount == 0) {
                $stmtBranch = self::$pdo->prepare("INSERT INTO `branches` (`id`, `name`, `location`, `phone`, `manager`, `code`) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtBranch->execute([
                    'b-1',
                    'فرع الرياض الرئيسي',
                    'الرياض - طريق الملك عبد العزيز',
                    '0501234567',
                    'أحمد الحربي',
                    'HQ-RUH'
                ]);
            }

            // Ensure we always have a super admin account in order to avoid locking out the administrator
            $adminCount = self::$pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'admin'")->fetchColumn();
            if ($adminCount == 0) {
                $hashedPassword = password_hash('admin123', PASSWORD_BCRYPT);
                $stmtUser = self::$pdo->prepare("INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `avatar`, `branch_id`, `email`, `phone`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtUser->execute([
                    'u-1',
                    'مدير النظام الموحد',
                    'admin',
                    $hashedPassword,
                    'admin',
                    'admin',
                    'b-1',
                    'admin@almakhzoun.pro',
                    '0507654321'
                ]);
            }

            // Ensure default settings exist
            $settingsCount = self::$pdo->query("SELECT COUNT(*) FROM `settings`")->fetchColumn();
            if ($settingsCount == 0) {
                self::$pdo->exec("INSERT INTO `settings` (`id`, `company_name`, `phone`, `email`, `currency`, `address`, `showroom_header_title`, `showroom_theme`, `showroom_show_price`) VALUES (1, 'شركة المخزون للمحركات المحدودة', '920002131', 'info@almakhzoun.pro', 'ر.س', 'الرياض، المملكة العربية السعودية', 'اختر سيارة أحلامك من مخزوننا الحديث', 'indigo', 1)");
            }

            // 3. Folder Protection Self-Healer:
            // Ensure core folders exist and prevent file injection execution
            $criticalDirs = [
                dirname(self::$lockPath) . '/logs',
                dirname(self::$lockPath) . '/backups',
                dirname(dirname(__DIR__)) . '/uploads',
            ];

            foreach ($criticalDirs as $dir) {
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                
                // Add blank index.html to prevent Directory Listing / Leakage
                $indexHtmlFile = $dir . '/index.html';
                if (!file_exists($indexHtmlFile)) {
                    @file_put_contents($indexHtmlFile, '<html><head><title>Access Denied</title></head><body><h4>Access Denied. Directory secured by Almakhzoun Shield.</h4></body></html>');
                }

                // Place secure .htaccess file to completely block direct PHP/execution execution in folders
                $htaccessFile = $dir . '/.htaccess';
                if (!file_exists($htaccessFile)) {
                    $htaccessContent = "<Files *.php>\n    Order Deny,Allow\n    Deny from all\n</Files>\nOptions -Indexes\n";
                    @file_put_contents($htaccessFile, $htaccessContent);
                }
            }

            // 4. Log Rotation Engine (الصيانة الوقائية وسعة تخزين الخادم):
            // Check logs size and auto-rotate when they grow too large. Prevents server collapse.
            $logsCount = self::$pdo->query("SELECT COUNT(*) FROM `system_logs`")->fetchColumn();
            if ($logsCount > 15000) {
                // Keep the most recent 2,000 logs and delete the rest
                $minIdToKeep = self::$pdo->query("SELECT `id` FROM `system_logs` ORDER BY `id` DESC LIMIT 1 OFFSET 2000")->fetchColumn();
                if ($minIdToKeep) {
                    $stmtDelete = self::$pdo->prepare("DELETE FROM `system_logs` WHERE `id` < ?");
                    $stmtDelete->execute([$minIdToKeep]);
                    
                    // Log the optimization
                    self::$pdo->exec("INSERT INTO `system_logs` (`user_id`, `user_name`, `action`, `details`, `risk_level`) VALUES ('SYSTEM', 'النظام الآمن للصيانة', 'صيانة وقائية وتدوير سجلات', 'تم بنجاح تدوير وتقليص قاعدة بيانات السجلات للحفاظ على سعة استيعاب وسرعة الخادم الموحد', 'low')");
                }
            }
        } catch (Exception $e) {
            // Self-healing should not crash under any circumstances
        }
    }

    /**
     * Cross-Site Request Forgery (CSRF) Guard.
     * Generates a token and stores it in session.
     */
    public static function getCsrfToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verifies if the request CSRF token is correct.
     */
    public static function validateCsrfToken($token)
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Secures and escapes outputs globally against Cross-Site Scripting (XSS).
     */
    public static function escape($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Forces self-healing (called via SecurityCli or background processes).
     */
    public static function forceSelfHealing()
    {
        self::runSelfHealing();
    }

    /**
     * Logs security alerts from external background tasks or scanners.
     */
    public static function logSecurityAlert($action, $details)
    {
        if (self::$pdo) {
            try {
                $stmt = self::$pdo->prepare("INSERT INTO `system_logs` (`user_id`, `user_name`, `action`, `details`, `risk_level`, `ip`) VALUES ('CLI_SEC', 'مدقق الأمان والمهام الخلفية', ?, ?, 'high', ?)");
                $stmt->execute([$action, $details, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
            } catch (Exception $e) {}
        }
    }
}

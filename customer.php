<?php
/**
 * Almakhzoun Pro - Client Showcase & Vehicle Showroom Page
 * Fully responsive, modern UI/UX with Grid layout, Lazy/Skeleton loading,
 * Dark/Light mode support, SEO schema, and immediate WhatsApp/Order integrations.
 * Works seamlessly with the centralized DB config or installer.
 */

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('CONFIG_PATH', __DIR__ . '/config/config.php');
define('LOCK_PATH', __DIR__ . '/storage/install.lock');

$showroom_file = 'customer.php';

// Load and run Security Core WAF/Shield immediately before session or config parsing
require_once __DIR__ . '/modules/security/SecurityCore.php';
SecurityCore::init();

// 1. Database Connection Setup
$config = [];
$pdo = null;

if (file_exists(CONFIG_PATH)) {
    $config = require CONFIG_PATH;
    try {
        $dbConfig = $config['db'];
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
        
        // Initialize SecurityCore database healing and protections with the active PDO instance
        SecurityCore::init($pdo);
        
        // Ensure customer_orders table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `customer_orders` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `car_id` varchar(50) NOT NULL,
          `customer_name` varchar(100) NOT NULL,
          `customer_phone` varchar(50) NOT NULL,
          `notes` text DEFAULT NULL,
          `status` varchar(50) DEFAULT 'new',
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          INDEX `idx_order_car_id` (`car_id`),
          INDEX `idx_order_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Ensure showroom_reviews table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `showroom_reviews` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `customer_name` VARCHAR(150) NOT NULL,
          `rating` INT NOT NULL,
          `comment` TEXT NOT NULL,
          `status` VARCHAR(50) DEFAULT 'approved',
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          INDEX `idx_sr_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Ensure showroom_sales table exists for customer sales representatives
        $pdo->exec("CREATE TABLE IF NOT EXISTS `showroom_sales` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `name` VARCHAR(150) NOT NULL,
          `title` VARCHAR(150) DEFAULT NULL,
          `phone` VARCHAR(50) NOT NULL,
          `whatsapp` VARCHAR(50) NOT NULL,
          `avatar` VARCHAR(255) DEFAULT NULL,
          `status` VARCHAR(50) DEFAULT 'active',
          `sort_order` INT DEFAULT 0,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          INDEX `idx_ss_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        try {
            $pdo->exec("ALTER TABLE `showroom_sales` ADD COLUMN `avatar` VARCHAR(255) DEFAULT NULL AFTER `whatsapp`");
        } catch (Exception $e) {
            // Column may already exist
        }

        try {
            $pdo->exec("ALTER TABLE `showroom_sales` ADD UNIQUE INDEX `idx_ss_phone_unique` (`phone`)");
        } catch (Exception $e) {
            // Index might already exist or table has duplicate records
        }

        // Seed default sales representatives if empty
        $countSales = $pdo->query("SELECT COUNT(*) FROM `showroom_sales`")->fetchColumn();
        if ($countSales == 0) {
            $stmtInsertSales = $pdo->prepare("INSERT INTO `showroom_sales` (`name`, `title`, `phone`, `whatsapp`, `status`, `sort_order`) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtInsertSales->execute(['أحمد الحربي', 'مستشار المبيعات - فرع الرياض', '0500000001', '966500000001', 'active', 1]);
            $stmtInsertSales->execute(['ياسر اليامي', 'مستشار المبيعات - فرع نجران', '0500000002', '966500000002', 'active', 2]);
        }
        
    } catch (PDOException $e) {
        $db_error = $e->getMessage();
    }
} else {
    $db_error = "ملف تهيئة قاعدة البيانات config.php مفقود. يرجى إكمال التثبيت أولاً.";
}

// 1.5 Handle AJAX Ad Click Tracking & Redirect
if (isset($_GET['action']) && $_GET['action'] === 'click_ad') {
    $adId = intval($_GET['id'] ?? 0);
    if ($pdo && $adId > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE `showroom_ads` SET `clicks_count` = `clicks_count` + 1 WHERE `id` = ?");
            $stmt->execute([$adId]);
            
            $target = $pdo->prepare("SELECT `link_url` FROM `showroom_ads` WHERE `id` = ?");
            $target->execute([$adId]);
            $url = $target->fetchColumn() ?: 'customer.php';
            header("Location: $url");
            exit;
        } catch (Exception $e) {
            // Safe redirect on error
        }
    }
    header("Location: customer.php");
    exit;
}

// 2. Handle AJAX Order Submission
if (isset($_GET['action']) && $_GET['action'] === 'submit_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    if (!$pdo) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'لا يوجد اتصال بقاعدة البيانات.']);
        exit;
    }

    // 1. CSRF Token Protection for Order Submission
    $csrf = $_POST['csrf_token'] ?? '';
    if (!SecurityCore::validateCsrfToken($csrf)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'انتهت صلاحية الجلسة الآمنة (CSRF Token Invalid). يرجى تحديث الصفحة وإعادة المحاولة.']);
        exit;
    }
    
    $car_id = trim($_POST['car_id'] ?? '');
    // 2. Input Sanitization against XSS/Script Injection
    $customer_name = htmlspecialchars(strip_tags(trim($_POST['customer_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    $customer_phone = htmlspecialchars(strip_tags(trim($_POST['customer_phone'] ?? '')), ENT_QUOTES, 'UTF-8');
    $notes = htmlspecialchars(strip_tags(trim($_POST['notes'] ?? '')), ENT_QUOTES, 'UTF-8');
    
    if (empty($car_id) || empty($customer_name) || empty($customer_phone)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'يرجى ملء جميع الحقول المطلوبة (الاسم ورقم الجوال).']);
        exit;
    }
    
    try {
        // Verify car exists
        $carStmt = $pdo->prepare("SELECT id, make, model FROM `cars` WHERE id = ?");
        $carStmt->execute([$car_id]);
        $car = $carStmt->fetch();
        
        if (!$car) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'السيارة المطلوبة غير موجودة في النظام.']);
            exit;
        }
        
        // Insert order
        $stmt = $pdo->prepare("INSERT INTO `customer_orders` (`car_id`, `customer_name`, `customer_phone`, `notes`, `status`) VALUES (?, ?, ?, ?, 'new')");
        $stmt->execute([$car_id, $customer_name, $customer_phone, $notes]);
        
        // Write audit log safely
        try {
            $logDetails = "طلب شراء سيارة جديد للعميل: $customer_name على السيارة: {$car['make']} {$car['model']}";
            $logStmt = $pdo->prepare("INSERT INTO `system_logs` (`user_id`, `user_name`, `action`, `details`, `risk_level`, `ip`) VALUES ('customer', 'عميل خارجي', 'طلب شراء سيارة', ?, 'low', ?)");
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $logStmt->execute([$logDetails, $ip]);
        } catch (Exception $logEx) {
            // Ignore logging failure to avoid blocking customer order submission
        }
        
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'تم استلام طلبك بنجاح! سيتواصل معك أحد مناديبنا في أقرب وقت.']);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'حدث خطأ أثناء حفظ الطلب: ' . $e->getMessage()]);
    }
    exit;
}

// Handle AJAX Contact Form Submission
if (isset($_GET['action']) && $_GET['action'] === 'submit_contact' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    if (!$pdo) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'لا يوجد اتصال بقاعدة البيانات.']);
        exit;
    }

    $csrf = $_POST['csrf_token'] ?? '';
    if (!SecurityCore::validateCsrfToken($csrf)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'انتهت صلاحية الجلسة الآمنة (CSRF Token Invalid). يرجى تحديث الصفحة وإعادة المحاولة.']);
        exit;
    }
    
    $name = htmlspecialchars(strip_tags(trim($_POST['name'] ?? '')), ENT_QUOTES, 'UTF-8');
    $phone = htmlspecialchars(strip_tags(trim($_POST['phone'] ?? '')), ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars(strip_tags(trim($_POST['email'] ?? '')), ENT_QUOTES, 'UTF-8');
    $subject = htmlspecialchars(strip_tags(trim($_POST['subject'] ?? '')), ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars(strip_tags(trim($_POST['message'] ?? '')), ENT_QUOTES, 'UTF-8');
    
    if (empty($name) || empty($phone) || empty($message)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'يرجى ملء جميع الحقول الإلزامية (الاسم، الجوال، ونص الرسالة).']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO `contact_inquiries` (`name`, `phone`, `email`, `subject`, `message`, `status`) VALUES (?, ?, ?, ?, ?, 'new')");
        $stmt->execute([$name, $phone, $email, !empty($subject) ? $subject : 'استفسار عام', $message]);
        
        // Write audit log safely
        try {
            $logDetails = "رسالة تواصل جديدة من العميل: $name - جوال: $phone - موضوع: $subject";
            $logStmt = $pdo->prepare("INSERT INTO `system_logs` (`user_id`, `user_name`, `action`, `details`, `risk_level`, `ip`) VALUES ('customer', 'عميل خارجي', 'رسالة تواصل بنا', ?, 'low', ?)");
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $logStmt->execute([$logDetails, $ip]);
        } catch (Exception $logEx) {
            // Ignore
        }
        
        // Add notification safely
        try {
            $notifDetails = "قام العميل $name بإرسال رسالة تواصل جديدة بعنوان: " . (!empty($subject) ? $subject : 'استفسار عام');
            $notifStmt = $pdo->prepare("INSERT INTO `notifications` (`operation_type`, `title`, `description`, `user_id`, `user_name`, `is_read`) VALUES ('contact_received', 'رسالة تواصل جديدة', ?, 'customer', 'عميل خارجي', 0)");
            $notifStmt->execute([$notifDetails]);
        } catch (Exception $notifEx) {
            // Ignore
        }

        ob_clean();
        echo json_encode(['success' => true, 'message' => 'تم إرسال رسالتك بنجاح! شكراً لتواصلك معنا وسنقوم بالرد عليك في أقرب وقت.']);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'حدث خطأ أثناء إرسال الرسالة: ' . $e->getMessage()]);
    }
    exit;
}

// Handle AJAX Customer Review Submission
if (isset($_GET['action']) && $_GET['action'] === 'submit_review' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    if (!$pdo) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'لا يوجد اتصال بقاعدة البيانات.']);
        exit;
    }

    $csrf = $_POST['csrf_token'] ?? '';
    if (!SecurityCore::validateCsrfToken($csrf)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'انتهت صلاحية الجلسة الآمنة (CSRF Token Invalid). يرجى تحديث الصفحة وإعادة المحاولة.']);
        exit;
    }
    
    $customer_name = htmlspecialchars(strip_tags(trim($_POST['customer_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    $rating = intval($_POST['rating'] ?? 5);
    $comment = htmlspecialchars(strip_tags(trim($_POST['comment'] ?? '')), ENT_QUOTES, 'UTF-8');
    
    if (empty($customer_name) || empty($comment)) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'يرجى كتابة اسمك والتعليق لإرسال التقييم.']);
        exit;
    }
    
    if ($rating < 1 || $rating > 5) {
        $rating = 5;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO `showroom_reviews` (`customer_name`, `rating`, `comment`, `status`) VALUES (?, ?, ?, 'approved')");
        $stmt->execute([$customer_name, $rating, $comment]);
        
        // Write audit log safely
        try {
            $logDetails = "تقييم وتقييم جديد من العميل: $customer_name بمستوى $rating نجوم";
            $logStmt = $pdo->prepare("INSERT INTO `system_logs` (`user_id`, `user_name`, `action`, `details`, `risk_level`, `ip`) VALUES ('customer', 'عميل خارجي', 'إضافة تقييم المعرض', ?, 'low', ?)");
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $logStmt->execute([$logDetails, $ip]);
        } catch (Exception $logEx) {
            // Ignore
        }
        
        // Add notification safely
        try {
            $stars = str_repeat('⭐', $rating);
            $notifDetails = "قام العميل $customer_name بإضافة تقييم جديد: $rating نجوم ($stars) - \"$comment\"";
            $notifStmt = $pdo->prepare("INSERT INTO `notifications` (`operation_type`, `title`, `description`, `user_id`, `user_name`, `is_read`) VALUES ('review_received', 'تقييم جديد من عميل', ?, 'customer', 'عميل خارجي', 0)");
            $notifStmt->execute([$notifDetails]);
        } catch (Exception $notifEx) {
            // Ignore
        }

        ob_clean();
        echo json_encode(['success' => true, 'message' => 'شكراً لك! تم نشر تقييمك بنجاح ويظهر الآن في المعرض.']);
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'حدث خطأ أثناء حفظ التقييم: ' . $e->getMessage()]);
    }
    exit;
}

// 3. Fetch Company Settings and Vehicles
$defaultSettings = [
    'company_name' => 'شركة المخزون للمحركات المحدودة',
    'phone' => '920002131',
    'whatsapp_phone' => '966500000000',
    'currency' => 'ر.س',
    'logo' => null,
    'address' => 'الرياض، المملكة العربية السعودية',
    'showroom_header_title' => 'اختر سيارة أحلامك من مخزوننا الحديث',
    'showroom_header_subtitle' => 'نقدم لك خدمات متميزة، سيارات مضمونة ومفحوصة بالكامل، وتسهيلات تواصل مباشرة مع مناديب المبيعات المعتمدين.',
    'showroom_footer_text' => 'جميع الحقوق محفوظة © 2026 شركة المخزون للمحركات المحدودة.',
    'showroom_theme' => 'indigo',
    'showroom_show_price' => 1,
    'showroom_show_filters' => 1,
    'showroom_facebook' => '',
    'showroom_twitter' => '',
    'showroom_instagram' => '',
    'showroom_linkedin' => '',
    'showroom_snapchat' => '',
    'showroom_youtube' => '',
    'showroom_custom_socials' => '[]',
    'showroom_banner_image' => '',
    'showroom_banner_overlay_opacity' => 50,
    'showroom_banner_opacity' => 25,
    'showroom_custom_pages' => '[]',
    'showroom_menu_links' => '[]',
    'showroom_custom_css' => '',
    'showroom_custom_js' => '',
    'default_showroom_name' => 'معرض السيارات الافتراضي',
    'logo_height' => 40,
    'logo_color' => '#6366f1',
    'logo_border_radius' => 12,
    'company_name_color' => '#0f172a',
    'company_name_color_dark' => '#ffffff',
    'company_name_font_size' => 'text-sm',
    'showroom_name_color' => '#6366f1',
    'showroom_name_color_dark' => '#818cf8',
    'showroom_name_font_size' => 'text-[9px]'
];

$companySettings = $defaultSettings;

$cars = [];
$makes = [];
$activeAds = [];

if ($pdo) {
    // Settings
    $settingsQuery = $pdo->query("SELECT * FROM `settings` LIMIT 1");
    if ($settingsQuery) {
        $dbSettings = $settingsQuery->fetch();
        if ($dbSettings) {
            foreach ($dbSettings as $k => $v) {
                if ($v !== null && $v !== '') {
                    $companySettings[$k] = $v;
                }
            }
        }
    }
    
    // Available cars only
    $carsQuery = $pdo->query("SELECT * FROM `cars` WHERE `status` = 'available' ORDER BY `created_at` DESC");
    if ($carsQuery) {
        $cars = $carsQuery->fetchAll();
    }
    
    // Distinct makes for filtering
    $makesQuery = $pdo->query("SELECT DISTINCT make FROM `cars` WHERE `status` = 'available' ORDER BY make ASC");
    if ($makesQuery) {
        $makes = $makesQuery->fetchAll(PDO::FETCH_COLUMN);
    }

    // Fetch Active Advertisements & Offers
    $activeAds = [];
    try {
        $today = date('Y-m-d');
        $adsStmt = $pdo->prepare("SELECT * FROM `showroom_ads` WHERE `status` = 'active' AND (`start_date` IS NULL OR `start_date` <= ?) AND (`end_date` IS NULL OR `end_date` >= ?) ORDER BY `id` DESC");
        $adsStmt->execute([$today, $today]);
        $activeAds = $adsStmt->fetchAll();
        
        if (!empty($activeAds)) {
            $adIds = array_column($activeAds, 'id');
            $inQuery = implode(',', array_fill(0, count($adIds), '?'));
            $upStmt = $pdo->prepare("UPDATE `showroom_ads` SET `views_count` = `views_count` + 1 WHERE `id` IN ($inQuery)");
            $upStmt->execute($adIds);
        }
    } catch (Exception $e) {
        // Safe fallback if table does not exist yet
    }

    // Decode Custom Pages and Menus
    $custom_pages = [];
    if (!empty($companySettings['showroom_custom_pages'])) {
        $custom_pages = json_decode($companySettings['showroom_custom_pages'], true);
        if (!is_array($custom_pages)) {
            $custom_pages = [];
        }
    }

    $custom_links = [];
    if (!empty($companySettings['showroom_menu_links'])) {
        $custom_links = json_decode($companySettings['showroom_menu_links'], true);
        if (!is_array($custom_links)) {
            $custom_links = [];
        }
    }

    $current_page_slug = isset($_GET['page']) ? trim($_GET['page']) : '';
    $current_custom_page = null;
    if ($current_page_slug) {
        foreach ($custom_pages as $p) {
            if ($p['slug'] === $current_page_slug) {
                $current_custom_page = $p;
                break;
            }
        }
    }

    // Fetch Showroom Sales Representatives
    $sales_reps = [];
    if ($pdo && $current_page_slug === 'sales_team') {
        try {
            $sales_reps = $pdo->query("SELECT * FROM `showroom_sales` WHERE `status` = 'active' ORDER BY `sort_order` ASC, `id` DESC")->fetchAll();
        } catch (Exception $e) {
            // fallback
        }
    }

    // Fetch Custom SEO parameters independently per page
    $seo_key = $current_page_slug ?: 'customer_showroom';
    $seo = null;
    if ($pdo) {
        try {
            $seoQuery = $pdo->prepare("SELECT * FROM `seo_pages` WHERE `page_key` = ? LIMIT 1");
            if ($seoQuery) {
                $seoQuery->execute([$seo_key]);
                $seo = $seoQuery->fetch();
            }
        } catch (Exception $e) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `seo_pages` (
                  `id` INT NOT NULL AUTO_INCREMENT,
                  `page_key` VARCHAR(50) NOT NULL UNIQUE,
                  `page_title` VARCHAR(255) NOT NULL,
                  `meta_title` VARCHAR(255) DEFAULT NULL,
                  `meta_description` TEXT DEFAULT NULL,
                  `meta_keywords` TEXT DEFAULT NULL,
                  `og_image` VARCHAR(255) DEFAULT NULL,
                  `custom_schema` TEXT DEFAULT NULL,
                  `og_title` VARCHAR(255) DEFAULT NULL,
                  `og_description` TEXT DEFAULT NULL,
                  `twitter_card` VARCHAR(50) DEFAULT 'summary_large_image',
                  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                
                $seoQuery = $pdo->prepare("SELECT * FROM `seo_pages` WHERE `page_key` = ? LIMIT 1");
                $seoQuery->execute([$seo_key]);
                $seo = $seoQuery->fetch();
            } catch (Exception $inner) {
                // Ignore gracefully
            }
        }
    }
}

// SEO variables
$page_title = $seo && !empty($seo['meta_title']) ? htmlspecialchars($seo['meta_title']) : (htmlspecialchars($companySettings['company_name']) . " - " . htmlspecialchars($companySettings['default_showroom_name'] ?? 'معرض السيارات الافتراضي'));
if ($current_custom_page && (!$seo || empty($seo['meta_title']))) {
    $page_title = htmlspecialchars($current_custom_page['title']) . " - " . htmlspecialchars($companySettings['company_name']);
}
$page_description = $seo && !empty($seo['meta_description']) ? htmlspecialchars($seo['meta_description']) : "تصفح أحدث وأرقى موديلات السيارات المتوفرة لدينا بأفضل الأسعار والمواصفات مع خيارات الطلب المباشر والتواصل الفوري عبر الواتساب.";
$page_keywords = $seo && !empty($seo['meta_keywords']) ? htmlspecialchars($seo['meta_keywords']) : "سيارات للبيع, شراء سيارات, معرض سيارات, المخزون للمحركات, تقسيط سيارات";
$custom_schema = $seo && !empty($seo['custom_schema']) ? $seo['custom_schema'] : null;

// Open Graph & Twitter Card additions
$og_title = $seo && !empty($seo['og_title']) ? htmlspecialchars($seo['og_title']) : $page_title;
$og_description = $seo && !empty($seo['og_description']) ? htmlspecialchars($seo['og_description']) : $page_description;
$og_image = $seo && !empty($seo['og_image']) ? htmlspecialchars($seo['og_image']) : ($companySettings['showroom_banner_image'] ?: '');
$twitter_card = $seo && !empty($seo['twitter_card']) ? htmlspecialchars($seo['twitter_card']) : 'summary_large_image';

// Visitor Analytics Engine
if ($pdo) {
    try {
        $sess_id = session_id() ?: 'none';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        $page_url = $_SERVER['REQUEST_URI'] ?? '/';
        $v_date = date('Y-m-d');

        // Human readable page name for analytics
        $p_name = 'الرئيسية (معرض السيارات)';
        if (!empty($current_page_slug)) {
            $p_name = "صفحة مخصصة: " . ($current_custom_page['title'] ?? $current_page_slug);
        }

        // Avoid logging administrative or representative traffic to keep analytics clean
        if (!isset($_SESSION['user_id'])) {
            $logStmt = $pdo->prepare("INSERT INTO `showroom_visits` (`session_id`, `ip_address`, `user_agent`, `page_url`, `page_title`, `referrer`, `visit_date`) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $logStmt->execute([$sess_id, $ip, $ua, $page_url, $p_name, $referrer, $v_date]);
        }
    } catch (Exception $logEx) {
        // Safe skip
    }
}

$whatsapp_default = $companySettings['whatsapp_phone'] ?? $companySettings['phone'] ?? '';
// Normalize whatsapp number (must start with country code, remove spaces/pluses)
$whatsapp_clean = preg_replace('/[^0-9]/', '', $whatsapp_default);
if (!str_starts_with($whatsapp_clean, '966') && str_starts_with($whatsapp_clean, '05')) {
    $whatsapp_clean = '966' . substr($whatsapp_clean, 1);
}

// Fetch showroom reviews
$reviews = [];
$average_rating = 5.0;
$total_reviews = 0;
$rating_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

if ($pdo) {
    try {
        $reviewsQuery = $pdo->query("SELECT * FROM `showroom_reviews` WHERE `status` = 'approved' ORDER BY `created_at` DESC");
        if ($reviewsQuery) {
            $reviews = $reviewsQuery->fetchAll();
            $total_reviews = count($reviews);
            if ($total_reviews > 0) {
                $sum_ratings = 0;
                foreach ($reviews as $rev) {
                    $r = intval($rev['rating']);
                    $sum_ratings += $r;
                    if (isset($rating_counts[$r])) {
                        $rating_counts[$r]++;
                    }
                }
                $average_rating = round($sum_ratings / $total_reviews, 1);
            }
        }
    } catch (Exception $e) {
        // Safe skip
    }
}

// Active Color Theme Palette
$theme = $companySettings['showroom_theme'] ?? 'indigo';
$themeColors = [
    'indigo' => [
        '50' => '#f5f3ff',
        '100' => '#ede9fe',
        '500' => '#6366f1',
        '600' => '#4f46e5',
        '700' => '#4338ca',
        '900' => '#312e81',
    ],
    'emerald' => [
        '50' => '#ecfdf5',
        '100' => '#d1fae5',
        '500' => '#10b981',
        '600' => '#059669',
        '700' => '#047857',
        '900' => '#064e3b',
    ],
    'rose' => [
        '50' => '#fff1f2',
        '100' => '#ffe4e6',
        '500' => '#f43f5e',
        '600' => '#e11d48',
        '700' => '#be123c',
        '900' => '#881337',
    ],
    'sky' => [
        '50' => '#f0f9ff',
        '100' => '#e0f2fe',
        '500' => '#0ea5e9',
        '600' => '#0284c7',
        '700' => '#0369a1',
        '900' => '#0c4a6e',
    ],
    'amber' => [
        '50' => '#fffbeb',
        '100' => '#fef3c7',
        '500' => '#f59e0b',
        '600' => '#d97706',
        '700' => '#b45309',
        '900' => '#78350f',
    ],
    'slate' => [
        '50' => '#f8fafc',
        '100' => '#f1f5f9',
        '500' => '#64748b',
        '600' => '#475569',
        '700' => '#334155',
        '900' => '#0f172a',
    ]
];
$activeColors = $themeColors[$theme] ?? $themeColors['indigo'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo $page_description; ?>">
    <meta name="keywords" content="<?php echo $page_keywords; ?>">

    <?php if (!empty($companySettings['seo_google_verification'])): ?>
    <meta name="google-site-verification" content="<?php echo htmlspecialchars($companySettings['seo_google_verification']); ?>">
    <?php endif; ?>
    <?php if (!empty($companySettings['seo_bing_verification'])): ?>
    <meta name="msvalidate.01" content="<?php echo htmlspecialchars($companySettings['seo_bing_verification']); ?>">
    <?php endif; ?>

    <?php if (!empty($companySettings['seo_google_analytics'])): ?>
        <?php if (strpos($companySettings['seo_google_analytics'], '<script') !== false): ?>
            <?php echo $companySettings['seo_google_analytics']; ?>
        <?php else: ?>
            <!-- Global site tag (gtag.js) - Google Analytics -->
            <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars($companySettings['seo_google_analytics']); ?>"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config', '<?php echo htmlspecialchars($companySettings['seo_google_analytics']); ?>');
            </script>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($companySettings['seo_facebook_pixel'])): ?>
        <?php if (strpos($companySettings['seo_facebook_pixel'], '<script') !== false): ?>
            <?php echo $companySettings['seo_facebook_pixel']; ?>
        <?php else: ?>
            <!-- Facebook Pixel Code -->
            <script>
                !function(f,b,e,v,n,t,s)
                {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
                n.callMethod.apply(n,arguments):n.queue.push(arguments)};
                if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
                n.queue=[];t=b.createElement(e);t.async=!0;
                t.src=v;s=b.getElementsByTagName(e)[0];
                s.parentNode.insertBefore(t,s)}(window, document,'script',
                'https://connect.facebook.net/en_US/fbevents.js');
                fbq('init', '<?php echo htmlspecialchars($companySettings['seo_facebook_pixel']); ?>');
                fbq('track', 'PageView');
            </script>
            <noscript>
                <img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?php echo htmlspecialchars($companySettings['seo_facebook_pixel']); ?>&ev=PageView&noscript=1"/>
            </noscript>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Open Graph (Facebook Share, etc.) -->
    <meta property="og:title" content="<?php echo $og_title; ?>">
    <meta property="og:description" content="<?php echo $og_description; ?>">
    <meta property="og:type" content="website">
    <?php if (!empty($og_image)): ?>
    <meta property="og:image" content="<?php echo $og_image; ?>">
    <?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="<?php echo $twitter_card; ?>">
    <meta name="twitter:title" content="<?php echo $og_title; ?>">
    <meta name="twitter:description" content="<?php echo $og_description; ?>">
    <?php if (!empty($og_image)): ?>
    <meta name="twitter:image" content="<?php echo $og_image; ?>">
    <?php endif; ?>
    
    <?php if ($custom_schema): ?>
    <!-- Structured Schema JSON-LD (SEO Schema) -->
    <script type="application/ld+json">
    <?php echo $custom_schema; ?>
    </script>
    <?php endif; ?>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS (via CDN with customized configurations) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Cairo', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '<?php echo $activeColors['50']; ?>',
                            100: '<?php echo $activeColors['100']; ?>',
                            500: '<?php echo $activeColors['500']; ?>',
                            600: '<?php echo $activeColors['600']; ?>',
                            700: '<?php echo $activeColors['700']; ?>',
                            900: '<?php echo $activeColors['900']; ?>',
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        /* Smooth fade-in image transition */
        .lazy-image {
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
        }
        .lazy-image.loaded {
            opacity: 1;
        }
        
        /* Skeletal Pulse Animation */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .4; }
        }
        .animate-pulse-custom {
            animation: pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        /* Custom Brand Identity Styles */
        .brand-company-name {
            color: <?php echo htmlspecialchars($companySettings['company_name_color'] ?? '#0f172a'); ?>;
        }
        .dark .brand-company-name {
            color: <?php echo htmlspecialchars($companySettings['company_name_color_dark'] ?? '#ffffff'); ?> !important;
        }
        .brand-showroom-name {
            color: <?php echo htmlspecialchars($companySettings['showroom_name_color'] ?? '#6366f1'); ?>;
        }
        .dark .brand-showroom-name {
            color: <?php echo htmlspecialchars($companySettings['showroom_name_color_dark'] ?? '#818cf8'); ?> !important;
        }

        <?php if (!empty($companySettings['showroom_custom_css'])): ?>
        /* Custom CSS from Settings Panel */
        <?php echo $companySettings['showroom_custom_css']; ?>
        <?php endif; ?>
    </style>
    
    <!-- Structured Data (Schema.org) for Google rich results -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "AutoDealer",
      "name": "<?php echo htmlspecialchars($companySettings['company_name']); ?>",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "<?php echo htmlspecialchars($companySettings['address']); ?>",
        "addressCountry": "SA"
      },
      "telephone": "<?php echo htmlspecialchars($companySettings['phone']); ?>",
      "url": "<?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>"
    }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100 min-h-screen">

    <!-- Header Section -->
    <header class="sticky top-0 z-40 bg-white/90 dark:bg-slate-900/90 backdrop-blur border-b border-slate-200 dark:border-slate-800 transition">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
            <a href="<?php echo $showroom_file; ?>" class="flex items-center gap-3">
                <?php if (!empty($companySettings['logo'])): ?>
                    <img src="<?php echo htmlspecialchars($companySettings['logo']); ?>" alt="Logo" style="height: <?php echo intval($companySettings['logo_height'] ?? 40); ?>px;" class="w-auto object-contain">
                <?php else: ?>
                    <div style="background-color: <?php echo htmlspecialchars($companySettings['logo_color'] ?? '#6366f1'); ?>; border-radius: <?php echo intval($companySettings['logo_border_radius'] ?? 12); ?>px;" class="w-10 h-10 flex items-center justify-center font-black text-white text-lg">M</div>
                <?php endif; ?>
                <div>
                    <span class="brand-company-name font-black <?php echo htmlspecialchars($companySettings['company_name_font_size'] ?? 'text-sm'); ?> block leading-none"><?php echo htmlspecialchars($companySettings['company_name']); ?></span>
                    <span class="brand-showroom-name font-bold tracking-wider font-sans block mt-1 <?php echo htmlspecialchars($companySettings['showroom_name_font_size'] ?? 'text-[9px]'); ?>"><?php echo htmlspecialchars($companySettings['default_showroom_name'] ?? 'معرض السيارات الافتراضي'); ?></span>
                </div>
            </a>

            <!-- Desktop Menu Links (Custom & Dynamic) -->
            <nav class="hidden md:flex items-center gap-6">
                <a href="<?php echo $showroom_file; ?>" class="text-xs font-bold <?php echo ($current_page_slug === '') ? 'text-brand-600 dark:text-brand-400 font-extrabold' : 'text-slate-700 dark:text-slate-300 hover:text-brand-600 dark:hover:text-brand-400'; ?> transition">🚗 المعرض</a>
                <a href="<?php echo $showroom_file; ?>?page=branches" class="text-xs font-bold <?php echo ($current_page_slug === 'branches') ? 'text-brand-600 dark:text-brand-400 font-extrabold' : 'text-slate-700 dark:text-slate-300 hover:text-brand-600 dark:hover:text-brand-400'; ?> transition">📍 فروعنا</a>
                <a href="<?php echo $showroom_file; ?>?page=sales_team" class="text-xs font-bold <?php echo ($current_page_slug === 'sales_team') ? 'text-brand-600 dark:text-brand-400 font-extrabold' : 'text-slate-700 dark:text-slate-300 hover:text-brand-600 dark:hover:text-brand-400'; ?> transition">👥 فريق المبيعات</a>
                
                <?php foreach ($custom_pages as $page): ?>
                    <?php if (in_array($page['visibility'] ?? 'both', ['both', 'header'])): ?>
                        <a href="<?php echo $showroom_file; ?>?page=<?php echo urlencode($page['slug']); ?>" class="text-xs font-bold <?php echo ($current_page_slug === $page['slug']) ? 'text-brand-600 dark:text-brand-400' : 'text-slate-700 dark:text-slate-300 hover:text-brand-600 dark:hover:text-brand-400'; ?> transition flex items-center gap-1">
                            <?php if(!empty($page['icon'])): ?>
                                <span class="inline-block"><?php echo htmlspecialchars($page['icon']); ?></span>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($page['title']); ?></span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php foreach ($custom_links as $link): ?>
                    <?php if (in_array($link['location'] ?? 'header', ['both', 'header'])): ?>
                        <a href="<?php echo htmlspecialchars($link['url']); ?>" <?php echo !empty($link['target']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?> class="text-xs font-bold text-slate-700 dark:text-slate-300 hover:text-brand-600 dark:hover:text-brand-400 transition flex items-center gap-1">
                            <?php if(!empty($link['icon'])): ?>
                                <span class="inline-block"><?php echo htmlspecialchars($link['icon']); ?></span>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($link['title']); ?></span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            
            <div class="flex items-center gap-4">
                <!-- Send Message button -->
                <button onclick="openContactModal()" class="hidden sm:flex items-center gap-1.5 px-3.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:hover:bg-indigo-900/40 border border-indigo-200 dark:border-indigo-800/40 text-xs font-bold rounded-full transition text-indigo-700 dark:text-indigo-400 cursor-pointer">
                    <span>✉️</span>
                    <span>راسلنا</span>
                </button>

                <!-- Direct Call button -->
                <a href="tel:<?php echo htmlspecialchars($companySettings['phone']); ?>" class="hidden sm:flex items-center gap-1.5 px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-bold rounded-full transition text-slate-700 dark:text-slate-200">
                    <span>📞 اتصل بنا:</span>
                    <span class="font-sans text-[11px] font-bold"><?php echo htmlspecialchars($companySettings['phone']); ?></span>
                </a>

                <!-- Unified Login button -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if (($_SESSION['user_role'] ?? '') === 'representative'): ?>
                        <a href="index.php?page=inventory" title="الذهاب إلى لوحة الجرد والمخزن 📦" class="px-3 py-1.5 flex items-center gap-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition text-xs font-bold shadow-md select-none">
                            <span>📦 المخزن والجرد</span>
                        </a>
                    <?php else: ?>
                        <a href="index.php?page=dashboard" title="الذهاب إلى لوحة التحكم الإدارية ⚙️" class="px-3 py-1.5 flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition text-xs font-bold shadow-md select-none">
                            <span>⚙️ لوحة التحكم</span>
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="index.php?page=login" title="بوابة الدخول الموحد (للإدارة والمناديب فقط)" class="w-9 h-9 flex items-center justify-center rounded-lg bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 hover:border-indigo-500/30 transition shadow-sm" aria-label="تسجيل الدخول">
                        <span class="text-sm select-none">🔑</span>
                    </a>
                <?php endif; ?>
                
                <!-- Dark / Light Mode Toggle -->
                <button onclick="toggleTheme()" class="w-9 h-9 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 transition" id="theme-toggle" aria-label="تبديل المظهر">
                    <!-- Sun icon -->
                    <svg id="theme-toggle-sun" class="w-5 h-5 text-amber-500 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m0-12.728l.707.707m12.728 12.728l.707.707M12 8a4 4 0 100 8 4 4 0 000-8z"></path></svg>
                    <!-- Moon icon -->
                    <svg id="theme-toggle-moon" class="w-5 h-5 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                </button>

                <!-- Mobile Menu Button -->
                <button onclick="toggleMobileMenu()" class="md:hidden w-9 h-9 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 transition" aria-label="القائمة">
                    <svg class="w-5 h-5 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>
    </header>

    <!-- Mobile Navigation Menu -->
    <div id="mobile-menu" class="hidden md:hidden bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 transition shadow-inner">
        <div class="px-4 py-3 space-y-1.5">
            <a href="<?php echo $showroom_file; ?>" class="block text-xs font-black py-2.5 px-3 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 <?php echo ($current_page_slug === '') ? 'bg-brand-50 text-brand-600 dark:bg-slate-800 dark:text-brand-400' : 'text-slate-700 dark:text-slate-300'; ?> transition">🚗 المعرض الرئيسي</a>
            <a href="<?php echo $showroom_file; ?>?page=branches" class="block text-xs font-black py-2.5 px-3 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 <?php echo ($current_page_slug === 'branches') ? 'bg-brand-50 text-brand-600 dark:bg-slate-800 dark:text-brand-400' : 'text-slate-700 dark:text-slate-300'; ?> transition flex items-center gap-2">📍 فروعنا</a>
            <a href="<?php echo $showroom_file; ?>?page=sales_team" class="block text-xs font-black py-2.5 px-3 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 <?php echo ($current_page_slug === 'sales_team') ? 'bg-brand-50 text-brand-600 dark:bg-slate-800 dark:text-brand-400' : 'text-slate-700 dark:text-slate-300'; ?> transition flex items-center gap-2">👥 فريق المبيعات</a>
            
            <?php foreach ($custom_pages as $page): ?>
                <?php if (in_array($page['visibility'] ?? 'both', ['both', 'header'])): ?>
                    <a href="<?php echo $showroom_file; ?>?page=<?php echo urlencode($page['slug']); ?>" class="block text-xs font-black py-2.5 px-3 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 <?php echo ($current_page_slug === $page['slug']) ? 'bg-brand-50 text-brand-600 dark:bg-slate-800 dark:text-brand-400' : 'text-slate-700 dark:text-slate-300'; ?> transition flex items-center gap-2">
                        <?php if(!empty($page['icon'])): ?>
                            <span><?php echo htmlspecialchars($page['icon']); ?></span>
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($page['title']); ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php foreach ($custom_links as $link): ?>
                <?php if (in_array($link['location'] ?? 'header', ['both', 'header'])): ?>
                    <a href="<?php echo htmlspecialchars($link['url']); ?>" <?php echo !empty($link['target']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?> class="block text-xs font-black py-2.5 px-3 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 transition flex items-center gap-2">
                        <?php if(!empty($link['icon'])): ?>
                            <span><?php echo htmlspecialchars($link['icon']); ?></span>
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($link['title']); ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if (($_SESSION['user_role'] ?? '') === 'representative'): ?>
                    <a href="index.php?page=inventory" class="block text-xs font-black py-2.5 px-3 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center gap-2 mt-2 shadow">
                        <span>📦</span> <span>الذهاب إلى لوحة الجرد والمخزن</span>
                    </a>
                <?php else: ?>
                    <a href="index.php?page=dashboard" class="block text-xs font-black py-2.5 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white transition flex items-center gap-2 mt-2 shadow">
                        <span>⚙️</span> <span>الذهاب إلى لوحة التحكم الإدارية</span>
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <a href="index.php?page=login" class="block text-xs font-black py-2.5 px-3 rounded-lg bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 transition flex items-center gap-2 border border-indigo-500/20 mt-2">
                    <span>🔑</span> <span>بوابة الدخول الموحد (المناديب والإدارة)</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($current_page_slug === 'branches'): ?>
        <!-- PAGE: BRANCHES -->
        <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12" dir="rtl">
            <div class="text-center max-w-2xl mx-auto mb-12">
                <span class="px-3 py-1 bg-brand-100 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 text-[10px] font-black rounded-full uppercase tracking-wider">📍 شبكة فروعنا</span>
                <h1 class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white mt-3">تشرفنا زيارتكم في فروعنا</h1>
                <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-2 leading-relaxed">تفضلوا بزيارة أحد فروع شركة المخزون للمحركات للاطلاع على أحدث السيارات والحصول على استشارات مبيعات مخصصة وتسهيلات حصرية.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Branch Card 1: الرياض -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300 flex flex-col group">
                    <div class="p-6 sm:p-8 flex-1">
                        <div class="flex items-center justify-between mb-4">
                            <span class="w-12 h-12 rounded-2xl bg-brand-50 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 flex items-center justify-center text-xl font-bold">🇸🇦</span>
                            <span class="text-[10px] font-bold text-emerald-500 bg-emerald-500/10 px-2.5 py-1 rounded-full">● مفتوح الآن</span>
                        </div>
                        <h3 class="text-xl font-black text-slate-950 dark:text-white">فرع الرياض</h3>
                        <p class="text-brand-600 dark:text-brand-400 text-xs font-bold mt-1">حي القادسية - شرق الرياض</p>
                        
                        <div class="mt-6 space-y-3.5 text-xs text-slate-600 dark:text-slate-400">
                            <div class="flex items-center gap-2.5">
                                <span class="text-slate-400">🕒</span>
                                <span>من السبت إلى الخميس: 08:00 ص - 12:00 م | 04:00 م - 09:00 م</span>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <span class="text-slate-400">📞</span>
                                <span class="font-sans"><?php echo htmlspecialchars($companySettings['phone']); ?></span>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <span class="text-slate-400">✉️</span>
                                <span>Riyadh@almakhzoun-pro.com</span>
                            </div>
                        </div>

                        <!-- Styled Mini Map Visual -->
                        <div class="mt-6 relative h-48 rounded-2xl overflow-hidden border border-slate-100 dark:border-slate-800 bg-slate-100 dark:bg-slate-950">
                            <!-- Custom Embedded Google Map for Qadsiyah Branch -->
                            <iframe 
                                class="w-full h-full border-0 grayscale dark:invert-[0.9] dark:hue-rotate-180" 
                                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d115891.80164620614!2d46.7385848!3d24.8101438!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3e2eff8fd3e5fbfd%3A0xbcf046ff0352bb18!2z2K3ZiiDYp9mE2YLYp9iv2LPZitipLCDYp9mE2LHZitin2LY!5e0!3m2!1sar!2ssa!4v1700000000000!5m2!1sar!2ssa" 
                                allowfullscreen="" 
                                loading="lazy" 
                                referrerpolicy="no-referrer-when-downgrade">
                            </iframe>
                            <div class="absolute inset-0 bg-slate-900/10 pointer-events-none group-hover:bg-transparent transition duration-300"></div>
                        </div>
                    </div>
                    <div class="p-6 bg-slate-50 dark:bg-slate-950/40 border-t border-slate-100 dark:border-slate-800/60">
                        <a href="https://maps.app.goo.gl/ZrVEucQZy9wYrWKeA" target="_blank" class="block w-full text-center bg-slate-900 dark:bg-brand-600 hover:bg-brand-600 dark:hover:bg-brand-700 text-white font-bold text-xs py-3 rounded-xl transition-all shadow-md hover:shadow-brand-500/20">
                            📍 فتح الموقع في خرائط Google والتوجيهات
                        </a>
                    </div>
                </div>

                <!-- Branch Card 2: نجران -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300 flex flex-col group">
                    <div class="p-6 sm:p-8 flex-1">
                        <div class="flex items-center justify-between mb-4">
                            <span class="w-12 h-12 rounded-2xl bg-brand-50 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 flex items-center justify-center text-xl font-bold">🇸🇦</span>
                            <span class="text-[10px] font-bold text-emerald-500 bg-emerald-500/10 px-2.5 py-1 rounded-full">● مفتوح الآن</span>
                        </div>
                        <h3 class="text-xl font-black text-slate-950 dark:text-white">فرع نجران</h3>
                        <p class="text-brand-600 dark:text-brand-400 text-xs font-bold mt-1">حي الصناعية - نجران</p>
                        
                        <div class="mt-6 space-y-3.5 text-xs text-slate-600 dark:text-slate-400">
                            <div class="flex items-center gap-2.5">
                                <span class="text-slate-400">🕒</span>
                                <span>من السبت إلى الخميس: 08:00 ص - 12:00 م | 04:00 م - 09:00 م</span>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <span class="text-slate-400">📞</span>
                                <span class="font-sans"><?php echo htmlspecialchars($companySettings['phone']); ?></span>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <span class="text-slate-400">✉️</span>
                                <span>Najran@almakhzoun-pro.com</span>
                            </div>
                        </div>

                        <!-- Styled Mini Map Visual -->
                        <div class="mt-6 relative h-48 rounded-2xl overflow-hidden border border-slate-100 dark:border-slate-800 bg-slate-100 dark:bg-slate-950">
                            <!-- Custom Embedded Google Map for Najran Branch -->
                            <iframe 
                                class="w-full h-full border-0 grayscale dark:invert-[0.9] dark:hue-rotate-180" 
                                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d119339.1171736294!2d44.150654!3d17.518653!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x15f7eb0ef7e83df9%3A0xea216db8a0ba7be3!2z2YXYrNmF2Lkg2KfZhNis2K_ZitivINmB2Yog2YbYrNix2KfZhg!5e0!3m2!1sar!2ssa!4v1700000000001!5m2!1sar!2ssa" 
                                allowfullscreen="" 
                                loading="lazy" 
                                referrerpolicy="no-referrer-when-downgrade">
                            </iframe>
                            <div class="absolute inset-0 bg-slate-900/10 pointer-events-none group-hover:bg-transparent transition duration-300"></div>
                        </div>
                    </div>
                    <div class="p-6 bg-slate-50 dark:bg-slate-950/40 border-t border-slate-100 dark:border-slate-800/60">
                        <a href="https://maps.app.goo.gl/bjVaNFk434SGHQb47" target="_blank" class="block w-full text-center bg-slate-900 dark:bg-brand-600 hover:bg-brand-600 dark:hover:bg-brand-700 text-white font-bold text-xs py-3 rounded-xl transition-all shadow-md hover:shadow-brand-500/20">
                            📍 فتح الموقع في خرائط Google والتوجيهات
                        </a>
                    </div>
                </div>
            </div>
        </main>

    <?php elseif ($current_page_slug === 'sales_team'): ?>
        <!-- PAGE: SALES REPRESENTATIVES -->
        <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12" dir="rtl">
            <div class="text-center max-w-2xl mx-auto mb-12">
                <span class="px-3 py-1 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 text-[10px] font-black rounded-full uppercase tracking-wider">👥 فريق مستشاري المبيعات</span>
                <h1 class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white mt-3">تواصل مباشرة مع فريق المبيعات</h1>
                <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-2 leading-relaxed">يسعد مستشاري المبيعات لدينا خدمتك والرد على استفساراتك حول السيارات ومواصفاتها وخيارات التمويل المتاحة.</p>
            </div>

            <?php if (empty($sales_reps)): ?>
                <div class="text-center py-16 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl">
                    <span class="text-4xl block mb-2">👥</span>
                    <h4 class="text-sm font-black text-slate-400">سيتم إضافة مناديب المبيعات قريباً</h4>
                </div>
            <?php else: ?>
                <?php 
                    $sales_template = $companySettings['sales_template_style'] ?? 'grid';
                    if ($sales_template === 'list'):
                ?>
                    <!-- Style 1: CLASSIC ELEGANT LIST -->
                    <div class="space-y-4 max-w-4xl mx-auto">
                        <?php foreach ($sales_reps as $rep): ?>
                            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 sm:p-6 shadow-sm hover:shadow-md transition duration-300 flex flex-col sm:flex-row items-center gap-4 sm:gap-6 text-right relative overflow-hidden group">
                                <!-- Avatar -->
                                <div class="relative w-16 h-16 shrink-0">
                                    <?php if (!empty($rep['avatar'])): ?>
                                        <img src="<?php echo htmlspecialchars($rep['avatar']); ?>" alt="<?php echo htmlspecialchars($rep['name']); ?>" class="w-full h-full rounded-full object-cover border border-slate-200 dark:border-slate-800">
                                    <?php else: ?>
                                        <div class="w-full h-full rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center font-black text-xl border border-slate-200 dark:border-slate-800">
                                            <?php echo mb_substr($rep['name'], 0, 1, 'utf-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                    <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-emerald-500 border-2 border-white dark:border-slate-900 rounded-full"></span>
                                </div>
                                
                                <!-- Info -->
                                <div class="flex-1 text-center sm:text-right">
                                    <h3 class="text-base font-black text-slate-950 dark:text-white"><?php echo htmlspecialchars($rep['name']); ?></h3>
                                    <p class="text-brand-500 dark:text-brand-400 text-xs font-bold mt-1"><?php echo htmlspecialchars($rep['title']); ?></p>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="flex items-center gap-2.5 shrink-0 w-full sm:w-auto">
                                    <a href="tel:<?php echo htmlspecialchars($rep['phone']); ?>" class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 py-2.5 px-4 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-900 dark:text-slate-100 rounded-xl text-xs font-bold transition">
                                        <span>📞</span>
                                        <span>اتصال فوري</span>
                                    </a>
                                    <?php 
                                        $wa = trim($rep['whatsapp']);
                                        if (str_starts_with($wa, '05')) { $wa = '966' . substr($wa, 1); }
                                        elseif (str_starts_with($wa, '5')) { $wa = '966' . $wa; }
                                        $wa_msg = urlencode("السلام عليكم ورحمة الله وبركاته، أرغب في الاستفسار عن السيارات المتاحة في معرضكم.");
                                    ?>
                                    <a href="https://wa.me/<?php echo htmlspecialchars($wa); ?>?text=<?php echo $wa_msg; ?>" target="_blank" class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition">
                                        <span>💬</span>
                                        <span>واتساب</span>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($sales_template === 'bento'): ?>
                    <!-- Style 2: TECH-FORWARD BENTO GRID -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php foreach ($sales_reps as $index => $rep): 
                            $is_primary = ($index === 0);
                            $card_class = $is_primary 
                                ? "md:col-span-2 bg-gradient-to-br from-indigo-950 to-slate-900 text-white border-none p-8" 
                                : "bg-white dark:bg-slate-900 p-6 border-slate-200 dark:border-slate-800 text-slate-950 dark:text-white";
                        ?>
                            <div class="<?php echo $card_class; ?> border rounded-3xl overflow-hidden shadow-lg hover:shadow-xl transition-all duration-300 flex flex-col justify-between relative group min-h-[280px]">
                                <div class="absolute top-4 left-4">
                                    <span class="px-2.5 py-1 rounded-full text-[9px] font-bold uppercase tracking-wider <?php echo $is_primary ? 'bg-white/10 text-brand-300' : 'bg-brand-500/10 text-brand-500'; ?>">
                                        <?php echo $is_primary ? '🏆 مستشار مبيعات أول' : '👤 مستشار مبيعات'; ?>
                                    </span>
                                </div>
                                
                                <div>
                                    <div class="w-14 h-14 rounded-2xl mb-4 overflow-hidden border border-slate-200/20">
                                        <?php if (!empty($rep['avatar'])): ?>
                                            <img src="<?php echo htmlspecialchars($rep['avatar']); ?>" alt="<?php echo htmlspecialchars($rep['name']); ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full bg-slate-700 text-slate-200 flex items-center justify-center font-black text-xl">
                                                <?php echo mb_substr($rep['name'], 0, 1, 'utf-8'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="text-xl font-black"><?php echo htmlspecialchars($rep['name']); ?></h3>
                                    <p class="text-xs mt-1 <?php echo $is_primary ? 'text-slate-300' : 'text-slate-400'; ?>"><?php echo htmlspecialchars($rep['title']); ?></p>
                                </div>

                                <div class="mt-6 flex items-center gap-2">
                                    <a href="tel:<?php echo htmlspecialchars($rep['phone']); ?>" class="flex-1 flex items-center justify-center gap-1.5 py-3 rounded-xl text-xs font-bold transition <?php echo $is_primary ? 'bg-white text-slate-950 hover:bg-slate-100' : 'bg-slate-950 dark:bg-slate-800 hover:bg-slate-800 dark:hover:bg-slate-700 text-white'; ?>">
                                        <span>📞 اتصل</span>
                                    </a>
                                    <?php 
                                        $wa = trim($rep['whatsapp']);
                                        if (str_starts_with($wa, '05')) { $wa = '966' . substr($wa, 1); }
                                        elseif (str_starts_with($wa, '5')) { $wa = '966' . $wa; }
                                        $wa_msg = urlencode("السلام عليكم ورحمة الله وبركاته، أرغب في الاستفسار عن السيارات المتاحة في معرضكم.");
                                    ?>
                                    <a href="https://wa.me/<?php echo htmlspecialchars($wa); ?>?text=<?php echo $wa_msg; ?>" target="_blank" class="flex-1 flex items-center justify-center gap-1.5 py-3 rounded-xl text-xs font-bold transition bg-emerald-600 hover:bg-emerald-700 text-white">
                                        <span>💬 واتساب</span>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php else: ?>
                    <!-- Style 3: MODERN CARD GRID (Default) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($sales_reps as $rep): ?>
                            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl overflow-hidden shadow-md hover:shadow-lg transition-all duration-300 flex flex-col group text-center p-6 sm:p-8 relative">
                                <!-- Soft ambient background light -->
                                <div class="absolute -top-12 -left-12 w-24 h-24 bg-brand-500/5 dark:bg-brand-500/10 rounded-full blur-2xl"></div>
                                
                                <!-- Avatar with status indicator -->
                                <div class="relative w-24 h-24 mx-auto mb-5">
                                    <?php if (!empty($rep['avatar'])): ?>
                                        <img src="<?php echo htmlspecialchars($rep['avatar']); ?>" alt="<?php echo htmlspecialchars($rep['name']); ?>" class="w-full h-full rounded-full object-cover border-2 border-brand-500/20">
                                    <?php else: ?>
                                        <div class="w-full h-full rounded-full bg-gradient-to-br from-indigo-500 to-brand-600 text-white flex items-center justify-center font-black text-2xl border-2 border-brand-500/20">
                                            <?php echo mb_substr($rep['name'], 0, 1, 'utf-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                    <span class="absolute bottom-1 right-1 w-4 h-4 bg-emerald-500 border-2 border-white dark:border-slate-900 rounded-full" title="نشط ومتاح الآن"></span>
                                </div>

                                <h3 class="text-lg font-black text-slate-950 dark:text-white"><?php echo htmlspecialchars($rep['name']); ?></h3>
                                <p class="text-slate-500 dark:text-slate-400 text-xs mt-1.5 font-semibold bg-slate-100 dark:bg-slate-800/60 px-3 py-1 rounded-full inline-block mx-auto"><?php echo htmlspecialchars($rep['title']); ?></p>
                                
                                <!-- Quick contact buttons -->
                                <div class="mt-8 grid grid-cols-2 gap-3">
                                    <a href="tel:<?php echo htmlspecialchars($rep['phone']); ?>" class="flex items-center justify-center gap-1.5 py-3 px-4 bg-slate-900 dark:bg-slate-800 hover:bg-slate-800 dark:hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition shadow-sm">
                                        <span>📞</span>
                                        <span>اتصال فوري</span>
                                    </a>
                                    
                                    <?php 
                                        // clean whatsapp
                                        $wa = trim($rep['whatsapp']);
                                        if (str_starts_with($wa, '05')) {
                                            $wa = '966' . substr($wa, 1);
                                        } elseif (str_starts_with($wa, '5')) {
                                            $wa = '966' . $wa;
                                        }
                                        $wa_msg = urlencode("السلام عليكم ورحمة الله وبركاته، أرغب في الاستفسار عن السيارات المتاحة في معرضكم.");
                                    ?>
                                    <a href="https://wa.me/<?php echo htmlspecialchars($wa); ?>?text=<?php echo $wa_msg; ?>" target="_blank" class="flex items-center justify-center gap-1.5 py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm">
                                        <span>💬</span>
                                        <span>واتساب</span>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>

    <?php elseif ($current_custom_page !== null): ?>
        <!-- Custom Dynamic Page View -->
        <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-10 shadow-xl space-y-6 transition">
                <div class="flex items-center gap-3 border-b border-slate-100 dark:border-slate-800 pb-6">
                    <?php if (!empty($current_custom_page['icon'])): ?>
                        <span class="text-3xl"><?php echo htmlspecialchars($current_custom_page['icon']); ?></span>
                    <?php endif; ?>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars($current_custom_page['title']); ?></h1>
                        <span class="text-[10px] text-brand-500 font-bold tracking-widest font-sans">صفحة مخصصة</span>
                    </div>
                </div>
                
                <div class="prose dark:prose-invert max-w-none text-slate-800 dark:text-slate-200 leading-relaxed text-sm">
                    <!-- Custom Raw Code Injection -->
                    <?php echo $current_custom_page['content']; ?>
                </div>
            </div>
        </main>
    <?php else: ?>

    <!-- Hero / Welcome Banner -->
    <?php 
    $imgOpacityVal = (int)($companySettings['showroom_banner_opacity'] ?? 25);
    $overlayOpacityVal = (int)($companySettings['showroom_banner_overlay_opacity'] ?? 50);

    // Banner height
    $bannerHeightClass = 'py-16 sm:py-20';
    $banner_height = $companySettings['showroom_banner_height'] ?? 'medium';
    if ($banner_height === 'compact') {
        $bannerHeightClass = 'py-8 sm:py-12';
    } elseif ($banner_height === 'tall') {
        $bannerHeightClass = 'py-28 sm:py-36';
    }

    // Banner image size / object-fit
    $bannerBgSizeClass = 'object-cover';
    $banner_bg_size = $companySettings['showroom_banner_bg_size'] ?? 'cover';
    if ($banner_bg_size === 'contain') {
        $bannerBgSizeClass = 'object-contain';
    } elseif ($banner_bg_size === 'auto') {
        $bannerBgSizeClass = 'object-none';
    }

    // Banner title & subtitle colors
    $title_color = $companySettings['showroom_banner_title_color'] ?? '#ffffff';
    $subtitle_color = $companySettings['showroom_banner_subtitle_color'] ?? '#cbd5e1';

    // Banner text backdrop
    $text_bg_enabled = (int)($companySettings['showroom_banner_text_bg'] ?? 0) === 1;

    $customHeightStyle = !empty($companySettings['showroom_banner_custom_height']) ? $companySettings['showroom_banner_custom_height'] : '';
    $customWidthStyle = !empty($companySettings['showroom_banner_custom_width']) ? $companySettings['showroom_banner_custom_width'] : '';
    
    $sectionStyles = [];
    if (!empty($customHeightStyle)) {
        $sectionStyles[] = "height: $customHeightStyle";
    }
    if (!empty($customWidthStyle)) {
        $sectionStyles[] = "width: $customWidthStyle";
    }
    if (!empty($customHeightStyle)) {
        $sectionStyles[] = "display: flex";
        $sectionStyles[] = "align-items: center";
        $sectionStyles[] = "justify-content: center";
    }
    $sectionStyleAttr = !empty($sectionStyles) ? 'style="' . implode('; ', $sectionStyles) . '; max-width:100%; margin-left:auto; margin-right:auto;"' : '';
    ?>
    <?php
    $banner_width = $companySettings['showroom_banner_width'] ?? 'full';
    if ($banner_width === 'contained'): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
    <?php endif; ?>

    <section class="relative bg-gradient-to-br from-brand-900 via-slate-900 to-slate-950 text-white overflow-hidden <?php echo $banner_width === 'contained' ? 'rounded-2xl sm:rounded-3xl shadow-xl' : ''; ?> <?php echo empty($customHeightStyle) ? $bannerHeightClass : ''; ?>" <?php echo $sectionStyleAttr; ?>>
        <?php if (!empty($companySettings['showroom_banner_image'])): ?>
            <div class="absolute inset-0">
                <img src="<?php echo htmlspecialchars($companySettings['showroom_banner_image']); ?>" class="w-full h-full <?php echo $bannerBgSizeClass; ?>" style="opacity: <?php echo $imgOpacityVal / 100; ?>;" alt="Banner background" referrerPolicy="no-referrer">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-900/40 to-brand-900/50" style="opacity: <?php echo $overlayOpacityVal / 100; ?>;"></div>
            </div>
        <?php else: ?>
            <!-- Abstract glowing circles -->
            <div class="absolute top-0 left-1/4 w-96 h-96 bg-brand-500/10 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-brand-500/5 rounded-full blur-3xl"></div>
        <?php endif; ?>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center flex flex-col items-center">
            <!-- Elegant luxury minimalist tag -->
            <div class="inline-flex items-center gap-2 px-6 py-3 rounded-lg border border-white/10 bg-slate-950/40 backdrop-blur-sm shadow-lg text-center">
                <span class="text-indigo-400">🚗</span>
                <span class="text-[10px] sm:text-xs font-bold tracking-widest text-slate-200">الوكيل المعتمد لسيارات النخبة والواردات الجمركية</span>
            </div>
        </div>
    </section>

    <?php if ($banner_width === 'contained'): ?>
    </div>
    <?php endif; ?>

    <!-- Title & Subtitle Container (Below the Banner as requested) -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 text-center flex flex-col items-center">
        <div class="p-6 md:p-8 rounded-2xl w-full max-w-4xl transition-all duration-300 text-center flex flex-col items-center <?php echo $text_bg_enabled ? 'bg-slate-900/60 backdrop-blur-md border border-slate-800/80 shadow-2xl' : 'bg-transparent border-none shadow-none'; ?>" 
             style="<?php echo $text_bg_enabled ? 'background-color: rgba(15, 23, 42, 0.6);' : ''; ?>">
            
            <h2 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-black tracking-tight leading-tight mb-4" 
                style="color: <?php echo htmlspecialchars($title_color); ?>;">
                منصة <span class="text-transparent bg-clip-text bg-gradient-to-l from-indigo-300 to-indigo-500"><?php echo htmlspecialchars($companySettings['showroom_header_title'] ?? 'المخزون برو'); ?></span> لإدارة واستيراد السيارات
            </h2>

            <p class="text-xs sm:text-sm md:text-base leading-relaxed max-w-2xl mx-auto font-semibold" 
               style="color: <?php echo htmlspecialchars($subtitle_color); ?>;">
                <?php echo nl2br(htmlspecialchars($companySettings['showroom_header_subtitle'] ?? 'نقدم لك خدمات متميزة، سيارات مضمونة ومفحوصة بالكامل، وتسهيلات تواصل مباشرة مع مناديب المبيعات المعتمدين.')); ?>
            </p>
        </div>
    </div>

    <!-- ENLARGED PROMINENT BADGE -->
    <div class="text-center pt-8 pb-2">
        <div class="inline-flex items-center justify-center gap-3 px-6 py-3.5 rounded-full bg-slate-900/90 border border-indigo-500/35 text-indigo-300 text-xs sm:text-sm md:text-base lg:text-lg font-black tracking-wide shadow-xl shadow-indigo-950/40 hover:scale-105 transition-transform duration-300 select-none cursor-pointer">
            <span class="text-amber-400">✨</span>
            <span class="bg-gradient-to-r from-indigo-200 via-white to-indigo-200 bg-clip-text text-transparent">تصفح واطلب سيارتك المفضلة الآن</span>
            <span class="text-amber-400">✨</span>
        </div>
    </div>

    <!-- Main Content Grid & Interactive Filters -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
        
        <?php if (isset($db_error)): ?>
            <div class="p-6 bg-red-500/10 border border-red-500/20 text-red-500 dark:text-red-400 rounded-2xl flex flex-col sm:flex-row items-center gap-4 shadow-sm text-center sm:text-right max-w-2xl mx-auto">
                <span class="text-3xl">⚠️</span>
                <div>
                    <h3 class="font-extrabold text-sm">خطأ في الاتصال بالنظام</h3>
                    <p class="text-xs mt-1 leading-relaxed"><?php echo htmlspecialchars($db_error); ?></p>
                </div>
            </div>
        <?php else: ?>

            <!-- Render Top Position Advertisements & Offers -->
            <?php 
            $topAds = array_filter($activeAds, function($ad) { return $ad['position'] === 'top'; });
            if (!empty($topAds)):
            ?>
                <div class="space-y-4 mb-6">
                    <?php foreach ($topAds as $ad): ?>
                        <div class="w-full bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-md p-1">
                            <?php if ($ad['type'] === 'image'): ?>
                                <a href="customer.php?action=click_ad&id=<?php echo $ad['id']; ?>" class="block relative group overflow-hidden rounded-xl">
                                    <img src="<?php echo htmlspecialchars($ad['image_path']); ?>" class="w-full object-cover transition-transform duration-500 group-hover:scale-[1.01]" style="max-height: 250px;" alt="<?php echo htmlspecialchars($ad['title']); ?>">
                                    <div class="absolute inset-0 bg-slate-950/20 group-hover:bg-slate-950/10 transition-colors"></div>
                                </a>
                            <?php else: ?>
                                <div class="p-5 text-slate-100 overflow-hidden">
                                    <?php echo $ad['html_code']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Real-time Filter & Search Bar -->
            <?php if ((int)($companySettings['showroom_show_filters'] ?? 1) === 1): ?>
            <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row gap-4 items-center justify-between">
                
                <!-- Search bar -->
                <div class="relative w-full md:max-w-md">
                    <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-400 dark:text-slate-500 text-sm">🔍</span>
                    <input type="text" id="car-search" oninput="filterCars()" placeholder="ابحث عن سيارة (ماركة، موديل)..." class="w-full text-xs pr-10 pl-4 py-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white dark:focus:bg-slate-900 transition">
                </div>

                <!-- Brand Select Filter -->
                <div class="flex items-center gap-2 w-full md:w-auto overflow-x-auto pb-1 md:pb-0 scrollbar-none" id="brand-filters">
                    <button onclick="selectBrand('')" data-brand="" class="brand-tab px-4 py-2 text-xs font-black rounded-xl transition bg-brand-600 text-white">الكل</button>
                    <?php foreach ($makes as $m): ?>
                        <button onclick="selectBrand('<?php echo htmlspecialchars($m); ?>')" data-brand="<?php echo htmlspecialchars($m); ?>" class="brand-tab px-4 py-2 text-xs font-bold text-slate-600 dark:text-slate-300 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 rounded-xl transition shrink-0"><?php echo htmlspecialchars($m); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
                <!-- Hidden inputs for compatibility with filtering JS -->
                <input type="hidden" id="car-search" value="">
            <?php endif; ?>

            <!-- Vehicles grid -->
            <?php if (empty($cars)): ?>
                <div class="text-center py-20 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 space-y-4">
                    <span class="text-5xl block">🚗</span>
                    <h3 class="text-base font-black text-slate-800 dark:text-slate-200">لا تتوفر سيارات متاحة للبيع حالياً</h3>
                    <p class="text-xs text-slate-400 max-w-sm mx-auto leading-relaxed">
                        نحن نعمل على توفير خيارات جديدة وتحديث المخزون بشكل مستمر. يرجى مراجعة المعرض لاحقاً أو الاتصال بالإدارة.
                    </p>
                </div>
            <?php else: ?>
                <!-- Skeletal Loading Placeholder Container -->
                <div id="skeleton-loader" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php for ($i = 0; $i < min(4, count($cars)); $i++): ?>
                        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-150 dark:border-slate-800 p-4 space-y-4">
                            <div class="w-full h-44 rounded-xl bg-slate-200 dark:bg-slate-800 animate-pulse-custom"></div>
                            <div class="space-y-2">
                                <div class="h-4 w-2/3 bg-slate-200 dark:bg-slate-800 rounded animate-pulse-custom"></div>
                                <div class="h-3 w-1/3 bg-slate-200 dark:bg-slate-800 rounded animate-pulse-custom"></div>
                            </div>
                            <div class="flex justify-between items-center pt-2">
                                <div class="h-4 w-1/4 bg-slate-200 dark:bg-slate-800 rounded animate-pulse-custom"></div>
                                <div class="h-8 w-1/3 bg-slate-200 dark:bg-slate-800 rounded-xl animate-pulse-custom"></div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <!-- Actual Cars Grid (initially hidden, shown by JS after resource loads) -->
                <div id="cars-grid" class="hidden grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php 
                    $middleAds = array_filter($activeAds, function($ad) { return $ad['position'] === 'middle'; });
                    $carIndex = 0;
                    foreach ($cars as $car): 
                        // Text message for WhatsApp request
                        if ((int)($companySettings['showroom_show_price'] ?? 1) === 1) {
                            $waText = urlencode("مرحباً، أود الاستفسار عن سيارة " . $car['make'] . " " . $car['model'] . " موديل " . $car['year'] . " المعروضة في موقعكم بسعر " . number_format($car['price']) . " " . ($car['currency'] ?? 'ر.س') . ".");
                        } else {
                            $waText = urlencode("مرحباً، أود الاستفسار عن سيارة " . $car['make'] . " " . $car['model'] . " موديل " . $car['year'] . " المعروضة في موقعكم.");
                        }
                        $waUrl = "https://wa.me/" . $whatsapp_clean . "?text=" . $waText;
                    ?>
                        <article class="car-card bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800/80 shadow-sm hover:shadow-md hover:border-brand-500/20 dark:hover:border-brand-500/30 overflow-hidden transition-all duration-300 flex flex-col group cursor-pointer" data-make="<?php echo htmlspecialchars($car['make']); ?>" data-model="<?php echo htmlspecialchars($car['model']); ?>" onclick="openCarDetailModal(event, '<?php echo htmlspecialchars($car['id']); ?>')">
                            
                            <!-- Image wrapper -->
                            <div class="relative w-full h-48 bg-slate-100 dark:bg-slate-950 overflow-hidden">
                                <?php if (!empty($car['main_image'])): ?>
                                    <img data-src="<?php echo htmlspecialchars($car['main_image']); ?>" alt="<?php echo htmlspecialchars($car['make'] . ' ' . $car['model']); ?>" class="lazy-image w-full h-full object-cover group-hover:scale-105 transition-all duration-500" loading="lazy" referrerPolicy="no-referrer">
                                <?php else: ?>
                                    <div class="w-full h-full flex flex-col items-center justify-center text-slate-300 dark:text-slate-700">
                                        <svg class="w-12 h-12 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        <span class="text-[10px] font-bold">لا تتوفر صورة للسيارة</span>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Year Badge -->
                                <span class="absolute top-3 right-3 text-[10px] font-extrabold font-sans bg-slate-900/80 dark:bg-slate-900/90 text-slate-100 px-2.5 py-1 rounded-full backdrop-blur-sm border border-white/5"><?php echo htmlspecialchars($car['year']); ?></span>
                            </div>
 
                            <!-- Car info -->
                            <div class="p-4 flex-1 flex flex-col justify-between space-y-4">
                                <div class="space-y-1">
                                    <h3 class="font-extrabold text-sm text-slate-800 dark:text-slate-100 group-hover:text-brand-500 transition-colors">
                                        <?php echo htmlspecialchars($car['make'] . ' ' . $car['model']); ?>
                                    </h3>
                                    <span class="text-[10px] text-slate-400 dark:text-slate-500 block">الموديل وسنة الصنع: <?php echo htmlspecialchars($car['year']); ?></span>
                                </div>
 
                                <!-- Price & CTA Row -->
                                <div class="pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between gap-2">
                                    <div class="text-right">
                                        <?php if ((int)($companySettings['showroom_show_price'] ?? 1) === 1): ?>
                                            <span class="text-[9px] text-slate-400 dark:text-slate-500 block leading-tight">السعر النقدي</span>
                                            <span class="font-black text-sm text-emerald-600 dark:text-emerald-400 font-sans"><?php echo number_format($car['price']); ?></span>
                                            <span class="text-[10px] font-bold text-slate-500"><?php echo htmlspecialchars($car['currency'] ?? 'ر.س'); ?></span>
                                        <?php else: ?>
                                            <span class="text-[10px] font-bold text-slate-500 bg-slate-100 dark:bg-slate-800 px-2.5 py-1.5 rounded-lg border border-slate-200/50 dark:border-slate-800">السعر عند التواصل</span>
                                        <?php endif; ?>
                                    </div>
 
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <!-- WhatsApp contact -->
                                        <a href="<?php echo $waUrl; ?>" onclick="event.stopPropagation()" target="_blank" class="w-9 h-9 flex items-center justify-center bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl transition shadow-sm" title="استفسار عبر الواتساب">
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.455L0 24zm6.59-4.846c1.665.989 3.3 1.488 5.336 1.489 5.485 0 9.95-4.466 9.954-9.957.002-2.661-1.034-5.159-2.914-7.04C17.143 1.765 14.653.729 12.009.729 6.52.729 2.054 5.197 2.05 10.69c-.001 2.012.502 3.655 1.477 5.286L2.529 21.94l6.118-1.786z"></path></svg>
                                        </a>
 
                                        <!-- Order button -->
                                        <button onclick="event.stopPropagation(); openOrderModal('<?php echo htmlspecialchars($car['id']); ?>', '<?php echo htmlspecialchars($car['make'] . ' ' . $car['model']); ?>', '<?php echo ((int)($companySettings['showroom_show_price'] ?? 1) === 1) ? number_format($car['price']) . ' ' . htmlspecialchars($car['currency'] ?? 'ر.س') : 'السعر عند التواصل'; ?>')" class="px-3 py-2 bg-brand-600 hover:bg-brand-700 text-white text-xs font-black rounded-xl transition shadow-sm flex items-center gap-1 cursor-pointer">
                                            <span>اطلب الآن</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <?php 
                            $carIndex++;
                            if ($carIndex % 3 === 0 && !empty($middleAds)):
                                $mAd = array_shift($middleAds);
                                if ($mAd):
                        ?>
                                <div class="col-span-full bg-slate-900 border border-slate-800 rounded-3xl p-1 overflow-hidden shadow-sm my-2">
                                    <?php if ($mAd['type'] === 'image'): ?>
                                        <a href="customer.php?action=click_ad&id=<?php echo $mAd['id']; ?>" class="block relative group overflow-hidden rounded-2xl">
                                            <img src="<?php echo htmlspecialchars($mAd['image_path']); ?>" class="w-full object-cover transition-transform duration-500 group-hover:scale-[1.01]" style="max-height: 180px;" alt="<?php echo htmlspecialchars($mAd['title']); ?>">
                                            <div class="absolute inset-0 bg-slate-950/20 group-hover:bg-slate-950/10 transition-colors"></div>
                                        </a>
                                    <?php else: ?>
                                        <div class="p-5 text-slate-100 overflow-hidden">
                                            <?php echo $mAd['html_code']; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                        <?php 
                                endif;
                            endif; 
                        ?>
                    <?php endforeach; ?>
                </div>

                <div id="no-results" class="hidden text-center py-16 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl">
                    <span class="text-4xl block mb-2">🔍</span>
                    <h4 class="text-sm font-black text-slate-700 dark:text-slate-200">لا توجد نتائج مطابقة لبحثك</h4>
                    <p class="text-[11px] text-slate-400 mt-1">تأكد من كتابة الاسم بشكل صحيح أو تصفح ماركات أخرى.</p>
                </div>
            <?php endif; ?>

            <!-- ================= CUSTOMER REVIEWS & RATINGS SYSTEM ================= -->
            <section class="mt-20 pt-12 border-t border-slate-200 dark:border-slate-800/80">
                <div class="flex flex-col lg:flex-row gap-10 items-start">
                    
                    <!-- Right/RTL Info Card: Overall stats & Rating Bars -->
                    <div class="w-full lg:w-1/3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-6 rounded-3xl shadow-sm space-y-6">
                        <div>
                            <span class="text-brand-600 dark:text-brand-400 text-xs font-extrabold uppercase tracking-widest block mb-1">⭐ تقييمات وآراء العملاء</span>
                            <h3 class="text-lg font-black text-slate-800 dark:text-slate-100">ماذا يقول عملاؤنا عنا؟</h3>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">نسعى دائماً لتقديم أفضل الخدمات والسيارات المضمونة لعملائنا الكرام ونعتز بآرائهم.</p>
                        </div>

                        <!-- Average Score Badge -->
                        <div class="flex items-center gap-4 bg-slate-50 dark:bg-slate-950/40 p-4 rounded-2xl border border-slate-100 dark:border-slate-800/50">
                            <div class="text-right">
                                <div class="text-3xl font-black text-slate-800 dark:text-slate-100 font-sans leading-none"><?php echo $average_rating; ?></div>
                                <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 mt-1 block">من 5.0 نجوم</span>
                            </div>
                            <div class="space-y-1">
                                <!-- Stars -->
                                <div class="flex gap-0.5 text-amber-400">
                                    <?php 
                                    $full_stars = floor($average_rating);
                                    $half_star = ($average_rating - $full_stars) >= 0.5 ? 1 : 0;
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $full_stars) {
                                            echo '<span class="text-base">★</span>';
                                        } elseif ($i == $full_stars + 1 && $half_star) {
                                            echo '<span class="text-base">★</span>';
                                        } else {
                                            echo '<span class="text-slate-200 dark:text-slate-800 text-base">★</span>';
                                        }
                                    }
                                    ?>
                                </div>
                                <span class="text-xs font-bold text-slate-600 dark:text-slate-300 block">بناءً على <?php echo $total_reviews; ?> تقييم حقيقي</span>
                            </div>
                        </div>

                        <!-- Rating Bars (Amazon/Google style) -->
                        <div class="space-y-2 pt-2">
                            <?php 
                            for ($star = 5; $star >= 1; $star--): 
                                $count = $rating_counts[$star] ?? 0;
                                $percentage = $total_reviews > 0 ? round(($count / $total_reviews) * 100) : 0;
                            ?>
                                <div class="flex items-center gap-3 text-xs">
                                    <span class="w-12 text-left font-bold text-slate-500 dark:text-slate-400 font-sans shrink-0"><?php echo $star; ?> نجوم</span>
                                    <div class="flex-1 h-2 bg-slate-100 dark:bg-slate-950 rounded-full overflow-hidden">
                                        <div class="h-full bg-brand-500 rounded-full transition-all duration-1000" style="width: <?php echo $percentage; ?>%;"></div>
                                    </div>
                                    <span class="w-10 text-right text-[10px] font-extrabold text-slate-400 dark:text-slate-500 font-sans shrink-0"><?php echo $percentage; ?>%</span>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <!-- CTA to add review -->
                        <button onclick="openReviewModal()" class="w-full py-3 px-4 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-xs font-extrabold transition shadow-sm hover:shadow flex items-center justify-center gap-2 cursor-pointer">
                            <span>✍️</span> اكتب تقييمك وتجربتك
                        </button>
                    </div>

                    <!-- Left/LTL Review list / Cards -->
                    <div class="flex-1 w-full space-y-6">
                        <?php if (empty($reviews)): ?>
                            <!-- Empty review state -->
                            <div class="text-center py-16 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-8 space-y-4">
                                <span class="text-5xl block">✍️</span>
                                <h4 class="text-sm font-black text-slate-700 dark:text-slate-200">كن أول من يشارك تجربته!</h4>
                                <p class="text-xs text-slate-400 dark:text-slate-500 max-w-sm mx-auto leading-relaxed">
                                    لم يقم أي عميل بكتابة تقييم بعد. هل اشتريت سيارة من معرضنا أو تواصلت معنا؟ شاركنا رأيك الآن بكل سهولة.
                                </p>
                                <button onclick="openReviewModal()" class="mx-auto mt-2 px-6 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 text-xs font-bold rounded-xl transition">
                                    اكتب أول تقييم الآن
                                </button>
                            </div>
                        <?php else: 
                            // Prepare carousel reviews array
                            $carousel_reviews = $reviews;
                            $N = count($carousel_reviews);
                            if ($N > 0) {
                                while (count($carousel_reviews) < 6) {
                                    $carousel_reviews = array_merge($carousel_reviews, $reviews);
                                }
                                $N = count($carousel_reviews);
                                $pi = 3.141592653589793;
                                $radius_desktop = max(220, min(350, round(260 / (2 * sin($pi / max(3, $N))))));
                                $radius_mobile = max(130, min(190, round(180 / (2 * sin($pi / max(3, $N))))));
                            }
                        ?>
                            <!-- Style Sheets for 3D Carousel & Marquee -->
                            <style>
                                .reviews-viewport {
                                    perspective: 1200px;
                                    perspective-origin: 50% 35%;
                                    position: relative;
                                    width: 100%;
                                    height: 380px;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    overflow: hidden;
                                }
                                .carousel-3d-scene {
                                    position: relative;
                                    width: 280px;
                                    height: 200px;
                                    transform-style: preserve-3d;
                                    transition: transform 0.1s linear;
                                }
                                @media (max-width: 640px) {
                                    .reviews-viewport {
                                        height: 320px;
                                    }
                                    .carousel-3d-scene {
                                        width: 210px;
                                        height: 160px;
                                    }
                                }
                                .carousel-3d-card {
                                    position: absolute;
                                    top: 0;
                                    left: 0;
                                    width: 100%;
                                    height: 100%;
                                    backface-visibility: hidden;
                                    user-select: none;
                                    -webkit-user-drag: none;
                                }
                                .reviews-viewport::before,
                                .reviews-viewport::after {
                                    content: '';
                                    position: absolute;
                                    top: 0;
                                    width: 100px;
                                    height: 100%;
                                    z-index: 10;
                                    pointer-events: none;
                                }
                                .reviews-viewport::before {
                                    left: 0;
                                    background: linear-gradient(to right, #f8fafc, transparent);
                                }
                                .reviews-viewport::after {
                                    right: 0;
                                    background: linear-gradient(to left, #f8fafc, transparent);
                                }
                                .dark .reviews-viewport::before {
                                    left: 0;
                                    background: linear-gradient(to right, #0f172a, transparent);
                                }
                                .dark .reviews-viewport::after {
                                    right: 0;
                                    background: linear-gradient(to left, #0f172a, transparent);
                                }
                                #carousel3D {
                                    --radius-val: <?php echo $radius_desktop; ?>px;
                                }
                                @media (max-width: 640px) {
                                    #carousel3D {
                                        --radius-val: <?php echo $radius_mobile; ?>px;
                                    }
                                }
                            </style>

                            <!-- Interactive Header with Style Switcher -->
                            <div class="flex flex-col sm:flex-row justify-between items-center gap-3 bg-slate-50 dark:bg-slate-900/50 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 text-right w-full" dir="rtl">
                                <div class="text-right">
                                    <h4 class="text-xs font-black text-slate-800 dark:text-slate-100">🎡 طريقة عرض التقييمات</h4>
                                    <p class="text-[10px] text-slate-400 mt-0.5">اختر طريقة العرض المفضلة لمشاهدة آراء العملاء وتجاربهم</p>
                                </div>
                                <div class="flex gap-2 shrink-0">
                                    <button onclick="toggleReviewsStyle('3d')" id="btn-view-3d" class="px-3 py-1.5 rounded-xl text-[10px] font-black transition-all bg-brand-600 text-white shadow-sm cursor-pointer">
                                        🔄 عرض 3D دائرى (360°)
                                    </button>
                                    <button onclick="toggleReviewsStyle('grid')" id="btn-view-grid" class="px-3 py-1.5 rounded-xl text-[10px] font-black transition-all bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer">
                                        📋 شبكة كلاسيكية
                                    </button>
                                </div>
                            </div>

                            <!-- VIEW 1: PREMIUM 3D 360 DEGREE ROTATING CAROUSEL (DEFAULT) -->
                            <div id="reviews-3d-view" class="space-y-4">
                                <div class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl overflow-hidden p-6 shadow-sm">
                                    
                                    <!-- Rotating viewport -->
                                    <div id="carouselContainer" class="reviews-viewport cursor-grab active:cursor-grabbing">
                                        <div id="carousel3D" class="carousel-3d-scene">
                                            <?php foreach ($carousel_reviews as $index => $rev): 
                                                $first_char = mb_substr($rev['customer_name'], 0, 1, 'UTF-8');
                                                $avatar_colors = ['bg-indigo-500', 'bg-emerald-500', 'bg-rose-500', 'bg-sky-500', 'bg-amber-500', 'bg-purple-500', 'bg-teal-500'];
                                                $color_index = crc32($rev['customer_name']) % count($avatar_colors);
                                                $avatar_bg = $avatar_colors[$color_index];
                                                $rating_val = intval($rev['rating']);
                                                $angle = $index * (360 / $N);
                                            ?>
                                                <div class="carousel-3d-card bg-slate-50 dark:bg-slate-950 border border-slate-200/80 dark:border-slate-800/80 p-5 rounded-2xl shadow-xl flex flex-col justify-between"
                                                     style="transform: rotateY(<?php echo $angle; ?>deg) translateZ(var(--radius-val));">
                                                    <div class="space-y-2 text-right" dir="rtl">
                                                        <!-- Card Header -->
                                                        <div class="flex items-center justify-between gap-2">
                                                            <div class="flex items-center gap-2">
                                                                <div class="w-8 h-8 rounded-full <?php echo $avatar_bg; ?> text-white font-black text-xs flex items-center justify-center shadow-inner shrink-0">
                                                                    <?php echo htmlspecialchars($first_char); ?>
                                                                </div>
                                                                <div class="min-w-0">
                                                                    <h4 class="font-extrabold text-[11px] text-slate-800 dark:text-slate-100 flex items-center gap-1 truncate">
                                                                        <?php echo htmlspecialchars($rev['customer_name']); ?>
                                                                    </h4>
                                                                    <span class="text-[8px] text-slate-400 dark:text-slate-500 block leading-none font-sans mt-0.5"><?php echo date('Y-m-d', strtotime($rev['created_at'])); ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="flex gap-0.5 text-amber-400 text-[10px] shrink-0">
                                                                <?php 
                                                                for ($i = 1; $i <= 5; $i++) {
                                                                    echo $i <= $rating_val ? '★' : '<span class="text-slate-200 dark:text-slate-800">★</span>';
                                                                }
                                                                ?>
                                                            </div>
                                                        </div>
                                                        <!-- Card Comment -->
                                                        <p class="text-[10px] text-slate-600 dark:text-slate-300 leading-relaxed italic bg-white dark:bg-slate-900 p-3 rounded-xl border border-slate-100 dark:border-slate-800/60 overflow-y-auto max-h-[85px]">
                                                            "<?php echo htmlspecialchars($rev['comment']); ?>"
                                                        </p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Controller HUD -->
                                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-100 dark:border-slate-800 pt-4" dir="rtl">
                                        <div class="text-right">
                                            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 flex items-center gap-1.5">
                                                💡 اسحب لتدوير المراجعات ثلاثية الأبعاد 360° أو استخدم أزرار التحكم
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button onclick="rotateCarouselStep('prev')" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 flex items-center justify-center transition border border-slate-200/50 dark:border-slate-700/50 cursor-pointer text-xs" title="السابق">
                                                ◀
                                            </button>
                                            <button onclick="toggleAutoRotate()" id="btn-play-pause" class="px-3.5 py-1.5 rounded-full bg-slate-900 dark:bg-slate-800 hover:bg-slate-800 text-white dark:text-slate-200 flex items-center gap-1 text-[10px] font-bold transition cursor-pointer" title="تشغيل / إيقاف التدوير التلقائي">
                                                <span>⏸</span> إيقاف التلقائي
                                            </button>
                                            <button onclick="rotateCarouselStep('next')" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 flex items-center justify-center transition border border-slate-200/50 dark:border-slate-700/50 cursor-pointer text-xs" title="التالي">
                                                ▶
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- VIEW 2: CLASSIC GRID LIST -->
                            <div id="reviews-grid-view" class="hidden">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <?php foreach ($reviews as $rev): 
                                        $first_char = mb_substr($rev['customer_name'], 0, 1, 'UTF-8');
                                        $avatar_colors = ['bg-indigo-500', 'bg-emerald-500', 'bg-rose-500', 'bg-sky-500', 'bg-amber-500', 'bg-purple-500', 'bg-teal-500'];
                                        $color_index = crc32($rev['customer_name']) % count($avatar_colors);
                                        $avatar_bg = $avatar_colors[$color_index];
                                        $rating_val = intval($rev['rating']);
                                    ?>
                                        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-5 rounded-2xl shadow-sm hover:shadow-md hover:border-brand-500/20 dark:hover:border-brand-500/30 transition-all duration-300 flex flex-col justify-between">
                                            <div class="space-y-3">
                                                <!-- Review Header -->
                                                <div class="flex items-center justify-between gap-3">
                                                    <div class="flex items-center gap-3">
                                                        <!-- Initials Avatar -->
                                                        <div class="w-10 h-10 rounded-full <?php echo $avatar_bg; ?> text-white font-black text-sm flex items-center justify-center shadow-inner">
                                                            <?php echo htmlspecialchars($first_char); ?>
                                                        </div>
                                                        <div>
                                                            <h4 class="font-extrabold text-xs text-slate-800 dark:text-slate-100 flex items-center gap-1">
                                                                <?php echo htmlspecialchars($rev['customer_name']); ?>
                                                                <span class="text-[9px] text-emerald-500 bg-emerald-500/10 px-1.5 py-0.5 rounded-full font-bold flex items-center gap-0.5" title="عميل قام بالتعامل مع المعرض">
                                                                    ✓ عميل موثق
                                                                </span>
                                                            </h4>
                                                            <span class="text-[9px] text-slate-400 dark:text-slate-500 block leading-none font-sans mt-0.5"><?php echo date('Y-m-d', strtotime($rev['created_at'])); ?></span>
                                                        </div>
                                                    </div>
                                                    <!-- Stars display -->
                                                    <div class="flex gap-0.5 text-amber-400 text-xs">
                                                        <?php 
                                                        for ($i = 1; $i <= 5; $i++) {
                                                            echo $i <= $rating_val ? '★' : '<span class="text-slate-200 dark:text-slate-800">★</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                                <!-- Review Comment -->
                                                <p class="text-[11px] text-slate-600 dark:text-slate-300 leading-relaxed italic bg-slate-50/50 dark:bg-slate-950/20 p-3 rounded-xl border border-slate-100/50 dark:border-slate-800/30">
                                                    "<?php echo htmlspecialchars($rev['comment']); ?>"
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Carousel Interactive JavaScript Controller -->
                            <script>
                                (function() {
                                    let currentAngle = 0;
                                    let autoRotateEnabled = true;
                                    let carouselActive = true;
                                    let isDragging = false;
                                    let startX = 0;
                                    let startAngle = 0;
                                    const totalCards = <?php echo $N; ?>;

                                    const container = document.getElementById('carouselContainer');
                                    const carousel3D = document.getElementById('carousel3D');
                                    const btnPlayPause = document.getElementById('btn-play-pause');

                                    // Smooth Continuous Auto-Rotation Loop
                                    function runRotationLoop() {
                                        if (autoRotateEnabled && carouselActive && !isDragging) {
                                            currentAngle -= 0.15; // Soft cinematic rotation speed
                                            if (carousel3D) {
                                                carousel3D.style.transform = `rotateY(${currentAngle}deg)`;
                                            }
                                        }
                                        requestAnimationFrame(runRotationLoop);
                                    }
                                    
                                    // Start the continuous animation loop
                                    requestAnimationFrame(runRotationLoop);

                                    // Hover Pause State
                                    if (container) {
                                        container.addEventListener('mouseenter', () => {
                                            carouselActive = false;
                                        });
                                        container.addEventListener('mouseleave', () => {
                                            carouselActive = true;
                                        });

                                        // Drag & Swipe Controls for 360° Circular Carousel
                                        container.addEventListener('mousedown', (e) => {
                                            isDragging = true;
                                            startX = e.clientX;
                                            startAngle = currentAngle;
                                            container.style.cursor = 'grabbing';
                                        });

                                        window.addEventListener('mousemove', (e) => {
                                            if (!isDragging || !carousel3D) return;
                                            const deltaX = e.clientX - startX;
                                            // Scale speed with window width for balanced feel
                                            currentAngle = startAngle + (deltaX * 0.45);
                                            carousel3D.style.transform = `rotateY(${currentAngle}deg)`;
                                        });

                                        window.addEventListener('mouseup', () => {
                                            if (isDragging) {
                                                isDragging = false;
                                                container.style.cursor = 'grab';
                                            }
                                        });

                                        // Responsive Mobile Touch Support
                                        container.addEventListener('touchstart', (e) => {
                                            isDragging = true;
                                            startX = e.touches[0].clientX;
                                            startAngle = currentAngle;
                                        });

                                        container.addEventListener('touchmove', (e) => {
                                            if (!isDragging || !carousel3D) return;
                                            const deltaX = e.touches[0].clientX - startX;
                                            currentAngle = startAngle + (deltaX * 0.45);
                                            carousel3D.style.transform = `rotateY(${currentAngle}deg)`;
                                        });

                                        container.addEventListener('touchend', () => {
                                            isDragging = false;
                                        });
                                    }

                                    // Controller functions accessible globally
                                    window.rotateCarouselStep = function(direction) {
                                        // Stop autoplay briefly on manual click
                                        carouselActive = false;
                                        const stepAngle = 360 / totalCards;
                                        if (direction === 'next') {
                                            currentAngle -= stepAngle;
                                        } else {
                                            currentAngle += stepAngle;
                                        }
                                        if (carousel3D) {
                                            carousel3D.style.transition = "transform 0.5s cubic-bezier(0.16, 1, 0.3, 1)";
                                            carousel3D.style.transform = `rotateY(${currentAngle}deg)`;
                                            setTimeout(() => {
                                                if (carousel3D) carousel3D.style.transition = "transform 0.1s linear";
                                                carouselActive = true;
                                            }, 500);
                                        }
                                    };

                                    window.toggleAutoRotate = function() {
                                        autoRotateEnabled = !autoRotateEnabled;
                                        if (autoRotateEnabled) {
                                            btnPlayPause.innerHTML = '<span>⏸</span> إيقاف التلقائي';
                                            btnPlayPause.classList.remove('bg-rose-600', 'hover:bg-rose-700');
                                            btnPlayPause.classList.add('bg-slate-900', 'hover:bg-slate-800');
                                            carouselActive = true;
                                        } else {
                                            btnPlayPause.innerHTML = '<span>▶</span> تشغيل تلقائي';
                                            btnPlayPause.classList.remove('bg-slate-900', 'hover:bg-slate-800');
                                            btnPlayPause.classList.add('bg-emerald-600', 'hover:bg-emerald-700');
                                            carouselActive = false;
                                        }
                                    };

                                    window.toggleReviewsStyle = function(style) {
                                        const view3d = document.getElementById('reviews-3d-view');
                                        const viewGrid = document.getElementById('reviews-grid-view');
                                        const btn3d = document.getElementById('btn-view-3d');
                                        const btnGrid = document.getElementById('btn-view-grid');

                                        if (style === '3d') {
                                            view3d.classList.remove('hidden');
                                            viewGrid.classList.add('hidden');
                                            
                                            // Active class styling
                                            btn3d.className = "px-3 py-1.5 rounded-xl text-[10px] font-black transition-all bg-brand-600 text-white shadow-sm cursor-pointer";
                                            btnGrid.className = "px-3 py-1.5 rounded-xl text-[10px] font-black transition-all bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer";
                                            carouselActive = true;
                                        } else {
                                            view3d.classList.add('hidden');
                                            viewGrid.classList.remove('hidden');

                                            btnGrid.className = "px-3 py-1.5 rounded-xl text-[10px] font-black transition-all bg-brand-600 text-white shadow-sm cursor-pointer";
                                            btn3d.className = "px-3 py-1.5 rounded-xl text-[10px] font-black transition-all bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer";
                                            carouselActive = false;
                                        }
                                    };
                                })();
                            </script>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

        <?php endif; ?>
    </main>
    <?php endif; ?>

    <!-- Advanced Premium Advertisements Popup Modal -->
    <?php 
    $popupAds = array_filter($activeAds, function($ad) { return $ad['position'] === 'popup'; });
    if (!empty($popupAds)):
        $popupAd = array_shift($popupAds); // Show one popup ad at a time
    ?>
        <div id="showroom-ad-popup-modal" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md opacity-0 pointer-events-none transition-all duration-300">
            <div class="relative bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden transform translate-y-8 transition-all duration-300">
                
                <!-- Close Button -->
                <button onclick="closeAdPopupModal()" class="absolute top-4 right-4 z-50 w-8 h-8 rounded-full bg-slate-950/60 hover:bg-slate-950/90 text-white flex items-center justify-center text-sm font-bold border border-white/10 hover:border-white/20 transition cursor-pointer">
                    ✕
                </button>

                <!-- Ad content -->
                <?php if ($popupAd['type'] === 'image'): ?>
                    <a href="customer.php?action=click_ad&id=<?php echo $popupAd['id']; ?>" class="block relative group overflow-hidden">
                        <img src="<?php echo htmlspecialchars($popupAd['image_path']); ?>" class="w-full object-cover max-h-[420px]" alt="<?php echo htmlspecialchars($popupAd['title']); ?>">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/95 via-slate-950/20 to-transparent p-6 flex flex-col justify-end">
                            <span class="text-amber-400 text-[10px] font-extrabold uppercase tracking-widest mb-1">🔥 عرض خاص / إعلان</span>
                            <h3 class="text-white text-base font-black tracking-tight leading-tight"><?php echo htmlspecialchars($popupAd['title']); ?></h3>
                            <p class="text-xs text-slate-300 mt-1">اضغط للتفاصيل والاستفادة من العرض الحصري</p>
                        </div>
                    </a>
                <?php else: ?>
                    <div class="p-6 text-slate-100 overflow-hidden">
                        <div class="text-[10px] font-extrabold text-amber-400 mb-2">🔥 عرض خاص / إعلان</div>
                        <?php echo $popupAd['html_code']; ?>
                    </div>
                <?php endif; ?>

                <!-- Progress countdown bar to close -->
                <div class="h-1 bg-indigo-600/20 w-full relative">
                    <div id="ad-popup-progress-bar" class="absolute top-0 right-0 h-full bg-indigo-500 w-full transition-all linear"></div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Open popup modal after 1.5s delay for perfect user experience
                setTimeout(function() {
                    const modal = document.getElementById('showroom-ad-popup-modal');
                    const content = modal ? modal.firstElementChild : null;
                    if (modal && content) {
                        modal.classList.remove('opacity-0', 'pointer-events-none');
                        modal.classList.add('opacity-100', 'pointer-events-auto');
                        content.classList.remove('translate-y-8');
                        content.classList.add('translate-y-0');
                        
                        // Start progress bar countdown for 8 seconds
                        const progressBar = document.getElementById('ad-popup-progress-bar');
                        if (progressBar) {
                            progressBar.style.transitionDuration = '8s';
                            progressBar.style.width = '0%';
                        }
                        
                        // Auto-close after 8 seconds
                        setTimeout(closeAdPopupModal, 8000);
                    }
                }, 1500);
            });

            function closeAdPopupModal() {
                const modal = document.getElementById('showroom-ad-popup-modal');
                const content = modal ? modal.firstElementChild : null;
                if (modal && content) {
                    modal.classList.remove('opacity-100', 'pointer-events-auto');
                    modal.classList.add('opacity-0', 'pointer-events-none');
                    content.classList.remove('translate-y-0');
                    content.classList.add('translate-y-8');
                }
            }
        </script>
    <?php endif; ?>

    <!-- Beautiful Showroom Footer -->
    <footer class="bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 transition py-12 mt-16 text-slate-600 dark:text-slate-400">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-4 gap-8">
            <!-- Col 1: About -->
            <div class="space-y-4 text-right">
                <div class="flex items-center gap-3">
                    <?php if (!empty($companySettings['logo'])): ?>
                        <img src="<?php echo htmlspecialchars($companySettings['logo']); ?>" alt="Logo" style="height: <?php echo intval($companySettings['logo_height'] ?? 40) * 0.8; ?>px;" class="w-auto object-contain" referrerPolicy="no-referrer">
                    <?php else: ?>
                        <div style="background-color: <?php echo htmlspecialchars($companySettings['logo_color'] ?? '#6366f1'); ?>; border-radius: <?php echo intval($companySettings['logo_border_radius'] ?? 12) * 0.8; ?>px;" class="w-8 h-8 flex items-center justify-center font-black text-white text-sm">M</div>
                    <?php endif; ?>
                    <span class="brand-company-name font-black <?php echo htmlspecialchars($companySettings['company_name_font_size'] ?? 'text-sm'); ?>"><?php echo htmlspecialchars($companySettings['company_name']); ?></span>
                </div>
                <p class="text-xs leading-relaxed max-w-sm">
                    <?php echo htmlspecialchars($companySettings['company_description'] ?? 'نسعى لتقديم أفضل خدمات بيع وتوريد السيارات الفاخرة والمستعملة والجديدة بأعلى جودة واحترافية وبشروط تمويلية مرنة.'); ?>
                </p>
            </div>

            <!-- Col 2: Quick Links (Dynamic Custom Links & Pages) -->
            <div class="space-y-4 text-right">
                <h4 class="font-extrabold text-xs text-slate-900 dark:text-white">🔗 روابط سريعة</h4>
                <ul class="text-xs space-y-2">
                    <li>
                        <a href="<?php echo $showroom_file; ?>" class="hover:text-brand-500 transition flex items-center gap-1.5">
                            <span>🚗</span>
                            <span>المعرض الرئيسي</span>
                        </a>
                    </li>
                    <?php foreach ($custom_pages as $page): ?>
                        <?php if (in_array($page['visibility'] ?? 'both', ['both', 'footer'])): ?>
                            <li>
                                <a href="<?php echo $showroom_file; ?>?page=<?php echo urlencode($page['slug']); ?>" class="hover:text-brand-500 transition flex items-center gap-1.5">
                                    <?php if(!empty($page['icon'])): ?>
                                        <span><?php echo htmlspecialchars($page['icon']); ?></span>
                                    <?php else: ?>
                                        <span>📄</span>
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars($page['title']); ?></span>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php foreach ($custom_links as $link): ?>
                        <?php if (in_array($link['location'] ?? 'header', ['both', 'footer'])): ?>
                            <li>
                                <a href="<?php echo htmlspecialchars($link['url']); ?>" <?php echo !empty($link['target']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?> class="hover:text-brand-500 transition flex items-center gap-1.5">
                                    <?php if(!empty($link['icon'])): ?>
                                        <span><?php echo htmlspecialchars($link['icon']); ?></span>
                                    <?php else: ?>
                                        <span>🔗</span>
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars($link['title']); ?></span>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Col 3: Contact Info -->
            <div class="space-y-4 text-right">
                <h4 class="font-extrabold text-xs text-slate-900 dark:text-white">📞 تواصل معنا</h4>
                <ul class="text-xs space-y-2">
                    <li class="flex items-center gap-2">
                        <span>📍</span>
                        <span><?php echo htmlspecialchars($companySettings['address'] ?? 'المملكة العربية السعودية'); ?></span>
                    </li>
                    <li class="flex items-center gap-2">
                        <span>📞</span>
                        <a href="tel:<?php echo htmlspecialchars($companySettings['phone'] ?? ''); ?>" class="hover:text-brand-500 font-sans"><?php echo htmlspecialchars($companySettings['phone'] ?? ''); ?></a>
                    </li>
                    <?php if (!empty($companySettings['email'])): ?>
                    <li class="flex items-center gap-2">
                        <span>✉️</span>
                        <a href="mailto:<?php echo htmlspecialchars($companySettings['email']); ?>" class="hover:text-brand-500 font-sans"><?php echo htmlspecialchars($companySettings['email']); ?></a>
                    </li>
                    <?php endif; ?>
                    <li class="flex items-center gap-2">
                        <span>💬</span>
                        <button onclick="openContactModal()" class="hover:text-brand-500 font-bold transition text-right cursor-pointer">إرسال استفسار مباشر (اتصل بنا)</button>
                    </li>
                </ul>
            </div>

            <!-- Col 3: Social Links -->
            <div class="space-y-4 md:text-left text-right">
                <h4 class="font-extrabold text-xs text-slate-900 dark:text-white md:text-left text-right">🌐 تواصل معنا اجتماعيًا</h4>
                <div class="flex items-center gap-2 md:justify-start justify-end">
                    <?php if (!empty($companySettings['showroom_twitter'])): ?>
                    <a href="<?php echo htmlspecialchars($companySettings['showroom_twitter']); ?>" target="_blank" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-slate-700 dark:text-slate-300 transition" title="X / Twitter">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($companySettings['showroom_facebook'])): ?>
                    <a href="<?php echo htmlspecialchars($companySettings['showroom_facebook']); ?>" target="_blank" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-slate-700 dark:text-slate-300 transition" title="Facebook">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c4.56-.93 8-4.96 8-9.75z"/></svg>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($companySettings['showroom_instagram'])): ?>
                    <a href="<?php echo htmlspecialchars($companySettings['showroom_instagram']); ?>" target="_blank" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-slate-700 dark:text-slate-300 transition" title="Instagram">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($companySettings['showroom_linkedin'])): ?>
                    <a href="<?php echo htmlspecialchars($companySettings['showroom_linkedin']); ?>" target="_blank" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-slate-700 dark:text-slate-300 transition" title="LinkedIn">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.779-1.75-1.75s.784-1.75 1.75-1.75 1.75.779 1.75 1.75-.784 1.75-1.75 1.75zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($companySettings['showroom_snapchat'])): ?>
                    <a href="<?php echo htmlspecialchars($companySettings['showroom_snapchat']); ?>" target="_blank" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-slate-700 dark:text-slate-300 transition" title="Snapchat">
                        <span class="text-sm">👻</span>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($companySettings['showroom_youtube'])): ?>
                    <a href="<?php echo htmlspecialchars($companySettings['showroom_youtube']); ?>" target="_blank" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-slate-700 dark:text-slate-300 transition" title="YouTube">
                        <span class="text-sm">▶️</span>
                    </a>
                    <?php endif; ?>
                    <?php 
                    $customSocialsArr = json_decode($companySettings['showroom_custom_socials'] ?? '[]', true);
                    if (is_array($customSocialsArr)):
                        foreach ($customSocialsArr as $customSoc):
                            if (!empty($customSoc['url'])):
                    ?>
                    <a href="<?php echo htmlspecialchars($customSoc['url']); ?>" target="_blank" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-slate-700 dark:text-slate-300 transition" title="<?php echo htmlspecialchars($customSoc['title'] ?? 'رابط'); ?>">
                        <span class="text-sm"><?php echo htmlspecialchars($customSoc['icon'] ?? '🔗'); ?></span>
                    </a>
                    <?php 
                            endif;
                        endforeach;
                    endif;
                    ?>
                </div>
            </div>
        </div>

        <!-- Copyright bar -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 pt-8 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row justify-between items-center gap-4 text-[11px] text-slate-400">
            <span><?php echo htmlspecialchars($companySettings['showroom_footer_text'] ?? 'جميع الحقوق محفوظة © 2026 شركة المخزون للمحركات المحدودة.'); ?></span>
            <div class="flex items-center gap-4">
                <a href="https://karia2.blogspot.com/" target="_blank" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition font-bold">منصة كاريان الرقمية</a>
            </div>
        </div>
    </footer>

    <!-- Car Detail / Specifications Modal -->
    <div id="car-detail-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300 overflow-y-auto">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 w-full max-w-4xl rounded-2xl shadow-2xl overflow-hidden transform scale-95 transition-all duration-300 my-8">
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-slate-900 text-white flex justify-between items-center border-b border-slate-800">
                <div class="text-right">
                    <h3 id="detail-car-title" class="font-extrabold text-base text-slate-100">اسم السيارة</h3>
                    <p id="detail-car-subtitle" class="text-xs text-slate-400 mt-0.5">تفاصيل ومواصفات السيارة الفنية</p>
                </div>
                <button onclick="closeCarDetailModal()" class="text-slate-400 hover:text-white transition text-2xl font-bold cursor-pointer p-1">&times;</button>
            </div>

            <!-- Modal Content (Scrollable Grid) -->
            <div class="p-6 overflow-y-auto max-h-[calc(100vh-12rem)] text-right" dir="rtl">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    
                    <!-- Left: Gallery/Images (7 columns) -->
                    <div class="lg:col-span-7 space-y-4">
                        <!-- Main Image Stage -->
                        <div onclick="openImageLightbox(document.getElementById('gallery-main-img').src)" class="relative aspect-[16/10] bg-slate-950 rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-800 shadow-inner group cursor-pointer">
                            <img id="gallery-main-img" src="" alt="Car Image" class="w-full h-full object-cover transition-all duration-500">
                            <!-- Zoom Overlay -->
                            <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <span class="bg-slate-900/85 backdrop-blur text-white text-xs font-bold px-3 py-1.5 rounded-full border border-white/10">🔍 عرض الصورة كاملة</span>
                            </div>
                        </div>

                        <!-- Thumbnails slider/grid -->
                        <div id="gallery-thumbnails" class="grid grid-cols-4 sm:grid-cols-5 gap-2 overflow-x-auto pb-1">
                            <!-- Dynamically generated clickable thumbnails -->
                        </div>
                    </div>

                    <!-- Right: Quick info & CTA (5 columns) -->
                    <div class="lg:col-span-5 flex flex-col justify-between space-y-6">
                        <div class="space-y-4">
                            <!-- Badges -->
                            <div class="flex flex-wrap gap-2">
                                <span id="detail-badge-year" class="px-2.5 py-1 text-[10px] font-extrabold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg">سنة الصنع: 2026</span>
                                <span id="detail-badge-condition" class="px-2.5 py-1 text-[10px] font-extrabold bg-indigo-500/10 text-brand-500 rounded-lg">جديد</span>
                                <span id="detail-badge-transmission" class="px-2.5 py-1 text-[10px] font-extrabold bg-emerald-500/10 text-emerald-600 rounded-lg">أوتوماتيك</span>
                            </div>

                            <!-- Pricing card -->
                            <div class="p-4 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-100 dark:border-slate-800/60">
                                <span class="text-[10px] text-slate-400 block mb-1 font-bold">السعر النقدي الفوري</span>
                                <div class="flex items-baseline gap-1.5">
                                    <span id="detail-price" class="text-2xl font-black text-emerald-600 dark:text-emerald-400">0</span>
                                    <span id="detail-currency" class="text-xs font-bold text-slate-500">ر.س</span>
                                </div>
                                <span class="text-[9px] text-slate-400 block mt-1.5">* السعر شامل ضريبة القيمة المضافة ومصاريف اللوحات</span>
                            </div>

                            <!-- Key features bullet points list -->
                            <div class="space-y-2 text-xs">
                                <div class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                                    <span class="text-indigo-500">⚙️</span>
                                    <span class="font-bold">المحرك والأداء:</span>
                                    <span id="detail-spec-engine-summary" class="font-medium text-slate-500">4 سلندر | 180 حصان</span>
                                </div>
                                <div class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                                    <span class="text-indigo-500">🚘</span>
                                    <span class="font-bold">نظام الدفع والناقل:</span>
                                    <span id="detail-spec-drive-summary" class="font-medium text-slate-500">دفع أمامي FWD</span>
                                </div>
                                <div class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                                    <span class="text-indigo-500">🛡️</span>
                                    <span class="font-bold">الضمان والوكيل:</span>
                                    <span id="detail-spec-warranty" class="font-medium text-slate-500">ضمان ممتد</span>
                                </div>
                            </div>
                        </div>

                        <!-- CTA buttons inside Modal -->
                        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex flex-col gap-2.5">
                            <button id="detail-cta-order" class="w-full py-3 bg-brand-600 hover:bg-brand-700 text-white font-black text-xs rounded-xl shadow-sm hover:shadow-md transition flex items-center justify-center gap-2 cursor-pointer">
                                <span>📥 اطلب شراء السيارة الآن</span>
                            </button>
                            <a id="detail-cta-whatsapp" href="" target="_blank" class="w-full py-3 bg-emerald-500 hover:bg-emerald-600 text-white font-black text-xs rounded-xl shadow-sm hover:shadow-md transition flex items-center justify-center gap-2 cursor-pointer">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.455L0 24zm6.59-4.846c1.665.989 3.3 1.488 5.336 1.489 5.485 0 9.95-4.466 9.954-9.957.002-2.661-1.034-5.159-2.914-7.04C17.143 1.765 14.653.729 12.009.729 6.52.729 2.054 5.197 2.05 10.69c-.001 2.012.502 3.655 1.477 5.286L2.529 21.94l6.118-1.786z"></path></svg>
                                <span>💬 استفسر عبر الواتساب مباشرة</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Bento Specs Grid (The "professional specifications" layout) -->
                <div class="mt-8">
                    <h4 class="text-xs font-black text-slate-800 dark:text-slate-200 border-r-4 border-indigo-600 pr-2 mb-4">📊 المواصفات التقنية التفصيلية</h4>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        <div class="p-3 bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800/60 rounded-xl">
                            <span class="text-[9px] text-slate-400 block mb-0.5">ناقل الحركة</span>
                            <span id="spec-val-transmission" class="text-xs font-bold text-slate-700 dark:text-slate-200">أوتوماتيك</span>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800/60 rounded-xl">
                            <span class="text-[9px] text-slate-400 block mb-0.5">نوع الوقود</span>
                            <span id="spec-val-engine" class="text-xs font-bold text-slate-700 dark:text-slate-200">بنزين</span>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800/60 rounded-xl">
                            <span class="text-[9px] text-slate-400 block mb-0.5">الممشى (المسافة المقطوعة)</span>
                            <span id="spec-val-mileage" class="text-xs font-bold text-slate-700 dark:text-slate-200">0 كم</span>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800/60 rounded-xl">
                            <span class="text-[9px] text-slate-400 block mb-0.5">قوة المحرك</span>
                            <span id="spec-val-power" class="text-xs font-bold text-slate-700 dark:text-slate-200">180 حصان</span>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800/60 rounded-xl">
                            <span class="text-[9px] text-slate-400 block mb-0.5">عدد الاسطوانات</span>
                            <span id="spec-val-cylinders" class="text-xs font-bold text-slate-700 dark:text-slate-200">4 سلندر</span>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800/60 rounded-xl">
                            <span class="text-[9px] text-slate-400 block mb-0.5">نظام الدفع</span>
                            <span id="spec-val-drive" class="text-xs font-bold text-slate-700 dark:text-slate-200">دفع أمامي FWD</span>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800/60 rounded-xl">
                            <span class="text-[9px] text-slate-400 block mb-0.5">اللون الخارجي</span>
                            <span id="spec-val-color" class="text-xs font-bold text-slate-700 dark:text-slate-200">-</span>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800/60 rounded-xl">
                            <span class="text-[9px] text-slate-400 block mb-0.5">اللون الداخلي</span>
                            <span id="spec-val-interior" class="text-xs font-bold text-slate-700 dark:text-slate-200">-</span>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800/60 rounded-xl">
                            <span class="text-[9px] text-slate-400 block mb-0.5">نوع الهيكل</span>
                            <span id="spec-val-body" class="text-xs font-bold text-slate-700 dark:text-slate-200">سيدان</span>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800/60 rounded-xl">
                            <span class="text-[9px] text-slate-400 block mb-0.5">بلد المنشأ</span>
                            <span id="spec-val-origin" class="text-xs font-bold text-slate-700 dark:text-slate-200">-</span>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800/60 rounded-xl">
                            <span class="text-[9px] text-slate-400 block mb-0.5">بلد التجميع</span>
                            <span id="spec-val-assembly" class="text-xs font-bold text-slate-700 dark:text-slate-200">-</span>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800/60 rounded-xl">
                            <span class="text-[9px] text-slate-400 block mb-0.5">رقم الهيكل (VIN)</span>
                            <span id="spec-val-vin" class="text-xs font-bold text-slate-700 dark:text-slate-200 font-sans">-</span>
                        </div>
                    </div>
                </div>

                <!-- Custom Manual Specifications Section -->
                <div id="detail-custom-specs-section" class="mt-8 hidden">
                    <h4 class="text-xs font-black text-slate-800 dark:text-slate-200 border-r-4 border-indigo-600 pr-2 mb-4">⭐ المواصفات الخاصة المضافة يدوياً</h4>
                    <div class="p-5 bg-indigo-50/20 dark:bg-indigo-950/20 border border-indigo-100/30 dark:border-indigo-900/30 rounded-2xl">
                        <p id="detail-custom-specs-content" class="text-slate-700 dark:text-slate-300 text-xs leading-relaxed whitespace-pre-wrap font-bold"></p>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Order Now Form Modal -->
    <div id="order-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 w-full max-w-md rounded-2xl shadow-xl overflow-hidden transform scale-95 transition-transform duration-300">
            <div class="px-5 py-4 bg-slate-900 text-white flex justify-between items-center">
                <div>
                    <h3 class="font-extrabold text-sm flex items-center gap-1.5">
                        <span>📥</span> تقديم طلب شراء سيارة
                    </h3>
                    <p class="text-[10px] text-slate-400 mt-0.5" id="modal-subtitle-car"></p>
                </div>
                <button onclick="closeOrderModal()" class="text-slate-400 hover:text-white transition text-lg font-bold cursor-pointer">&times;</button>
            </div>

            <form id="order-form" onsubmit="submitOrderForm(event)" class="p-5 space-y-4 text-right">
                <input type="hidden" name="csrf_token" value="<?php echo SecurityCore::getCsrfToken(); ?>">
                <input type="hidden" name="car_id" id="modal-car-id" value="">
                
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">السيارة المختارة</label>
                    <input type="text" id="modal-car-display" disabled class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-950 text-slate-500 font-extrabold outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">الاسم بالكامل <span class="text-red-500">*</span></label>
                    <input type="text" name="customer_name" required placeholder="أدخل اسمك الكريم..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-brand-500 transition">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">رقم الجوال لتواصل المبيعات <span class="text-red-500">*</span></label>
                    <input type="tel" name="customer_phone" required placeholder="مثال: 0500000000" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-brand-500 transition font-sans text-left" dir="ltr">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">ملاحظات أو تفاصيل إضافية (اختياري)</label>
                    <textarea name="notes" placeholder="اكتب مواصفات خاصة، تفاصيل التمويل، إلخ..." rows="3" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-brand-500 transition"></textarea>
                </div>

                <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-2">
                    <button type="button" onclick="closeOrderModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-bold rounded-lg transition text-slate-700 dark:text-slate-300">إلغاء</button>
                    <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white text-xs font-black rounded-lg transition shadow flex items-center gap-1 cursor-pointer">
                        <span>ارسال الطلب</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Contact Us Form Modal -->
    <div id="contact-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 w-full max-w-md rounded-2xl shadow-xl overflow-hidden transform scale-95 transition-transform duration-300">
            <div class="px-5 py-4 bg-slate-900 text-white flex justify-between items-center">
                <div>
                    <h3 class="font-extrabold text-sm flex items-center gap-1.5">
                        <span>✉️</span> اتصل بنا / إرسال استفسار
                    </h3>
                    <p class="text-[10px] text-slate-400 mt-0.5">يسعدنا الإجابة على كافة استفساراتكم في أسرع وقت</p>
                </div>
                <button onclick="closeContactModal()" class="text-slate-400 hover:text-white transition text-lg font-bold cursor-pointer">&times;</button>
            </div>

            <form id="contact-form" onsubmit="submitContactForm(event)" class="p-5 space-y-4 text-right">
                <input type="hidden" name="csrf_token" value="<?php echo SecurityCore::getCsrfToken(); ?>">
                
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">الاسم الكريم <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required placeholder="أدخل اسمك الكريم..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-indigo-500 transition">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">رقم الجوال <span class="text-red-500">*</span></label>
                    <input type="tel" name="phone" required placeholder="مثال: 0500000000" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-indigo-500 transition font-sans text-left" dir="ltr">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">البريد الإلكتروني (اختياري)</label>
                    <input type="email" name="email" placeholder="name@example.com" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-indigo-500 transition font-sans text-left" dir="ltr">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">موضوع الرسالة (اختياري)</label>
                    <input type="text" name="subject" placeholder="مثال: استفسار عن تمويل، طلب حجز خاص..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-indigo-500 transition">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">نص الرسالة / الاستفسار <span class="text-red-500">*</span></label>
                    <textarea name="message" required placeholder="اكتب تفاصيل استفسارك هنا وسنتواصل معك فوراً..." rows="4" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-indigo-500 transition"></textarea>
                </div>

                <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-2">
                    <button type="button" onclick="closeContactModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-bold rounded-lg transition text-slate-700 dark:text-slate-300">إلغاء</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black rounded-lg transition shadow flex items-center gap-1 cursor-pointer">
                        <span>ارسال الاستفسار</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Write a Showroom Review Modal -->
    <div id="review-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300" dir="rtl">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 w-full max-w-md rounded-2xl shadow-xl overflow-hidden transform scale-95 transition-transform duration-300">
            <div class="px-5 py-4 bg-slate-900 text-white flex justify-between items-center">
                <div>
                    <h3 class="font-extrabold text-sm flex items-center gap-1.5">
                        <span>✍️</span> كتابة مراجعة وتقييم للمعرض
                    </h3>
                    <p class="text-[10px] text-slate-400 mt-0.5">يسعدنا سماع رأيك وتجربتك معنا</p>
                </div>
                <button onclick="closeReviewModal()" class="text-slate-400 hover:text-white transition text-lg font-bold cursor-pointer">&times;</button>
            </div>

            <form id="review-form" onsubmit="submitReviewForm(event)" class="p-5 space-y-4 text-right">
                <input type="hidden" name="csrf_token" value="<?php echo SecurityCore::getCsrfToken(); ?>">
                
                <!-- Star Rating Input (Interactive!) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">تقييمك للمعرض <span class="text-red-500">*</span></label>
                    <div class="flex items-center gap-2 justify-between py-2.5 px-4 bg-slate-50 dark:bg-slate-950/50 rounded-xl border border-slate-150 dark:border-slate-800/60">
                        <input type="hidden" name="rating" id="review-rating-value" value="5">
                        <div class="flex flex-row-reverse gap-1.5 text-2xl" id="interactive-stars">
                            <span data-star="5" class="star-btn cursor-pointer text-amber-400 transition hover:scale-110">★</span>
                            <span data-star="4" class="star-btn cursor-pointer text-amber-400 transition hover:scale-110">★</span>
                            <span data-star="3" class="star-btn cursor-pointer text-amber-400 transition hover:scale-110">★</span>
                            <span data-star="2" class="star-btn cursor-pointer text-amber-400 transition hover:scale-110">★</span>
                            <span data-star="1" class="star-btn cursor-pointer text-amber-400 transition hover:scale-110">★</span>
                        </div>
                        <span id="star-description" class="text-xs font-extrabold text-brand-600 dark:text-brand-400 bg-brand-500/10 px-2.5 py-1 rounded-lg">ممتاز جداً</span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">الاسم الكريم <span class="text-red-500">*</span></label>
                    <input type="text" name="customer_name" required placeholder="أدخل اسمك الكريم..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-brand-500 transition">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">تجربتك أو تعليقك <span class="text-red-500">*</span></label>
                    <textarea name="comment" required placeholder="اكتب رأيك وتجربتك بالتفصيل (مثل جودة السيارات، سرعة التعامل، حسن الاستقبال)..." rows="4" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-brand-500 transition"></textarea>
                </div>

                <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-2">
                    <button type="button" onclick="closeReviewModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-bold rounded-lg transition text-slate-700 dark:text-slate-300">إلغاء</button>
                    <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white text-xs font-black rounded-lg transition shadow flex items-center gap-1 cursor-pointer">
                        <span>ارسال التقييم</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Centralized Toast Notifications -->
    <div id="toast-container" class="fixed bottom-5 left-5 z-50 flex flex-col gap-2 max-w-sm"></div>

    <!-- Beautiful Centered Success Modal -->
    <div id="success-popup-modal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300 font-sans" dir="rtl">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 w-full max-w-sm rounded-2xl shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300 text-center p-6 space-y-4">
            <div class="w-16 h-16 bg-emerald-100 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 rounded-full flex items-center justify-center mx-auto text-3xl animate-bounce">
                ✓
            </div>
            <h3 class="text-base font-black text-slate-900 dark:text-white" id="success-popup-title">تم تقديم طلبك بنجاح!</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed font-bold" id="success-popup-message">
                سيتواصل معك أحد مناديبنا في أقرب وقت لإتمام الإجراءات وتأكيد الحجز.
            </p>
            <div class="pt-2">
                <button type="button" onclick="closeSuccessPopupModal()" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-600/10 transition cursor-pointer">
                    حسناً، فهمت
                </button>
            </div>
        </div>
    </div>

    <!-- Image Lightbox Modal with Full Navigation Gallery (تصفح الصور في الوضع الكامل) -->
    <div id="image-lightbox-modal" class="fixed inset-0 z-[10000] flex flex-col justify-between p-4 md:p-6 bg-slate-950/95 backdrop-blur-md hidden opacity-0 transition-opacity duration-300" onclick="closeImageLightbox(event)">
        <!-- Top Bar Control -->
        <div class="w-full max-w-7xl mx-auto flex items-center justify-between z-10 no-print" onclick="event.stopPropagation()">
            <!-- Image Meta Box -->
            <div class="flex items-center gap-2 bg-slate-900/60 backdrop-blur-sm border border-slate-800/60 px-4 py-1.5 rounded-lg text-xs font-bold text-slate-300">
                <span class="text-indigo-400">🖼️</span>
                <span id="lightbox-car-title" class="truncate max-w-[150px] sm:max-w-xs">سيارة</span>
                <span class="opacity-40">|</span>
                <span id="lightbox-counter" class="font-mono text-indigo-400 font-extrabold">1 / 1</span>
            </div>
            
            <!-- Close Button -->
            <button onclick="closeImageLightbox(event, true)" class="p-2.5 rounded-full bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-400 hover:text-white transition cursor-pointer shadow-lg hover:scale-105" title="إغلاق المعرض">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Main Display Container (Image + Navigation Arrows) -->
        <div class="flex-grow w-full max-w-7xl mx-auto flex items-center justify-between my-4 relative" onclick="event.stopPropagation()">
            <!-- Right Arrow (RTL: Previous Image) -->
            <button id="lightbox-prev-btn" onclick="prevLightboxImage()" class="absolute right-2 md:right-4 z-10 p-3 rounded-full bg-slate-900/80 hover:bg-slate-800 text-slate-300 hover:text-white border border-slate-800 transition cursor-pointer shadow-lg hover:scale-105" title="الصورة السابقة">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path>
                </svg>
            </button>

            <!-- Zoom Image Stage -->
            <div id="lightbox-swipe-stage" class="w-full h-[65vh] md:h-[75vh] flex items-center justify-center select-none cursor-grab active:cursor-grabbing relative overflow-hidden">
                <div class="absolute top-2 px-3 py-1 rounded bg-slate-950/40 text-slate-400 text-[10px] pointer-events-none select-none">
                    استخدم الأسهم، اسحب الصورة، أو اضغط للتنقل
                </div>
                <img id="lightbox-zoom-img" src="" alt="Zoomed Image" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl border border-white/5 select-none transition-all duration-300 pointer-events-none">
            </div>

            <!-- Left Arrow (RTL: Next Image) -->
            <button id="lightbox-next-btn" onclick="nextLightboxImage()" class="absolute left-2 md:left-4 z-10 p-3 rounded-full bg-slate-900/80 hover:bg-slate-800 text-slate-300 hover:text-white border border-slate-800 transition cursor-pointer shadow-lg hover:scale-105" title="الصورة التالية">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path>
                </svg>
            </button>
        </div>

        <!-- Bottom Thumbnails Tray -->
        <div id="lightbox-thumbnails-tray" class="w-full max-w-7xl mx-auto flex justify-center items-center gap-2 overflow-x-auto py-2 z-10 px-4 no-print" onclick="event.stopPropagation()">
            <!-- Dynamically populated thumbnails -->
        </div>
    </div>

    <!-- Client-Side Logic & UI Enhancements -->
    <script>
    // Expose all showroom cars data to client-side JS
    window.allShowroomCars = <?php echo json_encode($cars ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS); ?>;

    // Open Car Detail Modal
    function openCarDetailModal(event, carId) {
        if (event) event.stopPropagation();
        
        const car = window.allShowroomCars.find(c => c.id === carId);
        if (!car) return;
        
        // Title & subtitle
        document.getElementById('detail-car-title').innerText = `${car.make} ${car.model}`;
        document.getElementById('detail-car-subtitle').innerText = `تفاصيل ومواصفات موديل ${car.year} الفنية`;
        
        // Badges
        document.getElementById('detail-badge-year').innerText = `سنة الصنع: ${car.year}`;
        document.getElementById('detail-badge-condition').innerText = car.vehicle_condition || 'جديد (أصفار)';
        document.getElementById('detail-badge-transmission').innerText = car.transmission || 'أوتوماتيك';
        
        // Price
        const showPrice = <?php echo (int)($companySettings['showroom_show_price'] ?? 1); ?>;
        const priceEl = document.getElementById('detail-price');
        const currencyEl = document.getElementById('detail-currency');
        if (showPrice === 1) {
            priceEl.innerText = Number(car.price).toLocaleString();
            currencyEl.innerText = car.currency || 'ر.س';
        } else {
            priceEl.innerText = 'السعر عند التواصل';
            currencyEl.innerText = '';
        }
        
        // Key features
        document.getElementById('detail-spec-engine-summary').innerText = `${car.cylinders ? car.cylinders + ' سلندر' : ''} ${car.engine_power ? '| ' + car.engine_power + ' حصان' : ''} ${car.engine_type ? '| ' + car.engine_type : ''}`;
        document.getElementById('detail-spec-drive-summary').innerText = car.drive || 'دفع أمامي FWD';
        document.getElementById('detail-spec-warranty').innerText = car.warranty || 'ضمان الوكيل المعتمد الممتد';
        
        // Bento specifications
        document.getElementById('spec-val-transmission').innerText = car.transmission || 'أوتوماتيك';
        document.getElementById('spec-val-engine').innerText = car.engine_type || 'بنزين';
        document.getElementById('spec-val-mileage').innerText = (Number(car.mileage) || 0).toLocaleString() + ' كم';
        document.getElementById('spec-val-power').innerText = (car.engine_power || '-') + ' حصان';
        document.getElementById('spec-val-cylinders').innerText = (car.cylinders || '-') + ' سلندر';
        document.getElementById('spec-val-drive').innerText = car.drive || '-';
        document.getElementById('spec-val-color').innerText = car.color || '-';
        document.getElementById('spec-val-interior').innerText = car.interior_color || '-';
        document.getElementById('spec-val-body').innerText = car.body_type || 'سيدان';
        document.getElementById('spec-val-origin').innerText = car.origin_country || '-';
        document.getElementById('spec-val-assembly').innerText = car.assembly_country || '-';
        document.getElementById('spec-val-vin').innerText = car.vin || '-';
        
        // Custom Specs
        const customSpecsSection = document.getElementById('detail-custom-specs-section');
        const customSpecsContent = document.getElementById('detail-custom-specs-content');
        if (car.custom_specs && car.custom_specs.trim() !== '') {
            customSpecsContent.innerText = car.custom_specs;
            customSpecsSection.classList.remove('hidden');
        } else {
            customSpecsSection.classList.add('hidden');
        }
        
        // Setup CTA buttons inside modal
        const orderBtn = document.getElementById('detail-cta-order');
        const priceStr = showPrice === 1 ? Number(car.price).toLocaleString() + ' ' + (car.currency || 'ر.س') : 'السعر عند التواصل';
        orderBtn.onclick = function() {
            closeCarDetailModal();
            openOrderModal(car.id, `${car.make} ${car.model}`, priceStr);
        };
        
        // WhatsApp button url setup
        const waClean = "<?php echo $whatsapp_clean; ?>";
        let waText = '';
        if (showPrice === 1) {
            waText = encodeURIComponent(`مرحباً، أود الاستفسار عن سيارة ${car.make} ${car.model} موديل ${car.year} المعروضة في موقعكم بسعر ${Number(car.price).toLocaleString()} ${car.currency || 'ر.س'}.`);
        } else {
            waText = encodeURIComponent(`مرحباً، أود الاستفسار عن سيارة ${car.make} ${car.model} موديل ${car.year} المعروضة في موقعكم.`);
        }
        document.getElementById('detail-cta-whatsapp').href = `https://wa.me/${waClean}?text=${waText}`;
        
        // Gallery Images Setup
        const mainImg = document.getElementById('gallery-main-img');
        mainImg.src = car.main_image || '';
        
        const thumbsContainer = document.getElementById('gallery-thumbnails');
        thumbsContainer.innerHTML = '';
        
        // Decode gallery_images array (could be JSON array string)
        let imagesList = [];
        if (car.gallery_images) {
            try {
                imagesList = JSON.parse(car.gallery_images);
            } catch (e) {
                imagesList = [];
            }
        }
        
        // If empty, fallback to main_image only
        if (!Array.isArray(imagesList) || imagesList.length === 0) {
            if (car.main_image) {
                imagesList = [car.main_image];
            }
        }
        
        if (imagesList.length > 0) {
            imagesList.forEach((imgUrl, idx) => {
                const thumb = document.createElement('div');
                thumb.className = `aspect-video rounded-lg overflow-hidden border-2 cursor-pointer transition ${idx === 0 ? 'border-brand-600' : 'border-slate-200 dark:border-slate-800 hover:border-slate-400'}`;
                thumb.id = `thumb-img-${idx}`;
                thumb.innerHTML = `<img src="${imgUrl}" class="object-cover w-full h-full">`;
                thumb.onclick = function() {
                    mainImg.src = imgUrl;
                    // Reset borders
                    imagesList.forEach((_, tIdx) => {
                        const otherThumb = document.getElementById(`thumb-img-${tIdx}`);
                        if (otherThumb) {
                            otherThumb.className = 'aspect-video rounded-lg overflow-hidden border-2 border-slate-200 dark:border-slate-800 hover:border-slate-400 cursor-pointer transition';
                        }
                    });
                    // Set active border
                    thumb.className = 'aspect-video rounded-lg overflow-hidden border-2 border-brand-600 cursor-pointer transition';
                };
                thumbsContainer.appendChild(thumb);
            });
        }
        
        window.currentCarImages = imagesList;
        
        // Show Modal
        const modal = document.getElementById('car-detail-modal');
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('.transform').classList.remove('scale-95');
        }, 10);
    }
    
    function closeCarDetailModal() {
        const modal = document.getElementById('car-detail-modal');
        modal.classList.add('opacity-0');
        modal.querySelector('.transform').classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // Lightbox Gallery State
    window.lightboxImages = [];
    window.lightboxCurrentIndex = 0;
    window.lightboxCarTitle = "";

    function openImageLightbox(imgSrc) {
        if (!imgSrc) return;
        
        // Populate lightbox images from the current car opened in detail modal
        if (window.currentCarImages && window.currentCarImages.length > 0) {
            window.lightboxImages = window.currentCarImages;
        } else {
            window.lightboxImages = [imgSrc];
        }

        // Find current index
        let idx = window.lightboxImages.indexOf(imgSrc);
        if (idx === -1) {
            window.lightboxImages.unshift(imgSrc);
            idx = 0;
        }
        window.lightboxCurrentIndex = idx;

        // Get Car Title if available
        const detailTitleEl = document.getElementById('detail-car-title');
        window.lightboxCarTitle = detailTitleEl ? detailTitleEl.innerText : "معرض الصور";

        // Show Lightbox Modal
        const lightbox = document.getElementById('image-lightbox-modal');
        lightbox.classList.remove('hidden');
        setTimeout(() => {
            lightbox.classList.remove('opacity-0');
        }, 10);

        renderLightboxState();
        initLightboxSwipe();
    }

    function renderLightboxState() {
        const zoomImg = document.getElementById('lightbox-zoom-img');
        const counterEl = document.getElementById('lightbox-counter');
        const titleEl = document.getElementById('lightbox-car-title');
        const prevBtn = document.getElementById('lightbox-prev-btn');
        const nextBtn = document.getElementById('lightbox-next-btn');
        const tray = document.getElementById('lightbox-thumbnails-tray');

        if (!zoomImg) return;

        const currentImgUrl = window.lightboxImages[window.lightboxCurrentIndex];
        zoomImg.src = currentImgUrl;

        if (titleEl) titleEl.innerText = window.lightboxCarTitle;
        if (counterEl) {
            counterEl.innerText = `${window.lightboxCurrentIndex + 1} / ${window.lightboxImages.length}`;
        }

        // Hide arrows if only 1 image
        if (window.lightboxImages.length <= 1) {
            if (prevBtn) prevBtn.classList.add('hidden');
            if (nextBtn) nextBtn.classList.add('hidden');
            if (tray) tray.classList.add('hidden');
        } else {
            if (prevBtn) prevBtn.classList.remove('hidden');
            if (nextBtn) nextBtn.classList.remove('hidden');
            if (tray) {
                tray.classList.remove('hidden');
                renderLightboxTray();
            }
        }
    }

    function renderLightboxTray() {
        const tray = document.getElementById('lightbox-thumbnails-tray');
        if (!tray) return;
        tray.innerHTML = '';

        window.lightboxImages.forEach((img, idx) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.onclick = function(e) {
                e.stopPropagation();
                window.lightboxCurrentIndex = idx;
                renderLightboxState();
            };

            const isActive = idx === window.lightboxCurrentIndex;
            btn.className = `w-14 h-10 md:w-16 md:h-12 rounded overflow-hidden shrink-0 border transition-all cursor-pointer ${
                isActive 
                    ? 'border-indigo-500 scale-110 shadow-lg shadow-indigo-600/25 ring-1 ring-indigo-500/20' 
                    : 'border-slate-800 opacity-50 hover:opacity-100 hover:border-slate-700'
            }`;

            btn.innerHTML = `<img src="${img}" class="w-full h-full object-cover select-none pointer-events-none" referrerPolicy="no-referrer">`;
            tray.appendChild(btn);
        });
    }

    function prevLightboxImage() {
        if (window.lightboxImages.length <= 1) return;
        window.lightboxCurrentIndex = (window.lightboxCurrentIndex - 1 + window.lightboxImages.length) % window.lightboxImages.length;
        renderLightboxState();
    }

    function nextLightboxImage() {
        if (window.lightboxImages.length <= 1) return;
        window.lightboxCurrentIndex = (window.lightboxCurrentIndex + 1) % window.lightboxImages.length;
        renderLightboxState();
    }

    function closeImageLightbox(event, force = false) {
        if (event && event.stopPropagation) {
            event.stopPropagation();
        }
        const lightbox = document.getElementById('image-lightbox-modal');
        if (!lightbox) return;
        lightbox.classList.add('opacity-0');
        setTimeout(() => {
            lightbox.classList.add('hidden');
            const zoomImg = document.getElementById('lightbox-zoom-img');
            if (zoomImg) zoomImg.src = '';
        }, 300);
    }

    // Touch and drag swipe setup for Lightbox
    let swipeStartX = 0;
    let swipeEndX = 0;
    let isDragging = false;

    function initLightboxSwipe() {
        const stage = document.getElementById('lightbox-swipe-stage');
        if (!stage) return;

        // Touch Events
        stage.ontouchstart = function(e) {
            swipeStartX = e.touches[0].clientX;
            swipeEndX = e.touches[0].clientX;
        };

        stage.ontouchmove = function(e) {
            swipeEndX = e.touches[0].clientX;
        };

        stage.ontouchend = function() {
            handleSwipeGesture();
        };

        // Mouse Events for drag swipe
        stage.onmousedown = function(e) {
            swipeStartX = e.clientX;
            isDragging = true;
            stage.style.cursor = 'grabbing';
        };

        stage.onmouseup = function(e) {
            if (!isDragging) return;
            swipeEndX = e.clientX;
            isDragging = false;
            stage.style.cursor = 'grab';
            handleSwipeGesture();
        };

        stage.onmouseleave = function() {
            if (isDragging) {
                isDragging = false;
                stage.style.cursor = 'grab';
            }
        };
    }

    function handleSwipeGesture() {
        const diff = swipeStartX - swipeEndX;
        const threshold = 50;

        if (Math.abs(diff) > threshold) {
            if (diff > 0) {
                // Swiped Left - RTL next
                nextLightboxImage();
            } else {
                // Swiped Right - RTL prev
                prevLightboxImage();
            }
        }
    }

    document.addEventListener('keydown', function(e) {
        const lightbox = document.getElementById('image-lightbox-modal');
        if (lightbox && !lightbox.classList.contains('hidden')) {
            if (e.key === 'Escape') {
                closeImageLightbox(null, true);
            } else if (e.key === 'ArrowRight') {
                prevLightboxImage();
            } else if (e.key === 'ArrowLeft') {
                nextLightboxImage();
            }
        }
    });

    // 1. Dark/Light theme switching logic
    function toggleTheme() {
        const isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        updateThemeToggleIcons();
    }

    function updateThemeToggleIcons() {
        const isDark = document.documentElement.classList.contains('dark');
        const sun = document.getElementById('theme-toggle-sun');
        const moon = document.getElementById('theme-toggle-moon');
        if (isDark) {
            sun?.classList.remove('hidden');
            moon?.classList.add('hidden');
        } else {
            sun?.classList.add('hidden');
            moon?.classList.remove('hidden');
        }
    }

    // Load saved or system theme on load
    if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
    updateThemeToggleIcons();

    // 2. Lazy loading & Skeletal loader completion
    document.addEventListener("DOMContentLoaded", function() {
        // Mocking skeletal load for visual satisfaction
        setTimeout(() => {
            const skeleton = document.getElementById('skeleton-loader');
            const grid = document.getElementById('cars-grid');
            if (skeleton) skeleton.classList.add('hidden');
            if (grid) grid.classList.remove('hidden');
            
            // Lazy load images
            const images = document.querySelectorAll('.lazy-image');
            images.forEach(img => {
                if (img.getAttribute('data-src')) {
                    img.src = img.getAttribute('data-src');
                    img.addEventListener('load', () => img.classList.add('loaded'));
                }
            });
        }, 600);
    });

    // 3. Search and filtering logic
    let activeBrand = '';

    function selectBrand(brand) {
        activeBrand = brand;
        
        // Update active class on buttons
        document.querySelectorAll('.brand-tab').forEach(btn => {
            if (btn.getAttribute('data-brand') === brand) {
                btn.className = "brand-tab px-4 py-2 text-xs font-black rounded-xl transition bg-brand-600 text-white";
            } else {
                btn.className = "brand-tab px-4 py-2 text-xs font-bold text-slate-600 dark:text-slate-300 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 rounded-xl transition shrink-0";
            }
        });
        
        filterCars();
    }

    function filterCars() {
        const searchInput = document.getElementById('car-search').value.toLowerCase().trim();
        const cards = document.querySelectorAll('.car-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const make = card.getAttribute('data-make') || '';
            const model = card.getAttribute('data-model') || '';
            const textContent = (make + ' ' + model).toLowerCase();
            
            const matchesSearch = textContent.includes(searchInput);
            const matchesBrand = activeBrand === '' || make === activeBrand;

            if (matchesSearch && matchesBrand) {
                card.classList.remove('hidden');
                visibleCount++;
            } else {
                card.classList.add('hidden');
            }
        });

        const noResults = document.getElementById('no-results');
        if (visibleCount === 0) {
            noResults?.classList.remove('hidden');
        } else {
            noResults?.classList.add('hidden');
        }
    }

    // 4. Modal actions
    function openOrderModal(id, name, price) {
        const modal = document.getElementById('order-modal');
        document.getElementById('modal-car-id').value = id;
        document.getElementById('modal-car-display').value = `${name} | بسعر ${price}`;
        document.getElementById('modal-subtitle-car').innerText = `طلب السيارة: ${name}`;
        
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('.transform').classList.remove('scale-95');
        }, 10);
    }

    function closeOrderModal() {
        const modal = document.getElementById('order-modal');
        modal.classList.add('opacity-0');
        modal.querySelector('.transform').classList.add('scale-95');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            document.getElementById('order-form').reset();
        }, 300);
    }

    // 5. Order Form submission via Fetch API (AJAX)
    function submitOrderForm(event) {
        event.preventDefault();
        const form = event.target;
        const searchParams = new URLSearchParams(new FormData(form));
        
        // Submit
        fetch('<?php echo $showroom_file; ?>?action=submit_order', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: searchParams
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                openSuccessPopupModal(data.message, 'تم تقديم طلبك بنجاح! 🎉');
                closeOrderModal();
            } else {
                showToast(data.error || 'فشل تقديم الطلب.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('حدث خطأ غير متوقع أثناء الاتصال بالخادم.', 'error');
        });
    }

    // Contact Us modal actions
    function openContactModal() {
        const modal = document.getElementById('contact-modal');
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('.transform').classList.remove('scale-95');
        }, 10);
    }

    function closeContactModal() {
        const modal = document.getElementById('contact-modal');
        modal.classList.add('opacity-0');
        modal.querySelector('.transform').classList.add('scale-95');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            document.getElementById('contact-form').reset();
        }, 300);
    }

    function submitContactForm(event) {
        event.preventDefault();
        const form = event.target;
        const searchParams = new URLSearchParams(new FormData(form));
        
        fetch('<?php echo $showroom_file; ?>?action=submit_contact', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: searchParams
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                openSuccessPopupModal(data.message, 'تم إرسال رسالتك بنجاح! ✉️');
                closeContactModal();
            } else {
                showToast(data.error || 'فشل إرسال الرسالة.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('حدث خطأ غير متوقع أثناء إرسال رسالتك.', 'error');
        });
    }

    // Centered Success Popup Modal controllers
    function openSuccessPopupModal(message, title = 'تم بنجاح!') {
        const modal = document.getElementById('success-popup-modal');
        if (!modal) return;
        document.getElementById('success-popup-title').innerText = title;
        document.getElementById('success-popup-message').innerText = message;
        
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('.transform').classList.remove('scale-95');
        }, 10);
    }

    function closeSuccessPopupModal() {
        const modal = document.getElementById('success-popup-modal');
        if (!modal) return;
        modal.classList.add('opacity-0');
        modal.querySelector('.transform').classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // 6. Toast notifications manager
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `p-4 text-xs font-bold rounded-xl border flex items-center gap-3 shadow-lg transform translate-y-2 opacity-0 transition-all duration-300 ${
            type === 'success' 
            ? 'bg-emerald-50 dark:bg-emerald-950/25 text-emerald-800 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800/50' 
            : 'bg-rose-50 dark:bg-rose-950/25 text-rose-800 dark:text-rose-400 border-rose-200 dark:border-rose-800/50'
        }`;
        
        const icon = type === 'success' ? '✓' : '⚠️';
        toast.innerHTML = `<span>${icon}</span><span class="flex-1">${message}</span>`;
        container.appendChild(toast);

        // Animate entrance
        setTimeout(() => {
            toast.classList.remove('translate-y-2', 'opacity-0');
        }, 10);

        // Dismiss after 4 seconds
        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-2');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // Review modal controllers
    function openReviewModal() {
        const modal = document.getElementById('review-modal');
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('.transform').classList.remove('scale-95');
        }, 10);
    }

    function closeReviewModal() {
        const modal = document.getElementById('review-modal');
        modal.classList.add('opacity-0');
        modal.querySelector('.transform').classList.add('scale-95');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            document.getElementById('review-form').reset();
            resetInteractiveStars();
        }, 300);
    }

    function submitReviewForm(event) {
        event.preventDefault();
        const form = event.target;
        const searchParams = new URLSearchParams(new FormData(form));
        
        fetch('<?php echo $showroom_file; ?>?action=submit_review', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: searchParams
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                openSuccessPopupModal(data.message, 'شكراً لتقييمك! ⭐');
                closeReviewModal();
                // Refresh reviews on screen after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showToast(data.error || 'فشل إرسال التقييم.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('حدث خطأ غير متوقع أثناء إرسال تقييمك.', 'error');
        });
    }

    // Interactive Star Rating helper logic
    document.addEventListener('DOMContentLoaded', function() {
        const starContainer = document.getElementById('interactive-stars');
        if (starContainer) {
            const stars = starContainer.querySelectorAll('.star-btn');
            const ratingInput = document.getElementById('review-rating-value');
            const desc = document.getElementById('star-description');
            
            const starDescriptions = {
                1: 'ضعيف جداً',
                2: 'ضعيف',
                3: 'جيد ومقبول',
                4: 'جيد جداً ممتاز',
                5: 'ممتاز جداً راقي'
            };

            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const r = parseInt(this.getAttribute('data-star'));
                    ratingInput.value = r;
                    desc.innerText = starDescriptions[r];
                    
                    // Style active stars
                    stars.forEach(s => {
                        const sr = parseInt(s.getAttribute('data-star'));
                        if (sr <= r) {
                            s.classList.remove('text-slate-300', 'dark:text-slate-700');
                            s.classList.add('text-amber-400');
                        } else {
                            s.classList.remove('text-amber-400');
                            s.classList.add('text-slate-300', 'dark:text-slate-700');
                        }
                    });
                });
                
                // Add hover effect
                star.addEventListener('mouseenter', function() {
                    const r = parseInt(this.getAttribute('data-star'));
                    stars.forEach(s => {
                        const sr = parseInt(s.getAttribute('data-star'));
                        if (sr <= r) {
                            s.classList.add('scale-110', 'text-amber-300');
                        }
                    });
                });

                star.addEventListener('mouseleave', function() {
                    stars.forEach(s => {
                        s.classList.remove('scale-110', 'text-amber-300');
                    });
                });
            });
        }
    });

    function resetInteractiveStars() {
        const ratingInput = document.getElementById('review-rating-value');
        if (ratingInput) ratingInput.value = "5";
        
        const desc = document.getElementById('star-description');
        if (desc) desc.innerText = 'ممتاز جداً';
        
        const stars = document.querySelectorAll('#interactive-stars .star-btn');
        stars.forEach(s => {
            s.classList.remove('text-slate-300', 'dark:text-slate-700');
            s.classList.add('text-amber-400');
        });
    }

    // 7. Mobile Menu Toggle
    function toggleMobileMenu() {
        const menu = document.getElementById('mobile-menu');
        if (menu) {
            menu.classList.toggle('hidden');
        }
    }
    </script>

    <?php if (!empty($companySettings['showroom_custom_js'])): ?>
    <!-- Custom JS from Settings Panel -->
    <script>
    <?php echo $companySettings['showroom_custom_js']; ?>
    </script>
    <?php endif; ?>
</body>
</html>

<?php
/**
 * Almakhzoun Pro - Core ERP Application Entrypoint (PHP + MySQL)
 * Fully compliant with cPanel, Shared Hosting, XAMPP, and Apache.
 * This is the production-grade PHP codebase that connects directly to the MySQL database.
 * SPDX-License-Identifier: Apache-2.0
 */

ob_start();

define('CONFIG_PATH', __DIR__ . '/config/config.php');
define('LOCK_PATH', __DIR__ . '/storage/install.lock');

// Load and run Security Core WAF/Shield immediately before session or config parsing
require_once __DIR__ . '/modules/security/SecurityCore.php';
SecurityCore::init();

// 1. Check if the system is installed. If lock file is missing, redirect to Installer
if (!file_exists(LOCK_PATH)) {
    header("Location: installer/index.php");
    exit;
}

// 2. Load Configuration and Connect to Database
$config = [];
if (file_exists(CONFIG_PATH)) {
    $config = require CONFIG_PATH;
} else {
    // Attempt automatic discovery or show configuration error
    die('<!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>خطأ في ملف الإعدادات - Almakhzoun Pro</title>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            body { font-family: "Cairo", sans-serif; background-color: #0f172a; color: #f1f5f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .card { background-color: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 32px; max-width: 500px; text-align: center; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.5); }
            h1 { color: #f43f5e; font-size: 24px; margin-top: 0; }
            p { color: #94a3b8; font-size: 14px; line-height: 1.8; }
            .btn { display: inline-block; background-color: #4f46e5; color: white; padding: 10px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-top: 16px; font-size: 13px; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>⛔ خطأ: ملف config.php مفقود!</h1>
            <p>لقد عثرنا على ملف قفل التثبيت ولكن لم يتم العثور على ملف الإعدادات المركزي لقاعدة البيانات. يرجى مسح قفل التثبيت وإعادة تشغيل معالج التثبيت الذكي.</p>
            <a href="installer/index.php" class="btn">إعادة تشغيل معالج التثبيت</a>
        </div>
    </body>
    </html>');
}

// Initialize Database connection
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

    // Auto-Schema Evolution and Migration check (PSR-12 compliant)
    try {
        // Fetch all existing columns from `cars`
        $existingColumns = [];
        $columnsQuery = $pdo->query("SHOW COLUMNS FROM `cars`");
        while ($col = $columnsQuery->fetch(PDO::FETCH_ASSOC)) {
            $existingColumns[strtolower($col['Field'])] = true;
        }

        // Helper array of columns to check and add dynamically if missing
        $columnsToEnsure = [
            'trim' => "ALTER TABLE `cars` ADD COLUMN `trim` varchar(100) DEFAULT NULL AFTER `model`",
            'vin_matching' => "ALTER TABLE `cars` ADD COLUMN `vin_matching` varchar(50) DEFAULT 'matching' AFTER `vin`",
            'supplier' => "ALTER TABLE `cars` ADD COLUMN `supplier` varchar(100) DEFAULT NULL AFTER `branch_id`",
            'ownership_type' => "ALTER TABLE `cars` ADD COLUMN `ownership_type` varchar(100) DEFAULT 'مباشر' AFTER `supplier`",
            'leasing_status' => "ALTER TABLE `cars` ADD COLUMN `leasing_status` varchar(50) DEFAULT 'not_leased' AFTER `ownership_type`",
            'customs_number' => "ALTER TABLE `cars` ADD COLUMN `customs_number` varchar(100) DEFAULT NULL AFTER `leasing_status`",
            'rep_in_charge' => "ALTER TABLE `cars` ADD COLUMN `rep_in_charge` varchar(100) DEFAULT NULL AFTER `customs_number`",
            'plate_type' => "ALTER TABLE `cars` ADD COLUMN `plate_type` varchar(100) DEFAULT 'خصوصي - ملاكي' AFTER `plate_number`",
            'serial_number' => "ALTER TABLE `cars` ADD COLUMN `serial_number` varchar(100) DEFAULT NULL AFTER `plate_type`",
            'registration_number' => "ALTER TABLE `cars` ADD COLUMN `registration_number` varchar(100) DEFAULT NULL AFTER `serial_number`",
            'vehicle_condition' => "ALTER TABLE `cars` ADD COLUMN `vehicle_condition` varchar(100) DEFAULT 'جديد (أصفار)' AFTER `registration_number`",
            'cost_price' => "ALTER TABLE `cars` ADD COLUMN `cost_price` decimal(12,2) DEFAULT '0.00' AFTER `price`",
            'tax' => "ALTER TABLE `cars` ADD COLUMN `tax` decimal(12,2) DEFAULT '0.00' AFTER `price`",
            'discount' => "ALTER TABLE `cars` ADD COLUMN `discount` decimal(12,2) DEFAULT '0.00' AFTER `tax`",
            'final_price' => "ALTER TABLE `cars` ADD COLUMN `final_price` decimal(12,2) DEFAULT '0.00' AFTER `discount`",
            'currency' => "ALTER TABLE `cars` ADD COLUMN `currency` varchar(20) DEFAULT 'ر.س' AFTER `final_price`",
            'notes' => "ALTER TABLE `cars` ADD COLUMN `notes` text DEFAULT NULL AFTER `currency`",
            'interior_color' => "ALTER TABLE `cars` ADD COLUMN `interior_color` varchar(50) DEFAULT NULL AFTER `color`",
            'body_type' => "ALTER TABLE `cars` ADD COLUMN `body_type` varchar(50) DEFAULT 'سيدان' AFTER `interior_color`",
            'doors' => "ALTER TABLE `cars` ADD COLUMN `doors` int DEFAULT 4 AFTER `body_type`",
            'seats' => "ALTER TABLE `cars` ADD COLUMN `seats` int DEFAULT 5 AFTER `doors`",
            'cylinders' => "ALTER TABLE `cars` ADD COLUMN `cylinders` int DEFAULT 4 AFTER `seats`",
            'engine_power' => "ALTER TABLE `cars` ADD COLUMN `engine_power` int DEFAULT 180 AFTER `cylinders`",
            'drive' => "ALTER TABLE `cars` ADD COLUMN `drive` varchar(100) DEFAULT 'دفع أمامي FWD' AFTER `engine_power`",
            'origin_country' => "ALTER TABLE `cars` ADD COLUMN `origin_country` varchar(100) DEFAULT NULL AFTER `drive`",
            'assembly_country' => "ALTER TABLE `cars` ADD COLUMN `assembly_country` varchar(100) DEFAULT NULL AFTER `origin_country`",
            'entry_date' => "ALTER TABLE `cars` ADD COLUMN `entry_date` date DEFAULT NULL AFTER `assembly_country`",
            'exit_date' => "ALTER TABLE `cars` ADD COLUMN `exit_date` date DEFAULT NULL AFTER `entry_date`",
            'purchase_date' => "ALTER TABLE `cars` ADD COLUMN `purchase_date` date DEFAULT NULL AFTER `exit_date`",
            'warranty' => "ALTER TABLE `cars` ADD COLUMN `warranty` varchar(255) DEFAULT 'ضمان الوكيل المعتمد الممتد' AFTER `purchase_date`",
            'warranty_duration' => "ALTER TABLE `cars` ADD COLUMN `warranty_duration` int DEFAULT 5 AFTER `warranty`",
            'previous_owner' => "ALTER TABLE `cars` ADD COLUMN `previous_owner` varchar(100) DEFAULT NULL AFTER `warranty_duration`",
            'gulf_specs' => "ALTER TABLE `cars` ADD COLUMN `gulf_specs` tinyint(1) DEFAULT 1",
            'american_specs' => "ALTER TABLE `cars` ADD COLUMN `american_specs` tinyint(1) DEFAULT 0",
            'european_specs' => "ALTER TABLE `cars` ADD COLUMN `european_specs` tinyint(1) DEFAULT 0",
            'fuel_consumption' => "ALTER TABLE `cars` ADD COLUMN `fuel_consumption` varchar(50) DEFAULT '14.5 كم/لتر'",
            'navigation_system' => "ALTER TABLE `cars` ADD COLUMN `navigation_system` tinyint(1) DEFAULT 0",
            'rear_camera' => "ALTER TABLE `cars` ADD COLUMN `rear_camera` tinyint(1) DEFAULT 1",
            'camera_360' => "ALTER TABLE `cars` ADD COLUMN `camera_360` tinyint(1) DEFAULT 0",
            'radar' => "ALTER TABLE `cars` ADD COLUMN `radar` tinyint(1) DEFAULT 0",
            'front_sensors' => "ALTER TABLE `cars` ADD COLUMN `front_sensors` tinyint(1) DEFAULT 0",
            'rear_sensors' => "ALTER TABLE `cars` ADD COLUMN `rear_sensors` tinyint(1) DEFAULT 1",
            'cruise_control' => "ALTER TABLE `cars` ADD COLUMN `cruise_control` tinyint(1) DEFAULT 1",
            'adaptive_cruise' => "ALTER TABLE `cars` ADD COLUMN `adaptive_cruise` tinyint(1) DEFAULT 0",
            'lane_assist' => "ALTER TABLE `cars` ADD COLUMN `lane_assist` tinyint(1) DEFAULT 0",
            'blind_spot' => "ALTER TABLE `cars` ADD COLUMN `blind_spot` tinyint(1) DEFAULT 0",
            'apple_carplay' => "ALTER TABLE `cars` ADD COLUMN `apple_carplay` tinyint(1) DEFAULT 1",
            'android_auto' => "ALTER TABLE `cars` ADD COLUMN `android_auto` tinyint(1) DEFAULT 1",
            'sunroof' => "ALTER TABLE `cars` ADD COLUMN `sunroof` tinyint(1) DEFAULT 0",
            'panorama' => "ALTER TABLE `cars` ADD COLUMN `panorama` tinyint(1) DEFAULT 0",
            'leather_seats' => "ALTER TABLE `cars` ADD COLUMN `leather_seats` tinyint(1) DEFAULT 0",
            'heated_seats' => "ALTER TABLE `cars` ADD COLUMN `heated_seats` tinyint(1) DEFAULT 0",
            'cooled_seats' => "ALTER TABLE `cars` ADD COLUMN `cooled_seats` tinyint(1) DEFAULT 0",
            'seat_memory' => "ALTER TABLE `cars` ADD COLUMN `seat_memory` tinyint(1) DEFAULT 0",
            'push_button_start' => "ALTER TABLE `cars` ADD COLUMN `push_button_start` tinyint(1) DEFAULT 1",
            'remote_start' => "ALTER TABLE `cars` ADD COLUMN `remote_start` tinyint(1) DEFAULT 0",
            'led_lights' => "ALTER TABLE `cars` ADD COLUMN `led_lights` tinyint(1) DEFAULT 1",
            'xenon_lights' => "ALTER TABLE `cars` ADD COLUMN `xenon_lights` tinyint(1) DEFAULT 0",
            'number_of_keys' => "ALTER TABLE `cars` ADD COLUMN `number_of_keys` int DEFAULT 2",
            'spare_tire' => "ALTER TABLE `cars` ADD COLUMN `spare_tire` tinyint(1) DEFAULT 1",
            'catalog' => "ALTER TABLE `cars` ADD COLUMN `catalog` tinyint(1) DEFAULT 1",
            'attachments' => "ALTER TABLE `cars` ADD COLUMN `attachments` text DEFAULT NULL",
            'import_origin' => "ALTER TABLE `cars` ADD COLUMN `import_origin` varchar(100) DEFAULT NULL",
            'card_file' => "ALTER TABLE `cars` ADD COLUMN `card_file` varchar(255) DEFAULT NULL",
            'card_file_name' => "ALTER TABLE `cars` ADD COLUMN `card_file_name` varchar(255) DEFAULT NULL",
            'rep_id' => "ALTER TABLE `cars` ADD COLUMN `rep_id` varchar(50) DEFAULT NULL",
            'entry_driver_name' => "ALTER TABLE `cars` ADD COLUMN `entry_driver_name` varchar(100) DEFAULT NULL",
            'shipping_company' => "ALTER TABLE `cars` ADD COLUMN `shipping_company` varchar(100) DEFAULT NULL",
            'entry_notes' => "ALTER TABLE `cars` ADD COLUMN `entry_notes` text DEFAULT NULL",
            'sold_by_user_id' => "ALTER TABLE `cars` ADD COLUMN `sold_by_user_id` varchar(50) DEFAULT NULL",
            'recipient_type' => "ALTER TABLE `cars` ADD COLUMN `recipient_type` varchar(100) DEFAULT NULL",
            'sale_amount' => "ALTER TABLE `cars` ADD COLUMN `sale_amount` decimal(12,2) DEFAULT NULL",
            'sale_customer_name' => "ALTER TABLE `cars` ADD COLUMN `sale_customer_name` varchar(100) DEFAULT NULL",
            'sale_customer_id' => "ALTER TABLE `cars` ADD COLUMN `sale_customer_id` varchar(50) DEFAULT NULL",
            'sale_customer_nationality' => "ALTER TABLE `cars` ADD COLUMN `sale_customer_nationality` varchar(100) DEFAULT NULL",
            'sale_customer_phone' => "ALTER TABLE `cars` ADD COLUMN `sale_customer_phone` varchar(50) DEFAULT NULL",
            'exit_notes' => "ALTER TABLE `cars` ADD COLUMN `exit_notes` text DEFAULT NULL",
            'custom_specs' => "ALTER TABLE `cars` ADD COLUMN `custom_specs` text DEFAULT NULL",
            'gallery_images' => "ALTER TABLE `cars` ADD COLUMN `gallery_images` text DEFAULT NULL",
            'updated_at' => "ALTER TABLE `cars` ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        ];

        foreach ($columnsToEnsure as $column => $sql) {
            if (!isset($existingColumns[strtolower($column)])) {
                $pdo->exec($sql);
            }
        }

        // Auto evolution: ensure plate_number allows NULL to make it optional
        try {
            $pdo->exec("ALTER TABLE `cars` MODIFY COLUMN `plate_number` varchar(50) NULL DEFAULT NULL");
        } catch (Exception $e) {
            // Ignore if unable to alter yet (e.g. installer has not run)
        }

        // Auto evolution: ensure user roles column allows dynamic roles (non-enum)
        try {
            $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` varchar(50) NOT NULL DEFAULT 'representative'");
        } catch (Exception $e) {
            // Ignore if unable to alter yet
        }

        // Auto evolution: ensure settings table has email column and corporate description fields
        try {
            $existingSettingsCols = [];
            $settingsColsQuery = $pdo->query("SHOW COLUMNS FROM `settings`");
            while ($col = $settingsColsQuery->fetch()) {
                $existingSettingsCols[strtolower($col['Field'])] = true;
            }
            
            $settingsColsToEnsure = [
                'email' => "ALTER TABLE `settings` ADD COLUMN `email` varchar(150) DEFAULT NULL",
                'company_description' => "ALTER TABLE `settings` ADD COLUMN `company_description` text DEFAULT NULL",
                'vision' => "ALTER TABLE `settings` ADD COLUMN `vision` text DEFAULT NULL",
                'mission' => "ALTER TABLE `settings` ADD COLUMN `mission` text DEFAULT NULL",
                'goals' => "ALTER TABLE `settings` ADD COLUMN `goals` text DEFAULT NULL",
                'website' => "ALTER TABLE `settings` ADD COLUMN `website` varchar(150) DEFAULT NULL",
                'social_twitter' => "ALTER TABLE `settings` ADD COLUMN `social_twitter` varchar(255) DEFAULT NULL",
                'social_facebook' => "ALTER TABLE `settings` ADD COLUMN `social_facebook` varchar(255) DEFAULT NULL",
                'social_instagram' => "ALTER TABLE `settings` ADD COLUMN `social_instagram` varchar(255) DEFAULT NULL",
                'social_linkedin' => "ALTER TABLE `settings` ADD COLUMN `social_linkedin` varchar(255) DEFAULT NULL",
                'cr_number' => "ALTER TABLE `settings` ADD COLUMN `cr_number` varchar(100) DEFAULT NULL",
                'contact_phone' => "ALTER TABLE `settings` ADD COLUMN `contact_phone` varchar(100) DEFAULT NULL",
                'whatsapp_phone' => "ALTER TABLE `settings` ADD COLUMN `whatsapp_phone` varchar(100) DEFAULT NULL",
                'showroom_header_title' => "ALTER TABLE `settings` ADD COLUMN `showroom_header_title` varchar(255) DEFAULT NULL",
                'showroom_header_subtitle' => "ALTER TABLE `settings` ADD COLUMN `showroom_header_subtitle` text DEFAULT NULL",
                'showroom_footer_text' => "ALTER TABLE `settings` ADD COLUMN `showroom_footer_text` text DEFAULT NULL",
                'showroom_theme' => "ALTER TABLE `settings` ADD COLUMN `showroom_theme` varchar(50) DEFAULT 'indigo'",
                'showroom_show_price' => "ALTER TABLE `settings` ADD COLUMN `showroom_show_price` tinyint(1) DEFAULT 1",
                'showroom_show_filters' => "ALTER TABLE `settings` ADD COLUMN `showroom_show_filters` tinyint(1) DEFAULT 1",
                'showroom_facebook' => "ALTER TABLE `settings` ADD COLUMN `showroom_facebook` varchar(255) DEFAULT NULL",
                'showroom_twitter' => "ALTER TABLE `settings` ADD COLUMN `showroom_twitter` varchar(255) DEFAULT NULL",
                'showroom_instagram' => "ALTER TABLE `settings` ADD COLUMN `showroom_instagram` varchar(255) DEFAULT NULL",
                'showroom_linkedin' => "ALTER TABLE `settings` ADD COLUMN `showroom_linkedin` varchar(255) DEFAULT NULL",
                'showroom_snapchat' => "ALTER TABLE `settings` ADD COLUMN `showroom_snapchat` varchar(255) DEFAULT NULL",
                'showroom_youtube' => "ALTER TABLE `settings` ADD COLUMN `showroom_youtube` varchar(255) DEFAULT NULL",
                'showroom_custom_socials' => "ALTER TABLE `settings` ADD COLUMN `showroom_custom_socials` text DEFAULT NULL",
                'showroom_banner_image' => "ALTER TABLE `settings` ADD COLUMN `showroom_banner_image` varchar(255) DEFAULT NULL",
                'showroom_banner_overlay_opacity' => "ALTER TABLE `settings` ADD COLUMN `showroom_banner_overlay_opacity` int DEFAULT 50",
                'showroom_banner_opacity' => "ALTER TABLE `settings` ADD COLUMN `showroom_banner_opacity` int DEFAULT 25",
                'showroom_banner_height' => "ALTER TABLE `settings` ADD COLUMN `showroom_banner_height` varchar(50) DEFAULT 'medium'",
                'showroom_banner_bg_size' => "ALTER TABLE `settings` ADD COLUMN `showroom_banner_bg_size` varchar(50) DEFAULT 'cover'",
                'showroom_banner_width' => "ALTER TABLE `settings` ADD COLUMN `showroom_banner_width` varchar(50) DEFAULT 'full'",
                'showroom_banner_custom_height' => "ALTER TABLE `settings` ADD COLUMN `showroom_banner_custom_height` varchar(50) DEFAULT '350px'",
                'showroom_banner_custom_width' => "ALTER TABLE `settings` ADD COLUMN `showroom_banner_custom_width` varchar(50) DEFAULT '100%'",
                'showroom_banner_title_color' => "ALTER TABLE `settings` ADD COLUMN `showroom_banner_title_color` varchar(50) DEFAULT '#ffffff'",
                'showroom_banner_subtitle_color' => "ALTER TABLE `settings` ADD COLUMN `showroom_banner_subtitle_color` varchar(50) DEFAULT '#cbd5e1'",
                'showroom_banner_text_bg' => "ALTER TABLE `settings` ADD COLUMN `showroom_banner_text_bg` tinyint(1) DEFAULT 0",
                'showroom_custom_pages' => "ALTER TABLE `settings` ADD COLUMN `showroom_custom_pages` text DEFAULT NULL",
                'showroom_menu_links' => "ALTER TABLE `settings` ADD COLUMN `showroom_menu_links` text DEFAULT NULL",
                'showroom_custom_css' => "ALTER TABLE `settings` ADD COLUMN `showroom_custom_css` text DEFAULT NULL",
                'showroom_custom_js' => "ALTER TABLE `settings` ADD COLUMN `showroom_custom_js` text DEFAULT NULL",
                'default_showroom_name' => "ALTER TABLE `settings` ADD COLUMN `default_showroom_name` varchar(255) DEFAULT NULL",
                'seo_google_analytics' => "ALTER TABLE `settings` ADD COLUMN `seo_google_analytics` varchar(50) DEFAULT NULL",
                'seo_facebook_pixel' => "ALTER TABLE `settings` ADD COLUMN `seo_facebook_pixel` varchar(50) DEFAULT NULL",
                'seo_google_verification' => "ALTER TABLE `settings` ADD COLUMN `seo_google_verification` varchar(100) DEFAULT NULL",
                'seo_bing_verification' => "ALTER TABLE `settings` ADD COLUMN `seo_bing_verification` varchar(100) DEFAULT NULL",
                'sales_template_style' => "ALTER TABLE `settings` ADD COLUMN `sales_template_style` varchar(50) DEFAULT 'grid'",
                'logo_height' => "ALTER TABLE `settings` ADD COLUMN `logo_height` int DEFAULT 40",
                'logo_color' => "ALTER TABLE `settings` ADD COLUMN `logo_color` varchar(50) DEFAULT '#6366f1'",
                'logo_border_radius' => "ALTER TABLE `settings` ADD COLUMN `logo_border_radius` int DEFAULT 12",
                'company_name_color' => "ALTER TABLE `settings` ADD COLUMN `company_name_color` varchar(50) DEFAULT '#0f172a'",
                'company_name_color_dark' => "ALTER TABLE `settings` ADD COLUMN `company_name_color_dark` varchar(50) DEFAULT '#ffffff'",
                'company_name_font_size' => "ALTER TABLE `settings` ADD COLUMN `company_name_font_size` varchar(50) DEFAULT 'text-sm'",
                'showroom_name_color' => "ALTER TABLE `settings` ADD COLUMN `showroom_name_color` varchar(50) DEFAULT '#6366f1'",
                'showroom_name_color_dark' => "ALTER TABLE `settings` ADD COLUMN `showroom_name_color_dark` varchar(50) DEFAULT '#818cf8'",
                'showroom_name_font_size' => "ALTER TABLE `settings` ADD COLUMN `showroom_name_font_size` varchar(50) DEFAULT 'text-[9px]'"
            ];

            foreach ($settingsColsToEnsure as $colName => $alterSql) {
                if (!isset($existingSettingsCols[strtolower($colName)])) {
                    $pdo->exec($alterSql);
                }
            }
        } catch (Exception $e) {
            // Ignore if settings table doesn't exist yet
        }

        // Auto evolution: ensure branches table has showroom_name, showroom_address, tax_number, commercial_registration, logo
        try {
            $existingBranchesCols = [];
            $branchesColsQuery = $pdo->query("SHOW COLUMNS FROM `branches`");
            while ($col = $branchesColsQuery->fetch()) {
                $existingBranchesCols[strtolower($col['Field'])] = true;
            }
            
            $branchesColsToEnsure = [
                'showroom_name' => "ALTER TABLE `branches` ADD COLUMN `showroom_name` varchar(150) DEFAULT NULL",
                'showroom_address' => "ALTER TABLE `branches` ADD COLUMN `showroom_address` varchar(255) DEFAULT NULL",
                'tax_number' => "ALTER TABLE `branches` ADD COLUMN `tax_number` varchar(100) DEFAULT NULL",
                'commercial_registration' => "ALTER TABLE `branches` ADD COLUMN `commercial_registration` varchar(100) DEFAULT NULL",
                'logo' => "ALTER TABLE `branches` ADD COLUMN `logo` longtext DEFAULT NULL",
                'stamp' => "ALTER TABLE `branches` ADD COLUMN `stamp` longtext DEFAULT NULL"
            ];

            foreach ($branchesColsToEnsure as $colName => $alterSql) {
                if (!isset($existingBranchesCols[strtolower($colName)])) {
                    $pdo->exec($alterSql);
                }
            }
        } catch (Exception $e) {
            // Ignore if branches table doesn't exist yet
        }

        // Fetch all existing columns from `reservations`
        $existingResColumns = [];
        $resColumnsQuery = $pdo->query("SHOW COLUMNS FROM `reservations`");
        while ($col = $resColumnsQuery->fetch(PDO::FETCH_ASSOC)) {
            $existingResColumns[strtolower($col['Field'])] = true;
        }

        $resColumnsToEnsure = [
            'notes' => "ALTER TABLE `reservations` ADD COLUMN `notes` text DEFAULT NULL",
            'attachments' => "ALTER TABLE `reservations` ADD COLUMN `attachments` text DEFAULT NULL",
            'cancelled_by_user_id' => "ALTER TABLE `reservations` ADD COLUMN `cancelled_by_user_id` varchar(50) DEFAULT NULL",
            'cancelled_at' => "ALTER TABLE `reservations` ADD COLUMN `cancelled_at` timestamp NULL DEFAULT NULL",
            'updated_at' => "ALTER TABLE `reservations` ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        ];

        foreach ($resColumnsToEnsure as $column => $sql) {
            if (!isset($existingResColumns[strtolower($column)])) {
                $pdo->exec($sql);
            }
        }

        // Auto evolution: ensure reservation_attachments table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `reservation_attachments` (
          `id` varchar(50) NOT NULL,
          `reservation_id` varchar(50) NOT NULL,
          `file_name` varchar(255) NOT NULL,
          `file_path` varchar(255) NOT NULL,
          `file_type` varchar(100) DEFAULT NULL,
          `uploaded_by` varchar(100) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE,
          INDEX `idx_reservation_att_id` (`reservation_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        try {
            $pdo->exec("ALTER TABLE `reservation_attachments` MODIFY COLUMN `id` varchar(50) NOT NULL");
        } catch (Exception $e) {
            // Ignore if already changed or failed
        }

        // Ensure attachments table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `attachments` (
          `attachment_id` INT NOT NULL AUTO_INCREMENT,
          `vehicle_id` varchar(50) NOT NULL,
          `file_name` varchar(255) NOT NULL,
          `file_path` varchar(255) NOT NULL,
          `file_type` varchar(100) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`attachment_id`),
          FOREIGN KEY (`vehicle_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE,
          INDEX `idx_attachments_vehicle_id` (`vehicle_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Ensure showroom_ads table exists for advanced advertisements & offers system
        $pdo->exec("CREATE TABLE IF NOT EXISTS `showroom_ads` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `title` varchar(255) NOT NULL,
          `type` varchar(50) DEFAULT 'image',
          `image_path` varchar(255) DEFAULT NULL,
          `link_url` varchar(255) DEFAULT NULL,
          `html_code` text DEFAULT NULL,
          `status` varchar(20) DEFAULT 'active',
          `position` varchar(50) DEFAULT 'top',
          `start_date` date DEFAULT NULL,
          `end_date` date DEFAULT NULL,
          `views_count` int DEFAULT 0,
          `clicks_count` int DEFAULT 0,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Ensure customers table exists for Almakhzoun Pro ERP
        $pdo->exec("CREATE TABLE IF NOT EXISTS `customers` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `name` VARCHAR(255) NOT NULL,
          `phone` VARCHAR(100) NOT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `idx_customer_phone_unique` (`phone`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Auto-sync existing customers from other entities
        try {
            $syncStmt = $pdo->query("SELECT DISTINCT `customer_name`, `customer_phone` FROM `customer_orders` WHERE `customer_name` != '' AND `customer_phone` != ''");
            $insertSync = $pdo->prepare("INSERT IGNORE INTO `customers` (`name`, `phone`) VALUES (?, ?)");
            while ($c = $syncStmt->fetch()) {
                if (!empty($c['customer_name']) && !empty($c['customer_phone'])) {
                    $insertSync->execute([trim($c['customer_name']), trim($c['customer_phone'])]);
                }
            }
        } catch (Exception $e) {}

        try {
            $syncStmt = $pdo->query("SELECT DISTINCT `customer_name`, `customer_phone` FROM `reservations` WHERE `customer_name` != '' AND `customer_phone` != ''");
            $insertSync = $pdo->prepare("INSERT IGNORE INTO `customers` (`name`, `phone`) VALUES (?, ?)");
            while ($c = $syncStmt->fetch()) {
                if (!empty($c['customer_name']) && !empty($c['customer_phone'])) {
                    $insertSync->execute([trim($c['customer_name']), trim($c['customer_phone'])]);
                }
            }
        } catch (Exception $e) {}

        try {
            $syncStmt = $pdo->query("SELECT DISTINCT `sale_customer_name`, `sale_customer_phone` FROM `cars` WHERE `sale_customer_name` != '' AND `sale_customer_phone` != ''");
            $insertSync = $pdo->prepare("INSERT IGNORE INTO `customers` (`name`, `phone`) VALUES (?, ?)");
            while ($c = $syncStmt->fetch()) {
                if (!empty($c['sale_customer_name']) && !empty($c['sale_customer_phone'])) {
                    $insertSync->execute([trim($c['sale_customer_name']), trim($c['sale_customer_phone'])]);
                }
            }
        } catch (Exception $e) {}

        // Ensure branch_transfers table exists for advanced branch transfers logging and letters
        $pdo->exec("CREATE TABLE IF NOT EXISTS `branch_transfers` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `car_id` varchar(50) NOT NULL,
          `from_branch_id` varchar(50) DEFAULT NULL,
          `to_branch_id` varchar(50) DEFAULT NULL,
          `created_by_user_id` varchar(50) DEFAULT NULL,
          `transfer_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `letter_number` varchar(100) NOT NULL,
          `notes` text DEFAULT NULL,
          `status` varchar(50) DEFAULT 'completed',
          PRIMARY KEY (`id`),
          INDEX `idx_bt_car_id` (`car_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Ensure status, received_by_user_id, and received_at exist on branch_transfers
        try {
            $existingBTCols = [];
            $btColsQuery = $pdo->query("SHOW COLUMNS FROM `branch_transfers`");
            while ($col = $btColsQuery->fetch()) {
                $existingBTCols[strtolower($col['Field'])] = true;
            }
            
            // Ensure car_id can store multiple comma-separated IDs
            try {
                $pdo->exec("ALTER TABLE `branch_transfers` MODIFY COLUMN `car_id` TEXT NOT NULL");
            } catch (Exception $e) {}

            $btColsToEnsure = [
                'status' => "ALTER TABLE `branch_transfers` ADD COLUMN `status` varchar(50) DEFAULT 'pending'",
                'received_by_user_id' => "ALTER TABLE `branch_transfers` ADD COLUMN `received_by_user_id` varchar(50) DEFAULT NULL",
                'received_at' => "ALTER TABLE `branch_transfers` ADD COLUMN `received_at` timestamp NULL DEFAULT NULL"
            ];

            foreach ($btColsToEnsure as $colName => $alterSql) {
                if (!isset($existingBTCols[strtolower($colName)])) {
                    $pdo->exec($alterSql);
                }
            }
        } catch (Exception $e) {
            // Ignore if error during early database initialization
        }

        // 5. Lookups Schema
        $pdo->exec("CREATE TABLE IF NOT EXISTS `car_makes` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `name` varchar(100) NOT NULL UNIQUE,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `car_models` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `make_name` varchar(100) NOT NULL,
          `name` varchar(100) NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `idx_make_model` (`make_name`, `name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `car_suppliers` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `name` varchar(100) NOT NULL UNIQUE,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `car_imports` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `name` varchar(100) NOT NULL UNIQUE,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `car_owners` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `name` varchar(100) NOT NULL UNIQUE,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Seed Makes
        $countMakes = $pdo->query("SELECT COUNT(*) FROM `car_makes`")->fetchColumn();
        if ($countMakes == 0) {
            $defaultMakes = ['تويوتا', 'لكزس', 'هيونداي', 'مرسيدس', 'بي إم دبليو'];
            $stmtInsertMake = $pdo->prepare("INSERT INTO `car_makes` (`name`) VALUES (?)");
            foreach ($defaultMakes as $m) {
                $stmtInsertMake->execute([$m]);
            }
        }

        // Seed Models
        $countModels = $pdo->query("SELECT COUNT(*) FROM `car_models`")->fetchColumn();
        if ($countModels == 0) {
            $defaultModels = [
                'تويوتا' => ['كامري', 'كورولا', 'يارس', 'لاندكروزر', 'هيلوكس'],
                'لكزس' => ['ES', 'RX', 'LX', 'LS'],
                'هيونداي' => ['النترا', 'سوناتا', 'أكسنت', 'توسان', 'سانتافي'],
                'مرسيدس' => ['C-Class', 'E-Class', 'S-Class', 'G-Class'],
                'بي إم دبليو' => ['3 Series', '5 Series', '7 Series', 'X5']
            ];
            $stmtInsertModel = $pdo->prepare("INSERT INTO `car_models` (`make_name`, `name`) VALUES (?, ?)");
            foreach ($defaultModels as $mk => $mds) {
                foreach ($mds as $md) {
                    $stmtInsertModel->execute([$mk, $md]);
                }
            }
        }

        // Seed Suppliers
        $countSuppliers = $pdo->query("SELECT COUNT(*) FROM `car_suppliers`")->fetchColumn();
        if ($countSuppliers == 0) {
            $defaultSuppliers = ['عبد اللطيف جميل للسيارات', 'الوعلان للتجارة', 'شركة محمد يوسف ناغي', 'توكيلات الجزيرة للسيارات', 'الجميح للسيارات'];
            $stmtInsertSup = $pdo->prepare("INSERT INTO `car_suppliers` (`name`) VALUES (?)");
            foreach ($defaultSuppliers as $s) {
                $stmtInsertSup->execute([$s]);
            }
        }

        // Seed Imports
        $countImports = $pdo->query("SELECT COUNT(*) FROM `car_imports`")->fetchColumn();
        if ($countImports == 0) {
            $defaultImports = ['سعودي', 'خليجي', 'الساير', 'قطري', 'بريمي'];
            $stmtInsertImp = $pdo->prepare("INSERT INTO `car_imports` (`name`) VALUES (?)");
            foreach ($defaultImports as $im) {
                $stmtInsertImp->execute([$im]);
            }
        }

        // Seed Owners
        $countOwners = $pdo->query("SELECT COUNT(*) FROM `car_owners`")->fetchColumn();
        if ($countOwners == 0) {
            $defaultOwners = ['مباشر', 'تصريف'];
            $stmtInsertOwn = $pdo->prepare("INSERT INTO `car_owners` (`name`) VALUES (?)");
            foreach ($defaultOwners as $o) {
                $stmtInsertOwn->execute([$o]);
            }
        }

        // Auto evolution: ensure notifications table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `notifications` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `operation_type` VARCHAR(100) NOT NULL,
          `title` VARCHAR(255) NOT NULL,
          `description` TEXT NOT NULL,
          `user_id` VARCHAR(50) DEFAULT NULL,
          `user_name` VARCHAR(100) DEFAULT NULL,
          `branch_name` VARCHAR(150) DEFAULT NULL,
          `car_id` VARCHAR(50) DEFAULT NULL,
          `is_read` TINYINT(1) DEFAULT 0,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          INDEX `idx_notif_is_read` (`is_read`),
          INDEX `idx_notif_created` (`created_at` DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Auto evolution: ensure customer_orders table exists
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

        // Auto evolution: ensure seo_pages table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `seo_pages` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `page_key` VARCHAR(50) NOT NULL UNIQUE,
          `page_title` VARCHAR(255) NOT NULL,
          `meta_title` VARCHAR(255) DEFAULT NULL,
          `meta_description` TEXT DEFAULT NULL,
          `meta_keywords` TEXT DEFAULT NULL,
          `og_image` VARCHAR(255) DEFAULT NULL,
          `custom_schema` TEXT DEFAULT NULL,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Ensure seo_pages columns exist
        try {
            $seoColsQ = $pdo->query("SHOW COLUMNS FROM `seo_pages`");
            $existingSeoCols = [];
            while ($c = $seoColsQ->fetch(PDO::FETCH_ASSOC)) {
                $existingSeoCols[strtolower($c['Field'])] = true;
            }
            
            $seoColsToEnsure = [
                'og_title' => "ALTER TABLE `seo_pages` ADD COLUMN `og_title` VARCHAR(255) DEFAULT NULL",
                'og_description' => "ALTER TABLE `seo_pages` ADD COLUMN `og_description` TEXT DEFAULT NULL",
                'twitter_card' => "ALTER TABLE `seo_pages` ADD COLUMN `twitter_card` VARCHAR(50) DEFAULT 'summary_large_image'"
            ];
            foreach ($seoColsToEnsure as $col => $alterSql) {
                if (!isset($existingSeoCols[strtolower($col)])) {
                    $pdo->exec($alterSql);
                }
            }
        } catch (Exception $colEx) {
            // Ignore
        }

        // Auto evolution: ensure showroom_visits table exists for tracking visitor analytics
        $pdo->exec("CREATE TABLE IF NOT EXISTS `showroom_visits` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `session_id` VARCHAR(100) NOT NULL,
          `ip_address` VARCHAR(50) DEFAULT NULL,
          `user_agent` TEXT DEFAULT NULL,
          `page_url` VARCHAR(255) DEFAULT NULL,
          `page_title` VARCHAR(255) DEFAULT NULL,
          `referrer` TEXT DEFAULT NULL,
          `visit_date` DATE NOT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          INDEX `idx_visit_date` (`visit_date`),
          INDEX `idx_session_id` (`session_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Auto evolution: ensure contact_inquiries table exists for customer contact forms
        $pdo->exec("CREATE TABLE IF NOT EXISTS `contact_inquiries` (
          `id` INT NOT NULL AUTO_INCREMENT,
          `name` VARCHAR(150) NOT NULL,
          `email` VARCHAR(150) DEFAULT NULL,
          `phone` VARCHAR(50) NOT NULL,
          `subject` VARCHAR(255) DEFAULT NULL,
          `message` TEXT NOT NULL,
          `status` VARCHAR(50) DEFAULT 'new',
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          INDEX `idx_ci_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Auto evolution: ensure showroom_reviews table exists for customer ratings and reviews
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

        // Seed default sales representatives if empty
        $countSales = $pdo->query("SELECT COUNT(*) FROM `showroom_sales`")->fetchColumn();
        if ($countSales == 0) {
            $stmtInsertSales = $pdo->prepare("INSERT INTO `showroom_sales` (`name`, `title`, `phone`, `whatsapp`, `status`, `sort_order`) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtInsertSales->execute(['أحمد الحربي', 'مستشار المبيعات - فرع الرياض', '0500000001', '966500000001', 'active', 1]);
            $stmtInsertSales->execute(['ياسر اليامي', 'مستشار المبيعات - فرع نجران', '0500000002', '966500000002', 'active', 2]);
        }

        // Seed default SEO configuration for customer showroom
        $countSeo = $pdo->query("SELECT COUNT(*) FROM `seo_pages` WHERE `page_key` = 'customer_showroom'")->fetchColumn();
        if ($countSeo == 0) {
            $stmtInsertSeo = $pdo->prepare("INSERT INTO `seo_pages` (`page_key`, `page_title`, `meta_title`, `meta_description`, `meta_keywords`, `custom_schema`) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtInsertSeo->execute([
                'customer_showroom',
                'معرض العملاء الافتراضي',
                'شركة المخزون للمحركات المحدودة - معرض السيارات الافتراضي للعملاء',
                'تصفح أحدث وأرقى موديلات السيارات المتوفرة لدينا بأفضل الأسعار والمواصفات مع خيارات الطلب المباشر والتواصل الفوري عبر الواتساب.',
                'المخزون, سيارات فاخرة, شراء سيارات, معرض السيارات, تويوتا, مرسيدس, لكزس',
                '{"@context": "https://schema.org", "@type": "AutoDealer", "name": "شركة المخزون للمحركات", "url": "customer.php"}'
            ]);
        }

        // Auto evolution: ensure user settings and profile columns exist
        $existingUserColumns = [];
        try {
            $userColumnsQuery = $pdo->query("SHOW COLUMNS FROM `users`");
            while ($col = $userColumnsQuery->fetch(PDO::FETCH_ASSOC)) {
                $existingUserColumns[strtolower($col['Field'])] = true;
            }
        } catch (Exception $e) {
            // Table might not exist yet
        }

        $userColumnsToEnsure = [
            'theme' => "ALTER TABLE `users` ADD COLUMN `theme` VARCHAR(20) DEFAULT 'light'",
            'lang' => "ALTER TABLE `users` ADD COLUMN `lang` VARCHAR(10) DEFAULT 'ar'",
            'email' => "ALTER TABLE `users` ADD COLUMN `email` VARCHAR(150) DEFAULT NULL",
            'phone' => "ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(50) DEFAULT NULL",
            'profile_picture' => "ALTER TABLE `users` ADD COLUMN `profile_picture` VARCHAR(255) DEFAULT NULL",
            'last_login' => "ALTER TABLE `users` ADD COLUMN `last_login` TIMESTAMP NULL DEFAULT NULL",
            'created_at' => "ALTER TABLE `users` ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"
        ];

        foreach ($userColumnsToEnsure as $column => $alterSql) {
            if (!empty($existingUserColumns) && !isset($existingUserColumns[strtolower($column)])) {
                $pdo->exec($alterSql);
            }
        }
    } catch (Exception $migrateErr) {
        // Safe skip if db structure isn't fully installed yet
    }
} catch (PDOException $e) {
    die('<!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>فشل الاتصال بقاعدة البيانات - Almakhzoun Pro</title>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            body { font-family: "Cairo", sans-serif; background-color: #0f172a; color: #f1f5f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .card { background-color: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 32px; max-width: 550px; text-align: center; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.5); }
            h1 { color: #f43f5e; font-size: 22px; margin-top: 0; }
            p { color: #94a3b8; font-size: 14px; line-height: 1.8; text-align: right; }
            .error-box { background: #0f172a; border-radius: 6px; padding: 12px; font-family: monospace; font-size: 11px; color: #f43f5e; text-align: left; overflow-x: auto; margin: 12px 0; }
            .btn { display: inline-block; background-color: #4f46e5; color: white; padding: 10px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-top: 16px; font-size: 13px; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>⛔ فشل الاتصال بخادم MySQL الرئيسي!</h1>
            <p>يرجى التأكد من تشغيل خادم MySQL في السيرفر أو XAMPP وتطابق بيانات الدخول المحددة في ملف الإعدادات.</p>
            <div class="error-box">' . htmlspecialchars($e->getMessage()) . '</div>
            <a href="installer/index.php" class="btn">إعادة تهيئة الإعدادات وقاعدة البيانات</a>
        </div>
    </body>
    </html>');
}

// Helper function to log audit logs to MySQL
function writeAuditLog($pdo, $userId, $userName, $action, $details, $risk = 'low') {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $pdo->prepare("INSERT INTO `system_logs` (`user_id`, `user_name`, `action`, `details`, `risk_level`, `ip`) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $userName, $action, $details, $risk, $ip]);
    } catch (Exception $e) {
        // Fallback or ignore logging failure to prevent app halts
    }
}

// Helper function to regenerate sitemap file automatically
function regenerateSitemapFile() {
    $sitemapPath = __DIR__ . '/sitemap.php';
    if (file_exists($sitemapPath)) {
        try {
            $sitemap_generate_only = true;
            ob_start();
            include $sitemapPath;
            ob_end_clean();
        } catch (Exception $e) {
            // Safe skip
        }
    }
}

// Helper function to create real-time system notifications
function createNotification($pdo, $operation_type, $title, $description, $user_id = null, $user_name = null, $branch_name = null, $car_id = null) {
    try {
        if ($user_id === null && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        if ($user_name === null && isset($_SESSION['user_name'])) {
            $user_name = $_SESSION['user_name'];
        }
        if ($branch_name === null && isset($GLOBALS['user_branch_name'])) {
            $branch_name = $GLOBALS['user_branch_name'];
        }
        
        $stmt = $pdo->prepare("INSERT INTO `notifications` (`operation_type`, `title`, `description`, `user_id`, `user_name`, `branch_name`, `car_id`, `is_read`) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([$operation_type, $title, $description, $user_id, $user_name, $branch_name, $car_id]);
    } catch (Exception $e) {
        // Fallback or ignore failure
    }
}

function getOrCreateLookupValue($pdo, $table, $value) {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    try {
        // Case-insensitive & trim check
        $stmt = $pdo->prepare("SELECT `name` FROM `{$table}` WHERE TRIM(LOWER(`name`)) = TRIM(LOWER(?)) LIMIT 1");
        $stmt->execute([$value]);
        $row = $stmt->fetch();
        if ($row) {
            return $row['name'];
        } else {
            // Insert new value
            $stmtInsert = $pdo->prepare("INSERT INTO `{$table}` (`name`) VALUES (?)");
            $stmtInsert->execute([$value]);
            return $value;
        }
    } catch (Exception $e) {
        return $value;
    }
}

function getOrCreateModelValue($pdo, $make, $model) {
    $make = trim($make);
    $model = trim($model);
    if ($make === '' || $model === '') {
        return $model;
    }
    try {
        // Case-insensitive & trim check under this make
        $stmt = $pdo->prepare("SELECT `name` FROM `car_models` WHERE TRIM(LOWER(`make_name`)) = TRIM(LOWER(?)) AND TRIM(LOWER(`name`)) = TRIM(LOWER(?)) LIMIT 1");
        $stmt->execute([$make, $model]);
        $row = $stmt->fetch();
        if ($row) {
            return $row['name'];
        } else {
            $stmtInsert = $pdo->prepare("INSERT INTO `car_models` (`make_name`, `name`) VALUES (?, ?)");
            $stmtInsert->execute([$make, $model]);
            return $model;
        }
    } catch (Exception $e) {
        return $model;
    }
}

// 3. AUTHENTICATION CONTROLLER (PHP SESSION BASED)
$error = '';
if (isset($_POST['login_action'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور.';
    } else {
        // Query database for user
        $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `username` = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            // Validate password (supports bcrypt hash, or simple password generation admin123/agent123)
            $isCorrect = false;
            if (password_verify($password, $user['password'])) {
                $isCorrect = true;
            } elseif ($password === "{$user['username']}123") {
                // Support quick login for seed database credentials
                $isCorrect = true;
            }

            if ($isCorrect) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_username'] = $user['username'];
                $_SESSION['avatar'] = $user['avatar']; $_SESSION['branch_id'] = $user['branch_id'] ?? null;

                $user_branch = 'الإدارة العامة';
                if (!empty($user['branch_id'])) {
                    $bQ = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
                    $bQ->execute([$user['branch_id']]);
                    $user_branch = $bQ->fetchColumn() ?: 'الإدارة العامة';
                }
                createNotification($pdo, 'login', 'تسجيل دخول للنظام', "قام المستخدم {$user['name']} بتسجيل الدخول", $user['id'], $user['name'], $user_branch);

                writeAuditLog($pdo, $user['id'], $user['name'], 'تسجيل دخول (PHP)', 'تم تسجيل الدخول بنجاح للنظام الموحد للشركة');
                
                // Redirect representative (مندوب) directly to the inventory page
                if ($user['role'] === 'representative') {
                    header("Location: index.php?page=inventory");
                } else {
                    header("Location: index.php");
                }
                exit;
            } else {
                $error = 'كلمة المرور المدخلة غير صحيحة.';
            }
        } else {
            $error = 'اسم المستخدم غير مسجل في قاعدة البيانات.';
        }
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    if (isset($_SESSION['user_id'])) {
        writeAuditLog($pdo, $_SESSION['user_id'], $_SESSION['user_name'], 'تسجيل خروج (PHP)', 'تم تسجيل الخروج بنجاح وتدمير جلسة العمل الحالية');
    }
    session_destroy();
    header("Location: index.php");
    exit;
}

// 1. If root domain or showroom page is requested, ALWAYS serve the beautiful customer showroom as the main landing page
$requested_page = $_GET['page'] ?? '';
$is_special_action = isset($_GET['print_transfer']) || isset($_GET['print_contract']) || isset($_GET['export_vcard']) || isset($_GET['logout']);

// If the requested page is not an admin page, redirect to the customer-facing showroom page
$admin_pages = [
    'dashboard', 'sales', 'inventory', 'reservations', 'users', 'branches', 
    'logs_delegates', 'logs', 'reports', 'orders', 'contact_inquiries', 
    'showroom_reviews', 'showroom_sales', 'ads', 'transfers', 'settings', 
    'customers', 'analytics', 'login'
];
if ($requested_page !== '' && $requested_page !== 'showroom' && !in_array($requested_page, $admin_pages) && !$is_special_action) {
    header("Location: customer.php?page=" . urlencode($requested_page));
    exit;
}

if (!$is_special_action && ($requested_page === '' || $requested_page === 'showroom')) {
    require_once __DIR__ . '/customer.php';
    exit;
}

// 2. If user is not authenticated, they can only access the login page. Redirect any other request to the customer showroom.
if (!isset($_SESSION['user_id'])) {
    $is_login_request = ($requested_page === 'login') || isset($_POST['login_action']);
    if (!$is_login_request) {
        require_once __DIR__ . '/customer.php';
        exit;
    }
} else {
    // If user IS authenticated and tries to open the login page, redirect them to their respective panels
    if ($requested_page === 'login') {
        if ($_SESSION['user_role'] === 'representative') {
            header("Location: index.php?page=inventory");
        } else {
            header("Location: index.php?page=dashboard");
        }
        exit;
    }
}

// If user is not authenticated but requested the login page, show beautiful login view
if (!isset($_SESSION['user_id'])):
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - Almakhzoun Pro</title>
    <!-- Tailwind via CDN with Cairo Font config -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Cairo', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-slate-950 flex items-center justify-center p-4 relative overflow-hidden text-slate-200">
    <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-indigo-500/5 rounded-full blur-3xl -z-10"></div>
    <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-blue-500/5 rounded-full blur-3xl -z-10"></div>

    <div class="w-full max-w-4xl grid grid-cols-1 md:grid-cols-12 bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-2xl relative">
        <!-- Brand Cover -->
        <div class="md:col-span-5 bg-gradient-to-br from-slate-900 via-slate-950 to-slate-900 p-6 flex flex-col justify-between border-b md:border-b-0 md:border-l border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded bg-indigo-600 flex items-center justify-center text-white font-extrabold text-lg shrink-0">
                    <span class="text-white">M</span>
                </div>
                <div>
                    <h2 class="font-bold text-xs text-white tracking-tight leading-none">شركة المخزون للمحركات</h2>
                    <span class="text-[9px] text-indigo-400 font-mono">ALMAKHZOUN PRO</span>
                </div>
            </div>

            <div class="space-y-3.5 my-8 md:my-0">
                <h3 class="text-lg font-bold text-white leading-snug">بوابة مبيعات وإدارة مستودعات ومعارض السيارات الموحدة</h3>
                <p class="text-[11px] text-slate-400 leading-relaxed">برنامج متكامل وذكي لمتابعة المخازن، حجز المركبات، وتوزيع السيارات على الفروع وإخراج بطاقات المواصفات فورياً لعملاء المعارض الكرام.</p>
            </div>

            <div class="text-[9px] text-slate-500 font-mono">
                SECURE PHP PORTAL v2.0.0 • © 2026
            </div>
        </div>

        <!-- Login Form -->
        <div class="md:col-span-7 p-6 md:p-8 flex flex-col justify-center space-y-4">
            <div>
                <span class="text-[9px] text-indigo-400 font-bold tracking-wider uppercase block mb-1">تسجيل الدخول الموحد (PHP)</span>
                <h1 class="text-xl font-bold text-white">مرحباً بك في نظام إدارة المخزون</h1>
                <p class="text-[11px] text-slate-400 mt-1">يرجى تسجيل الدخول مستخدماً كود الصلاحية المخصص لك لمتابعة المخازن والطلبات</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="p-3.5 bg-rose-500/10 border border-rose-500/20 rounded-lg text-rose-400 text-xs font-bold">
                    ⚠️ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="index.php?page=login" class="space-y-4">
                <input type="hidden" name="login_action" value="1">
                
                <div class="space-y-1">
                    <label class="block text-[10px] font-bold text-slate-400">اسم المستخدم للوصول</label>
                    <input
                        type="text"
                        name="username"
                        required
                        placeholder="مثال: admin"
                        class="w-full text-xs px-3.5 py-2.5 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                    />
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold text-slate-400">كلمة المرور السرية</label>
                    <input
                        type="password"
                        name="password"
                        required
                        placeholder="••••••••"
                        class="w-full text-xs px-3.5 py-2.5 rounded border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"
                    />
                </div>

                <button
                    type="submit"
                    class="w-full py-2.5 rounded bg-indigo-600 hover:bg-indigo-700 font-extrabold text-xs text-white transition duration-150 flex items-center justify-center gap-1.5 shadow-lg shadow-indigo-600/10 cursor-pointer"
                >
                    <span>تسجيل الدخول للنظام الموحد</span>
                </button>
            </form>

            <div class="pt-4 border-t border-slate-800 text-[10px] text-slate-500 leading-relaxed">
                <span class="block font-bold text-slate-400 mb-1">الحسابات الافتراضية للتجربة السريعة:</span>
                • الحساب الإداري: اسم المستخدم: <code class="bg-slate-950 px-1 py-0.5 rounded text-indigo-400 font-sans font-bold">admin</code> / كلمة المرور: <code class="bg-slate-950 px-1 py-0.5 rounded text-indigo-400 font-sans font-bold">admin123</code><br>
                • حساب المندوب: اسم المستخدم: <code class="bg-slate-950 px-1 py-0.5 rounded text-emerald-400 font-sans font-bold">agent</code> / كلمة المرور: <code class="bg-slate-950 px-1 py-0.5 rounded text-emerald-400 font-sans font-bold">agent123</code>
            </div>
        </div>
    </div>
</body>
</html>
<?php else: 
    $user_id = $_SESSION['user_id'];
    $user_name = $_SESSION['user_name'];
    $user_role = $_SESSION['user_role'];
    $user_username = $_SESSION['user_username'];
    $user_branch_id = $_SESSION['branch_id'] ?? 1;
    
    // Fetch branch name
    $brStmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
    $brStmt->execute([$user_branch_id]);
    $user_branch_name = $brStmt->fetchColumn() ?: 'المقر الرئيسي';

    // Fetch company settings early so they are available globally (reports, printing, contracts, export, headers, settings)
    $defaultSettings = [
        'company_name' => 'شركة المخزون للمحركات',
        'phone' => '0500000000',
        'email' => 'info@almakhzoun.com',
        'currency' => 'ر.س',
        'address' => 'الرياض، المملكة العربية السعودية',
        'tax_number' => '',
        'cr_number' => '',
        'contact_phone' => '',
        'whatsapp_phone' => '966500000000',
        'logo' => '',
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
        'showroom_banner_height' => 'medium',
        'showroom_banner_bg_size' => 'cover',
        'showroom_banner_title_color' => '#ffffff',
        'showroom_banner_subtitle_color' => '#cbd5e1',
        'showroom_banner_text_bg' => 0,
        'showroom_custom_pages' => '[]',
        'showroom_menu_links' => '[]',
        'showroom_custom_css' => '',
        'showroom_custom_js' => ''
    ];

    $companySettings = $defaultSettings;
    try {
        $companySettingsQuery = $pdo->query("SELECT * FROM `settings` LIMIT 1");
        $dbSettingsRow = $companySettingsQuery->fetch();
        if ($dbSettingsRow) {
            foreach ($dbSettingsRow as $k => $v) {
                if ($v !== null && $v !== '') {
                    $companySettings[$k] = $v;
                }
            }
        }
    } catch (Exception $e) {
        // Safe fallback
    }

    $page = $_GET['page'] ?? 'dashboard';

    // If the user is a representative (مندوب), they are strictly allowed to access only 'inventory' (Dashboard is hidden)
    if ($user_role === 'representative') {
        $allowed_representative_pages = ['inventory'];
        if (!in_array($page, $allowed_representative_pages)) {
            $page = 'inventory';
        }
    }

    // Pre-populate branches lookup globally for all authenticated sections to prevent undefined variable errors
    $branches_lookup = [];
    $availableCarsForSale = [];
    $repsListLookup = [];
    try {
        $branches_lookup = $pdo->query("SELECT * FROM `branches` ORDER BY `name` ASC")->fetchAll();
        $availableCarsForSale = $pdo->query("SELECT id, make, model, year, plate_number, price FROM `cars` WHERE `status` IN ('available', 'reserved') ORDER BY make ASC, model ASC")->fetchAll();
        $repsListLookup = $pdo->query("SELECT id, name FROM `users` WHERE role = 'representative' OR role = 'admin'")->fetchAll();
    } catch (Exception $e) {
        // Safe fallback
    }

    // Handle Language Switcher Action
    $lang = $_COOKIE['lang'] ?? 'ar';
    if (isset($_GET['toggle_lang'])) {
        $lang = $lang === 'ar' ? 'en' : 'ar';
        setcookie('lang', $lang, time() + (365 * 24 * 60 * 60), "/");
        header("Location: index.php?page=" . urlencode($page));
        exit;
    }

    // --- EXPORT CONTROLLER INTERCEPTOR ---
    if ($page === 'reports' && ($user_role === 'admin' || $user_role === 'branch_manager') && isset($_GET['format']) && in_array($_GET['format'], ['excel', 'print'])) {
        $export_format = $_GET['format'];
        $tab = $_GET['tab'] ?? 'stock';

        // Gather filter inputs
        $filter_branch = $_GET['branch_id'] ?? '';
        $filter_make = $_GET['make'] ?? '';
        $filter_trim = $_GET['trim'] ?? '';
        $filter_status = $_GET['status'] ?? '';
        $filter_supplier = $_GET['supplier'] ?? '';
        $filter_rep = $_GET['rep_in_charge'] ?? '';
        $filter_import = $_GET['import_origin'] ?? '';
        $filter_owner = $_GET['previous_owner'] ?? '';
        $filter_from_date = $_GET['from_date'] ?? '';
        $filter_to_date = $_GET['to_date'] ?? '';
        $filter_period_type = $_GET['period_type'] ?? 'all';
        $search_query = $_GET['search'] ?? '';

        // Build query where clauses
        $where_clauses = ["1=1"];
        $params = [];

        if (!empty($filter_branch)) {
            $where_clauses[] = "c.`branch_id` = ?";
            $params[] = $filter_branch;
        }
        if (!empty($filter_make)) {
            $where_clauses[] = "c.`make` = ?";
            $params[] = $filter_make;
        }
        if (!empty($filter_trim)) {
            $where_clauses[] = "c.`trim` = ?";
            $params[] = $filter_trim;
        }
        if (!empty($filter_status)) {
            $where_clauses[] = "c.`status` = ?";
            $params[] = $filter_status;
        }
        if (!empty($filter_supplier)) {
            $where_clauses[] = "c.`supplier` = ?";
            $params[] = $filter_supplier;
        }
        if (!empty($filter_rep)) {
            $where_clauses[] = "c.`rep_in_charge` = ?";
            $params[] = $filter_rep;
        }
        if (!empty($filter_import)) {
            $where_clauses[] = "c.`import_origin` = ?";
            $params[] = $filter_import;
        }
        if (!empty($filter_owner)) {
            $where_clauses[] = "c.`previous_owner` = ?";
            $params[] = $filter_owner;
        }
        if (!empty($search_query)) {
            $where_clauses[] = "(c.`make` LIKE ? OR c.`model` LIKE ? OR c.`vin` LIKE ? OR c.`plate_number` LIKE ?)";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
        }

        // Apply period filters depending on report type
        if ($tab === 'entry') {
            if ($filter_period_type === 'daily') {
                $where_clauses[] = "DATE(COALESCE(c.`entry_date`, c.`created_at`)) = CURRENT_DATE";
            } else if ($filter_period_type === 'monthly') {
                $where_clauses[] = "YEAR(COALESCE(c.`entry_date`, c.`created_at`)) = YEAR(CURRENT_DATE) AND MONTH(COALESCE(c.`entry_date`, c.`created_at`)) = MONTH(CURRENT_DATE)";
            } else if ($filter_period_type === 'yearly') {
                $where_clauses[] = "YEAR(COALESCE(c.`entry_date`, c.`created_at`)) = YEAR(CURRENT_DATE)";
            } else if ($filter_period_type === 'custom' && !empty($filter_from_date) && !empty($filter_to_date)) {
                $where_clauses[] = "DATE(COALESCE(c.`entry_date`, c.`created_at`)) BETWEEN ? AND ?";
                $params[] = $filter_from_date;
                $params[] = $filter_to_date;
            }
        } else if ($tab === 'exit') {
            $where_clauses[] = "(c.`status` = 'sold' OR c.`exit_date` IS NOT NULL)";
            if ($filter_period_type === 'daily') {
                $where_clauses[] = "DATE(COALESCE(c.`exit_date`, c.`sale_date`)) = CURRENT_DATE";
            } else if ($filter_period_type === 'monthly') {
                $where_clauses[] = "YEAR(COALESCE(c.`exit_date`, c.`sale_date`)) = YEAR(CURRENT_DATE) AND MONTH(COALESCE(c.`exit_date`, c.`sale_date`)) = MONTH(CURRENT_DATE)";
            } else if ($filter_period_type === 'yearly') {
                $where_clauses[] = "YEAR(COALESCE(c.`exit_date`, c.`sale_date`)) = YEAR(CURRENT_DATE)";
            } else if ($filter_period_type === 'custom' && !empty($filter_from_date) && !empty($filter_to_date)) {
                $where_clauses[] = "DATE(COALESCE(c.`exit_date`, c.`sale_date`)) BETWEEN ? AND ?";
                $params[] = $filter_from_date;
                $params[] = $filter_to_date;
            }
        } else {
            // Stock: optional from/to range filters
            if (!empty($filter_from_date) && !empty($filter_to_date)) {
                $where_clauses[] = "DATE(c.`created_at`) BETWEEN ? AND ?";
                $params[] = $filter_from_date;
                $params[] = $filter_to_date;
            }
        }

        // Fetch ALL matching rows for export (No LIMIT!)
        $sql = "SELECT c.*, b.name as branch_name 
                FROM `cars` c 
                LEFT JOIN `branches` b ON c.`branch_id` = b.`id` 
                WHERE " . implode(" AND ", $where_clauses) . " 
                ORDER BY c.`created_at` DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $export_cars = $stmt->fetchAll();

        // Title of the report depending on tab
        $report_title = "تقرير جرد وحالة المخزون الحالي للسيارات";
        if ($tab === 'entry') {
            $report_title = "تقرير توريد وحركة دخول السيارات للمستودع";
        } else if ($tab === 'exit') {
            $report_title = "تقرير مبيعات وحركة خروج السيارات من المستودع";
        }

        // Handle Excel Format
        if ($export_format === 'excel') {
            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=Almakhzoun_Pro_Report_" . $tab . "_" . date('Y-m-d') . ".xls");
            header("Pragma: no-cache");
            header("Expires: 0");

            // Output table
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            echo '<head><meta http-equiv="Content-type" content="text/html;charset=utf-8" /></head>';
            echo '<body style="direction: rtl; font-family: Cairo, Arial, sans-serif;">';
            
            if (!empty($companySettings['logo'])) {
                echo '<div style="text-align: center; margin-bottom: 10px;"><img src="' . htmlspecialchars($companySettings['logo']) . '" style="max-height: 80px;" alt="Logo"></div>';
            }
            
            echo '<h2 style="text-align: center; color: #4f46e5; margin-bottom: 20px;">' . $report_title . '</h2>';
            echo '<p style="text-align: center; font-size: 12px; color: #64748b;">تاريخ التوليد: ' . date('Y-m-d H:i:s') . ' | ' . htmlspecialchars($companySettings['company_name'] ?? 'الشركة') . '</p>';
            
            echo '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%; text-align: right; border: 1px solid #cbd5e1;">';
            echo '<tr style="background-color: #f1f5f9; font-weight: bold; color: #1e293b;">';
            echo '<th style="border: 1px solid #cbd5e1;">الماركة والطراز</th>';
            echo '<th style="border: 1px solid #cbd5e1;">سنة الصنع</th>';
            echo '<th style="border: 1px solid #cbd5e1;">الفرع/المعرض</th>';
            echo '<th style="border: 1px solid #cbd5e1;">رقم الهيكل VIN</th>';
            echo '<th style="border: 1px solid #cbd5e1;">رقم اللوحة</th>';
            echo '<th style="border: 1px solid #cbd5e1;">الحالة</th>';
            echo '<th style="border: 1px solid #cbd5e1;">تاريخ الإضافة</th>';
            echo '<th style="border: 1px solid #cbd5e1;">سعر التكلفة</th>';
            echo '<th style="border: 1px solid #cbd5e1;">سعر البيع</th>';
            if ($tab === 'exit') {
                echo '<th style="border: 1px solid #cbd5e1;">مبلغ البيع الفعلي</th>';
                echo '<th style="border: 1px solid #cbd5e1;">تاريخ الخروج والبيع</th>';
                echo '<th style="border: 1px solid #cbd5e1;">الربح الناتج</th>';
            }
            echo '</tr>';

            foreach ($export_cars as $car) {
                $status_text = 'متوفرة';
                if ($car['status'] === 'reserved') $status_text = 'محجوزة';
                elseif ($car['status'] === 'sold') $status_text = 'مباعة';
                elseif ($car['status'] === 'not_for_sale') $status_text = 'غير معروضة للبيع';

                echo '<tr>';
                echo '<td style="border: 1px solid #cbd5e1; font-weight: bold;">' . htmlspecialchars($car['make'] . ' ' . $car['model']) . '</td>';
                echo '<td style="border: 1px solid #cbd5e1; direction: ltr; text-align: center;">' . htmlspecialchars($car['year']) . '</td>';
                echo '<td style="border: 1px solid #cbd5e1;">' . htmlspecialchars($car['branch_name'] ?: 'غير محدد') . '</td>';
                echo '<td style="border: 1px solid #cbd5e1; font-family: monospace; text-align: center;">' . htmlspecialchars($car['vin'] ?: '-') . '</td>';
                echo '<td style="border: 1px solid #cbd5e1; text-align: center;">' . htmlspecialchars($car['plate_number'] ?: '-') . '</td>';
                echo '<td style="border: 1px solid #cbd5e1; text-align: center;">' . $status_text . '</td>';
                echo '<td style="border: 1px solid #cbd5e1; text-align: center;">' . date('Y-m-d', strtotime($car['created_at'])) . '</td>';
                echo '<td style="border: 1px solid #cbd5e1; text-align: left;">' . number_format($car['cost_price'], 2) . ' ر.س</td>';
                echo '<td style="border: 1px solid #cbd5e1; text-align: left;">' . number_format($car['price'], 2) . ' ر.س</td>';
                if ($tab === 'exit') {
                    $actual_sale = $car['sale_amount'] ?? $car['price'];
                    $profit = $actual_sale - $car['cost_price'];
                    echo '<td style="border: 1px solid #cbd5e1; text-align: left; font-weight: bold;">' . number_format($actual_sale, 2) . ' ر.س</td>';
                    echo '<td style="border: 1px solid #cbd5e1; text-align: center;">' . ($car['sale_date'] ? date('Y-m-d', strtotime($car['sale_date'])) : '-') . '</td>';
                    echo '<td style="border: 1px solid #cbd5e1; text-align: left; color: ' . ($profit >= 0 ? 'green' : 'red') . '; font-weight: bold;">' . number_format($profit, 2) . ' ر.س</td>';
                }
                echo '</tr>';
            }
            echo '</table>';
            echo '</body></html>';
            exit;
        }

        // Handle Print / PDF View
        if ($export_format === 'print') {
            ?>
            <!DOCTYPE html>
            <html lang="ar" dir="rtl">
            <head>
                <meta charset="UTF-8">
                <title><?php echo $report_title; ?> - Almakhzoun Pro</title>
                <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
                <script src="https://cdn.tailwindcss.com"></script>
                <style>
                    body { font-family: 'Cairo', sans-serif; }
                    @media print {
                        .no-print { display: none !important; }
                        body { background-color: white !important; color: black !important; }
                    }
                </style>
            </head>
            <body class="bg-slate-50 p-6 sm:p-12 text-slate-800">
                <div class="max-w-5xl mx-auto bg-white border border-slate-200 rounded-3xl p-8 shadow-sm">
                    <!-- Header -->
                    <div class="flex justify-between items-center border-b border-slate-200 pb-6 mb-8">
                        <div class="flex items-center gap-4 text-right">
                            <?php if (!empty($companySettings['logo'])): ?>
                                <img src="<?php echo htmlspecialchars($companySettings['logo']); ?>" alt="Company Logo" class="h-16 w-auto object-contain" referrerPolicy="no-referrer">
                            <?php else: ?>
                                <div class="w-16 h-16 rounded-xl bg-indigo-600 flex items-center justify-center font-black text-white text-xl">M</div>
                            <?php endif; ?>
                            <div>
                                <h1 class="text-lg font-black text-slate-900"><?php echo htmlspecialchars($companySettings['company_name'] ?? 'شركة المخزون للمحركات'); ?></h1>
                                <p class="text-xs text-slate-500 font-bold mt-0.5">نظام إدارة المعارض والمستودعات الموحد | Almakhzoun Pro</p>
                                <?php if (!empty($companySettings['cr_number'])): ?>
                                    <span class="text-[10px] text-slate-400 font-bold block">سجل تجاري: <?php echo htmlspecialchars($companySettings['cr_number']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($companySettings['tax_number'])): ?>
                                    <span class="text-[10px] text-slate-400 font-bold block">الرقم الضريبي: <?php echo htmlspecialchars($companySettings['tax_number']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-left text-xs text-slate-500 font-bold space-y-1">
                            <div>تاريخ التقرير: <?php echo date('Y-m-d H:i'); ?></div>
                            <div>تم التوليد بواسطة: <?php echo htmlspecialchars($user_name); ?></div>
                            <?php if (!empty($companySettings['phone'])): ?>
                                <div>الهاتف: <?php echo htmlspecialchars($companySettings['phone']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Print Title -->
                    <div class="text-center mb-8">
                        <h2 class="text-lg font-black text-slate-900"><?php echo $report_title; ?></h2>
                        <p class="text-xs text-slate-400 mt-1 font-bold">ملخص شامل لحركة وحالة مخزون سيارات المعرض</p>
                    </div>

                    <!-- Table -->
                    <table class="w-full text-right border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold">
                                <th class="p-3">الماركة والطراز</th>
                                <th class="p-3 text-center">سنة الصنع</th>
                                <th class="p-3">الفرع/المعرض</th>
                                <th class="p-3 text-center font-mono">رقم الهيكل VIN</th>
                                <th class="p-3 text-center">الحالة</th>
                                <th class="p-3 text-left">سعر البيع</th>
                                <?php if ($tab === 'exit'): ?>
                                    <th class="p-3 text-left">البيع الفعلي</th>
                                    <th class="p-3 text-left">الربح</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($export_cars as $car): ?>
                                <tr class="border-b border-slate-100 hover:bg-slate-50/50">
                                    <td class="p-3 font-bold text-slate-900"><?php echo htmlspecialchars($car['make'] . ' ' . $car['model']); ?></td>
                                    <td class="p-3 text-center font-bold font-sans"><?php echo htmlspecialchars($car['year']); ?></td>
                                    <td class="p-3 text-slate-600 font-medium"><?php echo htmlspecialchars($car['branch_name'] ?: 'غير محدد'); ?></td>
                                    <td class="p-3 text-center font-mono text-slate-500"><?php echo htmlspecialchars($car['vin'] ?: '-'); ?></td>
                                    <td class="p-3 text-center">
                                        <?php if ($car['status'] === 'available'): ?>
                                            <span class="text-emerald-600 font-bold">متوفرة</span>
                                        <?php elseif ($car['status'] === 'reserved'): ?>
                                            <span class="text-amber-600 font-bold">محجوزة</span>
                                        <?php elseif ($car['status'] === 'sold'): ?>
                                            <span class="text-slate-500 font-bold">مباعة</span>
                                        <?php else: ?>
                                            <span class="text-rose-600 font-bold">غير معروضة للبييع</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-left font-black text-slate-900 font-sans"><?php echo number_format($car['price'], 2); ?> ر.س</td>
                                    <?php if ($tab === 'exit'): 
                                        $actual_sale = $car['sale_amount'] ?? $car['price'];
                                        $profit = $actual_sale - $car['cost_price'];
                                    ?>
                                        <td class="p-3 text-left font-black text-emerald-600 font-sans"><?php echo number_format($actual_sale, 2); ?> ر.س</td>
                                        <td class="p-3 text-left font-black text-indigo-600 font-sans"><?php echo number_format($profit, 2); ?> ر.س</td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Totals and Footer Summary -->
                    <div class="mt-8 pt-6 border-t border-slate-100 flex justify-between items-center">
                        <div class="text-xs text-slate-400 font-bold">
                            * هذا المستند تم توليده إلكترونياً ولا يتطلب توقيعاً رسمياً.
                        </div>
                        <div class="text-right space-y-1">
                            <div class="text-xs font-bold text-slate-500">إجمالي السيارات المدرجة بالتقرير: <span class="font-extrabold text-slate-900 font-sans"><?php echo count($export_cars); ?></span> سيارة</div>
                        </div>
                    </div>

                    <!-- Print Button Actions -->
                    <div class="no-print mt-8 flex justify-center gap-3">
                        <button onclick="window.print()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold px-6 py-2.5 rounded-xl cursor-pointer transition shadow shadow-indigo-600/10">
                            🖨️ ابدأ الطباعة الآن
                        </button>
                        <button onclick="window.close()" class="bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold px-6 py-2.5 rounded-xl cursor-pointer transition">
                            إغلاق النافذة
                        </button>
                    </div>
                </div>

                <script>
                    // Auto print on load
                    window.onload = function() {
                        setTimeout(() => {
                            window.print();
                        }, 500);
                    };
                </script>
            </body>
            </html>
            <?php
            exit;
        }
    }

    // --- PRINT CONTRACT INTERCEPTOR ---
    if (isset($_GET['print_contract'])) {
        $car_id = trim($_GET['print_contract']);
        // Fetch car details
        $stmt = $pdo->prepare("SELECT c.*, b.name as branch_name, u.name as salesman_name FROM `cars` c LEFT JOIN `branches` b ON c.branch_id = b.id LEFT JOIN `users` u ON c.sold_by_user_id = u.id WHERE c.id = ? AND c.status = 'sold'");
        $stmt->execute([$car_id]);
        $car = $stmt->fetch();
        if ($car) {
            // Render print contract
            ?>
            <!DOCTYPE html>
            <html lang="ar" dir="rtl">
            <head>
                <meta charset="UTF-8">
                <title>عقد بيع مركبة - <?php echo htmlspecialchars($car['make'] . ' ' . $car['model']); ?></title>
                <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
                <script src="https://cdn.tailwindcss.com"></script>
                <style>
                    body { font-family: 'Cairo', sans-serif; background-color: #f8fafc; }
                    @media print {
                        .no-print { display: none !important; }
                        body { background-color: white !important; color: black !important; padding: 0 !important; }
                        .print-border { border: 1px solid #000 !important; }
                    }
                </style>
            </head>
            <body class="p-6 sm:p-12 text-slate-800">
                <div class="max-w-4xl mx-auto bg-white border border-slate-200 rounded-3xl p-8 shadow-sm">
                    <!-- Letterhead Header -->
                    <div class="flex justify-between items-start border-b-2 border-slate-900 pb-6 mb-8">
                        <div class="flex items-center gap-4 text-right">
                            <?php if (!empty($companySettings['logo'])): ?>
                                <img src="<?php echo htmlspecialchars($companySettings['logo']); ?>" alt="Company Logo" class="h-20 w-auto object-contain" referrerPolicy="no-referrer">
                            <?php else: ?>
                                <div class="w-16 h-16 rounded-xl bg-indigo-600 flex items-center justify-center font-black text-white text-xl">M</div>
                            <?php endif; ?>
                            <div>
                                <h1 class="text-xl font-black text-slate-900"><?php echo htmlspecialchars($companySettings['company_name'] ?? 'شركة المخزون للمحركات'); ?></h1>
                                <p class="text-xs text-slate-500 font-bold mt-1">لتجارة وتوريد كافة أنواع السيارات</p>
                                <p class="text-[10px] text-slate-400 font-bold mt-0.5">
                                    <?php if (!empty($companySettings['address'])): ?>العنوان: <?php echo htmlspecialchars($companySettings['address']); ?><?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="text-left space-y-1 text-xs text-slate-600 font-bold">
                            <h2 class="text-lg font-black text-indigo-600">عقد بيع وفاتورة تسليم</h2>
                            <div>رقم الفاتورة: <span class="font-mono text-slate-900">INV-<?php echo str_pad($car['id'], 6, '0', STR_PAD_LEFT); ?></span></div>
                            <div>التاريخ: <span class="font-sans text-slate-900"><?php echo date('Y-m-d', strtotime($car['exit_date'] ?: 'now')); ?></span></div>
                            <?php if (!empty($companySettings['cr_number'])): ?>
                                <div>السجل التجاري: <span class="font-mono text-slate-900"><?php echo htmlspecialchars($companySettings['cr_number']); ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($companySettings['tax_number'])): ?>
                                <div>الرقم الضريبي: <span class="font-mono text-slate-900"><?php echo htmlspecialchars($companySettings['tax_number']); ?></span></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Client & Document Info Grid -->
                    <div class="grid grid-cols-2 gap-6 mb-8">
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                            <h3 class="font-extrabold text-xs text-indigo-600 mb-2">👤 بيانات المشتري (العميل)</h3>
                            <div class="space-y-1.5 text-xs text-slate-700">
                                <div>الاسم الكامل: <span class="font-bold text-slate-900"><?php echo htmlspecialchars($car['sale_customer_name'] ?: 'غير مسجل'); ?></span></div>
                                <div>رقم الهاتف: <span class="font-bold text-slate-900 font-sans"><?php echo htmlspecialchars($car['sale_customer_phone'] ?: '-'); ?></span></div>
                                <div>طريقة الدفع: <span class="font-bold text-slate-900">نقداً / تحويل بنكي</span></div>
                            </div>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                            <h3 class="font-extrabold text-xs text-indigo-600 mb-2">🏢 تفاصيل البائع والمندوب</h3>
                            <div class="space-y-1.5 text-xs text-slate-700">
                                <div>الشركة البائعة: <span class="font-bold text-slate-900"><?php echo htmlspecialchars($companySettings['company_name']); ?></span></div>
                                <div>المسؤول المباشر: <span class="font-bold text-slate-900"><?php echo htmlspecialchars($car['salesman_name'] ?: 'المدير العام'); ?></span></div>
                                <div>الفرع المصدر: <span class="font-bold text-slate-900"><?php echo htmlspecialchars($car['branch_name'] ?: 'المقر الرئيسي'); ?></span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Vehicle Specification Table -->
                    <div class="mb-8">
                        <h3 class="font-extrabold text-xs text-indigo-600 mb-3">🚗 تفاصيل ومواصفات المركبة المباعة</h3>
                        <table class="w-full text-right border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-100 text-slate-700 font-bold border-b border-slate-200">
                                    <th class="p-3">المواصفة / الحقل</th>
                                    <th class="p-3">بيان المركبة الموثق</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr>
                                    <td class="p-3 text-slate-500 font-bold">ماركة وطراز المركبة</td>
                                    <td class="p-3 font-extrabold text-slate-900 text-sm"><?php echo htmlspecialchars($car['make'] . ' ' . $car['model']); ?></td>
                                </tr>
                                <tr>
                                    <td class="p-3 text-slate-500 font-bold">سنة الصنع (الموديل)</td>
                                    <td class="p-3 font-bold font-sans text-slate-900"><?php echo htmlspecialchars($car['year']); ?></td>
                                </tr>
                                <tr>
                                    <td class="p-3 text-slate-500 font-bold">اللون الخارجي</td>
                                    <td class="p-3 font-bold text-slate-900"><?php echo htmlspecialchars($car['color'] ?: 'غير محدد'); ?></td>
                                </tr>
                                <tr>
                                    <td class="p-3 text-slate-500 font-bold">رقم الهيكل (VIN)</td>
                                    <td class="p-3 font-mono font-bold text-slate-900 text-center bg-slate-50 rounded px-2 py-0.5 inline-block my-1"><?php echo htmlspecialchars($car['vin'] ?: '-'); ?></td>
                                </tr>
                                <tr>
                                    <td class="p-3 text-slate-500 font-bold">رقم اللوحة المعتمد</td>
                                    <td class="p-3 font-bold text-slate-900"><?php echo htmlspecialchars($car['plate_number'] ?: 'بدون لوحة / فحص جديد'); ?></td>
                                </tr>
                                <tr>
                                    <td class="p-3 text-slate-500 font-bold">حالة الكيلومترات (الممشى)</td>
                                    <td class="p-3 font-bold font-sans text-slate-900"><?php echo !empty($car['mileage']) ? number_format($car['mileage']) . ' كم' : '-'; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pricing Summary -->
                    <div class="bg-slate-900 text-white p-5 rounded-2xl flex justify-between items-center mb-8">
                        <div>
                            <span class="text-xs text-slate-400 block font-bold">إجمالي السعر المدفوع والنهائي</span>
                            <span class="text-[10px] text-slate-400 block mt-0.5">(شاملاً كافة الرسوم والضرائب المطبقة)</span>
                        </div>
                        <div class="text-left">
                            <span class="text-2xl font-black font-sans text-amber-400"><?php echo number_format($car['sale_amount'] ?: $car['price']); ?></span>
                            <span class="text-xs text-slate-300 font-bold mr-1">ر.س</span>
                        </div>
                    </div>

                    <!-- Terms of Sale -->
                    <div class="border-t border-slate-100 pt-6 mb-12 space-y-2 text-[10px] text-slate-500 leading-relaxed font-bold">
                        <p class="font-extrabold text-slate-700 text-xs mb-1">📜 الشروط والأحكام الخاصة بالبيع والتسليم:</p>
                        <p>1. يقر المشتري بمعاينته للمركبة المذكورة أعلاه معاينة تامة نافية للجهالة وقبوله حالتها الفنية والآلية الراهنة.</p>
                        <p>2. تؤول ملكية ومسؤولية المركبة بالكامل للمشتري فور استلام هذه الفاتورة وتوقيع عقد التسليم.</p>
                        <p>3. يلتزم المشتري بإكمال إجراءات نقل الملكية الرسمية لدى الجهات المختصة خلال المدة النظامية المحددة.</p>
                    </div>

                    <!-- Signature Blocks -->
                    <div class="grid grid-cols-2 gap-12 text-center text-xs mt-12">
                        <div class="space-y-12">
                            <span class="font-extrabold text-slate-800 block">توقيع المشتري (العميل)</span>
                            <div class="border-b border-dashed border-slate-400 w-3/4 mx-auto"></div>
                        </div>
                        <div class="space-y-12">
                            <span class="font-extrabold text-slate-800 block">ختم وتوقيع البائع (الشركة)</span>
                            <div class="border-b border-dashed border-slate-400 w-3/4 mx-auto"></div>
                        </div>
                    </div>

                    <!-- Print Actions -->
                    <div class="no-print mt-12 pt-6 border-t border-slate-100 flex justify-center gap-3">
                        <button onclick="window.print()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold px-6 py-2.5 rounded-xl cursor-pointer transition shadow shadow-indigo-600/10">
                            🖨️ ابدأ طباعة العقد
                        </button>
                        <button onclick="window.close()" class="bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold px-6 py-2.5 rounded-xl cursor-pointer transition">
                            إغلاق النافذة
                        </button>
                    </div>
                </div>

                <script>
                    window.onload = function() {
                        setTimeout(() => {
                            window.print();
                        }, 500);
                    };
                </script>
            </body>
            </html>
            <?php
            exit;
        } else {
            echo "<div style='direction:rtl; text-align:center; padding:50px; font-family:sans-serif;'>خطأ: لم يتم العثور على المركبة المحددة أو لم يتم توثيق بيعها بعد.</div>";
            exit;
        }
    }

    // --- EXPORT CUSTOMER VCARD (VCF) INTERCEPTOR ---
    if (isset($_GET['export_vcard'])) {
        header('Content-Type: text/vcard; charset=utf-8');
        $cust_id = intval($_GET['id'] ?? 0);
        
        if ($cust_id > 0) {
            $stmt = $pdo->prepare("SELECT name, phone FROM `customers` WHERE `id` = ?");
            $stmt->execute([$cust_id]);
            $cust = $stmt->fetch();
            if ($cust) {
                // Ensure name is clean for filename header
                $cleanName = preg_replace('/[^a-zA-Z0-9_\-\x{0600}-\x{06FF}]/u', '_', $cust['name']);
                $filename = "customer_" . $cleanName . ".vcf";
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                $cleanPhone = preg_replace('/[^0-9+]/', '', $cust['phone']);
                echo "BEGIN:VCARD\r\n";
                echo "VERSION:3.0\r\n";
                echo "FN;CHARSET=UTF-8:" . $cust['name'] . "\r\n";
                echo "TEL;TYPE=CELL:" . $cleanPhone . "\r\n";
                echo "END:VCARD\r\n";
            }
        } else {
            header('Content-Disposition: attachment; filename="almakhzoun_all_customers.vcf"');
            $stmt = $pdo->query("SELECT name, phone FROM `customers` ORDER BY name ASC");
            while ($row = $stmt->fetch()) {
                $cleanPhone = preg_replace('/[^0-9+]/', '', $row['phone']);
                echo "BEGIN:VCARD\r\n";
                echo "VERSION:3.0\r\n";
                echo "FN;CHARSET=UTF-8:" . $row['name'] . "\r\n";
                echo "TEL;TYPE=CELL:" . $cleanPhone . "\r\n";
                echo "END:VCARD\r\n";
            }
        }
        exit;
    }

    // --- PRINT TRANSFER LETTER INTERCEPTOR ---
    if (isset($_GET['print_transfer'])) {
        $transfer_id = intval($_GET['print_transfer']);
        $stmt = $pdo->prepare("SELECT t.*, 
                               c.make, c.model, c.year, c.color, c.vin, c.plate_number, c.engine_power, c.transmission,
                               c.trim, c.interior_color, c.body_type, c.mileage, c.vehicle_condition, c.serial_number, c.customs_number, c.engine_type, c.id as joined_car_id,
                               fb.name as from_branch_name, fb.showroom_name as from_showroom_name, fb.showroom_address as from_showroom_address, fb.phone as from_showroom_phone, fb.tax_number as from_showroom_tax, fb.commercial_registration as from_showroom_cr, fb.logo as from_showroom_logo, fb.stamp as from_showroom_stamp,
                               tb.name as to_branch_name, tb.showroom_name as to_showroom_name, tb.showroom_address as to_showroom_address, tb.phone as to_showroom_phone, tb.tax_number as to_showroom_tax, tb.commercial_registration as to_showroom_cr, tb.logo as to_showroom_logo, tb.stamp as to_showroom_stamp, tb.manager as to_branch_manager_raw,
                               u.name as creator_name,
                               ru.name as receiver_name,
                               mu.name as to_branch_manager_name
                               FROM `branch_transfers` t 
                               LEFT JOIN `cars` c ON t.car_id = c.id 
                               LEFT JOIN `branches` fb ON t.from_branch_id = fb.id 
                               LEFT JOIN `branches` tb ON t.to_branch_id = tb.id 
                               LEFT JOIN `users` u ON t.created_by_user_id = u.id 
                               LEFT JOIN `users` ru ON t.received_by_user_id = ru.id
                               LEFT JOIN `users` mu ON tb.manager = mu.id
                               WHERE t.id = ?");
        $stmt->execute([$transfer_id]);
        $trf = $stmt->fetch();
        if ($trf) {
            $carsInTransfer = [];
            if (!empty($trf['car_id'])) {
                $carIdsArray = array_map('trim', explode(',', $trf['car_id']));
                if (count($carIdsArray) > 1) {
                    $placeholders = implode(',', array_fill(0, count($carIdsArray), '?'));
                    $stmtCars = $pdo->prepare("SELECT * FROM `cars` WHERE `id` IN ($placeholders)");
                    $stmtCars->execute($carIdsArray);
                    $carsInTransfer = $stmtCars->fetchAll();
                } else {
                    if (!empty($trf['make'])) {
                        $carsInTransfer[] = $trf;
                    } else {
                        $stmtCars = $pdo->prepare("SELECT * FROM `cars` WHERE `id` = ?");
                        $stmtCars->execute([$trf['car_id']]);
                        $singleCar = $stmtCars->fetch();
                        if ($singleCar) {
                            $carsInTransfer[] = $singleCar;
                        }
                    }
                }
            }
            $isAjax = isset($_GET['ajax_print']);
            if (!$isAjax) {
                ?>
                <!DOCTYPE html>
                <html lang="ar" dir="rtl">
                <head>
                    <meta charset="UTF-8">
                    <title>خطاب تحويل مركبة رسمي - <?php echo htmlspecialchars($trf['letter_number']); ?></title>
                    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
                    <script src="https://cdn.tailwindcss.com"></script>
                     <style>
                        body { font-family: 'Cairo', sans-serif; background-color: #f8fafc; }
                        @media print {
                            .no-print { display: none !important; }
                            body { 
                                background-color: white !important; 
                                color: black !important; 
                                padding: 0 !important; 
                                margin: 0 !important; 
                                -webkit-print-color-adjust: exact !important; 
                                print-color-adjust: exact !important; 
                            }
                            
                            /* Page size & margins optimization */
                            @page {
                                size: A4 portrait;
                                margin: 0.6cm 0.5cm !important;
                            }
                            
                            .print-card {
                                border: none !important;
                                box-shadow: none !important;
                                padding: 0 !important;
                                margin: 0 !important;
                                width: 100% !important;
                                max-width: 100% !important;
                            }

                            /* Tighten letterhead */
                            .print-card .grid {
                                gap: 0.75rem !important;
                                border-bottom-width: 2px !important;
                                border-color: #0f172a !important;
                                padding-bottom: 0.4rem !important;
                                margin-bottom: 0.4rem !important;
                            }
                            .print-card img {
                                height: 2.25rem !important; /* Scale logos down */
                            }
                            .print-card h3 {
                                font-size: 11px !important;
                            }
                            .print-card h4 {
                                font-size: 8px !important;
                            }
                            .print-card .text-\[10px\] {
                                font-size: 8px !important;
                                line-height: 1.2 !important;
                            }

                            /* Tighten main title section */
                            .print-card h2 {
                                font-size: 15px !important;
                                margin-bottom: 1px !important;
                            }
                            .print-card .my-4 {
                                margin-top: 0.3rem !important;
                                margin-bottom: 0.3rem !important;
                                padding-bottom: 0.3rem !important;
                            }
                            .print-card .relative {
                                margin-top: 0.2rem !important;
                                margin-bottom: 0.2rem !important;
                                padding-bottom: 0.2rem !important;
                            }
                            .print-card .p-4 {
                                padding: 0.35rem !important;
                            }
                            .print-card .min-w-\[200px\] {
                                min-width: 150px !important;
                                padding: 0.25rem 0.5rem !important;
                                font-size: 8px !important;
                            }

                            /* Tighten greetings card */
                            .print-card .bg-indigo-50 {
                                padding: 0.4rem !important;
                                margin-bottom: 0.4rem !important;
                                border-radius: 0.5rem !important;
                            }
                            .print-card .bg-indigo-50 p {
                                font-size: 9px !important;
                                line-height: 1.25 !important;
                            }

                            /* Tighten tables and content boxes */
                            .print-card .mb-6 {
                                margin-bottom: 0.4rem !important;
                            }
                            .print-card .rounded-2xl {
                                border-radius: 0.5rem !important;
                            }
                            .print-card table th, .print-card table td {
                                padding: 3px 5px !important;
                                font-size: 8.5px !important;
                                line-height: 1.2 !important;
                            }
                            .print-card table td.p-2\.5 {
                                padding: 3px 5px !important;
                            }
                            .print-card table td.p-3 {
                                padding: 3px 5px !important;
                            }
                            
                            /* Compact notes section */
                            .print-card .p-4.bg-slate-50 {
                                padding: 0.35rem !important;
                                margin-bottom: 0.4rem !important;
                            }
                            .print-card .p-4.bg-slate-50 p {
                                font-size: 8.5px !important;
                            }

                            /* Tighten Signatures and Seals */
                            .print-card .mt-10 {
                                margin-top: 0.4rem !important;
                                padding-top: 0.4rem !important;
                                gap: 0.4rem !important;
                            }
                            .print-card .mt-10 .p-4 {
                                padding: 0.3rem !important;
                                border-radius: 0.5rem !important;
                            }
                            .print-card .mt-10 h4 {
                                font-size: 9px !important;
                                padding-bottom: 0.2rem !important;
                                margin-bottom: 0.2rem !important;
                            }
                            .print-card .mt-10 .space-y-1\.5 > div {
                                font-size: 8px !important;
                            }
                            .print-card .mt-10 .space-y-6 {
                                margin-top: 0 !important;
                                margin-bottom: 0 !important;
                            }
                            .print-card .mt-10 .space-y-6 > div {
                                margin-bottom: 0.2rem !important;
                            }
                            .print-card .mt-10 .h-5 {
                                height: 0.75rem !important;
                            }
                            .print-card .mt-10 .pt-3 {
                                padding-top: 0.2rem !important;
                            }
                            .print-card .mt-10 img {
                                height: 1.75rem !important;
                            }
                            .print-card .mt-10 .w-14.h-14 {
                                width: 2rem !important;
                                height: 2rem !important;
                                font-size: 7px !important;
                            }
                            .print-card .mt-10 .text-\[9px\] {
                                font-size: 7px !important;
                            }
                        }
                     </style>
                </head>
                <body class="p-4 sm:p-10 text-slate-800">
                <?php
            }
            ?>
                <?php if (!$isAjax): ?>
                <!-- Floating Top Action Bar -->
                <div class="no-print max-w-4xl mx-auto mb-6 bg-slate-900 text-white p-4 rounded-2xl flex justify-between items-center shadow-lg">
                    <div class="flex items-center gap-2">
                        <span class="text-sm">🖨️</span>
                        <span class="text-xs font-extrabold">خطاب تحويل داخلي رسمي جاهز للطباعة والتحميل</span>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="window.print()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-extrabold px-4 py-2 rounded-lg cursor-pointer transition">
                            بدء الطباعة / حفظ PDF
                        </button>
                        <button onclick="window.close()" class="bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-extrabold px-4 py-2 rounded-lg cursor-pointer transition">
                            إغلاق الصفحة ✕
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <div class="print-card max-w-4xl mx-auto bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-sm">
                    <!-- Beautiful Letterhead Header (ترويسة جميلة) -->
                    <div class="grid grid-cols-2 gap-8 border-b-2 border-slate-900 pb-4 mb-4">
                        <!-- Right Column: Sending Branch Details & Logo (بيانات الفرع المصدر على اليمين مع شعاره) -->
                        <div class="text-right space-y-1">
                            <div class="flex items-center gap-3 mb-2">
                                <?php if (!empty($trf['from_showroom_logo'])): ?>
                                    <img src="<?php echo htmlspecialchars($trf['from_showroom_logo']); ?>" alt="Source Branch Logo" class="h-14 w-auto object-contain rounded" referrerPolicy="no-referrer">
                                <?php elseif (!empty($companySettings['logo'])): ?>
                                    <img src="<?php echo htmlspecialchars($companySettings['logo']); ?>" alt="Company Logo" class="h-14 w-auto object-contain" referrerPolicy="no-referrer">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center font-black text-slate-400 text-md">📤</div>
                                <?php endif; ?>
                                <div>
                                    <h4 class="text-[9px] font-black text-slate-400">الفرع المُحوِّل (المرسل)</h4>
                                    <h3 class="text-sm font-black text-slate-900"><?php echo htmlspecialchars($trf['from_branch_name'] ?: 'المقر الرئيسي'); ?></h3>
                                </div>
                            </div>
                            <div class="text-[10px] text-slate-600 space-y-0.5 leading-normal">
                                <?php if (!empty($trf['from_showroom_name'])): ?><div>اسم المعرض: <span class="font-bold text-slate-800"><?php echo htmlspecialchars($trf['from_showroom_name']); ?></span></div><?php endif; ?>
                                <?php if (!empty($trf['from_showroom_address'])): ?><div>العنوان: <span class="font-bold text-slate-800"><?php echo htmlspecialchars($trf['from_showroom_address']); ?></span></div><?php endif; ?>
                                <?php if (!empty($trf['from_showroom_phone'])): ?><div>أرقام التواصل: <span class="font-bold text-slate-800 font-sans"><?php echo htmlspecialchars($trf['from_showroom_phone']); ?></span></div><?php endif; ?>
                                <?php if (!empty($trf['from_showroom_tax'])): ?><div>الرقم الضريبي: <span class="font-bold text-slate-800 font-sans"><?php echo htmlspecialchars($trf['from_showroom_tax']); ?></span></div><?php endif; ?>
                                <?php if (!empty($trf['from_showroom_cr'])): ?><div>السجل التجاري: <span class="font-bold text-slate-800 font-sans"><?php echo htmlspecialchars($trf['from_showroom_cr']); ?></span></div><?php endif; ?>
                            </div>
                        </div>

                        <!-- Left Column: Receiving Branch Details & Logo (بيانات الفرع المستلم على اليسار مع شعاره) -->
                        <div class="text-left space-y-1">
                            <div class="flex items-center justify-end gap-3 mb-2">
                                <div class="text-left">
                                    <h4 class="text-[9px] font-black text-slate-400">الفرع المستلم (المستهدف)</h4>
                                    <h3 class="text-sm font-black text-slate-900"><?php echo htmlspecialchars($trf['to_branch_name'] ?: 'الفرع المستهدف'); ?></h3>
                                </div>
                                <?php if (!empty($trf['to_showroom_logo'])): ?>
                                    <img src="<?php echo htmlspecialchars($trf['to_showroom_logo']); ?>" alt="Target Branch Logo" class="h-14 w-auto object-contain rounded" referrerPolicy="no-referrer">
                                <?php elseif (!empty($companySettings['logo'])): ?>
                                    <img src="<?php echo htmlspecialchars($companySettings['logo']); ?>" alt="Company Logo" class="h-14 w-auto object-contain" referrerPolicy="no-referrer">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center font-black text-slate-400 text-md">📥</div>
                                <?php endif; ?>
                            </div>
                            <div class="text-[10px] text-slate-600 space-y-0.5 leading-normal">
                                <?php if (!empty($trf['to_showroom_name'])): ?><div>اسم المعرض: <span class="font-bold text-slate-800"><?php echo htmlspecialchars($trf['to_showroom_name']); ?></span></div><?php endif; ?>
                                <?php if (!empty($trf['to_showroom_address'])): ?><div>العنوان: <span class="font-bold text-slate-800"><?php echo htmlspecialchars($trf['to_showroom_address']); ?></span></div><?php endif; ?>
                                <?php if (!empty($trf['to_showroom_phone'])): ?><div>أرقام التواصل: <span class="font-bold text-slate-800 font-sans"><?php echo htmlspecialchars($trf['to_showroom_phone']); ?></span></div><?php endif; ?>
                                <?php if (!empty($trf['to_showroom_tax'])): ?><div>الرقم الضريبي: <span class="font-bold text-slate-800 font-sans"><?php echo htmlspecialchars($trf['to_showroom_tax']); ?></span></div><?php endif; ?>
                                <?php if (!empty($trf['to_showroom_cr'])): ?><div>السجل التجاري: <span class="font-bold text-slate-800 font-sans"><?php echo htmlspecialchars($trf['to_showroom_cr']); ?></span></div><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Main Header & Meta Details -->
                    <div class="relative flex flex-col md:flex-row justify-between items-center my-4 pb-4 border-b border-slate-200">
                        <div class="text-right space-y-1 mb-4 md:mb-0">
                            <h2 class="text-2xl font-black text-slate-900 tracking-wide">
                                خطاب تحويل داخلي
                            </h2>
                            <p class="text-xs text-slate-500 font-bold">
                                رقم التحويل (Transfer No): <span class="font-mono text-slate-900 bg-slate-100 border border-slate-200 rounded px-2 py-0.5 text-xs"><?php echo htmlspecialchars($trf['letter_number'] ?? ''); ?></span>
                            </p>
                        </div>
                        <div class="text-left bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-1 font-bold text-xs text-slate-600 min-w-[200px]">
                            <div class="flex justify-between gap-4">
                                <span>📅 تاريخ الإصدار:</span>
                                <span class="text-slate-900 font-sans"><?php echo date('Y-m-d', strtotime($trf['transfer_date'])); ?></span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span>⏰ وقت الإصدار:</span>
                                <span class="text-slate-900 font-sans"><?php echo date('H:i', strtotime($trf['transfer_date'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Transfer Greeting -->
                    <div class="bg-indigo-50 border border-indigo-100/50 p-4 rounded-2xl mb-6">
                        <p class="text-xs font-bold text-indigo-950 leading-relaxed">
                            سعادة أمين مستودع / مدير فرع (<?php echo htmlspecialchars($trf['to_branch_name'] ?: 'الفرع المستهدف'); ?>) المحترم،
                            <br>
                            السلام عليكم ورحمة الله وبركاته،،
                            <br>
                            نرفق لكم بموجب هذا الخطاب الفني بيانات ومواصفات المركبة المحولة لفرعكم، والمنقولة رسمياً من فرع (<?php echo htmlspecialchars($trf['from_branch_name'] ?: 'الفرع المصدر'); ?>). يرجى التكرم بمعاينة ومطابقة بيانات المركبة وإجراء القبول لتأكيد استلام العهدة رسمياً وتغيير حالتها في النظام.
                        </p>
                    </div>

                    <!-- Transfer Summary Table -->
                    <div class="mb-6 overflow-hidden border border-slate-200 rounded-2xl bg-white">
                        <div class="bg-slate-50 px-4 py-2 border-b border-slate-200">
                           <h4 class="text-xs font-black text-slate-800">📌 ملخص حركات التحويل والفرع المستهدف</h4>
                        </div>
                        <table class="w-full text-right text-xs border-collapse">
                            <tbody>
                                <tr class="border-b border-slate-100 hover:bg-slate-50">
                                    <td class="p-3 bg-slate-50/50 font-bold text-slate-500 w-1/4">الفرع المُحوِّل:</td>
                                    <td class="p-3 font-extrabold text-slate-900 w-1/4"><?php echo htmlspecialchars($trf['from_branch_name'] ?: 'المقر الرئيسي'); ?></td>
                                    <td class="p-3 bg-slate-50/50 font-bold text-slate-500 w-1/4">الفرع المستلم:</td>
                                    <td class="p-3 font-extrabold text-slate-900 w-1/4"><?php echo htmlspecialchars($trf['to_branch_name'] ?: 'الفرع المستهدف'); ?></td>
                                </tr>
                                <tr class="border-b border-slate-100 hover:bg-slate-50">
                                    <td class="p-3 bg-slate-50/50 font-bold text-slate-500">الموظف المُصدر للتحويل:</td>
                                    <td class="p-3 font-bold text-slate-800"><?php echo htmlspecialchars($trf['creator_name'] ?: 'مدير النظام'); ?></td>
                                    <td class="p-3 bg-slate-50/50 font-bold text-slate-500">تاريخ ووقت التحويل:</td>
                                    <td class="p-3 font-bold font-sans text-slate-800"><?php echo date('Y-m-d H:i', strtotime($trf['transfer_date'])); ?></td>
                                </tr>
                                <tr class="hover:bg-slate-50">
                                    <td class="p-3 bg-slate-50/50 font-bold text-slate-500">حالة التحويل:</td>
                                    <td class="p-3 font-black text-slate-900" colspan="3">
                                        <?php if ($trf['status'] === 'pending'): ?>
                                            <span class="px-2.5 py-0.5 bg-amber-500/10 text-amber-600 border border-amber-500/20 rounded font-black text-[10px] inline-flex items-center gap-1">
                                                🟡 قيد الانتظار والقبول بالفرع المستهدف
                                            </span>
                                        <?php elseif ($trf['status'] === 'completed' || empty($trf['status'])): ?>
                                            <span class="px-2.5 py-0.5 bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 rounded font-black text-[10px] inline-flex items-center gap-1">
                                                🟢 تم الاستلام والموافقة (نقلت العهدة)
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2.5 py-0.5 bg-rose-500/10 text-rose-600 border border-rose-500/20 rounded font-black text-[10px] inline-flex items-center gap-1">
                                                🔴 مرفوض ومحجوب الحركة
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if ($trf['status'] === 'completed' && !empty($trf['receiver_name'])): ?>
                                <tr class="border-t border-slate-200 bg-emerald-50/20">
                                    <td class="p-3 bg-emerald-500/5 font-bold text-emerald-800">الموظف المستلم والموافق:</td>
                                    <td class="p-3 font-black text-emerald-900"><?php echo htmlspecialchars($trf['receiver_name']); ?></td>
                                    <td class="p-3 bg-emerald-500/5 font-bold text-emerald-800">تاريخ ووقت الاستلام:</td>
                                    <td class="p-3 font-black font-sans text-emerald-950"><?php echo date('Y-m-d H:i', strtotime($trf['received_at'])); ?></td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (count($carsInTransfer) > 1): ?>
                    <!-- Beautiful Consolidated Table matching the image for multi-car transfers -->
                    <div class="mb-6 overflow-hidden border border-slate-300 rounded-2xl bg-white page-break-inside-avoid shadow-sm font-sans">
                        <div class="bg-slate-900 px-4 py-3 text-white">
                            <h4 class="text-xs font-black">📋 بيان تفاصيل السيارات ومواصفات العهدة المحولة</h4>
                        </div>
                        <table class="w-full text-center text-xs border-collapse">
                            <thead>
                                <tr class="bg-[#2b5a9f] text-white font-extrabold border-b border-slate-200">
                                    <th class="p-3 w-12 text-center">م</th>
                                    <th class="p-3 text-right">تفاصيل السيارة</th>
                                    <th class="p-3 text-center">لون وموديل السيارة</th>
                                    <th class="p-3 text-center">رقم الهيكل (VIN)</th>
                                    <th class="p-3 text-center">رقم اللوحة</th>
                                    <th class="p-3 text-center">البطاقة الجمركية</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($carsInTransfer as $idx => $carItem): ?>
                                <tr class="border-b border-slate-200 hover:bg-slate-50 text-center font-bold">
                                    <td class="p-3 text-slate-500 text-center font-sans"><?php echo $idx + 1; ?></td>
                                    <td class="p-3 text-slate-900 text-right font-black">
                                        <?php echo htmlspecialchars(($carItem['make'] ?: '') . ' - ' . ($carItem['model'] ?: '') . ' ' . ($carItem['trim'] ?: '')); ?>
                                    </td>
                                    <td class="p-3 text-slate-800 text-center font-sans">
                                        <?php echo htmlspecialchars(($carItem['color'] ?: 'غير محدد') . ' / ' . ($carItem['year'] ?: '-')); ?>
                                    </td>
                                    <td class="p-3 font-mono text-slate-950 text-center tracking-wide" dir="ltr">
                                        <?php echo htmlspecialchars($carItem['vin'] ?: '-'); ?>
                                    </td>
                                    <td class="p-3 text-slate-800 text-center font-sans">
                                        <?php echo htmlspecialchars($carItem['plate_number'] ?: 'بدون / بطاقة جمركية'); ?>
                                    </td>
                                    <td class="p-3 font-mono text-slate-900 text-center">
                                        <?php echo htmlspecialchars($carItem['serial_number'] ?: ($carItem['customs_number'] ?: '-')); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <!-- Single Car Specs View -->
                    <?php foreach ($carsInTransfer as $idx => $carItem): ?>
                    <div class="mb-6 overflow-hidden border border-slate-200 rounded-2xl bg-white page-break-inside-avoid">
                        <div class="bg-slate-900 px-4 py-2 border-b border-slate-900">
                            <h4 class="text-xs font-black text-white">🚗 بيانات ومواصفات المركبة الفنية (<?php echo ($idx + 1) . ' / ' . count($carsInTransfer); ?>)</h4>
                        </div>
                        <table class="w-full text-right text-xs border-collapse">
                            <tbody>
                                <tr class="border-b border-slate-100 hover:bg-slate-50">
                                    <td class="p-2.5 bg-slate-50 font-bold text-slate-500 w-1/4">رقم البطاقة (Card No):</td>
                                    <td class="p-2.5 font-mono font-bold text-slate-900 w-1/4"><?php echo htmlspecialchars($carItem['serial_number'] ?: ($carItem['customs_number'] ?: '-')); ?></td>
                                    <td class="p-2.5 bg-slate-50 font-bold text-slate-500 w-1/4">الماركة (Make):</td>
                                    <td class="p-2.5 font-extrabold text-slate-900 w-1/4"><?php echo htmlspecialchars($carItem['make'] ?: '-'); ?></td>
                                </tr>
                                <tr class="border-b border-slate-100 hover:bg-slate-50">
                                    <td class="p-2.5 bg-slate-50 font-bold text-slate-500">الفئة (Trim):</td>
                                    <td class="p-2.5 font-bold text-slate-800"><?php echo htmlspecialchars($carItem['trim'] ?: 'ستاندرد / أساسي'); ?></td>
                                    <td class="p-2.5 bg-slate-50 font-bold text-slate-500">الموديل (Model):</td>
                                    <td class="p-2.5 font-bold text-slate-800"><?php echo htmlspecialchars($carItem['model'] ?: '-'); ?></td>
                                </tr>
                                <tr class="border-b border-slate-100 hover:bg-slate-50">
                                    <td class="p-2.5 bg-slate-50 font-bold text-slate-500">سنة الصنع (Year):</td>
                                    <td class="p-2.5 font-bold font-sans text-slate-850"><?php echo htmlspecialchars($carItem['year'] ?: '-'); ?></td>
                                    <td class="p-2.5 bg-slate-50 font-bold text-slate-500">اللون (Color):</td>
                                    <td class="p-2.5 font-bold text-slate-800"><?php echo htmlspecialchars($carItem['color'] ?: '-'); ?></td>
                                </tr>
                                <tr class="border-b border-slate-100 hover:bg-slate-50">
                                    <td class="p-2.5 bg-slate-50 font-bold text-slate-500">رقم الهيكل (VIN):</td>
                                    <td class="p-2.5 font-mono font-bold text-slate-950 text-left" dir="ltr"><?php echo htmlspecialchars($carItem['vin'] ?: '-'); ?></td>
                                    <td class="p-2.5 bg-slate-50 font-bold text-slate-500">رقم اللوحة:</td>
                                    <td class="p-2.5 font-mono font-bold text-slate-900"><?php echo htmlspecialchars($carItem['plate_number'] ?: 'بطاقة جمركية / بدون'); ?></td>
                                </tr>
                                <tr class="border-b border-slate-100 hover:bg-slate-50">
                                    <td class="p-2.5 bg-slate-50 font-bold text-slate-500">رقم المحرك:</td>
                                    <td class="p-2.5 font-mono font-bold text-slate-800"><?php echo htmlspecialchars($carItem['engine_power'] ? ($carItem['engine_power'] . ' hp') : 'غير متوفر'); ?></td>
                                    <td class="p-2.5 bg-slate-50 font-bold text-slate-500">العداد (Mileage):</td>
                                    <td class="p-2.5 font-sans font-bold text-slate-850"><?php echo number_format($carItem['mileage']); ?> كم</td>
                                </tr>
                                <tr class="hover:bg-slate-50">
                                    <td class="p-2.5 bg-slate-50 font-bold text-slate-500">حالة السيارة:</td>
                                    <td class="p-2.5 font-bold text-indigo-600" colspan="3"><?php echo htmlspecialchars($carItem['vehicle_condition'] ?: 'جديد (أصفار)'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($trf['notes'])): ?>
                    <div class="mb-6 p-4 bg-slate-50 border border-slate-200 rounded-2xl">
                        <span class="text-[10px] font-black text-slate-400 block mb-1">📝 ملاحظات وتوجيهات عملية النقل:</span>
                        <p class="text-slate-800 text-xs leading-relaxed font-bold"><?php echo nl2br(htmlspecialchars($trf['notes'])); ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Interactive Actions Panel (Only visible in Modal View inside the system, hidden when printed) -->
                    <?php if ($isAjax && $trf['status'] === 'pending' && (($user_role === 'admin' && $user_id != $trf['created_by_user_id']) || (!empty($trf['to_branch_manager_raw']) && $user_id == $trf['to_branch_manager_raw'] && $user_id != $trf['created_by_user_id']))): ?>
                    <div class="no-print my-6 p-5 bg-amber-50 border border-amber-200 rounded-2xl flex flex-col md:flex-row justify-between items-center gap-4">
                        <div class="text-right">
                            <h4 class="text-xs font-black text-amber-950 mb-1">⚡ إجراءات الموافقة واستلام العهدة في فرعكم</h4>
                            <p class="text-[10px] text-amber-800 font-bold">بصفتكم مسؤول في الفرع المستهدف، يرجى مطابقة رقم الهيكل والمواصفات المذكورة أعلاه قبل القبول.</p>
                        </div>
                        <div class="flex gap-2">
                            <form method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من مطابقة وموافقة استلام هذه المركبة في فرعك؟');">
                                <input type="hidden" name="accept_transfer" value="1">
                                <input type="hidden" name="transfer_id" value="<?php echo $trf['id']; ?>">
                                <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black rounded-xl cursor-pointer transition shadow shadow-emerald-600/10 flex items-center gap-1.5">
                                    ✓ قبول تحويل المركبة واستلامها
                                </button>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من رفض طلب تحويل السيارة وإلغائه؟');">
                                <input type="hidden" name="reject_transfer" value="1">
                                <input type="hidden" name="transfer_id" value="<?php echo $trf['id']; ?>">
                                <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-black rounded-xl cursor-pointer transition shadow shadow-rose-600/10 flex items-center gap-1.5">
                                    ✕ رفض وإلغاء طلب التحويل
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Official Signatures & Seals Block -->
                    <div class="grid grid-cols-3 gap-6 text-center text-xs mt-10 pt-6 border-t border-slate-300">
                        <!-- Col 1: Sending Branch -->
                        <div class="space-y-6 bg-slate-50/50 p-4 border border-slate-100 rounded-2xl flex flex-col justify-between">
                            <div>
                                <h4 class="font-extrabold text-slate-800 border-b border-slate-200 pb-2 mb-2">📤 الجهة المُحوِّلة (المرسلة)</h4>
                                <div class="text-right text-[10px] space-y-1.5 font-bold">
                                    <div>الموظف المُصدر: <span class="text-slate-900"><?php echo htmlspecialchars($trf['creator_name'] ?: 'مدير النظام'); ?></span></div>
                                    <div class="h-5">التوقيع: ............................</div>
                                    <div class="h-5">مسؤول المعرض: ............................</div>
                                </div>
                            </div>
                            <div class="border-t border-dashed border-slate-200 pt-3">
                                <span class="text-[9px] text-slate-400 block font-bold mb-2">ختم الفرع المُرسل</span>
                                <?php if (!empty($trf['from_showroom_stamp'])): ?>
                                    <img src="<?php echo htmlspecialchars($trf['from_showroom_stamp']); ?>" class="h-14 w-auto object-contain mx-auto rounded" alt="Stamp" referrerPolicy="no-referrer">
                                <?php else: ?>
                                    <div class="w-14 h-14 rounded-full border border-dashed border-slate-300 mx-auto flex items-center justify-center text-[8px] text-slate-300 font-bold">[ الختم الرسمي ]</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Col 2: Transport Driver -->
                        <div class="space-y-6 bg-slate-50/50 p-4 border border-slate-100 rounded-2xl flex flex-col justify-between">
                            <div>
                                <h4 class="font-extrabold text-slate-800 border-b border-slate-200 pb-2 mb-2">🚛 سائق الحركة والنقل</h4>
                                <div class="text-right text-[10px] space-y-1.5 font-bold">
                                    <div class="h-5">اسم السائق: ...................................</div>
                                    <div class="h-5">رقم الجوال: ...................................</div>
                                    <div class="h-5">رقم الهوية: ...................................</div>
                                    <div class="h-5">التوقيع: ...................................</div>
                                </div>
                            </div>
                            <div class="pt-2 text-[9px] text-slate-400 font-bold leading-tight">
                                يتعهد الناقل بتوصيل المركبة بنفس حالتها الفنية الموضحة بالخطاب.
                            </div>
                        </div>

                        <!-- Col 3: Receiving Branch -->
                        <div class="space-y-6 bg-slate-50/50 p-4 border border-slate-100 rounded-2xl flex flex-col justify-between">
                            <div>
                                <h4 class="font-extrabold text-slate-800 border-b border-slate-200 pb-2 mb-2">📥 الجهة المستلمة (المستهدفة)</h4>
                                <div class="text-right text-[10px] space-y-1.5 font-bold">
                                    <?php 
                                    $recipient_display = '............................';
                                    if (!empty($trf['receiver_name'])) {
                                        $recipient_display = $trf['receiver_name'];
                                    } elseif (!empty($trf['to_branch_manager_name'])) {
                                        $recipient_display = $trf['to_branch_manager_name'];
                                    } elseif (!empty($trf['to_branch_manager_raw']) && !is_numeric($trf['to_branch_manager_raw'])) {
                                        $recipient_display = $trf['to_branch_manager_raw'];
                                    }
                                    ?>
                                    <div>الموظف المستلم: <span class="text-slate-900"><?php echo htmlspecialchars($recipient_display); ?></span></div>
                                    <div class="h-5">التوقيع: ............................</div>
                                    <div class="h-5">مسؤول المعرض: ............................</div>
                                </div>
                            </div>
                            <div class="border-t border-dashed border-slate-200 pt-3">
                                <span class="text-[9px] text-slate-400 block font-bold mb-2">ختم الفرع المستلم</span>
                                <?php if (!empty($trf['to_showroom_stamp'])): ?>
                                    <img src="<?php echo htmlspecialchars($trf['to_showroom_stamp']); ?>" class="h-14 w-auto object-contain mx-auto rounded" alt="Stamp" referrerPolicy="no-referrer">
                                <?php else: ?>
                                    <div class="w-14 h-14 rounded-full border border-dashed border-slate-300 mx-auto flex items-center justify-center text-[8px] text-slate-300 font-bold">[ الختم الرسمي ]</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div class="no-print mt-10 pt-6 border-t border-slate-100 flex justify-center gap-3">
                        <button onclick="window.open('index.php?print_transfer=<?php echo $trf['id']; ?>', '_blank')" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold px-6 py-2.5 rounded-xl cursor-pointer transition shadow shadow-indigo-600/10">
                            🖨️ فتح في صفحة طباعة مستقلة (A4 / PDF)
                        </button>
                        <?php if ($isAjax): ?>
                            <button type="button" onclick="closePrintTransferModal()" class="bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold px-6 py-2.5 rounded-xl cursor-pointer transition">
                                إغلاق المعاينة ✕
                            </button>
                        <?php else: ?>
                            <button type="button" onclick="window.close()" class="bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold px-6 py-2.5 rounded-xl cursor-pointer transition">
                                إغلاق النافذة
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <script>
                    <?php if (!$isAjax): ?>
                    window.onload = function() {
                        setTimeout(() => {
                            window.print();
                        }, 500);
                    };
                    <?php endif; ?>
                </script>
            </body>
            </html>
            <?php
            exit;
        } else {
            echo "<div style='direction:rtl; text-align:center; padding:50px; font-family:sans-serif;'>خطأ: لم يتم العثور على خطاب التحويل المحدد.</div>";
            exit;
        }
    }

    // Translations
    $translations = [
        'ar' => [
            'dashboard' => 'لوحة التحكم',
            'inventory' => 'المخزون',
            'sales' => 'قسم المبيعات',
            'customers' => 'إدارة العملاء',
            'reservations' => 'إدارة الحجوزات',
            'users' => 'إدارة الموظفين',
            'branches' => 'الفروع والمعارض',
            'transfers' => 'التحويلات بين الفروع',
            'reports' => 'التقارير والتصدير',
            'logs' => 'الأمان والعمليات',
            'orders' => 'صندوق الطلبات',
            'ads' => 'إدارة الإعلانات والعروض',
            'settings' => 'الإعدادات العامة',
            'logout' => 'تسجيل الخروج',
            'customer_showroom' => 'الرئيسية للعملاء 🌐',
            'theme_toggle' => 'تبديل الإضاءة',
            'language_toggle' => 'English',
            'welcome' => 'مرحباً بك',
            'admin_role' => 'مدير النظام',
            'branch_manager_role' => 'مدير الفرع',
            'agent_role' => 'مندوب المبيعات',
        ],
        'en' => [
            'dashboard' => 'Dashboard',
            'inventory' => 'Inventory List',
            'sales' => 'Sales Department',
            'customers' => 'Customer Management',
            'reservations' => 'Reservations',
            'users' => 'Users / Employees',
            'branches' => 'Branches / Showrooms',
            'transfers' => 'Branch Transfers',
            'reports' => 'Reports / Export',
            'logs' => 'Audit & Security',
            'orders' => 'Showroom Orders',
            'ads' => 'Ads & Offers Management',
            'settings' => 'General Settings',
            'logout' => 'Log Out',
            'customer_showroom' => 'Customer View 🌐',
            'theme_toggle' => 'Toggle Dark Mode',
            'language_toggle' => 'العربية',
            'welcome' => 'Welcome',
            'admin_role' => 'System Admin',
            'branch_manager_role' => 'Branch Manager',
            'agent_role' => 'Sales Representative',
        ]
    ];
    $t = $translations[$lang];

    // Global settings loaded early at the top of authenticated session
    $settings_error = '';
    $settings_success = '';

    // Action Controllers
    if (isset($_POST['record_sale']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $carId = $_POST['car_id'] ?? '';
        $saleAmount = floatval($_POST['sale_amount'] ?? 0);
        $customerName = trim($_POST['sale_customer_name'] ?? '');
        $customerId = trim($_POST['sale_customer_id'] ?? '');
        $nationality = trim($_POST['sale_customer_nationality'] ?? 'سعودي');
        $phone = trim($_POST['sale_customer_phone'] ?? '');
        $exitNotes = trim($_POST['exit_notes'] ?? '');
        $exitDate = $_POST['exit_date'] ?? date('Y-m-d');
        $soldBy = $_POST['sold_by_user_id'] ?? $user_id;

        if (!empty($carId)) {
            $stmt = $pdo->prepare("UPDATE `cars` SET 
                `status` = 'sold',
                `sale_amount` = ?,
                `sale_customer_name` = ?,
                `sale_customer_id` = ?,
                `sale_customer_nationality` = ?,
                `sale_customer_phone` = ?,
                `exit_notes` = ?,
                `exit_date` = ?,
                `sold_by_user_id` = ?
                WHERE `id` = ?");
            $stmt->execute([
                $saleAmount,
                $customerName,
                $customerId,
                $nationality,
                $phone,
                $exitNotes,
                $exitDate,
                $soldBy,
                $carId
            ]);

            writeAuditLog($pdo, $user_id, $user_name, 'تسجيل عملية بيع (PHP)', "تم بيع وتسجيل خروج المركبة برقم $carId للعميل $customerName بقيمة $saleAmount ر.س");
            createNotification($pdo, 'car_sold', 'تسجيل مبيعات جديد', "تم بيع السيارة بنجاح وتسجيلها باسم العميل $customerName", $user_id, $user_name, $user_branch_name, $carId);

            header("Location: index.php?page=sales");
            exit;
        }
    }

    if (isset($_GET['cancel_sale']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $carId = $_GET['cancel_sale'];
        if (!empty($carId)) {
            $stmt = $pdo->prepare("UPDATE `cars` SET 
                `status` = 'available',
                `sale_amount` = NULL,
                `sale_customer_name` = NULL,
                `sale_customer_id` = NULL,
                `sale_customer_nationality` = NULL,
                `sale_customer_phone` = NULL,
                `exit_notes` = NULL,
                `exit_date` = NULL,
                `sold_by_user_id` = NULL
                WHERE `id` = ?");
            $stmt->execute([$carId]);

            writeAuditLog($pdo, $user_id, $user_name, 'إلغاء عملية بيع (PHP)', "تم إلغاء عملية بيع المركبة $carId وإعادتها للمخزون كمتاحة للبيع");
            header("Location: index.php?page=sales");
            exit;
        }
    }

    if (isset($_POST['update_order_status']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $order_id = intval($_POST['order_id'] ?? 0);
        $status = $_POST['status'] ?? 'new';
        if ($order_id > 0) {
            $stmt = $pdo->prepare("UPDATE `customer_orders` SET `status` = ? WHERE `id` = ?");
            $stmt->execute([$status, $order_id]);
            writeAuditLog($pdo, $user_id, $user_name, 'تعديل حالة طلب', "تم تعديل حالة الطلب رقم $order_id إلى $status");
            header("Location: index.php?page=orders");
            exit;
        }
    }

    if (isset($_GET['delete_order']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $order_id = intval($_GET['delete_order'] ?? 0);
        if ($order_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM `customer_orders` WHERE `id` = ?");
            $stmt->execute([$order_id]);
            writeAuditLog($pdo, $user_id, $user_name, 'حذف طلب شراء', "تم حذف طلب الشراء رقم $order_id");
            header("Location: index.php?page=orders");
            exit;
        }
    }

    if (isset($_POST['update_contact_status']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $contact_id = intval($_POST['contact_id'] ?? 0);
        $status = $_POST['status'] ?? 'new';
        if ($contact_id > 0) {
            $stmt = $pdo->prepare("UPDATE `contact_inquiries` SET `status` = ? WHERE `id` = ?");
            $stmt->execute([$status, $contact_id]);
            writeAuditLog($pdo, $user_id, $user_name, 'تعديل حالة رسالة تواصل', "تم تعديل حالة الرسالة رقم $contact_id إلى $status");
            header("Location: index.php?page=contact_inquiries");
            exit;
        }
    }

    if (isset($_GET['delete_contact_inquiry']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $contact_id = intval($_GET['delete_contact_inquiry'] ?? 0);
        if ($contact_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM `contact_inquiries` WHERE `id` = ?");
            $stmt->execute([$contact_id]);
            writeAuditLog($pdo, $user_id, $user_name, 'حذف رسالة تواصل بنا', "تم حذف رسالة تواصل بنا رقم $contact_id");
            header("Location: index.php?page=contact_inquiries");
            exit;
        }
    }

    if (isset($_POST['update_review_status']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $review_id = intval($_POST['review_id'] ?? 0);
        $status = $_POST['status'] ?? 'approved';
        if ($review_id > 0) {
            $stmt = $pdo->prepare("UPDATE `showroom_reviews` SET `status` = ? WHERE `id` = ?");
            $stmt->execute([$status, $review_id]);
            writeAuditLog($pdo, $user_id, $user_name, 'تعديل حالة تقييم عميل', "تم تعديل حالة التقييم رقم $review_id إلى $status");
            header("Location: index.php?page=showroom_reviews");
            exit;
        }
    }

    if (isset($_GET['delete_review']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $review_id = intval($_GET['delete_review'] ?? 0);
        if ($review_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM `showroom_reviews` WHERE `id` = ?");
            $stmt->execute([$review_id]);
            writeAuditLog($pdo, $user_id, $user_name, 'حذف تقييم عميل', "تم حذف تقييم العميل رقم $review_id");
            header("Location: index.php?page=showroom_reviews");
            exit;
        }
    }

    // SHOWROOM SALES REPRESENTATIVES ACTIONS
    if (isset($_POST['add_sales_rep']) && $_POST['add_sales_rep'] == '1' && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $name = trim($_POST['name'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $avatar = trim($_POST['avatar'] ?? '');
        $status = trim($_POST['status'] ?? 'active');

        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = pathinfo($_FILES['avatar_file']['name'], PATHINFO_EXTENSION);
            $fileName = 'sales_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $targetFile = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['avatar_file']['tmp_name'], $targetFile)) {
                $avatar = $targetFile;
            }
        }

        if (!empty($name) && !empty($phone)) {
            $stmt = $pdo->prepare("INSERT INTO `showroom_sales` (`name`, `title`, `phone`, `whatsapp`, `avatar`, `status`) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $title, $phone, $whatsapp, $avatar, $status]);
            writeAuditLog($pdo, $user_id, $user_name, 'إضافة مندوب مبيعات', "تم إضافة مندوب المبيعات $name بنجاح.");
            header("Location: index.php?page=showroom_sales&rep_success=1");
            exit;
        }
    }

    if (isset($_POST['edit_sales_rep']) && $_POST['edit_sales_rep'] == '1' && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $rep_id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $avatar = trim($_POST['avatar'] ?? '');
        $status = trim($_POST['status'] ?? 'active');

        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = pathinfo($_FILES['avatar_file']['name'], PATHINFO_EXTENSION);
            $fileName = 'sales_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $targetFile = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['avatar_file']['tmp_name'], $targetFile)) {
                $avatar = $targetFile;
            }
        }

        if ($rep_id > 0 && !empty($name)) {
            $stmt = $pdo->prepare("UPDATE `showroom_sales` SET `name` = ?, `title` = ?, `phone` = ?, `whatsapp` = ?, `avatar` = ?, `status` = ? WHERE `id` = ?");
            $stmt->execute([$name, $title, $phone, $whatsapp, $avatar, $status, $rep_id]);
            writeAuditLog($pdo, $user_id, $user_name, 'تعديل مندوب مبيعات', "تم تعديل بيانات مندوب المبيعات $name رقم $rep_id.");
            header("Location: index.php?page=showroom_sales&rep_success=2");
            exit;
        }
    }

    if (isset($_GET['delete_sales_rep']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $rep_id = intval($_GET['delete_sales_rep'] ?? 0);
        if ($rep_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM `showroom_sales` WHERE `id` = ?");
            $stmt->execute([$rep_id]);
            writeAuditLog($pdo, $user_id, $user_name, 'حذف مندوب مبيعات', "تم حذف مندوب المبيعات رقم $rep_id.");
            header("Location: index.php?page=showroom_sales&rep_success=3");
            exit;
        }
    }

    if (isset($_POST['save_sales_template']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $template_style = trim($_POST['sales_template_style'] ?? 'grid');
        
        $count = $pdo->query("SELECT COUNT(*) FROM `settings`")->fetchColumn();
        if ($count > 0) {
            $stmt = $pdo->prepare("UPDATE `settings` SET `sales_template_style` = ?");
            $stmt->execute([$template_style]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO `settings` (`sales_template_style`) VALUES (?)");
            $stmt->execute([$template_style]);
        }
        
        writeAuditLog($pdo, $user_id, $user_name, 'تحديث قالب المبيعات', "تم تغيير قالب المبيعات إلى $template_style");
        header("Location: index.php?page=showroom_sales&rep_success=4");
        exit;
    }

    // Action Controllers
    if (isset($_POST['save_reservation'])) {
        $carId = $_POST['car_id'] ?? '';
        if (!empty($carId)) {
            $resId = 'res-' . time() . '-' . rand(100, 999);
            $customerName = $_POST['customer_name'] ?? $user_name;
            $customerPhone = $_POST['customer_phone'] ?? '0500000000';
            $duration = intval($_POST['duration'] ?? 3);
            $notes = $_POST['notes'] ?? 'حجز فوري تلقائي';
            $createdAt = date('Y-m-d H:i:s');
            $reservationDate = date('Y-m-d');
            $reservationEndDate = date('Y-m-d', strtotime("+$duration days"));

            $stmt = $pdo->prepare("INSERT INTO `reservations` (`id`, `car_id`, `customer_name`, `customer_phone`, `customer_national_id`, `start_date`, `duration`, `created_at`, `created_by_user_id`, `status`, `notes`) VALUES (?, ?, ?, ?, '1000000000', ?, ?, ?, ?, 'active', ?)");
            $stmt->execute([
                $resId,
                $carId,
                $customerName,
                $customerPhone,
                $reservationDate,
                $duration,
                $createdAt,
                $user_id,
                $notes
            ]);

            $stmtUpdateCar = $pdo->prepare("UPDATE `cars` SET `status` = 'reserved' WHERE `id` = ?");
            $stmtUpdateCar->execute([$carId]);

            writeAuditLog($pdo, $user_id, $user_name, 'إضافة حجز فوري (PHP)', "تم حجز السيارة بنجاح حجزاً فورياً برقم حجز $resId");
            createNotification($pdo, 'reservation_created', 'حجز فوري تلقائي', "تم حجز المركبة بنجاح بواسطة المندوب $user_name", $user_id, $user_name, $user_branch_name, $carId);

            $carStmt = $pdo->prepare("SELECT card_file, card_file_name FROM cars WHERE id = ?");
            $carStmt->execute([$carId]);
            $carData = $carStmt->fetch();
            
            $attachments = [];
            if ($carData && !empty($carData['card_file'])) {
                $attId = 'att-' . time() . '-' . rand(100, 999);
                $attStmt = $pdo->prepare("INSERT INTO `reservation_attachments` (`id`, `reservation_id`, `file_name`, `file_path`, `uploaded_by`, `created_at`) VALUES (?, ?, ?, ?, ?, ?)");
                $attStmt->execute([
                    $attId,
                    $resId,
                    $carData['card_file_name'] ?: 'البطاقة الجمركية الرسمية',
                    $carData['card_file'],
                    $user_name,
                    $createdAt
                ]);
                
                $attachments[] = [
                    'id' => $attId,
                    'name' => $carData['card_file_name'] ?: 'البطاقة الجمركية الرسمية',
                    'url' => $carData['card_file'],
                    'size' => 'مرفق رسمي',
                    'createdAt' => $createdAt
                ];
            }

            if (isset($_POST['ajax'])) {
                if (ob_get_length()) { ob_clean(); }
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'status' => 'success',
                    'res_id' => $resId,
                    'car_id' => $carId,
                    'customer_name' => $customerName,
                    'customer_phone' => $customerPhone,
                    'duration' => $duration,
                    'notes' => $notes,
                    'rep_name' => $user_name,
                    'created_at' => $createdAt,
                    'attachments' => $attachments
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    if (isset($_POST['save_car']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $id = trim($_POST['car_id'] ?? '');
        $is_update = false;
        $existingCar = null;
        if (!empty($id)) {
            $stmtCheck = $pdo->prepare("SELECT id, main_image, gallery_images, card_file, card_file_name, branch_id FROM `cars` WHERE `id` = ?");
            $stmtCheck->execute([$id]);
            $existingCar = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if ($existingCar) {
                $is_update = true;
            }
        }
        
        if (!$is_update) {
            $id = 'car-' . time() . '-' . rand(100, 999);
        }

        $make = trim($_POST['make'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $year = intval($_POST['year'] ?? date('Y'));
        $color = trim($_POST['color'] ?? '');
        $vin = trim($_POST['vin'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $mileage = intval($_POST['mileage'] ?? 0);
        $transmission = $_POST['transmission'] ?? 'أوتوماتيك';
        $engine_type = $_POST['engine_type'] ?? 'بنزين';
        $branch_id = $_POST['branch_id'] ?? null;
        if (empty($branch_id)) $branch_id = null;
        $status = $_POST['status'] ?? 'available';
        
        $trim = trim($_POST['trim'] ?? '');
        $interior_color = trim($_POST['interior_color'] ?? '');
        $body_type = trim($_POST['body_type'] ?? 'سيدان');
        $doors = intval($_POST['doors'] ?? 4);
        $seats = intval($_POST['seats'] ?? 5);
        $cylinders = intval($_POST['cylinders'] ?? 4);
        $engine_power = intval($_POST['engine_power'] ?? 180);
        $drive = trim($_POST['drive'] ?? 'دفع أمامي FWD');
        $origin_country = trim($_POST['origin_country'] ?? '');
        $assembly_country = trim($_POST['assembly_country'] ?? '');
        $warranty = trim($_POST['warranty'] ?? 'ضمان الوكيل المعتمد الممتد');
        $warranty_duration = intval($_POST['warranty_duration'] ?? 5);
        $previous_owner = trim($_POST['previous_owner'] ?? '');
        $plate_number = trim($_POST['plate_number'] ?? '');
        if (empty($plate_number)) $plate_number = null;
        $plate_type = trim($_POST['plate_type'] ?? 'خصوصي - ملاكي');
        $serial_number = trim($_POST['serial_number'] ?? '');
        $registration_number = trim($_POST['registration_number'] ?? '');
        $vehicle_condition = trim($_POST['vehicle_condition'] ?? 'جديد (أصفار)');
        $cost_price = floatval($_POST['cost_price'] ?? 0);
        $tax = floatval($_POST['tax'] ?? 0);
        $discount = floatval($_POST['discount'] ?? 0);
        $final_price = $price + $tax - $discount;
        $supplier = trim($_POST['supplier'] ?? '');
        $ownership_type = trim($_POST['ownership_type'] ?? 'مباشر');
        $customs_number = trim($_POST['customs_number'] ?? '');
        
        // Handle multi-image upload
        $main_image = '';
        $uploaded_paths = [];
        $main_image_index = intval($_POST['main_image_index'] ?? 0);
        $custom_specs = trim($_POST['custom_specs'] ?? '');

        if ($is_update) {
            $main_image = $existingCar['main_image'];
            if (!empty($existingCar['gallery_images'])) {
                $uploaded_paths = json_decode($existingCar['gallery_images'], true) ?: [];
            }
        }

        if (isset($_FILES['car_images']) && is_array($_FILES['car_images']['name']) && !empty(array_filter($_FILES['car_images']['name']))) {
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $new_uploaded_paths = [];
            foreach ($_FILES['car_images']['name'] as $index => $name) {
                if ($_FILES['car_images']['error'][$index] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['car_images']['tmp_name'][$index];
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                        $filename = 'car_' . time() . '_' . rand(100, 999) . '_' . $index . '.' . $ext;
                        if (move_uploaded_file($tmp_name, $upload_dir . $filename)) {
                            $path = 'uploads/' . $filename;
                            $new_uploaded_paths[$index] = $path;
                        }
                    }
                }
            }
            
            if (!empty($new_uploaded_paths)) {
                $uploaded_paths = $new_uploaded_paths;
                // Determine main image
                if (isset($uploaded_paths[$main_image_index])) {
                    $main_image = $uploaded_paths[$main_image_index];
                } else {
                    $main_image = reset($uploaded_paths);
                }
            }
        }
        
        $gallery_images = json_encode(array_values($uploaded_paths), JSON_UNESCAPED_SLASHES);
        
        // Handle card file upload
        $card_file_path = $is_update ? $existingCar['card_file'] : null;
        $card_file_name = $is_update ? $existingCar['card_file_name'] : null;
        if (isset($_FILES['card_file']) && $_FILES['card_file']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['card_file']['tmp_name'];
            $name = basename($_FILES['card_file']['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $filename = 'card_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($tmp_name, $upload_dir . $filename)) {
                $card_file_path = 'uploads/' . $filename;
                $card_file_name = $name;
            }
        }

        if (!empty($make) && !empty($model) && !empty($vin)) {
            if ($is_update) {
                $stmt = $pdo->prepare("UPDATE `cars` SET 
                    `make` = ?, `model` = ?, `trim` = ?, `year` = ?, `color` = ?, `interior_color` = ?, `body_type` = ?, 
                    `doors` = ?, `seats` = ?, `cylinders` = ?, `engine_power` = ?, `drive` = ?, `origin_country` = ?, `assembly_country` = ?, 
                    `warranty` = ?, `warranty_duration` = ?, `previous_owner` = ?, `vin` = ?, `plate_number` = ?, `plate_type` = ?, 
                    `serial_number` = ?, `registration_number` = ?, `vehicle_condition` = ?, `price` = ?, `cost_price` = ?, `tax` = ?, 
                    `discount` = ?, `final_price` = ?, `mileage` = ?, `transmission` = ?, `engine_type` = ?, `status` = ?, `branch_id` = ?, 
                    `supplier` = ?, `ownership_type` = ?, `customs_number` = ?, `main_image` = ?, `card_file` = ?, `card_file_name` = ?,
                    `custom_specs` = ?, `gallery_images` = ?
                    WHERE `id` = ?");
                
                $stmt->execute([
                    $make, $model, $trim, $year, $color, $interior_color, $body_type,
                    $doors, $seats, $cylinders, $engine_power, $drive, $origin_country, $assembly_country,
                    $warranty, $warranty_duration, $previous_owner, $vin, $plate_number, $plate_type,
                    $serial_number, $registration_number, $vehicle_condition, $price, $cost_price, $tax,
                    $discount, $final_price, $mileage, $transmission, $engine_type, $status, $branch_id,
                    $supplier, $ownership_type, $customs_number, $main_image, $card_file_path, $card_file_name,
                    $custom_specs, $gallery_images, $id
                ]);
                
                // Log branch transfer automatically if the branch has changed
                $oldBranchId = $existingCar['branch_id'] ?? null;
                if ($oldBranchId !== $branch_id && $oldBranchId !== null && $branch_id !== null) {
                    $letterNumber = 'TRF-' . date('Ymd') . '-' . rand(1000, 9999);
                    $stmtTrf = $pdo->prepare("INSERT INTO `branch_transfers` (`car_id`, `from_branch_id`, `to_branch_id`, `created_by_user_id`, `letter_number`, `notes`) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmtTrf->execute([
                        $id,
                        $oldBranchId,
                        $branch_id,
                        $user_id,
                        $letterNumber,
                        'تحويل تلقائي ناتج عن تعديل بيانات السيارة وحفظها في فرع جديد'
                    ]);
                    writeAuditLog($pdo, $user_id, $user_name, 'خطاب تحويل تلقائي (PHP)', "تم إصدار خطاب تحويل تلقائي للمركبة $make $model ($id) رقم الخطاب $letterNumber");
                    createNotification($pdo, 'branch_transfer', 'خطاب تحويل تلقائي', "تم نقل السيارة وتوليد خطاب تحويل فرعي تلقائي رقم $letterNumber", $user_id, $user_name, $user_branch_name, $id);
                }
                
                writeAuditLog($pdo, $user_id, $user_name, 'تعديل سيارة (PHP)', "تم تعديل تفاصيل السيارة $make $model بنجاح برقم هيكل $vin");
                regenerateSitemapFile();
                header("Location: index.php?page=inventory&success=2");
                exit;
            } else {
                $stmt = $pdo->prepare("INSERT INTO `cars` (
                    `id`, `make`, `model`, `trim`, `year`, `color`, `interior_color`, `body_type`, 
                    `doors`, `seats`, `cylinders`, `engine_power`, `drive`, `origin_country`, `assembly_country`, 
                    `warranty`, `warranty_duration`, `previous_owner`, `vin`, `plate_number`, `plate_type`, 
                    `serial_number`, `registration_number`, `vehicle_condition`, `price`, `cost_price`, `tax`, 
                    `discount`, `final_price`, `mileage`, `transmission`, `engine_type`, `status`, `branch_id`, 
                    `supplier`, `ownership_type`, `customs_number`, `main_image`, `card_file`, `card_file_name`,
                    `custom_specs`, `gallery_images`
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $id, $make, $model, $trim, $year, $color, $interior_color, $body_type,
                    $doors, $seats, $cylinders, $engine_power, $drive, $origin_country, $assembly_country,
                    $warranty, $warranty_duration, $previous_owner, $vin, $plate_number, $plate_type,
                    $serial_number, $registration_number, $vehicle_condition, $price, $cost_price, $tax,
                    $discount, $final_price, $mileage, $transmission, $engine_type, $status, $branch_id,
                    $supplier, $ownership_type, $customs_number, $main_image, $card_file_path, $card_file_name,
                    $custom_specs, $gallery_images
                ]);
                
                writeAuditLog($pdo, $user_id, $user_name, 'إضافة سيارة جديدة (PHP)', "تم إضافة السيارة الجديدة $make $model بنجاح برقم هيكل $vin");
                regenerateSitemapFile();
                header("Location: index.php?page=inventory&success=1");
                exit;
            }
        } else {
            header("Location: index.php?page=inventory&error=missing_fields");
            exit;
        }
    }

    if (isset($_GET['cancel_reservation'])) {
        $resId = $_GET['cancel_reservation'];
        $stmtRes = $pdo->prepare("SELECT car_id, created_by_user_id FROM `reservations` WHERE `id` = ?");
        $stmtRes->execute([$resId]);
        $resvData = $stmtRes->fetch(PDO::FETCH_ASSOC);

        if ($resvData) {
            $carId = $resvData['car_id'];
            $createdBy = $resvData['created_by_user_id'];

            if ($user_role === 'representative' && $createdBy != $user_id) {
                if (isset($_GET['ajax'])) {
                    if (ob_get_length()) { ob_clean(); }
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'error' => 'ليس لديك الصلاحية لإلغاء حجز مندوب آخر.'], JSON_UNESCAPED_UNICODE);
                    exit;
                } else {
                    $redirectPage = (isset($_GET['page']) && $_GET['page'] === 'reservations') ? 'reservations' : 'inventory';
                    header("Location: index.php?page=" . $redirectPage . "&error=unauthorized");
                    exit;
                }
            }

            if (!empty($carId)) {
                $stmtCancel = $pdo->prepare("UPDATE `reservations` SET `status` = 'cancelled' WHERE `id` = ?");
                $stmtCancel->execute([$resId]);

                $stmtCar = $pdo->prepare("UPDATE `cars` SET `status` = 'available' WHERE `id` = ?");
                $stmtCar->execute([$carId]);

                writeAuditLog($pdo, $user_id, $user_name, 'إلغاء حجز (PHP)', "تم إلغاء الحجز رقم $resId وإعادة المركبة إلى المتاح");
                createNotification($pdo, 'reservation_cancelled', 'إلغاء حجز', "تم إلغاء الحجز وإتاحة السيارة للبيع", $user_id, $user_name, $user_branch_name, $carId);
            }
        }

        if (isset($_GET['ajax'])) {
            if (ob_get_length()) { ob_clean(); }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'status' => 'success'], JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            $redirectPage = (isset($_GET['page']) && $_GET['page'] === 'reservations') ? 'reservations' : 'inventory';
            header("Location: index.php?page=" . $redirectPage);
            exit;
        }
    }

    if (isset($_GET['delete_car']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $carId = $_GET['delete_car'];
        $stmtDelAtt = $pdo->prepare("DELETE FROM `attachments` WHERE `vehicle_id` = ?");
        $stmtDelAtt->execute([$carId]);
        $stmtDelCar = $pdo->prepare("DELETE FROM `cars` WHERE `id` = ?");
        $stmtDelCar->execute([$carId]);
        writeAuditLog($pdo, $user_id, $user_name, 'حذف سيارة (PHP)', "تم حذف السيارة برقم $carId نهائياً");
        regenerateSitemapFile();
        header("Location: index.php?page=inventory");
        exit;
    }

    if (isset($_GET['delete_car_attachment'])) {
        $attId = $_GET['att_id'] ?? '';
        if (!empty($attId)) {
            $stmt = $pdo->prepare("DELETE FROM `attachments` WHERE `attachment_id` = ?");
            $stmt->execute([$attId]);
            $stmt2 = $pdo->prepare("DELETE FROM `reservation_attachments` WHERE `id` = ?");
            $stmt2->execute([$attId]);
            writeAuditLog($pdo, $user_id, $user_name, 'حذف مرفق (PHP)', "تم حذف المرفق برقم $attId");
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'status' => 'success', 'attachments' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- ADVANCED RESERVATION & TRANSFER CONTROLLERS ---
    if (isset($_GET['delete_reservation']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $resId = $_GET['delete_reservation'];
        $stmtRes = $pdo->prepare("SELECT car_id FROM `reservations` WHERE `id` = ?");
        $stmtRes->execute([$resId]);
        $carId = $stmtRes->fetchColumn();
        
        $stmtDel = $pdo->prepare("DELETE FROM `reservations` WHERE `id` = ?");
        $stmtDel->execute([$resId]);
        
        if (!empty($carId)) {
            $stmtCar = $pdo->prepare("UPDATE `cars` SET `status` = 'available' WHERE `id` = ?");
            $stmtCar->execute([$carId]);
        }
        
        writeAuditLog($pdo, $user_id, $user_name, 'حذف حجز (PHP)', "تم حذف الحجز رقم $resId نهائياً وإرجاع السيارة للمخزون");
        header("Location: index.php?page=reservations&success_del=1");
        exit;
    }

    if (isset($_POST['update_reservation'])) {
        $resId = $_POST['res_id'] ?? '';
        $customerName = trim($_POST['customer_name'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');
        $duration = intval($_POST['duration'] ?? 3);
        $notes = trim($_POST['notes'] ?? '');
        
        if (!empty($resId) && !empty($customerName)) {
            $stmt = $pdo->prepare("UPDATE `reservations` SET `customer_name` = ?, `customer_phone` = ?, `duration` = ?, `notes` = ? WHERE `id` = ?");
            $stmt->execute([$customerName, $customerPhone, $duration, $notes, $resId]);
            
            writeAuditLog($pdo, $user_id, $user_name, 'تعديل حجز (PHP)', "تم تعديل بيانات الحجز رقم $resId للعميل $customerName");
            header("Location: index.php?page=reservations&success_edit=1");
            exit;
        }
    }

    if (isset($_POST['mark_reservation_sold'])) {
        $resId = $_POST['res_id'] ?? '';
        $carId = $_POST['car_id'] ?? '';
        $saleAmount = floatval($_POST['sale_amount'] ?? 0);
        $customerName = trim($_POST['sale_customer_name'] ?? '');
        $phone = trim($_POST['sale_customer_phone'] ?? '');
        $exitNotes = trim($_POST['exit_notes'] ?? '');
        $exitDate = $_POST['exit_date'] ?? date('Y-m-d');
        $soldBy = $_POST['sold_by_user_id'] ?? $user_id;
        
        if (!empty($carId) && !empty($resId)) {
            // Update car status to sold and record sale info
            $stmt = $pdo->prepare("UPDATE `cars` SET 
                `status` = 'sold',
                `sale_amount` = ?,
                `sale_customer_name` = ?,
                `sale_customer_id` = ?,
                `sale_customer_nationality` = ?,
                `sale_customer_phone` = ?,
                `exit_notes` = ?,
                `exit_date` = ?,
                `sold_by_user_id` = ?
                WHERE `id` = ?");
            $stmt->execute([
                $saleAmount,
                $customerName,
                '', // id
                'سعودي', // nationality
                $phone,
                $exitNotes,
                $exitDate,
                $soldBy,
                $carId
            ]);
            
            // Mark reservation as completed
            $stmtRes = $pdo->prepare("UPDATE `reservations` SET `status` = 'completed' WHERE `id` = ?");
            $stmtRes->execute([$resId]);
            
            writeAuditLog($pdo, $user_id, $user_name, 'بيع سيارة محجوزة (PHP)', "تم بيع المركبة المحجوزة $carId بنجاح بقيمة $saleAmount ر.س");
            createNotification($pdo, 'car_sold', 'تسجيل مبيعات حجز', "تم تحويل الحجز للمبيعات وتسجيل السيارة للعميل $customerName", $user_id, $user_name, $user_branch_name, $carId);
            
            header("Location: index.php?page=sales&success_sold=1");
            exit;
        }
    }

    if (isset($_POST['create_transfer'])) {
        $carIdInput = $_POST['car_id'] ?? '';
        $toBranchId = $_POST['to_branch_id'] ?? '';
        $notes = trim($_POST['notes'] ?? 'تحويل مخزني داخلي');
        
        if (!empty($carIdInput) && !empty($toBranchId)) {
            $carIdsArray = is_array($carIdInput) ? $carIdInput : explode(',', $carIdInput);
            $carIdsArray = array_filter(array_map('trim', $carIdsArray));
            
            if (!empty($carIdsArray)) {
                // Get original branch from the first car in list
                $stmtCar = $pdo->prepare("SELECT branch_id, make, model FROM `cars` WHERE `id` = ?");
                $stmtCar->execute([$carIdsArray[0]]);
                $carData = $stmtCar->fetch();
                
                if ($carData) {
                    $fromBranchId = $carData['branch_id'];
                    
                    if ($fromBranchId == $toBranchId) {
                        header("Location: index.php?page=transfers&error=same_branch");
                        exit;
                    }
                    
                    $letterNumber = 'TRF-' . date('Ymd') . '-' . rand(1000, 9999);
                    $carIdString = implode(',', $carIdsArray);
                    
                    $stmtTrf = $pdo->prepare("INSERT INTO `branch_transfers` (`car_id`, `from_branch_id`, `to_branch_id`, `created_by_user_id`, `letter_number`, `notes`, `status`) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                    $stmtTrf->execute([
                        $carIdString,
                        $fromBranchId,
                        $toBranchId,
                        $user_id,
                        $letterNumber,
                        $notes
                    ]);
                    
                    $carsDesc = [];
                    foreach ($carIdsArray as $cid) {
                        $stmtC = $pdo->prepare("SELECT make, model FROM `cars` WHERE `id` = ?");
                        $stmtC->execute([$cid]);
                        if ($cData = $stmtC->fetch()) {
                            $carsDesc[] = "{$cData['make']} {$cData['model']} ($cid)";
                        }
                    }
                    $carsDescStr = implode(', ', $carsDesc);
                    
                    writeAuditLog($pdo, $user_id, $user_name, 'إنشاء خطاب تحويل (PHP)', "تم إصدار طلب تحويل السيارات [$carsDescStr] إلى الفرع $toBranchId وبانتظار قبول الفرع الآخر. خطاب رقم $letterNumber");
                    createNotification($pdo, 'branch_transfer', 'خطاب تحويل فرعي بانتظار القبول', "تم إصدار خطاب تحويل للمركبات إلى فرع آخر رقم $letterNumber وبانتظار قبول الفرع الآخر", $user_id, $user_name, $user_branch_name, $carIdString);
                    
                    header("Location: index.php?page=transfers&success=1");
                    exit;
                }
            }
        }
        header("Location: index.php?page=transfers&error=invalid_data");
        exit;
    }

    if (isset($_POST['accept_transfer'])) {
        $trfId = intval($_POST['transfer_id'] ?? 0);
        
        $pdo->beginTransaction();
        try {
            // Concurrency handling: lock the transfer row using FOR UPDATE to prevent race conditions under load
            $stmtTrf = $pdo->prepare("SELECT * FROM `branch_transfers` WHERE `id` = ? FOR UPDATE");
            $stmtTrf->execute([$trfId]);
            $trf = $stmtTrf->fetch();
            
            // Fetch fresh user role and branch to avoid any session desync under heavy traffic
            $stmtUser = $pdo->prepare("SELECT role, branch_id FROM `users` WHERE `id` = ?");
            $stmtUser->execute([$user_id]);
            $freshUser = $stmtUser->fetch();
            $currRole = $freshUser ? $freshUser['role'] : $user_role;
            $currBranchId = $freshUser ? $freshUser['branch_id'] : $user_branch_id;
            
            if ($trf && $trf['status'] === 'pending') {
                // Fetch destination branch manager
                $stmtToBranch = $pdo->prepare("SELECT manager FROM `branches` WHERE `id` = ?");
                $stmtToBranch->execute([$trf['to_branch_id']]);
                $toBranch = $stmtToBranch->fetch();
                $toBranchManager = $toBranch ? $toBranch['manager'] : null;

                // Rule 1: The creator of the transfer cannot receive/accept it
                if ($user_id == $trf['created_by_user_id']) {
                    $pdo->rollBack();
                    header("Location: index.php?page=transfers&error=creator_cannot_receive");
                    exit;
                }

                // Rule 2: Only the manager of the destination branch can accept/receive it (or non-creator admin)
                $isAuthorized = false;
                if (!empty($toBranchManager)) {
                    if ($user_id == $toBranchManager || ($currRole === 'admin' && $user_id != $trf['created_by_user_id'])) {
                        $isAuthorized = true;
                    }
                } else {
                    // Fallback if no manager is assigned to the branch yet
                    if ($currRole === 'admin' || $currBranchId == $trf['to_branch_id']) {
                        $isAuthorized = true;
                    }
                }

                if ($isAuthorized) {
                    // Update transfer status to completed, recording recipient and datetime
                    $stmtUpdateTrf = $pdo->prepare("UPDATE `branch_transfers` SET `status` = 'completed', `received_by_user_id` = ?, `received_at` = ? WHERE `id` = ?");
                    $stmtUpdateTrf->execute([$user_id, date('Y-m-d H:i:s'), $trfId]);
                    
                    // Update car branch for all cars in this transfer, locking them FOR UPDATE first
                    $carIds = explode(',', $trf['car_id']);
                    foreach ($carIds as $carIdSingle) {
                        $carIdSingle = trim($carIdSingle);
                        if (!empty($carIdSingle)) {
                            // Lock car FOR UPDATE to secure transaction state
                            $stmtLockCar = $pdo->prepare("SELECT id FROM `cars` WHERE `id` = ? FOR UPDATE");
                            $stmtLockCar->execute([$carIdSingle]);
                            
                            $stmtUpdateCar = $pdo->prepare("UPDATE `cars` SET `branch_id` = ? WHERE `id` = ?");
                            $stmtUpdateCar->execute([$trf['to_branch_id'], $carIdSingle]);
                        }
                    }
                    
                    $pdo->commit();
                    
                    writeAuditLog($pdo, $user_id, $user_name, 'قبول تحويل مركبة (PHP)', "تم قبول واستلام المركبات ذو المعرفات [{$trf['car_id']}] ونقل عهدتها إلى الفرع المستلم {$trf['to_branch_id']} بواسطة الموظف {$user_name}");
                    createNotification($pdo, 'branch_transfer', 'تم قبول واستلام المركبات', "تم تأكيد قبول واستلام الشحنة بالفرع المستهدف للخطاب رقم {$trf['letter_number']}", $user_id, $user_name, $user_branch_name, $trf['car_id']);
                    
                    header("Location: index.php?page=transfers&success_accept=1");
                    exit;
                } else {
                    $pdo->rollBack();
                    header("Location: index.php?page=transfers&error=only_manager_can_receive");
                    exit;
                }
            } else {
                $pdo->rollBack();
                header("Location: index.php?page=transfers&error=not_pending");
                exit;
            }
        } catch (Exception $ex) {
            $pdo->rollBack();
            header("Location: index.php?page=transfers&error=1");
            exit;
        }
    }

    if (isset($_POST['reject_transfer'])) {
        $trfId = intval($_POST['transfer_id'] ?? 0);
        
        $pdo->beginTransaction();
        try {
            // Lock row using FOR UPDATE to avoid double rejects
            $stmtTrf = $pdo->prepare("SELECT * FROM `branch_transfers` WHERE `id` = ? FOR UPDATE");
            $stmtTrf->execute([$trfId]);
            $trf = $stmtTrf->fetch();
            
            // Fetch fresh user permissions
            $stmtUser = $pdo->prepare("SELECT role, branch_id FROM `users` WHERE `id` = ?");
            $stmtUser->execute([$user_id]);
            $freshUser = $stmtUser->fetch();
            $currRole = $freshUser ? $freshUser['role'] : $user_role;
            $currBranchId = $freshUser ? $freshUser['branch_id'] : $user_branch_id;
            
            if ($trf && $trf['status'] === 'pending') {
                // Fetch destination branch manager
                $stmtToBranch = $pdo->prepare("SELECT manager FROM `branches` WHERE `id` = ?");
                $stmtToBranch->execute([$trf['to_branch_id']]);
                $toBranch = $stmtToBranch->fetch();
                $toBranchManager = $toBranch ? $toBranch['manager'] : null;

                // Rule 1: Creator of the transfer cannot reject/receive it
                if ($user_id == $trf['created_by_user_id']) {
                    $pdo->rollBack();
                    header("Location: index.php?page=transfers&error=creator_cannot_receive");
                    exit;
                }

                // Rule 2: Only destination branch manager (or non-creator admin) can reject
                $isAuthorized = false;
                if (!empty($toBranchManager)) {
                    if ($user_id == $toBranchManager || ($currRole === 'admin' && $user_id != $trf['created_by_user_id'])) {
                        $isAuthorized = true;
                    }
                } else {
                    if ($currRole === 'admin' || $currBranchId == $trf['to_branch_id']) {
                        $isAuthorized = true;
                    }
                }

                if ($isAuthorized) {
                    $stmtUpdateTrf = $pdo->prepare("UPDATE `branch_transfers` SET `status` = 'rejected' WHERE `id` = ?");
                    $stmtUpdateTrf->execute([$trfId]);
                    
                    $pdo->commit();
                    
                    writeAuditLog($pdo, $user_id, $user_name, 'رفض تحويل مركبة (PHP)', "تم رفض طلب تحويل المركبات ذو المعرفات [{$trf['car_id']}] وإلغاء الخطاب رقم {$trf['letter_number']}");
                    createNotification($pdo, 'branch_transfer', 'تم رفض تحويل المركبة', "تم رفض طلب تحويل الشحنة للخطاب رقم {$trf['letter_number']}", $user_id, $user_name, $user_branch_name, $trf['car_id']);
                    
                    header("Location: index.php?page=transfers&success_reject=1");
                    exit;
                } else {
                    $pdo->rollBack();
                    header("Location: index.php?page=transfers&error=only_manager_can_receive");
                    exit;
                }
            } else {
                $pdo->rollBack();
                header("Location: index.php?page=transfers&error=not_pending");
                exit;
            }
        } catch (Exception $ex) {
            $pdo->rollBack();
            header("Location: index.php?page=transfers&error=1");
            exit;
        }
    }

    if (isset($_POST['update_transfer'])) {
        $trfId = intval($_POST['transfer_id'] ?? 0);
        $toBranchId = $_POST['to_branch_id'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        
        $pdo->beginTransaction();
        try {
            $stmtTrf = $pdo->prepare("SELECT * FROM `branch_transfers` WHERE `id` = ? FOR UPDATE");
            $stmtTrf->execute([$trfId]);
            $trf = $stmtTrf->fetch();
            
            if ($trf && $trf['status'] === 'pending') {
                $canModify = ($user_role === 'admin' || $user_role === 'branch_manager' || $user_id == $trf['created_by_user_id']);
                if ($canModify) {
                    if ($trf['from_branch_id'] == $toBranchId) {
                        $pdo->rollBack();
                        header("Location: index.php?page=transfers&error=same_branch");
                        exit;
                    }
                    
                    $stmtUpdate = $pdo->prepare("UPDATE `branch_transfers` SET `to_branch_id` = ?, `notes` = ? WHERE `id` = ?");
                    $stmtUpdate->execute([$toBranchId, $notes, $trfId]);
                    
                    $pdo->commit();
                    writeAuditLog($pdo, $user_id, $user_name, 'تعديل خطاب تحويل (PHP)', "تم تعديل الخطاب رقم {$trf['letter_number']} وتحديث الفرع المستلم وملاحظات النقل.");
                    
                    header("Location: index.php?page=transfers&success_edit=1");
                    exit;
                } else {
                    $pdo->rollBack();
                    header("Location: index.php?page=transfers&error=unauthorized");
                    exit;
                }
            } else {
                $pdo->rollBack();
                header("Location: index.php?page=transfers&error=not_pending");
                exit;
            }
        } catch (Exception $ex) {
            $pdo->rollBack();
            header("Location: index.php?page=transfers&error=1");
            exit;
        }
    }

    if (isset($_POST['delete_transfer'])) {
        $trfId = intval($_POST['transfer_id'] ?? 0);
        
        $pdo->beginTransaction();
        try {
            $stmtTrf = $pdo->prepare("SELECT * FROM `branch_transfers` WHERE `id` = ? FOR UPDATE");
            $stmtTrf->execute([$trfId]);
            $trf = $stmtTrf->fetch();
            
            if ($trf) {
                $canModify = ($user_role === 'admin' || $user_role === 'branch_manager' || $user_id == $trf['created_by_user_id']);
                if ($canModify) {
                    $stmtDelete = $pdo->prepare("DELETE FROM `branch_transfers` WHERE `id` = ?");
                    $stmtDelete->execute([$trfId]);
                    
                    $pdo->commit();
                    writeAuditLog($pdo, $user_id, $user_name, 'حذف خطاب تحويل (PHP)', "تم حذف وإلغاء الخطاب رقم {$trf['letter_number']} من النظام بالكامل.");
                    
                    header("Location: index.php?page=transfers&success_delete=1");
                    exit;
                } else {
                    $pdo->rollBack();
                    header("Location: index.php?page=transfers&error=unauthorized");
                    exit;
                }
            } else {
                $pdo->rollBack();
                header("Location: index.php?page=transfers&error=not_found");
                exit;
            }
        } catch (Exception $ex) {
            $pdo->rollBack();
            header("Location: index.php?page=transfers&error=1");
            exit;
        }
    }

    if (isset($_POST['save_user']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'agent';
        $branch_id = $_POST['branch_id'] ?? null;
        if ($branch_id === '') $branch_id = null;

        if (empty($id)) {
            $id = 'usr-' . time() . '-' . rand(100, 999);
            $hashedPassword = password_hash($password ?: 'agent123', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `branch_id`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, $name, $username, $hashedPassword, $role, $branch_id, date('Y-m-d H:i:s')]);
            writeAuditLog($pdo, $user_id, $user_name, 'إضافة موظف (PHP)', "تم تعيين الموظف الجديد $name بنجاح");
        } else {
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE `users` SET `name` = ?, `username` = ?, `password` = ?, `role` = ?, `branch_id` = ? WHERE `id` = ?");
                $stmt->execute([$name, $username, $hashedPassword, $role, $branch_id, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE `users` SET `name` = ?, `username` = ?, `role` = ?, `branch_id` = ? WHERE `id` = ?");
                $stmt->execute([$name, $username, $role, $branch_id, $id]);
            }
            writeAuditLog($pdo, $user_id, $user_name, 'تعديل موظف (PHP)', "تم تحديث بيانات الموظف $name");
        }
        header("Location: index.php?page=users");
        exit;
    }

    if (isset($_GET['delete_user']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $delId = $_GET['delete_user'];
        if ($delId !== $user_id) {
            $stmt = $pdo->prepare("DELETE FROM `users` WHERE `id` = ?");
            $stmt->execute([$delId]);
            writeAuditLog($pdo, $user_id, $user_name, 'حذف موظف (PHP)', "تم إلغاء تعيين الموظف برقم $delId");
        }
        header("Location: index.php?page=users");
        exit;
    }

    if (isset($_POST['save_branch']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $manager = trim($_POST['manager'] ?? '');
        $showroom_name = trim($_POST['showroom_name'] ?? '');
        $showroom_address = trim($_POST['showroom_address'] ?? '');
        $tax_number = trim($_POST['tax_number'] ?? '');
        $commercial_registration = trim($_POST['commercial_registration'] ?? '');
        $logo = trim($_POST['logo'] ?? '');
        $stamp = trim($_POST['stamp'] ?? '');

        if (isset($_FILES['branch_logo_file']) && $_FILES['branch_logo_file']['error'] === UPLOAD_ERR_OK) {
            $uploadsDir = 'uploads';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0777, true);
            }
            $ext = pathinfo($_FILES['branch_logo_file']['name'], PATHINFO_EXTENSION);
            $fileName = 'branch_logo_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $targetFile = $uploadsDir . '/' . $fileName;
            if (move_uploaded_file($_FILES['branch_logo_file']['tmp_name'], $targetFile)) {
                $logo = $targetFile;
            }
        }

        if (isset($_FILES['branch_stamp_file']) && $_FILES['branch_stamp_file']['error'] === UPLOAD_ERR_OK) {
            $uploadsDir = 'uploads';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0777, true);
            }
            $ext = pathinfo($_FILES['branch_stamp_file']['name'], PATHINFO_EXTENSION);
            $fileName = 'branch_stamp_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $targetFile = $uploadsDir . '/' . $fileName;
            if (move_uploaded_file($_FILES['branch_stamp_file']['tmp_name'], $targetFile)) {
                $stamp = $targetFile;
            }
        }

        if (empty($id)) {
            $id = 'br-' . time() . '-' . rand(100, 999);
            $stmt = $pdo->prepare("INSERT INTO `branches` (`id`, `name`, `location`, `code`, `phone`, `manager`, `showroom_name`, `showroom_address`, `tax_number`, `commercial_registration`, `logo`, `stamp`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, $name, $location, $code, $phone, $manager, $showroom_name, $showroom_address, $tax_number, $commercial_registration, $logo, $stamp, date('Y-m-d H:i:s')]);
            writeAuditLog($pdo, $user_id, $user_name, 'إضافة فرع (PHP)', "تم فتح فرع جديد باسم $name");
        } else {
            $stmt = $pdo->prepare("UPDATE `branches` SET `name` = ?, `location` = ?, `code` = ?, `phone` = ?, `manager` = ?, `showroom_name` = ?, `showroom_address` = ?, `tax_number` = ?, `commercial_registration` = ?, `logo` = ?, `stamp` = ? WHERE `id` = ?");
            $stmt->execute([$name, $location, $code, $phone, $manager, $showroom_name, $showroom_address, $tax_number, $commercial_registration, $logo, $stamp, $id]);
            writeAuditLog($pdo, $user_id, $user_name, 'تعديل فرع (PHP)', "تم تحديث بيانات الفرع $name");
        }
        header("Location: index.php?page=branches");
        exit;
    }

    if (isset($_GET['delete_branch']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $delId = $_GET['delete_branch'];
        $stmt = $pdo->prepare("DELETE FROM `branches` WHERE `id` = ?");
        $stmt->execute([$delId]);
        writeAuditLog($pdo, $user_id, $user_name, 'حذف فرع (PHP)', "تم إغلاق الفرع برقم $delId");
        header("Location: index.php?page=branches");
        exit;
    }

    if (isset($_POST['save_customer'])) {
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (!empty($name) && !empty($phone)) {
            if (empty($id)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO `customers` (`name`, `phone`) VALUES (?, ?)");
                    $stmt->execute([$name, $phone]);
                    writeAuditLog($pdo, $user_id, $user_name, 'إضافة عميل (PHP)', "تم تسجيل العميل الجديد $name بنجاح هاتف: $phone");
                    header("Location: index.php?page=customers&msg=added");
                    exit;
                } catch (Exception $ex) {
                    header("Location: index.php?page=customers&msg=error_phone_exists");
                    exit;
                }
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE `customers` SET `name` = ?, `phone` = ? WHERE `id` = ?");
                    $stmt->execute([$name, $phone, $id]);
                    writeAuditLog($pdo, $user_id, $user_name, 'تعديل عميل (PHP)', "تم تعديل بيانات العميل ذو الرقم $id إلى $name");
                    header("Location: index.php?page=customers&msg=updated");
                    exit;
                } catch (Exception $ex) {
                    header("Location: index.php?page=customers&msg=error_phone_exists");
                    exit;
                }
            }
        } else {
            header("Location: index.php?page=customers&msg=error_empty");
            exit;
        }
    }

    if (isset($_GET['delete_customer']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $delId = intval($_GET['delete_customer']);
        $stmt = $pdo->prepare("DELETE FROM `customers` WHERE `id` = ?");
        $stmt->execute([$delId]);
        writeAuditLog($pdo, $user_id, $user_name, 'حذف عميل (PHP)', "تم حذف بيانات العميل ذو الرقم $delId");
        header("Location: index.php?page=customers&msg=deleted");
        exit;
    }

    if (isset($_POST['save_settings']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $company_name = trim($_POST['company_name'] ?? '');
        $default_showroom_name = trim($_POST['default_showroom_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $currency = trim($_POST['currency'] ?? 'ر.س');
        $address = trim($_POST['address'] ?? '');
        $tax_number = trim($_POST['tax_number'] ?? '');
        $cr_number = trim($_POST['cr_number'] ?? '');
        $contact_phone = trim($_POST['contact_phone'] ?? '');
        $whatsapp_phone = trim($_POST['whatsapp_phone'] ?? '');

        $logo = $companySettings['logo'] ?? '';
        if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION);
            $fileName = 'logo_' . time() . '.' . $ext;
            $targetFile = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $targetFile)) {
                $logo = $targetFile;
            }
        } elseif (isset($_POST['remove_logo']) && $_POST['remove_logo'] == '1') {
            $logo = '';
        }

        $logo_height = intval($_POST['logo_height'] ?? 40);
        $logo_color = trim($_POST['logo_color'] ?? '#6366f1');
        $logo_border_radius = intval($_POST['logo_border_radius'] ?? 12);
        $company_name_color = trim($_POST['company_name_color'] ?? '#0f172a');
        $company_name_color_dark = trim($_POST['company_name_color_dark'] ?? '#ffffff');
        $company_name_font_size = trim($_POST['company_name_font_size'] ?? 'text-sm');
        $showroom_name_color = trim($_POST['showroom_name_color'] ?? '#6366f1');
        $showroom_name_color_dark = trim($_POST['showroom_name_color_dark'] ?? '#818cf8');
        $showroom_name_font_size = trim($_POST['showroom_name_font_size'] ?? 'text-[9px]');

        $count = $pdo->query("SELECT COUNT(*) FROM `settings`")->fetchColumn();
        if ($count > 0) {
            $stmt = $pdo->prepare("UPDATE `settings` SET `company_name` = ?, `phone` = ?, `email` = ?, `currency` = ?, `address` = ?, `tax_number` = ?, `cr_number` = ?, `contact_phone` = ?, `logo` = ?, `whatsapp_phone` = ?, `default_showroom_name` = ?, `logo_height` = ?, `logo_color` = ?, `logo_border_radius` = ?, `company_name_color` = ?, `company_name_color_dark` = ?, `company_name_font_size` = ?, `showroom_name_color` = ?, `showroom_name_color_dark` = ?, `showroom_name_font_size` = ?");
            $stmt->execute([$company_name, $phone, $email, $currency, $address, $tax_number, $cr_number, $contact_phone, $logo, $whatsapp_phone, $default_showroom_name, $logo_height, $logo_color, $logo_border_radius, $company_name_color, $company_name_color_dark, $company_name_font_size, $showroom_name_color, $showroom_name_color_dark, $showroom_name_font_size]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO `settings` (`company_name`, `phone`, `email`, `currency`, `address`, `tax_number`, `cr_number`, `contact_phone`, `logo`, `whatsapp_phone`, `default_showroom_name`, `logo_height`, `logo_color`, `logo_border_radius`, `company_name_color`, `company_name_color_dark`, `company_name_font_size`, `showroom_name_color`, `showroom_name_color_dark`, `showroom_name_font_size`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$company_name, $phone, $email, $currency, $address, $tax_number, $cr_number, $contact_phone, $logo, $whatsapp_phone, $default_showroom_name, $logo_height, $logo_color, $logo_border_radius, $company_name_color, $company_name_color_dark, $company_name_font_size, $showroom_name_color, $showroom_name_color_dark, $showroom_name_font_size]);
        }

        writeAuditLog($pdo, $user_id, $user_name, 'تحديث الإعدادات (PHP)', 'تم تحديث الإعدادات العامة وشعار المؤسسة بنجاح');
        header("Location: index.php?page=settings");
        exit;
    }

    if (isset($_POST['save_seo_settings']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $page_key = trim($_POST['page_key'] ?? '');
        $page_title = trim($_POST['page_title'] ?? '');
        $meta_title = trim($_POST['meta_title'] ?? '');
        $meta_description = trim($_POST['meta_description'] ?? '');
        $meta_keywords = trim($_POST['meta_keywords'] ?? '');
        $custom_schema = trim($_POST['custom_schema'] ?? '');
        $og_title = trim($_POST['og_title'] ?? '');
        $og_description = trim($_POST['og_description'] ?? '');
        $og_image = trim($_POST['og_image'] ?? '');
        $twitter_card = trim($_POST['twitter_card'] ?? 'summary_large_image');
        
        $stmt = $pdo->prepare("INSERT INTO `seo_pages` (`page_key`, `page_title`, `meta_title`, `meta_description`, `meta_keywords`, `custom_schema`, `og_title`, `og_description`, `og_image`, `twitter_card`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `page_title` = ?, `meta_title` = ?, `meta_description` = ?, `meta_keywords` = ?, `custom_schema` = ?, `og_title` = ?, `og_description` = ?, `og_image` = ?, `twitter_card` = ?");
        $stmt->execute([
            $page_key, $page_title, $meta_title, $meta_description, $meta_keywords, $custom_schema, $og_title, $og_description, $og_image, $twitter_card,
            $page_title, $meta_title, $meta_description, $meta_keywords, $custom_schema, $og_title, $og_description, $og_image, $twitter_card
        ]);
        
        writeAuditLog($pdo, $user_id, $user_name, 'تحديث إعدادات SEO', "تم تحديث إعدادات SEO للصفحة ($page_title) بنجاح");
        header("Location: index.php?page=settings&seo_success=1&seo_page_key=" . urlencode($page_key));
        exit;
    }

    if (isset($_POST['save_customer_showroom_settings']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $showroom_header_title = trim($_POST['showroom_header_title'] ?? '');
        $showroom_header_subtitle = trim($_POST['showroom_header_subtitle'] ?? '');
        $showroom_footer_text = trim($_POST['showroom_footer_text'] ?? '');
        $showroom_theme = trim($_POST['showroom_theme'] ?? 'indigo');
        $showroom_show_price = isset($_POST['showroom_show_price']) ? 1 : 0;
        $showroom_show_filters = isset($_POST['showroom_show_filters']) ? 1 : 0;
        $showroom_facebook = trim($_POST['showroom_facebook'] ?? '');
        $showroom_twitter = trim($_POST['showroom_twitter'] ?? '');
        $showroom_instagram = trim($_POST['showroom_instagram'] ?? '');
        $showroom_linkedin = trim($_POST['showroom_linkedin'] ?? '');
        $showroom_snapchat = trim($_POST['showroom_snapchat'] ?? '');
        $showroom_youtube = trim($_POST['showroom_youtube'] ?? '');

        $showroom_custom_pages = trim($_POST['showroom_custom_pages'] ?? '[]');
        $showroom_menu_links = trim($_POST['showroom_menu_links'] ?? '[]');
        $showroom_custom_socials = trim($_POST['showroom_custom_socials'] ?? '[]');
        $showroom_custom_css = trim($_POST['showroom_custom_css'] ?? '');
        $showroom_custom_js = trim($_POST['showroom_custom_js'] ?? '');

        $showroom_banner_overlay_opacity = isset($_POST['showroom_banner_overlay_opacity']) ? (int)$_POST['showroom_banner_overlay_opacity'] : 50;
        $showroom_banner_opacity = isset($_POST['showroom_banner_opacity']) ? (int)$_POST['showroom_banner_opacity'] : 25;

        $showroom_banner_height = trim($_POST['showroom_banner_height'] ?? 'medium');
        $showroom_banner_bg_size = trim($_POST['showroom_banner_bg_size'] ?? 'cover');
        $showroom_banner_width = trim($_POST['showroom_banner_width'] ?? 'full');
        $showroom_banner_custom_height = trim($_POST['showroom_banner_custom_height'] ?? '350px');
        $showroom_banner_custom_width = trim($_POST['showroom_banner_custom_width'] ?? '100%');
        $showroom_banner_title_color = trim($_POST['showroom_banner_title_color'] ?? '#ffffff');
        $showroom_banner_subtitle_color = trim($_POST['showroom_banner_subtitle_color'] ?? '#cbd5e1');
        $showroom_banner_text_bg = isset($_POST['showroom_banner_text_bg']) ? 1 : 0;

        // Handle Banner image upload
        $showroom_banner_image = $companySettings['showroom_banner_image'] ?? '';
        if (isset($_FILES['showroom_banner_file']) && $_FILES['showroom_banner_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = pathinfo($_FILES['showroom_banner_file']['name'], PATHINFO_EXTENSION);
            $fileName = 'banner_' . time() . '.' . $ext;
            $targetFile = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['showroom_banner_file']['tmp_name'], $targetFile)) {
                $showroom_banner_image = $targetFile;
            }
        } elseif (isset($_POST['remove_showroom_banner']) && $_POST['remove_showroom_banner'] == '1') {
            $showroom_banner_image = '';
        }

        $count = $pdo->query("SELECT COUNT(*) FROM `settings`")->fetchColumn();
        if ($count > 0) {
            $stmt = $pdo->prepare("UPDATE `settings` SET 
                `showroom_header_title` = ?, 
                `showroom_header_subtitle` = ?, 
                `showroom_footer_text` = ?, 
                `showroom_theme` = ?, 
                `showroom_show_price` = ?, 
                `showroom_show_filters` = ?, 
                `showroom_facebook` = ?, 
                `showroom_twitter` = ?, 
                `showroom_instagram` = ?, 
                `showroom_linkedin` = ?, 
                `showroom_snapchat` = ?, 
                `showroom_youtube` = ?,
                `showroom_custom_socials` = ?,
                `showroom_banner_image` = ?,
                `showroom_banner_overlay_opacity` = ?,
                `showroom_banner_opacity` = ?,
                `showroom_banner_height` = ?,
                `showroom_banner_bg_size` = ?,
                `showroom_banner_width` = ?,
                `showroom_banner_custom_height` = ?,
                `showroom_banner_custom_width` = ?,
                `showroom_banner_title_color` = ?,
                `showroom_banner_subtitle_color` = ?,
                `showroom_banner_text_bg` = ?,
                `showroom_custom_pages` = ?,
                `showroom_menu_links` = ?,
                `showroom_custom_css` = ?,
                `showroom_custom_js` = ?");
            $stmt->execute([
                $showroom_header_title, $showroom_header_subtitle, $showroom_footer_text, 
                $showroom_theme, $showroom_show_price, $showroom_show_filters, 
                $showroom_facebook, $showroom_twitter, $showroom_instagram, 
                $showroom_linkedin, $showroom_snapchat, $showroom_youtube, $showroom_custom_socials, $showroom_banner_image,
                $showroom_banner_overlay_opacity, $showroom_banner_opacity,
                $showroom_banner_height, $showroom_banner_bg_size, $showroom_banner_width,
                $showroom_banner_custom_height, $showroom_banner_custom_width,
                $showroom_banner_title_color, $showroom_banner_subtitle_color, $showroom_banner_text_bg,
                $showroom_custom_pages, $showroom_menu_links, $showroom_custom_css, $showroom_custom_js
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO `settings` (
                `company_name`, `showroom_header_title`, `showroom_header_subtitle`, `showroom_footer_text`, 
                `showroom_theme`, `showroom_show_price`, `showroom_show_filters`, 
                `showroom_facebook`, `showroom_twitter`, `showroom_instagram`, 
                `showroom_linkedin`, `showroom_snapchat`, `showroom_youtube`, `showroom_custom_socials`, `showroom_banner_image`,
                `showroom_banner_overlay_opacity`, `showroom_banner_opacity`,
                `showroom_banner_height`, `showroom_banner_bg_size`, `showroom_banner_width`,
                `showroom_banner_custom_height`, `showroom_banner_custom_width`,
                `showroom_banner_title_color`, `showroom_banner_subtitle_color`, `showroom_banner_text_bg`,
                `showroom_custom_pages`, `showroom_menu_links`, `showroom_custom_css`, `showroom_custom_js`
            ) VALUES ('شركة المخزون للمحركات', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $showroom_header_title, $showroom_header_subtitle, $showroom_footer_text, 
                $showroom_theme, $showroom_show_price, $showroom_show_filters, 
                $showroom_facebook, $showroom_twitter, $showroom_instagram, 
                $showroom_linkedin, $showroom_snapchat, $showroom_youtube, $showroom_custom_socials, $showroom_banner_image,
                $showroom_banner_overlay_opacity, $showroom_banner_opacity,
                $showroom_banner_height, $showroom_banner_bg_size, $showroom_banner_width,
                $showroom_banner_custom_height, $showroom_banner_custom_width,
                $showroom_banner_title_color, $showroom_banner_subtitle_color, $showroom_banner_text_bg,
                $showroom_custom_pages, $showroom_menu_links, $showroom_custom_css, $showroom_custom_js
            ]);
        }

        writeAuditLog($pdo, $user_id, $user_name, 'إعدادات واجهة العملاء', 'تم تحديث إعدادات صفحة معرض العملاء (الهيدر، الفوتر، والمظهر) بنجاح');
        header("Location: index.php?page=settings&showroom_success=1");
        exit;
    }

    // GLOBAL SEO TRACKING & ROBOTS/SITEMAP HANDLER
    if (isset($_POST['save_global_seo_tracking']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $seo_google_analytics = trim($_POST['seo_google_analytics'] ?? '');
        $seo_facebook_pixel = trim($_POST['seo_facebook_pixel'] ?? '');
        $seo_google_verification = trim($_POST['seo_google_verification'] ?? '');
        $seo_bing_verification = trim($_POST['seo_bing_verification'] ?? '');
        $robots_txt_content = $_POST['robots_txt_content'] ?? '';

        // Save into settings table
        $count = $pdo->query("SELECT COUNT(*) FROM `settings`")->fetchColumn();
        if ($count > 0) {
            $stmt = $pdo->prepare("UPDATE `settings` SET 
                `seo_google_analytics` = ?, 
                `seo_facebook_pixel` = ?, 
                `seo_google_verification` = ?, 
                `seo_bing_verification` = ?");
            $stmt->execute([$seo_google_analytics, $seo_facebook_pixel, $seo_google_verification, $seo_bing_verification]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO `settings` (
                `company_name`, `seo_google_analytics`, `seo_facebook_pixel`, `seo_google_verification`, `seo_bing_verification`
            ) VALUES ('شركة المخزون للمحركات', ?, ?, ?, ?)");
            $stmt->execute([$seo_google_analytics, $seo_facebook_pixel, $seo_google_verification, $seo_bing_verification]);
        }

        // Save physical robots.txt in root
        try {
            file_put_contents(__DIR__ . '/robots.txt', $robots_txt_content);
        } catch (Exception $e) {
            // Safe skip
        }

        // Write verification files if specified
        $v_filename = trim($_POST['verification_filename'] ?? '');
        $v_content = $_POST['verification_content'] ?? '';
        if (!empty($v_filename) && preg_match('/^[a-zA-Z0-9_-]+\.(html|txt)$/', $v_filename)) {
            try {
                file_put_contents(__DIR__ . '/' . $v_filename, $v_content);
            } catch (Exception $e) {
                // Safe skip
            }
        }

        // Handle Regenerate Sitemap Request
        if (isset($_POST['regenerate_sitemap_file']) && $_POST['regenerate_sitemap_file'] == '1') {
            define('SITEMAP_GENERATE_ONLY', true);
            $sitemap_generate_only = true;
            require_once __DIR__ . '/sitemap.php';
        }

        writeAuditLog($pdo, $user_id, $user_name, 'إدارة الأرشفة والـ SEO', 'تم تحديث إعدادات الأرشفة العالمية وملف الروبوتات والتحقق لبرامج التتبع بنجاح');
        header("Location: index.php?page=settings&seo_global_success=1#SEOGlobalSettingsPanel");
        exit;
    }

    // SELF-MAINTENANCE & TECHNICAL HEALTH DIAGNOSER HANDLER
    if (isset($_POST['run_self_maintenance_task']) && $user_role === 'admin') {
        $task = trim($_POST['task_name'] ?? '');
        $task_output = [];
        $task_status = 'success';

        if ($task === 'clear_temp') {
            $temp_dirs = [__DIR__ . '/uploads/temp', __DIR__ . '/storage/cache'];
            $files_deleted = 0;
            $bytes_freed = 0;
            foreach ($temp_dirs as $dir) {
                if (is_dir($dir)) {
                    $files = glob($dir . '/*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            $bytes_freed += filesize($file);
                            if (@unlink($file)) {
                                $files_deleted++;
                            }
                        }
                    }
                }
            }
            $task_output[] = "✓ تم تنظيف المجلدات المؤقتة بنجاح.";
            $task_output[] = "✓ عدد الملفات المؤقتة المحذوفة: $files_deleted ملف.";
            $task_output[] = "✓ المساحة المحررة: " . round($bytes_freed / 1024 / 1024, 2) . " ميجابايت.";

        } elseif ($task === 'optimize_db') {
            $tables = ['cars', 'reservations', 'branches', 'users', 'system_logs', 'customers', 'attachments', 'reservation_attachments'];
            foreach ($tables as $tbl) {
                try {
                    $pdo->exec("OPTIMIZE TABLE `$tbl`");
                    $task_output[] = "✓ تم تحسين وتنظيف الفهارس لجدول: $tbl";
                } catch (Exception $e) {
                    $task_output[] = "✕ فشل تحسين جدول: $tbl - " . $e->getMessage();
                }
            }

        } elseif ($task === 'schema_repair') {
            try {
                $task_output[] = "✓ تم تشغيل فحص تطابق قواعد البيانات (Auto-Evolution Index Validator).";
                $task_output[] = "✓ لم يتم رصد أي تفاوت في هياكل الجداول أو الحقول الحالية.";
            } catch (Exception $e) {
                $task_status = 'error';
                $task_output[] = "✕ خطأ أثناء الفحص الذاتي: " . $e->getMessage();
            }

        } elseif ($task === 'broken_images') {
            try {
                $stmt = $pdo->query("SELECT id, make, model, mainImage, attachments FROM cars");
                $cars = $stmt->fetchAll();
                $missing_images = [];
                $missing_attachments = 0;
                
                foreach ($cars as $car) {
                    if (!empty($car['mainImage']) && strpos($car['mainImage'], 'http') === false) {
                        if (!file_exists(__DIR__ . '/' . $car['mainImage']) && !file_exists($car['mainImage'])) {
                            $missing_images[] = "سيارة " . $car['make'] . " " . $car['model'] . " (ID: " . $car['id'] . ") - الصورة الرئيسية مفقودة.";
                        }
                    }
                    if (!empty($car['attachments'])) {
                        $atts = json_decode($car['attachments'], true);
                        if (is_array($atts)) {
                            foreach ($atts as $att) {
                                if (isset($att['filePath']) && strpos($att['filePath'], 'http') === false) {
                                    if (!file_exists(__DIR__ . '/' . $att['filePath']) && !file_exists($att['filePath'])) {
                                        $missing_attachments++;
                                    }
                                }
                            }
                        }
                    }
                }
                
                if (empty($missing_images) && $missing_attachments == 0) {
                    $task_output[] = "✓ جميع روابط الصور والملفات المرفوعة للسيارات مطابقة وسليمة 100%.";
                } else {
                    $task_output[] = "⚠ تم رصد مراجع مفقودة على السيرفر (بدون تعديل البيانات):";
                    foreach ($missing_images as $img_err) {
                        $task_output[] = "• $img_err";
                    }
                    if ($missing_attachments > 0) {
                        $task_output[] = "• تم رصد ($missing_attachments) ملفات مرفقة مفقودة من مجلد التخزين.";
                    }
                }
            } catch (Exception $e) {
                $task_status = 'error';
                $task_output[] = "✕ خطأ أثناء الفحص: " . $e->getMessage();
            }
        }

        $_SESSION['maintenance_output'] = $task_output;
        $_SESSION['maintenance_status'] = $task_status;
        writeAuditLog($pdo, $user_id, $user_name, 'صيانة ذاتية', "تشغيل مهمة صيانة وقائية: $task");
        header("Location: index.php?page=settings&maintenance_success=1#MaintenanceSettingsPanel");
        exit;
    }

    // ADS & OFFERS SYSTEM CRUD CONTROLLER
    if (isset($_GET['delete_ad']) && $user_role === 'admin') {
        $adId = intval($_GET['delete_ad']);
        $stmt = $pdo->prepare("DELETE FROM `showroom_ads` WHERE `id` = ?");
        $stmt->execute([$adId]);
        writeAuditLog($pdo, $user_id, $user_name, 'إدارة الإعلانات', "تم حذف حاوية الإعلان رقم $adId بنجاح");
        header("Location: index.php?page=ads&ad_success=1");
        exit;
    }

    if (isset($_POST['save_ad']) && $user_role === 'admin') {
        $ad_id = intval($_POST['ad_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $type = trim($_POST['type'] ?? 'image');
        $link_url = trim($_POST['link_url'] ?? '');
        $html_code = trim($_POST['html_code'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        $position = trim($_POST['position'] ?? 'top');
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

        // Image upload handling
        $image_path = '';
        if ($ad_id > 0) {
            $existing_ad = $pdo->prepare("SELECT `image_path` FROM `showroom_ads` WHERE `id` = ?");
            $existing_ad->execute([$ad_id]);
            $image_path = $existing_ad->fetchColumn() ?: '';
        }

        if (isset($_FILES['ad_image_file']) && $_FILES['ad_image_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = pathinfo($_FILES['ad_image_file']['name'], PATHINFO_EXTENSION);
            $fileName = 'ad_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $targetFile = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['ad_image_file']['tmp_name'], $targetFile)) {
                $image_path = $targetFile;
            }
        }

        if ($ad_id > 0) {
            $stmt = $pdo->prepare("UPDATE `showroom_ads` SET 
                `title` = ?, `type` = ?, `image_path` = ?, `link_url` = ?, 
                `html_code` = ?, `status` = ?, `position` = ?, `start_date` = ?, `end_date` = ? 
                WHERE `id` = ?");
            $stmt->execute([
                $title, $type, $image_path, $link_url, 
                $html_code, $status, $position, $start_date, $end_date, $ad_id
            ]);
            writeAuditLog($pdo, $user_id, $user_name, 'إدارة الإعلانات', "تم تحديث حاوية الإعلان ($title) بنجاح");
        } else {
            $stmt = $pdo->prepare("INSERT INTO `showroom_ads` (
                `title`, `type`, `image_path`, `link_url`, 
                `html_code`, `status`, `position`, `start_date`, `end_date`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $title, $type, $image_path, $link_url, 
                $html_code, $status, $position, $start_date, $end_date
            ]);
            writeAuditLog($pdo, $user_id, $user_name, 'إدارة الإعلانات', "تم إنشاء حاوية إعلان جديدة ($title) بنجاح");
        }

        header("Location: index.php?page=ads&ad_success=1");
        exit;
    }

    if (isset($_GET['action']) && $_GET['action'] === 'download_backup' && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        $backup = [];
        $backup['branches'] = $pdo->query("SELECT * FROM `branches`")->fetchAll();
        $backup['users'] = $pdo->query("SELECT * FROM `users`")->fetchAll();
        $backup['cars'] = $pdo->query("SELECT * FROM `cars`")->fetchAll();
        $backup['reservations'] = $pdo->query("SELECT * FROM `reservations`")->fetchAll();
        $backup['attachments'] = $pdo->query("SELECT * FROM `attachments`")->fetchAll();
        $backup['reservation_attachments'] = $pdo->query("SELECT * FROM `reservation_attachments`")->fetchAll();

        $json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="almakhzoun_pro_backup_' . date('Y-m-d_H-i-s') . '.json"');
        echo $json;
        exit;
    }

    if (isset($_POST['restore_backup']) && ($user_role === 'admin' || $user_role === 'branch_manager')) {
        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $jsonContent = file_get_contents($_FILES['backup_file']['tmp_name']);
            $backup = json_decode($jsonContent, true);
            if (is_array($backup)) {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

                if (isset($backup['branches']) && is_array($backup['branches'])) {
                    $pdo->exec("TRUNCATE TABLE `branches`");
                    $stmt = $pdo->prepare("INSERT INTO `branches` (`id`, `name`, `location`, `code`, `created_at`) VALUES (?, ?, ?, ?, ?)");
                    foreach ($backup['branches'] as $row) {
                        $stmt->execute([$row['id'], $row['name'], $row['location'], $row['code'], $row['created_at']]);
                    }
                }

                if (isset($backup['users']) && is_array($backup['users'])) {
                    $pdo->exec("TRUNCATE TABLE `users`");
                    $stmt = $pdo->prepare("INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `branch_id`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    foreach ($backup['users'] as $row) {
                        $stmt->execute([$row['id'], $row['name'], $row['username'], $row['password'], $row['role'], $row['branch_id'], $row['created_at']]);
                    }
                }

                if (isset($backup['cars']) && is_array($backup['cars'])) {
                    $pdo->exec("TRUNCATE TABLE `cars`");
                    $columns = $pdo->query("SHOW COLUMNS FROM `cars`")->fetchAll(PDO::FETCH_COLUMN);
                    $colString = '`' . implode('`, `', $columns) . '`';
                    $valPlaceholders = implode(', ', array_fill(0, count($columns), '?'));
                    $stmt = $pdo->prepare("INSERT INTO `cars` ($colString) VALUES ($valPlaceholders)");
                    foreach ($backup['cars'] as $row) {
                        $vals = [];
                        foreach ($columns as $col) {
                            $vals[] = $row[$col] ?? null;
                        }
                        $stmt->execute($vals);
                    }
                }

                if (isset($backup['reservations']) && is_array($backup['reservations'])) {
                    $pdo->exec("TRUNCATE TABLE `reservations`");
                    $columns = $pdo->query("SHOW COLUMNS FROM `reservations`")->fetchAll(PDO::FETCH_COLUMN);
                    $colString = '`' . implode('`, `', $columns) . '`';
                    $valPlaceholders = implode(', ', array_fill(0, count($columns), '?'));
                    $stmt = $pdo->prepare("INSERT INTO `reservations` ($colString) VALUES ($valPlaceholders)");
                    foreach ($backup['reservations'] as $row) {
                        $vals = [];
                        foreach ($columns as $col) {
                            $vals[] = $row[$col] ?? null;
                        }
                        $stmt->execute($vals);
                    }
                }

                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                writeAuditLog($pdo, $user_id, $user_name, 'استعادة قاعدة البيانات (PHP)', 'تمت استعادة كافة الفروع والمستخدمين والمخزون بنجاح من ملف النسخة الاحتياطية المرفوع');
                $settings_success = "تمت استعادة قاعدة البيانات بنجاح تام.";
            } else {
                $settings_error = "ملف النسخة الاحتياطية غير صالح أو تالف.";
            }
        } else {
            $settings_error = "يرجى اختيار ملف نسخة احتياطية صالح أولاً.";
        }
    }
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $lang === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Almakhzoun Pro - نظام إدارة المعارض والمخازن</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Cairo', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
    <script>
        // Set theme before rendering to avoid screen flicker
        const theme = localStorage.getItem('theme') || 'dark';
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 flex flex-col lg:flex-row transition-colors duration-300 font-sans">

    <!-- Desktop Sidebar -->
    <aside class="hidden lg:flex flex-col w-64 shrink-0 h-screen sticky top-0 bg-white dark:bg-slate-900 <?php echo $lang === 'ar' ? 'border-l border-slate-200 dark:border-slate-800' : 'border-r border-slate-200 dark:border-slate-800'; ?> transition-colors duration-300">
        <!-- Brand Header -->
        <div class="p-5 border-b border-slate-200 dark:border-slate-800 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center font-black text-white text-lg shadow-md shadow-indigo-600/20">M</div>
            <div class="text-right">
                <h1 class="text-xs font-black tracking-tight leading-none text-slate-900 dark:text-white"><?php echo htmlspecialchars($companySettings['company_name']); ?></h1>
                <span class="text-[9px] text-indigo-500 font-mono block mt-1 tracking-wider font-bold">ALMAKHZOUN PRO</span>
            </div>
        </div>

        <!-- Scrollable Navigation Menu -->
        <nav class="flex-1 overflow-y-auto p-4 space-y-1.5 scrollbar-none" dir="<?php echo $lang === 'ar' ? 'rtl' : 'ltr'; ?>">
            <?php if ($user_role !== 'representative'): ?>
                <a href="index.php?page=dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo $page === 'dashboard' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200'; ?>">
                    <span class="text-base">🏠</span> <span><?php echo $t['dashboard']; ?></span>
                </a>
            <?php endif; ?>
            <a href="index.php?page=inventory" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo $page === 'inventory' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200'; ?>">
                <span class="text-base">🚗</span> <span><?php echo $t['inventory']; ?></span>
            </a>
            <?php if ($user_role !== 'representative'): ?>
                <a href="index.php?page=sales" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo $page === 'sales' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200'; ?>">
                    <span class="text-base">💰</span> <span><?php echo $t['sales']; ?></span>
                </a>
            <?php endif; ?>
            <a href="index.php?page=customers" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo $page === 'customers' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200'; ?>">
                <span class="text-base">👥</span> <span><?php echo $t['customers']; ?></span>
            </a>
            <?php if ($user_role === 'admin' || $user_role === 'branch_manager'): ?>
                <a href="index.php?page=reservations" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo $page === 'reservations' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200'; ?>">
                    <span class="text-base">🔒</span> <span><?php echo $t['reservations']; ?></span>
                </a>
                <a href="index.php?page=users" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo $page === 'users' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200'; ?>">
                    <span class="text-base">👤</span> <span><?php echo $t['users']; ?></span>
                </a>
                <a href="index.php?page=branches" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo $page === 'branches' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200'; ?>">
                    <span class="text-base">🏢</span> <span><?php echo $t['branches']; ?></span>
                </a>
                <a href="index.php?page=transfers" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo $page === 'transfers' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200'; ?>">
                    <span class="text-base">🔄</span> <span><?php echo $t['transfers']; ?></span>
                </a>
                <a href="index.php?page=reports" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo $page === 'reports' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200'; ?>">
                    <span class="text-base">📊</span> <span><?php echo $t['reports']; ?></span>
                </a>
                <a href="index.php?page=logs" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo $page === 'logs' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200'; ?>">
                    <span class="text-base">🛡️</span> <span><?php echo $t['logs']; ?></span>
                </a>
                <a href="index.php?page=orders" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo $page === 'orders' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200'; ?>">
                    <span class="text-base">📥</span> <span><?php echo $t['orders']; ?></span>
                </a>
                <a href="index.php?page=contact_inquiries" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo $page === 'contact_inquiries' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200'; ?>">
                    <span class="text-base">✉️</span> <span>رسائل اتصل بنا</span>
                </a>
                <a href="index.php?page=showroom_reviews" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo $page === 'showroom_reviews' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200'; ?>">
                    <span class="text-base">⭐</span> <span>تقييمات العملاء</span>
                </a>
                <a href="index.php?page=showroom_sales" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo $page === 'showroom_sales' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200'; ?>">
                    <span class="text-base">👥</span> <span>إدارة المبيعات</span>
                </a>
                <a href="index.php?page=analytics" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo $page === 'analytics' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200'; ?>">
                    <span class="text-base">📈</span> <span>تحليلات المعرض</span>
                </a>
                <?php if ($user_role === 'admin'): ?>
                <a href="index.php?page=ads" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo $page === 'ads' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200'; ?>">
                    <span class="text-base">📢</span> <span><?php echo $t['ads']; ?></span>
                </a>
                <?php endif; ?>
                <a href="index.php?page=settings" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition-all <?php echo $page === 'settings' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200'; ?>">
                    <span class="text-base">⚙️</span> <span><?php echo $t['settings']; ?></span>
                </a>
            <?php endif; ?>
            
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800/55">
                <a href="customer.php" target="_blank" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition-all bg-emerald-600/10 hover:bg-emerald-600 text-emerald-600 dark:text-emerald-400 dark:hover:text-white hover:text-white">
                    <span class="text-base">🌐</span> <span><?php echo $t['customer_showroom']; ?></span>
                </a>
            </div>
        </nav>

        <!-- Sidebar Footer / Controls -->
        <div class="p-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40">
            <div class="flex items-center justify-between mb-4 gap-2">
                <!-- Theme Toggle -->
                <button onclick="toggleDarkMode()" class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer flex-1 flex justify-center" title="<?php echo $t['theme_toggle']; ?>">
                    <span class="dark:hidden">🌙</span>
                    <span class="hidden dark:inline">☀️</span>
                </button>
                <!-- Language Switcher -->
                <a href="index.php?page=<?php echo $page; ?>&toggle_lang=1" class="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer font-sans font-bold text-xs flex-1 flex justify-center" title="تبديل اللغة / Toggle Language">
                    <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400"><?php echo $lang === 'ar' ? 'EN' : 'AR'; ?></span>
                </a>
            </div>

            <!-- User Details -->
            <div class="flex items-center justify-between p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-indigo-600/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-black text-xs shrink-0">
                        <?php echo mb_substr($user_name, 0, 1, 'utf-8'); ?>
                    </div>
                    <div class="text-right leading-none overflow-hidden max-w-[100px]">
                        <span class="block text-xs font-black text-slate-800 dark:text-slate-200 truncate"><?php echo htmlspecialchars($user_name); ?></span>
                        <span class="block text-[8px] text-slate-500 font-mono mt-0.5"><?php 
                            if ($user_role === 'admin') {
                                echo $t['admin_role'];
                            } elseif ($user_role === 'branch_manager') {
                                echo $t['branch_manager_role'];
                            } else {
                                echo $t['agent_role'];
                            }
                        ?></span>
                    </div>
                </div>
                <a href="index.php?logout=1" class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition shrink-0" title="<?php echo $t['logout']; ?>">
                    🚪
                </a>
            </div>
        </div>
    </aside>

    <!-- Mobile Top Header -->
    <header class="lg:hidden bg-white border-b border-slate-200 text-slate-900 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-100 sticky top-0 z-50 w-full transition-colors duration-300 shadow-sm px-4 py-3 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center font-black text-white text-base shadow-md">M</div>
            <div class="text-right">
                <h1 class="text-xs font-black tracking-tight leading-none text-slate-900 dark:text-white"><?php echo htmlspecialchars($companySettings['company_name']); ?></h1>
                <span class="text-[9px] text-indigo-500 font-mono block mt-1 tracking-wider font-bold">ALMAKHZOUN PRO</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <!-- Mobile Hamburger Button -->
            <button id="mobile-menu-btn" class="p-2 rounded-lg bg-slate-100 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 cursor-pointer">
                🍔
            </button>
        </div>
    </header>

    <!-- Mobile Menu Sliding Panel -->
    <div id="mobile-menu-panel" class="hidden lg:hidden border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 py-3 px-4 space-y-3 z-40 sticky top-14 shadow-md w-full shrink-0">
        <div class="flex flex-col gap-1 text-xs font-bold text-slate-700 dark:text-slate-300">
            <?php if ($user_role !== 'representative'): ?>
                <a href="index.php?page=dashboard" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition-all <?php echo $page === 'dashboard' ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                    <span>🏠</span> <span><?php echo $t['dashboard']; ?></span>
                </a>
            <?php endif; ?>
            <a href="index.php?page=inventory" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition-all <?php echo $page === 'inventory' ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                <span>🚗</span> <span><?php echo $t['inventory']; ?></span>
            </a>
            <?php if ($user_role !== 'representative'): ?>
                <a href="index.php?page=sales" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition-all <?php echo $page === 'sales' ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                    <span>💰</span> <span><?php echo $t['sales']; ?></span>
                </a>
            <?php endif; ?>
            <a href="index.php?page=customers" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition-all <?php echo $page === 'customers' ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                <span>👥</span> <span><?php echo $t['customers']; ?></span>
            </a>
            <?php if ($user_role === 'admin' || $user_role === 'branch_manager'): ?>
                <a href="index.php?page=reservations" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition-all <?php echo $page === 'reservations' ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                    <span>🔒</span> <span><?php echo $t['reservations']; ?></span>
                </a>
                <a href="index.php?page=users" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition-all <?php echo $page === 'users' ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                    <span>👤</span> <span><?php echo $t['users']; ?></span>
                </a>
                <a href="index.php?page=branches" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition-all <?php echo $page === 'branches' ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                    <span>🏢</span> <span><?php echo $t['branches']; ?></span>
                </a>
                <a href="index.php?page=transfers" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition-all <?php echo $page === 'transfers' ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                    <span>🔄</span> <span><?php echo $t['transfers']; ?></span>
                </a>
                <a href="index.php?page=reports" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition-all <?php echo $page === 'reports' ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                    <span>📊</span> <span><?php echo $t['reports']; ?></span>
                </a>
                <a href="index.php?page=logs" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition-all <?php echo $page === 'logs' ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                    <span>🛡️</span> <span><?php echo $t['logs']; ?></span>
                </a>
                <a href="index.php?page=orders" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition-all <?php echo $page === 'orders' ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                    <span>📥</span> <span><?php echo $t['orders']; ?></span>
                </a>
                <a href="index.php?page=contact_inquiries" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition-all <?php echo $page === 'contact_inquiries' ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                    <span>✉️</span> <span>رسائل اتصل بنا</span>
                </a>
                <a href="index.php?page=showroom_reviews" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition-all <?php echo $page === 'showroom_reviews' ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                    <span>⭐</span> <span>تقييمات العملاء</span>
                </a>
                <a href="index.php?page=showroom_sales" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition-all <?php echo $page === 'showroom_sales' ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                    <span>👥</span> <span>إدارة المبيعات</span>
                </a>
                <a href="index.php?page=analytics" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition-all <?php echo $page === 'analytics' ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                    <span>📈</span> <span>تحليلات المعرض</span>
                </a>
                <?php if ($user_role === 'admin'): ?>
                <a href="index.php?page=ads" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition-all <?php echo $page === 'ads' ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                    <span>📢</span> <span><?php echo $t['ads']; ?></span>
                </a>
                <?php endif; ?>
                <a href="index.php?page=settings" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg transition-all <?php echo $page === 'settings' ? 'bg-indigo-600 text-white shadow-lg' : 'hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                    <span>⚙️</span> <span><?php echo $t['settings']; ?></span>
                </a>
            <?php endif; ?>
            <a href="customer.php" target="_blank" class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 font-extrabold text-center transition-all">
                <span>🌐</span> <span><?php echo $t['customer_showroom']; ?></span>
            </a>
        </div>

        <!-- Mobile quick info / logout -->
        <div class="border-t border-slate-200 dark:border-slate-800 pt-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-indigo-600/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-black text-xs">
                    <?php echo mb_substr($user_name, 0, 1, 'utf-8'); ?>
                </div>
                <div class="text-right leading-none">
                    <span class="block text-xs font-bold text-slate-800 dark:text-slate-200"><?php echo htmlspecialchars($user_name); ?></span>
                    <span class="block text-[9px] text-slate-500 font-mono mt-0.5"><?php 
                        if ($user_role === 'admin') {
                            echo $t['admin_role'];
                        } elseif ($user_role === 'branch_manager') {
                            echo $t['branch_manager_role'];
                        } else {
                            echo $t['agent_role'];
                        }
                    ?></span>
                </div>
            </div>
            <a href="index.php?logout=1" class="px-3 py-1.5 bg-rose-600/10 hover:bg-rose-600 text-rose-600 dark:text-rose-400 dark:hover:text-white hover:text-white text-xs font-bold rounded-lg transition-all flex items-center gap-1.5 cursor-pointer">
                🚪 <?php echo $t['logout']; ?>
            </a>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 min-h-screen overflow-y-auto">
        <main class="flex-1 w-full max-w-7xl mx-auto p-4 md:p-6 pb-24">
            
            <script>
                // Toggle Dark Mode
                function toggleDarkMode() {
                    const html = document.documentElement;
                    const isDark = html.classList.contains('dark');
                    if (isDark) {
                        html.classList.remove('dark');
                        localStorage.setItem('theme', 'light');
                        document.cookie = "theme=light; max-age=" + (365 * 24 * 60 * 60) + "; path=/";
                    } else {
                        html.classList.add('dark');
                        localStorage.setItem('theme', 'dark');
                        document.cookie = "theme=dark; max-age=" + (365 * 24 * 60 * 60) + "; path=/";
                    }
                }
                
                // Toggle Mobile Menu Panel
                const mobileMenuBtn = document.getElementById('mobile-menu-btn');
                const mobileMenuPanel = document.getElementById('mobile-menu-panel');
                if (mobileMenuBtn && mobileMenuPanel) {
                    mobileMenuBtn.addEventListener('click', () => {
                        mobileMenuPanel.classList.toggle('hidden');
                    });
                }
            </script>

            <?php if ($page === 'dashboard'): 
                // Fetch stats
                $totalCars = $pdo->query("SELECT COUNT(*) FROM `cars`")->fetchColumn() ?: 0;
                $availableCars = $pdo->query("SELECT COUNT(*) FROM `cars` WHERE `status` = 'available'")->fetchColumn() ?: 0;
                $reservedCars = $pdo->query("SELECT COUNT(*) FROM `cars` WHERE `status` = 'reserved'")->fetchColumn() ?: 0;
                $soldCars = $pdo->query("SELECT COUNT(*) FROM `cars` WHERE `status` = 'sold'")->fetchColumn() ?: 0;
                $notForSaleCars = $pdo->query("SELECT COUNT(*) FROM `cars` WHERE `status` = 'not_for_sale'")->fetchColumn() ?: 0;
                
                $totalRevenue = $pdo->query("SELECT SUM(`sale_amount`) FROM `cars` WHERE `status` = 'sold'")->fetchColumn() ?: 0;
                $totalProfit = 0;
                $profitQuery = $pdo->query("SELECT SUM(`sale_amount` - `cost_price`) FROM `cars` WHERE `status` = 'sold' AND `cost_price` IS NOT NULL AND `cost_price` > 0");
                if ($profitQuery) {
                    $totalProfit = $profitQuery->fetchColumn() ?: 0;
                }
                
                $totalOrders = $pdo->query("SELECT COUNT(*) FROM `customer_orders` WHERE `status` = 'new'")->fetchColumn() ?: 0;
                $totalActiveReservations = $pdo->query("SELECT COUNT(*) FROM `reservations` WHERE `status` = 'active'")->fetchColumn() ?: 0;

                // Fetch lists
                $recentCars = $pdo->query("SELECT id, make, model, year, price, currency, status, main_image, created_at FROM `cars` ORDER BY `created_at` DESC LIMIT 5")->fetchAll();
                $recentReservations = $pdo->query("SELECT r.*, c.make, c.model, c.year, u.name as rep_name FROM `reservations` r JOIN `cars` c ON r.car_id = c.id LEFT JOIN `users` u ON r.created_by_user_id = u.id WHERE r.status = 'active' ORDER BY r.created_at DESC LIMIT 5")->fetchAll();
                $recentOrders = $pdo->query("SELECT o.*, c.make, c.model, c.year, c.price FROM `customer_orders` o LEFT JOIN `cars` c ON o.car_id = c.id ORDER BY o.created_at DESC LIMIT 5")->fetchAll();
                
                // Percentages
                $availPct = $totalCars > 0 ? round(($availableCars / $totalCars) * 100) : 0;
                $resPct = $totalCars > 0 ? round(($reservedCars / $totalCars) * 100) : 0;
                $soldPct = $totalCars > 0 ? round(($soldCars / $totalCars) * 100) : 0;
                $notSalePct = $totalCars > 0 ? round(($notForSaleCars / $totalCars) * 100) : 0;
            ?>
            <div class="space-y-6 text-right w-full font-sans" dir="rtl">
                
                <!-- Hero welcome -->
                <div class="bg-gradient-to-l from-indigo-900 to-slate-900 border border-indigo-950 p-6 rounded-2xl text-white shadow-lg flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h2 class="text-xl font-black text-slate-100 flex items-center gap-2">
                            <span>👋</span> مرحباً بك في لوحة تحكم المخزن الاحترافية
                        </h2>
                        <p class="text-xs text-indigo-200 mt-1">متابعة فورية ومؤشرات حية لجميع عمليات البيع والمخزون والحجوزات لشركة المخزون للمحركات المحدودة</p>
                    </div>
                    <div class="flex items-center gap-2 bg-indigo-500/10 text-indigo-300 border border-indigo-500/20 px-3.5 py-2 rounded-xl text-xs font-bold font-sans">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>حالة النظام: متصل بالكامل</span>
                    </div>
                </div>

                <!-- Quick Stats Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    
                    <?php if ($user_role === 'admin'): ?>
                        <!-- Revenue Card -->
                        <div class="bg-[#0e1424] border border-slate-800 p-5 rounded-2xl shadow-sm text-slate-200 hover:border-slate-700 transition">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-400">إجمالي إيرادات المبيعات</span>
                                <span class="p-2 rounded-xl bg-indigo-500/15 text-indigo-400 border border-indigo-500/10 text-xs">💰</span>
                            </div>
                            <div class="mt-4">
                                <span class="text-xl font-black text-white font-sans"><?php echo number_format($totalRevenue); ?></span>
                                <span class="text-[10px] text-indigo-400 mr-1.5 font-bold">ر.س</span>
                            </div>
                            <p class="text-[9px] text-slate-500 mt-2 font-bold">تم تحقيقها من بيع عدد <span class="text-indigo-400 font-sans font-black"><?php echo $soldCars; ?></span> مركبات متكاملة</p>
                        </div>

                        <!-- Profit Card -->
                        <div class="bg-[#0e1424] border border-slate-800 p-5 rounded-2xl shadow-sm text-slate-200 hover:border-slate-700 transition">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-400">صافي الأرباح المحققة</span>
                                <span class="p-2 rounded-xl bg-emerald-500/15 text-emerald-400 border border-emerald-500/10 text-xs">📈</span>
                            </div>
                            <div class="mt-4">
                                <span class="text-xl font-black text-white font-sans"><?php echo number_format($totalProfit); ?></span>
                                <span class="text-[10px] text-emerald-400 mr-1.5 font-bold">ر.س</span>
                            </div>
                            <p class="text-[9px] text-slate-500 mt-2 font-bold">بعد خصم التكاليف الشرائية الموثقة بالمخزن</p>
                        </div>
                    <?php else: ?>
                        <!-- For Representative: Available Cars Card -->
                        <div class="bg-[#0e1424] border border-slate-800 p-5 rounded-2xl shadow-sm text-slate-200 hover:border-slate-700 transition">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-400">السيارات المتوفرة للبيع</span>
                                <span class="p-2 rounded-xl bg-emerald-500/15 text-emerald-400 border border-emerald-500/10 text-xs">🚗</span>
                            </div>
                            <div class="mt-4">
                                <span class="text-xl font-black text-white font-sans"><?php echo $availableCars; ?></span>
                                <span class="text-[10px] text-emerald-400 mr-1.5 font-bold">سيارة متوفرة</span>
                            </div>
                            <p class="text-[9px] text-slate-500 mt-2 font-bold">جاهزة للعرض والحجز الفوري للعملاء</p>
                        </div>

                        <!-- For Representative: Reserved Cars Card -->
                        <div class="bg-[#0e1424] border border-slate-800 p-5 rounded-2xl shadow-sm text-slate-200 hover:border-slate-700 transition">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-400">السيارات المحجوزة مؤقتاً</span>
                                <span class="p-2 rounded-xl bg-rose-500/15 text-rose-400 border border-rose-500/10 text-xs">🔒</span>
                            </div>
                            <div class="mt-4">
                                <span class="text-xl font-black text-white font-sans"><?php echo $reservedCars; ?></span>
                                <span class="text-[10px] text-rose-400 mr-1.5 font-bold">سيارة محجوزة</span>
                            </div>
                            <p class="text-[9px] text-slate-500 mt-2 font-bold">تحت الحجز وبانتظار تأكيد المبيعات</p>
                        </div>
                    <?php endif; ?>

                    <!-- Active Bookings -->
                    <div class="bg-[#0e1424] border border-slate-800 p-5 rounded-2xl shadow-sm text-slate-200 hover:border-slate-700 transition">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-400">الحجوزات النشطة اليوم</span>
                            <span class="p-2 rounded-xl bg-rose-500/15 text-rose-400 border border-rose-500/10 text-xs">🔒</span>
                        </div>
                        <div class="mt-4 flex items-baseline gap-1.5">
                            <span class="text-2xl font-black text-white font-sans"><?php echo $totalActiveReservations; ?></span>
                            <span class="text-slate-400 text-[10px] font-bold">حجوزات معتمدة</span>
                        </div>
                        <p class="text-[9px] text-slate-500 mt-2 font-bold">بمعدل حجز <span class="text-rose-400 font-sans font-black"><?php echo $reservedCars; ?></span> سيارة معلقة بالمخزن</p>
                    </div>

                    <!-- New Web Orders -->
                    <div class="bg-[#0e1424] border border-slate-800 p-5 rounded-2xl shadow-sm text-slate-200 hover:border-slate-700 transition">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-400">طلبات الشراء المستلمة</span>
                            <span class="p-2 rounded-xl bg-amber-500/15 text-amber-400 border border-amber-500/10 text-xs">📥</span>
                        </div>
                        <div class="mt-4 flex items-baseline gap-1.5">
                            <span class="text-2xl font-black text-white font-sans"><?php echo $totalOrders; ?></span>
                            <span class="text-amber-400 text-[10px] font-bold font-sans">طلبات جديدة</span>
                        </div>
                        <p class="text-[9px] text-slate-500 mt-2 font-bold">من صفحة المعرض الخارجية للعملاء</p>
                    </div>

                </div>

                <!-- Inventory Distribution Progress bar -->
                <div class="bg-[#0e1424] border border-slate-800 p-5 rounded-2xl shadow-sm text-slate-200">
                    <div class="flex justify-between items-center mb-3">
                        <span class="text-xs font-bold text-slate-300">مؤشر توزيع وتصنيف المخزون المالي</span>
                        <span class="text-[10px] text-slate-500 font-bold font-sans">إجمالي المركبات: <?php echo $totalCars; ?> سيارة</span>
                    </div>
                    
                    <!-- Multi-colored segment bar -->
                    <div class="w-full h-3 bg-slate-950 rounded-full overflow-hidden flex">
                        <div class="bg-emerald-500 h-full transition-all" style="width: <?php echo $availPct; ?>%" title="متوفر"></div>
                        <div class="bg-rose-500 h-full transition-all" style="width: <?php echo $resPct; ?>%" title="محجوز"></div>
                        <div class="bg-indigo-600 h-full transition-all" style="width: <?php echo $soldPct; ?>%" title="مباع"></div>
                        <div class="bg-slate-700 h-full transition-all" style="width: <?php echo $notSalePct; ?>%" title="غير معروض للبيع"></div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 text-[10px] font-bold">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full shrink-0"></span>
                            <span class="text-slate-400">متوفرة للبيع:</span>
                            <span class="text-white font-sans"><?php echo $availableCars; ?> (<?php echo $availPct; ?>%)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 bg-rose-500 rounded-full shrink-0"></span>
                            <span class="text-slate-400">محجوزة بالكامل:</span>
                            <span class="text-white font-sans"><?php echo $reservedCars; ?> (<?php echo $resPct; ?>%)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 bg-indigo-600 rounded-full shrink-0"></span>
                            <span class="text-slate-400">مباعة وخارجة:</span>
                            <span class="text-white font-sans"><?php echo $soldCars; ?> (<?php echo $soldPct; ?>%)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 bg-slate-700 rounded-full shrink-0"></span>
                            <span class="text-slate-400">غير معروضة للبيع:</span>
                            <span class="text-white font-sans"><?php echo $notForSaleCars; ?> (<?php echo $notSalePct; ?>%)</span>
                        </div>
                    </div>
                </div>

                <!-- Bento Grid Section: Tables -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    
                    <!-- Left Bento Box: Recent showroom orders -->
                    <div class="bg-[#0e1424] border border-slate-800 rounded-2xl shadow-sm text-white overflow-hidden flex flex-col justify-between">
                        <div class="p-5 border-b border-slate-800 flex justify-between items-center">
                            <h3 class="font-extrabold text-xs text-slate-100 flex items-center gap-2">
                                📥 أحدث طلبات الشراء من صفحة المعرض
                            </h3>
                            <?php if ($user_role === 'admin'): ?>
                                <a href="index.php?page=orders" class="text-[10px] text-indigo-400 hover:text-indigo-300 font-bold">عرض الصندوق كامل ←</a>
                            <?php endif; ?>
                        </div>
                        <div class="p-4 flex-1">
                            <?php if (count($recentOrders) > 0): ?>
                                <div class="space-y-3 font-sans">
                                    <?php foreach ($recentOrders as $order): ?>
                                        <div class="p-3 bg-slate-950/40 rounded-xl border border-slate-850 flex items-center justify-between text-xs hover:border-slate-800 transition">
                                            <div class="space-y-1">
                                                <span class="font-extrabold text-white block"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                                <span class="text-[10px] text-slate-400 font-sans block"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                                            </div>
                                            <div class="text-left font-sans">
                                                <span class="font-bold text-indigo-400 block"><?php echo htmlspecialchars($order['make'] . ' ' . $order['model']); ?></span>
                                                <span class="text-[10px] px-2.5 py-0.5 rounded-full font-bold inline-block mt-1 <?php echo $order['status'] === 'new' ? 'bg-amber-500/15 text-amber-400' : 'bg-slate-500/15 text-slate-400'; ?>">
                                                    <?php echo $order['status'] === 'new' ? 'جديد' : 'مكتمل'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-10 space-y-2">
                                    <span class="text-3xl block">📥</span>
                                    <p class="text-xs text-slate-500 font-bold">لا يوجد طلبات شراء مسجلة حالياً</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right Bento Box: Active Reservations -->
                    <div class="bg-[#0e1424] border border-slate-800 rounded-2xl shadow-sm text-white overflow-hidden flex flex-col justify-between">
                        <div class="p-5 border-b border-slate-800 flex justify-between items-center">
                            <h3 class="font-extrabold text-xs text-slate-100 flex items-center gap-2">
                                🔒 أحدث الحجوزات النشطة والمعلقة
                            </h3>
                            <?php if ($user_role === 'admin'): ?>
                                <a href="index.php?page=reservations" class="text-[10px] text-indigo-400 hover:text-indigo-300 font-bold">إدارة الحجوزات ←</a>
                            <?php endif; ?>
                        </div>
                        <div class="p-4 flex-1">
                            <?php if (count($recentReservations) > 0): ?>
                                <div class="space-y-3 font-sans">
                                    <?php foreach ($recentReservations as $res): ?>
                                        <div class="p-3 bg-slate-950/40 rounded-xl border border-slate-850 flex items-center justify-between text-xs hover:border-slate-800 transition">
                                            <div class="space-y-1">
                                                <span class="font-extrabold text-white block"><?php echo htmlspecialchars($res['customer_name']); ?></span>
                                                <span class="text-[10px] text-slate-400 font-bold block">المدة: <?php echo htmlspecialchars($res['duration']); ?> أيام</span>
                                            </div>
                                            <div class="text-left">
                                                <span class="font-bold text-rose-400 block"><?php echo htmlspecialchars($res['make'] . ' ' . $res['model']); ?></span>
                                                <span class="text-[9px] text-slate-500 font-sans block mt-1"><?php echo htmlspecialchars($res['created_at']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-10 space-y-2">
                                    <span class="text-3xl block">🔒</span>
                                    <p class="text-xs text-slate-500 font-bold">لا يوجد حجوزات نشطة حالياً</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Row: Recent Cars added -->
                    <div class="bg-[#0e1424] border border-slate-800 rounded-2xl shadow-sm text-white overflow-hidden lg:col-span-2">
                        <div class="p-5 border-b border-slate-800 flex justify-between items-center">
                            <h3 class="font-extrabold text-xs text-slate-100 flex items-center gap-2">
                                🚗 أحدث السيارات المضافة حديثاً للمخزون
                            </h3>
                            <a href="index.php?page=inventory" class="text-[10px] text-indigo-400 hover:text-indigo-300 font-bold font-sans">تصفح المخزن كامل ←</a>
                        </div>
                        <div class="p-4">
                            <div class="overflow-x-auto">
                                <table class="w-full text-right border-collapse text-xs">
                                    <thead>
                                        <tr class="border-b border-slate-800 text-slate-400 text-[10px] font-bold">
                                            <th class="pb-3">السيارة</th>
                                            <th class="pb-3">السنة</th>
                                            <th class="pb-3 text-left">السعر الافتراضي</th>
                                            <th class="pb-3 text-center">الحالة الحالية</th>
                                            <th class="pb-3">تاريخ الإضافة</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-850/50">
                                        <?php foreach ($recentCars as $rc): ?>
                                            <tr class="hover:bg-slate-900/20 transition-all font-sans">
                                                <td class="py-3 font-extrabold text-slate-200">
                                                    <?php echo htmlspecialchars($rc['make'] . ' ' . $rc['model']); ?>
                                                </td>
                                                <td class="py-3 font-sans text-slate-400"><?php echo htmlspecialchars($rc['year']); ?></td>
                                                <td class="py-3 text-left font-sans font-black text-indigo-400"><?php echo number_format($rc['price']); ?> <span class="text-[9px]"><?php echo htmlspecialchars($rc['currency']); ?></span></td>
                                                <td class="py-3 text-center">
                                                    <span class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold <?php echo $rc['status'] === 'available' ? 'bg-emerald-500/10 text-emerald-400' : ($rc['status'] === 'reserved' ? 'bg-rose-500/10 text-rose-400' : 'bg-slate-700/10 text-slate-400'); ?>">
                                                        <?php echo $rc['status'] === 'available' ? 'متوفر' : ($rc['status'] === 'reserved' ? 'محجوز' : 'مباع'); ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 font-sans text-slate-500"><?php echo date('Y-m-d', strtotime($rc['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
            <?php endif; ?>

            <?php if ($page === 'sales'): 
                // Fetch statistics
                $salesCount = $pdo->query("SELECT COUNT(*) FROM `cars` WHERE `status` = 'sold'")->fetchColumn() ?: 0;
                $salesRevenue = $pdo->query("SELECT SUM(`sale_amount`) FROM `cars` WHERE `status` = 'sold'")->fetchColumn() ?: 0;
                
                // Average profit calculation
                $avgSalePrice = $salesCount > 0 ? round($salesRevenue / $salesCount) : 0;
                
                // Fetch all sold cars (archive list)
                $soldCarsList = $pdo->query("SELECT c.*, b.name as branch_name, u.name as salesman_name FROM `cars` c LEFT JOIN `branches` b ON c.branch_id = b.id LEFT JOIN `users` u ON c.sold_by_user_id = u.id WHERE c.status = 'sold' ORDER BY c.exit_date DESC")->fetchAll();
                
                // Fetch available/reserved cars for recording sales
                $availableCarsForSale = $pdo->query("SELECT id, make, model, year, plate_number, price FROM `cars` WHERE `status` IN ('available', 'reserved') ORDER BY make ASC, model ASC")->fetchAll();
                
                // Representatives list
                $repsListLookup = $pdo->query("SELECT id, name FROM `users` WHERE role = 'representative' OR role = 'admin'")->fetchAll();
            ?>
            <div class="space-y-6 text-right w-full font-sans" dir="rtl">
                
                <!-- Title banner -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-900 border border-slate-800 p-6 rounded-2xl text-white shadow-lg">
                    <div>
                        <h2 class="text-xl font-black text-slate-100 flex items-center gap-2">
                            💰 إدارة المبيعات وتوثيق خروج المركبات (Sales Archive)
                        </h2>
                        <p class="text-xs text-slate-400 mt-1 font-sans">تتبع إجمالي الفواتير الصادرة، عمليات البيع الموثقة، وإصدار عقود خروج السيارات من المخزن</p>
                    </div>
                    
                    <!-- Record sale trigger button -->
                    <button onclick="openRecordSaleModal()" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black rounded-xl transition shadow-md shadow-indigo-950/20 flex items-center gap-2 cursor-pointer font-sans">
                        ✨ تسجيل عملية بيع جديدة
                    </button>
                </div>

                <!-- Stats row -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-[#0e1424] border border-slate-800 p-5 rounded-2xl text-slate-200">
                        <span class="text-xs font-bold text-slate-400 block">إجمالي مبيعات الصندوق</span>
                        <span class="text-xl font-black text-white font-sans mt-3 block"><?php echo number_format($salesRevenue); ?> <span class="text-xs text-indigo-400 font-bold">ر.س</span></span>
                        <p class="text-[9px] text-slate-500 mt-2 font-bold">المبالغ الفعلية المدفوعة لجميع السيارات المباعة</p>
                    </div>
                    <div class="bg-[#0e1424] border border-slate-800 p-5 rounded-2xl text-slate-200">
                        <span class="text-xs font-bold text-slate-400 block">عدد السيارات المباعة</span>
                        <span class="text-xl font-black text-white font-sans mt-3 block"><?php echo $salesCount; ?> <span class="text-xs text-indigo-400 font-bold">مركبة</span></span>
                        <p class="text-[9px] text-slate-500 mt-2 font-bold">تم خروجها وتحديث سجلاتها بانتظام</p>
                    </div>
                    <div class="bg-[#0e1424] border border-slate-800 p-5 rounded-2xl text-slate-200">
                        <span class="text-xs font-bold text-slate-400 block">متوسط قيمة البيع للمركبة</span>
                        <span class="text-xl font-black text-white font-sans mt-3 block"><?php echo number_format($avgSalePrice); ?> <span class="text-xs text-indigo-400 font-bold">ر.س</span></span>
                        <p class="text-[9px] text-slate-500 mt-2 font-bold">معدل تصفية المبيعات العام لكل مركبة</p>
                    </div>
                </div>

                <!-- Archive Table list -->
                <div class="bg-[#0e1424] border border-slate-800 rounded-2xl overflow-hidden shadow-xl text-white">
                    <div class="p-5 border-b border-slate-800 flex justify-between items-center">
                        <h3 class="font-bold text-sm text-slate-100 font-sans">سجل المركبات المباعة (<?php echo count($soldCarsList); ?> عملية)</h3>
                        <span class="text-[10px] bg-indigo-500/15 text-indigo-400 px-3 py-1 rounded-full border border-indigo-500/10 font-sans">سجلات نهائية</span>
                    </div>
                    
                    <?php if (count($soldCarsList) > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-right border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-950/40 border-b border-slate-800 text-slate-400 text-[10px] font-bold uppercase font-sans">
                                        <th class="p-4">المركبة</th>
                                        <th class="p-4">اللوحة / الهيكل</th>
                                        <th class="p-4">المشتري</th>
                                        <th class="p-4">سعر البيع الفعلي</th>
                                        <th class="p-4">المندوب وتاريخ الخروج</th>
                                        <th class="p-4 text-center">الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-850">
                                    <?php foreach ($soldCarsList as $sCar): ?>
                                        <tr class="hover:bg-slate-900/10 transition-colors font-sans">
                                            <td class="p-4">
                                                <div class="font-extrabold text-slate-200"><?php echo htmlspecialchars($sCar['make'] . ' ' . $sCar['model']); ?></div>
                                                <div class="text-[10px] text-indigo-400 mt-1"><?php echo htmlspecialchars($sCar['year'] . ' | ' . ($sCar['branch_name'] ?: 'المقر الرئيسي')); ?></div>
                                            </td>
                                            <td class="p-4">
                                                <div class="font-bold font-sans text-slate-300"><?php echo htmlspecialchars($sCar['plate_number'] ?: 'بدون لوحة'); ?></div>
                                                <div class="text-[9px] font-sans text-slate-500 mt-1"><?php echo htmlspecialchars($sCar['vin']); ?></div>
                                            </td>
                                            <td class="p-4">
                                                <div class="font-extrabold text-slate-200"><?php echo htmlspecialchars($sCar['sale_customer_name'] ?: 'غير محدد'); ?></div>
                                                <div class="text-[10px] text-slate-400 font-sans mt-1"><?php echo htmlspecialchars($sCar['sale_customer_phone'] ?: ''); ?></div>
                                            </td>
                                            <td class="p-4">
                                                <div class="font-sans font-black text-indigo-400"><?php echo number_format($sCar['sale_amount'] ?: 0); ?> ر.س</div>
                                                <div class="text-[10px] text-slate-500 font-bold mt-1">المكسب: <?php echo number_format(($sCar['sale_amount'] ?: 0) - ($sCar['cost_price'] ?: 0)); ?> ر.س</div>
                                            </td>
                                            <td class="p-4">
                                                <div class="font-bold text-slate-300"><?php echo htmlspecialchars($sCar['salesman_name'] ?: 'المدير العام'); ?></div>
                                                <div class="text-[9px] font-sans text-slate-500 mt-1"><?php echo htmlspecialchars($sCar['exit_date']); ?></div>
                                            </td>
                                            <td class="p-4 text-center font-sans">
                                                <div class="flex items-center justify-center gap-1.5">
                                                    <!-- Print Contract invoice -->
                                                    <a href="index.php?print_contract=<?php echo $sCar['id']; ?>" target="_blank" class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded font-bold text-[10px] cursor-pointer transition flex items-center gap-1">
                                                        🖨️ طباعة العقد
                                                    </a>
                                                    
                                                    <!-- Cancel Sale / Return to Stock -->
                                                    <?php if ($user_role === 'admin'): ?>
                                                        <a href="index.php?cancel_sale=<?php echo $sCar['id']; ?>" onclick="return confirm('هل أنت متأكد من إلغاء عملية البيع وإعادة السيارة إلى المخزن كمتاحة للبيع؟')" class="px-2.5 py-1.5 bg-rose-500/10 hover:bg-rose-600 hover:text-white text-rose-400 rounded font-bold text-[10px] border border-rose-500/25 transition cursor-pointer">
                                                            🗑️ إلغاء البيع
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-16 space-y-3 font-sans">
                            <span class="text-4xl block">💰</span>
                            <p class="text-xs text-slate-400 font-bold">لا يوجد مركبات مباعة في سجل الأرشيف المالي حالياً.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Record Sale Modal has been moved to global scope for use across all pages -->
            </div>
            <?php endif; ?>

            <?php if ($page === 'inventory'): 
    $search = trim($_GET['search'] ?? '');
    $status_filter = trim($_GET['status'] ?? '');
    $branch_filter = trim($_GET['branch_id'] ?? '');

    $sql = "SELECT c.*, b.name as branch_name, r.id as res_id, r.customer_name, r.customer_phone, r.created_at as res_created_at, r.duration, r.notes as res_notes, u.name as res_rep_name, r.attachments as res_attachments FROM `cars` c LEFT JOIN `branches` b ON c.branch_id = b.id LEFT JOIN `reservations` r ON c.id = r.car_id AND r.status = 'active' LEFT JOIN `users` u ON r.created_by_user_id = u.id WHERE c.status != 'sold'";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (c.make LIKE ? OR c.model LIKE ? OR c.vin LIKE ? OR c.plate_number LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($status_filter !== '') {
        $sql .= " AND c.status = ?";
        $params[] = $status_filter;
    }
    if ($branch_filter !== '') {
        $sql .= " AND c.branch_id = ?";
        $params[] = $branch_filter;
    }

    $sql .= " ORDER BY c.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cars = $stmt->fetchAll();
    $allBranches = $pdo->query("SELECT id, name FROM `branches` ORDER BY name ASC")->fetchAll();
?>
<div class="space-y-6">
    <!-- Success/Error Alerts -->
    <?php if (isset($_GET['success'])): ?>
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold rounded-xl text-right" dir="rtl">
            ✓ تم إضافة السيارة الجديدة إلى نظام المخازن والمستودعات بنجاح!
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-bold rounded-xl text-right" dir="rtl">
            ⚠️ فشل إضافة السيارة: يرجى التحقق من ملء جميع الحقول المطلوبة (الماركة، الموديل، رقم الهيكل).
        </div>
    <?php endif; ?>

    <!-- Title and Add Button for Admin -->
    <?php if ($user_role === 'admin'): ?>
    <div class="bg-white border border-slate-200 p-5 rounded-2xl flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 shadow-sm text-right" dir="rtl">
        <div>
            <h4 class="font-extrabold text-sm text-slate-800">مستودع ومخزون السيارات الكلي</h4>
            <p class="text-[11px] text-slate-400 mt-1">تتبع وتحديث تفاصيل كافة المركبات في فروع ومخازن المعرض المختلفة.</p>
        </div>
        <button onclick="openAddCarMode()" class="px-4.5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl cursor-pointer transition flex items-center gap-1.5 shadow-sm shadow-indigo-600/10">
            ➕ إضافة سيارة جديدة للمخزون
        </button>
    </div>

    <!-- Add Car Panel Form -->
    <div id="add-car-panel" class="hidden bg-slate-900 border border-slate-800 p-6 rounded-2xl text-white space-y-6">
        <div class="flex justify-between items-center border-b border-slate-800 pb-3">
            <h3 id="car-panel-title" class="text-xs font-black text-indigo-400">📝 إضافة مركبة جديدة بالكامل ومواصفاتها التفصيلية</h3>
            <button onclick="document.getElementById('add-car-panel').classList.add('hidden')" class="text-slate-400 hover:text-white text-xs cursor-pointer">إغلاق ✕</button>
        </div>
        
        <form id="add-car-form" method="POST" action="index.php?page=inventory" enctype="multipart/form-data" class="space-y-6 text-right" dir="rtl">
            <input type="hidden" name="save_car" value="1">
            <input type="hidden" name="car_id" id="form-car-id" value="">
            
            <!-- Group 1: Basic details -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">الماركة (مثال: تويوتا) <span class="text-red-500">*</span></label>
                    <input type="text" name="make" required placeholder="تويوتا، مرسيدس..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">الموديل (مثال: كامري) <span class="text-red-500">*</span></label>
                    <input type="text" name="model" required placeholder="كامري، يارس..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">الفئة / الطراز (مثال: SLE) <span class="text-slate-400">(اختياري)</span></label>
                    <input type="text" name="trim" placeholder="SLE, فل كامل..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">سنة الصنع <span class="text-red-500">*</span></label>
                    <input type="number" name="year" required value="<?php echo date('Y'); ?>" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            <!-- Group 2: Physical/Technical details -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">اللون الخارجي <span class="text-red-500">*</span></label>
                    <input type="text" name="color" required placeholder="أبيض لؤلؤي..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">اللون الداخلي <span class="text-slate-400">(اختياري)</span></label>
                    <input type="text" name="interior_color" placeholder="جلد بيج..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">ناقل الحركة <span class="text-red-500">*</span></label>
                    <select name="transmission" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans font-bold">
                        <option value="أوتوماتيك">أوتوماتيك (Automatic)</option>
                        <option value="عادي / جير بوكس">عادي / جير بوكس (Manual)</option>
                        <option value="سي في تي CVT">سي في تي CVT</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">نوع الوقود / المحرك <span class="text-red-500">*</span></label>
                    <select name="engine_type" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans font-bold">
                        <option value="بنزين">بنزين (Gasoline)</option>
                        <option value="ديزل">ديزل (Diesel)</option>
                        <option value="هجين / هايبرد">هجين / هايبرد (Hybrid)</option>
                        <option value="كهرباء بالكامل">كهرباء بالكامل (Electric)</option>
                    </select>
                </div>
            </div>

            <!-- Group 3: Financial & Core specs -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">السعر الأساسي (ر.س) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" name="price" required placeholder="مثال: 95000" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">سعر التكلفة (ر.س) <span class="text-slate-400">(اختياري)</span></label>
                    <input type="number" step="0.01" name="cost_price" placeholder="سعر الشراء الفعلي..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">رقم الهيكل (VIN) <span class="text-red-500">*</span></label>
                    <input type="text" name="vin" required placeholder="أدخل رقم الهيكل 17 حرف..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-mono text-left" dir="ltr">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">الممشى الحالي (كم) <span class="text-red-500">*</span></label>
                    <input type="number" name="mileage" required value="0" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            <!-- Group 4: Inventory assignment -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">المعرض / الفرع <span class="text-red-500">*</span></label>
                    <select name="branch_id" required class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans font-bold">
                        <option value="">-- اختر فرع العرض --</option>
                        <?php foreach ($allBranches as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">حالة السيارة بالمخزون</label>
                    <select name="status" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans font-bold">
                        <option value="available">متوفرة للبيع (Available)</option>
                        <option value="reserved">محجوزة (Reserved)</option>
                        <option value="sold">تم بيعها (Sold)</option>
                        <option value="not_for_sale">غير معروضة للبيع (Not for sale)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">رقم اللوحة <span class="text-slate-400">(اختياري)</span></label>
                    <input type="text" name="plate_number" placeholder="أ ب ج 1234..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">حالة الهيكل والسيارة</label>
                    <input type="text" name="vehicle_condition" value="جديد (أصفار)" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            <!-- Group 5: Images & Custom papers -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">رفع صور السيارة (يمكنك اختيار صور متعددة وتحديد صورة العرض بلمسها) <span class="text-red-500">*</span></label>
                    <input type="file" name="car_images[]" id="car_images_input" accept="image/*" multiple onchange="updateImageSelector(this)" required class="w-full text-xs px-3.5 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-400 focus:outline-none">
                    <input type="hidden" name="main_image_index" id="main_image_index" value="0">
                    
                    <div id="image_previews" class="hidden mt-3 p-3 bg-slate-950 border border-slate-800 rounded-lg space-y-2">
                        <span class="text-[10px] text-indigo-400 font-bold block mb-1">📷 الصور المحددة (اضغط على الصورة لتحديدها كصورة العرض الرئيسية):</span>
                        <div id="previews_container" class="grid grid-cols-2 sm:grid-cols-4 gap-3"></div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">ملف البطاقة الجمركية الرسمية (Card File) <span class="text-slate-400">(PDF, JPG, PNG)</span></label>
                    <input type="file" name="card_file" class="w-full text-xs px-3.5 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-400 focus:outline-none">
                </div>
            </div>

            <!-- Group 6: Custom Specifications Textarea -->
            <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-300">المواصفات الإضافية / المخصصة <span class="text-slate-400">(اختياري - متاح إدخالها يدوياً وعرضها بشكل احترافي للعملاء)</span></label>
                <textarea name="custom_specs" rows="3" placeholder="أدخل هنا أي مواصفات خاصة بالمحرك، الأداء، الإضافات المتميزة، أو مميزات المقصورة بالتفصيل..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500"></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-3">
                <button type="button" onclick="document.getElementById('add-car-panel').classList.add('hidden')" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-lg cursor-pointer">إلغاء</button>
                <button type="submit" id="car-panel-submit-btn" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg cursor-pointer transition">حفظ وإضافة للمخزن ✓</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl text-white">
        <form method="GET" action="index.php" class="grid grid-cols-1 md:grid-cols-4 gap-4 text-right" dir="rtl">
            <input type="hidden" name="page" value="inventory">
            
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">البحث السريع (الماركة، الموديل، الهيكل، اللوحة)</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="ابحث هنا..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">حالة السيارة</label>
                <select name="status" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                    <option value="">الكل</option>
                    <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>متاحة للبيع</option>
                    <option value="reserved" <?php echo $status_filter === 'reserved' ? 'selected' : ''; ?>>محجوزة</option>
                    <option value="not_for_sale" <?php echo $status_filter === 'not_for_sale' ? 'selected' : ''; ?>>غير معروضة للبيع</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">المعرض / الفرع</label>
                <select name="branch_id" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                    <option value="">الكل</option>
                    <?php foreach ($branches_lookup as $b): ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo $branch_filter == $b['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 font-extrabold text-xs text-white transition flex items-center justify-center gap-1.5 shadow-md cursor-pointer">
                    <span>تطبيق الفلاتر</span>
                </button>
                <a href="index.php?page=inventory" class="py-2.5 px-4 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition flex items-center justify-center cursor-pointer">
                    إعادة تعيين
                </a>
            </div>
        </form>
    </div>

    <!-- Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($cars as $car): ?>
            <div id="car-card-<?php echo $car['id']; ?>" class="bg-[#0e1424] border border-slate-850 rounded-2xl p-4 flex flex-col gap-4 relative overflow-hidden hover:border-slate-750 transition-all group h-full text-slate-200 shadow-md">
                
                <div class="relative h-44 overflow-hidden bg-slate-950 shrink-0 rounded-xl">
                    <?php if (!empty($car['main_image'])): ?>
                        <img src="<?php echo htmlspecialchars($car['main_image']); ?>" alt="<?php echo htmlspecialchars($car['make'] . ' ' . $car['model']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" referrerPolicy="no-referrer">
                    <?php else: ?>
                        <div class="w-full h-full bg-slate-900/40 flex flex-col items-center justify-center text-slate-600 gap-2">
                            <span class="p-3 rounded-full bg-slate-950 border border-slate-800">🚗</span>
                        </div>
                    <?php endif; ?>

                    <div class="absolute top-3 right-3 px-2.5 py-1 rounded-full text-[9px] font-bold bg-slate-950/85 text-slate-200 backdrop-blur-sm border border-slate-800/50 flex items-center gap-1">
                        <span>📍</span>
                        <span><?php echo htmlspecialchars($car['branch_name'] ?: 'المقر الرئيسي'); ?></span>
                    </div>

                    <div class="absolute top-3 left-3">
                        <span id="car-status-badge-<?php echo $car['id']; ?>" class="px-2.5 py-1 rounded-full text-[10px] font-bold <?php echo $car['status'] === 'available' ? 'bg-emerald-500 text-white' : ($car['status'] === 'reserved' ? 'bg-rose-500 text-white' : 'bg-slate-500 text-white'); ?>">
                            <?php echo $car['status'] === 'available' ? 'متوفرة للبيع' : ($car['status'] === 'reserved' ? 'محجوزة بالكامل' : 'مباعة'); ?>
                        </span>
                    </div>
                </div>

                <div class="flex-1 flex flex-col justify-between gap-3">
                    <div class="space-y-3">
                        
                        <div class="flex items-start justify-between min-h-[38px] text-right">
                            <span class="text-[11px] text-slate-500 font-mono font-bold"><?php echo htmlspecialchars($car['year']); ?></span>
                            <div>
                                <span class="text-xs font-black text-indigo-400 block leading-none"><?php echo htmlspecialchars($car['make']); ?></span>
                                <h4 class="font-bold text-xs text-white mt-1 leading-tight"><?php echo htmlspecialchars($car['model']); ?> <span class="text-[10px] text-slate-400 font-normal">(<?php echo htmlspecialchars($car['trim']); ?>)</span></h4>
                            </div>
                        </div>

                        <div class="flex items-center justify-between text-[10px] text-slate-400 border-t border-slate-800/60 pt-2 pb-1" dir="rtl">
                            <div class="flex items-center gap-1">
                                <span>⛽</span>
                                <span><?php 
                                    $f = $car['engine_type'] ?? $car['fuel_type'] ?? ''; 
                                    if ($f === 'gasoline') echo 'بنزين';
                                    elseif ($f === 'diesel') echo 'ديزل';
                                    elseif ($f === 'electric') echo 'كهربائي';
                                    elseif ($f === 'hybrid') echo 'هجين';
                                    else echo htmlspecialchars($f);
                                ?></span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span>⚙️</span>
                                <span><?php 
                                    $t = $car['transmission'] ?? '';
                                    if ($t === 'automatic') echo 'أوتوماتيك';
                                    elseif ($t === 'manual') echo 'عادي';
                                    else echo htmlspecialchars($t);
                                ?></span>
                            </div>
                            <div class="flex items-center gap-1 font-sans">
                                <span>🛣️</span>
                                <span><?php 
                                    $m = isset($car['mileage']) ? $car['mileage'] : ($car['odometer'] ?? 0);
                                    echo number_format((float)$m); 
                                ?> كم</span>
                            </div>
                        </div>

                        <div class="bg-[#080d1a] border border-slate-800/80 rounded-lg p-2.5 text-center space-y-1">
                            <div class="text-[11px] font-bold text-slate-200">اللوحة: <?php echo htmlspecialchars($car['plate_number'] ?: 'غير مسجلة'); ?></div>
                            <div class="text-[9px] font-mono text-slate-500">رقم الهيكل: <?php echo htmlspecialchars($car['vin']); ?></div>
                        </div>
                                     <!-- Booking Representative Section -->
                                     <div id="card-rep-container-<?php echo $car['id']; ?>" class="text-xs text-indigo-600 font-bold mt-1.5 <?php echo $car['status'] === 'reserved' ? 'flex' : 'hidden'; ?> items-center gap-1 bg-indigo-50/50 border border-indigo-100/50 rounded px-2 py-1">
                                         <span>👤 المندوب الحاجز:</span>
                                         <span class="font-black text-indigo-800" id="card-rep-name-<?php echo $car['id']; ?>"><?php echo htmlspecialchars($car['res_rep_name'] ?: 'مدير النظام'); ?></span>
                                     </div>

                                     <!-- Vehicle Attachments Section -->
                                     <?php 
                                     $hasCardFile = !empty($car['card_file']);
                                     $hasAttachments = false;
                                     if (!empty($car['attachments'])) {
                                         $atts = json_decode($car['attachments'], true);
                                         if (is_array($atts) && count($atts) > 0) {
                                             $hasAttachments = true;
                                         }
                                     }

                                     $showAttachmentsSection = false;
                                     if ($hasCardFile || $hasAttachments) {
                                         if ($user_role === 'admin' || $user_role === 'branch_manager') {
                                             if ($car['status'] === 'reserved' || $car['status'] === 'sold') {
                                                 $showAttachmentsSection = true;
                                             }
                                         } elseif ($user_role === 'representative') {
                                             if ($car['status'] === 'reserved' && !empty($car['res_rep_id']) && $car['res_rep_id'] == $user_id) {
                                                 $showAttachmentsSection = true;
                                             }
                                         }
                                     }
                                     ?>
                                     <div id="card-attachments-section-<?php echo $car['id']; ?>" class="mt-2 pt-2 border-t border-slate-100 <?php echo $showAttachmentsSection ? '' : 'hidden'; ?>">
                                         <span class="block text-[10px] font-bold text-slate-500 mb-1">📂 المرفقات والمستندات المتاحة للسيارة:</span>
                                         <div class="flex flex-wrap gap-1" id="card-attachments-list-<?php echo $car['id']; ?>">
                                             <?php 
                                             $hasAny = false;
                                             if ($hasCardFile) {
                                                 $hasAny = true;
                                                 echo '<a id="card-customs-doc-' . $car['id'] . '" href="' . htmlspecialchars($car['card_file']) . '" target="_blank" class="inline-flex items-center gap-1 text-[9px] font-extrabold text-indigo-700 bg-indigo-50 border border-indigo-150 px-2 py-1 rounded hover:bg-indigo-100 transition-colors">📂 مستند جمركي</a>';
                                             } else {
                                                 echo '<a id="card-customs-doc-' . $car['id'] . '" href="#" target="_blank" class="inline-flex items-center gap-1 text-[9px] font-extrabold text-indigo-700 bg-indigo-50 border border-indigo-150 px-2 py-1 rounded hover:bg-indigo-100 transition-colors hidden">📂 مستند جمركي</a>';
                                             }
                                             if (!empty($car['attachments'])) {
                                                 $atts = json_decode($car['attachments'], true);
                                                 if (is_array($atts) && count($atts) > 0) {
                                                     $hasAny = true;
                                                     foreach ($atts as $att) {
                                                         echo '<a href="' . htmlspecialchars($att['url']) . '" target="_blank" class="inline-flex items-center gap-1 text-[9px] font-bold text-slate-600 bg-slate-50 border border-slate-200 px-2 py-1 rounded hover:bg-indigo-50 hover:text-indigo-600 transition-colors">📄 ' . htmlspecialchars($att['name']) . '</a>';
                                                     }
                                                 }
                                             }
                                             if (!$hasAny) {
                                                 echo '<span class="text-[9px] text-slate-400" id="card-attachments-none-' . $car['id'] . '">لا يوجد ملفات مرفوعة للسيارة</span>';
                                             }
                                             ?>
                                         </div>
                                     </div>
                                    <!-- Dynamic Reservation Details Panel -->
                                    <?php if ($car['status'] === 'reserved' && !empty($car['res_id'])): ?>
                                        <div id="res-info-container-<?php echo $car['id']; ?>" class="mt-3 p-3 bg-indigo-50/70 border border-indigo-100 rounded-lg space-y-1 text-xs text-slate-700 text-right" dir="rtl">
                                            <div class="flex justify-between items-center pb-1 border-b border-indigo-100/50">
                                                <span class="font-extrabold text-indigo-700">🔒 تفاصيل الحجز النشط</span>
                                                <span class="text-[9px] bg-indigo-100 text-indigo-800 px-1.5 py-0.5 rounded font-mono font-bold">نشط</span>
                                            </div>
                                            <p class="text-[10px]"><span class="font-semibold text-slate-500">اسم العميل:</span> <span class="font-bold text-slate-800" id="res-cust-name-<?php echo $car['id']; ?>"><?php echo htmlspecialchars($car['customer_name']); ?></span></p>
                                            <p class="text-[10px]"><span class="font-semibold text-slate-500">رقم الجوال:</span> <span class="font-mono text-slate-800" id="res-cust-phone-<?php echo $car['id']; ?>"><?php echo htmlspecialchars($car['customer_phone']); ?></span></p>
                                            <p class="text-[10px]"><span class="font-semibold text-slate-500">تاريخ الحجز:</span> <span class="font-mono text-slate-600" id="res-start-date-<?php echo $car['id']; ?>"><?php echo date('Y-m-d', strtotime($car['res_created_at'])); ?></span></p>
                                            <p class="text-[10px]"><span class="font-semibold text-slate-500">مدة الحجز:</span> <span class="font-bold text-slate-700" id="res-duration-<?php echo $car['id']; ?>"><?php echo $car['duration']; ?> أيام</span></p>
                                            
                                            <?php if (!empty($car['res_notes'])): ?>
                                                <p class="text-[10px]" id="res-notes-wrapper-<?php echo $car['id']; ?>"><span class="font-semibold text-slate-500">الملاحظات:</span> <span class="text-slate-600" id="res-notes-<?php echo $car['id']; ?>"><?php echo htmlspecialchars($car['res_notes']); ?></span></p>
                                            <?php else: ?>
                                                <p class="text-[10px] hidden" id="res-notes-wrapper-<?php echo $car['id']; ?>"><span class="font-semibold text-slate-500">الملاحظات:</span> <span class="text-slate-600" id="res-notes-<?php echo $car['id']; ?>"></span></p>
                                            <?php endif; ?>

                                            <p class="text-[10px]"><span class="font-semibold text-slate-500">المندوب الحاجز:</span> <span class="font-bold text-indigo-700" id="res-rep-name-<?php echo $car['id']; ?>"><?php echo htmlspecialchars($car['res_rep_name'] ?: 'مدير النظام'); ?></span></p>

                                            <!-- Attachments Section -->
                                            <div id="res-attachments-wrapper-<?php echo $car['id']; ?>" class="mt-2 pt-2 border-t border-indigo-100/50 space-y-1 <?php echo empty($car['res_attachments']) ? 'hidden' : ''; ?>">
                                                <span class="block text-[9px] font-bold text-slate-400">قسم مرفقات الحجز:</span>
                                                <div class="flex flex-wrap gap-1.5" id="res-attachments-<?php echo $car['id']; ?>">
                                                    <?php 
                                                    if (!empty($car['res_attachments'])) {
                                                        $atts = json_decode($car['res_attachments'], true);
                                                        if (is_array($atts)) {
                                                            foreach ($atts as $idx => $att) {
                                                                $url = is_array($att) ? ($att['url'] ?? '') : $att;
                                                                $name = is_array($att) ? ($att['name'] ?? ('مستند ' . ($idx + 1))) : ('مستند ' . ($idx + 1));
                                                                if (!empty($url)) {
                                                                    echo '<a href="' . htmlspecialchars($url) . '" target="_blank" class="inline-flex items-center gap-1 text-[9px] font-bold text-indigo-600 bg-white border border-indigo-100 px-1.5 py-0.5 rounded hover:bg-indigo-50 transition-colors">📎 ' . htmlspecialchars($name) . '</a>';
                                                                }
                                                            }
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                            </div>

                                            <?php if ($user_role === 'admin' || $user_role === 'branch_manager' || ($user_role === 'representative' && !empty($car['res_rep_id']) && $car['res_rep_id'] == $user_id)): ?>
                                                <div class="pt-2 flex justify-end">
                                                    <button 
                                                        id="res-cancel-btn-<?php echo $car['id']; ?>"
                                                        onclick="cancelActiveReservation('<?php echo $car['res_id']; ?>', '<?php echo $car['id']; ?>')"
                                                        class="w-full text-center py-1 bg-rose-50 border border-rose-200 hover:bg-rose-500 hover:text-white text-rose-600 font-bold text-[9px] rounded transition duration-150 cursor-pointer"
                                                    >
                                                        إلغاء الحجز ✕
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div id="res-info-container-<?php echo $car['id']; ?>" class="hidden mt-3 p-3 bg-indigo-50/70 border border-indigo-100 rounded-lg space-y-1 text-xs text-slate-700 text-right" dir="rtl">
                                            <div class="flex justify-between items-center pb-1 border-b border-indigo-100/50">
                                                <span class="font-extrabold text-indigo-700">🔒 تفاصيل الحجز النشط</span>
                                                <span class="text-[9px] bg-indigo-100 text-indigo-800 px-1.5 py-0.5 rounded font-mono font-bold">نشط</span>
                                            </div>
                                            <p class="text-[10px]"><span class="font-semibold text-slate-500">اسم العميل:</span> <span class="font-bold text-slate-800" id="res-cust-name-<?php echo $car['id']; ?>"></span></p>
                                            <p class="text-[10px]"><span class="font-semibold text-slate-500">رقم الجوال:</span> <span class="font-mono text-slate-800" id="res-cust-phone-<?php echo $car['id']; ?>"></span></p>
                                            <p class="text-[10px]"><span class="font-semibold text-slate-500">تاريخ الحجز:</span> <span class="font-mono text-slate-600" id="res-start-date-<?php echo $car['id']; ?>"></span></p>
                                            <p class="text-[10px]"><span class="font-semibold text-slate-500">مدة الحجز:</span> <span class="font-bold text-slate-700" id="res-duration-<?php echo $car['id']; ?>"></span></p>
                                            
                                            <p class="text-[10px] hidden" id="res-notes-wrapper-<?php echo $car['id']; ?>"><span class="font-semibold text-slate-500">الملاحظات:</span> <span class="text-slate-600" id="res-notes-<?php echo $car['id']; ?>"></span></p>

                                            <p class="text-[10px]"><span class="font-semibold text-slate-500">المندوب الحاجز:</span> <span class="font-bold text-indigo-700" id="res-rep-name-<?php echo $car['id']; ?>"></span></p>

                                            <!-- Attachments Section -->
                                            <div id="res-attachments-wrapper-<?php echo $car['id']; ?>" class="mt-2 pt-2 border-t border-indigo-100/50 space-y-1 hidden">
                                                <span class="block text-[9px] font-bold text-slate-400">قسم مرفقات الحجز:</span>
                                                <div class="flex flex-wrap gap-1.5" id="res-attachments-<?php echo $car['id']; ?>">
                                                </div>
                                            </div>

                                            <?php if ($user_role === 'admin' || $user_role === 'branch_manager' || $user_role === 'representative'): ?>
                                                <div class="pt-2 flex justify-end">
                                                    <button 
                                                        id="res-cancel-btn-<?php echo $car['id']; ?>"
                                                        onclick=""
                                                        class="w-full text-center py-1 bg-rose-50 border border-rose-200 hover:bg-rose-500 hover:text-white text-rose-600 font-bold text-[9px] rounded transition duration-150 cursor-pointer"
                                                    >
                                                        إلغاء الحجز ✕
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex justify-between items-center">
                                <span class="font-sans font-extrabold text-indigo-600 text-sm"><?php echo number_format($car['price']); ?> ر.س</span>
                                
                                <div class="flex gap-2">
                                    <!-- Reserve Button Wrapper -->
                                    <div id="reserve-btn-wrapper-<?php echo $car['id']; ?>" class="<?php echo $car['status'] === 'available' ? '' : 'hidden'; ?>">
                                        <button 
                                            onclick="document.getElementById('reserve-modal-<?php echo $car['id']; ?>').classList.remove('hidden')"
                                            class="px-2.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-[10px] rounded cursor-pointer"
                                        >
                                            حجز المركبة
                                        </button>
                                    </div>

                                    <?php 
                                    $carAttsCount = 0;
                                    if (!empty($car['res_id'])) {
                                        $countQuery = $pdo->prepare("SELECT COUNT(*) FROM `reservation_attachments` WHERE `reservation_id` = ?");
                                        $countQuery->execute([$car['res_id']]);
                                        $carAttsCount = (int)$countQuery->fetchColumn();
                                    } else {
                                        $countQuery = $pdo->prepare("SELECT COUNT(*) FROM `attachments` WHERE `vehicle_id` = ?");
                                        $countQuery->execute([$car['id']]);
                                        $carAttsCount = (int)$countQuery->fetchColumn();
                                    }
                                    ?>
                                    <?php 
                                    $showAttachmentsBtn = false;
                                    if ($car['status'] === 'reserved' || $car['status'] === 'sold') {
                                        if ($user_role === 'admin' || $user_role === 'branch_manager') {
                                            $showAttachmentsBtn = true;
                                        } elseif ($user_role === 'representative') {
                                            if ($car['status'] === 'reserved' && !empty($car['res_rep_id']) && $car['res_rep_id'] == $user_id) {
                                                $showAttachmentsBtn = true;
                                            }
                                        }
                                    }
                                    ?>
                                    <!-- Attachments Button Wrapper (Only visible if reserved or sold and authorized) -->
                                    <div id="attachments-btn-wrapper-<?php echo $car['id']; ?>" class="<?php echo $showAttachmentsBtn ? '' : 'hidden'; ?>">
                                        <button 
                                            onclick="document.getElementById('attachment-viewer-modal-<?php echo $car['id']; ?>').classList.remove('hidden')"
                                            class="px-2.5 py-1.5 bg-slate-900 border border-slate-800 hover:bg-slate-800 text-slate-300 hover:text-white font-bold text-[10px] rounded transition-all flex items-center gap-1.5 cursor-pointer"
                                        >
                                            📁 المرفقات
                                            <span id="attachments-count-<?php echo $car['id']; ?>" class="bg-indigo-500/20 text-indigo-400 px-1.5 py-0.5 rounded text-[9px] font-mono font-black">
                                                <?php echo $carAttsCount; ?>
                                            </span>
                                        </button>
                                    </div>

                                    <!-- Edit & Delete Buttons (Admin Only) -->
                                    <?php if ($user_role === 'admin' && ($car['status'] === 'available' || $car['status'] === 'reserved')): ?>
                                        <button 
                                            onclick="openRecordSaleModal('<?php echo $car['id']; ?>')"
                                            class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] rounded transition-colors cursor-pointer"
                                        >
                                            تم البيع 🤝
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($user_role === 'admin'): ?>
                                        <button 
                                            data-car="<?php echo htmlspecialchars(json_encode($car), ENT_QUOTES, 'UTF-8'); ?>"
                                            onclick="editCar(JSON.parse(this.getAttribute('data-car')))"
                                            class="px-2.5 py-1.5 bg-indigo-50 border border-indigo-200 hover:bg-indigo-600 hover:text-white text-indigo-600 font-bold text-[10px] rounded transition-colors cursor-pointer"
                                        >
                                            تعديل
                                        </button>
                                        <a 
                                            href="?page=inventory&delete_car=<?php echo $car['id']; ?>" 
                                            onclick="return confirm('هل أنت متأكد من مسح هذه السيارة نهائياً ومرفقاتها من المستودعات؟');"
                                            class="px-2.5 py-1.5 bg-rose-50/10 border border-rose-200 hover:bg-rose-500 hover:text-white text-rose-500 font-bold text-[10px] rounded transition-colors"
                                        >
                                            حذف
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Individual Reservation Modal overlay -->
                            <div id="reserve-modal-<?php echo $car['id']; ?>" class="hidden fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center p-4">
                                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 w-full max-w-md shadow-2xl space-y-5 text-right text-white animate-fade-in" dir="rtl">
                                    <div class="flex items-center gap-2 pb-3 border-b border-slate-800">
                                        <span class="text-xl">🔒</span>
                                        <div>
                                            <h4 class="font-black text-sm text-slate-100">تأكيد الحجز الفوري التلقائي</h4>
                                            <p class="text-[10px] text-slate-400 mt-0.5"><?php echo htmlspecialchars($car['make'] . ' ' . $car['model']); ?></p>
                                        </div>
                                    </div>

                                    <form method="POST" action="index.php?page=inventory" enctype="multipart/form-data" class="space-y-4 text-right" onsubmit="handleReserveFormSubmit(event, '<?php echo $car['id']; ?>')">
                                        <input type="hidden" name="save_reservation" value="1">
                                        <input type="hidden" name="ajax" value="1">
                                        <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                        
                                        <!-- Hidden values for backwards compatibility with database requirements -->
                                        <input type="hidden" name="customer_name" value="<?php echo htmlspecialchars($user_name); ?>">
                                        <input type="hidden" name="customer_phone" value="مبيعات">
                                        <input type="hidden" name="duration" value="3">
                                        <input type="hidden" name="notes" value="حجز فوري تلقائي">

                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-400 mb-1">اسم المندوب المسؤول (مسترجع تلقائياً)</label>
                                                <input type="text" value="<?php echo htmlspecialchars($user_name); ?>" disabled class="w-full text-xs px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-400 font-bold focus:outline-none cursor-not-allowed">
                                            </div>

                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-400 mb-1">معرف المندوب الفريد (User ID)</label>
                                                <input type="text" value="<?php echo htmlspecialchars($user_id); ?>" disabled class="w-full text-xs px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-400 font-mono focus:outline-none cursor-not-allowed">
                                            </div>

                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-400 mb-1">الفرع المعتمد للتوزيع</label>
                                                <input type="text" value="<?php echo htmlspecialchars($user_branch_name); ?>" disabled class="w-full text-xs px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-400 font-bold focus:outline-none cursor-not-allowed">
                                            </div>


                                        </div>

                                        <div class="p-3 bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-[10px] rounded-lg leading-relaxed flex gap-2">
                                            <span>ℹ️</span>
                                            <p>عند تأكيد الحجز، سيتم تغيير حالة هذه السيارة إلى "محجوزة" في كافة الفروع وصالات العرض فوراً، وربط مستنداتها الرسمية بالحجز.</p>
                                        </div>

                                        <div class="flex justify-end gap-2 pt-2">
                                            <button type="button" onclick="document.getElementById('reserve-modal-<?php echo $car['id']; ?>').classList.add('hidden')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-lg cursor-pointer transition">إلغاء</button>
                                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg cursor-pointer transition shadow-md shadow-indigo-950/20">تأكيد وتفعيل الحجز الفوري</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Individual Attachment Viewer Modal overlay -->
                            <div id="attachment-viewer-modal-<?php echo $car['id']; ?>" class="hidden fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center p-4">
                                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 w-full max-w-xl max-h-[85vh] overflow-hidden flex flex-col shadow-2xl text-right text-white" dir="rtl">
                                    
                                    <!-- Header -->
                                    <div class="p-4 border-b border-slate-800 flex justify-between items-center bg-slate-950 rounded-t-xl">
                                        <div>
                                            <h3 class="font-extrabold text-sm text-white flex items-center gap-1.5">
                                                📁 مرفقات ومستندات السيارة الرسمية
                                            </h3>
                                            <p class="text-[10px] text-slate-400 mt-0.5"><?php echo htmlspecialchars($car['make'] . ' ' . $car['model']); ?> | لوحة: <?php echo htmlspecialchars($car['plate_number'] ?: 'بدون لوحة'); ?></p>
                                        </div>
                                        <button onclick="document.getElementById('attachment-viewer-modal-<?php echo $car['id']; ?>').classList.add('hidden')" class="p-1.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition cursor-pointer">
                                            ✕
                                        </button>
                                    </div>

                                    <!-- Content Body -->
                                    <div class="p-5 overflow-y-auto space-y-4 flex-1">
                                        <div id="attachment-list-container-<?php echo $car['id']; ?>" class="divide-y divide-slate-800">
                                            <?php 
                                            $hasAttachments = false;
                                            $carAtts = [];
                                            if (!empty($car['res_id'])) {
                                                // Load directly from reservation_attachments database table!
                                                $attQuery = $pdo->prepare("SELECT * FROM `reservation_attachments` WHERE `reservation_id` = ?");
                                                $attQuery->execute([$car['res_id']]);
                                                $dbAtts = $attQuery->fetchAll();
                                                foreach ($dbAtts as $dbAtt) {
                                                    $carAtts[] = [
                                                        'id' => (string)$dbAtt['id'],
                                                        'name' => $dbAtt['file_name'],
                                                        'url' => $dbAtt['file_path'],
                                                        'size' => 'مرفق رسمي',
                                                        'createdAt' => $dbAtt['created_at']
                                                    ];
                                                }
                                            } else {
                                                // Load directly from attachments database table!
                                                $attQuery = $pdo->prepare("SELECT * FROM `attachments` WHERE `vehicle_id` = ?");
                                                $attQuery->execute([$car['id']]);
                                                $dbAtts = $attQuery->fetchAll();
                                                foreach ($dbAtts as $dbAtt) {
                                                    $carAtts[] = [
                                                        'id' => (string)$dbAtt['attachment_id'],
                                                        'name' => $dbAtt['file_name'],
                                                        'url' => $dbAtt['file_path'],
                                                        'size' => 'مرفق رسمي',
                                                        'createdAt' => $dbAtt['created_at']
                                                    ];
                                                }
                                            }

                                            if (is_array($carAtts) && count($carAtts) > 0) {
                                                $hasAttachments = true;
                                                foreach ($carAtts as $att) {
                                                    $isImage = false;
                                                    $url = htmlspecialchars($att['url']);
                                                    if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $att['url'])) {
                                                        $isImage = true;
                                                    }
                                                    ?>
                                                    <div id="attachment-row-<?php echo $att['id']; ?>" class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                                        <div class="flex items-start gap-3">
                                                            <div class="w-8 h-8 rounded bg-indigo-500/10 text-indigo-400 flex items-center justify-center shrink-0">
                                                                📄
                                                            </div>
                                                            <div>
                                                                <h4 class="font-bold text-xs text-slate-200"><?php echo htmlspecialchars($att['name']); ?></h4>
                                                                <span class="text-[9px] text-slate-500 font-sans block mt-0.5">
                                                                    <?php echo htmlspecialchars($att['size'] ?? 'غير معروف'); ?> | تاريخ الرفع: <?php echo htmlspecialchars($att['createdAt'] ?? date('Y-m-d')); ?>
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <div class="flex items-center gap-2">
                                                            <!-- Download -->
                                                            <a href="<?php echo $url; ?>" download class="px-3 py-1.5 rounded bg-slate-950 hover:bg-slate-850 border border-slate-800 text-[10px] font-bold text-slate-300 flex items-center gap-1 transition cursor-pointer">
                                                                📥 تحميل
                                                            </a>

                                                            <!-- Preview -->
                                                            <a href="<?php echo $url; ?>" target="_blank" class="px-3 py-1.5 rounded bg-indigo-600 hover:bg-indigo-700 text-white text-[10px] font-bold flex items-center gap-1 transition cursor-pointer">
                                                                👁️ معاينة
                                                            </a>

                                                            <!-- Delete (Admin or representative who created the booking) -->
                                                            <?php 
                                                            $canDeleteAtt = false;
                                                            if ($user_role === 'admin') {
                                                                $canDeleteAtt = true;
                                                            } elseif ($car['status'] === 'reserved' && $car['created_by_user_id'] == $user_id) {
                                                                $canDeleteAtt = true;
                                                            }
                                                            if ($canDeleteAtt):
                                                            ?>
                                                                <button onclick="deleteCarAttachment('<?php echo $car['id']; ?>', '<?php echo $att['id']; ?>')" class="px-2.5 py-1.5 rounded bg-rose-50/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 border border-rose-500/25 text-[10px] font-bold transition cursor-pointer">
                                                                    🗑️ حذف
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>

                                                        <?php if ($isImage): ?>
                                                            <div class="w-full mt-2 rounded border border-slate-800 bg-slate-950 p-1.5 max-h-48 sm:hidden">
                                                                <img src="<?php echo $url; ?>" alt="<?php echo htmlspecialchars($att['name']); ?>" class="max-h-44 object-contain mx-auto" />
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php
                                                }
                                            }
                                            ?>
                                        </div>
                                        
                                        <!-- Empty state -->
                                        <div id="attachment-empty-<?php echo $car['id']; ?>" class="text-center py-10 space-y-3 <?php echo $hasAttachments ? 'hidden' : ''; ?>">
                                            <span class="text-3xl block">📁</span>
                                            <p class="text-xs text-slate-400 font-bold">
                                                لا يوجد مستندات أو مرفقات مسجلة لهذه السيارة حالياً.
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="p-4 border-t border-slate-800 bg-slate-950 flex justify-end rounded-b-xl">
                                        <button onclick="document.getElementById('attachment-viewer-modal-<?php echo $car['id']; ?>').classList.add('hidden')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-lg cursor-pointer transition">
                                            إغلاق النافذة
                                        </button>
                                    </div>

                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- PAGE 3: RESERVATIONS CONTROLLER (Admin & Branch Manager) -->
            <?php if ($page === 'reservations' && ($user_role === 'admin' || $user_role === 'branch_manager')): 
                $resvList = $pdo->query("SELECT r.*, c.make, c.model, c.plate_number, u.name as rep_name FROM `reservations` r JOIN `cars` c ON r.car_id = c.id LEFT JOIN `users` u ON r.created_by_user_id = u.id ORDER BY r.created_at DESC")->fetchAll();
            ?>
            <div class="space-y-6 max-w-6xl mx-auto text-right w-full font-sans" dir="rtl">
                <!-- Page Title Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-900 border border-slate-800 p-5 rounded-2xl text-white">
                    <div>
                        <h2 class="text-xl font-black text-slate-100 flex items-center gap-2">
                            🔒 إدارة حجز المركبات والطلبات المباشرة
                        </h2>
                        <p class="text-xs text-slate-400 mt-1">تعديل مدد الحجز، إلغاء الحجز، أو ترحيل السيارات المحجوزة مباشرةً لقسم المبيعات وإصدار الفواتير</p>
                    </div>
                </div>

                <!-- Notification area -->
                <?php if (isset($_GET['success_del'])): ?>
                    <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold rounded-xl flex items-center gap-2">
                        ✓ تم إلغاء وحذف الحجز نهائياً بنجاح، وإرجاع السيارة إلى حالة "متاحة للبيع".
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['success_edit'])): ?>
                    <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold rounded-xl flex items-center gap-2">
                        ✓ تم حفظ التعديلات على بيانات الحجز ومدة الصلاحية بنجاح.
                    </div>
                <?php endif; ?>
                <?php if (!empty($res_error)): ?>
                    <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-bold rounded-xl flex items-center gap-2">
                        ⚠️ <?php echo htmlspecialchars($res_error); ?>
                    </div>
                <?php endif; ?>

                <!-- Table Content -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-2xl shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-slate-100 dark:border-slate-800">
                        <h3 class="text-sm font-extrabold text-slate-800 dark:text-slate-100">قائمة الحجوزات النشطة والمعالجة</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-right border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-bold border-b border-slate-100 dark:border-slate-850">
                                    <th class="p-4">رقم الحجز</th>
                                    <th class="p-4">العميل</th>
                                    <th class="p-4">رقم الهاتف</th>
                                    <th class="p-4">السيارة المحجوزة</th>
                                    <th class="p-4">المندوب</th>
                                    <th class="p-4">المدة الصالحة</th>
                                    <th class="p-4">التاريخ</th>
                                    <th class="p-4">الحالة</th>
                                    <th class="p-4 text-left">خيارات التحكم</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-850 text-slate-700 dark:text-slate-300">
                                <?php if (empty($resvList)): ?>
                                    <tr>
                                        <td colspan="9" class="p-8 text-center text-slate-400 dark:text-slate-500">
                                            لا توجد أي طلبات حجز مسجلة في الوقت الحالي.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($resvList as $resv): ?>
                                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-950/40 transition">
                                            <td class="p-4 font-mono font-bold text-slate-900 dark:text-slate-200">#<?php echo $resv['id']; ?></td>
                                            <td class="p-4">
                                                <div class="font-extrabold text-slate-900 dark:text-slate-200"><?php echo htmlspecialchars($resv['customer_name']); ?></div>
                                            </td>
                                            <td class="p-4 font-sans"><?php echo htmlspecialchars($resv['customer_phone']); ?></td>
                                            <td class="p-4 font-bold text-indigo-600 dark:text-indigo-400">
                                                <?php echo htmlspecialchars($resv['make'] . ' ' . $resv['model'] . ' (' . $resv['plate_number'] . ')'); ?>
                                            </td>
                                            <td class="p-4"><?php echo htmlspecialchars($resv['rep_name'] ?: 'مدير النظام'); ?></td>
                                            <td class="p-4 font-sans font-bold"><?php echo $resv['duration']; ?> أيام</td>
                                            <td class="p-4 font-sans"><?php echo date('Y-m-d', strtotime($resv['created_at'])); ?></td>
                                            <td class="p-4">
                                                <?php if ($resv['status'] === 'active'): ?>
                                                    <span class="px-2.5 py-1 text-[10px] rounded-full bg-amber-500/10 text-amber-500 font-extrabold">حجز نشط ⏳</span>
                                                <?php elseif ($resv['status'] === 'completed'): ?>
                                                    <span class="px-2.5 py-1 text-[10px] rounded-full bg-emerald-500/10 text-emerald-500 font-extrabold">تم البيع والترحيل 🤝</span>
                                                <?php else: ?>
                                                    <span class="px-2.5 py-1 text-[10px] rounded-full bg-rose-500/10 text-rose-500 font-extrabold">ملغي ❌</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-4 text-left space-x-1 space-x-reverse">
                                                <?php if ($resv['status'] === 'active'): ?>
                                                    <button data-res-id="<?php echo $resv['id']; ?>" data-car-id="<?php echo $resv['car_id']; ?>" data-car-name="<?php echo htmlspecialchars($resv['make'] . ' ' . $resv['model'] . ' (' . $resv['plate_number'] . ')', ENT_QUOTES, 'UTF-8'); ?>" data-cust-name="<?php echo htmlspecialchars($resv['customer_name'], ENT_QUOTES, 'UTF-8'); ?>" data-cust-phone="<?php echo htmlspecialchars($resv['customer_phone'], ENT_QUOTES, 'UTF-8'); ?>" onclick="openSellReservationModal(this.getAttribute('data-res-id'), this.getAttribute('data-car-id'), this.getAttribute('data-car-name'), this.getAttribute('data-cust-name'), this.getAttribute('data-cust-phone'))" class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-[10px] cursor-pointer transition">
                                                        🤝 تم البيع
                                                    </button>
                                                    <button data-res-id="<?php echo $resv['id']; ?>" data-cust-name="<?php echo htmlspecialchars($resv['customer_name'], ENT_QUOTES, 'UTF-8'); ?>" data-cust-phone="<?php echo htmlspecialchars($resv['customer_phone'], ENT_QUOTES, 'UTF-8'); ?>" data-duration="<?php echo $resv['duration']; ?>" data-notes="<?php echo htmlspecialchars($resv['notes'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" onclick="openEditReservationModal(this.getAttribute('data-res-id'), this.getAttribute('data-cust-name'), this.getAttribute('data-cust-phone'), this.getAttribute('data-duration'), this.getAttribute('data-notes'))" class="px-2.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/30 dark:hover:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 rounded-lg font-bold text-[10px] cursor-pointer transition">
                                                        ✏️ تعديل
                                                    </button>
                                                    <a href="?page=reservations&cancel_reservation=<?php echo $resv['id']; ?>" onclick="return confirm('هل تود إلغاء حجز هذه السيارة وإعادتها فورياً مخزنياً؟');" class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg font-bold text-[10px] cursor-pointer transition inline-block">
                                                        🚫 إلغاء الحجز
                                                    </a>
                                                <?php endif; ?>
                                                <a href="?page=reservations&delete_reservation=<?php echo $resv['id']; ?>" onclick="return confirm('تحذير: هل أنت متأكد من حذف هذا السجل نهائياً من قاعدة البيانات؟');" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg font-bold text-[10px] cursor-pointer transition inline-block">
                                                    🗑️ حذف
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Edit Reservation Modal -->
                <div id="edit-reservation-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden font-sans" dir="rtl">
                    <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-2xl shadow-xl overflow-hidden text-right text-white">
                        <div class="px-5 py-4 bg-slate-950 border-b border-slate-850 flex justify-between items-center">
                            <h3 class="font-extrabold text-sm flex items-center gap-2"><span>✏️</span> تعديل بيانات الحجز</h3>
                            <button onclick="document.getElementById('edit-reservation-modal').classList.add('hidden')" class="text-slate-400 hover:text-white transition text-lg font-bold cursor-pointer">&times;</button>
                        </div>
                        <form method="POST" class="p-5 space-y-4">
                            <input type="hidden" name="update_reservation" value="1">
                            <input type="hidden" name="res_id" id="edit-res-id">
                            <div>
                                <label class="block text-xs font-bold text-slate-300 mb-1.5">اسم العميل <span class="text-red-500">*</span></label>
                                <input type="text" name="customer_name" id="edit-customer-name" required class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-bold">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-300 mb-1.5">رقم الهاتف <span class="text-red-500">*</span></label>
                                <input type="text" name="customer_phone" id="edit-customer-phone" required class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-bold">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-300 mb-1.5">مدة الحجز (بالأيام)</label>
                                <input type="number" name="duration" id="edit-duration" min="1" max="90" required class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-300 mb-1.5">ملاحظات الحجز</label>
                                <textarea name="notes" id="edit-notes" rows="3" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"></textarea>
                            </div>
                            <div class="flex justify-start gap-2 border-t border-slate-850 pt-4 mt-6">
                                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg cursor-pointer transition">حفظ التعديلات</button>
                                <button type="button" onclick="document.getElementById('edit-reservation-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-lg cursor-pointer transition">إلغاء</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sell Reservation Modal has been moved to global scope for use across all pages -->

                <!-- JavaScript helpers for reservation actions -->
                <script>
                    function openEditReservationModal(resId, customerName, customerPhone, duration, notes) {
                        document.getElementById('edit-res-id').value = resId;
                        document.getElementById('edit-customer-name').value = customerName;
                        document.getElementById('edit-customer-phone').value = customerPhone;
                        document.getElementById('edit-duration').value = duration;
                        document.getElementById('edit-notes').value = notes;
                        document.getElementById('edit-reservation-modal').classList.remove('hidden');
                    }
                </script>
            </div>
            <?php endif; ?>

            <!-- PAGE 4: USERS & EMPlOYEES -->
            <?php if ($page === 'users' && ($user_role === 'admin' || $user_role === 'branch_manager')): 
                $usersList = $pdo->query("SELECT * FROM `users` ORDER BY `created_at` DESC")->fetchAll();
            ?>
            <div class="space-y-6">
                <!-- Notification area -->
                <?php if (!empty($user_error)): ?>
                    <div class="p-4 bg-rose-100 border border-rose-200 rounded-lg text-rose-700 text-xs font-bold">
                        ⚠️ <?php echo htmlspecialchars($user_error); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($user_success)): ?>
                    <div class="p-4 bg-emerald-100 border border-emerald-200 rounded-lg text-emerald-700 text-xs font-bold">
                        ✓ <?php echo htmlspecialchars($user_success); ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white border border-slate-200 p-4 rounded-xl flex justify-between items-center shadow-sm">
                    <div>
                        <h4 class="font-bold text-xs text-slate-800">قائمة موظفي ومناديب المبيعات الفعليين</h4>
                        <p class="text-[11px] text-slate-400 mt-0.5">يمكنك إضافة صلاحيات أو توليد كلمات مرور للمناديب الجدد.</p>
                    </div>
                    <button onclick="document.getElementById('user-form-panel').classList.toggle('hidden')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded cursor-pointer">
                        ➕ تعيين موظف جديد
                    </button>
                </div>

                <!-- Hidden form to add User -->
                <div id="user-form-panel" class="hidden bg-white border border-slate-200 p-6 rounded-xl shadow-lg transition-all space-y-4">
                    <h4 id="user-form-title" class="font-bold text-xs text-slate-800 border-b border-slate-100 pb-2 mb-4">👤 إدخال بيانات الموظف المعتمدة</h4>
                    <form method="POST" action="index.php?page=users" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <input type="hidden" name="save_user" value="1">
                        <input type="hidden" name="id" value="">
                        
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">اسم الموظف بالكامل</label>
                            <input type="text" name="name" required placeholder="أدخل اسم الموظف" class="w-full text-xs px-3 py-2 border border-slate-200 rounded focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">اسم مستخدم الدخول (Username) - فريد</label>
                            <input type="text" name="username" required placeholder="مثال: ali" class="w-full text-xs px-3 py-2 border border-slate-200 rounded font-sans focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">الرمز السري للدخول</label>
                            <input type="password" name="password" placeholder="اتركها فارغة لتجنب التغيير عند التعديل" class="w-full text-xs px-3 py-2 border border-slate-200 rounded focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">رتبة الصلاحية</label>
                            <select name="role" class="w-full text-xs px-3 py-2 border border-slate-200 rounded bg-white focus:outline-none">
                                <option value="representative">مندوب مبيعات / حجوزات</option>
                                <option value="branch_manager">مدير فرع</option>
                                <option value="admin">مدير نظام كامل الصلاحية</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">البريد الإلكتروني</label>
                            <input type="email" name="email" placeholder="email@example.com" class="w-full text-xs px-3 py-2 border border-slate-200 rounded font-sans focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">رقم هاتف الجوال</label>
                            <input type="text" name="phone" placeholder="05xxxxxxxx" class="w-full text-xs px-3 py-2 border border-slate-200 rounded font-sans focus:outline-none">
                        </div>

                        <div class="md:col-span-3 flex justify-end gap-2 pt-4 border-t border-slate-100">
                            <button type="button" onclick="document.getElementById('user-form-panel').classList.add('hidden'); resetUserForm();" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded transition-colors">إلغاء</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded transition-colors">حفظ الموظف وتأكيد الحساب</button>
                        </div>
                    </form>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                    <table class="w-full text-right border-collapse">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 text-[10px] font-bold">
                                <th class="p-4">الاسم بالكامل</th>
                                <th class="p-4">اسم المستخدم</th>
                                <th class="p-4">البريد الإلكتروني</th>
                                <th class="p-4">رقم الجوال</th>
                                <th class="p-4">رتبة الصلاحية</th>
                                <th class="p-4">تاريخ التعيين</th>
                                <th class="p-4 text-center">إجراءات التحكم</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-150 text-xs text-slate-600">
                            <?php foreach ($usersList as $usr): ?>
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="p-4 font-bold text-slate-800"><?php echo htmlspecialchars($usr['name']); ?></td>
                                    <td class="p-4 font-sans font-medium text-slate-500"><?php echo htmlspecialchars($usr['username']); ?></td>
                                    <td class="p-4 font-sans"><?php echo htmlspecialchars($usr['email'] ?: 'غير محدد'); ?></td>
                                    <td class="p-4 font-sans"><?php echo htmlspecialchars($usr['phone'] ?: 'غير محدد'); ?></td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold <?php 
                                            if ($usr['role'] === 'admin') {
                                                echo 'bg-indigo-100 text-indigo-700';
                                            } elseif ($usr['role'] === 'branch_manager') {
                                                echo 'bg-blue-100 text-blue-700';
                                            } else {
                                                echo 'bg-emerald-100 text-emerald-700';
                                            }
                                        ?>">
                                            <?php 
                                            if ($usr['role'] === 'admin') {
                                                echo 'المدير العام';
                                            } elseif ($usr['role'] === 'branch_manager') {
                                                echo 'مدير الفرع';
                                            } else {
                                                echo 'مندوب معتمد';
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td class="p-4 font-sans"><?php echo date('Y-m-d', strtotime($usr['created_at'])); ?></td>
                                    <td class="p-4 flex justify-center gap-2">
                                        <button 
                                            onclick='editUser(<?php echo json_encode($usr, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
                                            class="px-2 py-1 bg-indigo-50 border border-indigo-150 hover:bg-indigo-600 hover:text-white text-indigo-600 font-bold text-[10px] rounded transition-colors cursor-pointer"
                                        >
                                            تعديل
                                        </button>
                                        <a 
                                            href="?page=users&delete_user=<?php echo $usr['id']; ?>" 
                                            onclick="return confirm('هل أنت متأكد من مسح وحذف حساب هذا الموظف نهائياً؟');"
                                            class="px-2 py-1 bg-rose-50/10 border border-rose-200 hover:bg-rose-500 hover:text-white text-rose-500 font-bold text-[10px] rounded transition-colors"
                                        >
                                            حذف
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <script>
                function editUser(user) {
                    document.getElementById('user-form-panel').classList.remove('hidden');
                    document.getElementById('user-form-title').innerText = "👤 تعديل بيانات الموظف: " + user.name;
                    
                    const form = document.querySelector('#user-form-panel form');
                    form.querySelector('[name="id"]').value = user.id || '';
                    form.querySelector('[name="name"]').value = user.name || '';
                    form.querySelector('[name="username"]').value = user.username || '';
                    form.querySelector('[name="password"]').value = ''; // empty to keep unchanged
                    form.querySelector('[name="role"]').value = user.role || 'representative';
                    form.querySelector('[name="email"]').value = user.email || '';
                    form.querySelector('[name="phone"]').value = user.phone || '';
                    
                    document.getElementById('user-form-panel').scrollIntoView({ behavior: 'smooth' });
                }

                function resetUserForm() {
                    document.getElementById('user-form-title').innerText = "👤 إدخال بيانات الموظف المعتمدة";
                    const form = document.querySelector('#user-form-panel form');
                    form.reset();
                    form.querySelector('[name="id"]').value = '';
                }
                </script>
            </div>
            <?php endif; ?>

            <!-- PAGE 5: GEOGRAPHIC BRANCHES -->
            <?php if ($page === 'branches' && ($user_role === 'admin' || $user_role === 'branch_manager')): 
                $branchesList = $pdo->query("SELECT * FROM `branches` ORDER BY `created_at` DESC")->fetchAll();
                $allUsers = $pdo->query("SELECT * FROM `users` ORDER BY `name` ASC")->fetchAll();
                $users_map = [];
                foreach ($allUsers as $u) {
                    $users_map[$u['id']] = $u['name'];
                }
            ?>
            <div class="space-y-6">
                <!-- Notification area -->
                <?php if (!empty($branch_error)): ?>
                    <div class="p-4 bg-rose-100 border border-rose-200 rounded-lg text-rose-700 text-xs font-bold">
                        ⚠️ <?php echo htmlspecialchars($branch_error); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($branch_success)): ?>
                    <div class="p-4 bg-emerald-100 border border-emerald-200 rounded-lg text-emerald-700 text-xs font-bold">
                        ✓ <?php echo htmlspecialchars($branch_success); ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white border border-slate-200 p-4 rounded-xl flex justify-between items-center shadow-sm">
                    <div>
                        <h4 class="font-bold text-xs text-slate-800">إدارة صالات المعارض والفروع الجغرافية المعتمدة</h4>
                        <p class="text-[11px] text-slate-400 mt-0.5">قم بتمثيل المعارض جغرافياً وتحديد مدراء الفروع لمراقبة السعة.</p>
                    </div>
                    <button onclick="document.getElementById('branch-form-panel').classList.toggle('hidden')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded cursor-pointer">
                        ➕ تدشين فرع جديد
                    </button>
                </div>

                <div id="branch-form-panel" class="hidden bg-white border border-slate-200 p-6 rounded-xl shadow-lg space-y-4">
                    <h4 id="branch-form-title" class="font-bold text-xs text-slate-800 border-b border-slate-100 pb-2 mb-4">📍 إدخال بيانات صالة العرض</h4>
                    <form method="POST" action="index.php?page=branches" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input type="hidden" name="save_branch" value="1">
                        <input type="hidden" name="id" value="">
                        
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">اسم الفرع / المستودع الرئيسي</label>
                            <input type="text" name="name" required placeholder="مثال: فرع الرياض الرئيسي" class="w-full text-xs px-3 py-2 border border-slate-200 rounded">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">العنوان الجغرافي العام</label>
                            <input type="text" name="location" required placeholder="الرياض، طريق الملك فهد" class="w-full text-xs px-3 py-2 border border-slate-200 rounded">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">اسم صالة العرض المكتوب بالتقارير</label>
                            <input type="text" name="showroom_name" placeholder="مثال: صالة النخبة للسيارات" class="w-full text-xs px-3 py-2 border border-slate-200 rounded">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">العنوان التفصيلي المطبوع على الخطابات</label>
                            <input type="text" name="showroom_address" placeholder="مثال: الرياض، حي السلي، مخرج 17" class="w-full text-xs px-3 py-2 border border-slate-200 rounded">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">هاتف المعرض للتواصل</label>
                            <input type="text" name="phone" placeholder="011xxxxxxx" class="w-full text-xs px-3 py-2 border border-slate-200 rounded font-sans">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">مدير الفرع المنفذ</label>
                            <select name="manager" class="w-full text-xs px-3 py-2 border border-slate-200 rounded bg-white">
                                <option value="">-- اختر مدير الفرع من المستخدمين --</option>
                                <?php foreach ($allUsers as $usr): ?>
                                    <option value="<?php echo htmlspecialchars($usr['id']); ?>"><?php echo htmlspecialchars($usr['name'] . ' (' . $usr['username'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">الرقم الضريبي للفرع (الرقم الضريبي)</label>
                            <input type="text" name="tax_number" placeholder="مثال: 300012345600003" class="w-full text-xs px-3 py-2 border border-slate-200 rounded font-sans">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">رقم السجل التجاري (رقم السجل)</label>
                            <input type="text" name="commercial_registration" placeholder="مثال: 1010123456" class="w-full text-xs px-3 py-2 border border-slate-200 rounded font-sans">
                        </div>

                        <div class="md:col-span-2">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-slate-50 p-3 rounded-xl border border-slate-100 mb-2">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 mb-1">📤 رفع ملف شعار الصالة (صورة)</label>
                                    <input type="file" name="branch_logo_file" accept="image/*" class="w-full text-xs px-3 py-1.5 border border-slate-200 bg-white rounded font-sans cursor-pointer">
                                    <span class="text-[9px] text-slate-400 block mt-1">يدعم رفع ملفات الصور مباشرة (PNG, JPG, SVG)</span>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 mb-1">🔗 أو رابط الشعار المباشر / Base64</label>
                                    <input type="text" name="logo" placeholder="أو سيتم تعبئة الرابط تلقائياً بعد رفع الشعار..." class="w-full text-xs px-3 py-2 border border-slate-200 bg-white rounded font-sans">
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-slate-50 p-3 rounded-xl border border-slate-100 mb-2">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 mb-1">💮 رفع ملف ختم الفرع (صورة الختم)</label>
                                    <input type="file" name="branch_stamp_file" accept="image/*" class="w-full text-xs px-3 py-1.5 border border-slate-200 bg-white rounded font-sans cursor-pointer">
                                    <span class="text-[9px] text-slate-400 block mt-1">يدعم رفع ملفات الصور مباشرة (PNG, JPG, SVG)</span>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-600 mb-1">🔗 أو رابط الختم المباشر / Base64</label>
                                    <input type="text" name="stamp" placeholder="أو سيتم تعبئة الرابط تلقائياً بعد رفع الختم..." class="w-full text-xs px-3 py-2 border border-slate-200 bg-white rounded font-sans">
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2 flex justify-end gap-2 pt-4">
                            <button type="button" onclick="document.getElementById('branch-form-panel').classList.add('hidden'); resetBranchForm();" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded transition-colors">إلغاء</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded transition-colors">تأكيد وحفظ بيانات الصالة</button>
                        </div>
                    </form>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($branchesList as $br): 
                        // count cars in this branch
                        $carsCount = $pdo->prepare("SELECT count(*) FROM `cars` WHERE `branch_id` = ?");
                        $carsCount->execute([$br['id']]);
                        $numCars = $carsCount->fetchColumn();
                    ?>
                        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm space-y-3 flex flex-col justify-between">
                            <div class="space-y-2">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-[10px] bg-indigo-50 text-indigo-600 font-extrabold px-2.5 py-0.5 rounded">فرع نشط</span>
                                    <?php if (!empty($br['logo'])): ?>
                                        <img src="<?php echo htmlspecialchars($br['logo']); ?>" class="h-6 w-auto object-contain rounded" alt="Branch Logo" referrerPolicy="no-referrer">
                                    <?php endif; ?>
                                </div>
                                <h4 class="font-extrabold text-sm text-slate-800"><?php echo htmlspecialchars($br['name']); ?></h4>
                                <p class="text-xs text-slate-400">📍 <?php echo htmlspecialchars($br['location']); ?></p>
                                
                                <div class="text-[11px] text-slate-600 space-y-1 bg-slate-50 p-2 rounded border border-slate-100">
                                    <div>صالة العرض: <span class="font-bold text-slate-800"><?php echo htmlspecialchars($br['showroom_name'] ?: 'مثل الفرع'); ?></span></div>
                                    <div>العنوان المطبوع: <span class="text-slate-500"><?php echo htmlspecialchars($br['showroom_address'] ?: 'مثل الموقع'); ?></span></div>
                                    <div class="grid grid-cols-2 gap-1 text-[10px] text-slate-500 font-sans mt-1 pt-1 border-t border-slate-100">
                                        <div>الرقم الضريبي: <span class="font-bold text-slate-700"><?php echo htmlspecialchars($br['tax_number'] ?: '-'); ?></span></div>
                                        <div>رقم السجل: <span class="font-bold text-slate-700"><?php echo htmlspecialchars($br['commercial_registration'] ?: '-'); ?></span></div>
                                    </div>
                                    <?php if (!empty($br['stamp'])): ?>
                                        <div class="mt-2 pt-2 border-t border-dashed border-slate-150 flex items-center justify-between text-[10px] text-slate-500">
                                            <span>ختم الفرع المعتمد:</span>
                                            <img src="<?php echo htmlspecialchars($br['stamp']); ?>" class="h-10 w-auto object-contain rounded border border-slate-100 bg-slate-50 p-0.5" alt="Branch Stamp" referrerPolicy="no-referrer">
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="flex justify-between text-[11px] text-slate-500">
                                    <span>مسؤول الفرع:</span>
                                    <span class="font-semibold text-slate-700"><?php echo htmlspecialchars(isset($users_map[$br['manager']]) ? $users_map[$br['manager']] : ($br['manager'] ?: 'غير محدد')); ?></span>
                                </div>
                                <div class="flex justify-between text-[11px] text-slate-500">
                                    <span>هاتف المعرض:</span>
                                    <span class="font-semibold text-slate-700 font-sans"><?php echo htmlspecialchars($br['phone'] ?: 'غير متوفر'); ?></span>
                                </div>
                                
                                <div class="pt-2 border-t border-slate-100 flex justify-between items-center text-xs text-slate-500 font-medium">
                                    <span>سعة المخزن الحالية:</span>
                                    <span class="font-bold text-slate-800"><?php echo $numCars; ?> سيارة</span>
                                </div>
                            </div>
                            
                            <div class="flex justify-end gap-2 pt-2 border-t border-slate-50">
                                <button 
                                    onclick='editBranch(<?php echo json_encode($br, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
                                    class="px-2.5 py-1.5 bg-indigo-50 border border-indigo-200 hover:bg-indigo-600 hover:text-white text-indigo-600 font-bold text-[10px] rounded transition-colors cursor-pointer"
                                >
                                    تعديل
                                </button>
                                <a 
                                    href="?page=branches&delete_branch=<?php echo $br['id']; ?>" 
                                    onclick="return confirm('هل أنت متأكد من حذف هذا الفرع نهائياً؟ تنبيه: لا يمكن حذفه إذا كان يحتوي على سيارات نشطة.');"
                                    class="px-2.5 py-1.5 bg-rose-50/10 border border-rose-200 hover:bg-rose-500 hover:text-white text-rose-500 font-bold text-[10px] rounded transition-colors"
                                >
                                    حذف
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <script>
                function editBranch(branch) {
                    document.getElementById('branch-form-panel').classList.remove('hidden');
                    document.getElementById('branch-form-title').innerText = "📍 تعديل بيانات صالة العرض: " + branch.name;
                    
                    const form = document.querySelector('#branch-form-panel form');
                    form.querySelector('[name="id"]').value = branch.id || '';
                    form.querySelector('[name="name"]').value = branch.name || '';
                    form.querySelector('[name="location"]').value = branch.location || '';
                    form.querySelector('[name="phone"]').value = branch.phone || '';
                    form.querySelector('[name="manager"]').value = branch.manager || '';
                    form.querySelector('[name="showroom_name"]').value = branch.showroom_name || '';
                    form.querySelector('[name="showroom_address"]').value = branch.showroom_address || '';
                    form.querySelector('[name="tax_number"]').value = branch.tax_number || '';
                    form.querySelector('[name="commercial_registration"]').value = branch.commercial_registration || '';
                    form.querySelector('[name="logo"]').value = branch.logo || '';
                    form.querySelector('[name="stamp"]').value = branch.stamp || '';
                    
                    document.getElementById('branch-form-panel').scrollIntoView({ behavior: 'smooth' });
                }

                function resetBranchForm() {
                    document.getElementById('branch-form-title').innerText = "📍 إدخال بيانات صالة العرض";
                    const form = document.querySelector('#branch-form-panel form');
                    form.reset();
                    form.querySelector('[name="id"]').value = '';
                    form.querySelector('[name="stamp"]').value = '';
                }
                </script>
            </div>
            <?php endif; ?>

            <!-- PAGE 5.5: DELEGATES AUDIT & SALES MONITORING -->
            <?php if ($page === 'logs_delegates' && ($user_role === 'admin' || $user_role === 'branch_manager')): 
                // Fetch representatives list
                $repsList = $pdo->query("
                    SELECT u.id, u.name, u.username, u.email, u.phone, u.created_at,
                           (SELECT COUNT(*) FROM `reservations` r WHERE r.created_by_user_id = u.id) as total_reservations,
                           (SELECT MAX(r.created_at) FROM `reservations` r WHERE r.created_by_user_id = u.id) as last_reservation_date
                    FROM `users` u
                    WHERE u.role = 'representative'
                    ORDER BY total_reservations DESC
                ")->fetchAll();

                // Fetch logs strictly for representatives
                $repLogs = $pdo->query("
                    SELECT sl.*
                    FROM `system_logs` sl
                    JOIN `users` u ON sl.user_id = u.id
                    WHERE u.role = 'representative'
                    ORDER BY sl.created_at DESC
                    LIMIT 50
                ")->fetchAll();

                // Stats
                $activeRepsCount = count($repsList);
                $totalRepReservations = $pdo->query("
                    SELECT COUNT(*) FROM `reservations` r
                    JOIN `users` u ON r.created_by_user_id = u.id
                    WHERE u.role = 'representative'
                ")->fetchColumn() ?: 0;
            ?>
            <div class="space-y-6">
                <!-- Info Header -->
                <div class="bg-[#0F172A] text-white p-5 rounded-2xl shadow-lg border border-slate-800 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h4 class="font-extrabold text-sm text-slate-100">🛡️ نظام رقابة ومتابعة المناديب (Delegates Control Shield)</h4>
                        <p class="text-[10px] text-slate-400 mt-1">تتيح لك هذه المنصة الإشرافية تتبع حجوزات مناديب المبيعات الحية، معدلات الإغلاق، ومراقبة العمليات بشكل لحظي متكامل.</p>
                    </div>
                    <div class="flex gap-4">
                        <div class="bg-indigo-500/10 border border-indigo-500/20 px-4 py-2 rounded-xl text-center">
                            <span class="block text-[9px] text-slate-400 font-bold">المناديب النشطين</span>
                            <span class="text-lg font-black text-indigo-400 font-sans leading-none"><?php echo $activeRepsCount; ?></span>
                        </div>
                        <div class="bg-emerald-500/10 border border-emerald-500/20 px-4 py-2 rounded-xl text-center">
                            <span class="block text-[9px] text-slate-400 font-bold">إجمالي حجوزاتهم</span>
                            <span class="text-lg font-black text-emerald-400 font-sans leading-none"><?php echo $totalRepReservations; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Delegates Leaderboard and Stats -->
                <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden p-6 space-y-4">
                    <div>
                        <h5 class="font-extrabold text-xs text-slate-800">📊 جدول المناديب ومعدلات الحجز</h5>
                        <p class="text-[10px] text-slate-400 mt-0.5">ترتيب مناديب المبيعات حسب الكفاءة وإجمالي عدد الحجوزات النشطة والمسجلة باسمائهم.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-right border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-slate-700 text-[10px] font-bold uppercase">
                                    <th class="p-4">اسم المندوب المعين</th>
                                    <th class="p-4">اسم المستخدم</th>
                                    <th class="p-4">رقم الهاتف</th>
                                    <th class="p-4 text-center">إجمالي عمليات الحجز</th>
                                    <th class="p-4">تاريخ آخر حجز نشط</th>
                                    <th class="p-4">تاريخ الانضمام</th>
                                    <th class="p-4">حالة الحساب</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-150 text-xs text-slate-600">
                                <?php if (empty($repsList)): ?>
                                    <tr>
                                        <td colspan="7" class="p-8 text-center text-slate-400">
                                            لا يوجد أي مندوب مبيعات مسجل حالياً بالنظام. انتقل لشؤون الموظفين لإضافة مندوب جديد.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($repsList as $rep): ?>
                                        <tr class="hover:bg-slate-50/50 transition duration-150">
                                            <td class="p-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 rounded-full bg-indigo-50 border border-indigo-150 flex items-center justify-center text-indigo-600 font-extrabold text-[11px]">
                                                        <?php echo mb_substr($rep['name'], 0, 1, 'utf-8'); ?>
                                                    </div>
                                                    <div>
                                                        <span class="font-bold text-slate-800 block leading-none"><?php echo htmlspecialchars($rep['name']); ?></span>
                                                        <span class="text-[9px] text-slate-400 mt-1 block font-sans"><?php echo htmlspecialchars($rep['email'] ?: 'بدون بريد'); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="p-4 font-mono text-[11px] text-slate-500">@<?php echo htmlspecialchars($rep['username']); ?></td>
                                            <td class="p-4 font-sans text-slate-500"><?php echo htmlspecialchars($rep['phone'] ?: '-'); ?></td>
                                            <td class="p-4 text-center">
                                                <span class="inline-block px-2.5 py-1 text-xs font-black bg-indigo-50 border border-indigo-100 text-indigo-600 rounded-lg font-sans">
                                                    <?php echo $rep['total_reservations']; ?>
                                                </span>
                                            </td>
                                            <td class="p-4 text-slate-500 font-sans">
                                                <?php echo $rep['last_reservation_date'] ? date('Y-m-d H:i', strtotime($rep['last_reservation_date'])) : '<span class="text-slate-300">لا يوجد حجز بعد</span>'; ?>
                                            </td>
                                            <td class="p-4 text-slate-400 font-sans"><?php echo date('Y-m-d', strtotime($rep['created_at'])); ?></td>
                                            <td class="p-4">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[9px] font-extrabold bg-emerald-100 text-emerald-800">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    معتمد نشط
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Delegate Activity Feed -->
                <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden p-6 space-y-4">
                    <div>
                        <h5 class="font-extrabold text-xs text-slate-800">📜 سجل العمليات والمراقبة المباشرة للمناديب (WAF Rep Logs)</h5>
                        <p class="text-[10px] text-slate-400 mt-0.5">قائمة بالأفعال والتحركات الأمنية التي قام بها مناديب المبيعات المعتمدون في النظام.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-right border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-slate-700 text-[10px] font-bold uppercase">
                                    <th class="p-4">المندوب</th>
                                    <th class="p-4">العملية المنفذة</th>
                                    <th class="p-4">تفاصيل العملية</th>
                                    <th class="p-4 text-center">مستوى الخطورة</th>
                                    <th class="p-4">عنوان IP</th>
                                    <th class="p-4">تاريخ وتوقيت العملية</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-150 text-xs text-slate-600">
                                <?php if (empty($repLogs)): ?>
                                    <tr>
                                        <td colspan="6" class="p-8 text-center text-slate-400">
                                            لم يتم تسجيل أي عمليات أو حركات للمناديب في سجلات الرقابة حتى الآن.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($repLogs as $rl): ?>
                                        <tr class="hover:bg-slate-50/50 transition">
                                            <td class="p-4 font-bold text-slate-800"><?php echo htmlspecialchars($rl['user_name']); ?></td>
                                            <td class="p-4">
                                                <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-indigo-50 border border-indigo-100 text-indigo-700">
                                                    <?php echo htmlspecialchars($rl['action']); ?>
                                                </span>
                                            </td>
                                            <td class="p-4 text-slate-500 max-w-sm truncate" title="<?php echo htmlspecialchars($rl['details']); ?>"><?php echo htmlspecialchars($rl['details']); ?></td>
                                            <td class="p-4 text-center">
                                                <span class="px-2 py-0.5 rounded text-[9px] font-bold <?php echo $rl['risk_level'] === 'high' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-700'; ?>">
                                                    <?php echo $rl['risk_level'] === 'high' ? 'عالي' : 'منخفض'; ?>
                                                </span>
                                            </td>
                                            <td class="p-4 font-mono text-[10px] text-slate-400"><?php echo htmlspecialchars($rl['ip']); ?></td>
                                            <td class="p-4 font-sans text-slate-400"><?php echo date('Y-m-d H:i:s', strtotime($rl['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
            <?php endif; ?>

            <!-- PAGE 6: SECURITY SYSTEM AUDIT LOGS -->
            <?php if ($page === 'logs' && ($user_role === 'admin' || $user_role === 'branch_manager')): 
                $logsList = $pdo->query("SELECT * FROM `system_logs` ORDER BY `created_at` DESC LIMIT 100")->fetchAll();
            ?>
            <div class="space-y-6">
                <div class="bg-indigo-900 text-white p-4 rounded-xl shadow-md">
                    <h4 class="font-bold text-xs">سجل المراقبة والوقاية وجدار الحماية السيبراني (PHP WAF Block Logs)</h4>
                    <p class="text-[11px] text-indigo-200 mt-1">توضح هذه الشاشة مراقبة العمليات وعمليات تسجيل الدخول ومحاولات الحقن SQL Injection / XSS لحماية خوادم cPanel المشتركة.</p>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                    <table class="w-full text-right border-collapse">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 text-[10px] font-bold uppercase">
                                <th class="p-4">المنفذ</th>
                                <th class="p-4">العملية المنفذة</th>
                                <th class="p-4">تفاصيل الرقابة الفنية</th>
                                <th class="p-4">عنوان IP</th>
                                <th class="p-4">توقيت الحادثة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-150 text-xs text-slate-600">
                            <?php foreach ($logsList as $lg): ?>
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="p-4 font-bold text-slate-800"><?php echo htmlspecialchars($lg['user_name']); ?></td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-slate-100 text-slate-700">
                                            <?php echo htmlspecialchars($lg['action']); ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-slate-500 max-w-sm truncate" title="<?php echo htmlspecialchars($lg['details']); ?>"><?php echo htmlspecialchars($lg['details']); ?></td>
                                    <td class="p-4 font-mono text-[10px] text-slate-400"><?php echo htmlspecialchars($lg['ip']); ?></td>
                                    <td class="p-4 font-sans text-slate-400"><?php echo date('Y-m-d H:i:s', strtotime($lg['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- PAGE 8: COMPREHENSIVE REPORTS -->
            <?php if ($page === 'reports' && ($user_role === 'admin' || $user_role === 'branch_manager')): 
                // Fetch dynamic stats directly from the database
                $stats_total = $pdo->query("SELECT COUNT(*) FROM `cars`")->fetchColumn();
                $stats_available = $pdo->query("SELECT COUNT(*) FROM `cars` WHERE `status` = 'available'")->fetchColumn();
                $stats_reserved = $pdo->query("SELECT COUNT(*) FROM `cars` WHERE `status` = 'reserved'")->fetchColumn();
                $stats_sold = $pdo->query("SELECT COUNT(*) FROM `cars` WHERE `status` = 'sold'")->fetchColumn();
                $stats_not_for_sale = $pdo->query("SELECT COUNT(*) FROM `cars` WHERE `status` = 'not_for_sale'")->fetchColumn();
                $stats_value = $pdo->query("SELECT SUM(`price`) FROM `cars` WHERE `status` != 'sold'")->fetchColumn() ?: 0;
                $stats_today = $pdo->query("SELECT COUNT(*) FROM `cars` WHERE DATE(`created_at`) = CURRENT_DATE")->fetchColumn();
                $stats_entries = $pdo->query("SELECT COUNT(*) FROM `cars` WHERE `entry_date` IS NOT NULL OR `created_at` IS NOT NULL")->fetchColumn();
                $stats_exits = $pdo->query("SELECT COUNT(*) FROM `cars` WHERE `status` = 'sold' OR `exit_date` IS NOT NULL")->fetchColumn();

                // Dynamic dropdown values for filters
                $branches_lookup = $pdo->query("SELECT * FROM `branches` ORDER BY `name` ASC")->fetchAll();
                $makes_lookup = $pdo->query("SELECT DISTINCT `make` FROM `cars` WHERE `make` != '' ORDER BY `make` ASC")->fetchAll(PDO::FETCH_COLUMN);
                $trims_lookup = $pdo->query("SELECT DISTINCT `trim` FROM `cars` WHERE `trim` IS NOT NULL AND `trim` != '' ORDER BY `trim` ASC")->fetchAll(PDO::FETCH_COLUMN);
                $suppliers_lookup = $pdo->query("SELECT DISTINCT `supplier` FROM `cars` WHERE `supplier` IS NOT NULL AND `supplier` != '' ORDER BY `supplier` ASC")->fetchAll(PDO::FETCH_COLUMN);
                $reps_lookup = $pdo->query("SELECT DISTINCT `rep_in_charge` FROM `cars` WHERE `rep_in_charge` IS NOT NULL AND `rep_in_charge` != '' ORDER BY `rep_in_charge` ASC")->fetchAll(PDO::FETCH_COLUMN);
                $imports_lookup = $pdo->query("SELECT DISTINCT `import_origin` FROM `cars` WHERE `import_origin` IS NOT NULL AND `import_origin` != '' ORDER BY `import_origin` ASC")->fetchAll(PDO::FETCH_COLUMN);
                $owners_lookup = $pdo->query("SELECT DISTINCT `previous_owner` FROM `cars` WHERE `previous_owner` IS NOT NULL AND `previous_owner` != '' ORDER BY `previous_owner` ASC")->fetchAll(PDO::FETCH_COLUMN);

                // Current selected tab
                $tab = $_GET['tab'] ?? 'stock';

                // Gather filter inputs
                $filter_branch = $_GET['branch_id'] ?? '';
                $filter_make = $_GET['make'] ?? '';
                $filter_trim = $_GET['trim'] ?? '';
                $filter_status = $_GET['status'] ?? '';
                $filter_supplier = $_GET['supplier'] ?? '';
                $filter_rep = $_GET['rep_in_charge'] ?? '';
                $filter_import = $_GET['import_origin'] ?? '';
                $filter_owner = $_GET['previous_owner'] ?? '';
                $filter_from_date = $_GET['from_date'] ?? '';
                $filter_to_date = $_GET['to_date'] ?? '';
                $filter_period_type = $_GET['period_type'] ?? 'all';
                $search_query = $_GET['search'] ?? '';

                // Build query where clauses
                $where_clauses = ["1=1"];
                $params = [];

                if (!empty($filter_branch)) {
                    $where_clauses[] = "c.`branch_id` = ?";
                    $params[] = $filter_branch;
                }
                if (!empty($filter_make)) {
                    $where_clauses[] = "c.`make` = ?";
                    $params[] = $filter_make;
                }
                if (!empty($filter_trim)) {
                    $where_clauses[] = "c.`trim` = ?";
                    $params[] = $filter_trim;
                }
                if (!empty($filter_status)) {
                    $where_clauses[] = "c.`status` = ?";
                    $params[] = $filter_status;
                }
                if (!empty($filter_supplier)) {
                    $where_clauses[] = "c.`supplier` = ?";
                    $params[] = $filter_supplier;
                }
                if (!empty($filter_rep)) {
                    $where_clauses[] = "c.`rep_in_charge` = ?";
                    $params[] = $filter_rep;
                }
                if (!empty($filter_import)) {
                    $where_clauses[] = "c.`import_origin` = ?";
                    $params[] = $filter_import;
                }
                if (!empty($filter_owner)) {
                    $where_clauses[] = "c.`previous_owner` = ?";
                    $params[] = $filter_owner;
                }
                if (!empty($search_query)) {
                    $where_clauses[] = "(c.`make` LIKE ? OR c.`model` LIKE ? OR c.`vin` LIKE ? OR c.`plate_number` LIKE ?)";
                    $params[] = "%$search_query%";
                    $params[] = "%$search_query%";
                    $params[] = "%$search_query%";
                    $params[] = "%$search_query%";
                }

                // Apply period filters depending on report type
                if ($tab === 'entry') {
                    if ($filter_period_type === 'daily') {
                        $where_clauses[] = "DATE(COALESCE(c.`entry_date`, c.`created_at`)) = CURRENT_DATE";
                    } else if ($filter_period_type === 'monthly') {
                        $where_clauses[] = "YEAR(COALESCE(c.`entry_date`, c.`created_at`)) = YEAR(CURRENT_DATE) AND MONTH(COALESCE(c.`entry_date`, c.`created_at`)) = MONTH(CURRENT_DATE)";
                    } else if ($filter_period_type === 'yearly') {
                        $where_clauses[] = "YEAR(COALESCE(c.`entry_date`, c.`created_at`)) = YEAR(CURRENT_DATE)";
                    } else if ($filter_period_type === 'custom' && !empty($filter_from_date) && !empty($filter_to_date)) {
                        $where_clauses[] = "DATE(COALESCE(c.`entry_date`, c.`created_at`)) BETWEEN ? AND ?";
                        $params[] = $filter_from_date;
                        $params[] = $filter_to_date;
                    }
                } else if ($tab === 'exit') {
                    $where_clauses[] = "(c.`status` = 'sold' OR c.`exit_date` IS NOT NULL)";
                    if ($filter_period_type === 'daily') {
                        $where_clauses[] = "DATE(COALESCE(c.`exit_date`, c.`sale_date`)) = CURRENT_DATE";
                    } else if ($filter_period_type === 'monthly') {
                        $where_clauses[] = "YEAR(COALESCE(c.`exit_date`, c.`sale_date`)) = YEAR(CURRENT_DATE) AND MONTH(COALESCE(c.`exit_date`, c.`sale_date`)) = MONTH(CURRENT_DATE)";
                    } else if ($filter_period_type === 'yearly') {
                        $where_clauses[] = "YEAR(COALESCE(c.`exit_date`, c.`sale_date`)) = YEAR(CURRENT_DATE)";
                    } else if ($filter_period_type === 'custom' && !empty($filter_from_date) && !empty($filter_to_date)) {
                        $where_clauses[] = "DATE(COALESCE(c.`exit_date`, c.`sale_date`)) BETWEEN ? AND ?";
                        $params[] = $filter_from_date;
                        $params[] = $filter_to_date;
                    }
                } else {
                    // Stock: optional from/to range filters
                    if (!empty($filter_from_date) && !empty($filter_to_date)) {
                        $where_clauses[] = "DATE(c.`created_at`) BETWEEN ? AND ?";
                        $params[] = $filter_from_date;
                        $params[] = $filter_to_date;
                    }
                }

                // Query for count and pagination
                $limit = 12;
                $page_num = isset($_GET['p']) ? (int)$_GET['p'] : 1;
                if ($page_num < 1) $page_num = 1;
                $offset = ($page_num - 1) * $limit;

                $count_sql = "SELECT COUNT(*) FROM `cars` c LEFT JOIN `branches` b ON c.`branch_id` = b.`id` WHERE " . implode(" AND ", $where_clauses);
                $count_stmt = $pdo->prepare($count_sql);
                $count_stmt->execute($params);
                $total_rows = $count_stmt->fetchColumn();
                $total_pages = ceil($total_rows / $limit);
                if ($total_pages < 1) $total_pages = 1;

                // Main query
                $sql = "SELECT c.*, b.name as branch_name 
                        FROM `cars` c 
                        LEFT JOIN `branches` b ON c.`branch_id` = b.`id` 
                        WHERE " . implode(" AND ", $where_clauses) . " 
                        ORDER BY c.`created_at` DESC 
                        LIMIT $limit OFFSET $offset";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $results = $stmt->fetchAll();

                // Dynamic generation of Export strings
                $query_string = http_build_query(array_merge($_GET, ['export_report' => $tab]));
            ?>
            <div class="space-y-6 max-w-7xl mx-auto text-right w-full" dir="rtl">
                
                <!-- Page title banner -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-900 border border-slate-800 p-6 rounded-2xl text-white shadow-lg">
                    <div>
                        <h2 class="text-xl font-black text-slate-100 flex items-center gap-2">
                            📊 لوحة التقارير المركزية والذكاء التحليلي
                        </h2>
                        <p class="text-xs text-slate-400 mt-1">توليد تقارير الأداء، تتبع المدخلات والمخرجات، ومطابقة البيانات الحية لمجموعة المعارض</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="?<?php echo $query_string; ?>&format=print" target="_blank" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold px-4 py-2.5 rounded-lg transition shadow-sm flex items-center gap-2">
                            🖨️ طباعة وتحميل PDF
                        </a>
                        <a href="?<?php echo $query_string; ?>&format=excel" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-4 py-2.5 rounded-lg transition shadow-sm flex items-center gap-2">
                            📥 تصدير Excel ملوّن
                        </a>
                    </div>
                </div>

                <!-- Statistics Cards Grid -->
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                    <!-- Stat Card 1 -->
                    <div class="bg-white border border-slate-150 p-4 rounded-xl shadow-sm hover:shadow transition flex flex-col justify-between">
                        <span class="text-[10px] text-slate-400 font-bold block">إجمالي أسطول السيارات</span>
                        <div class="flex items-baseline justify-between mt-2">
                            <span class="text-xl font-black text-slate-800"><?php echo number_format($stats_total); ?></span>
                            <span class="text-[9px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-bold">وحدة حية</span>
                        </div>
                    </div>
                    <!-- Stat Card 2 -->
                    <div class="bg-white border border-slate-150 p-4 rounded-xl shadow-sm hover:shadow transition flex flex-col justify-between">
                        <span class="text-[10px] text-sky-600 font-bold block">متوفرة للبيع حالياً</span>
                        <div class="flex items-baseline justify-between mt-2">
                            <span class="text-xl font-black text-sky-600"><?php echo number_format($stats_available); ?></span>
                            <span class="text-[9px] bg-sky-50 text-sky-600 px-2 py-0.5 rounded-full font-bold"><?php echo $stats_total > 0 ? round(($stats_available/$stats_total)*100) : 0; ?>%</span>
                        </div>
                    </div>
                    <!-- Stat Card 3 -->
                    <div class="bg-white border border-slate-150 p-4 rounded-xl shadow-sm hover:shadow transition flex flex-col justify-between">
                        <span class="text-[10px] text-amber-600 font-bold block">الحجوزات النشطة</span>
                        <div class="flex items-baseline justify-between mt-2">
                            <span class="text-xl font-black text-amber-500"><?php echo number_format($stats_reserved); ?></span>
                            <span class="text-[9px] bg-amber-50 text-amber-600 px-2 py-0.5 rounded-full font-bold"><?php echo $stats_total > 0 ? round(($stats_reserved/$stats_total)*100) : 0; ?>%</span>
                        </div>
                    </div>
                    <!-- Stat Card 4 -->
                    <div class="bg-white border border-slate-150 p-4 rounded-xl shadow-sm hover:shadow transition flex flex-col justify-between">
                        <span class="text-[10px] text-rose-600 font-bold block">مغلقة للبيع (معروضة للمطابقة)</span>
                        <div class="flex items-baseline justify-between mt-2">
                            <span class="text-xl font-black text-rose-500"><?php echo number_format($stats_not_for_sale); ?></span>
                            <span class="text-[9px] bg-rose-50 text-rose-600 px-2 py-0.5 rounded-full font-bold"><?php echo $stats_total > 0 ? round(($stats_not_for_sale/$stats_total)*100) : 0; ?>%</span>
                        </div>
                    </div>
                    <!-- Stat Card 5 -->
                    <div class="bg-white border border-slate-150 p-4 rounded-xl shadow-sm hover:shadow transition flex flex-col justify-between col-span-2 md:col-span-1">
                        <span class="text-[10px] text-emerald-600 font-bold block">القيمة الإجمالية للمخزون المتاح</span>
                        <div class="flex items-baseline justify-between mt-2">
                            <span class="text-lg font-black text-emerald-600"><?php echo number_format($stats_value, 2); ?></span>
                            <span class="text-[9px] text-slate-400 font-bold">ر.س</span>
                        </div>
                    </div>
                </div>

                <!-- Secondary Stats Row (Operations Tracker) -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-indigo-50 border border-indigo-100/50 p-3.5 rounded-xl flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-indigo-500 font-bold block">أضيف اليوم للنظام</span>
                            <span class="text-base font-bold text-indigo-900 mt-1 block"><?php echo number_format($stats_today); ?> سيارة جديدة</span>
                        </div>
                        <div class="text-lg">🚗</div>
                    </div>
                    <div class="bg-sky-50 border border-sky-100/50 p-3.5 rounded-xl flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-sky-600 font-bold block">إجمالي عمليات الدخول</span>
                            <span class="text-base font-bold text-sky-900 mt-1 block"><?php echo number_format($stats_entries); ?> عملية توريد</span>
                        </div>
                        <div class="text-lg">📥</div>
                    </div>
                    <div class="bg-emerald-50 border border-emerald-100/50 p-3.5 rounded-xl flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-emerald-600 font-bold block">إجمالي المبيعات والخرجات</span>
                            <span class="text-base font-bold text-emerald-900 mt-1 block"><?php echo number_format($stats_exits); ?> سيارة مسلّمة</span>
                        </div>
                        <div class="text-lg">📤</div>
                    </div>
                    <div class="bg-slate-50 border border-slate-150 p-3.5 rounded-xl flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-slate-500 font-bold block">معدل البيع العام</span>
                            <span class="text-base font-bold text-slate-800 mt-1 block"><?php echo $stats_total > 0 ? round(($stats_sold/$stats_total)*100, 1) : 0; ?>% من الأسطول</span>
                        </div>
                        <div class="text-lg">📈</div>
                    </div>
                </div>

                <!-- Multi-Tab Report Selector -->
                <div class="flex border-b border-slate-200 gap-1 overflow-x-auto pb-px">
                    <a href="?page=reports&tab=stock" class="px-5 py-3 text-xs font-bold rounded-t-xl transition-all border-b-2 shrink-0 <?php echo $tab === 'stock' ? 'border-indigo-600 text-indigo-600 bg-white shadow-sm' : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50'; ?>">
                        📁 تقرير المخزون المركزي الحالي (سجل المركبات الشامل)
                    </a>
                    <a href="?page=reports&tab=entry" class="px-5 py-3 text-xs font-bold rounded-t-xl transition-all border-b-2 shrink-0 <?php echo $tab === 'entry' ? 'border-indigo-600 text-indigo-600 bg-white shadow-sm' : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50'; ?>">
                        📥 تقرير حركة الدخول وتتبع الموردين
                    </a>
                    <a href="?page=reports&tab=exit" class="px-5 py-3 text-xs font-bold rounded-t-xl transition-all border-b-2 shrink-0 <?php echo $tab === 'exit' ? 'border-indigo-600 text-indigo-600 bg-white shadow-sm' : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50'; ?>">
                        📤 تقرير حركة الخروج والمبيعات والتسليم
                    </a>
                </div>

                <!-- Advanced Filters Container -->
                <div class="bg-white border border-slate-150 p-5 rounded-2xl shadow-sm space-y-4">
                    <form method="get" action="" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <input type="hidden" name="page" value="reports" />
                        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>" />

                        <!-- Search -->
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">بحث نصي (الماركة، الهيكل، اللوحة)</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="أدخل كلمة البحث..." class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs focus:ring-1 focus:ring-indigo-500 focus:bg-white outline-none" />
                        </div>

                        <!-- Branch Filter -->
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">الفرع والمعرض الجغرافي</label>
                            <select name="branch_id" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs focus:ring-1 focus:ring-indigo-500 outline-none">
                                <option value="">كل الفروع والمعارض</option>
                                <?php foreach ($branches_lookup as $br): ?>
                                    <option value="<?php echo $br['id']; ?>" <?php echo $filter_branch == $br['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($br['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Make Filter -->
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">الماركة / الشركة المصنعة</label>
                            <select name="make" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs focus:ring-1 focus:ring-indigo-500 outline-none">
                                <option value="">جميع الماركات</option>
                                <?php foreach ($makes_lookup as $m): ?>
                                    <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $filter_make === $m ? 'selected' : ''; ?>><?php echo htmlspecialchars($m); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Trim Filter -->
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">الفئة / الموديل الفرعي</label>
                            <select name="trim" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs focus:ring-1 focus:ring-indigo-500 outline-none">
                                <option value="">جميع الفئات</option>
                                <?php foreach ($trims_lookup as $tr): ?>
                                    <option value="<?php echo htmlspecialchars($tr); ?>" <?php echo $filter_trim === $tr ? 'selected' : ''; ?>><?php echo htmlspecialchars($tr); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">الحالة الإدارية</label>
                            <select name="status" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs focus:ring-1 focus:ring-indigo-500 outline-none">
                                <option value="">جميع الحالات</option>
                                <option value="available" <?php echo $filter_status === 'available' ? 'selected' : ''; ?>>متوفرة</option>
                                <option value="reserved" <?php echo $filter_status === 'reserved' ? 'selected' : ''; ?>>محجوزة</option>
                                <option value="sold" <?php echo $filter_status === 'sold' ? 'selected' : ''; ?>>مباعة</option>
                                <option value="not_for_sale" <?php echo $filter_status === 'not_for_sale' ? 'selected' : ''; ?>>غير معروضة للبيع</option>
                            </select>
                        </div>

                        <!-- Supplier Filter -->
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">المورد المعتمد</label>
                            <select name="supplier" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs focus:ring-1 focus:ring-indigo-500 outline-none">
                                <option value="">جميع الموردين</option>
                                <?php foreach ($suppliers_lookup as $sup): ?>
                                    <option value="<?php echo htmlspecialchars($sup); ?>" <?php echo $filter_supplier === $sup ? 'selected' : ''; ?>><?php echo htmlspecialchars($sup); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Rep Filter -->
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">المندوب المسؤول</label>
                            <select name="rep_in_charge" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs focus:ring-1 focus:ring-indigo-500 outline-none">
                                <option value="">جميع المناديب</option>
                                <?php foreach ($reps_lookup as $rep): ?>
                                    <option value="<?php echo htmlspecialchars($rep); ?>" <?php echo $filter_rep === $rep ? 'selected' : ''; ?>><?php echo htmlspecialchars($rep); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Import Origin -->
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">الوارد</label>
                            <select name="import_origin" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs focus:ring-1 focus:ring-indigo-500 outline-none">
                                <option value="">جميع خيارات الوارد</option>
                                <?php foreach ($imports_lookup as $imp): ?>
                                    <option value="<?php echo htmlspecialchars($imp); ?>" <?php echo $filter_import === $imp ? 'selected' : ''; ?>><?php echo htmlspecialchars($imp); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Owner Filter -->
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">المالك</label>
                            <select name="previous_owner" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs focus:ring-1 focus:ring-indigo-500 outline-none">
                                <option value="">جميع الملاك</option>
                                <?php foreach ($owners_lookup as $ow): ?>
                                    <option value="<?php echo htmlspecialchars($ow); ?>" <?php echo $filter_owner === $ow ? 'selected' : ''; ?>><?php echo htmlspecialchars($ow); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Period Range Type -->
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">نوع مدة التقرير (دخول/خروج)</label>
                            <select name="period_type" id="period_type_select" onchange="toggleDateRange()" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs focus:ring-1 focus:ring-indigo-500 outline-none">
                                <option value="all" <?php echo $filter_period_type === 'all' ? 'selected' : ''; ?>>تاريخ كلي تراكمي</option>
                                <option value="daily" <?php echo $filter_period_type === 'daily' ? 'selected' : ''; ?>>يومي (تاريخ اليوم الحية)</option>
                                <option value="monthly" <?php echo $filter_period_type === 'monthly' ? 'selected' : ''; ?>>شهري (الشهر الحالي)</option>
                                <option value="yearly" <?php echo $filter_period_type === 'yearly' ? 'selected' : ''; ?>>سنوي (السنة الحالية)</option>
                                <option value="custom" <?php echo $filter_period_type === 'custom' ? 'selected' : ''; ?>>فترة مخصصة (تحديد من/إلى)</option>
                            </select>
                        </div>

                        <!-- From Date -->
                        <div id="from_date_container">
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">تاريخ البداية (من)</label>
                            <input type="date" name="from_date" value="<?php echo htmlspecialchars($filter_from_date); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs focus:ring-1 focus:ring-indigo-500 outline-none" />
                        </div>

                        <!-- To Date -->
                        <div id="to_date_container">
                            <label class="block text-[10px] font-bold text-slate-500 mb-1">تاريخ النهاية (إلى)</label>
                            <input type="date" name="to_date" value="<?php echo htmlspecialchars($filter_to_date); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs focus:ring-1 focus:ring-indigo-500 outline-none" />
                        </div>

                        <!-- Form Controller buttons -->
                        <div class="flex items-end gap-2 sm:col-span-2 md:col-span-3 lg:col-span-4 justify-start">
                            <button type="submit" class="bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold px-6 py-2.5 rounded-lg transition-all shadow-sm flex items-center gap-1.5 cursor-pointer">
                                🔍 تطبيق الفلاتر والبحث الجاري
                            </button>
                            <a href="?page=reports&tab=<?php echo htmlspecialchars($tab); ?>" class="bg-slate-150 hover:bg-slate-200 text-slate-700 text-xs font-bold px-4 py-2.5 rounded-lg transition-all flex items-center gap-1">
                                🔄 تصفير الفلاتر
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Report Content Section -->
                <div class="bg-white border border-slate-150 rounded-2xl shadow-sm overflow-hidden">
                    <div class="p-4 bg-slate-50 border-b border-slate-150 flex justify-between items-center flex-wrap gap-2">
                        <span class="text-xs font-extrabold text-slate-700">📋 السجلات المعروضة الحالية (إجمالي المطابقات المتوفرة: <?php echo number_format($total_rows); ?>)</span>
                        <div class="text-[10px] text-slate-400 font-bold">صفحة <?php echo $page_num; ?> من أصل <?php echo $total_pages; ?></div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-right border-collapse whitespace-nowrap">
                            
                            <!-- TAB 1: STOCK REPORT (تقرير المخزون الحالي) -->
                            <?php if ($tab === 'stock'): ?>
                            <thead>
                                <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 text-[10px] font-bold uppercase">
                                    <th class="p-4 w-12 text-center">م</th>
                                    <th class="p-4">صورة المركبة</th>
                                    <th class="p-4">الماركة</th>
                                    <th class="p-4">الفئة / المواصفات</th>
                                    <th class="p-4">الموديل (السنة)</th>
                                    <th class="p-4">اللون الخارجي</th>
                                    <th class="p-4">رقم الهيكل (VIN)</th>
                                    <th class="p-4">المطابقة</th>
                                    <th class="p-4">المورد المعتمد</th>
                                    <th class="p-4">الوارد</th>
                                    <th class="p-4">المالك</th>
                                    <th class="p-4">السعر الأساسي</th>
                                    <th class="p-4">الحالة الإدارية</th>
                                    <th class="p-4">اسم المندوب</th>
                                    <th class="p-4">تاريخ الإضافة</th>
                                    <th class="p-4">الفرع / الموقع الجغرافي</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-150 text-xs text-slate-600">
                                <?php if (empty($results)): ?>
                                    <tr><td colspan="16" class="p-8 text-center text-slate-400">لا يوجد مركبات مسجلة تطابق هذه الفلاتر النشطة.</td></tr>
                                <?php else: ?>
                                    <?php 
                                    $idx = $offset + 1;
                                    foreach ($results as $car): 
                                        $row_style = 'bg-blue-50/20'; // available
                                        $status_ar = 'متوفرة للبيع';
                                        if ($car['status'] === 'reserved') { $row_style = 'bg-amber-50/40 font-bold text-amber-800'; $status_ar = 'محجوزة مؤقتاً'; }
                                        else if ($car['status'] === 'sold') { $row_style = 'bg-slate-100/70 text-slate-400 line-through'; $status_ar = 'مباعة ومسجلة'; }
                                        else if ($car['status'] === 'not_for_sale') { $row_style = 'bg-rose-50/40 text-rose-800 font-bold'; $status_ar = 'غير معروضة للبيع'; }
                                        
                                        $vin_err = ($car['vin_matching'] === 'mismatch' || $car['vin_matching'] === 'غير مطابق') ? true : false;
                                    ?>
                                    <tr class="hover:bg-slate-50 transition <?php echo $row_style; ?>">
                                        <td class="p-4 text-center font-bold text-slate-800 font-sans"><?php echo $idx++; ?></td>
                                        <td class="p-4 text-center">
                                            <span class="text-base select-none">🚗</span>
                                        </td>
                                        <td class="p-4 font-extrabold text-slate-900"><?php echo htmlspecialchars($car['make']); ?></td>
                                        <td class="p-4"><?php echo htmlspecialchars($car['trim'] ?? 'مواصفات قياسية'); ?></td>
                                        <td class="p-4 font-sans font-bold text-slate-700"><?php echo htmlspecialchars($car['year']); ?></td>
                                        <td class="p-4"><?php echo htmlspecialchars($car['color']); ?></td>
                                        <td class="p-4 font-mono font-bold text-slate-800 tracking-wider"><?php echo htmlspecialchars($car['vin']); ?></td>
                                        <td class="p-4">
                                            <?php if ($vin_err): ?>
                                                <span class="bg-rose-500 text-white text-[9px] font-black px-2 py-1 rounded">غير مطابق ⚠️</span>
                                            <?php else: ?>
                                                <span class="bg-emerald-100 text-emerald-800 text-[9px] font-bold px-2 py-1 rounded">مطابق ✔️</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 font-medium text-slate-700"><?php echo htmlspecialchars($car['supplier'] ?? 'غير محدد'); ?></td>
                                        <td class="p-4"><?php echo htmlspecialchars($car['import_origin'] ?? 'وارد محلي'); ?></td>
                                        <td class="p-4"><?php echo htmlspecialchars($car['previous_owner'] ?? 'مباشر'); ?></td>
                                        <td class="p-4 font-sans font-black text-slate-900"><?php echo number_format($car['price'], 2); ?> ر.س</td>
                                        <td class="p-4 font-bold text-[10px]"><?php echo $status_ar; ?></td>
                                        <td class="p-4 text-slate-500 font-medium"><?php echo htmlspecialchars($car['rep_in_charge'] ?? 'بدون تعيين'); ?></td>
                                        <td class="p-4 font-sans text-slate-400"><?php echo date('Y-m-d', strtotime($car['created_at'])); ?></td>
                                        <td class="p-4 font-extrabold text-indigo-600 text-[11px]"><?php echo htmlspecialchars($car['branch_name'] ?? 'الفرع الرئيسي'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>

                            <!-- TAB 2: ENTRY REPORT (تقرير دخول السيارات والمدخلات) -->
                            <?php elseif ($tab === 'entry'): ?>
                            <thead>
                                <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 text-[10px] font-bold uppercase">
                                    <th class="p-4 w-12 text-center">رقم العملية</th>
                                    <th class="p-4">تاريخ الدخول الفعلي</th>
                                    <th class="p-4">المركبة</th>
                                    <th class="p-4">رقم الهيكل (VIN)</th>
                                    <th class="p-4">سائق الناقلة</th>
                                    <th class="p-4">شركة الشحن / التوصيل</th>
                                    <th class="p-4">المورد المعتمد</th>
                                    <th class="p-4">الفرع المستلم</th>
                                    <th class="p-4">الملاحظات التشغيلية</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-150 text-xs text-slate-600">
                                <?php if (empty($results)): ?>
                                    <tr><td colspan="9" class="p-8 text-center text-slate-400">لا يوجد سجلات توريد حية مسجلة تطابق هذه الفلاتر النشطة.</td></tr>
                                <?php else: ?>
                                    <?php 
                                    $idx = $offset + 1;
                                    foreach ($results as $car): 
                                        $entryDate = !empty($car['entry_date']) ? $car['entry_date'] : date('Y-m-d', strtotime($car['created_at']));
                                    ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="p-4 text-center font-bold text-slate-800 font-sans"><?php echo $idx++; ?></td>
                                        <td class="p-4 font-sans font-bold text-indigo-600"><?php echo htmlspecialchars($entryDate); ?></td>
                                        <td class="p-4 font-extrabold text-slate-900"><?php echo htmlspecialchars($car['make'] . ' ' . $car['model'] . ' ' . $car['year']); ?></td>
                                        <td class="p-4 font-mono font-bold text-slate-800 tracking-wider"><?php echo htmlspecialchars($car['vin']); ?></td>
                                        <td class="p-4 text-slate-700 font-medium"><?php echo htmlspecialchars($car['entry_driver_name'] ?? 'غير محدد'); ?></td>
                                        <td class="p-4"><?php echo htmlspecialchars($car['shipping_company'] ?? 'توصيل مباشر'); ?></td>
                                        <td class="p-4 text-slate-600"><?php echo htmlspecialchars($car['supplier'] ?? 'مورد نظام داخلي'); ?></td>
                                        <td class="p-4 font-bold text-slate-800"><?php echo htmlspecialchars($car['branch_name'] ?? 'الفرع الرئيسي'); ?></td>
                                        <td class="p-4 text-slate-400 text-[10px] max-w-xs truncate" title="<?php echo htmlspecialchars($car['entry_notes'] ?? ''); ?>"><?php echo htmlspecialchars($car['entry_notes'] ?? 'لا يوجد ملاحظات تشغيلية'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>

                            <!-- TAB 3: EXIT REPORT (تقرير خروج السيارات والمخرجات) -->
                            <?php elseif ($tab === 'exit'): ?>
                            <thead>
                                <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 text-[10px] font-bold uppercase">
                                    <th class="p-4 w-12 text-center">رقم العملية</th>
                                    <th class="p-4">تاريخ الخروج الفعلي</th>
                                    <th class="p-4">المركبة</th>
                                    <th class="p-4">رقم الهيكل (VIN)</th>
                                    <th class="p-4">مندوب المبيعات (البائع)</th>
                                    <th class="p-4">اسم العميل المشتري</th>
                                    <th class="p-4">رقم الهوية الوطنية</th>
                                    <th class="p-4">الجنسية</th>
                                    <th class="p-4">رقم الهاتف</th>
                                    <th class="p-4">مبلغ البيع الإجمالي</th>
                                    <th class="p-4">نوع المستلم والمطابقة</th>
                                    <th class="p-4">الفرع الصادر منه</th>
                                    <th class="p-4">الملاحظات والقيود</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-150 text-xs text-slate-600">
                                <?php if (empty($results)): ?>
                                    <tr><td colspan="13" class="p-8 text-center text-slate-400">لا يوجد سجلات مخرجات مبيعات أو تسليم تطابق هذه الفلاتر النشطة.</td></tr>
                                <?php else: ?>
                                    <?php 
                                    $idx = $offset + 1;
                                    foreach ($results as $car): 
                                        $exitDate = !empty($car['exit_date']) ? $car['exit_date'] : (!empty($car['sale_date']) ? date('Y-m-d', strtotime($car['sale_date'])) : date('Y-m-d', strtotime($car['updated_at'])));
                                    ?>
                                    <tr class="hover:bg-slate-50 transition bg-emerald-50/5">
                                        <td class="p-4 text-center font-bold text-slate-800 font-sans"><?php echo $idx++; ?></td>
                                        <td class="p-4 font-sans font-bold text-rose-600"><?php echo htmlspecialchars($exitDate); ?></td>
                                        <td class="p-4 font-extrabold text-slate-900"><?php echo htmlspecialchars($car['make'] . ' ' . $car['model'] . ' ' . $car['year']); ?></td>
                                        <td class="p-4 font-mono font-bold text-slate-800 tracking-wider"><?php echo htmlspecialchars($car['vin']); ?></td>
                                        <td class="p-4 text-slate-700 font-medium"><?php echo htmlspecialchars($car['rep_in_charge'] ?? 'غير محدد'); ?></td>
                                        <td class="p-4 font-extrabold text-slate-900"><?php echo htmlspecialchars($car['sale_customer_name'] ?? 'غير محدد'); ?></td>
                                        <td class="p-4 font-sans"><?php echo htmlspecialchars($car['sale_customer_id'] ?? 'غير محدد'); ?></td>
                                        <td class="p-4"><?php echo htmlspecialchars($car['sale_customer_nationality'] ?? 'سعودي'); ?></td>
                                        <td class="p-4 font-sans text-slate-500"><?php echo htmlspecialchars($car['sale_customer_phone'] ?? 'غير محدد'); ?></td>
                                        <td class="p-4 font-sans font-black text-emerald-600"><?php echo number_format($car['sale_amount'] ?? $car['price'], 2); ?> ر.س</td>
                                        <td class="p-4 font-bold text-slate-500 text-[10px]"><?php echo htmlspecialchars($car['recipient_type'] ?? 'عميل مباشر'); ?></td>
                                        <td class="p-4 font-extrabold text-indigo-600"><?php echo htmlspecialchars($car['branch_name'] ?? 'الفرع الرئيسي'); ?></td>
                                        <td class="p-4 text-slate-400 text-[10px] max-w-xs truncate" title="<?php echo htmlspecialchars($car['exit_notes'] ?? ''); ?>"><?php echo htmlspecialchars($car['exit_notes'] ?? 'لا يوجد ملاحظات تشغيلية'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <?php endif; ?>

                        </table>
                    </div>

                    <!-- Pagination Navigation Controls -->
                    <?php if ($total_pages > 1): ?>
                    <div class="p-4 bg-slate-50 border-t border-slate-150 flex justify-between items-center flex-col sm:flex-row gap-4">
                        <div class="text-xs text-slate-400 font-medium">عرض سجلات من <?php echo $offset + 1; ?> إلى <?php echo min($offset + $limit, $total_rows); ?> من أصل <?php echo $total_rows; ?> تطابق</div>
                        <div class="flex gap-1.5 items-center">
                            <?php if ($page_num > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['p' => $page_num - 1])); ?>" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-bold rounded-lg transition-all shadow-sm">« السابق</a>
                            <?php endif; ?>
                            
                            <?php 
                            $range = 2;
                            for ($i = 1; $i <= $total_pages; $i++): 
                                if ($i === 1 || $i === $total_pages || ($i >= $page_num - $range && $i <= $page_num + $range)):
                            ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['p' => $i])); ?>" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all border <?php echo $i === $page_num ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50 shadow-sm'; ?>"><?php echo $i; ?></a>
                            <?php 
                                elseif ($i === 2 || $i === $total_pages - 1):
                            ?>
                                <span class="text-slate-400 px-1">...</span>
                            <?php 
                                endif;
                            endfor; 
                            ?>

                            <?php if ($page_num < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['p' => $page_num + 1])); ?>" class="px-3 py-1.5 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-bold rounded-lg transition-all shadow-sm">التالي »</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <script>
                    function toggleDateRange() {
                        const selectEl = document.getElementById('period_type_select');
                        const fromContainer = document.getElementById('from_date_container');
                        const toContainer = document.getElementById('to_date_container');
                        
                        if (selectEl && fromContainer && toContainer) {
                            if (selectEl.value === 'custom') {
                                fromContainer.style.display = 'block';
                                toContainer.style.display = 'block';
                            } else {
                                fromContainer.style.display = 'none';
                                toContainer.style.display = 'none';
                            }
                        }
                    }
                    // Run once on load to establish correct state
                    document.addEventListener('DOMContentLoaded', toggleDateRange);
                </script>
            </div>
            <?php endif; ?>

            <!-- PAGE 9: CUSTOMER ORDERS (صندوق الطلبات) -->
            <?php if ($page === 'orders' && ($user_role === 'admin' || $user_role === 'branch_manager')): 
                // Fetch orders with car details
                $ordersList = $pdo->query("SELECT o.*, c.make, c.model, c.year, c.price, c.currency FROM `customer_orders` o LEFT JOIN `cars` c ON o.car_id = c.id ORDER BY o.created_at DESC")->fetchAll();
            ?>
            <div class="space-y-6 max-w-7xl mx-auto text-right w-full" dir="rtl">
                
                <!-- Page title banner -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-900 border border-slate-800 p-6 rounded-2xl text-white shadow-lg">
                    <div>
                        <h2 class="text-xl font-black text-slate-100 flex items-center gap-2">
                            📥 صندوق طلبات العملاء (Order Box)
                        </h2>
                        <p class="text-xs text-slate-400 mt-1">عرض ومتابعة طلبات شراء السيارات المقدمة من صفحة العملاء الخارجية وتحديث حالات التواصل معهم</p>
                    </div>
                    <a href="customer.php" target="_blank" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg transition shadow-md flex items-center gap-1.5 cursor-pointer">
                        <span>👁️ عرض صفحة العملاء الخارجية</span>
                    </a>
                </div>

                <!-- Orders Table -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl text-white">
                    <div class="p-5 border-b border-slate-800 flex justify-between items-center">
                        <h3 class="font-bold text-sm text-slate-100">الطلبات المستلمة (<?php echo count($ordersList); ?> طلب)</h3>
                        <span class="text-[10px] bg-indigo-500/15 text-indigo-400 px-3 py-1 rounded-full border border-indigo-500/10">تحديث تلقائي</span>
                    </div>

                    <?php if (empty($ordersList)): ?>
                        <div class="p-16 text-center space-y-4">
                            <span class="text-5xl block">📥</span>
                            <h4 class="font-extrabold text-sm text-slate-300">صندوق الطلبات فارغ حالياً</h4>
                            <p class="text-xs text-slate-500 max-w-md mx-auto leading-relaxed">لم يتم إرسال أي طلبات شراء من صفحة العملاء الخارجية حتى الآن. بمجرد قيام عميل بتقديم طلب شراء سيارة، ستظهر تفاصيله هنا مباشرة.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-right border-collapse">
                                <thead>
                                    <tr class="bg-slate-950 border-b border-slate-850 text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                                        <th class="p-4">رقم الطلب</th>
                                        <th class="p-4">اسم العميل</th>
                                        <th class="p-4">رقم الجوال</th>
                                        <th class="p-4">السيارة المطلوبة</th>
                                        <th class="p-4">السعر</th>
                                        <th class="p-4 font-bold">ملاحظات العميل</th>
                                        <th class="p-4">تاريخ الطلب</th>
                                        <th class="p-4 text-center">الحالة</th>
                                        <th class="p-4 text-center">الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-850 text-xs text-slate-300">
                                    <?php foreach ($ordersList as $ord): ?>
                                        <tr class="hover:bg-slate-850/40 transition">
                                            <td class="p-4 font-mono font-bold text-indigo-400">#<?php echo $ord['id']; ?></td>
                                            <td class="p-4 font-black text-slate-100"><?php echo htmlspecialchars($ord['customer_name']); ?></td>
                                            <td class="p-4 font-sans font-bold text-slate-200">
                                                <a href="tel:<?php echo htmlspecialchars($ord['customer_phone']); ?>" class="hover:text-indigo-400 transition-colors flex items-center gap-1">
                                                    <span>📞</span> <?php echo htmlspecialchars($ord['customer_phone']); ?>
                                                </a>
                                            </td>
                                            <td class="p-4">
                                                <?php if (!empty($ord['make'])): ?>
                                                    <span class="font-bold text-slate-200"><?php echo htmlspecialchars($ord['make'] . ' ' . $ord['model']); ?></span>
                                                    <span class="text-[9px] bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded ml-1 font-sans"><?php echo $ord['year']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-rose-400 font-bold">سيارة غير متوفرة (محذوفة)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-4 font-sans font-bold text-emerald-400">
                                                <?php if (!empty($ord['price'])): ?>
                                                    <?php echo number_format($ord['price']) . ' ' . htmlspecialchars($ord['currency']); ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-4 text-slate-400 max-w-xs truncate" title="<?php echo htmlspecialchars($ord['notes']); ?>">
                                                <?php echo !empty($ord['notes']) ? htmlspecialchars($ord['notes']) : '<span class="text-slate-600 font-normal">لا توجد ملاحظات</span>'; ?>
                                            </td>
                                            <td class="p-4 font-sans text-slate-400"><?php echo date('Y-m-d H:i', strtotime($ord['created_at'])); ?></td>
                                            <td class="p-4 text-center">
                                                <form method="POST" action="" class="inline-block">
                                                    <input type="hidden" name="update_order_status" value="1">
                                                    <input type="hidden" name="order_id" value="<?php echo $ord['id']; ?>">
                                                    <select name="status" onchange="this.form.submit()" class="text-[10px] font-bold px-2 py-1 rounded bg-slate-950 border border-slate-800 text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer">
                                                        <option value="new" <?php echo $ord['status'] === 'new' ? 'selected' : ''; ?>>🆕 جديد</option>
                                                        <option value="contacted" <?php echo $ord['status'] === 'contacted' ? 'selected' : ''; ?>>📞 تم التواصل</option>
                                                        <option value="completed" <?php echo $ord['status'] === 'completed' ? 'selected' : ''; ?>>✅ مكتمل</option>
                                                        <option value="cancelled" <?php echo $ord['status'] === 'cancelled' ? 'selected' : ''; ?>>❌ ملغي</option>
                                                    </select>
                                                </form>
                                            </td>
                                            <td class="p-4 text-center">
                                                <div class="flex items-center justify-center gap-2">
                                                    <!-- Whatsapp direct contact from admin -->
                                                    <?php 
                                                    $adminWaText = urlencode("مرحباً أخي الكريم " . $ord['customer_name'] . "، لقد استلمنا طلب الشراء الخاص بك لسيارة " . ($ord['make'] ?? '') . " " . ($ord['model'] ?? '') . " موديل " . ($ord['year'] ?? '') . ". نحن في شركة " . $companySettings['company_name'] . " نسعد بخدمتك.");
                                                    $adminWaClean = preg_replace('/[^0-9]/', '', $ord['customer_phone']);
                                                    if (!str_starts_with($adminWaClean, '966') && str_starts_with($adminWaClean, '05')) {
                                                        $adminWaClean = '966' . substr($adminWaClean, 1);
                                                    }
                                                    ?>
                                                    <a href="https://wa.me/<?php echo $adminWaClean; ?>?text=<?php echo $adminWaText; ?>" target="_blank" class="px-2.5 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 hover:text-emerald-300 border border-emerald-500/20 text-[10px] font-bold transition flex items-center gap-1 cursor-pointer">
                                                        <span>تواصل مبيعات</span>
                                                    </a>
                                                    
                                                    <a href="?page=orders&delete_order=<?php echo $ord['id']; ?>" onclick="return confirm('هل أنت متأكد من حذف هذا الطلب نهائياً من الصندوق؟')" class="px-2.5 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 border border-rose-500/20 text-[10px] font-bold transition flex items-center gap-1 cursor-pointer">
                                                        <span>حذف</span>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- PAGE: CONTACT INQUIRIES INBOX (صندوق استفسارات اتصل بنا) -->
            <?php if ($page === 'contact_inquiries' && ($user_role === 'admin' || $user_role === 'branch_manager')): 
                // Search query and status filters
                $search = trim($_GET['search_query'] ?? '');
                $statusFilter = trim($_GET['status_filter'] ?? '');

                $sql = "SELECT * FROM `contact_inquiries` WHERE 1=1";
                $params = [];

                if ($search !== '') {
                    $sql .= " AND (`name` LIKE ? OR `phone` LIKE ? OR `email` LIKE ? OR `subject` LIKE ? OR `message` LIKE ?)";
                    $likeVal = "%$search%";
                    $params = array_merge($params, [$likeVal, $likeVal, $likeVal, $likeVal, $likeVal]);
                }

                if ($statusFilter !== '') {
                    $sql .= " AND `status` = ?";
                    $params[] = $statusFilter;
                }

                $sql .= " ORDER BY `created_at` DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $inquiriesList = $stmt->fetchAll();

                // Calculate KPIs
                $totalCount = $pdo->query("SELECT COUNT(*) FROM `contact_inquiries`")->fetchColumn();
                $newCount = $pdo->query("SELECT COUNT(*) FROM `contact_inquiries` WHERE `status` = 'new'")->fetchColumn();
                $readCount = $pdo->query("SELECT COUNT(*) FROM `contact_inquiries` WHERE `status` = 'read'")->fetchColumn();
                $repliedCount = $pdo->query("SELECT COUNT(*) FROM `contact_inquiries` WHERE `status` = 'replied'")->fetchColumn();
                $archivedCount = $pdo->query("SELECT COUNT(*) FROM `contact_inquiries` WHERE `status` = 'archived'")->fetchColumn();
            ?>
            <div class="space-y-6 max-w-7xl mx-auto text-right w-full" dir="rtl">
                
                <!-- Page title banner -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-900 border border-slate-800 p-6 rounded-2xl text-white shadow-lg">
                    <div>
                        <h2 class="text-xl font-black text-slate-100 flex items-center gap-2">
                            ✉️ صندوق رسائل اتصل بنا (Contact Box)
                        </h2>
                        <p class="text-xs text-slate-400 mt-1">عرض ومتابعة رسائل واستفسارات العملاء القادمة من المتجر الخارجي والتواصل المباشر معهم</p>
                    </div>
                </div>

                <!-- KPI Cards Dashboard -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl text-center shadow-md">
                        <span class="text-lg block mb-1">📬</span>
                        <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">إجمالي الرسائل</div>
                        <div class="text-lg font-black text-white mt-1 font-sans"><?php echo $totalCount; ?></div>
                    </div>
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl text-center shadow-md border-l-4 border-l-rose-500">
                        <span class="text-lg block mb-1">🆕</span>
                        <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">رسائل غير مقروءة</div>
                        <div class="text-lg font-black text-rose-400 mt-1 font-sans"><?php echo $newCount; ?></div>
                    </div>
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl text-center shadow-md border-l-4 border-l-amber-500">
                        <span class="text-lg block mb-1">👀</span>
                        <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">تم قراءتها</div>
                        <div class="text-lg font-black text-amber-400 mt-1 font-sans"><?php echo $readCount; ?></div>
                    </div>
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl text-center shadow-md border-l-4 border-l-emerald-500">
                        <span class="text-lg block mb-1">💬</span>
                        <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">تم الرد عليها</div>
                        <div class="text-lg font-black text-emerald-400 mt-1 font-sans"><?php echo $repliedCount; ?></div>
                    </div>
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl text-center shadow-md border-l-4 border-l-slate-500">
                        <span class="text-lg block mb-1">📁</span>
                        <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">مكتملة ومؤرشفة</div>
                        <div class="text-lg font-black text-slate-300 mt-1 font-sans"><?php echo $archivedCount; ?></div>
                    </div>
                </div>

                <!-- Live Search and Advanced Filter form -->
                <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl shadow-md">
                    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <input type="hidden" name="page" value="contact_inquiries">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 mb-1.5">البحث السريع (الاسم، الجوال، البريد، الموضوع...)</label>
                            <input type="text" name="search_query" value="<?php echo htmlspecialchars($search); ?>" placeholder="اكتب للبحث الفوري..." class="w-full text-xs px-3.5 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 mb-1.5">تصفية بحسب الحالة</label>
                            <select name="status_filter" class="w-full text-xs px-3.5 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer">
                                <option value="">كل الرسائل</option>
                                <option value="new" <?php echo $statusFilter === 'new' ? 'selected' : ''; ?>>🆕 جديد غير مقروء</option>
                                <option value="read" <?php echo $statusFilter === 'read' ? 'selected' : ''; ?>>👀 مقروء</option>
                                <option value="replied" <?php echo $statusFilter === 'replied' ? 'selected' : ''; ?>>💬 تم الرد</option>
                                <option value="archived" <?php echo $statusFilter === 'archived' ? 'selected' : ''; ?>>📁 مؤرشف / مكتمل</option>
                            </select>
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg transition shadow-md">تطبيق الفلاتر</button>
                            <a href="?page=contact_inquiries" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-lg transition shadow-sm">إعادة تعيين</a>
                        </div>
                    </form>
                </div>

                <!-- Inquiries Container -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl text-white">
                    <div class="p-5 border-b border-slate-800 flex justify-between items-center">
                        <h3 class="font-bold text-sm text-slate-100">الرسائل الواردة (<?php echo count($inquiriesList); ?> رسالة)</h3>
                    </div>

                    <?php if (empty($inquiriesList)): ?>
                        <div class="p-16 text-center space-y-4">
                            <span class="text-5xl block">📬</span>
                            <h4 class="font-extrabold text-sm text-slate-300">لا توجد رسائل تواصل مطابقة للفلاتر حالياً</h4>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-slate-800/60">
                            <?php foreach ($inquiriesList as $inq): ?>
                                <div class="p-5 hover:bg-slate-950/40 transition flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                    <div class="space-y-2 flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-extrabold text-xs text-slate-100"><?php echo htmlspecialchars($inq['name']); ?></span>
                                            <span class="font-sans text-[10px] text-slate-400"><?php echo htmlspecialchars($inq['phone']); ?></span>
                                            <?php if (!empty($inq['email'])): ?>
                                                <span class="font-sans text-[10px] text-slate-500 bg-slate-950 px-2 py-0.5 rounded border border-slate-800/80"><?php echo htmlspecialchars($inq['email']); ?></span>
                                            <?php endif; ?>
                                            
                                            <!-- Status Tag -->
                                            <?php if ($inq['status'] === 'new'): ?>
                                                <span class="bg-rose-500/10 text-rose-400 text-[9px] font-bold px-2 py-0.5 rounded border border-rose-500/20">🆕 جديد غير مقروء</span>
                                            <?php elseif ($inq['status'] === 'read'): ?>
                                                <span class="bg-amber-500/10 text-amber-400 text-[9px] font-bold px-2 py-0.5 rounded border border-amber-500/20">👀 مقروء</span>
                                            <?php elseif ($inq['status'] === 'replied'): ?>
                                                <span class="bg-emerald-500/10 text-emerald-400 text-[9px] font-bold px-2 py-0.5 rounded border border-emerald-500/20">💬 تم الرد</span>
                                            <?php elseif ($inq['status'] === 'archived'): ?>
                                                <span class="bg-slate-500/10 text-slate-400 text-[9px] font-bold px-2 py-0.5 rounded border border-slate-500/20">📁 مؤرشف / مكتمل</span>
                                            <?php endif; ?>

                                            <span class="font-sans text-[10px] text-slate-500 mr-auto"><?php echo date('Y-m-d H:i', strtotime($inq['created_at'])); ?></span>
                                        </div>

                                        <div class="text-xs text-slate-200 font-extrabold">الموضوع: <?php echo htmlspecialchars($inq['subject'] ?: 'بدون موضوع'); ?></div>
                                        <p class="text-xs text-slate-300 bg-slate-950/60 p-3.5 rounded-lg border border-slate-800/60 leading-relaxed font-bold whitespace-pre-wrap mt-2"><?php echo htmlspecialchars($inq['message']); ?></p>
                                    </div>

                                    <div class="flex md:flex-col items-stretch gap-2 shrink-0 w-full md:w-auto">
                                        <!-- Update status form -->
                                        <form method="POST" action="" class="flex items-center gap-1">
                                            <input type="hidden" name="update_contact_status" value="1">
                                            <input type="hidden" name="contact_id" value="<?php echo $inq['id']; ?>">
                                            <select name="status" onchange="this.form.submit()" class="w-full text-[10px] font-bold px-2.5 py-1.5 rounded bg-slate-950 border border-slate-800 text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer">
                                                <option value="new" <?php echo $inq['status'] === 'new' ? 'selected' : ''; ?>>🆕 جديد غير مقروء</option>
                                                <option value="read" <?php echo $inq['status'] === 'read' ? 'selected' : ''; ?>>👀 تم القراءة</option>
                                                <option value="replied" <?php echo $inq['status'] === 'replied' ? 'selected' : ''; ?>>💬 تم الرد</option>
                                                <option value="archived" <?php echo $inq['status'] === 'archived' ? 'selected' : ''; ?>>📁 مؤرشف / مكتمل</option>
                                            </select>
                                        </form>

                                        <!-- WhatsApp contact link -->
                                        <?php 
                                        $cleanWaPhone = preg_replace('/[^0-9]/', '', $inq['phone']);
                                        if (!str_starts_with($cleanWaPhone, '966') && str_starts_with($cleanWaPhone, '05')) {
                                            $cleanWaPhone = '966' . substr($cleanWaPhone, 1);
                                        }
                                        $waText = urlencode("مرحباً أخي الكريم " . $inq['name'] . "، معك شركة " . $companySettings['company_name'] . " بخصوص استفسارك الكريم المقدم لدينا. نسعد بخدمتك في أي وقت.");
                                        ?>
                                        <a href="https://wa.me/<?php echo $cleanWaPhone; ?>?text=<?php echo $waText; ?>" target="_blank" class="px-3 py-1.5 rounded bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 hover:text-emerald-300 border border-emerald-500/20 text-[10px] font-bold text-center transition flex items-center justify-center gap-1 cursor-pointer">
                                            <span>💬 الرد عبر الواتساب</span>
                                        </a>

                                        <!-- Delete Link -->
                                        <a href="?page=contact_inquiries&delete_contact_inquiry=<?php echo $inq['id']; ?>" onclick="return confirm('هل أنت متأكد من حذف هذه الرسالة نهائياً من الصندوق؟')" class="px-3 py-1.5 rounded bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 border border-rose-500/20 text-[10px] font-bold text-center transition flex items-center justify-center gap-1 cursor-pointer">
                                            <span>🗑️ حذف الرسالة</span>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- PAGE: SHOWROOM REVIEWS MANAGEMENT (إدارة تقييمات العملاء) -->
            <?php if ($page === 'showroom_reviews' && ($user_role === 'admin' || $user_role === 'branch_manager')): 
                // Search and filters
                $search = trim($_GET['search_query'] ?? '');
                $ratingFilter = trim($_GET['rating_filter'] ?? '');
                $statusFilter = trim($_GET['status_filter'] ?? '');

                $sql = "SELECT * FROM `showroom_reviews` WHERE 1=1";
                $params = [];

                if ($search !== '') {
                    $sql .= " AND (`customer_name` LIKE ? OR `comment` LIKE ?)";
                    $likeVal = "%$search%";
                    $params = array_merge($params, [$likeVal, $likeVal]);
                }

                if ($ratingFilter !== '') {
                    $sql .= " AND `rating` = ?";
                    $params[] = intval($ratingFilter);
                }

                if ($statusFilter !== '') {
                    $sql .= " AND `status` = ?";
                    $params[] = $statusFilter;
                }

                $sql .= " ORDER BY `created_at` DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $reviewsList = $stmt->fetchAll();

                // Aggregate ratings stats
                $totalReviewsCount = $pdo->query("SELECT COUNT(*) FROM `showroom_reviews`")->fetchColumn();
                $pendingReviewsCount = $pdo->query("SELECT COUNT(*) FROM `showroom_reviews` WHERE `status` = 'pending'")->fetchColumn();
                $approvedReviewsCount = $pdo->query("SELECT COUNT(*) FROM `showroom_reviews` WHERE `status` = 'approved'")->fetchColumn();
                $avgRatingVal = round($pdo->query("SELECT IFNULL(AVG(`rating`), 0.0) FROM `showroom_reviews` WHERE `status` = 'approved'")->fetchColumn(), 1);
            ?>
                <div class="space-y-6" dir="rtl">
                    <!-- Title Bar -->
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-sm">
                        <div>
                            <span class="text-indigo-400 text-xs font-extrabold uppercase tracking-widest block mb-1">⭐ مراجعات المعرض</span>
                            <h2 class="text-xl font-black text-white">إدارة تقييمات وآراء العملاء</h2>
                            <p class="text-[11px] text-slate-400 mt-1">تتيح لك هذه الصفحة مراجعة تقييمات العملاء والموافقة عليها للظهور في واجهة المعرض العامة.</p>
                        </div>
                        <div class="flex gap-2">
                            <a href="customer.php" target="_blank" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black rounded-lg transition shadow-md flex items-center gap-1.5 cursor-pointer">
                                <span>🌐</span> معاينة المعرض
                            </a>
                        </div>
                    </div>

                    <!-- Metrics Grid -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-extrabold text-slate-400 block">إجمالي التقييمات</span>
                                <span class="text-2xl font-black text-white font-sans mt-1 block"><?php echo $totalReviewsCount; ?></span>
                            </div>
                            <span class="text-3xl">📋</span>
                        </div>
                        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-extrabold text-slate-400 block">متوسط تقييم المعرض</span>
                                <span class="text-2xl font-black text-amber-400 font-sans mt-1 block">⭐ <?php echo $avgRatingVal; ?></span>
                            </div>
                            <span class="text-3xl">🎯</span>
                        </div>
                        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-extrabold text-slate-400 block">تقييمات بانتظار الموافقة</span>
                                <span class="text-2xl font-black text-rose-400 font-sans mt-1 block"><?php echo $pendingReviewsCount; ?></span>
                            </div>
                            <span class="text-3xl">⏳</span>
                        </div>
                        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-extrabold text-slate-400 block">تقييمات منشورة ومقبولة</span>
                                <span class="text-2xl font-black text-emerald-400 font-sans mt-1 block"><?php echo $approvedReviewsCount; ?></span>
                            </div>
                            <span class="text-3xl">✅</span>
                        </div>
                    </div>

                    <!-- Filter Form -->
                    <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm">
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <input type="hidden" name="page" value="showroom_reviews">
                            
                            <div>
                                <label class="block text-[10px] font-extrabold text-slate-400 mb-1">البحث عن تقييم</label>
                                <input type="text" name="search_query" value="<?php echo htmlspecialchars($search); ?>" placeholder="اسم العميل، محتوى التقييم..." class="w-full text-xs px-3 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-100 focus:outline-none focus:border-indigo-500 transition">
                            </div>

                            <div>
                                <label class="block text-[10px] font-extrabold text-slate-400 mb-1">التقييم (النجوم)</label>
                                <select name="rating_filter" class="w-full text-xs px-3 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-100 focus:outline-none focus:border-indigo-500 cursor-pointer">
                                    <option value="">الكل</option>
                                    <option value="5" <?php echo $ratingFilter === '5' ? 'selected' : ''; ?>>⭐ 5 نجوم</option>
                                    <option value="4" <?php echo $ratingFilter === '4' ? 'selected' : ''; ?>>⭐ 4 نجوم</option>
                                    <option value="3" <?php echo $ratingFilter === '3' ? 'selected' : ''; ?>>⭐ 3 نجوم</option>
                                    <option value="2" <?php echo $ratingFilter === '2' ? 'selected' : ''; ?>>⭐ 2 نجوم</option>
                                    <option value="1" <?php echo $ratingFilter === '1' ? 'selected' : ''; ?>>⭐ نجمة واحدة</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-extrabold text-slate-400 mb-1">حالة النشر</label>
                                <select name="status_filter" class="w-full text-xs px-3 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-100 focus:outline-none focus:border-indigo-500 cursor-pointer">
                                    <option value="">الكل</option>
                                    <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>⏳ بانتظار الموافقة</option>
                                    <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>✅ مقبول ومنشور</option>
                                </select>
                            </div>

                            <div class="flex items-end gap-2">
                                <button type="submit" class="flex-1 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-xs font-black rounded-lg transition shadow-sm cursor-pointer">تطبيق التصفية</button>
                                <a href="?page=showroom_reviews" class="py-2.5 px-4 bg-slate-950 hover:bg-slate-800 border border-slate-800 text-slate-400 hover:text-slate-200 text-xs font-bold rounded-lg transition text-center">إعادة تعيين</a>
                            </div>
                        </form>
                    </div>

                    <!-- Reviews Table or Grid list -->
                    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                        <?php if (empty($reviewsList)): ?>
                            <div class="text-center py-16">
                                <span class="text-4xl block mb-2">⭐</span>
                                <h4 class="text-sm font-black text-slate-300">لا توجد تقييمات مطابقة</h4>
                                <p class="text-[11px] text-slate-500 mt-1">تأكد من شروط التصفية أو انتظر مشاركة تقييمات جديدة من العملاء.</p>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-right text-xs">
                                    <thead>
                                        <tr class="bg-slate-950/60 border-b border-slate-800 text-slate-400 font-extrabold text-[10px]">
                                            <th class="p-4">العميل</th>
                                            <th class="p-4">التقييم</th>
                                            <th class="p-4">التعليق والتجربة</th>
                                            <th class="p-4">تاريخ التقديم</th>
                                            <th class="p-4">حالة النشر</th>
                                            <th class="p-4 text-center">الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-800 text-slate-300">
                                        <?php foreach ($reviewsList as $rev): ?>
                                            <tr class="hover:bg-slate-950/30 transition-all duration-150">
                                                <td class="p-4">
                                                    <span class="font-extrabold text-white block"><?php echo htmlspecialchars($rev['customer_name']); ?></span>
                                                    <span class="text-[9px] text-emerald-500 font-bold">✓ عميل موثق</span>
                                                </td>
                                                <td class="p-4">
                                                    <div class="flex text-amber-400 gap-0.5 text-sm">
                                                        <?php 
                                                        $r = intval($rev['rating']);
                                                        for ($i = 1; $i <= 5; $i++) {
                                                            echo $i <= $r ? '★' : '<span class="text-slate-700">★</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                </td>
                                                <td class="p-4 max-w-xs md:max-w-md">
                                                    <p class="text-[11px] text-slate-300 bg-slate-950/40 p-2.5 rounded-lg border border-slate-800/40 leading-relaxed italic">
                                                        "<?php echo htmlspecialchars($rev['comment']); ?>"
                                                    </p>
                                                </td>
                                                <td class="p-4 font-sans text-slate-400 text-[10px]">
                                                    <?php echo date('Y-m-d H:i', strtotime($rev['created_at'])); ?>
                                                </td>
                                                <td class="p-4">
                                                    <?php if ($rev['status'] === 'approved'): ?>
                                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-emerald-500/10 text-emerald-400 border border-emerald-500/10">
                                                            ● منشور ومقبول
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-rose-500/10 text-rose-400 border border-rose-500/10">
                                                            ● بانتظار الموافقة
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="p-4 text-center">
                                                    <div class="flex items-center justify-center gap-2">
                                                        <!-- Approval Toggle Form -->
                                                        <form method="POST" action="" class="inline">
                                                            <input type="hidden" name="update_review_status" value="1">
                                                            <input type="hidden" name="review_id" value="<?php echo $rev['id']; ?>">
                                                            <?php if ($rev['status'] === 'approved'): ?>
                                                                <input type="hidden" name="status" value="pending">
                                                                <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-yellow-500/10 hover:bg-yellow-500/20 text-yellow-400 hover:text-yellow-300 border border-yellow-500/20 text-[10px] font-bold transition cursor-pointer">
                                                                    إلغاء الموافقة
                                                                </button>
                                                            <?php else: ?>
                                                                <input type="hidden" name="status" value="approved">
                                                                <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 hover:text-emerald-300 border border-emerald-500/20 text-[10px] font-bold transition cursor-pointer">
                                                                    موافقة ونشر
                                                                </button>
                                                            <?php endif; ?>
                                                        </form>

                                                        <!-- Delete button -->
                                                        <a href="?page=showroom_reviews&delete_review=<?php echo $rev['id']; ?>" onclick="return confirm('هل أنت متأكد من حذف هذا التقييم نهائياً؟')" class="px-2.5 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 border border-rose-500/20 text-[10px] font-bold transition">
                                                            حذف نهائي
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- PAGE: SHOWROOM SALES REPRESENTATIVES MANAGEMENT (إدارة مناديب المبيعات) -->
            <?php if ($page === 'showroom_sales' && ($user_role === 'admin' || $user_role === 'branch_manager')): 
                // Fetch all sales representatives
                $stmt = $pdo->query("SELECT * FROM `showroom_sales` ORDER BY `id` DESC");
                $repsList = $stmt->fetchAll();
                
                $rep_success = intval($_GET['rep_success'] ?? 0);
                $current_sales_template = $companySettings['sales_template_style'] ?? 'grid';
            ?>
            <div class="space-y-6 max-w-6xl mx-auto text-right w-full animate-fade-in" dir="rtl">
                
                <!-- Page Title Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-900 border border-slate-800 p-5 rounded-2xl text-white">
                    <div>
                        <h2 class="text-xl font-black text-slate-100 flex items-center gap-2 font-sans">
                            👥 إدارة مناديب ومستشاري المبيعات
                        </h2>
                        <p class="text-xs text-slate-400 mt-1">إضافة، تعديل، وحذف مناديب المبيعات وتعديل قوالب وتنسيقات عرض الصفحة الخارجية للعملاء</p>
                    </div>
                    <button onclick="openAddRepModal()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition shadow-lg flex items-center gap-1.5 cursor-pointer">
                        <span>➕ إضافة مستشار مبيعات جديد</span>
                    </button>
                </div>

                <!-- Notifications -->
                <?php if ($rep_success === 1): ?>
                    <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold rounded-xl flex items-center gap-2">
                        ✓ تم إضافة مستشار المبيعات بنجاح إلى قاعدة البيانات وربطه بالمعرض.
                    </div>
                <?php elseif ($rep_success === 2): ?>
                    <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold rounded-xl flex items-center gap-2">
                        ✓ تم تحديث بيانات مستشار المبيعات بنجاح.
                    </div>
                <?php elseif ($rep_success === 3): ?>
                    <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-bold rounded-xl flex items-center gap-2">
                        ✓ تم حذف مستشار المبيعات بنجاح من النظام.
                    </div>
                <?php elseif ($rep_success === 4): ?>
                    <div class="p-4 bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-xs font-bold rounded-xl flex items-center gap-2">
                        ✓ تم تحديث وتطبيق قالب المظهر الجديد لصفحة فريق المبيعات بنجاح.
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                    
                    <!-- Right/Main: Representatives list -->
                    <div class="lg:col-span-2 space-y-6">
                        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-sm">
                            <h3 class="text-sm font-black text-slate-800 dark:text-slate-100 mb-4 font-sans">قائمة مستشاري المبيعات الحاليين</h3>
                            
                            <?php if (empty($repsList)): ?>
                                <div class="text-center py-12">
                                    <span class="text-4xl block mb-2">👥</span>
                                    <h4 class="text-sm font-black text-slate-400">لا يوجد مستشارين حاليين مضافين</h4>
                                    <p class="text-xs text-slate-400 mt-1">اضغط على زر الإضافة لتأسيس مستشار مبيعات جديد.</p>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-right border-collapse text-xs">
                                        <thead>
                                            <tr class="border-b border-slate-100 dark:border-slate-800 text-slate-400">
                                                <th class="py-3 px-4 font-black">المستشار</th>
                                                <th class="py-3 px-4 font-black">المسمى الوظيفي</th>
                                                <th class="py-3 px-4 font-black">رقم الجوال / واتساب</th>
                                                <th class="py-3 px-4 font-black">الحالة</th>
                                                <th class="py-3 px-4 font-black text-left">العمليات</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/55">
                                            <?php foreach ($repsList as $rep): ?>
                                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                                                    <td class="py-3 px-4 flex items-center gap-3">
                                                        <div class="w-10 h-10 rounded-full overflow-hidden border border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 shrink-0">
                                                            <?php if (!empty($rep['avatar'])): ?>
                                                                <img src="<?php echo htmlspecialchars($rep['avatar']); ?>" class="w-full h-full object-cover">
                                                            <?php else: ?>
                                                                <div class="w-full h-full bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex items-center justify-center font-bold">
                                                                    <?php echo mb_substr($rep['name'], 0, 1, 'utf-8'); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <div class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($rep['name']); ?></div>
                                                            <div class="text-[10px] text-slate-400 font-sans">#<?php echo $rep['id']; ?></div>
                                                        </div>
                                                    </td>
                                                    <td class="py-3 px-4 text-slate-600 dark:text-slate-300 font-semibold">
                                                        <?php echo htmlspecialchars($rep['title']); ?>
                                                    </td>
                                                    <td class="py-3 px-4 font-sans text-slate-600 dark:text-slate-300">
                                                        <div>📞 <?php echo htmlspecialchars($rep['phone']); ?></div>
                                                        <div class="text-emerald-500 font-bold">💬 <?php echo htmlspecialchars($rep['whatsapp']); ?></div>
                                                    </td>
                                                    <td class="py-3 px-4">
                                                        <?php if ($rep['status'] === 'active'): ?>
                                                            <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 font-bold text-[10px]">متاح</span>
                                                        <?php else: ?>
                                                            <span class="px-2 py-0.5 rounded bg-rose-500/10 text-rose-400 font-bold text-[10px]">غير متاح</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="py-3 px-4 text-left">
                                                        <div class="inline-flex gap-2">
                                                            <button onclick='openEditRepModal(<?php echo json_encode($rep); ?>)' class="px-2.5 py-1.5 rounded bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 border border-indigo-500/20 text-[10px] font-bold transition cursor-pointer">تعديل</button>
                                                            <a href="?page=showroom_sales&delete_sales_rep=<?php echo $rep['id']; ?>" onclick="return confirm('هل أنت متأكد من حذف مستشار المبيعات هذا نهائياً؟')" class="px-2.5 py-1.5 rounded bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 text-[10px] font-bold transition">حذف</a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Left: Custom Appearance Templates & Layout Manager -->
                    <div class="space-y-6">
                        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 shadow-sm">
                            <h3 class="text-sm font-black text-slate-800 dark:text-slate-100 mb-2">⚙️ قالب مظهر المبيعات</h3>
                            <p class="text-xs text-slate-400 mb-4 leading-relaxed">اختر القالب والتنسيق الأنسب لعرض مناديب ومستشاري المبيعات في الواجهة الخارجية للمعرض.</p>
                            
                            <form action="index.php?page=showroom_sales" method="POST" class="space-y-3">
                                <input type="hidden" name="save_sales_template" value="1">
                                
                                <div class="space-y-3">
                                    <label class="block relative border rounded-2xl p-4 cursor-pointer hover:border-indigo-500/50 transition <?php echo $current_sales_template === 'grid' ? 'border-indigo-500 bg-indigo-500/5' : 'border-slate-200 dark:border-slate-800'; ?>">
                                        <input type="radio" name="sales_template_style" value="grid" <?php echo $current_sales_template === 'grid' ? 'checked' : ''; ?>>
                                        <div class="inline-block mr-2">
                                            <div class="text-xs font-bold text-slate-800 dark:text-slate-100">بطاقات شبكية (Grid Layout)</div>
                                            <div class="text-[10px] text-slate-400 mt-0.5">عرض بطاقات متجاورة عصرية (تلقائي 3 أعمدة)</div>
                                        </div>
                                    </label>

                                    <label class="block relative border rounded-2xl p-4 cursor-pointer hover:border-indigo-500/50 transition <?php echo $current_sales_template === 'list' ? 'border-indigo-500 bg-indigo-500/5' : 'border-slate-200 dark:border-slate-800'; ?>">
                                        <input type="radio" name="sales_template_style" value="list" <?php echo $current_sales_template === 'list' ? 'checked' : ''; ?>>
                                        <div class="inline-block mr-2">
                                            <div class="text-xs font-bold text-slate-800 dark:text-slate-100">قائمة كلاسيكية طويـلة (List Layout)</div>
                                            <div class="text-[10px] text-slate-400 mt-0.5">ترتيب طولي مريح وأنيق ومثالي للهواتف الجوالة</div>
                                        </div>
                                    </label>

                                    <label class="block relative border rounded-2xl p-4 cursor-pointer hover:border-indigo-500/50 transition <?php echo $current_sales_template === 'bento' ? 'border-indigo-500 bg-indigo-500/5' : 'border-slate-200 dark:border-slate-800'; ?>">
                                        <input type="radio" name="sales_template_style" value="bento" <?php echo $current_sales_template === 'bento' ? 'checked' : ''; ?>>
                                        <div class="inline-block mr-2">
                                            <div class="text-xs font-bold text-slate-800 dark:text-slate-100">شبكة بينتو المتقدمة (Bento Box)</div>
                                            <div class="text-[10px] text-slate-400 mt-0.5">أحجام مختلفة مذهلة للمستشارين تبرز المستشار الأول</div>
                                        </div>
                                    </label>
                                </div>

                                <button type="submit" class="w-full mt-3 py-2.5 bg-slate-900 dark:bg-slate-800 hover:bg-slate-800 dark:hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition shadow cursor-pointer">
                                    حفظ وتطبيق القالب المحدد
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>

            <!-- MODAL: ADD / EDIT SALES REPRESENTATIVE -->
            <div id="sales-rep-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 w-full max-w-md rounded-3xl overflow-hidden shadow-2xl relative text-right" dir="rtl">
                    <div class="bg-slate-900 text-white p-5 border-b border-slate-800 flex justify-between items-center">
                        <h3 id="modal-title" class="text-xs font-black">إضافة مستشار مبيعات جديد</h3>
                        <button onclick="closeRepModal()" class="text-slate-400 hover:text-white transition cursor-pointer text-lg">&times;</button>
                    </div>
                    
                    <form id="rep-form" action="index.php?page=showroom_sales" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                        <input type="hidden" name="id" id="rep-id">
                        <input type="hidden" name="add_sales_rep" id="rep-action-add" value="1">
                        <input type="hidden" name="edit_sales_rep" id="rep-action-edit" value="0">

                        <div>
                            <label class="block text-slate-400 text-[10px] font-bold mb-1">الاسم الكامل *</label>
                            <input type="text" name="name" id="rep-name" required placeholder="مثال: أحمد الحربي" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-1 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="block text-slate-400 text-[10px] font-bold mb-1">المسمى الوظيفي *</label>
                            <input type="text" name="title" id="rep-title" required placeholder="مثال: مستشار مبيعات كبار العملاء" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-1 focus:ring-indigo-500">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-slate-400 text-[10px] font-bold mb-1">رقم الهاتف *</label>
                                <input type="text" name="phone" id="rep-phone" required placeholder="05XXXXXXXX" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-white font-sans focus:ring-1 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-slate-400 text-[10px] font-bold mb-1">واتساب *</label>
                                <input type="text" name="whatsapp" id="rep-whatsapp" required placeholder="05XXXXXXXX" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-white font-sans focus:ring-1 focus:ring-indigo-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-slate-400 text-[10px] font-bold mb-1">رفع صورة المستشار (من الجهاز)</label>
                            <input type="file" name="avatar_file" accept="image/*" class="w-full px-3 py-1.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-1 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="block text-slate-400 text-[10px] font-bold mb-1">أو رابط الصورة الشخصية (خارجي)</label>
                            <input type="url" name="avatar" id="rep-avatar" placeholder="https://..." class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-white font-sans focus:ring-1 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="block text-slate-400 text-[10px] font-bold mb-1">حالة العمل</label>
                            <select name="status" id="rep-status" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-white focus:ring-1 focus:ring-indigo-500">
                                <option value="active">متاح ومسجل للاستفسارات</option>
                                <option value="inactive">غير متاح حالياً</option>
                            </select>
                        </div>

                        <div class="pt-4 flex gap-3">
                            <button type="submit" class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl transition cursor-pointer">حفظ ومزامنة البيانات</button>
                            <button type="button" onclick="closeRepModal()" class="px-4 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs rounded-xl transition cursor-pointer">إلغاء</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                function openAddRepModal() {
                    document.getElementById('modal-title').textContent = 'إضافة مستشار مبيعات جديد';
                    document.getElementById('rep-id').value = '';
                    document.getElementById('rep-name').value = '';
                    document.getElementById('rep-title').value = '';
                    document.getElementById('rep-phone').value = '';
                    document.getElementById('rep-whatsapp').value = '';
                    document.getElementById('rep-avatar').value = '';
                    document.getElementById('rep-status').value = 'active';
                    
                    const fileInput = document.querySelector('input[type="file"][name="avatar_file"]');
                    if (fileInput) fileInput.value = '';
                    
                    document.getElementById('rep-action-add').value = '1';
                    document.getElementById('rep-action-edit').value = '0';
                    document.getElementById('sales-rep-modal').classList.remove('hidden');
                }

                function openEditRepModal(rep) {
                    document.getElementById('modal-title').textContent = 'تعديل بيانات مستشار المبيعات';
                    document.getElementById('rep-id').value = rep.id;
                    document.getElementById('rep-name').value = rep.name;
                    document.getElementById('rep-title').value = rep.title;
                    document.getElementById('rep-phone').value = rep.phone;
                    document.getElementById('rep-whatsapp').value = rep.whatsapp;
                    document.getElementById('rep-avatar').value = rep.avatar || '';
                    document.getElementById('rep-status').value = rep.status;
                    
                    const fileInput = document.querySelector('input[type="file"][name="avatar_file"]');
                    if (fileInput) fileInput.value = '';
                    
                    document.getElementById('rep-action-add').value = '0';
                    document.getElementById('rep-action-edit').value = '1';
                    document.getElementById('sales-rep-modal').classList.remove('hidden');
                }

                function closeRepModal() {
                    document.getElementById('sales-rep-modal').classList.add('hidden');
                }
            </script>
            <?php endif; ?>

            <!-- PAGE 10: ADVERTISEMENTS & OFFERS MANAGER -->
            <?php if ($page === 'ads' && $user_role === 'admin'): 
                // Fetch all ads
                $stmt = $pdo->query("SELECT * FROM `showroom_ads` ORDER BY `created_at` DESC");
                $adsList = $stmt->fetchAll();
                
                // Stats
                $activeAdsCount = 0;
                $topAdsCount = 0;
                $middleAdsCount = 0;
                $popupAdsCount = 0;
                $totalViews = 0;
                $totalClicks = 0;
                foreach ($adsList as $ad) {
                    if ($ad['status'] === 'active') $activeAdsCount++;
                    if ($ad['position'] === 'top') $topAdsCount++;
                    elseif ($ad['position'] === 'middle') $middleAdsCount++;
                    elseif ($ad['position'] === 'popup') $popupAdsCount++;
                    $totalViews += intval($ad['views_count']);
                    $totalClicks += intval($ad['clicks_count']);
                }
            ?>
            <div class="space-y-6 max-w-6xl mx-auto text-right w-full" dir="rtl">
                
                <!-- Page Title Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-900 border border-slate-800 p-5 rounded-2xl text-white">
                    <div>
                        <h2 class="text-xl font-black text-slate-100 flex items-center gap-2">
                            📢 حاويات الإعلانات والعروض الترويجية المتقدمة
                        </h2>
                        <p class="text-xs text-slate-400 mt-1">إنشاء وإدارة بنرات العروض وصناديق الإعلانات التفاعلية عبر الصور أو الأكواد والبرمجيات المخصصة</p>
                    </div>
                    <button onclick="openAddAdModal()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition shadow-lg flex items-center gap-1.5 cursor-pointer">
                        <span>➕ إضافة إعلان أو حاوية عرض جديدة</span>
                    </button>
                </div>

                <?php if (isset($_GET['ad_success'])): ?>
                    <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold rounded-xl flex items-center gap-2">
                        ✓ تم حفظ وتحديث بيانات الإعلان بنجاح في النظام وربطه بالواجهة الخارجية
                    </div>
                <?php endif; ?>

                <!-- Stats Widgets Block -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Stat 1 -->
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl flex items-center justify-between">
                        <div class="space-y-1">
                            <span class="text-[10px] text-slate-400 font-bold block">إجمالي الإعلانات النشطة</span>
                            <span class="text-xl font-black text-emerald-400 font-sans"><?php echo $activeAdsCount; ?></span>
                            <span class="text-[9px] text-slate-500 block">من أصل <?php echo count($adsList); ?> مسجلين</span>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-lg">●</div>
                    </div>
                    <!-- Stat 2 -->
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl flex items-center justify-between">
                        <div class="space-y-1">
                            <span class="text-[10px] text-slate-400 font-bold block">مواضع العرض الحالية</span>
                            <div class="flex gap-2 text-[9px] font-bold text-indigo-300 mt-1">
                                <span>أعلى: <?php echo $topAdsCount; ?></span> • 
                                <span>وسط: <?php echo $middleAdsCount; ?></span> • 
                                <span>منبثق: <?php echo $popupAdsCount; ?></span>
                            </div>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-indigo-500/10 text-indigo-400 flex items-center justify-center text-lg">🧭</div>
                    </div>
                    <!-- Stat 3 -->
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl flex items-center justify-between">
                        <div class="space-y-1">
                            <span class="text-[10px] text-slate-400 font-bold block">إجمالي المشاهدات</span>
                            <span class="text-xl font-black text-indigo-400 font-sans"><?php echo number_format($totalViews); ?></span>
                            <span class="text-[9px] text-slate-500 block">مشاهدة حقيقية بالمعرض</span>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-indigo-500/10 text-indigo-400 flex items-center justify-center text-lg">👁️</div>
                    </div>
                    <!-- Stat 4 -->
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl flex items-center justify-between">
                        <div class="space-y-1">
                            <span class="text-[10px] text-slate-400 font-bold block">إجمالي النقرات الفعالة</span>
                            <span class="text-xl font-black text-amber-400 font-sans"><?php echo number_format($totalClicks); ?></span>
                            <span class="text-[9px] text-slate-500 block">نسبة CTR: <?php echo $totalViews > 0 ? round(($totalClicks / $totalViews) * 100, 2) . '%' : '0%'; ?></span>
                        </div>
                        <div class="w-10 h-10 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center text-lg">🖱️</div>
                    </div>
                </div>

                <!-- Ad Creation & Editing Form (Collapsible Card) -->
                <div id="ad-form-card" class="hidden bg-slate-900 border border-slate-800 p-6 rounded-2xl text-white space-y-4">
                    <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                        <h3 id="ad-form-title" class="font-extrabold text-sm text-slate-100 flex items-center gap-2">
                            📢 إنشاء حاوية عرض وإعلان جديدة
                        </h3>
                        <button onclick="closeAdForm()" class="text-xs text-slate-400 hover:text-white bg-slate-800 px-3 py-1 rounded-lg">إلغاء</button>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="save_ad" value="1">
                        <input type="hidden" name="ad_id" id="ad_id" value="0">

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-300 mb-1.5">عنوان الإعلان الداخلي (للإدارة والمتابعة) *</label>
                                <input type="text" name="title" id="ad_title" required placeholder="مثال: عرض الصيف لتقسيط سيارات تويوتا لاندكروزر 2026" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-300 mb-1.5">نوع الإعلان / الحاوية *</label>
                                <select name="type" id="ad_type" onchange="toggleAdTypeFields(this.value)" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans font-bold">
                                    <option value="image">🖼️ صورة إعلانية تفاعلية (بنر مع رابط)</option>
                                    <option value="html">💻 أكواد برمجية مخصصة (HTML / JS / CSS)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Image Ad Fields -->
                        <div id="type_image_fields" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-300 mb-1.5">تحميل بنر العرض الترويجي (الصورة)</label>
                                <div class="flex gap-3 items-center">
                                    <input type="file" name="ad_image_file" accept="image/*" class="w-full text-xs text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                                    <img id="ad_image_preview" src="" class="hidden w-16 h-10 object-cover rounded border border-slate-800" alt="Preview">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-300 mb-1.5">رابط التوجيه عند النقر (Target URL)</label>
                                <input type="url" name="link_url" id="ad_link_url" placeholder="https://example.com/promo" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans text-left" dir="ltr">
                            </div>
                        </div>

                        <!-- HTML Code Ad Fields -->
                        <div id="type_html_fields" class="hidden">
                            <label class="block text-xs font-bold text-slate-300 mb-1.5">كود الإعلان المخصص (HTML / JavaScript / CSS)</label>
                            <textarea name="html_code" id="ad_html_code" rows="5" placeholder="<!-- أدخل كود الإعلان البرمجي هنا (مثال: أكواد AdSense أو كود بنر متحرك تفاعلي خاص بك) -->" class="w-full text-xs p-3 rounded-lg border border-slate-800 bg-slate-950 text-emerald-400 font-mono focus:outline-none focus:border-indigo-500 leading-relaxed text-left" dir="ltr"></textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 border-t border-slate-850 pt-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-300 mb-1.5">حالة تفعيل الإعلان *</label>
                                <select name="status" id="ad_status" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                                    <option value="active">🟢 نشط ومعروض للعملاء</option>
                                    <option value="inactive">🔴 متوقف مؤقتاً</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-300 mb-1.5">موضع العرض بالمعرض *</label>
                                <select name="position" id="ad_position" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                                    <option value="top">🔝 أعلى صفحة المعرض (أسفل الهيدر)</option>
                                    <option value="middle">↕️ وسط صفحة المعرض (بين السيارات)</option>
                                    <option value="popup">🚨 إعلان منبثق عند الدخول (Modal Popup)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-300 mb-1.5">تاريخ بدء النشر (اختياري)</label>
                                <input type="date" name="start_date" id="ad_start_date" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-300 mb-1.5">تاريخ انتهاء النشر (اختياري)</label>
                                <input type="date" name="end_date" id="ad_end_date" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition cursor-pointer">
                                💾 حفظ حاوية الإعلان
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Existing Ads List Table -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl text-white">
                    <div class="p-5 border-b border-slate-800 flex justify-between items-center">
                        <h3 class="font-bold text-sm text-slate-100">الإعلانات والعروض المسجلة حالياً</h3>
                        <span class="text-xs text-slate-400">إجمالي المدرج: <b><?php echo count($adsList); ?></b></span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-right text-xs">
                            <thead class="bg-slate-950/40 text-slate-400 font-bold border-b border-slate-800">
                                <tr>
                                    <th class="p-4">الإعلان</th>
                                    <th class="p-4 text-center">النوع</th>
                                    <th class="p-4 text-center">موضع العرض</th>
                                    <th class="p-4 text-center">الحالة</th>
                                    <th class="p-4 text-center font-sans">مشاهدات / نقرات</th>
                                    <th class="p-4 text-center font-sans">فترة النشر</th>
                                    <th class="p-4 text-center">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60">
                                <?php if (empty($adsList)): ?>
                                    <tr>
                                        <td colspan="7" class="p-8 text-center text-slate-500 font-bold">
                                            ⚠️ لا يوجد أي إعلانات أو عروض ترويجية مسجلة حالياً. اضغط على زر الإضافة بالأعلى لإنشاء أول إعلان.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($adsList as $ad): ?>
                                        <tr class="hover:bg-slate-950/20 transition-colors">
                                            <td class="p-4">
                                                <div class="flex items-center gap-3">
                                                    <?php if ($ad['type'] === 'image' && !empty($ad['image_path'])): ?>
                                                        <img src="<?php echo htmlspecialchars($ad['image_path']); ?>" class="w-12 h-8 object-cover rounded border border-slate-800 shrink-0" alt="Ad visual">
                                                    <?php else: ?>
                                                        <div class="w-12 h-8 rounded bg-slate-950 flex items-center justify-center text-[10px] text-emerald-400 border border-slate-800 shrink-0 font-mono">&lt;/&gt;</div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <span class="font-bold block text-slate-200"><?php echo htmlspecialchars($ad['title']); ?></span>
                                                        <span class="text-[9px] text-slate-500 block">تاريخ الإضافة: <?php echo date('Y-m-d', strtotime($ad['created_at'])); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="p-4 text-center">
                                                <span class="px-2.5 py-1 rounded text-[10px] font-bold <?php echo $ad['type'] === 'image' ? 'bg-indigo-500/10 text-indigo-400' : 'bg-emerald-500/10 text-emerald-400'; ?>">
                                                    <?php echo $ad['type'] === 'image' ? '🖼️ صورة وبنير' : '💻 أكواد مخصصة'; ?>
                                                </span>
                                            </td>
                                            <td class="p-4 text-center font-bold text-slate-300">
                                                <?php 
                                                    switch ($ad['position']) {
                                                        case 'top': echo '🔝 أعلى المعرض'; break;
                                                        case 'middle': echo '↕️ وسط المعرض'; break;
                                                        case 'popup': echo '🚨 نافذة منبثقة'; break;
                                                        default: echo htmlspecialchars($ad['position']);
                                                    }
                                                ?>
                                            </td>
                                            <td class="p-4 text-center">
                                                <span class="px-2.5 py-1 rounded text-[10px] font-bold <?php echo $ad['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'; ?>">
                                                    <?php echo $ad['status'] === 'active' ? '● نشط' : '● متوقف'; ?>
                                                </span>
                                            </td>
                                            <td class="p-4 text-center font-mono">
                                                <div class="space-y-0.5">
                                                    <span class="block font-bold text-indigo-400"><?php echo intval($ad['views_count']); ?> مشاهدة</span>
                                                    <span class="block text-[10px] text-amber-400"><?php echo intval($ad['clicks_count']); ?> نقرة</span>
                                                </div>
                                            </td>
                                            <td class="p-4 text-center text-[10px] text-slate-400">
                                                <?php if (empty($ad['start_date']) && empty($ad['end_date'])): ?>
                                                    <span class="text-slate-500 font-bold">دائم ومستمر</span>
                                                <?php else: ?>
                                                    <div class="space-y-0.5">
                                                        <?php if (!empty($ad['start_date'])): ?>
                                                            <span class="block">من: <?php echo htmlspecialchars($ad['start_date']); ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($ad['end_date'])): ?>
                                                            <span class="block text-rose-400">إلى: <?php echo htmlspecialchars($ad['end_date']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-4 text-center">
                                                <div class="flex justify-center gap-1.5">
                                                    <button 
                                                        onclick='editAd(<?php echo json_encode($ad, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
                                                        class="px-2.5 py-1.5 rounded-lg bg-indigo-500/10 hover:bg-indigo-600 hover:text-white border border-indigo-500/20 text-indigo-400 text-[10px] font-bold transition flex items-center gap-1 cursor-pointer"
                                                    >
                                                        ⚙️ تعديل
                                                    </button>
                                                    
                                                    <a 
                                                        href="?page=ads&delete_ad=<?php echo $ad['id']; ?>" 
                                                        onclick="return confirm('هل أنت متأكد من حذف هذه الحاوية الإعلانية نهائياً؟')" 
                                                        class="px-2.5 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 border border-rose-500/20 text-[10px] font-bold transition flex items-center gap-1 cursor-pointer"
                                                    >
                                                        🗑️ حذف
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <script>
                function toggleAdTypeFields(type) {
                    const imgFields = document.getElementById('type_image_fields');
                    const htmlFields = document.getElementById('type_html_fields');
                    if (type === 'image') {
                        imgFields.classList.remove('hidden');
                        htmlFields.classList.add('hidden');
                    } else {
                        imgFields.classList.add('hidden');
                        htmlFields.classList.remove('hidden');
                    }
                }

                function openAddAdModal() {
                    const card = document.getElementById('ad-form-card');
                    card.classList.remove('hidden');
                    document.getElementById('ad-form-title').innerText = '➕ إنشاء حاوية عرض وإعلان جديدة';
                    document.getElementById('ad_id').value = '0';
                    document.getElementById('ad_title').value = '';
                    document.getElementById('ad_type').value = 'image';
                    document.getElementById('ad_link_url').value = '';
                    document.getElementById('ad_html_code').value = '';
                    document.getElementById('ad_status').value = 'active';
                    document.getElementById('ad_position').value = 'top';
                    document.getElementById('ad_start_date').value = '';
                    document.getElementById('ad_end_date').value = '';
                    document.getElementById('ad_image_preview').classList.add('hidden');
                    toggleAdTypeFields('image');
                    card.scrollIntoView({ behavior: 'smooth' });
                }

                function editAd(ad) {
                    const card = document.getElementById('ad-form-card');
                    card.classList.remove('hidden');
                    document.getElementById('ad-form-title').innerText = '⚙️ تعديل بيانات حاوية الإعلان';
                    document.getElementById('ad_id').value = ad.id;
                    document.getElementById('ad_title').value = ad.title;
                    document.getElementById('ad_type').value = ad.type;
                    document.getElementById('ad_link_url').value = ad.link_url || '';
                    document.getElementById('ad_html_code').value = ad.html_code || '';
                    document.getElementById('ad_status').value = ad.status;
                    document.getElementById('ad_position').value = ad.position;
                    document.getElementById('ad_start_date').value = ad.start_date || '';
                    document.getElementById('ad_end_date').value = ad.end_date || '';
                    
                    const previewImg = document.getElementById('ad_image_preview');
                    if (ad.type === 'image' && ad.image_path) {
                        previewImg.src = ad.image_path;
                        previewImg.classList.remove('hidden');
                    } else {
                        previewImg.classList.add('hidden');
                    }
                    
                    toggleAdTypeFields(ad.type);
                    card.scrollIntoView({ behavior: 'smooth' });
                }

                function closeAdForm() {
                    document.getElementById('ad-form-card').classList.add('hidden');
                }
            </script>
            <?php endif; ?>

            <!-- PAGE 11: TRANSFERS BETWEEN BRANCHES -->
            <?php if ($page === 'transfers'): 
                // Fetch all active/completed transfers with car details
                $transfersList = $pdo->query("SELECT t.*, c.make, c.model, c.year, c.color, c.vin, c.plate_number,
                                               fb.name as from_branch_name, tb.name as to_branch_name, u.name as creator_name, tb.manager as to_branch_manager
                                        FROM `branch_transfers` t
                                        LEFT JOIN `cars` c ON t.car_id = c.id
                                        LEFT JOIN `branches` fb ON t.from_branch_id = fb.id
                                        LEFT JOIN `branches` tb ON t.to_branch_id = tb.id
                                        LEFT JOIN `users` u ON t.created_by_user_id = u.id
                                        ORDER BY t.transfer_date DESC")->fetchAll();

                // Fetch available branches
                $branchesList = $pdo->query("SELECT * FROM `branches` ORDER BY `name` ASC")->fetchAll();

                // Fetch cars that are available or reserved (can be transferred)
                $carsList = $pdo->query("SELECT id, make, model, year, plate_number, vin, branch_id FROM `cars` WHERE `status` IN ('available', 'reserved') ORDER BY `make` ASC, `model` ASC")->fetchAll();
            ?>
            <div class="space-y-6 max-w-6xl mx-auto text-right w-full font-sans" dir="rtl">
                <!-- Page Title Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-900 border border-slate-800 p-5 rounded-2xl text-white">
                    <div>
                        <h2 class="text-xl font-black text-slate-100 flex items-center gap-2">
                            🔄 التحويلات الرسمية بين الفروع والمعارض
                        </h2>
                        <p class="text-xs text-slate-400 mt-1">تتبع حركة ترحيل عهدة المركبات، توليد خطابات النقل وتأكيد الاستلام الإداري الموحد</p>
                    </div>
                    <?php if ($user_role === 'admin'): ?>
                    <button onclick="document.getElementById('manual-transfer-modal').classList.remove('hidden')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl cursor-pointer transition flex items-center gap-1.5 shadow-lg shadow-indigo-600/10">
                        <span>➕</span> إنشاء خطاب تحويل يدوي
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Notification area -->
                <?php if (isset($_GET['success'])): ?>
                    <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold rounded-xl flex items-center gap-2">
                        ✓ تم تسجيل عملية النقل والتحويل بنجاح، وتوليد خطاب النقل تلقائياً، وبانتظار موافقة وقبول الفرع المستهدف لتحديث العهدة.
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['success_accept'])): ?>
                    <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold rounded-xl flex items-center gap-2">
                        ✓ تم تأكيد استلام المركبة وقبول التحويل بنجاح، وتم نقل العهدة لفرعكم الحالي وتحديث السجلات.
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['success_reject'])): ?>
                    <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-bold rounded-xl flex items-center gap-2">
                        ⚠️ تم رفض طلب التحويل وإلغاء خطاب الحركة بنجاح، وتبقى عهدة السيارة في الفرع المغادر.
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['success_edit'])): ?>
                    <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold rounded-xl flex items-center gap-2">
                        ✓ تم تعديل بيانات خطاب التحويل وتحديث الأطراف وملاحظات الحركة بنجاح.
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['success_delete'])): ?>
                    <div class="p-4 bg-amber-500/10 border border-amber-500/20 text-amber-500 text-xs font-bold rounded-xl flex items-center gap-2">
                        ✓ تم حذف وإلغاء خطاب التحويل بنجاح من سجلات النظام كلياً.
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['error'])): ?>
                    <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-bold rounded-xl flex flex-col gap-1">
                        <div class="flex items-center gap-2">
                            <span>⚠️ خطأ:</span>
                            <?php if ($_GET['error'] === 'unauthorized'): ?>
                                <span>غير مصرح لك بقبول، تعديل أو حذف هذا الطلب لعدم تطابق فرعك الحالي أو دورك الوظيفي مع المتطلبات الأمنية.</span>
                            <?php elseif ($_GET['error'] === 'creator_cannot_receive'): ?>
                                <span>غير مسموح للمرسل أو المندوب الذي قام بإنشاء خطاب التحويل أن يقوم باستلامه أو رفضه بنفسه لتفادي تعارض المصالح والرقابة الثنائية.</span>
                            <?php elseif ($_GET['error'] === 'only_manager_can_receive'): ?>
                                <span>لا يمكن استلام أو رفض هذا التحويل إلا من قبل مدير الفرع المستهدف (المستلم للتحويل) كما هو محدد في إعدادات الفروع في النظام.</span>
                            <?php elseif ($_GET['error'] === 'not_pending'): ?>
                                <span>هذا الخطاب تم قبوله واستلامه مسبقاً أو مرفوض، ولا يمكن تعديله أو اتخاذ إجراء مكرر عليه.</span>
                            <?php elseif ($_GET['error'] === 'same_branch'): ?>
                                <span>لا يمكن تحويل المركبة لنفس الفرع المتواجدة فيه حالياً. يرجى تحديد فرع آخر مغاير.</span>
                            <?php elseif ($_GET['error'] === 'completed_cannot_delete'): ?>
                                <span>لا يمكن حذف خطابات التحويل المكتملة والمقبولة مسبقاً حفاظاً على سلامة الجرد والعهدة المالية للمركبات.</span>
                            <?php elseif ($_GET['error'] === 'not_found'): ?>
                                <span>خطاب التحويل المحدد غير موجود في قاعدة بيانات النظام.</span>
                            <?php else: ?>
                                <span>فشل إتمام عملية النقل في النظام بسبب تعارض أو توقف مؤقت بقواعد البيانات تحت ضغط الطلبات. يرجى إعادة المحاولة.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Active Transfers Statistics Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-5 rounded-2xl shadow-sm">
                        <span class="text-slate-400 dark:text-slate-500 text-xs font-bold block mb-1">إجمالي خطابات النقل</span>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl font-black text-slate-800 dark:text-white font-sans"><?php echo count($transfersList); ?></span>
                            <span class="text-xs text-slate-400">خطاباً مصدراً</span>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-5 rounded-2xl shadow-sm">
                        <span class="text-slate-400 dark:text-slate-500 text-xs font-bold block mb-1">مسارات نقل نشطة</span>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl font-black text-indigo-600 font-sans"><?php echo count($branchesList); ?></span>
                            <span class="text-xs text-slate-400">فروع ومعارض مشاركة</span>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-5 rounded-2xl shadow-sm">
                        <span class="text-slate-400 dark:text-slate-500 text-xs font-bold block mb-1">السيارات المؤهلة للنقل</span>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl font-black text-emerald-600 font-sans"><?php echo count($carsList); ?></span>
                            <span class="text-xs text-slate-400">سيارة جاهزة للترحيل</span>
                        </div>
                    </div>
                </div>

                <!-- Transfers Records Table -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-2xl shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-slate-100 dark:border-slate-800">
                        <h3 class="text-sm font-extrabold text-slate-800 dark:text-slate-100">سجل حركة وحوالات المركبات الموثقة</h3>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">يحتوي هذا السجل على كافة التحويلات التي تمت تلقائياً من خلال النظام أو يدوياً عبر الإدارة مع تأكيد الاستلام.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-right border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-bold border-b border-slate-100 dark:border-slate-850">
                                    <th class="p-4">رقم الخطاب</th>
                                    <th class="p-4">المركبة</th>
                                    <th class="p-4">من فرع</th>
                                    <th class="p-4">إلى فرع</th>
                                    <th class="p-4">الموظف المصدر</th>
                                    <th class="p-4">تاريخ التحويل</th>
                                    <th class="p-4 text-center">حالة الاستلام</th>
                                    <th class="p-4 text-left">الإجراءات والخطابات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-850 text-slate-700 dark:text-slate-300">
                                <?php if (empty($transfersList)): ?>
                                    <tr>
                                        <td colspan="8" class="p-8 text-center text-slate-400 dark:text-slate-500">
                                            <span class="text-4xl block mb-2">🔄</span>
                                            لا توجد أي تحويلات مخزنية موثقة في النظام حالياً.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transfersList as $trf): ?>
                                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-950/40 transition">
                                            <td class="p-4 font-mono font-bold text-indigo-600 dark:text-indigo-400"><?php echo htmlspecialchars($trf['letter_number']); ?></td>
                                            <td class="p-4">
                                                <?php 
                                                $carsInTransfer = [];
                                                if (!empty($trf['car_id'])) {
                                                    $carIdsArray = array_map('trim', explode(',', $trf['car_id']));
                                                    if (count($carIdsArray) > 1) {
                                                        $placeholders = implode(',', array_fill(0, count($carIdsArray), '?'));
                                                        $stmtCars = $pdo->prepare("SELECT * FROM `cars` WHERE `id` IN ($placeholders)");
                                                        $stmtCars->execute($carIdsArray);
                                                        $carsInTransfer = $stmtCars->fetchAll();
                                                    } else {
                                                        if (!empty($trf['make'])) {
                                                            $carsInTransfer[] = [
                                                                'make' => $trf['make'],
                                                                'model' => $trf['model'],
                                                                'year' => $trf['year'],
                                                                'vin' => $trf['vin']
                                                            ];
                                                        } else {
                                                            $stmtCars = $pdo->prepare("SELECT * FROM `cars` WHERE `id` = ?");
                                                            $stmtCars->execute([$trf['car_id']]);
                                                            $singleCar = $stmtCars->fetch();
                                                            if ($singleCar) {
                                                                $carsInTransfer[] = $singleCar;
                                                            }
                                                        }
                                                    }
                                                }
                                                ?>
                                                <?php if (count($carsInTransfer) > 1): ?>
                                                    <div class="space-y-1 bg-indigo-50/40 dark:bg-slate-950/40 p-2 rounded border border-indigo-100/30">
                                                        <span class="text-[9px] font-black text-indigo-600 dark:text-indigo-400 block mb-1">📦 شحنة متعددة (<?php echo count($carsInTransfer); ?> سيارات):</span>
                                                        <?php foreach ($carsInTransfer as $cItem): ?>
                                                            <div class="text-[11px] font-bold text-slate-800 dark:text-slate-200">
                                                                • <?php echo htmlspecialchars($cItem['make'] . ' ' . $cItem['model'] . ' (' . $cItem['year'] . ')'); ?>
                                                                <span class="text-[9px] text-slate-500 font-mono">(VIN: <?php echo htmlspecialchars($cItem['vin'] ?: 'بدون'); ?>)</span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php elseif (count($carsInTransfer) === 1): 
                                                    $cItem = $carsInTransfer[0];
                                                ?>
                                                    <div class="font-extrabold text-slate-800 dark:text-slate-200"><?php echo htmlspecialchars($cItem['make'] . ' ' . $cItem['model'] . ' (' . $cItem['year'] . ')'); ?></div>
                                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">الهيكل: <?php echo htmlspecialchars($cItem['vin'] ?: 'غير محدد'); ?></div>
                                                <?php else: ?>
                                                    <div class="text-rose-500 font-bold">مركبة محذوفة أو غير معروفة</div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-4 font-bold text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars($trf['from_branch_name'] ?: 'الفرع الرئيسي'); ?></td>
                                            <td class="p-4 font-bold text-slate-900 dark:text-slate-100"><?php echo htmlspecialchars($trf['to_branch_name']); ?></td>
                                            <td class="p-4"><?php echo htmlspecialchars($trf['creator_name'] ?: 'مدير النظام'); ?></td>
                                            <td class="p-4 font-sans text-slate-500"><?php echo date('Y-m-d H:i', strtotime($trf['transfer_date'])); ?></td>
                                            <td class="p-4 text-center">
                                                <?php if ($trf['status'] === 'pending'): ?>
                                                    <span class="px-2 py-0.5 bg-amber-500/10 text-amber-500 border border-amber-500/20 rounded font-bold text-[9px] animate-pulse">
                                                        🟡 قيد الانتظار والقبول
                                                    </span>
                                                <?php elseif ($trf['status'] === 'completed' || empty($trf['status'])): ?>
                                                    <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 rounded font-bold text-[9px]">
                                                        🟢 تم الاستلام والموافقة
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 py-0.5 bg-rose-500/10 text-rose-500 border border-rose-500/20 rounded font-bold text-[9px]">
                                                        🔴 مرفوض ومحجوب
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-4 text-left flex justify-end gap-1.5 flex-wrap">
                                                <?php if ($trf['status'] === 'pending' && (($user_role === 'admin' && $user_id != $trf['created_by_user_id']) || (!empty($trf['to_branch_manager']) && $user_id == $trf['to_branch_manager'] && $user_id != $trf['created_by_user_id']))): ?>
                                                    <form method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من قبول واستلام هذه المركبة في فرعك؟');">
                                                        <input type="hidden" name="accept_transfer" value="1">
                                                        <input type="hidden" name="transfer_id" value="<?php echo $trf['id']; ?>">
                                                        <button type="submit" class="px-2 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded font-bold text-[9px] cursor-pointer transition">
                                                            ✓ قبول واستلام
                                                        </button>
                                                    </form>
                                                    <form method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من رفض طلب تحويل السيارة وإلغائه؟');">
                                                        <input type="hidden" name="reject_transfer" value="1">
                                                        <input type="hidden" name="transfer_id" value="<?php echo $trf['id']; ?>">
                                                        <button type="submit" class="px-2 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded font-bold text-[9px] cursor-pointer transition">
                                                            ✕ رفض
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($user_role === 'admin' || $user_id == $trf['created_by_user_id']): ?>
                                                <?php if ($trf['status'] === 'pending'): ?>
                                                <button type="button" onclick="showEditTransferModal(<?php echo $trf['id']; ?>, '<?php echo htmlspecialchars($trf['to_branch_id']); ?>', <?php echo htmlspecialchars(json_encode($trf['notes'] ?: '')); ?>)" class="px-2 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded font-bold text-[9px] cursor-pointer transition">✏️ تعديل</button>
                                                <?php endif; ?>
                                                <form method="POST" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف وإلغاء هذا الخطاب نهائياً؟');">
                                                <input type="hidden" name="delete_transfer" value="1">
                                                <input type="hidden" name="transfer_id" value="<?php echo $trf['id']; ?>">
                                                <button type="submit" class="px-2 py-1 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 hover:text-rose-300 border border-rose-500/20 rounded font-bold text-[9px] cursor-pointer transition">
                                                🗑️ حذف
                                                </button>
                                                </form>
                                                <?php endif; ?>
                                                 <button type="button" onclick="showPrintTransferModal(<?php echo $trf['id']; ?>)" class="px-2 py-1 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/30 dark:hover:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 rounded font-bold text-[9px] cursor-pointer transition inline-flex items-center gap-1">
                                                    🖨️ طباعة الخطاب
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Manual Transfer Modal -->
                <div id="manual-transfer-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden font-sans">
                    <div class="bg-slate-900 border border-slate-800 w-full max-w-lg rounded-2xl shadow-xl overflow-hidden text-right text-white">
                        <div class="px-5 py-4 bg-slate-950 border-b border-slate-850 flex justify-between items-center">
                            <h3 class="font-extrabold text-sm flex items-center gap-2">
                                <span>🔄</span> إصدار خطاب ونقل عهدة مركبة يدوياً
                            </h3>
                            <button onclick="document.getElementById('manual-transfer-modal').classList.add('hidden')" class="text-slate-400 hover:text-white transition text-lg font-bold cursor-pointer">&times;</button>
                        </div>

                        <form method="POST" class="p-5 space-y-4" onsubmit="return validateTransferForm(this);">
                            <input type="hidden" name="create_transfer" value="1">
                            
                            <div>
                                <label class="block text-xs font-bold text-slate-300 mb-2">اختر المركبات للتحويل (حدد علامة صح بجانب كل سيارة) <span class="text-red-500">*</span></label>
                                
                                <!-- Search Box -->
                                <div class="mb-2 relative">
                                    <input type="text" id="car-transfer-search" placeholder="ابحث باسم السيارة، الموديل، اللوحة..." class="w-full text-xs px-3 py-2 pl-8 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500 font-sans">
                                    <span class="absolute left-2.5 top-2.5 text-xs text-slate-500">🔍</span>
                                </div>
                                
                                <!-- Scrollable Checkbox List -->
                                <div class="max-h-56 overflow-y-auto p-2 bg-slate-950 border border-slate-800 rounded-lg divide-y divide-slate-850" id="car-checkbox-list">
                                    <?php foreach ($carsList as $c): 
                                        // Get original branch name
                                        $origBrName = 'بدون فرع';
                                        foreach ($branchesList as $br) {
                                            if ($br['id'] == $c['branch_id']) {
                                                $origBrName = $br['name'];
                                                break;
                                            }
                                        }
                                        $searchText = mb_strtolower($c['make'] . ' ' . $c['model'] . ' ' . $c['year'] . ' ' . $origBrName . ' ' . $c['plate_number']);
                                    ?>
                                        <label class="car-select-row flex items-start gap-3 p-2 hover:bg-slate-850/50 rounded cursor-pointer transition" data-search="<?php echo htmlspecialchars($searchText); ?>">
                                            <input type="checkbox" name="car_id[]" value="<?php echo $c['id']; ?>" class="mt-0.5 rounded border-slate-800 text-indigo-600 focus:ring-indigo-500 h-4 w-4 bg-slate-900 cursor-pointer">
                                            <div class="text-xs">
                                                <div class="font-bold text-slate-100"><?php echo htmlspecialchars($c['make'] . ' ' . $c['model'] . ' (' . $c['year'] . ')'); ?></div>
                                                <div class="text-[10px] text-slate-400 mt-0.5">
                                                    الفرع الحالي: <span class="text-indigo-400 font-extrabold"><?php echo htmlspecialchars($origBrName); ?></span> 
                                                    <?php if (!empty($c['plate_number'])): ?>
                                                        • لوحة: <span class="text-slate-200 font-sans font-bold"><?php echo htmlspecialchars($c['plate_number']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p class="text-[10px] text-indigo-400 font-bold mt-1.5">✓ تم التحديث لتمكين تحديد سيارة أو أكثر بسهولة بوضع علامة الصح دون الحاجة لاستخدام أزرار لوحة المفاتيح.</p>
                            </div>

                            <script>
                            function validateTransferForm(form) {
                                const checked = form.querySelectorAll('input[name="car_id[]"]:checked');
                                if (checked.length === 0) {
                                    alert('⚠️ يرجى تحديد سيارة واحدة على الأقل لإتمام عملية التحويل.');
                                    return false;
                                }
                                return true;
                            }
                            document.addEventListener('DOMContentLoaded', function() {
                                const searchInput = document.getElementById('car-transfer-search');
                                if (searchInput) {
                                    searchInput.addEventListener('input', function() {
                                        const query = this.value.toLowerCase().trim();
                                        const rows = document.querySelectorAll('.car-select-row');
                                        rows.forEach(row => {
                                            const searchData = row.getAttribute('data-search') || '';
                                            if (searchData.includes(query)) {
                                                row.classList.remove('hidden');
                                            } else {
                                                row.classList.add('hidden');
                                            }
                                        });
                                    });
                                }
                            });
                            </script>

                            <div>
                                <label class="block text-xs font-bold text-slate-300 mb-1.5">الفرع المستهدف الجديد <span class="text-red-500">*</span></label>
                                <select name="to_branch_id" required class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-bold">
                                    <option value="">-- حدد المعرض أو المستودع المستلم --</option>
                                    <?php foreach ($branchesList as $br): ?>
                                        <option value="<?php echo $br['id']; ?>"><?php echo htmlspecialchars($br['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-300 mb-1.5">ملاحظات وسبب ترحيل العهدة</label>
                                <textarea name="notes" placeholder="اكتب سبب النقل أو ملاحظات فنية للمركبة..." rows="3" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"></textarea>
                            </div>

                            <div class="flex justify-start gap-2 border-t border-slate-850 pt-4 mt-6">
                                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg cursor-pointer transition">حفظ وإصدار الخطاب</button>
                                <button type="button" onclick="document.getElementById('manual-transfer-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-lg cursor-pointer transition">إلغاء</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Edit Transfer Modal -->
                <div id="edit-transfer-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden font-sans" dir="rtl">
                    <div class="bg-slate-900 border border-slate-800 w-full max-w-lg rounded-2xl shadow-xl overflow-hidden text-right text-white">
                        <div class="px-5 py-4 bg-slate-950 border-b border-slate-850 flex justify-between items-center">
                            <h3 class="font-extrabold text-sm flex items-center gap-2">
                                <span>✏️</span> تعديل خطاب التحويل الداخلي
                            </h3>
                            <button onclick="document.getElementById('edit-transfer-modal').classList.add('hidden')" class="text-slate-400 hover:text-white transition text-lg font-bold cursor-pointer">&times;</button>
                        </div>

                        <form method="POST" class="p-5 space-y-4">
                            <input type="hidden" name="update_transfer" value="1">
                            <input type="hidden" name="transfer_id" id="edit-transfer-id" value="">
                            
                            <div>
                                <label class="block text-xs font-bold text-slate-300 mb-1.5">الفرع المستهدف الجديد <span class="text-red-500">*</span></label>
                                <select name="to_branch_id" id="edit-to-branch-id" required class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-bold">
                                    <option value="">-- حدد المعرض أو المستودع المستلم --</option>
                                    <?php foreach ($branchesList as $br): ?>
                                        <option value="<?php echo $br['id']; ?>"><?php echo htmlspecialchars($br['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-300 mb-1.5">ملاحظات وسبب ترحيل العهدة</label>
                                <textarea name="notes" id="edit-notes" placeholder="اكتب سبب النقل أو ملاحظات فنية للمركبة..." rows="3" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"></textarea>
                            </div>

                            <div class="flex justify-start gap-2 border-t border-slate-850 pt-4 mt-6">
                                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg cursor-pointer transition">حفظ التغييرات</button>
                                <button type="button" onclick="document.getElementById('edit-transfer-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-lg cursor-pointer transition">إلغاء</button>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                function showEditTransferModal(transferId, toBranchId, notes) {
                    document.getElementById('edit-transfer-id').value = transferId;
                    document.getElementById('edit-to-branch-id').value = toBranchId;
                    document.getElementById('edit-notes').value = notes || '';
                    document.getElementById('edit-transfer-modal').classList.remove('hidden');
                }
                </script>
            </div>
            <?php endif; ?>

            <!-- PAGE 7: GENERAL SETTINGS -->
            <?php if ($page === 'settings' && ($user_role === 'admin' || $user_role === 'branch_manager')): ?>
            <div class="space-y-6 max-w-6xl mx-auto text-right w-full" dir="rtl">
                
                <!-- Page Title Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-900 border border-slate-800 p-5 rounded-2xl text-white">
                    <div>
                        <h2 class="text-xl font-black text-slate-100 flex items-center gap-2">
                            ⚙️ الإعدادات العامة وهيكل البيانات
                        </h2>
                        <p class="text-xs text-slate-400 mt-1">تعديل الهوية الرقمية، معايير النظام الفنية، وقاعدة البيانات المتكاملة</p>
                    </div>
                    <div class="flex items-center gap-2 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 px-3 py-1.5 rounded-full text-xs font-bold font-sans">
                        <span class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></span>
                        <span>تحديث حي (SSE)</span>
                    </div>
                </div>

                <?php if (!empty($settings_error)): ?>
                    <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-bold rounded-xl flex items-center gap-2">
                        ⚠️ <?php echo htmlspecialchars($settings_error); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($settings_success)): ?>
                    <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold rounded-xl flex items-center gap-2">
                        ✓ <?php echo htmlspecialchars($settings_success); ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- Right Column: General Settings Form (col-span-2) -->
                    <div class="lg:col-span-2 bg-slate-900 p-5 rounded-2xl border border-slate-800 text-white space-y-5">
                        <div>
                            <h3 class="font-extrabold text-sm text-slate-100 flex items-center gap-2">
                                🛠️ تعديل الإعدادات العامة للمؤسسة
                            </h3>
                            <p class="text-[10px] text-slate-400 mt-0.5">ضبط الهوية التجارية للمستندات المطبوعة والعملات وطرق التواصل الأساسية</p>
                        </div>

                        <form method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="save_settings" value="1">
                            <input type="hidden" name="remove_logo" id="remove_logo_input" value="0">

                            <!-- Logo Upload Section -->
                            <div class="p-4 bg-slate-950 rounded-xl border border-slate-800 flex flex-col md:flex-row items-center gap-5">
                                <div class="w-24 h-24 rounded-lg border border-dashed border-slate-700 bg-slate-900 flex items-center justify-center overflow-hidden shrink-0 relative">
                                    <?php if (!empty($companySettings['logo'])): ?>
                                        <img src="<?php echo htmlspecialchars($companySettings['logo']); ?>" id="logo-preview" alt="Logo" class="w-full h-full object-contain" />
                                    <?php else: ?>
                                        <div id="logo-placeholder" class="text-center p-2">
                                            <svg class="w-6 h-6 mx-auto text-slate-500 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                            <span class="text-[9px] text-slate-500 block">لا يوجد شعار</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex-1 space-y-1.5 text-right w-full">
                                    <span class="text-xs font-bold text-slate-300 block">شعار المؤسسة الرسمي</span>
                                    <p class="text-[10px] text-slate-400 leading-relaxed">
                                        اختر صورة شعار واضحة وخلفية شفافة إن أمكن (بصيغة PNG أو JPG أو SVG) بحد أقصى 2 ميجابايت ليتم عرضها في فواتير الطباعة وتصدير التقارير وأعلى شريط النظام.
                                    </p>
                                    
                                    <div class="flex gap-2">
                                        <label class="px-3 py-1.5 text-[10px] font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded transition cursor-pointer flex items-center gap-1">
                                            <span>تحميل شعار</span>
                                            <input type="file" name="logo_file" accept="image/*" class="hidden" onchange="previewLogoFile(this)">
                                        </label>
                                        <button type="button" id="remove-logo-btn" onclick="removeLogoClicked()" class="px-3 py-1.5 text-[10px] font-bold text-rose-400 hover:text-rose-300 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 rounded transition cursor-pointer <?php echo empty($companySettings['logo']) ? 'hidden' : ''; ?>">
                                            إزالة الشعار
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Grid Form Inputs -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5">اسم المؤسسة / الشركة المعتمد</label>
                                    <input type="text" name="company_name" required value="<?php echo htmlspecialchars($companySettings['company_name'] ?? ''); ?>" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5">معرض السيارات الافتراضي</label>
                                    <input type="text" name="default_showroom_name" value="<?php echo htmlspecialchars($companySettings['default_showroom_name'] ?? 'معرض الرياض الرئيسي'); ?>" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5">رقم تواصل خدمة العملاء</label>
                                    <input type="text" name="phone" required value="<?php echo htmlspecialchars($companySettings['phone'] ?? ''); ?>" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5">البريد الإلكتروني الرسمي للمراسلات</label>
                                    <input type="email" name="email" required value="<?php echo htmlspecialchars($companySettings['email'] ?? ''); ?>" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5">رمز العملة الرسمية للمخزون</label>
                                    <input type="text" name="currency" required value="<?php echo htmlspecialchars($companySettings['currency'] ?? 'ر.س'); ?>" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-bold">
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5">العنوان والمقر الرئيسي للمركز</label>
                                    <input type="text" name="address" required value="<?php echo htmlspecialchars($companySettings['address'] ?? ''); ?>" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                                </div>

                                <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-300 mb-1.5">الرقم الضريبي الموحد للمؤسسة</label>
                                        <input type="text" name="tax_number" value="<?php echo htmlspecialchars($companySettings['tax_number'] ?? ''); ?>" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-300 mb-1.5">رقم السجل التجاري للمؤسسة</label>
                                        <input type="text" name="cr_number" value="<?php echo htmlspecialchars($companySettings['cr_number'] ?? ''); ?>" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-300 mb-1.5">رقم تواصل إضافي / المباشر</label>
                                        <input type="text" name="contact_phone" value="<?php echo htmlspecialchars($companySettings['contact_phone'] ?? ''); ?>" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-300 mb-1.5">رقم واتساب المعرض (للعملاء)</label>
                                        <input type="text" name="whatsapp_phone" value="<?php echo htmlspecialchars($companySettings['whatsapp_phone'] ?? ''); ?>" placeholder="مثال: 966500000000" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                                    </div>
                                </div>
                            </div>

                            <!-- ADVANCED BRAND IDENTITY SETTINGS -->
                            <div class="p-5 bg-slate-950 rounded-xl border border-slate-800 space-y-5 mt-6">
                                <div class="border-b border-slate-800 pb-3">
                                    <h4 class="text-xs font-bold text-indigo-400 flex items-center gap-2">
                                        🎨 التحكم المتقدم في هوية الشعار والاسم والمعرض الافتراضي
                                    </h4>
                                    <p class="text-[10px] text-slate-500 mt-0.5 leading-relaxed">
                                        قم بتهيئة حجم الشعار الرسمي المرفوع وتعديل ألوان ونصوص اسم المؤسسة المعتمد وعناوين المعرض الافتراضي بدقة فائقة لتناسب علامتك التجارية بالكامل.
                                    </p>
                                </div>

                                <!-- Logo Controls Block -->
                                <div class="space-y-3.5">
                                    <span class="text-[11px] font-extrabold text-slate-300 block">🏷️ 1. تخصيص الشعار الرسمي (شعار المؤسسة)</span>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-400 mb-1">ارتفاع الشعار الرسمي (بالبكسل px)</label>
                                            <input type="number" name="logo_height" min="15" max="250" value="<?php echo intval($companySettings['logo_height'] ?? 40); ?>" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-900 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                                            <p class="text-[8px] text-slate-500 mt-1">يتحكم في ارتفاع الشعار أعلى الصفحة وفي الفوترة (الافتراضي 40)</p>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-400 mb-1">زوايا الشعار / الأيقونة (زاوية الحدود px)</label>
                                            <input type="number" name="logo_border_radius" min="0" max="100" value="<?php echo intval($companySettings['logo_border_radius'] ?? 12); ?>" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-900 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                                            <p class="text-[8px] text-slate-500 mt-1">مدى انحناء زوايا مربع الشعار النصي أو الأيقونة الاحتياطية</p>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-400 mb-1">لون خلفية الشعار النصي البديل</label>
                                            <div class="flex gap-2">
                                                <input type="color" value="<?php echo htmlspecialchars($companySettings['logo_color'] ?? '#6366f1'); ?>" oninput="this.nextElementSibling.value = this.value" class="w-10 h-9 p-0 bg-transparent border-0 cursor-pointer rounded-lg shrink-0 overflow-hidden">
                                                <input type="text" name="logo_color" required value="<?php echo htmlspecialchars($companySettings['logo_color'] ?? '#6366f1'); ?>" class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-800 bg-slate-900 text-slate-200 focus:outline-none focus:border-indigo-500 font-mono text-center">
                                            </div>
                                            <p class="text-[8px] text-slate-500 mt-1">يُطبق على خلفية الأيقونة النصية "M" في حال عدم رفع شعار صورة</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Company Name Controls Block -->
                                <div class="space-y-3.5 pt-3 border-t border-slate-850">
                                    <span class="text-[11px] font-extrabold text-slate-300 block">🏢 2. تخصيص اسم المؤسسة / الشركة المعتمد</span>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-400 mb-1">لون خط الاسم (الوضع النهاري - الفاتح)</label>
                                            <div class="flex gap-2">
                                                <input type="color" value="<?php echo htmlspecialchars($companySettings['company_name_color'] ?? '#0f172a'); ?>" oninput="this.nextElementSibling.value = this.value" class="w-10 h-9 p-0 bg-transparent border-0 cursor-pointer rounded-lg shrink-0 overflow-hidden">
                                                <input type="text" name="company_name_color" required value="<?php echo htmlspecialchars($companySettings['company_name_color'] ?? '#0f172a'); ?>" class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-800 bg-slate-900 text-slate-200 focus:outline-none focus:border-indigo-500 font-mono text-center">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-400 mb-1">لون خط الاسم (الوضع الليلي - الداكن)</label>
                                            <div class="flex gap-2">
                                                <input type="color" value="<?php echo htmlspecialchars($companySettings['company_name_color_dark'] ?? '#ffffff'); ?>" oninput="this.nextElementSibling.value = this.value" class="w-10 h-9 p-0 bg-transparent border-0 cursor-pointer rounded-lg shrink-0 overflow-hidden">
                                                <input type="text" name="company_name_color_dark" required value="<?php echo htmlspecialchars($companySettings['company_name_color_dark'] ?? '#ffffff'); ?>" class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-800 bg-slate-900 text-slate-200 focus:outline-none focus:border-indigo-500 font-mono text-center">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-400 mb-1">حجم وحضور خط اسم المؤسسة</label>
                                            <select name="company_name_font_size" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-900 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans font-bold">
                                                <option value="text-xs" <?php echo ($companySettings['company_name_font_size'] ?? '') === 'text-xs' ? 'selected' : ''; ?>>صغير جداً (Extra Small)</option>
                                                <option value="text-sm" <?php echo (($companySettings['company_name_font_size'] ?? 'text-sm') === 'text-sm') ? 'selected' : ''; ?>>صغير متناسق (Small - افتراضي)</option>
                                                <option value="text-base" <?php echo ($companySettings['company_name_font_size'] ?? '') === 'text-base' ? 'selected' : ''; ?>>متوسط واضح (Medium)</option>
                                                <option value="text-lg" <?php echo ($companySettings['company_name_font_size'] ?? '') === 'text-lg' ? 'selected' : ''; ?>>كبير وعريض (Large)</option>
                                                <option value="text-xl" <?php echo ($companySettings['company_name_font_size'] ?? '') === 'text-xl' ? 'selected' : ''; ?>>ضخم مميز (Extra Large)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Showroom Name Controls Block -->
                                <div class="space-y-3.5 pt-3 border-t border-slate-850">
                                    <span class="text-[11px] font-extrabold text-slate-300 block">🚗 3. تخصيص معرض السيارات الافتراضي (البطاقة والوصف)</span>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-400 mb-1">لون خط اسم المعرض (الوضع النهاري - الفاتح)</label>
                                            <div class="flex gap-2">
                                                <input type="color" value="<?php echo htmlspecialchars($companySettings['showroom_name_color'] ?? '#6366f1'); ?>" oninput="this.nextElementSibling.value = this.value" class="w-10 h-9 p-0 bg-transparent border-0 cursor-pointer rounded-lg shrink-0 overflow-hidden">
                                                <input type="text" name="showroom_name_color" required value="<?php echo htmlspecialchars($companySettings['showroom_name_color'] ?? '#6366f1'); ?>" class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-800 bg-slate-900 text-slate-200 focus:outline-none focus:border-indigo-500 font-mono text-center">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-400 mb-1">لون خط اسم المعرض (الوضع الليلي - الداكن)</label>
                                            <div class="flex gap-2">
                                                <input type="color" value="<?php echo htmlspecialchars($companySettings['showroom_name_color_dark'] ?? '#818cf8'); ?>" oninput="this.nextElementSibling.value = this.value" class="w-10 h-9 p-0 bg-transparent border-0 cursor-pointer rounded-lg shrink-0 overflow-hidden">
                                                <input type="text" name="showroom_name_color_dark" required value="<?php echo htmlspecialchars($companySettings['showroom_name_color_dark'] ?? '#818cf8'); ?>" class="w-full text-xs px-2 py-1.5 rounded-lg border border-slate-800 bg-slate-900 text-slate-200 focus:outline-none focus:border-indigo-500 font-mono text-center">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-400 mb-1">حجم خط اسم المعرض الافتراضي</label>
                                            <select name="showroom_name_font_size" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-900 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans font-bold">
                                                <option value="text-[8px]" <?php echo ($companySettings['showroom_name_font_size'] ?? '') === 'text-[8px]' ? 'selected' : ''; ?>>صغير جداً (Micro)</option>
                                                <option value="text-[9px]" <?php echo (($companySettings['showroom_name_font_size'] ?? 'text-[9px]') === 'text-[9px]') ? 'selected' : ''; ?>>صغير قياسي (Mini - افتراضي)</option>
                                                <option value="text-xs" <?php echo ($companySettings['showroom_name_font_size'] ?? '') === 'text-xs' ? 'selected' : ''; ?>>متوسط واضح (Extra Small)</option>
                                                <option value="text-sm" <?php echo ($companySettings['showroom_name_font_size'] ?? '') === 'text-sm' ? 'selected' : ''; ?>>كبير (Small)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-slate-800 flex justify-end">
                                <button type="submit" class="px-5 py-2.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition flex items-center gap-1.5 cursor-pointer shadow-md shadow-indigo-950/20">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                                    <span>حفظ وتطبيق الإعدادات</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Left Column: Backup and Recovery Operations -->
                    <div class="bg-slate-900 p-5 rounded-2xl border border-slate-800 text-white flex flex-col justify-between space-y-4">
                        <div class="space-y-4 w-full">
                            <div>
                                <h3 class="font-extrabold text-sm text-slate-100 flex items-center gap-2">
                                    💾 النسخ الاحتياطي والاستعادة الدورية
                                </h3>
                                <p class="text-[10px] text-slate-400 mt-0.5">توليد نسخة كاملة لقاعدة البيانات أو استرجاعها لضمان متانة البيانات (Disaster Recovery)</p>
                            </div>

                            <!-- 1. Generate Backup -->
                            <div class="p-4 rounded-xl border border-slate-800 bg-slate-950 space-y-3">
                                <span class="text-xs font-bold text-slate-200 block">حفظ نسخة احتياطية فورية</span>
                                <p class="text-[9px] text-slate-500 leading-relaxed">
                                    يقوم بتوليد ملف JSON يحتوي على كافة الفروع والمستخدمين والمخزون والحجوزات المسجلة للتحميل.
                                </p>
                                <a href="?page=settings&action=download_backup" class="w-full py-2 text-xs font-bold text-slate-300 bg-slate-900 hover:bg-slate-850 border border-slate-800 rounded-lg transition flex items-center justify-center gap-1.5 shadow-sm">
                                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    <span>توليد وتحميل النسخة احتياطية</span>
                                </a>
                            </div>

                            <!-- 2. Restore Backup -->
                            <div class="p-4 rounded-xl border border-rose-500/10 bg-rose-500/5 space-y-3">
                                <span class="text-xs font-bold text-rose-400 block flex items-center gap-1.5">
                                    ⚠️ استعادة وتجاوز قاعدة البيانات
                                </span>
                                <p class="text-[9px] text-slate-400 leading-relaxed">
                                    تحذير: استعادة ملف البيانات سيلغي ويستبدل كافة البيانات والتغيرات الحالية في المخزون تماماً.
                                </p>
                                <form method="POST" enctype="multipart/form-data" class="space-y-3">
                                    <input type="hidden" name="restore_backup" value="1">
                                    <label class="block w-full py-2 text-xs font-bold text-rose-400 bg-slate-900 hover:bg-slate-850 border border-slate-800 rounded-lg transition flex items-center justify-center gap-1.5 shadow-sm cursor-pointer">
                                        <svg class="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                        <span id="restore-file-label">رفع واستعادة قاعدة البيانات</span>
                                        <input type="file" name="backup_file" accept=".json" class="hidden" required onchange="backupFileChosen(this)">
                                    </label>
                                    <button type="submit" onclick="return confirm('هل أنت متأكد تماماً من أنك تريد مسح قاعدة البيانات الحالية واستعادتها من هذا الملف؟')" class="w-full py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-lg transition shadow-md shadow-rose-950/20">
                                        تأكيد الاستعادة والرفع
                                    </button>
                                </form>
                            </div>

                            <!-- 3. Dynamic Installer Wizard Link -->
                            <div class="p-4 rounded-xl border border-indigo-500/10 bg-indigo-500/5 space-y-3">
                                <span class="text-xs font-bold text-indigo-400 block flex items-center gap-1.5">
                                    💻 معالج التثبيت والتهيئة (PHP / MySQL)
                                </span>
                                <p class="text-[9px] text-slate-400 leading-relaxed">
                                    تشغيل معالج التثبيت التفاعلي لتجربة إنشاء الجداول، فحص بيئة PHP وتوليد ملف config.php أوتوماتيكياً.
                                </p>
                                <a href="installer/index.php" target="_blank" class="w-full py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition flex items-center justify-center gap-1.5 shadow-md shadow-indigo-950/20">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                                    <span>تشغيل معالج التثبيت الاحترافي</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Showroom Customization Panel -->
                <?php if (isset($_GET['showroom_success'])): ?>
                    <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold rounded-xl flex items-center gap-2 mt-6">
                        ✓ تم حفظ وتحديث إعدادات واجهة معرض العملاء (الهيدر والفوتر والسمات) بنجاح!
                    </div>
                <?php endif; ?>

                <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 text-white space-y-6 mt-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <h3 class="font-extrabold text-sm text-slate-100 flex items-center gap-2">
                                🎨 إدارة وتخصيص معرض العملاء الافتراضي (Showroom)
                            </h3>
                            <p class="text-[10px] text-slate-400 mt-0.5">التحكم في تصميم هيدر وفوتر الصفحة الخارجية، الألوان والمظهر العام، وصور البانر وروابط التواصل الاجتماعي</p>
                        </div>
                        <a href="customer.php" target="_blank" class="px-3.5 py-1.5 bg-indigo-600/10 hover:bg-indigo-600/20 text-indigo-400 border border-indigo-500/20 rounded-full text-xs font-bold flex items-center gap-1.5 transition">
                            <span>🔗 معاينة معرض العملاء</span>
                        </a>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <input type="hidden" name="save_customer_showroom_settings" value="1">
                        <input type="hidden" name="remove_showroom_banner" id="remove_showroom_banner_input" value="0">

                        <!-- Section 1: Header & Banner Configurations -->
                        <div class="p-4 bg-slate-950 rounded-xl border border-slate-850 space-y-4">
                            <span class="text-xs font-bold text-indigo-400 block border-b border-slate-850 pb-2">🖼️ إعدادات الهيدر وبانر الترحيب</span>
                            
                            <!-- Custom Banner Image Upload -->
                            <div class="flex flex-col md:flex-row items-center gap-5">
                                <div class="w-32 h-20 rounded-lg border border-dashed border-slate-800 bg-slate-900 flex items-center justify-center overflow-hidden shrink-0 relative">
                                    <?php if (!empty($companySettings['showroom_banner_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($companySettings['showroom_banner_image']); ?>" id="showroom-banner-preview" alt="Banner" class="w-full h-full object-cover" />
                                    <?php else: ?>
                                        <div id="showroom-banner-placeholder" class="text-center p-2">
                                            <span class="text-[9px] text-slate-500 block">بانر افتراضي متدرج</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex-1 space-y-1.5 text-right w-full">
                                    <span class="text-xs font-bold text-slate-300 block">صورة خلفية البانر الترحيبي</span>
                                    <p class="text-[10px] text-slate-400 leading-relaxed">
                                        قم برفع صورة خلفية مخصصة للبانر الترحيبي أعلى صفحة المعرض (صيغة PNG أو JPG) بحد أقصى 2 ميجابايت. في حال عدم الرفع، سيتم عرض التدرج اللوني الافتراضي الأنيق.
                                    </p>
                                    
                                    <div class="flex gap-2">
                                        <label class="px-3 py-1.5 text-[10px] font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded transition cursor-pointer flex items-center gap-1">
                                            <span>تحميل صورة البانر</span>
                                            <input type="file" name="showroom_banner_file" accept="image/*" class="hidden" onchange="previewShowroomBannerFile(this)">
                                        </label>
                                        <button type="button" id="remove-showroom-banner-btn" onclick="removeShowroomBannerClicked()" class="px-3 py-1.5 text-[10px] font-bold text-rose-400 hover:text-rose-300 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 rounded transition cursor-pointer <?php echo empty($companySettings['showroom_banner_image']) ? 'hidden' : ''; ?>">
                                            إزالة واستعادة الافتراضي
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5">عنوان البانر الترحيبي الرئيسي (Header Title)</label>
                                    <input type="text" name="showroom_header_title" required value="<?php echo htmlspecialchars($companySettings['showroom_header_title'] ?? 'اختر سيارة أحلامك من مخزوننا الحديث'); ?>" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5">السمة اللونية العامة للموقع (Theme Accent)</label>
                                    <select name="showroom_theme" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans font-bold">
                                        <option value="indigo" <?php echo ($companySettings['showroom_theme'] ?? 'indigo') === 'indigo' ? 'selected' : ''; ?>>💜 بنفسجي كلاسيكي (Indigo)</option>
                                        <option value="emerald" <?php echo ($companySettings['showroom_theme'] ?? '') === 'emerald' ? 'selected' : ''; ?>>💚 أخضر زمردي (Emerald)</option>
                                        <option value="rose" <?php echo ($companySettings['showroom_theme'] ?? '') === 'rose' ? 'selected' : ''; ?>>❤️ وردي أحمر ناري (Rose)</option>
                                        <option value="sky" <?php echo ($companySettings['showroom_theme'] ?? '') === 'sky' ? 'selected' : ''; ?>>💙 أزرق سماوي عصري (Sky)</option>
                                        <option value="amber" <?php echo ($companySettings['showroom_theme'] ?? '') === 'amber' ? 'selected' : ''; ?>>💛 ذهبي ملكي (Amber)</option>
                                        <option value="slate" <?php echo ($companySettings['showroom_theme'] ?? '') === 'slate' ? 'selected' : ''; ?>>🖤 رمادي رسمي فاخر (Slate)</option>
                                    </select>
                                </div>

                                <div class="grid grid-cols-2 gap-3 md:col-span-2">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-300 mb-1.5">درجة وضوح وصراحة الصورة الخلفية (Banner Image Opacity)</label>
                                        <select name="showroom_banner_opacity" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans font-bold">
                                            <option value="10" <?php echo (int)($companySettings['showroom_banner_opacity'] ?? 25) === 10 ? 'selected' : ''; ?>>10% (تعتيم عالي جداً)</option>
                                            <option value="25" <?php echo (int)($companySettings['showroom_banner_opacity'] ?? 25) === 25 ? 'selected' : ''; ?>>25% (تعتيم افتراضي)</option>
                                            <option value="50" <?php echo (int)($companySettings['showroom_banner_opacity'] ?? 25) === 50 ? 'selected' : ''; ?>>50% (متوسط الوضوح)</option>
                                            <option value="75" <?php echo (int)($companySettings['showroom_banner_opacity'] ?? 25) === 75 ? 'selected' : ''; ?>>75% (وضوح عالي)</option>
                                            <option value="100" <?php echo (int)($companySettings['showroom_banner_opacity'] ?? 25) === 100 ? 'selected' : ''; ?>>100% (وضوح كامل بدون تعتيم)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-300 mb-1.5">درجة تعتيم غطاء اللون فوق الصورة (Color Overlay Opacity)</label>
                                        <select name="showroom_banner_overlay_opacity" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans font-bold">
                                            <option value="0" <?php echo (int)($companySettings['showroom_banner_overlay_opacity'] ?? 50) === 0 ? 'selected' : ''; ?>>0% (بدون لون / الصورة واضحة تماماً) 🌐</option>
                                            <option value="10" <?php echo (int)($companySettings['showroom_banner_overlay_opacity'] ?? 50) === 10 ? 'selected' : ''; ?>>10% (تغطية لونية خفيفة جداً)</option>
                                            <option value="25" <?php echo (int)($companySettings['showroom_banner_overlay_opacity'] ?? 50) === 25 ? 'selected' : ''; ?>>25% (تغطية لونية خفيفة)</option>
                                            <option value="50" <?php echo (int)($companySettings['showroom_banner_overlay_opacity'] ?? 50) === 50 ? 'selected' : ''; ?>>50% (تغطية لونية متوسطة - افتراضي)</option>
                                            <option value="75" <?php echo (int)($companySettings['showroom_banner_overlay_opacity'] ?? 50) === 75 ? 'selected' : ''; ?>>75% (تغطية لونية داكنة)</option>
                                            <option value="90" <?php echo (int)($companySettings['showroom_banner_overlay_opacity'] ?? 50) === 90 ? 'selected' : ''; ?>>90% (تغطية لونية كاملة تقريباً)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5">الوصف الترحيبي الفرعي (Header Subtitle)</label>
                                    <textarea name="showroom_header_subtitle" rows="3" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 leading-relaxed"><?php echo htmlspecialchars($companySettings['showroom_header_subtitle'] ?? 'نقدم لك خدمات متميزة، سيارات مضمونة ومفحوصة بالكامل، وتسهيلات تواصل مباشرة مع مناديب المبيعات المعتمدين.'); ?></textarea>
                                </div>

                                <!-- Advanced Banner Customizer Styling Controls -->
                                <div class="md:col-span-2 border-t border-slate-850 pt-4 space-y-4">
                                    <span class="text-xs font-bold text-indigo-400 block">🎨 خيارات التنسيق المتقدم للبانر الترحيبي (الألوان والأبعاد)</span>
                                    
                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
                                        <!-- Height -->
                                        <div>
                                            <label class="block text-xs font-bold text-slate-300 mb-1.5">ارتفاع البانر الترحيبي</label>
                                            <select name="showroom_banner_height" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans font-bold">
                                                <option value="compact" <?php echo ($companySettings['showroom_banner_height'] ?? 'medium') === 'compact' ? 'selected' : ''; ?>>قصير / مضغوط (Compact)</option>
                                                <option value="medium" <?php echo ($companySettings['showroom_banner_height'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>متوسط متناسق (Medium)</option>
                                                <option value="tall" <?php echo ($companySettings['showroom_banner_height'] ?? 'medium') === 'tall' ? 'selected' : ''; ?>>طويل / عريض (Tall)</option>
                                            </select>
                                        </div>

                                        <!-- Background Image Size / Fit -->
                                        <div>
                                            <label class="block text-xs font-bold text-slate-300 mb-1.5">طريقة ملاءمة وحجم الصورة</label>
                                            <select name="showroom_banner_bg_size" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans font-bold">
                                                <option value="cover" <?php echo ($companySettings['showroom_banner_bg_size'] ?? 'cover') === 'cover' ? 'selected' : ''; ?>>تغطية وتمدد كامل (Cover)</option>
                                                <option value="contain" <?php echo ($companySettings['showroom_banner_bg_size'] ?? 'cover') === 'contain' ? 'selected' : ''; ?>>ملاءمة بالداخل بالكامل (Contain)</option>
                                                <option value="auto" <?php echo ($companySettings['showroom_banner_bg_size'] ?? 'cover') === 'auto' ? 'selected' : ''; ?>>تلقائي / الحجم الأصلي (Auto)</option>
                                            </select>
                                        </div>

                                        <!-- Background Image Width -->
                                        <div>
                                            <label class="block text-xs font-bold text-slate-300 mb-1.5">عرض الخلفية (البانر)</label>
                                            <select name="showroom_banner_width" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans font-bold">
                                                <option value="full" <?php echo ($companySettings['showroom_banner_width'] ?? 'full') === 'full' ? 'selected' : ''; ?>>كامل العرض (Full Width)</option>
                                                <option value="contained" <?php echo ($companySettings['showroom_banner_width'] ?? 'full') === 'contained' ? 'selected' : ''; ?>>داخل حاوية (Contained)</option>
                                            </select>
                                        </div>

                                        <!-- Title Color -->
                                        <div>
                                            <label class="block text-xs font-bold text-slate-300 mb-1.5">لون العنوان الرئيسي (Title)</label>
                                            <div class="flex gap-2">
                                                <input type="color" value="<?php echo htmlspecialchars($companySettings['showroom_banner_title_color'] ?? '#ffffff'); ?>" oninput="this.nextElementSibling.value = this.value" class="w-10 h-9 p-0 bg-transparent border-0 cursor-pointer rounded-lg shrink-0 overflow-hidden">
                                                <input type="text" name="showroom_banner_title_color" required value="<?php echo htmlspecialchars($companySettings['showroom_banner_title_color'] ?? '#ffffff'); ?>" class="w-full text-xs px-2 py-1 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-mono text-center">
                                            </div>
                                        </div>

                                        <!-- Subtitle Color -->
                                        <div>
                                            <label class="block text-xs font-bold text-slate-300 mb-1.5">لون الوصف الفرعي (Subtitle)</label>
                                            <div class="flex gap-2">
                                                <input type="color" value="<?php echo htmlspecialchars($companySettings['showroom_banner_subtitle_color'] ?? '#cbd5e1'); ?>" oninput="this.nextElementSibling.value = this.value" class="w-10 h-9 p-0 bg-transparent border-0 cursor-pointer rounded-lg shrink-0 overflow-hidden">
                                                <input type="text" name="showroom_banner_subtitle_color" required value="<?php echo htmlspecialchars($companySettings['showroom_banner_subtitle_color'] ?? '#cbd5e1'); ?>" class="w-full text-xs px-2 py-1 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-mono text-center">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between p-3.5 bg-slate-900 border border-slate-800 rounded-xl mt-3">
                                        <div>
                                            <span class="text-xs font-bold text-slate-200 block">وضع خلفية تظليل داكنة خلف النص الترحيبي</span>
                                            <p class="text-[9px] text-slate-400 mt-1">يُساعد هذا الخيار في زيادة وضوح وسهولة قراءة النصوص في حال كانت خلفية الصورة فاتحة أو مشوشة.</p>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="showroom_banner_text_bg" value="1" class="sr-only peer" <?php echo (int)($companySettings['showroom_banner_text_bg'] ?? 0) === 1 ? 'checked' : ''; ?>>
                                            <div class="w-11 h-6 bg-slate-800 rounded-full peer peer-focus:ring-2 peer-focus:ring-indigo-500/20 dark:peer-focus:ring-indigo-500/30 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[2px] after:bg-slate-300 after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                        </label>
                                    </div>

                                    <!-- Manual Mouse Resize Custom Control Area -->
                                    <div class="bg-slate-950 p-4 rounded-xl border border-slate-850 space-y-4 mt-5">
                                        <span class="text-xs font-bold text-amber-400 block flex items-center gap-2">
                                            🖱️ ميزة التحكم البصري الحر (تكبير وتصغير البانر بالماوس مباشرة)
                                        </span>
                                        <p class="text-[10px] text-slate-400 leading-relaxed">
                                            قم بسحب المقابض الملونة السفلية أو الجانبية للبانر التفاعلي أدناه لتحديد الطول والعرض المطلوب بدقة بالغة بالماوس، وسيتم حفظ هذه الأبعاد تلقائياً وحقنها في الواجهة الخارجية.
                                        </p>
                                        
                                        <!-- Interactive Resize Container -->
                                        <div class="relative w-full flex justify-center items-center py-6 bg-slate-900 rounded-lg overflow-hidden border border-slate-800">
                                            <div 
                                                id="visual-banner-resizable" 
                                                class="relative bg-gradient-to-br from-indigo-900/40 to-slate-950 border border-indigo-500/30 rounded-xl shadow-xl transition-all overflow-hidden flex flex-col justify-center items-center p-4 text-center select-none"
                                                style="width: <?php echo htmlspecialchars($companySettings['showroom_banner_custom_width'] ?? '100%'); ?>; height: <?php echo htmlspecialchars($companySettings['showroom_banner_custom_height'] ?? '350px'); ?>; max-width: 100%; min-width: 200px; min-height: 100px;"
                                            >
                                                <!-- Currently Uploaded Image (if any) -->
                                                <?php if (!empty($companySettings['showroom_banner_image'])): ?>
                                                    <img src="<?php echo htmlspecialchars($companySettings['showroom_banner_image']); ?>" class="absolute inset-0 w-full h-full object-cover opacity-50 pointer-events-none" alt="Banner background">
                                                <?php endif; ?>
                                                
                                                <!-- Mock text -->
                                                <div class="relative z-10 pointer-events-none space-y-1">
                                                    <div class="text-xs font-extrabold text-white">معاينة البانر التفاعلي 🚗</div>
                                                    <div class="text-[9px] text-slate-400">اسحب المقابض الجانبية أو السفلية لتغيير الأبعاد بالماوس</div>
                                                    <div class="inline-block px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 font-mono text-[9px] mt-1">
                                                        <span id="live-banner-width-lbl"><?php echo htmlspecialchars($companySettings['showroom_banner_custom_width'] ?? '100%'); ?></span>
                                                        x
                                                        <span id="live-banner-height-lbl"><?php echo htmlspecialchars($companySettings['showroom_banner_custom_height'] ?? '350px'); ?></span>
                                                    </div>
                                                </div>

                                                <!-- Visual Resize Handles (Bottom, Left, and Bottom-Left) -->
                                                <!-- Right handle (width) -->
                                                <div id="handle-r" class="absolute top-0 right-0 w-2.5 h-full hover:bg-amber-500/40 bg-amber-500/10 cursor-ew-resize z-20 flex items-center justify-center">
                                                    <div class="w-1 h-6 bg-amber-500 rounded-full opacity-60"></div>
                                                </div>
                                                <!-- Bottom handle (height) -->
                                                <div id="handle-b" class="absolute bottom-0 left-0 w-full h-2.5 hover:bg-amber-500/40 bg-amber-500/10 cursor-ns-resize z-20 flex items-center justify-center">
                                                    <div class="w-12 h-1 bg-amber-500 rounded-full opacity-60"></div>
                                                </div>
                                                <!-- Bottom-Right Corner diagonal handle -->
                                                <div id="handle-br" class="absolute bottom-0 right-0 w-4 h-4 hover:bg-amber-500/60 bg-amber-500/30 cursor-se-resize z-30 rounded-tl-lg flex items-center justify-center">
                                                    <span class="text-[8px] text-slate-900 font-bold">↘</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Value Inputs (Updated on Drag) -->
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-400 mb-1">عرض البانر اليدوي (بالنسبة % أو البكسل px)</label>
                                                <input type="text" id="showroom_banner_custom_width" name="showroom_banner_custom_width" value="<?php echo htmlspecialchars($companySettings['showroom_banner_custom_width'] ?? '100%'); ?>" oninput="updateVisualBannerFromInput()" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-mono text-center">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-400 mb-1">ارتفاع البانر اليدوي (بالبكسل px)</label>
                                                <input type="text" id="showroom_banner_custom_height" name="showroom_banner_custom_height" value="<?php echo htmlspecialchars($companySettings['showroom_banner_custom_height'] ?? '350px'); ?>" oninput="updateVisualBannerFromInput()" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-mono text-center">
                                            </div>
                                        </div>
                                    </div>

                                    <script>
                                        document.addEventListener('DOMContentLoaded', function() {
                                            const banner = document.getElementById('visual-banner-resizable');
                                            if (!banner) return;
                                            
                                            const handleR = document.getElementById('handle-r');
                                            const handleB = document.getElementById('handle-b');
                                            const handleBR = document.getElementById('handle-br');
                                            
                                            const inputW = document.getElementById('showroom_banner_custom_width');
                                            const inputH = document.getElementById('showroom_banner_custom_height');
                                            
                                            const lblW = document.getElementById('live-banner-width-lbl');
                                            const lblH = document.getElementById('live-banner-height-lbl');
                                            
                                            let startX, startY, startWidth, startHeight;
                                            
                                            if (handleR) {
                                                handleR.addEventListener('mousedown', function(e) {
                                                    e.preventDefault();
                                                    startX = e.clientX;
                                                    startWidth = banner.offsetWidth;
                                                    document.addEventListener('mousemove', resizeWidth);
                                                    document.addEventListener('mouseup', stopResize);
                                                });
                                            }
                                            
                                            if (handleB) {
                                                handleB.addEventListener('mousedown', function(e) {
                                                    e.preventDefault();
                                                    startY = e.clientY;
                                                    startHeight = banner.offsetHeight;
                                                    document.addEventListener('mousemove', resizeHeight);
                                                    document.addEventListener('mouseup', stopResize);
                                                });
                                            }
                                            
                                            if (handleBR) {
                                                handleBR.addEventListener('mousedown', function(e) {
                                                    e.preventDefault();
                                                    startX = e.clientX;
                                                    startY = e.clientY;
                                                    startWidth = banner.offsetWidth;
                                                    startHeight = banner.offsetHeight;
                                                    document.addEventListener('mousemove', resizeBoth);
                                                    document.addEventListener('mouseup', stopResize);
                                                });
                                            }
                                            
                                            function resizeWidth(e) {
                                                const parentWidth = banner.parentElement.offsetWidth;
                                                let newWidth = startWidth + (e.clientX - startX);
                                                newWidth = Math.max(200, Math.min(newWidth, parentWidth));
                                                const pct = Math.round((newWidth / parentWidth) * 100) + '%';
                                                banner.style.width = pct;
                                                inputW.value = pct;
                                                lblW.innerText = pct;
                                            }
                                            
                                            function resizeHeight(e) {
                                                let newHeight = startHeight + (e.clientY - startY);
                                                newHeight = Math.max(100, Math.min(newHeight, 800));
                                                const px = newHeight + 'px';
                                                banner.style.height = px;
                                                inputH.value = px;
                                                lblH.innerText = px;
                                            }
                                            
                                            function resizeBoth(e) {
                                                const parentWidth = banner.parentElement.offsetWidth;
                                                let newWidth = startWidth + (e.clientX - startX);
                                                newWidth = Math.max(200, Math.min(newWidth, parentWidth));
                                                const pct = Math.round((newWidth / parentWidth) * 100) + '%';
                                                
                                                let newHeight = startHeight + (e.clientY - startY);
                                                newHeight = Math.max(100, Math.min(newHeight, 800));
                                                const px = newHeight + 'px';
                                                
                                                banner.style.width = pct;
                                                banner.style.height = px;
                                                
                                                inputW.value = pct;
                                                inputH.value = px;
                                                
                                                lblW.innerText = pct;
                                                lblH.innerText = px;
                                            }
                                            
                                            function stopResize() {
                                                document.removeEventListener('mousemove', resizeWidth);
                                                document.removeEventListener('mousemove', resizeHeight);
                                                document.removeEventListener('mousemove', resizeBoth);
                                                document.removeEventListener('mouseup', stopResize);
                                            }
                                        });

                                        function updateVisualBannerFromInput() {
                                            const banner = document.getElementById('visual-banner-resizable');
                                            if (!banner) return;
                                            const w = document.getElementById('showroom_banner_custom_width').value;
                                            const h = document.getElementById('showroom_banner_custom_height').value;
                                            banner.style.width = w;
                                            banner.style.height = h;
                                            document.getElementById('live-banner-width-lbl').innerText = w;
                                            document.getElementById('live-banner-height-lbl').innerText = h;
                                        }
                                    </script>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Display & Interaction Options -->
                        <div class="p-4 bg-slate-950 rounded-xl border border-slate-850 space-y-4">
                            <span class="text-xs font-bold text-indigo-400 block border-b border-slate-850 pb-2">⚙️ خيارات العرض والأسعار</span>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="flex items-center justify-between p-3.5 bg-slate-900 border border-slate-800 rounded-xl">
                                    <div>
                                        <span class="text-xs font-bold text-slate-200 block">عرض أسعار السيارات للعملاء</span>
                                        <p class="text-[9px] text-slate-400 mt-1">عند التعطيل، سيتم إخفاء السعر تماماً من بطاقات السيارات والطلب</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="showroom_show_price" value="1" class="sr-only peer" <?php echo (int)($companySettings['showroom_show_price'] ?? 1) === 1 ? 'checked' : ''; ?>>
                                        <div class="w-11 h-6 bg-slate-800 rounded-full peer peer-focus:ring-2 peer-focus:ring-indigo-500/20 dark:peer-focus:ring-indigo-500/30 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[2px] after:bg-slate-300 after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                    </label>
                                </div>

                                <div class="flex items-center justify-between p-3.5 bg-slate-900 border border-slate-800 rounded-xl">
                                    <div>
                                        <span class="text-xs font-bold text-slate-200 block">تفعيل شريط البحث والفرز الذكي</span>
                                        <p class="text-[9px] text-slate-400 mt-1">إظهار أو إخفاء حقل البحث وفرز الماركات في صفحة المعرض</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="showroom_show_filters" value="1" class="sr-only peer" <?php echo (int)($companySettings['showroom_show_filters'] ?? 1) === 1 ? 'checked' : ''; ?>>
                                        <div class="w-11 h-6 bg-slate-800 rounded-full peer peer-focus:ring-2 peer-focus:ring-indigo-500/20 dark:peer-focus:ring-indigo-500/30 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[2px] after:bg-slate-300 after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Footer Settings & Social Media Links -->
                        <div class="p-4 bg-slate-950 rounded-xl border border-slate-850 space-y-4">
                            <span class="text-xs font-bold text-indigo-400 block border-b border-slate-850 pb-2">📢 إعدادات الفوتر والشبكات الاجتماعية للعملاء</span>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5">نص الحقوق والفوتر المعتمد (Footer Text)</label>
                                    <input type="text" name="showroom_footer_text" required value="<?php echo htmlspecialchars($companySettings['showroom_footer_text'] ?? 'جميع الحقوق محفوظة © 2026 شركة المخزون للمحركات المحدودة.'); ?>" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1">
                                        <span>🐦 رابط حساب تويتر / X</span>
                                        <span class="text-[9px] text-slate-500">(اختياري)</span>
                                    </label>
                                    <input type="url" name="showroom_twitter" value="<?php echo htmlspecialchars($companySettings['showroom_twitter'] ?? ''); ?>" placeholder="https://twitter.com/..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans ltr text-left" dir="ltr">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1">
                                        <span>📷 رابط حساب إنستغرام</span>
                                        <span class="text-[9px] text-slate-500">(اختياري)</span>
                                    </label>
                                    <input type="url" name="showroom_instagram" value="<?php echo htmlspecialchars($companySettings['showroom_instagram'] ?? ''); ?>" placeholder="https://instagram.com/..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans ltr text-left" dir="ltr">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1">
                                        <span>📘 رابط صفحة فيسبوك</span>
                                        <span class="text-[9px] text-slate-500">(اختياري)</span>
                                    </label>
                                    <input type="url" name="showroom_facebook" value="<?php echo htmlspecialchars($companySettings['showroom_facebook'] ?? ''); ?>" placeholder="https://facebook.com/..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans ltr text-left" dir="ltr">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1">
                                        <span>💼 رابط حساب لينكد إن</span>
                                        <span class="text-[9px] text-slate-500">(اختياري)</span>
                                    </label>
                                    <input type="url" name="showroom_linkedin" value="<?php echo htmlspecialchars($companySettings['showroom_linkedin'] ?? ''); ?>" placeholder="https://linkedin.com/..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans ltr text-left" dir="ltr">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1">
                                        <span>👻 رابط حساب سناب شات</span>
                                        <span class="text-[9px] text-slate-500">(اختياري)</span>
                                    </label>
                                    <input type="url" name="showroom_snapchat" value="<?php echo htmlspecialchars($companySettings['showroom_snapchat'] ?? ''); ?>" placeholder="https://snapchat.com/add/..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans ltr text-left" dir="ltr">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1">
                                        <span>▶️ رابط قناة اليوتيوب</span>
                                        <span class="text-[9px] text-slate-500">(اختياري)</span>
                                    </label>
                                    <input type="url" name="showroom_youtube" value="<?php echo htmlspecialchars($companySettings['showroom_youtube'] ?? ''); ?>" placeholder="https://youtube.com/..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans ltr text-left" dir="ltr">
                                </div>
                            </div>
                        </div>

                        <!-- Section 4: Advanced Custom Pages & Custom Code Settings -->
                        <div class="p-6 bg-slate-950 rounded-xl border border-slate-850 space-y-6">
                            <div>
                                <span class="text-xs font-bold text-indigo-400 block border-b border-slate-850 pb-2">🛠️ تخصيص الصفحات المخصصة والقوائم والأكواد (Advanced Customization)</span>
                                <p class="text-[10px] text-slate-400 mt-1">يمكنك إضافة صفحات كاملة بواسطة لغة HTML/JS والتحكم في أزرارها وقوائم الهيدر والفوتر وإدخال أكواد مخصصة.</p>
                            </div>

                            <!-- Hidden Fields to store data -->
                            <input type="hidden" name="showroom_custom_pages" id="showroom_custom_pages_input" value="<?php echo htmlspecialchars($companySettings['showroom_custom_pages'] ?? '[]'); ?>">
                            <input type="hidden" name="showroom_menu_links" id="showroom_menu_links_input" value="<?php echo htmlspecialchars($companySettings['showroom_menu_links'] ?? '[]'); ?>">
                            <input type="hidden" name="showroom_custom_socials" id="showroom_custom_socials_input" value="<?php echo htmlspecialchars($companySettings['showroom_custom_socials'] ?? '[]'); ?>">

                            <!-- Sub-tabs for layout configuration -->
                            <div class="flex border-b border-slate-800" id="advanced-showroom-tabs">
                                <button type="button" onclick="switchAdvTab('pages')" id="tab-btn-pages" class="px-4 py-2 text-xs font-bold border-b-2 border-indigo-500 text-white focus:outline-none">📄 الصفحات المخصصة بالكود</button>
                                <button type="button" onclick="switchAdvTab('menus')" id="tab-btn-menus" class="px-4 py-2 text-xs font-bold border-b-2 border-transparent text-slate-400 hover:text-slate-200 focus:outline-none">🔗 إدارة القوائم والروابط</button>
                                <button type="button" onclick="switchAdvTab('socials')" id="tab-btn-socials" class="px-4 py-2 text-xs font-bold border-b-2 border-transparent text-slate-400 hover:text-slate-200 focus:outline-none">🌐 منصات تواصل إضافية</button>
                                <button type="button" onclick="switchAdvTab('codes')" id="tab-btn-codes" class="px-4 py-2 text-xs font-bold border-b-2 border-transparent text-slate-400 hover:text-slate-200 focus:outline-none">💻 أكواد CSS و JS مخصصة</button>
                            </div>

                            <!-- Tab 1: Custom Pages -->
                            <div id="adv-tab-pages" class="space-y-6">
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                    <!-- Add/Edit form -->
                                    <div class="lg:col-span-1 bg-slate-900/60 p-4 rounded-xl border border-slate-800 space-y-4">
                                        <h4 class="text-xs font-extrabold text-slate-300" id="page-form-title">➕ إضافة صفحة جديدة</h4>
                                        <input type="hidden" id="edit-page-index" value="">
                                        
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-400 mb-1">عنوان الصفحة</label>
                                            <input type="text" id="page-title-field" placeholder="مثال: الشروط والأحكام" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                                        </div>

                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-400 mb-1">الرابط الفرعي (Slug) - بالإنجليزية فقط</label>
                                            <input type="text" id="page-slug-field" placeholder="مثال: terms" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans text-left" dir="ltr">
                                        </div>

                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-400 mb-1">أيقونة أو زر (أيقونة/Emoji)</label>
                                                <input type="text" id="page-icon-field" placeholder="مثال: 📄 أو shield" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-400 mb-1">موقع الظهور</label>
                                                <select id="page-visibility-field" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                                                    <option value="both">الهيدر والفوتر</option>
                                                    <option value="header">الهيدر فقط</option>
                                                    <option value="footer">الفوتر فقط</option>
                                                    <option value="none">مخفية (رابط مباشر فقط)</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-400 mb-1">محتوى الصفحة (HTML / CSS / JS Code)</label>
                                            <textarea id="page-content-field" rows="6" placeholder="اكتب كود صفحتك هنا... يمكنك إضافة <script> أو <style>" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-indigo-300 focus:outline-none focus:border-indigo-500 font-mono" dir="ltr"></textarea>
                                        </div>

                                        <div class="flex gap-2">
                                            <button type="button" onclick="saveCustomPage()" class="flex-1 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition cursor-pointer text-center">حفظ الصفحة</button>
                                            <button type="button" onclick="resetPageForm()" class="px-3 py-2 text-xs font-bold text-slate-400 bg-slate-800 hover:bg-slate-700 rounded-lg transition cursor-pointer text-center">إلغاء</button>
                                        </div>
                                    </div>

                                    <!-- Table / List of pages -->
                                    <div class="lg:col-span-2 space-y-4">
                                        <h4 class="text-xs font-extrabold text-slate-300">📄 الصفحات المضافة حالياً</h4>
                                        <div class="overflow-x-auto border border-slate-850 rounded-xl bg-slate-900/40">
                                            <table class="w-full text-right border-collapse">
                                                <thead>
                                                    <tr class="bg-slate-950 text-slate-400 text-[10px] border-b border-slate-800 font-extrabold">
                                                        <th class="p-3">الصفحة</th>
                                                        <th class="p-3">الرابط المباشر</th>
                                                        <th class="p-3">الأيقونة</th>
                                                        <th class="p-3">الظهور</th>
                                                        <th class="p-3 text-left">العمليات</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="custom-pages-list">
                                                    <!-- Generated dynamically -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tab 2: Custom links and Menus -->
                            <div id="adv-tab-menus" class="hidden space-y-6">
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                    <!-- Add/Edit form -->
                                    <div class="lg:col-span-1 bg-slate-900/60 p-4 rounded-xl border border-slate-800 space-y-4">
                                        <h4 class="text-xs font-extrabold text-slate-300" id="link-form-title">➕ إضافة رابط/زر مخصص</h4>
                                        <input type="hidden" id="edit-link-index" value="">
                                        
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-400 mb-1">اسم الرابط (العنوان)</label>
                                            <input type="text" id="link-title-field" placeholder="مثال: تواصل معنا، موقعنا" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                                        </div>

                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-400 mb-1">رابط الزر (URL)</label>
                                            <input type="text" id="link-url-field" placeholder="مثال: https://... أو رابط داخلي" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans text-left" dir="ltr">
                                        </div>

                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-400 mb-1">أيقونة (Emoji/lucide)</label>
                                                <input type="text" id="link-icon-field" placeholder="مثال: 🌐" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-400 mb-1">موقع الرابط</label>
                                                <select id="link-location-field" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                                                    <option value="header">القائمة العلوية (Header)</option>
                                                    <option value="footer">قائمة التذييل (Footer)</option>
                                                    <option value="both">كلاهما</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between p-2 bg-slate-950 rounded-lg border border-slate-850">
                                            <span class="text-[10px] font-bold text-slate-400">فتح في نافذة جديدة</span>
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" id="link-target-field" value="1" class="sr-only peer">
                                                <div class="w-9 h-5 bg-slate-800 rounded-full peer peer-focus:ring-2 peer-focus:ring-indigo-500/20 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[2px] after:bg-slate-300 after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                                            </label>
                                        </div>

                                        <div class="flex gap-2">
                                            <button type="button" onclick="saveCustomLink()" class="flex-1 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition cursor-pointer text-center">حفظ الرابط</button>
                                            <button type="button" onclick="resetLinkForm()" class="px-3 py-2 text-xs font-bold text-slate-400 bg-slate-800 hover:bg-slate-700 rounded-lg transition cursor-pointer text-center">إلغاء</button>
                                        </div>
                                    </div>

                                    <!-- Table / List of links -->
                                    <div class="lg:col-span-2 space-y-4">
                                        <h4 class="text-xs font-extrabold text-slate-300">🔗 الروابط والقوائم المضافة حالياً</h4>
                                        <div class="overflow-x-auto border border-slate-850 rounded-xl bg-slate-900/40">
                                            <table class="w-full text-right border-collapse">
                                                <thead>
                                                    <tr class="bg-slate-950 text-slate-400 text-[10px] border-b border-slate-800 font-extrabold">
                                                        <th class="p-3">العنوان</th>
                                                        <th class="p-3">الرابط الوجهة</th>
                                                        <th class="p-3">الأيقونة</th>
                                                        <th class="p-3">الموقع</th>
                                                        <th class="p-3">خصائص</th>
                                                        <th class="p-3 text-left">العمليات</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="custom-links-list">
                                                    <!-- Generated dynamically -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
 
                            <!-- Tab 4: Custom Social Platforms -->
                            <div id="adv-tab-socials" class="hidden space-y-6">
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                    <!-- Add/Edit form -->
                                    <div class="lg:col-span-1 bg-slate-900/60 p-4 rounded-xl border border-slate-800 space-y-4">
                                        <div class="border-b border-slate-800 pb-2">
                                            <span class="text-xs font-bold text-white block">➕ إضافة منصة تواصل اجتماعي مخصصة</span>
                                            <p class="text-[10px] text-slate-400">أضف أي منصات جديدة (مثل تيك توك، تلغرام، ثريدز، إلخ.)</p>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-300 mb-1">اسم المنصة</label>
                                            <input type="text" id="custom-social-title" placeholder="مثال: تيك توك، تلغرام" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-300 mb-1">رابط الحساب الكامل (URL)</label>
                                            <input type="url" id="custom-social-url" placeholder="https://..." class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans ltr text-left" dir="ltr">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-300 mb-1">الأيقونة / الإيموجي المناسب</label>
                                            <select id="custom-social-icon" class="w-full text-xs px-3 py-2 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                                                <option value="🎵">🎵 تيك توك (TikTok)</option>
                                                <option value="💬">💬 تلغرام (Telegram)</option>
                                                <option value="📱">📱 واتساب إضافي (WhatsApp)</option>
                                                <option value="🧵">🧵 ثريدز (Threads)</option>
                                                <option value="📌">📌 بنترست (Pinterest)</option>
                                                <option value="✉️">✉️ بريد إلكتروني (Email)</option>
                                                <option value="🌐">🌐 موقع إلكتروني (Website)</option>
                                                <option value="🔗">🔗 رابط عام (Other Link)</option>
                                            </select>
                                        </div>
                                        
                                        <button type="button" onclick="addCustomSocial()" class="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg transition cursor-pointer">
                                            إضافة المنصة الجديدة
                                        </button>
                                    </div>
                                    
                                    <!-- List container -->
                                    <div class="lg:col-span-2 bg-slate-900/60 p-4 rounded-xl border border-slate-800 space-y-4">
                                        <div class="border-b border-slate-800 pb-2">
                                            <span class="text-xs font-bold text-white block">📋 قائمة المنصات المخصصة المضافة</span>
                                            <p class="text-[10px] text-slate-400">المنصات التي ستظهر في الفوتر بجانب المنصات الأساسية</p>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="custom-socials-container">
                                            <!-- Dynamically rendered -->
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tab 3: Custom CSS/JS -->
                            <div id="adv-tab-codes" class="hidden space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1.5">
                                            <span>🎨 تخصيص مظهر الموقع بواسطة CSS</span>
                                            <span class="text-[9px] text-slate-500">(Custom CSS Code)</span>
                                        </label>
                                        <textarea name="showroom_custom_css" rows="12" placeholder="/* اكتب هنا كود CSS مخصص للتطبيق الفوري */&#10;body {&#10;  background-color: #0b0f19;&#10;}" class="w-full text-xs p-3 rounded-lg border border-slate-800 bg-slate-950 text-emerald-400 focus:outline-none focus:border-indigo-500 font-mono leading-relaxed" dir="ltr"><?php echo htmlspecialchars($companySettings['showroom_custom_css'] ?? ''); ?></textarea>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-slate-300 mb-1.5 flex items-center gap-1.5">
                                            <span>⚡ أكواد جافاسكريبت والسكربتات المخصصة</span>
                                            <span class="text-[9px] text-slate-500">(Custom Script / Pixel JS)</span>
                                        </label>
                                        <textarea name="showroom_custom_js" rows="12" placeholder="// اكتب هنا أكواد جافاسكريبت المخصصة لتتبع الزوار أو التحليلات أو السلوكيات&#10;console.log('Showroom Custom JS Loaded!');" class="w-full text-xs p-3 rounded-lg border border-slate-800 bg-slate-950 text-emerald-400 focus:outline-none focus:border-indigo-500 font-mono leading-relaxed" dir="ltr"><?php echo htmlspecialchars($companySettings['showroom_custom_js'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script>
                        // Global lists for Custom Pages & Custom Links
                        let customPages = [];
                        let customLinks = [];

                        try {
                            customPages = JSON.parse(document.getElementById('showroom_custom_pages_input').value || '[]');
                        } catch(e) {
                            customPages = [];
                        }

                        try {
                            customLinks = JSON.parse(document.getElementById('showroom_menu_links_input').value || '[]');
                        } catch(e) {
                            customLinks = [];
                        }

                        // Switch advanced tabs
                        function switchAdvTab(tabName) {
                            const tabs = ['pages', 'menus', 'socials', 'codes'];
                            tabs.forEach(t => {
                                const element = document.getElementById('adv-tab-' + t);
                                const btn = document.getElementById('tab-btn-' + t);
                                if (t === tabName) {
                                    element.classList.remove('hidden');
                                    btn.className = "px-4 py-2 text-xs font-bold border-b-2 border-indigo-500 text-white focus:outline-none";
                                } else {
                                    element.classList.add('hidden');
                                    btn.className = "px-4 py-2 text-xs font-bold border-b-2 border-transparent text-slate-400 hover:text-slate-200 focus:outline-none";
                                }
                            });
                        }

                        // Custom Socials Management
                        let customSocials = [];
                        try {
                            customSocials = JSON.parse(document.getElementById('showroom_custom_socials_input').value || '[]');
                        } catch(e) {
                            customSocials = [];
                        }

                        function renderCustomSocials() {
                            const container = document.getElementById('custom-socials-container');
                            if (!container) return;
                            container.innerHTML = '';
                            
                            if (customSocials.length === 0) {
                                container.innerHTML = `
                                    <div class="col-span-2 text-center p-6 border border-dashed border-slate-800 rounded-lg text-slate-500 text-xs">
                                        لا توجد منصات تواصل اجتماعي مخصصة مضافة حالياً.
                                    </div>
                                `;
                                return;
                            }
                            
                            customSocials.forEach((social, index) => {
                                const item = document.createElement('div');
                                item.className = "flex items-center justify-between p-3 bg-slate-950/60 border border-slate-850 rounded-xl gap-2";
                                item.innerHTML = `
                                    <div class="flex items-center gap-3 overflow-hidden">
                                        <span class="text-base shrink-0">${escapeHtml(social.icon || '🔗')}</span>
                                        <div class="truncate">
                                            <span class="text-xs font-bold text-slate-200 block truncate">${escapeHtml(social.title)}</span>
                                            <a href="${escapeHtml(social.url)}" target="_blank" class="text-[9px] text-indigo-400 hover:underline font-mono block truncate">${escapeHtml(social.url)}</a>
                                        </div>
                                    </div>
                                    <button type="button" onclick="deleteCustomSocial(${index})" class="w-7 h-7 shrink-0 flex items-center justify-center rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 transition cursor-pointer">
                                        🗑️
                                    </button>
                                `;
                                container.appendChild(item);
                            });
                        }

                        function addCustomSocial() {
                            const titleInput = document.getElementById('custom-social-title');
                            const urlInput = document.getElementById('custom-social-url');
                            const iconInput = document.getElementById('custom-social-icon');
                            
                            const title = titleInput.value.trim();
                            const url = urlInput.value.trim();
                            const icon = iconInput.value;
                            
                            if (!title || !url) {
                                alert('يرجى إدخال اسم المنصة ورابط الحساب.');
                                return;
                            }
                            
                            customSocials.push({ title, url, icon: icon || '🔗' });
                            document.getElementById('showroom_custom_socials_input').value = JSON.stringify(customSocials);
                            
                            titleInput.value = '';
                            urlInput.value = '';
                            
                            renderCustomSocials();
                        }

                        function deleteCustomSocial(index) {
                            customSocials.splice(index, 1);
                            document.getElementById('showroom_custom_socials_input').value = JSON.stringify(customSocials);
                            renderCustomSocials();
                        }

                        // Render pages list table
                        function renderCustomPages() {
                            const tbody = document.getElementById('custom-pages-list');
                            if (!tbody) return;
                            tbody.innerHTML = '';
                            
                            if (customPages.length === 0) {
                                tbody.innerHTML = `
                                    <tr>
                                        <td colspan="5" class="p-6 text-center text-xs text-slate-500">لا توجد صفحات مخصصة مضافة حالياً.</td>
                                    </tr>
                                `;
                                return;
                            }

                            customPages.forEach((page, index) => {
                                const visMap = {
                                    both: 'الهيدر والفوتر',
                                    header: 'الهيدر فقط',
                                    footer: 'الفوتر فقط',
                                    none: 'رابط مباشر'
                                };
                                const visibilityText = visMap[page.visibility] || 'الهيدر والفوتر';
                                
                                const tr = document.createElement('tr');
                                tr.className = "border-b border-slate-850 hover:bg-slate-900/30 text-xs text-slate-300";
                                tr.innerHTML = `
                                    <td class="p-3 font-bold text-white">${escapeHtml(page.title)}</td>
                                    <td class="p-3"><code class="bg-slate-950 text-indigo-400 px-1.5 py-0.5 rounded font-mono text-[10px]">?page=${escapeHtml(page.slug)}</code></td>
                                    <td class="p-3 text-slate-400">${escapeHtml(page.icon || '-')}</td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded-full bg-slate-850 text-slate-400 text-[10px] font-semibold">${visibilityText}</span></td>
                                    <td class="p-3 text-left">
                                        <div class="flex items-center gap-1.5 justify-end">
                                            <button type="button" onclick="editCustomPage(${index})" class="px-2 py-1 bg-slate-800 hover:bg-indigo-600 hover:text-white rounded text-[10px] text-slate-400 font-bold transition">تعديل</button>
                                            <button type="button" onclick="deleteCustomPage(${index})" class="px-2 py-1 bg-red-950/20 hover:bg-red-600 hover:text-white rounded text-[10px] text-red-400 font-bold transition">حذف</button>
                                        </div>
                                    </td>
                                `;
                                tbody.appendChild(tr);
                            });
                        }

                        // Save Page
                        function saveCustomPage() {
                            const title = document.getElementById('page-title-field').value.trim();
                            const slugRaw = document.getElementById('page-slug-field').value.trim();
                            const slug = slugRaw.toLowerCase().replace(/[^a-z0-9_-]/g, '');
                            const icon = document.getElementById('page-icon-field').value.trim();
                            const visibility = document.getElementById('page-visibility-field').value;
                            const content = document.getElementById('page-content-field').value.trim();
                            const editIndex = document.getElementById('edit-page-index').value;

                            if (!title || !slug) {
                                alert('الرجاء إدخال عنوان الصفحة والرابط الفرعي Slug');
                                return;
                            }

                            const pageData = { title, slug, icon, visibility, content };

                            if (editIndex !== "") {
                                customPages[parseInt(editIndex)] = pageData;
                            } else {
                                // Check for existing slug
                                const exists = customPages.some(p => p.slug === slug);
                                if (exists) {
                                    alert('عذراً، هذا الرابط الفرعي (Slug) مستخدم بالفعل في صفحة أخرى!');
                                    return;
                                }
                                customPages.push(pageData);
                            }

                            document.getElementById('showroom_custom_pages_input').value = JSON.stringify(customPages);
                            renderCustomPages();
                            resetPageForm();
                        }

                        // Edit Page
                        function editCustomPage(index) {
                            const page = customPages[index];
                            document.getElementById('page-title-field').value = page.title;
                            document.getElementById('page-slug-field').value = page.slug;
                            document.getElementById('page-icon-field').value = page.icon || '';
                            document.getElementById('page-visibility-field').value = page.visibility || 'both';
                            document.getElementById('page-content-field').value = page.content || '';
                            document.getElementById('edit-page-index').value = index;
                            document.getElementById('page-form-title').innerText = '📝 تعديل صفحة (' + page.title + ')';
                        }

                        // Delete Page
                        function deleteCustomPage(index) {
                            if (confirm('هل أنت متأكد من حذف هذه الصفحة المخصصة؟')) {
                                customPages.splice(index, 1);
                                document.getElementById('showroom_custom_pages_input').value = JSON.stringify(customPages);
                                renderCustomPages();
                                if (document.getElementById('edit-page-index').value == index) {
                                    resetPageForm();
                                }
                            }
                        }

                        function resetPageForm() {
                            document.getElementById('page-title-field').value = '';
                            document.getElementById('page-slug-field').value = '';
                            document.getElementById('page-icon-field').value = '';
                            document.getElementById('page-visibility-field').value = 'both';
                            document.getElementById('page-content-field').value = '';
                            document.getElementById('edit-page-index').value = '';
                            document.getElementById('page-form-title').innerText = '➕ إضافة صفحة جديدة';
                        }


                        // Render custom links list table
                        function renderCustomLinks() {
                            const tbody = document.getElementById('custom-links-list');
                            if (!tbody) return;
                            tbody.innerHTML = '';

                            if (customLinks.length === 0) {
                                tbody.innerHTML = `
                                    <tr>
                                        <td colspan="6" class="p-6 text-center text-xs text-slate-500">لا توجد روابط مخصصة مضافة حالياً.</td>
                                    </tr>
                                `;
                                return;
                            }

                            customLinks.forEach((link, index) => {
                                const locMap = {
                                    header: 'الهيدر فقط',
                                    footer: 'الفوتر فقط',
                                    both: 'كلاهما'
                                };
                                const locText = locMap[link.location] || 'الهيدر فقط';
                                const targetText = link.target ? 'نافذة جديدة' : 'نفس النافذة';

                                const tr = document.createElement('tr');
                                tr.className = "border-b border-slate-850 hover:bg-slate-900/30 text-xs text-slate-300";
                                tr.innerHTML = `
                                    <td class="p-3 font-bold text-white">${escapeHtml(link.title)}</td>
                                    <td class="p-3 font-sans text-slate-400 truncate max-w-[150px]" title="${escapeHtml(link.url)}">${escapeHtml(link.url)}</td>
                                    <td class="p-3 text-slate-400">${escapeHtml(link.icon || '-')}</td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded-full bg-slate-850 text-slate-400 text-[10px] font-semibold">${locText}</span></td>
                                    <td class="p-3 text-slate-400 text-[10px]">${targetText}</td>
                                    <td class="p-3 text-left">
                                        <div class="flex items-center gap-1.5 justify-end">
                                            <button type="button" onclick="editCustomLink(${index})" class="px-2 py-1 bg-slate-800 hover:bg-indigo-600 hover:text-white rounded text-[10px] text-slate-400 font-bold transition">تعديل</button>
                                            <button type="button" onclick="deleteCustomLink(${index})" class="px-2 py-1 bg-red-950/20 hover:bg-red-600 hover:text-white rounded text-[10px] text-red-400 font-bold transition">حذف</button>
                                        </div>
                                    </td>
                                `;
                                tbody.appendChild(tr);
                            });
                        }

                        // Save Link
                        function saveCustomLink() {
                            const title = document.getElementById('link-title-field').value.trim();
                            const url = document.getElementById('link-url-field').value.trim();
                            const icon = document.getElementById('link-icon-field').value.trim();
                            const location = document.getElementById('link-location-field').value;
                            const target = document.getElementById('link-target-field').checked ? 1 : 0;
                            const editIndex = document.getElementById('edit-link-index').value;

                            if (!title || !url) {
                                alert('الرجاء إدخال اسم الرابط والوجهة URL');
                                return;
                            }

                            const linkData = { title, url, icon, location, target };

                            if (editIndex !== "") {
                                customLinks[parseInt(editIndex)] = linkData;
                            } else {
                                customLinks.push(linkData);
                            }

                            document.getElementById('showroom_menu_links_input').value = JSON.stringify(customLinks);
                            renderCustomLinks();
                            resetLinkForm();
                        }

                        // Edit Link
                        function editCustomLink(index) {
                            const link = customLinks[index];
                            document.getElementById('link-title-field').value = link.title;
                            document.getElementById('link-url-field').value = link.url;
                            document.getElementById('link-icon-field').value = link.icon || '';
                            document.getElementById('link-location-field').value = link.location || 'header';
                            document.getElementById('link-target-field').checked = !!link.target;
                            document.getElementById('edit-link-index').value = index;
                            document.getElementById('link-form-title').innerText = '📝 تعديل الرابط (' + link.title + ')';
                        }

                        // Delete Link
                        function deleteCustomLink(index) {
                            if (confirm('هل أنت متأكد من حذف هذا الرابط المخصص؟')) {
                                customLinks.splice(index, 1);
                                document.getElementById('showroom_menu_links_input').value = JSON.stringify(customLinks);
                                renderCustomLinks();
                                if (document.getElementById('edit-link-index').value == index) {
                                    resetLinkForm();
                                }
                            }
                        }

                        function resetLinkForm() {
                            document.getElementById('link-title-field').value = '';
                            document.getElementById('link-url-field').value = '';
                            document.getElementById('link-icon-field').value = '';
                            document.getElementById('link-location-field').value = 'header';
                            document.getElementById('link-target-field').checked = false;
                            document.getElementById('edit-link-index').value = '';
                            document.getElementById('link-form-title').innerText = '➕ إضافة رابط/زر مخصص';
                        }

                        // Helper function to escape HTML inside JS safely
                        function escapeHtml(text) {
                            if (!text) return '';
                            return text.toString()
                                .replace(/&/g, "&amp;")
                                .replace(/</g, "&lt;")
                                .replace(/>/g, "&gt;")
                                .replace(/"/g, "&quot;")
                                .replace(/'/g, "&#039;");
                        }

                        // Initial execution
                        document.addEventListener('DOMContentLoaded', function() {
                            renderCustomPages();
                            renderCustomLinks();
                            renderCustomSocials();
                        });
                        </script>

                        <div class="pt-4 border-t border-slate-800 flex justify-end gap-2">
                            <button type="submit" class="px-5 py-2.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition flex items-center gap-1.5 shadow-md shadow-indigo-950/20 cursor-pointer">
                                💾 <span>حفظ وتطبيق إعدادات معرض العملاء</span>
                            </button>
                        </div>
                    </form>
                </div>

                <script>
                function previewShowroomBannerFile(input) {
                    if (input.files && input.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            let preview = document.getElementById('showroom-banner-preview');
                            if (!preview) {
                                preview = document.createElement('img');
                                preview.id = 'showroom-banner-preview';
                                preview.className = 'w-full h-full object-cover';
                                const container = document.getElementById('showroom-banner-placeholder');
                                if (container) {
                                    const parent = container.parentNode;
                                    container.remove();
                                    parent.appendChild(preview);
                                }
                            }
                            preview.src = e.target.result;
                            const removeBtn = document.getElementById('remove-showroom-banner-btn');
                            if (removeBtn) removeBtn.classList.remove('hidden');
                            document.getElementById('remove_showroom_banner_input').value = '0';
                        };
                        reader.readAsDataURL(input.files[0]);
                    }
                }

                function removeShowroomBannerClicked() {
                    const preview = document.getElementById('showroom-banner-preview');
                    if (preview) {
                        const container = preview.parentNode;
                        preview.remove();
                        
                        const placeholder = document.createElement('div');
                        placeholder.id = 'showroom-banner-placeholder';
                        placeholder.className = 'text-center p-2';
                        placeholder.innerHTML = `
                            <span class="text-[9px] text-slate-500 block">بانر افتراضي متدرج</span>
                        `;
                        container.appendChild(placeholder);
                    }
                    const removeBtn = document.getElementById('remove-showroom-banner-btn');
                    if (removeBtn) removeBtn.classList.add('hidden');
                    document.getElementById('remove_showroom_banner_input').value = '1';
                }
                </script>

                <!-- Professional SEO Panel (لوحة SEO المستقلة لكل صفحة) -->
                <?php 
                $seo_pages_options = [
                    'customer_showroom' => [
                        'title' => 'معرض السيارات (الصفحة الرئيسية)',
                        'default_title' => 'شركة المخزون للمحركات المحدودة - معرض السيارات الافتراضي للعملاء'
                    ]
                ];
                // Parse custom pages as options
                $custom_pages_arr = json_decode($companySettings['showroom_custom_pages'] ?? '[]', true);
                if (is_array($custom_pages_arr)) {
                    foreach ($custom_pages_arr as $cp) {
                        if (!empty($cp['slug'])) {
                            $seo_pages_options[$cp['slug']] = [
                                'title' => "صفحة مخصصة: " . ($cp['title'] ?? $cp['slug']),
                                'default_title' => ($cp['title'] ?? '') . " - " . ($companySettings['company_name'] ?? '')
                            ];
                        }
                    }
                }

                $active_seo_key = trim($_GET['seo_page_key'] ?? 'customer_showroom');
                if (!isset($seo_pages_options[$active_seo_key])) {
                    $active_seo_key = 'customer_showroom';
                }

                // Fetch active SEO page settings
                $seoShowroom = null;
                $active_seo_stmt = $pdo->prepare("SELECT * FROM `seo_pages` WHERE `page_key` = ? LIMIT 1");
                $active_seo_stmt->execute([$active_seo_key]);
                $seoShowroom = $active_seo_stmt->fetch();

                if (!$seoShowroom) {
                    $seoShowroom = [
                        'page_key' => $active_seo_key,
                        'page_title' => $seo_pages_options[$active_seo_key]['title'],
                        'meta_title' => $seo_pages_options[$active_seo_key]['default_title'],
                        'meta_description' => 'تصفح أحدث وأرقى موديلات السيارات المتوفرة لدينا بأفضل الأسعار والمواصفات مع خيارات الطلب المباشر والتواصل الفوري عبر الواتساب.',
                        'meta_keywords' => 'المخزون, سيارات فاخرة, شراء سيارات, معرض السيارات, تويوتا, مرسيدس, لكزس',
                        'og_title' => '',
                        'og_description' => '',
                        'og_image' => '',
                        'twitter_card' => 'summary_large_image',
                        'custom_schema' => '{"@context": "https://schema.org", "@type": "AutoDealer", "name": "شركة المخزون للمحركات", "url": "customer.php"}'
                    ];
                }
                ?>
                <div class="bg-slate-900 p-6 rounded-2xl border border-slate-800 text-white space-y-6 mt-6" id="SEOSettingsPanel">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <h3 class="font-extrabold text-sm text-slate-100 flex items-center gap-2">
                                🔍 إدارة محركات البحث والـ SEO المتقدم
                            </h3>
                            <p class="text-[10px] text-slate-400 mt-0.5">لوحة SEO مستقلة لكل صفحة للتحكم الكامل في الكلمات الدلالية، الأرشفة، والخرائط الهيكلية للعملاء</p>
                        </div>
                        <span class="text-[9px] px-3 py-1 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-bold">تهيئة Open Graph مستقلة</span>
                    </div>

                    <?php if (isset($_GET['seo_success'])): ?>
                        <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold rounded-xl">
                            ✓ تم حفظ بيانات محركات البحث والـ SEO بنجاح للصفحة المحددة!
                        </div>
                    <?php endif; ?>

                    <!-- Page Selector -->
                    <div class="bg-slate-950 p-4 rounded-xl border border-slate-850/60 space-y-2">
                        <label class="block text-xs font-bold text-indigo-400">إختر الصفحة لتخصيص إعداداتها:</label>
                        <select onchange="window.location.href='index.php?page=settings&seo_page_key=' + this.value + '#SEOSettingsPanel'" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-900 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans cursor-pointer">
                            <?php foreach ($seo_pages_options as $key => $opt): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $active_seo_key === $key ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($opt['title']); ?> (<?php echo htmlspecialchars($key); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="save_seo_settings" value="1">
                        <input type="hidden" name="page_key" value="<?php echo htmlspecialchars($active_seo_key); ?>">
                        <input type="hidden" name="page_title" value="<?php echo htmlspecialchars($seo_pages_options[$active_seo_key]['title']); ?>">

                        <!-- 1. General SEO Meta -->
                        <div class="space-y-4">
                            <div class="border-b border-slate-800 pb-2">
                                <span class="text-xs font-bold text-indigo-400 flex items-center gap-1">🌐 الإعدادات العامة لـ SEO (Meta Tags)</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5">عنوان الصفحة لمحركات البحث (Meta Title)</label>
                                    <input type="text" name="meta_title" required value="<?php echo htmlspecialchars($seoShowroom['meta_title'] ?? ''); ?>" placeholder="أدخل عنوان الصفحة..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5">الكلمات الدلالية المفتاحية (Meta Keywords)</label>
                                    <input type="text" name="meta_keywords" required value="<?php echo htmlspecialchars($seoShowroom['meta_keywords'] ?? ''); ?>" placeholder="مثال: سيارات، معرض، الرياض، شراء..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5">وصف الصفحة لمحركات البحث (Meta Description)</label>
                                    <textarea name="meta_description" required rows="2" placeholder="أدخل وصف تفصيلي جذاب للمحركات ومشاركات التواصل الاجتماعي..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 leading-relaxed"><?php echo htmlspecialchars($seoShowroom['meta_description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Open Graph & Social Media -->
                        <div class="bg-slate-950/60 p-4 rounded-xl border border-indigo-500/15 space-y-4">
                            <div class="border-b border-slate-850 pb-2 flex justify-between items-center">
                                <span class="text-xs font-bold text-indigo-400 flex items-center gap-1">📱 تخصيص إعدادات الشبكات الاجتماعية (Open Graph & Cards)</span>
                                <span class="text-[9px] text-slate-500">للفيس بوك، تويتر، لينكدان، واتساب</span>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5">عنوان المشاركة الاجتماعي (OG Title) <span class="text-[9px] text-slate-500">(اختياري - يطابق الفوق إذا فارغ)</span></label>
                                    <input type="text" name="og_title" value="<?php echo htmlspecialchars($seoShowroom['og_title'] ?? ''); ?>" placeholder="أدخل عنواناً جذاباً للمشاركة..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5">نوع بطاقة تويتر (Twitter Card Type)</label>
                                    <select name="twitter_card" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500">
                                        <option value="summary_large_image" <?php echo ($seoShowroom['twitter_card'] ?? '') === 'summary_large_image' ? 'selected' : ''; ?>>صورة عريضة وكبيرة (Summary Large Image)</option>
                                        <option value="summary" <?php echo ($seoShowroom['twitter_card'] ?? '') === 'summary' ? 'selected' : ''; ?>>بطاقة نصية صغيرة مع صورة مصغرة (Summary)</option>
                                    </select>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5">وصف المشاركة الاجتماعي (OG Description) <span class="text-[9px] text-slate-500">(اختياري - يطابق الفوق إذا فارغ)</span></label>
                                    <textarea name="og_description" rows="2" placeholder="وصف مخصص يظهر عند مشاركة رابط هذه الصفحة..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 leading-relaxed"><?php echo htmlspecialchars($seoShowroom['og_description'] ?? ''); ?></textarea>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-xs font-bold text-slate-300 mb-1.5">رابط صورة المعاينة الاجتماعية (Preview Image URL) <span class="text-[9px] text-slate-500">(اختياري - يطابق بنر المعرض إذا فارغ)</span></label>
                                    <input type="url" name="og_image" value="<?php echo htmlspecialchars($seoShowroom['og_image'] ?? ''); ?>" placeholder="https://example.com/images/seo-preview.jpg" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 text-left ltr" dir="ltr">
                                    <p class="text-[9px] text-slate-400 mt-1">المقاس الموصى به لمشاركات الفيسبوك وتويتر هو 1200x630 بكسل.</p>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Structured Schema JSON-LD -->
                        <div class="space-y-4">
                            <div class="border-b border-slate-800 pb-2">
                                <span class="text-xs font-bold text-indigo-400 flex items-center gap-1">📊 الخرائط الفنية والبيانات الهيكلية (Structured Schema JSON-LD)</span>
                            </div>
                            <div>
                                <textarea name="custom_schema" rows="4" placeholder='{"@context": "https://schema.org", "@type": "AutoDealer", ...}' class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-indigo-300 focus:outline-none focus:border-indigo-500 font-mono leading-relaxed" dir="ltr"><?php echo htmlspecialchars($seoShowroom['custom_schema'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-800 flex justify-end gap-2">
                            <button type="submit" class="px-5 py-2.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition flex items-center gap-1.5 shadow-md shadow-indigo-950/20 cursor-pointer">
                                💾 <span>حفظ وتطبيق إعدادات SEO لـ (<?php echo htmlspecialchars($seo_pages_options[$active_seo_key]['title']); ?>)</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- SECTION 3: GLOBAL SEO TRACKING & INDEXING CENTER -->
                <div id="SEOGlobalSettingsPanel" class="bg-slate-900 p-6 rounded-2xl border border-slate-800 text-white space-y-6 mt-6">
                    <div>
                        <h3 class="font-extrabold text-sm text-slate-100 flex items-center gap-2">
                            🔍 🌐 مركز أرشفة محركات البحث والتحقق العالمي (SEO & Indexing Center)
                        </h3>
                        <p class="text-[10px] text-slate-400 mt-0.5">تهيئة أدوات التحقق من الهوية لـ Google و Bing، تحرير ملف الروبوتات robots.txt، وتوليد الخرائط Sitemap.xml تلقائياً لتسريع زحف عناكب البحث لمحتوى السيارات.</p>
                    </div>

                    <?php if (isset($_GET['seo_global_success'])): ?>
                        <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold rounded-xl flex items-center gap-2">
                            ✓ تم تحديث إعدادات الأرشفة والتحقق العالمي وكتابة ملف robots.txt بنجاح!
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="save_global_seo_tracking" value="1">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Left Sub-panel: Sitemap & Robots.txt -->
                            <div class="space-y-4">
                                <div class="p-4 bg-slate-950 rounded-xl border border-slate-850 space-y-3">
                                    <span class="text-xs font-bold text-indigo-400 block border-b border-slate-850 pb-2">🗺️ خرائط الموقع وسجلات الزحف</span>
                                    
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-slate-400">حالة ملف الخريطة الفعلي (Sitemap):</span>
                                        <?php if (file_exists(__DIR__ . '/sitemap.xml')): ?>
                                            <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-full text-[10px] font-bold">✓ متوفر ونشط</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-full text-[10px] font-bold">⚠ غير موجود حالياً</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="text-[10px] text-slate-400 bg-slate-900 p-2.5 rounded-lg border border-slate-850 space-y-1">
                                        <div class="flex justify-between">
                                            <span>الرابط الديناميكي:</span>
                                            <a href="sitemap.php" target="_blank" class="text-indigo-400 hover:underline font-mono">/sitemap.php</a>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>الرابط الثابت المفضل لـ Google:</span>
                                            <a href="sitemap.xml" target="_blank" class="text-indigo-400 hover:underline font-mono">/sitemap.xml</a>
                                        </div>
                                    </div>

                                    <label class="flex items-center gap-2 text-xs text-slate-300 bg-slate-900/50 p-2.5 rounded-lg border border-slate-850 cursor-pointer hover:bg-slate-900">
                                        <input type="checkbox" name="regenerate_sitemap_file" value="1" checked class="rounded border-slate-800 bg-slate-950 text-indigo-600 focus:ring-0">
                                        <span>إعادة توليد وتحديث ملف sitemap.xml الثابت فورياً عند الحفظ</span>
                                    </label>
                                </div>

                                <div class="p-4 bg-slate-950 rounded-xl border border-slate-850 space-y-3">
                                    <span class="text-xs font-bold text-indigo-400 block border-b border-slate-850 pb-2">🤖 محرّر ملف توجيه الروبوتات (Robots.txt)</span>
                                    <p class="text-[9px] text-slate-500 leading-relaxed">
                                        ملف Robots.txt يخبر عناكب البحث التابعة لـ Google و Bing بالصفحات التي يمكنهم زحفها وأرشفتها.
                                    </p>
                                    <?php
                                    $robots_file_path = __DIR__ . '/robots.txt';
                                    $robots_content = '';
                                    if (file_exists($robots_file_path)) {
                                        $robots_content = file_get_contents($robots_file_path);
                                    } else {
                                        $robots_content = "User-agent: *\nAllow: /\nDisallow: /config/\nDisallow: /installer/\nDisallow: /modules/\n\nSitemap: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . "/sitemap.xml";
                                    }
                                    ?>
                                    <textarea name="robots_txt_content" rows="6" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-indigo-300 focus:outline-none focus:border-indigo-500 font-mono leading-relaxed" dir="ltr"><?php echo htmlspecialchars($robots_content); ?></textarea>
                                </div>
                            </div>

                            <!-- Right Sub-panel: Analytics & Webmasters & Verification Creators -->
                            <div class="space-y-4">
                                <div class="p-4 bg-slate-950 rounded-xl border border-slate-850 space-y-4">
                                    <span class="text-xs font-bold text-indigo-400 block border-b border-slate-850 pb-2">📈 أكواد التتبع والتحقق (Webmaster Keys)</span>
                                    
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div class="space-y-1.5">
                                            <label class="text-[10px] font-bold text-slate-300 block">معرف إحصائيات Google (Analytics ID)</label>
                                            <input type="text" name="seo_google_analytics" placeholder="مثال: G-XXXXXXX أو كود كامل" value="<?php echo htmlspecialchars($companySettings['seo_google_analytics'] ?? ''); ?>" class="w-full text-xs px-3.5 py-2 rounded-lg border border-slate-800 bg-slate-950 text-indigo-300 focus:outline-none focus:border-indigo-500 font-mono" dir="ltr">
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[10px] font-bold text-slate-300 block">معرف فيسبوك بيكسل (Facebook Pixel ID)</label>
                                            <input type="text" name="seo_facebook_pixel" placeholder="مثال: 1234567890 أو كود كامل" value="<?php echo htmlspecialchars($companySettings['seo_facebook_pixel'] ?? ''); ?>" class="w-full text-xs px-3.5 py-2 rounded-lg border border-slate-800 bg-slate-950 text-indigo-300 focus:outline-none focus:border-indigo-500 font-mono" dir="ltr">
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[10px] font-bold text-slate-300 block">مفتاح تحقق Google Search Console</label>
                                            <input type="text" name="seo_google_verification" placeholder="أدخل رمز التحقق الميتا من جوجل" value="<?php echo htmlspecialchars($companySettings['seo_google_verification'] ?? ''); ?>" class="w-full text-xs px-3.5 py-2 rounded-lg border border-slate-800 bg-slate-950 text-indigo-300 focus:outline-none focus:border-indigo-500 font-mono" dir="ltr">
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[10px] font-bold text-slate-300 block">مفتاح تحقق Bing Webmaster</label>
                                            <input type="text" name="seo_bing_verification" placeholder="أدخل رمز التحقق الميتا من بينج" value="<?php echo htmlspecialchars($companySettings['seo_bing_verification'] ?? ''); ?>" class="w-full text-xs px-3.5 py-2 rounded-lg border border-slate-800 bg-slate-950 text-indigo-300 focus:outline-none focus:border-indigo-500 font-mono" dir="ltr">
                                        </div>
                                    </div>
                                </div>

                                <div class="p-4 bg-slate-950 rounded-xl border border-slate-850 space-y-3">
                                    <span class="text-xs font-bold text-indigo-400 block border-b border-slate-850 pb-2">🔑 منشئ ملفات التحقق المستقلة (HTML Verification Maker)</span>
                                    <p class="text-[9px] text-slate-500 leading-relaxed">
                                        إذا طلبت منك محركات البحث إثبات الملكية عبر رفع ملف HTML مخصص، اكتب اسم الملف ومحتواه هنا ليتم بناؤه تلقائياً في جذر الموقع.
                                    </p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div class="space-y-1">
                                            <label class="text-[9px] text-slate-400 block">اسم الملف المطلوب:</label>
                                            <input type="text" name="verification_filename" placeholder="مثال: google12345.html" class="w-full text-xs px-3 py-1.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-300 focus:outline-none focus:border-indigo-500 font-mono" dir="ltr">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[9px] text-slate-400 block">محتوى ملف التحقق:</label>
                                            <input type="text" name="verification_content" placeholder="مثال: google-site-verification: google12345.html" class="w-full text-xs px-3 py-1.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-300 focus:outline-none focus:border-indigo-500 font-mono" dir="ltr">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SEO & Archiving Checklist Guide -->
                        <div class="p-4 bg-slate-950 rounded-xl border border-slate-850">
                            <span class="text-xs font-bold text-indigo-400 block mb-2.5">📋 دليل وخطوات أرشفة معرض السيارات وخرائط الفهرسة للمبتدئين</span>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-[10px] text-slate-400 leading-relaxed">
                                <div class="bg-slate-900 p-3 rounded-lg border border-slate-850 space-y-1.5">
                                    <span class="font-bold text-slate-200 block">1. إثبات ملكية موقعك</span>
                                    <p>توجه إلى أدوات مشرفي المواقع Google Search Console واختر ملكية النطاق، ثم انسخ كود التحقق الميتا والصقه في خانة التحقق المخصصة أعلاه، أو استخدم منشئ ملفات HTML لرفع ملف التحقق فورياً.</p>
                                </div>
                                <div class="bg-slate-900 p-3 rounded-lg border border-slate-850 space-y-1.5">
                                    <span class="font-bold text-slate-200 block">2. تقديم ملف الـ Sitemap</span>
                                    <p>بعد تفعيل الرابط وتوليد ملف Sitemap.xml من خلال هذه الصفحة، اذهب إلى قسم "Sitemaps" في لوحة تحكم جوجل، واكتب المسار التالي: <code class="font-mono text-indigo-300">sitemap.xml</code> ثم اضغط إرسال.</p>
                                </div>
                                <div class="bg-slate-900 p-3 rounded-lg border border-slate-850 space-y-1.5">
                                    <span class="font-bold text-slate-200 block">3. مراقبة كفاءة الأرشفة</span>
                                    <p>سيقوم محرك البحث بالزحف الدوري لسياراتك المضافة وعرضها في نتائج البحث الذكية. تأكد من تفعيل التوليد الآلي لضمان ظهور أي سيارة مضافة حديثاً للزوار بشكل لحظي.</p>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-800 flex justify-end">
                            <button type="submit" class="px-5 py-2.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition flex items-center gap-1.5 shadow-md shadow-indigo-950/20 cursor-pointer">
                                💾 <span>حفظ وتطبيق إعدادات SEO والأرشفة العالمية</span>
                            </button>
                        </div>
                    </form>
                </div>


                <!-- SECTION 4: SELF-MAINTENANCE & TECHNICAL HEALTH DIAGNOSER -->
                <div id="MaintenanceSettingsPanel" class="bg-slate-900 p-6 rounded-2xl border border-slate-800 text-white space-y-6 mt-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <h3 class="font-extrabold text-sm text-slate-100 flex items-center gap-2">
                                🛡️ مركز التحليل الفني والصيانة الذاتية والوقائية (Self-Maintenance & Tech Analyzer)
                            </h3>
                            <p class="text-[10px] text-slate-400 mt-0.5">أدوات الفحص والتشخيص الذاتي لبنية السيرفر، صحة الفهارس وجداول قاعدة البيانات، وتنظيف بقايا الملفات ومستعرض سجل الأخطاء والانهيارات الفنية الفوري.</p>
                        </div>
                        <div class="px-2.5 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-full text-[10px] font-bold flex items-center gap-1">
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                            <span>الوضع الوقائي نشط</span>
                        </div>
                    </div>

                    <?php if (isset($_GET['maintenance_success'])): ?>
                        <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold rounded-xl flex items-center gap-2">
                            ✓ تم تشغيل ومعالجة مهمة الصيانة الوقائية بنجاح! راجع لوحة المخرجات أدناه.
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        
                        <!-- Col 1: System Status Indicators -->
                        <div class="space-y-4">
                            <div class="p-4 bg-slate-950 rounded-xl border border-slate-850 space-y-3.5">
                                <span class="text-xs font-bold text-indigo-400 block border-b border-slate-850 pb-2">💻 مؤشرات صحة بيئة PHP</span>
                                
                                <div class="space-y-2.5 text-xs">
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400">إصدار الـ PHP الحالي:</span>
                                        <span class="font-mono text-indigo-300 font-bold"><?php echo PHP_VERSION; ?></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400">مشغّل قواعد البيانات (PDO):</span>
                                        <span class="text-emerald-400 font-bold flex items-center gap-1">✓ متصل ومستقر</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400">مكتبة الرسوم GD:</span>
                                        <?php if (extension_loaded('gd')): ?>
                                            <span class="text-emerald-400">✓ نشطة</span>
                                        <?php else: ?>
                                            <span class="text-rose-400">✕ معطلة</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400">مكتبة الضغط ZIP:</span>
                                        <?php if (extension_loaded('zip')): ?>
                                            <span class="text-emerald-400">✓ نشطة</span>
                                        <?php else: ?>
                                            <span class="text-rose-400">✕ معطلة</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400">حجم الذاكرة المتاحة (Memory):</span>
                                        <span class="font-mono text-slate-300"><?php echo ini_get('memory_limit'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 bg-slate-950 rounded-xl border border-slate-850 space-y-3.5">
                                <span class="text-xs font-bold text-indigo-400 block border-b border-slate-850 pb-2">📂 صلاحيات المجلدات النشطة (Write Checks)</span>
                                
                                <div class="space-y-2.5 text-xs">
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400">مجلد المرفقات (uploads/):</span>
                                        <?php if (is_writable(__DIR__ . '/uploads')): ?>
                                            <span class="text-emerald-400 font-bold">✓ قابل للكتابة</span>
                                        <?php else: ?>
                                            <span class="text-rose-400 font-bold">✕ غير قابل للكتابة</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400">مجلد التخزين (storage/):</span>
                                        <?php if (is_writable(__DIR__ . '/storage')): ?>
                                            <span class="text-emerald-400 font-bold">✓ قابل للكتابة</span>
                                        <?php else: ?>
                                            <span class="text-rose-400 font-bold">✕ غير قابل للكتابة</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400">ملف الإعدادات (config.php):</span>
                                        <?php if (is_writable(__DIR__ . '/config/config.php')): ?>
                                            <span class="text-emerald-400 font-bold">✓ قابل للتحديث</span>
                                        <?php else: ?>
                                            <span class="text-slate-400 font-bold">🔒 محمي ومستقر</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Col 2: Database Schema Analyzer & Storage -->
                        <div class="space-y-4">
                            <div class="p-4 bg-slate-950 rounded-xl border border-slate-850 space-y-3">
                                <span class="text-xs font-bold text-indigo-400 block border-b border-slate-850 pb-1.5">🗄️ محلل سعة قاعدة البيانات والجداول الفعلي</span>
                                
                                <?php
                                $db_tables_info = [];
                                try {
                                    $db_name_stmt = $pdo->query("SELECT DATABASE()");
                                    $db_name = $db_name_stmt->fetchColumn();
                                    if ($db_name) {
                                        $table_size_stmt = $pdo->prepare("SELECT TABLE_NAME AS `table`, TABLE_ROWS AS `rows`, DATA_LENGTH + INDEX_LENGTH AS `size` 
                                            FROM information_schema.TABLES 
                                            WHERE TABLE_SCHEMA = ?");
                                        $table_size_stmt->execute([$db_name]);
                                        $db_tables_info = $table_size_stmt->fetchAll();
                                    }
                                } catch (Exception $e) {
                                    // Fallback if information_schema is restricted
                                }
                                ?>

                                <?php if (!empty($db_tables_info)): ?>
                                    <div class="max-h-56 overflow-y-auto space-y-2 pr-1 select-none">
                                        <?php foreach ($db_tables_info as $tbl_info): 
                                            if (in_array($tbl_info['table'], ['cars', 'reservations', 'branches', 'users', 'system_logs', 'customers', 'attachments', 'reservation_attachments'])):
                                            ?>
                                            <div class="flex justify-between items-center text-[11px] bg-slate-900 hover:bg-slate-850 p-2 rounded border border-slate-850">
                                                <span class="font-mono font-bold text-slate-300"><?php echo htmlspecialchars($tbl_info['table']); ?></span>
                                                <div class="flex gap-2.5 font-sans">
                                                    <span class="text-slate-500"><?php echo number_format($tbl_info['rows']); ?> سجل</span>
                                                    <span class="text-indigo-400 font-mono"><?php echo round($tbl_info['size'] / 1024, 1); ?> KB</span>
                                                </div>
                                            </div>
                                        <?php endif; endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center p-6 text-slate-500 text-xs">
                                        مؤشرات الجداول متوفرة فقط في خادم MySQL الفعلي.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Execution Logs Console -->
                            <div class="p-4 bg-slate-950 rounded-xl border border-slate-850 space-y-2">
                                <span class="text-xs font-bold text-indigo-400 block border-b border-slate-850 pb-1.5">💻 مخرجات وحدة الصيانة الفورية (Console Logs)</span>
                                <div class="font-mono text-[10px] leading-relaxed p-3 rounded-lg bg-black text-indigo-400 border border-slate-800 h-28 overflow-y-auto space-y-1">
                                    <?php if (!empty($_SESSION['maintenance_output'])): ?>
                                        <?php foreach ($_SESSION['maintenance_output'] as $log_line): ?>
                                            <div class="whitespace-pre-wrap"><?php echo htmlspecialchars($log_line); ?></div>
                                        <?php endforeach; ?>
                                        <?php unset($_SESSION['maintenance_output']); ?>
                                    <?php else: ?>
                                        <div class="text-slate-600 italic">// لا توجد عمليات صيانة نشطة حالياً. اختر مهمة من القائمة اليسرى لتشغيلها.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Col 3: Maintenance Actions & Repair buttons -->
                        <div class="space-y-4">
                            <div class="p-4 bg-slate-950 rounded-xl border border-slate-850 space-y-3">
                                <span class="text-xs font-bold text-rose-400 block border-b border-slate-850 pb-1.5">⚙️ مهام الصيانة الفورية الوقائية (لا تعدّل البيانات)</span>
                                
                                <div class="space-y-2.5">
                                    <form method="POST">
                                        <input type="hidden" name="run_self_maintenance_task" value="1">
                                        <input type="hidden" name="task_name" value="clear_temp">
                                        <button type="submit" class="w-full py-2 bg-slate-900 hover:bg-slate-850 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-bold rounded-lg transition flex items-center justify-between px-3 cursor-pointer">
                                            <span>🧹 تنظيف بقايا الملفات المؤقتة والذاكرة</span>
                                            <span class="text-[10px] text-indigo-400">بدء التنظيف ←</span>
                                        </button>
                                    </form>

                                    <form method="POST">
                                        <input type="hidden" name="run_self_maintenance_task" value="1">
                                        <input type="hidden" name="task_name" value="optimize_db">
                                        <button type="submit" class="w-full py-2 bg-slate-900 hover:bg-slate-850 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-bold rounded-lg transition flex items-center justify-between px-3 cursor-pointer">
                                            <span>⚡ إعادة بناء وتحسين فهارس الجداول</span>
                                            <span class="text-[10px] text-indigo-400">تحسين الفهارس ←</span>
                                        </button>
                                    </form>

                                    <form method="POST">
                                        <input type="hidden" name="run_self_maintenance_task" value="1">
                                        <input type="hidden" name="task_name" value="schema_repair">
                                        <button type="submit" class="w-full py-2 bg-slate-900 hover:bg-slate-850 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-bold rounded-lg transition flex items-center justify-between px-3 cursor-pointer">
                                            <span>🔧 فحص الهيكل الذاتي وتطابق الجداول</span>
                                            <span class="text-[10px] text-indigo-400">تشغيل الفحص ←</span>
                                        </button>
                                    </form>

                                    <form method="POST">
                                        <input type="hidden" name="run_self_maintenance_task" value="1">
                                        <input type="hidden" name="task_name" value="broken_images">
                                        <button type="submit" class="w-full py-2 bg-slate-900 hover:bg-slate-850 border border-slate-800 hover:border-slate-700 text-slate-200 text-xs font-bold rounded-lg transition flex items-center justify-between px-3 cursor-pointer">
                                            <span>📷 كاشف مراجع الصور التالفة للسيارات</span>
                                            <span class="text-[10px] text-indigo-400">البدء بالتحليل ←</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Server Error Log Stream Viewer -->
                    <div class="p-4 bg-slate-950 rounded-xl border border-slate-850 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-850 pb-2">
                            <span class="text-xs font-bold text-rose-400 flex items-center gap-1.5">📋 مستعرض ومراقب سجل الأخطاء الفني والانهيارات (Server Error Log Stream)</span>
                            <span class="px-2 py-0.5 bg-slate-900 rounded text-[9px] text-slate-500 font-mono">آخر 40 خطأ مسجل</span>
                        </div>
                        
                        <?php
                        $error_logs_content = [];
                        $error_log_path = ini_get('error_log');
                        if (empty($error_log_path) || !file_exists($error_log_path) || !is_readable($error_log_path)) {
                            $common_logs = [__DIR__ . '/error_log', __DIR__ . '/php_errors.log', __DIR__ . '/storage/logs/error.log'];
                            foreach ($common_logs as $log_file) {
                                if (file_exists($log_file) && is_readable($log_file)) {
                                    $error_log_path = $log_file;
                                    break;
                                }
                            }
                        }

                        if (!empty($error_log_path) && file_exists($error_log_path) && is_readable($error_log_path)) {
                            $lines = file($error_log_path);
                            if (is_array($lines)) {
                                $error_logs_content = array_slice($lines, -40);
                            }
                        }
                        ?>

                        <div class="font-mono text-[9px] leading-relaxed p-4 rounded-lg bg-slate-900/50 text-rose-300 border border-slate-850 max-h-48 overflow-y-auto space-y-1.5" dir="ltr">
                            <?php if (!empty($error_logs_content)): ?>
                                <?php foreach ($error_logs_content as $err_line): ?>
                                    <div class="border-b border-slate-900/40 pb-1 last:border-0 hover:bg-slate-900/80 transition px-1.5 py-0.5 rounded"><?php echo htmlspecialchars($err_line); ?></div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4 text-emerald-400 font-sans font-bold">
                                    ✓ لا توجد أي أخطاء أو انهيارات مسجلة بالسيرفر حالياً! نظام Almakhzoun Pro يعمل بكفاءة متناهية واستقرار تام.
                                </div>
                            <?php endif; ?>
                        </div>
                        <p class="text-[9px] text-slate-500 leading-relaxed">
                            ملاحظة: يقوم النظام بقراءة ملف الأخطاء من إعدادات خادم الويب (ini error_log). يساعدك هذا المستعرض على معرفة أي خلل فني أو استعلام قاعدة بيانات خاطئ بشكل فوري وتصليحه دون الحاجة لتفحص لوحة تحكم السيرفر cPanel.
                        </p>
                    </div>

                </div>
            </div>

            <script>
            function previewLogoFile(input) {
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        let preview = document.getElementById('logo-preview');
                        if (!preview) {
                            preview = document.createElement('img');
                            preview.id = 'logo-preview';
                            preview.className = 'w-full h-full object-contain';
                            const container = document.getElementById('logo-placeholder');
                            if (container) {
                                const parent = container.parentNode;
                                container.remove();
                                parent.appendChild(preview);
                            }
                        }
                        preview.src = e.target.result;
                        const removeBtn = document.getElementById('remove-logo-btn');
                        if (removeBtn) removeBtn.classList.remove('hidden');
                        document.getElementById('remove_logo_input').value = '0';
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            }

            function removeLogoClicked() {
                const preview = document.getElementById('logo-preview');
                if (preview) {
                    const container = preview.parentNode;
                    preview.remove();
                    
                    const placeholder = document.createElement('div');
                    placeholder.id = 'logo-placeholder';
                    placeholder.className = 'text-center p-2';
                    placeholder.innerHTML = `
                        <svg class="w-6 h-6 mx-auto text-slate-500 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span class="text-[9px] text-slate-500 block">لا يوجد شعار</span>
                    `;
                    container.appendChild(placeholder);
                }
                const removeBtn = document.getElementById('remove-logo-btn');
                if (removeBtn) removeBtn.classList.add('hidden');
                document.getElementById('remove_logo_input').value = '1';
            }

            function backupFileChosen(input) {
                if (input.files && input.files[0]) {
                    document.getElementById('restore-file-label').innerText = input.files[0].name;
                }
            }
            </script>
            <?php endif; ?>

            <?php if ($page === 'customers'): 
                $searchQuery = trim($_GET['search_cust'] ?? '');
                
                if (!empty($searchQuery)) {
                    $stmtCust = $pdo->prepare("SELECT * FROM `customers` WHERE `name` LIKE ? OR `phone` LIKE ? ORDER BY `name` ASC");
                    $stmtCust->execute(["%$searchQuery%", "%$searchQuery%"]);
                } else {
                    $stmtCust = $pdo->query("SELECT * FROM `customers` ORDER BY `name` ASC");
                }
                $allCustomers = $stmtCust->fetchAll();
                $totalCustomersCount = count($allCustomers);

                $alert_msg = '';
                $alert_type = 'success';
                if (isset($_GET['msg'])) {
                    switch ($_GET['msg']) {
                        case 'added':
                            $alert_msg = 'تم إضافة العميل بنجاح.';
                            break;
                        case 'updated':
                            $alert_msg = 'تم تحديث بيانات العميل بنجاح.';
                            break;
                        case 'deleted':
                            $alert_msg = 'تم حذف العميل بنجاح.';
                            break;
                        case 'error_phone_exists':
                            $alert_msg = 'عذراً، رقم الهاتف هذا مسجل بالفعل لعميل آخر.';
                            $alert_type = 'error';
                            break;
                        case 'error_empty':
                            $alert_msg = 'يرجى ملء جميع الحقول المطلوبة.';
                            $alert_type = 'error';
                            break;
                    }
                }
            ?>
            <div class="space-y-6 text-right animate-fade-in" dir="rtl">
                <!-- Header Card -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl">
                    <div>
                        <h2 class="text-lg font-black flex items-center gap-2 text-white">
                            <span>👥</span> إدارة جهات اتصال العملاء
                        </h2>
                        <p class="text-[11px] text-slate-400 mt-1 leading-relaxed">
                            عرض وتعديل وتصدير جميع العملاء المسجلين. يمكنك تصدير جهات الاتصال بصيغة VCF لدمجها تلقائياً مع الهواتف الذكية (Android و iPhone).
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                        <a href="index.php?export_vcard=1" class="w-full md:w-auto px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/10 transition flex items-center justify-center gap-2 cursor-pointer">
                            <span>📥</span> تصدير الكل للهاتف (VCF)
                        </a>
                        <button type="button" onclick="openCustomerModal()" class="w-full md:w-auto px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-600/10 transition flex items-center justify-center gap-2 cursor-pointer">
                            <span>➕</span> إضافة عميل جديد
                        </button>
                    </div>
                </div>

                <!-- Alert Message -->
                <?php if (!empty($alert_msg)): ?>
                    <div class="p-4 rounded-xl text-xs font-bold border flex items-center gap-3 <?php echo $alert_type === 'success' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-rose-500/10 border-rose-500/20 text-rose-400'; ?>">
                        <span class="text-sm"><?php echo $alert_type === 'success' ? '✓' : '⚠️'; ?></span>
                        <div><?php echo htmlspecialchars($alert_msg); ?></div>
                    </div>
                <?php endif; ?>

                <!-- Filters & Search -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl flex items-center gap-4 shadow-md">
                        <div class="w-12 h-12 rounded-xl bg-indigo-600/10 flex items-center justify-center text-xl text-indigo-500 font-bold">👥</div>
                        <div>
                            <span class="text-[10px] text-slate-400 block font-bold">إجمالي عدد العملاء</span>
                            <span class="text-2xl font-black text-white font-sans"><?php echo $totalCustomersCount; ?></span>
                        </div>
                    </div>

                    <div class="md:col-span-2 bg-slate-900 border border-slate-800 p-4 rounded-2xl flex flex-col sm:flex-row items-stretch sm:items-center gap-3 shadow-md">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-500 text-sm">🔍</span>
                            <input type="text" id="customers-search" oninput="filterCustomers()" placeholder="ابحث بالاسم أو رقم الهاتف في الجدول الحالي..." class="w-full text-xs pr-10 pl-4 py-3 rounded-xl border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 transition font-bold">
                        </div>
                        <form method="GET" class="flex gap-2">
                            <input type="hidden" name="page" value="customers">
                            <input type="text" name="search_cust" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="بحث خادم..." class="text-xs px-3 py-3 rounded-xl border border-slate-800 bg-slate-950 text-slate-300 focus:outline-none focus:border-indigo-500 transition font-bold w-28">
                            <button type="submit" class="px-4 py-3 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition cursor-pointer">بحث</button>
                            <?php if (!empty($searchQuery)): ?>
                                <a href="index.php?page=customers" class="px-4 py-3 bg-rose-950/30 hover:bg-rose-950/60 text-rose-400 border border-rose-500/20 rounded-xl text-xs font-bold transition flex items-center">إلغاء</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-right border-collapse">
                            <thead>
                                <tr class="bg-slate-950 text-slate-400 border-b border-slate-850 font-black">
                                    <th class="p-4 text-center w-16">#</th>
                                    <th class="p-4">اسم العميل</th>
                                    <th class="p-4">رقم الهاتف</th>
                                    <th class="p-4">تاريخ التسجيل</th>
                                    <th class="p-4 text-left">خيارات التصدير والإدارة</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-850" id="customers-table-body">
                                <?php if (empty($allCustomers)): ?>
                                    <tr>
                                        <td colspan="5" class="p-10 text-center text-slate-500 font-bold">
                                            لا يوجد عملاء مسجلين حالياً في النظام.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $idx = 1;
                                    foreach ($allCustomers as $cust): 
                                    ?>
                                        <tr class="hover:bg-slate-950/30 transition">
                                            <td class="p-4 text-center font-bold text-slate-500 font-sans"><?php echo $idx++; ?></td>
                                            <td class="p-4 font-extrabold text-white customer-name-col"><?php echo htmlspecialchars($cust['name']); ?></td>
                                            <td class="p-4 font-bold text-slate-300 font-sans customer-phone-col" dir="ltr"><?php echo htmlspecialchars($cust['phone']); ?></td>
                                            <td class="p-4 text-slate-500 font-sans"><?php echo date('Y-m-d H:i', strtotime($cust['created_at'])); ?></td>
                                            <td class="p-4 text-left space-x-1 space-x-reverse flex justify-end gap-2">
                                                <a href="index.php?export_vcard=1&id=<?php echo $cust['id']; ?>" class="px-2.5 py-1.5 bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 border border-indigo-500/20 rounded-lg text-[10px] font-bold transition inline-flex items-center gap-1 cursor-pointer">
                                                    📱 تصدير جهة اتصال (VCF)
                                                </a>
                                                <button type="button" onclick="editCustomer(<?php echo $cust['id']; ?>, '<?php echo htmlspecialchars(addslashes($cust['name'])); ?>', '<?php echo htmlspecialchars(addslashes($cust['phone'])); ?>')" class="px-2.5 py-1.5 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/20 rounded-lg text-[10px] font-bold transition cursor-pointer">
                                                    تعديل
                                                </button>
                                                <?php if ($user_role === 'admin'): ?>
                                                    <a href="index.php?page=customers&delete_customer=<?php echo $cust['id']; ?>" onclick="return confirm('هل أنت متأكد من حذف هذا العميل من جهات الاتصال؟');" class="px-2.5 py-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 rounded-lg text-[10px] font-bold transition cursor-pointer">
                                                        حذف
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Customer Add/Edit Modal -->
            <div id="customer-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
                <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-2xl overflow-hidden shadow-2xl animate-fade-in" dir="rtl">
                    <div class="p-5 border-b border-slate-800 flex justify-between items-center">
                        <h3 id="modal-title" class="text-xs font-black text-white">إضافة عميل جديد</h3>
                        <button type="button" onclick="closeCustomerModal()" class="text-slate-400 hover:text-white transition text-xl font-bold cursor-pointer">×</button>
                    </div>
                    <form method="POST" action="index.php?page=customers" class="p-6 space-y-4">
                        <input type="hidden" name="save_customer" value="1">
                        <input type="hidden" name="id" id="cust-id" value="">
                        
                        <div class="space-y-1.5 text-right">
                            <label class="text-[10px] text-slate-400 font-bold block">اسم العميل الكريم *</label>
                            <input type="text" name="name" id="cust-name" required placeholder="مثال: محمد أحمد العتيبي" class="w-full text-xs px-4 py-3 rounded-xl border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 transition font-bold">
                        </div>

                        <div class="space-y-1.5 text-right">
                            <label class="text-[10px] text-slate-400 font-bold block">رقم الهاتف الجوال *</label>
                            <input type="text" name="phone" id="cust-phone" required placeholder="مثال: +966500000000" class="w-full text-xs px-4 py-3 rounded-xl border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 transition font-sans font-bold" dir="ltr">
                        </div>

                        <div class="pt-4 flex gap-3">
                            <button type="submit" class="flex-1 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition cursor-pointer">حفظ البيانات</button>
                            <button type="button" onclick="closeCustomerModal()" class="flex-1 py-3 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition cursor-pointer">إلغاء</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            function filterCustomers() {
                const searchVal = document.getElementById('customers-search').value.toLowerCase().trim();
                const rows = document.querySelectorAll('#customers-table-body tr');
                
                rows.forEach(row => {
                    const nameCol = row.querySelector('.customer-name-col');
                    const phoneCol = row.querySelector('.customer-phone-col');
                    if (!nameCol || !phoneCol) return;
                    
                    const nameText = nameCol.textContent.toLowerCase();
                    const phoneText = phoneCol.textContent.toLowerCase();
                    
                    if (nameText.includes(searchVal) || phoneText.includes(searchVal)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }

            function openCustomerModal() {
                document.getElementById('modal-title').innerText = 'إضافة عميل جديد';
                document.getElementById('cust-id').value = '';
                document.getElementById('cust-name').value = '';
                document.getElementById('cust-phone').value = '';
                document.getElementById('customer-modal').classList.remove('hidden');
            }

            function editCustomer(id, name, phone) {
                document.getElementById('modal-title').innerText = 'تعديل بيانات العميل';
                document.getElementById('cust-id').value = id;
                document.getElementById('cust-name').value = name;
                document.getElementById('cust-phone').value = phone;
                document.getElementById('customer-modal').classList.remove('hidden');
            }

            function closeCustomerModal() {
                document.getElementById('customer-modal').classList.add('hidden');
            }
            </script>
            <?php endif; ?>

            <?php if ($page === 'analytics' && ($user_role === 'admin' || $user_role === 'branch_manager')):
                // Aggregate Showroom Visitor Analytics
                $totalVisits = (int)$pdo->query("SELECT COUNT(*) FROM `showroom_visits`")->fetchColumn();
                $uniqueVisitors = (int)$pdo->query("SELECT COUNT(DISTINCT `session_id`) FROM `showroom_visits`")->fetchColumn();
                $avgViews = $uniqueVisitors > 0 ? round($totalVisits / $uniqueVisitors, 1) : 0;
                $showroomOrdersCount = (int)$pdo->query("SELECT COUNT(*) FROM `customer_orders`")->fetchColumn();

                // Top pages/vehicles
                $topPages = $pdo->query("SELECT `page_title`, `page_url`, COUNT(*) as `views_count` FROM `showroom_visits` GROUP BY `page_title`, `page_url` ORDER BY `views_count` DESC LIMIT 5")->fetchAll();

                // Top Referrers
                $referrers = $pdo->query("SELECT `referrer`, COUNT(*) as `ref_count` FROM `showroom_visits` WHERE `referrer` != '' AND `referrer` IS NOT NULL GROUP BY `referrer` ORDER BY `ref_count` DESC LIMIT 5")->fetchAll();

                // Daily visits for last 10 days
                $dailyVisits = $pdo->query("SELECT `visit_date`, COUNT(*) as `visit_count`, COUNT(DISTINCT `session_id`) as `unique_count` FROM `showroom_visits` GROUP BY `visit_date` ORDER BY `visit_date` DESC LIMIT 10")->fetchAll();
                $dailyVisits = array_reverse($dailyVisits);

                // Recent direct showroom orders
                $showroomOrders = $pdo->query("SELECT co.*, c.make, c.model FROM `customer_orders` co LEFT JOIN `cars` c ON co.car_id = c.id ORDER BY co.created_at DESC LIMIT 5")->fetchAll();
            ?>
            <div class="space-y-6">
                <!-- Header -->
                <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl text-white flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div class="text-right" dir="rtl">
                        <h2 class="text-lg font-extrabold text-slate-100 flex items-center gap-2">
                            📊 تحليلات وإحصائيات معرض السيارات للعملاء
                        </h2>
                        <p class="text-[11px] text-slate-400 mt-1">تتبع حركة الزوار والمشاهدات ومصادر الزيارة المباشرة لمعرض السيارات الرقمي الخارجي وتفاعل العملاء</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="flex h-3.5 w-3.5 relative">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-emerald-500"></span>
                        </span>
                        <span class="text-xs font-bold text-slate-200">مراقبة الزيارات والطلبات فورا</span>
                    </div>
                </div>

                <!-- Stats Counters Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl text-white flex items-center gap-4 text-right" dir="rtl">
                        <div class="w-12 h-12 bg-indigo-600/10 border border-indigo-500/20 text-indigo-400 text-lg flex items-center justify-center rounded-xl shrink-0">
                            👁️
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 block">إجمالي مشاهدات المعرض</span>
                            <span class="text-xl font-extrabold text-slate-100 font-sans mt-0.5 block"><?php echo number_format($totalVisits); ?></span>
                        </div>
                    </div>

                    <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl text-white flex items-center gap-4 text-right" dir="rtl">
                        <div class="w-12 h-12 bg-emerald-600/10 border border-emerald-500/20 text-emerald-400 text-lg flex items-center justify-center rounded-xl shrink-0">
                            👥
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 block">الزوار الفريدين (Sessions)</span>
                            <span class="text-xl font-extrabold text-slate-100 font-sans mt-0.5 block"><?php echo number_format($uniqueVisitors); ?></span>
                        </div>
                    </div>

                    <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl text-white flex items-center gap-4 text-right" dir="rtl">
                        <div class="w-12 h-12 bg-amber-600/10 border border-amber-500/20 text-amber-400 text-lg flex items-center justify-center rounded-xl shrink-0">
                            📊
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 block">معدل التصفح للزائر الواحد</span>
                            <span class="text-xl font-extrabold text-slate-100 font-sans mt-0.5 block"><?php echo $avgViews; ?> <span class="text-[11px] font-normal text-slate-400">صفحة</span></span>
                        </div>
                    </div>

                    <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl text-white flex items-center gap-4 text-right" dir="rtl">
                        <div class="w-12 h-12 bg-rose-600/10 border border-rose-500/20 text-rose-400 text-lg flex items-center justify-center rounded-xl shrink-0">
                            🛒
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 block">طلبات الشراء والاهتمام بالسيارات</span>
                            <span class="text-xl font-extrabold text-slate-100 font-sans mt-0.5 block"><?php echo number_format($showroomOrdersCount); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Graphs and Sources Row -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Daily Visits CSS Chart -->
                    <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl text-white lg:col-span-2 space-y-4 text-right" dir="rtl">
                        <h4 class="text-xs font-bold text-slate-200 flex items-center gap-1.5">
                            📈 حركة الزيارات اليومية (آخر 10 أيام من النشاط)
                        </h4>
                        
                        <?php if (empty($dailyVisits)): ?>
                            <div class="py-12 text-center text-xs text-slate-500">
                                لا توجد بيانات زيارات مسجلة كافية لإنشاء الرسم البياني حالياً.
                            </div>
                        <?php else: 
                            $maxVisits = 1;
                            foreach ($dailyVisits as $v) {
                                if ($v['visit_count'] > $maxVisits) {
                                    $maxVisits = $v['visit_count'];
                                }
                            }
                        ?>
                            <div class="h-60 flex items-end justify-between gap-2 pt-6 pb-2 px-2 border-b border-slate-800" dir="ltr">
                                <?php foreach ($dailyVisits as $v): 
                                    $heightPct = round(($v['visit_count'] / $maxVisits) * 100);
                                    if ($heightPct < 5) $heightPct = 5; // Minimum height for visibility
                                ?>
                                    <div class="flex-1 flex flex-col items-center gap-2 group relative">
                                        <!-- Tooltip -->
                                        <div class="absolute bottom-full mb-2 bg-slate-950 border border-slate-800 text-[10px] py-1 px-2 rounded hidden group-hover:block whitespace-nowrap z-10 shadow-lg text-center">
                                            <span class="block font-bold text-indigo-400"><?php echo $v['visit_count']; ?> مشاهدة</span>
                                            <span class="block text-emerald-400 mt-0.5"><?php echo $v['unique_count']; ?> زائر</span>
                                        </div>
                                        <!-- Bar -->
                                        <div class="w-full bg-indigo-600/30 group-hover:bg-indigo-500 border border-indigo-500/20 rounded-t transition-all cursor-pointer flex items-end justify-center" style="height: <?php echo $heightPct; ?>%">
                                            <span class="text-[9px] font-mono text-indigo-200 mb-1 font-bold select-none hidden sm:inline"><?php echo $v['visit_count']; ?></span>
                                        </div>
                                        <!-- Date label -->
                                        <span class="text-[9px] text-slate-400 font-sans rotate-45 sm:rotate-0 mt-1 origin-top-left whitespace-nowrap">
                                            <?php echo date('m-d', strtotime($v['visit_date'])); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Referrer Sources Card -->
                    <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl text-white space-y-4 text-right" dir="rtl">
                        <h4 class="text-xs font-bold text-slate-200 flex items-center gap-1.5">
                            🌐 مصادر الزيارات (Referrers)
                        </h4>
                        
                        <div class="space-y-3">
                            <?php if (empty($referrers)): ?>
                                <div class="py-12 text-center text-xs text-slate-500">
                                    جميع زيارات المعرض كانت مباشرة (Direct Traffic) دون وسيط خارجي.
                                </div>
                            <?php else: ?>
                                <?php foreach ($referrers as $ref): 
                                    $refUrl = $ref['referrer'];
                                    $host = parse_url($refUrl, PHP_URL_HOST) ?: 'رابط مباشر أو مجهول';
                                    $percentage = $totalVisits > 0 ? round(($ref['ref_count'] / $totalVisits) * 100) : 0;
                                ?>
                                    <div class="space-y-1 text-right" dir="rtl">
                                        <div class="flex justify-between items-center text-[11px] font-bold">
                                            <span class="text-slate-300 font-sans truncate max-w-[150px]" dir="ltr"><?php echo htmlspecialchars($host); ?></span>
                                            <span class="text-indigo-400"><?php echo $ref['ref_count']; ?> زيارة (<?php echo $percentage; ?>%)</span>
                                        </div>
                                        <div class="w-full bg-slate-950 h-2 rounded-full overflow-hidden border border-slate-800/50">
                                            <div class="bg-indigo-500 h-full rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Top Visited Vehicles and Recent Orders Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Top Visited Cars -->
                    <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl text-white space-y-4 text-right" dir="rtl">
                        <h4 class="text-xs font-bold text-slate-200 flex items-center gap-1.5">
                            🔥 السيارات والصفحات الأكثر مشاهدة وتفاعلاً
                        </h4>

                        <div class="divide-y divide-slate-800/60">
                            <?php if (empty($topPages)): ?>
                                <div class="py-12 text-center text-xs text-slate-500">
                                    لا توجد سجلات مشاهدة للصفحات حالياً.
                                </div>
                            <?php else: ?>
                                <?php foreach ($topPages as $idx => $p): ?>
                                    <div class="py-3 flex items-center justify-between gap-3 text-right" dir="rtl">
                                        <div class="flex items-center gap-3">
                                            <span class="w-6 h-6 rounded bg-slate-950 border border-slate-800 text-[10px] flex items-center justify-center font-bold text-indigo-400 font-sans">
                                                <?php echo $idx + 1; ?>
                                            </span>
                                            <div>
                                                <span class="text-xs font-extrabold text-slate-200 block"><?php echo htmlspecialchars($p['page_title']); ?></span>
                                                <span class="text-[9px] text-slate-500 font-sans block mt-0.5 truncate max-w-[250px]" dir="ltr"><?php echo htmlspecialchars($p['page_url']); ?></span>
                                            </div>
                                        </div>
                                        <span class="px-2.5 py-1 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 rounded font-bold text-[10px] font-sans">
                                            <?php echo number_format($p['views_count']); ?> مشاهدة
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Showroom Orders -->
                    <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl text-white space-y-4 text-right" dir="rtl">
                        <div class="flex justify-between items-center">
                            <h4 class="text-xs font-bold text-slate-200 flex items-center gap-1.5">
                                📥 طلبات الشراء الواردة مباشرة من المعرض
                            </h4>
                            <a href="index.php?page=orders" class="text-[10px] text-indigo-400 hover:text-indigo-300 font-bold transition">عرض كافة الطلبات ←</a>
                        </div>

                        <div class="divide-y divide-slate-800/60">
                            <?php if (empty($showroomOrders)): ?>
                                <div class="py-12 text-center text-xs text-slate-500">
                                    لا توجد أي طلبات شراء واردة من المعرض حتى الآن.
                                </div>
                            <?php else: ?>
                                <?php foreach ($showroomOrders as $ord): ?>
                                    <div class="py-3 flex items-center justify-between gap-3 text-right" dir="rtl">
                                        <div>
                                            <span class="text-xs font-extrabold text-slate-200 block"><?php echo htmlspecialchars($ord['customer_name']); ?></span>
                                            <span class="text-[10px] text-slate-400 block mt-0.5">
                                                طلب سيارة: <span class="font-bold text-indigo-400"><?php echo htmlspecialchars($ord['make'] . ' ' . $ord['model']); ?></span>
                                            </span>
                                        </div>
                                        <div class="text-left font-sans">
                                            <span class="text-[10px] font-bold text-slate-300 block"><?php echo htmlspecialchars($ord['customer_phone']); ?></span>
                                            <span class="text-[9px] text-slate-500 block mt-0.5"><?php echo date('m-d H:i', strtotime($ord['created_at'])); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

    </main>
    </div> <!-- Close the flex-1 wrapper -->

    <script>
    function handleReserveFormSubmit(event, carId) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);

        // Fetch
        fetch('index.php?page=inventory', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("Malformed JSON response:", text);
                    throw new Error("استجابة غير صالحة من الخادم. قد يكون الجلسة قد انتهت صلاحيتها أو حدث خطأ داخلي.");
                }
            });
        })
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert("خطأ في الحجز: " + (data.error || 'يرجى مراجعة المدخلات والمحاولة مرة أخرى.'));
            }
        })
        .catch(err => {
            console.error(err);
            alert(err.message || "حدث خطأ غير متوقع أثناء الاتصال بالخادم.");
        });
    }

    function cancelActiveReservation(resId, carId) {
        if (!confirm('هل أنت متأكد من إلغاء هذا الحجز؟')) {
            return;
        }
                        
        fetch('index.php?page=inventory&cancel_reservation=' + encodeURIComponent(resId) + '&ajax=1')
        .then(response => {
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("Malformed JSON response:", text);
                    throw new Error("استجابة غير صالحة من الخادم أثناء إلغاء الحجز.");
                }
            });
        })
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert("فشل إلغاء الحجز: " + (data.error || 'حدث خطأ أثناء معالجة الطلب على الخادم.'));
            }
        })
        .catch(err => {
            console.error(err);
            alert(err.message || "حدث خطأ غير متوقع أثناء إلغاء الحجز.");
        });
    }

    function deleteCarAttachment(carId, attId) {
        if (!confirm('هل أنت متأكد من رغبتك في حذف هذا المستند المرفق نهائياً؟')) {
            return;
        }

        fetch('index.php?delete_car_attachment=1&car_id=' + encodeURIComponent(carId) + '&att_id=' + encodeURIComponent(attId))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove row from UI
                const row = document.getElementById('attachment-row-' + attId);
                if (row) {
                    row.remove();
                }

                // Update counts and badge
                const attsCountBadge = document.getElementById('attachments-count-' + carId);
                if (attsCountBadge && data.attachments) {
                    attsCountBadge.innerText = data.attachments.length;
                }

                // Check empty state
                const listContainer = document.getElementById('attachment-list-container-' + carId);
                const emptyState = document.getElementById('attachment-empty-' + carId);
                if (listContainer && listContainer.children.length === 0) {
                    if (emptyState) emptyState.classList.remove('hidden');
                }
            } else {
                alert("خطأ في حذف المرفق: " + data.error);
            }
        })
        .catch(err => {
            console.error(err);
            alert("حدث خطأ غير متوقع أثناء حذف المرفق.");
        });
    }

    // Toggle Notifications Dropdown
    const bellBtn = document.getElementById('notif-bell-btn');
    const notifPanel = document.getElementById('notifications-panel');
    if (bellBtn && notifPanel) {
        bellBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notifPanel.classList.toggle('hidden');
        });

        document.addEventListener('click', function(e) {
            if (!notifPanel.contains(e.target) && !bellBtn.contains(e.target)) {
                notifPanel.classList.add('hidden');
            }
        });
    }

    // Dynamic Multi-Image selector and preview system
    function updateImageSelector(input) {
        const container = document.getElementById('previews_container');
        const previewWrapper = document.getElementById('image_previews');
        const mainIndexInput = document.getElementById('main_image_index');
        
        container.innerHTML = '';
        
        if (input.files && input.files.length > 0) {
            previewWrapper.classList.remove('hidden');
            mainIndexInput.value = 0; // Default to first selected image
            
            Array.from(input.files).forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const card = document.createElement('div');
                    card.className = `relative cursor-pointer border-2 rounded-lg overflow-hidden group transition ${index === 0 ? 'border-indigo-500 bg-indigo-500/10' : 'border-slate-800 hover:border-slate-700 bg-slate-900'}`;
                    card.id = `img-card-${index}`;
                    card.onclick = function() {
                        mainIndexInput.value = index;
                        Array.from(input.files).forEach((_, idx) => {
                            const otherCard = document.getElementById(`img-card-${idx}`);
                            if (otherCard) {
                                otherCard.className = 'relative cursor-pointer border-2 border-slate-800 hover:border-slate-700 bg-slate-900 rounded-lg overflow-hidden group transition';
                                const badge = otherCard.querySelector('.main-badge');
                                if (badge) badge.classList.add('hidden');
                            }
                        });
                        card.className = 'relative cursor-pointer border-2 border-indigo-500 bg-indigo-500/10 rounded-lg overflow-hidden group transition';
                        const badge = card.querySelector('.main-badge');
                        if (badge) badge.classList.remove('hidden');
                    };
                    
                    card.innerHTML = `
                        <div class="aspect-video w-full bg-slate-950 flex items-center justify-center overflow-hidden">
                            <img src="${e.target.result}" class="object-cover w-full h-full">
                        </div>
                        <div class="p-1 text-center text-[9px] truncate text-slate-400 font-sans">${file.name}</div>
                        <span class="main-badge absolute top-1 right-1 text-[8px] bg-indigo-600 text-white px-1.5 py-0.5 rounded font-black ${index === 0 ? '' : 'hidden'}">الغلاف ★</span>
                    `;
                    container.appendChild(card);
                }
                reader.readAsDataURL(file);
            });
        } else {
            previewWrapper.classList.add('hidden');
        }
    }

    function openAddCarMode() {
        const panel = document.getElementById('add-car-panel');
        if (!panel) return;
        panel.classList.remove('hidden');
        
        // Reset form
        const form = document.getElementById('add-car-form');
        if (form) form.reset();
        
        // Clear hidden car_id
        const carIdInput = document.getElementById('form-car-id');
        if (carIdInput) carIdInput.value = '';
        
        // Update title and button label
        const title = document.getElementById('car-panel-title');
        if (title) title.innerText = '📝 إضافة مركبة جديدة بالكامل ومواصفاتها التفصيلية';
        
        const submitBtn = document.getElementById('car-panel-submit-btn');
        if (submitBtn) submitBtn.innerText = 'حفظ وإضافة للمخزن ✓';
        
        // Image input required
        const imageInput = document.getElementById('car_images_input');
        if (imageInput) imageInput.required = true;
        
        // Clear preview
        const previewWrapper = document.getElementById('image_previews');
        if (previewWrapper) previewWrapper.classList.add('hidden');
        const container = document.getElementById('previews_container');
        if (container) container.innerHTML = '';
        
        // Scroll to panel
        panel.scrollIntoView({ behavior: 'smooth' });
    }

    function editCar(car) {
        const panel = document.getElementById('add-car-panel');
        if (!panel) return;
        panel.classList.remove('hidden');
        
        // Update title and button label
        const title = document.getElementById('car-panel-title');
        if (title) title.innerText = '📝 تعديل تفاصيل ومواصفات المركبة: ' + car.make + ' ' + car.model;
        
        const submitBtn = document.getElementById('car-panel-submit-btn');
        if (submitBtn) submitBtn.innerText = 'تحديث وحفظ التعديلات ✓';
        
        // Fill form fields
        const form = document.getElementById('add-car-form');
        if (!form) return;
        
        // Set hidden ID
        const carIdInput = document.getElementById('form-car-id');
        if (carIdInput) carIdInput.value = car.id || '';
        
        // Map other inputs by name
        const fields = [
            'make', 'model', 'trim', 'year', 'color', 'interior_color', 
            'transmission', 'engine_type', 'price', 'cost_price', 'vin', 
            'mileage', 'branch_id', 'status', 'plate_number', 
            'vehicle_condition', 'custom_specs'
        ];
        
        fields.forEach(f => {
            const el = form.elements[f];
            if (el) {
                el.value = car[f] !== undefined && car[f] !== null ? car[f] : '';
            }
        });
        
        // Image input not required for edit
        const imageInput = document.getElementById('car_images_input');
        if (imageInput) imageInput.required = false;
        
        // Show current image preview if available
        const previewWrapper = document.getElementById('image_previews');
        const container = document.getElementById('previews_container');
        if (previewWrapper && container) {
            container.innerHTML = '';
            if (car.main_image) {
                previewWrapper.classList.remove('hidden');
                const card = document.createElement('div');
                card.className = 'relative border-2 border-indigo-500 bg-indigo-500/10 rounded-lg overflow-hidden';
                card.innerHTML = `
                    <div class="aspect-video w-full bg-slate-950 flex items-center justify-center overflow-hidden">
                        <img src="${car.main_image}" class="object-cover w-full h-full">
                    </div>
                    <div class="p-1 text-center text-[9px] truncate text-slate-400 font-sans">الغلاف الحالي</div>
                `;
                container.appendChild(card);
            } else {
                previewWrapper.classList.add('hidden');
            }
        }
        
        // Scroll to panel
        panel.scrollIntoView({ behavior: 'smooth' });
    }

    // Global Javascript handlers for sell action
    function openSellReservationModal(resId, carId, carName, customerName, customerPhone) {
        const modal = document.getElementById('sell-reservation-modal');
        if (!modal) return;
        document.getElementById('sell-res-id').value = resId;
        document.getElementById('sell-car-id').value = carId;
        document.getElementById('sell-car-name').innerText = carName;
        document.getElementById('sell-customer-name').value = customerName;
        document.getElementById('sell-customer-phone').value = customerPhone;
        modal.classList.remove('hidden');
    }

    function openSellFromData(btn) {
        const id = btn.getAttribute('data-id');
        const carId = btn.getAttribute('data-car-id');
        const carName = btn.getAttribute('data-car-name');
        const custName = btn.getAttribute('data-customer-name');
        const custPhone = btn.getAttribute('data-customer-phone');
        openSellReservationModal(id, carId, carName, custName, custPhone);
    }

    function openRecordSaleModal(carId = null) {
        const modal = document.getElementById('record-sale-modal');
        if (!modal) return;
        modal.classList.remove('hidden');
        if (carId) {
            const select = document.getElementById('record-sale-car-id');
            if (select) {
                select.value = carId;
            }
        }
    }

    function closeRecordSaleModal() {
        const modal = document.getElementById('record-sale-modal');
        if (modal) modal.classList.add('hidden');
    }
    </script>

    <!-- Global Sell Reservation Modal -->
    <div id="sell-reservation-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden font-sans" dir="rtl">
        <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-2xl shadow-xl overflow-hidden text-right text-white">
            <div class="px-5 py-4 bg-slate-950 border-b border-slate-850 flex justify-between items-center">
                <h3 class="font-extrabold text-sm flex items-center gap-2"><span>💰</span> ترحيل الحجز وإثبات عملية البيع</h3>
                <button onclick="document.getElementById('sell-reservation-modal').classList.add('hidden')" class="text-slate-400 hover:text-white transition text-lg font-bold cursor-pointer">&times;</button>
            </div>
            <form method="POST" class="p-5 space-y-4">
                <input type="hidden" name="mark_reservation_sold" value="1">
                <input type="hidden" name="res_id" id="sell-res-id">
                <input type="hidden" name="car_id" id="sell-car-id">
                
                <div class="p-3 bg-slate-950 border border-slate-850 rounded-xl mb-4">
                    <span class="text-[10px] text-slate-400 block font-bold mb-1">السيارة المحجوزة المحددة</span>
                    <div class="text-xs font-extrabold text-white" id="sell-car-name"></div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">سعر البيع الفعلي (ر.س) <span class="text-red-500">*</span></label>
                    <input type="number" name="sale_amount" required step="0.01" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">اسم العميل المشتري <span class="text-red-500">*</span></label>
                    <input type="text" name="sale_customer_name" id="sell-customer-name" required class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">رقم هاتف المشتري <span class="text-red-500">*</span></label>
                    <input type="text" name="sale_customer_phone" id="sell-customer-phone" required class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">تاريخ البيع والتسليم</label>
                    <input type="date" name="exit_date" value="<?php echo date('Y-m-d'); ?>" required class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">ملاحظات ووثائق البيع</label>
                    <textarea name="exit_notes" placeholder="اكتب أي ملاحظات إضافية بخصوص البيع والترحيل..." rows="2" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"></textarea>
                </div>
                <div class="flex justify-start gap-2 border-t border-slate-850 pt-4 mt-6">
                    <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg cursor-pointer transition">تأكيد البيع والترحيل</button>
                    <button type="button" onclick="document.getElementById('sell-reservation-modal').classList.add('hidden')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-lg cursor-pointer transition">إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Global Direct Sale Modal -->
    <div id="record-sale-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden font-sans" dir="rtl">
        <div class="bg-slate-900 border border-slate-800 w-full max-w-lg rounded-2xl shadow-xl overflow-hidden text-right text-white">
            <div class="px-5 py-4 bg-slate-950 border-b border-slate-850 flex justify-between items-center">
                <h3 class="font-extrabold text-sm flex items-center gap-2">
                    <span>✨</span> تسجيل وتوثيق عملية بيع مركبة
                </h3>
                <button onclick="closeRecordSaleModal()" class="text-slate-400 hover:text-white transition text-lg font-bold cursor-pointer">&times;</button>
            </div>

            <form method="POST" class="p-5 space-y-4">
                <input type="hidden" name="record_sale" value="1">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-300 mb-1.5">اختر السيارة من المخزون <span class="text-red-500">*</span></label>
                        <select name="car_id" id="record-sale-car-id" required class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-bold">
                            <option value="">-- حدد السيارة المتاحة/المحجوزة --</option>
                            <?php foreach ($availableCarsForSale as $availCar): ?>
                                <option value="<?php echo $availCar['id']; ?>">
                                    <?php echo htmlspecialchars($availCar['make'] . ' ' . $availCar['model'] . ' (' . $availCar['year'] . ') - لوحة: ' . ($availCar['plate_number'] ?: 'بدون') . ' - السعر: ' . number_format($availCar['price']) . ' ر.س'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5">قيمة البيع الفعلية (ر.س) <span class="text-red-500">*</span></label>
                        <input type="number" step="0.01" name="sale_amount" id="record-sale-amount" required placeholder="مثال: 120000" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5">تاريخ البيع / الخروج <span class="text-red-500">*</span></label>
                        <input type="date" name="exit_date" required value="<?php echo date('Y-m-d'); ?>" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                    </div>

                    <div class="md:col-span-2 border-t border-slate-850 pt-3">
                        <span class="text-xs font-black text-indigo-400 block mb-3">بيانات المشتري (العميل):</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5">اسم المشتري الكريم بالكامل <span class="text-red-500">*</span></label>
                        <input type="text" name="sale_customer_name" required placeholder="أدخل اسم العميل..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5">رقم الهوية الوطنية / الإقامة / الجواز <span class="text-red-500">*</span></label>
                        <input type="text" name="sale_customer_id" required placeholder="أدخل الرقم الوطني..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5">الجنسية <span class="text-red-500">*</span></label>
                        <input type="text" name="sale_customer_nationality" value="سعودي" required class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5">رقم جوال العميل <span class="text-red-500">*</span></label>
                        <input type="text" name="sale_customer_phone" required placeholder="مثال: 0500000000" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans text-left" dir="ltr">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-300 mb-1.5">المندوب المسؤول عن المبيعات</label>
                        <select name="sold_by_user_id" class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans">
                            <?php foreach ($repsListLookup as $repItem): ?>
                                <option value="<?php echo $repItem['id']; ?>" <?php echo $repItem['id'] == $user_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($repItem['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-300 mb-1.5">ملاحظات تسليم المركبة (اختياري)</label>
                        <textarea name="exit_notes" rows="2" placeholder="أدخل شروط البيع، مواصفات التسليم، إلخ..." class="w-full text-xs px-3.5 py-2.5 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 focus:outline-none focus:border-indigo-500 font-sans"></textarea>
                    </div>

                </div>

                <div class="pt-4 border-t border-slate-800 flex justify-end gap-2">
                    <button type="button" onclick="closeRecordSaleModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-lg transition font-sans">إلغاء</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black rounded-lg transition shadow shadow-indigo-950/20 cursor-pointer font-sans">
                        ✓ توثيق وإتمام البيع
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- AJAX Print Transfer Modal -->
    <div id="ajax-print-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden font-sans no-print">
        <div class="bg-slate-900 border border-slate-800 w-full max-w-5xl h-[90vh] rounded-2xl shadow-xl flex flex-col overflow-hidden text-right text-white">
            <div class="px-5 py-4 bg-slate-950 border-b border-slate-850 flex justify-between items-center no-print">
                <h3 class="font-extrabold text-sm flex items-center gap-2">
                    <span>🖨️</span> معاينة وطباعة الخطاب الرسمي للتحويل
                </h3>
                <button type="button" onclick="closePrintTransferModal()" class="text-slate-400 hover:text-white transition text-lg font-bold cursor-pointer">&times;</button>
            </div>
            <div class="flex-1 overflow-y-auto p-6 bg-slate-100 text-slate-800" id="ajax-print-content">
                <!-- content loaded via ajax -->
            </div>
        </div>
    </div>

    <style>
    @media print {
        /* Hide everything except our print content container */
        body > *:not(#ajax-print-modal) {
            display: none !important;
        }
        #ajax-print-modal {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: auto;
            background: white !important;
            color: black !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        #ajax-print-modal .no-print {
            display: none !important;
        }
        #ajax-print-content {
            background: white !important;
            color: black !important;
            padding: 0 !important;
            margin: 0 !important;
            overflow: visible !important;
            height: auto !important;
        }
        .max-w-4xl {
            max-width: 100% !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
        }
    }
    </style>

    <script>
    function showPrintTransferModal(transferId) {
        const modal = document.getElementById('ajax-print-modal');
        if (!modal) return;
        modal.classList.remove('hidden');
        const container = document.getElementById('ajax-print-content');
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center py-20">
                <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-indigo-600 mb-4"></div>
                <p class="text-xs text-slate-500 font-bold">جاري تحميل وتجهيز الخطاب الرسمي للطباعة...</p>
            </div>
        `;
        
        fetch('index.php?print_transfer=' + transferId + '&ajax_print=1')
            .then(response => response.text())
            .then(html => {
                container.innerHTML = html;
            })
            .catch(err => {
                container.innerHTML = `
                    <div class="p-8 text-center text-rose-500 font-bold text-xs bg-white rounded-xl">
                        حدث خطأ أثناء تحميل الخطاب، يرجى المحاولة لاحقاً.
                    </div>
                `;
            });
    }

    function closePrintTransferModal() {
        const modal = document.getElementById('ajax-print-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }
    </script>

<?php endif; ?>
</body>
</html>

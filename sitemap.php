<?php
/**
 * Almakhzoun Pro - Automatic Sitemap Generator
 * Generates both dynamic XML and a physical sitemap.xml file at the root.
 * Fully integrated with PHP 8.2+ and PDO database.
 */

define('CONFIG_PATH', __DIR__ . '/config/config.php');

if (!file_exists(CONFIG_PATH)) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "Config file not found.";
    exit;
}

$config = require CONFIG_PATH;
$dbConfig = $config['db'];
$dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "Database connection failed.";
    exit;
}

/**
 * Automatically detects the website base URL.
 * Falls back to localhost if running in CLI or if host is missing.
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['SERVER_POST'] == 443 || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/sitemap.php';
    $dir = rtrim(dirname($scriptName), '/\\');
    return $protocol . $host . $dir . '/';
}

/**
 * Generates the sitemap XML string.
 */
function buildSitemapXml($pdo) {
    $baseUrl = getBaseUrl();
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    // 1. Add Main Showroom Page
    $xml .= "  <url>\n";
    $xml .= "    <loc>" . htmlspecialchars($baseUrl . "index.php") . "</loc>\n";
    $xml .= "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
    $xml .= "    <changefreq>daily</changefreq>\n";
    $xml .= "    <priority>1.0</priority>\n";
    $xml .= "  </url>\n";

    // 2. Fetch Company Settings & Custom Showroom Pages
    try {
        $settingsQuery = $pdo->query("SELECT `showroom_custom_pages` FROM `settings` LIMIT 1");
        if ($settingsQuery) {
            $settings = $settingsQuery->fetch();
            if ($settings && !empty($settings['showroom_custom_pages'])) {
                $customPages = json_decode($settings['showroom_custom_pages'], true);
                if (is_array($customPages)) {
                    foreach ($customPages as $cp) {
                        if (!empty($cp['slug'])) {
                            $xml .= "  <url>\n";
                            $xml .= "    <loc>" . htmlspecialchars($baseUrl . "index.php?page=" . $cp['slug']) . "</loc>\n";
                            $xml .= "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
                            $xml .= "    <changefreq>weekly</changefreq>\n";
                            $xml .= "    <priority>0.7</priority>\n";
                            $xml .= "  </url>\n";
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Safe skip
    }

    // 3. Fetch All Available Cars from database
    try {
        $carsQuery = $pdo->query("SELECT `id`, `updated_at` FROM `cars` WHERE `status` = 'available' ORDER BY `created_at` DESC");
        if ($carsQuery) {
            $cars = $carsQuery->fetchAll();
            foreach ($cars as $car) {
                $lastmod = !empty($car['updated_at']) ? date('Y-m-d', strtotime($car['updated_at'])) : date('Y-m-d');
                $xml .= "  <url>\n";
                $xml .= "    <loc>" . htmlspecialchars($baseUrl . "index.php?car_id=" . $car['id']) . "</loc>\n";
                $xml .= "    <lastmod>" . $lastmod . "</lastmod>\n";
                $xml .= "    <changefreq>weekly</changefreq>\n";
                $xml .= "    <priority>0.8</priority>\n";
                $xml .= "  </url>\n";
            }
        }
    } catch (Exception $e) {
        // Safe skip
    }

    $xml .= '</urlset>';
    return $xml;
}

// Generate the sitemap content
$sitemapXml = buildSitemapXml($pdo);

// Save a copy physically as sitemap.xml in root directory
try {
    @file_put_contents(__DIR__ . '/sitemap.xml', $sitemapXml);
} catch (Exception $e) {
    // Fail silently if directory permission issues, dynamic fallback will still work!
}

// If included from regenerateSitemapFile() or if output is suppressed
if (defined('SITEMAP_GENERATE_ONLY') || (isset($sitemap_generate_only) && $sitemap_generate_only)) {
    return;
}

// Output XML to browser
header("Content-Type: application/xml; charset=utf-8");
echo $sitemapXml;
exit;

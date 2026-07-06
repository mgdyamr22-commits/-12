-- Almakhzoun Pro (MySQL Database Schema)
-- Generated automatically for enterprise-grade deployment

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Branches Table
CREATE TABLE IF NOT EXISTS `branches` (
  `id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `manager` varchar(100) DEFAULT NULL,
  `showroom_name` varchar(150) DEFAULT NULL,
  `showroom_address` varchar(255) DEFAULT NULL,
  `tax_number` varchar(100) DEFAULT NULL,
  `commercial_registration` varchar(100) DEFAULT NULL,
  `logo` longtext DEFAULT NULL,
  `stamp` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_branch_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'representative',
  `avatar` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Cars Table
CREATE TABLE IF NOT EXISTS `cars` (
  `id` varchar(50) NOT NULL,
  `make` varchar(100) NOT NULL,
  `model` varchar(100) NOT NULL,
  `trim` varchar(100) DEFAULT NULL,
  `year` int NOT NULL,
  `color` varchar(50) NOT NULL,
  `interior_color` varchar(50) DEFAULT NULL,
  `body_type` varchar(50) DEFAULT 'سيدان',
  `doors` int DEFAULT 4,
  `seats` int DEFAULT 5,
  `cylinders` int DEFAULT 4,
  `engine_power` int DEFAULT 180,
  `drive` varchar(100) DEFAULT 'دفع أمامي FWD',
  `origin_country` varchar(100) DEFAULT NULL,
  `assembly_country` varchar(100) DEFAULT NULL,
  `entry_date` date DEFAULT NULL,
  `exit_date` date DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `warranty` varchar(255) DEFAULT 'ضمان الوكيل المعتمد الممتد',
  `warranty_duration` int DEFAULT 5,
  `previous_owner` varchar(100) DEFAULT NULL,
  `vin` varchar(100) NOT NULL UNIQUE,
  `vin_matching` varchar(50) DEFAULT 'matching',
  `plate_number` varchar(50) DEFAULT NULL UNIQUE,
  `plate_type` varchar(100) DEFAULT 'خصوصي - ملاكي',
  `serial_number` varchar(100) DEFAULT NULL,
  `registration_number` varchar(100) DEFAULT NULL,
  `vehicle_condition` varchar(100) DEFAULT 'جديد (أصفار)',
  `price` decimal(12,2) NOT NULL,
  `cost_price` decimal(12,2) DEFAULT '0.00',
  `tax` decimal(12,2) DEFAULT '0.00',
  `discount` decimal(12,2) DEFAULT '0.00',
  `final_price` decimal(12,2) DEFAULT '0.00',
  `currency` varchar(20) DEFAULT 'ر.س',
  `mileage` int NOT NULL,
  `transmission` varchar(50) NOT NULL,
  `engine_type` varchar(50) NOT NULL,
  `status` enum('available', 'reserved', 'sold', 'not_for_sale', 'out_of_stock') NOT NULL DEFAULT 'available',
  `branch_id` varchar(50) DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `ownership_type` varchar(100) DEFAULT 'مباشر',
  `leasing_status` varchar(50) DEFAULT 'not_leased',
  `customs_number` varchar(100) DEFAULT NULL,
  `rep_in_charge` varchar(100) DEFAULT NULL,
  `main_image` longtext DEFAULT NULL,
  -- Specs columns
  `gulf_specs` tinyint(1) DEFAULT 1,
  `american_specs` tinyint(1) DEFAULT 0,
  `european_specs` tinyint(1) DEFAULT 0,
  `fuel_consumption` varchar(50) DEFAULT '14.5 كم/لتر',
  `navigation_system` tinyint(1) DEFAULT 0,
  `rear_camera` tinyint(1) DEFAULT 1,
  `camera_360` tinyint(1) DEFAULT 0,
  `radar` tinyint(1) DEFAULT 0,
  `front_sensors` tinyint(1) DEFAULT 0,
  `rear_sensors` tinyint(1) DEFAULT 1,
  `cruise_control` tinyint(1) DEFAULT 1,
  `adaptive_cruise` tinyint(1) DEFAULT 0,
  `lane_assist` tinyint(1) DEFAULT 0,
  `blind_spot` tinyint(1) DEFAULT 0,
  `apple_carplay` tinyint(1) DEFAULT 1,
  `android_auto` tinyint(1) DEFAULT 1,
  `sunroof` tinyint(1) DEFAULT 0,
  `panorama` tinyint(1) DEFAULT 0,
  `leather_seats` tinyint(1) DEFAULT 0,
  `heated_seats` tinyint(1) DEFAULT 0,
  `cooled_seats` tinyint(1) DEFAULT 0,
  `seat_memory` tinyint(1) DEFAULT 0,
  `push_button_start` tinyint(1) DEFAULT 1,
  `remote_start` tinyint(1) DEFAULT 0,
  `led_lights` tinyint(1) DEFAULT 1,
  `xenon_lights` tinyint(1) DEFAULT 0,
  `number_of_keys` int DEFAULT 2,
  `spare_tire` tinyint(1) DEFAULT 1,
  `catalog` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `attachments` text DEFAULT NULL,
  `card_file_path` varchar(255) DEFAULT NULL,
  `card_file_name` varchar(255) DEFAULT NULL,
  `card_file_type` varchar(50) DEFAULT NULL,
  `card_file_date` varchar(50) DEFAULT NULL,
  `sold_by_user_id` varchar(50) DEFAULT NULL,
  `recipient_type` varchar(255) DEFAULT NULL,
  `sale_amount` decimal(12,2) DEFAULT NULL,
  `sale_customer_name` varchar(255) DEFAULT NULL,
  `sale_customer_id` varchar(100) DEFAULT NULL,
  `sale_customer_nationality` varchar(100) DEFAULT NULL,
  `sale_customer_phone` varchar(100) DEFAULT NULL,
  `exit_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sale_date` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  INDEX `idx_car_status` (`status`),
  INDEX `idx_car_make_model` (`make`, `model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Reservations Table
CREATE TABLE IF NOT EXISTS `reservations` (
  `id` varchar(50) NOT NULL,
  `car_id` varchar(50) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `customer_phone` varchar(50) NOT NULL,
  `customer_national_id` varchar(50) DEFAULT NULL,
  `start_date` date NOT NULL,
  `duration` int NOT NULL DEFAULT 3,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by_user_id` varchar(50) DEFAULT NULL,
  `status` enum('active', 'completed', 'cancelled') NOT NULL DEFAULT 'active',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_reservation_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.5. Reservation Attachments Table
CREATE TABLE IF NOT EXISTS `reservation_attachments` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. System Logs Table (Audit Engine)
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL,
  `user_name` varchar(100) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text NOT NULL,
  `risk_level` varchar(50) DEFAULT 'low',
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_log_user` (`user_id`),
  INDEX `idx_log_risk` (`risk_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Settings Table
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_name` varchar(150) NOT NULL,
  `tax_number` varchar(50) DEFAULT NULL,
  `logo` longtext DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `currency` varchar(20) DEFAULT 'ر.س',
  `email` varchar(150) DEFAULT NULL,
  `company_description` text DEFAULT NULL,
  `vision` text DEFAULT NULL,
  `mission` text DEFAULT NULL,
  `goals` text DEFAULT NULL,
  `website` varchar(150) DEFAULT NULL,
  `social_twitter` varchar(255) DEFAULT NULL,
  `social_facebook` varchar(255) DEFAULT NULL,
  `social_instagram` varchar(255) DEFAULT NULL,
  `social_linkedin` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Customers Table
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_customer_phone_unique` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Branch Transfers Table
CREATE TABLE IF NOT EXISTS `branch_transfers` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `car_id` varchar(50) NOT NULL,
  `from_branch_id` varchar(50) DEFAULT NULL,
  `to_branch_id` varchar(50) DEFAULT NULL,
  `created_by_user_id` varchar(50) DEFAULT NULL,
  `transfer_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `letter_number` varchar(100) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `received_by_user_id` varchar(50) DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_bt_car_id` (`car_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Contact Inquiries Table
CREATE TABLE IF NOT EXISTS `contact_inquiries` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `status` varchar(50) DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_ci_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Seed Data
INSERT IGNORE INTO `branches` (`id`, `name`, `location`, `phone`, `manager`) VALUES
('b-1', 'معرض الرياض الرئيسي', 'طريق خريص، الرياض', '0112345678', 'عبدالرحمن العتيبي'),
('b-2', 'فرع جدة - طريق المدينة', 'طريق المدينة الطالع، جدة', '0123456789', 'خالد الغامدي');

INSERT IGNORE INTO `users` (`id`, `username`, `password`, `name`, `email`, `phone`, `role`, `avatar`) VALUES
('u-1', 'admin', '$2y$10$bO6gKsm4C6XhWz9p7YFvIu69eXp7n.12eGv2GZpI4/fS4g538pE3u', 'المدير العام', 'admin@al-makhzoun.com', '0500000001', 'admin', NULL),
('u-2', 'ahmed', '$2y$10$O0FzKsm4C6XhWz9p7YFvIu69eXp7n.12eGv2GZpI4/fS4g538pE3u', 'أحمد الحربي', 'ahmed@al-makhzoun.com', '0500000002', 'representative', NULL),
('u-3', 'sami', '$2y$10$O0FzKsm4C6XhWz9p7YFvIu69eXp7n.12eGv2GZpI4/fS4g538pE3u', 'سامي القحطاني', 'sami@al-makhzoun.com', '0500000003', 'representative', NULL),
('u-4', 'yasser', '$2y$10$O0FzKsm4C6XhWz9p7YFvIu69eXp7n.12eGv2GZpI4/fS4g538pE3u', 'ياسر الشهري', 'yasser@al-makhzoun.com', '0500000004', 'representative', NULL);

INSERT IGNORE INTO `settings` (`id`, `company_name`, `tax_number`, `logo`, `address`, `phone`, `currency`, `email`, `company_description`, `vision`, `mission`, `goals`, `website`, `social_twitter`, `social_facebook`, `social_instagram`, `social_linkedin`) VALUES
(1, 'شركة المخزون للمحركات المحدودة', '310249823100003', NULL, 'الرياض، المملكة العربية السعودية', '920002131', 'ر.س', 'info@al-makhzoun.com', 'مؤسسة النخبة هي شركة رائدة في مجال استيراد وتجارة السيارات الفاخرة والحديثة في المملكة العربية السعودية، نسعى لتقديم أفضل الخيارات وبجودة عالية.', 'أن نكون الخيار الأول والوجهة الموثوقة لعملاء السيارات الفاخرة في السوق الخليجي من خلال التميز والابتكار.', 'تقديم تجربة شراء فريدة لعملائنا من خلال توفير تشكيلة واسعة من السيارات المتميزة مع تقديم أرقى مستويات الخدمة والدعم.', 'توسيع شبكة فروعنا لتشمل كافة مناطق المملكة، عقد شراكات استراتيجية مع كبار المصنعين، وتقديم خدمات ما بعد البيع استثنائية.', 'www.elite-cars.com', 'https://twitter.com/elite_cars', 'https://facebook.com/elite_cars', 'https://instagram.com/elite_cars', 'https://linkedin.com/company/elite_cars');

INSERT IGNORE INTO `cars` (`id`, `make`, `model`, `trim`, `year`, `color`, `interior_color`, `body_type`, `doors`, `seats`, `cylinders`, `engine_power`, `drive`, `origin_country`, `assembly_country`, `entry_date`, `purchase_date`, `warranty`, `warranty_duration`, `previous_owner`, `vin`, `vin_matching`, `plate_number`, `plate_type`, `vehicle_condition`, `price`, `cost_price`, `tax`, `discount`, `final_price`, `currency`, `mileage`, `transmission`, `engine_type`, `status`, `branch_id`, `supplier`, `ownership_type`, `leasing_status`, `rep_in_charge`, `gulf_specs`, `notes`) VALUES
('c-1', 'تويوتا', 'كامري', 'GLE', 2024, 'أبيض لؤلؤي', 'بيج', 'سيدان', 4, 5, 4, 204, 'دفع أمامي FWD', 'اليابان', 'اليابان', '2026-01-10', '2026-01-05', 'ضمان عبد اللطيف جميل ممتد', 5, 'مباشر من المصنع', 'T7831Y289410A8129', 'matching', 'أ ب ج 1234', 'خصوصي', 'جديد (أصفار)', 115000.00, 95000.00, 17250.00, 2000.00, 130250.00, 'ر.س', 15, 'أوتوماتيك', 'بنزين', 'available', 'b-1', 'الوكيل المحلي عبد اللطيف جميل', 'مباشر', 'not_leased', 'أحمد الحربي', 1, 'كامري جديدة كلياً بفئة جي إل إي الفاخرة، جاهزة للتسليم الفوري مع كرت الضمان والكتالوجات كاملة.'),
('c-2', 'لكزس', 'LX500', 'VIP', 2024, 'أسود ملكي', 'جملي فاخر', 'SUV', 5, 4, 6, 409, 'دفع رباعي مستمر 4WD', 'اليابان', 'اليابان', '2026-02-15', '2026-02-10', 'ضمان لكزس المعتمد', 5, 'مباشر', 'L8312P289410A4921', 'matching', 'د ر س 8888', 'خصوصي', 'جديد (أصفار)', 485000.00, 410000.00, 72750.00, 5000.00, 552750.00, 'ر.س', 22, 'أوتوماتيك', 'بنزين', 'reserved', 'b-1', 'شركة عبد اللطيف جميل للسيارات', 'مباشر', 'not_leased', 'سامي القحطاني', 1, 'لكزس في آي بي فاخرة جداً، شاشات خلفية، ثلاجة، تدليك مراتب، محجوزة لعميل مميز وفي انتظار استكمال الدفع.'),
('c-3', 'نيسان', 'باترول', 'Platinum', 2023, 'فضي معدني', 'زعفراني', 'SUV', 5, 8, 8, 400, 'دفع رباعي 4WD', 'اليابان', 'اليابان', '2026-01-20', '2026-01-15', 'ضمان العيسى المعتمد', 5, 'مباشر', 'N9301M289410A3810', 'matching', 'ق و ر 9999', 'خصوصي', 'جديد (أصفار)', 295000.00, 260000.00, 44250.00, 0.00, 339250.00, 'ر.س', 50, 'أوتوماتيك', 'بنزين', 'available', 'b-2', 'العيسى للسيارات', 'مباشر', 'not_leased', 'ياسر الشهري', 1, 'نيسان باترول بلاتينيوم بطل الدروب، لون فضي داخلي زعفراني مميز، كامل المواصفات فل كامل.'),
('c-4', 'مرسيدس بنز', 'S-Class', 'S500', 2024, 'أزرق كحلي', 'أوف وايت ديزاينو', 'سيدان فاخرة', 4, 5, 6, 435, 'دفع رباعي 4MATIC', 'ألمانيا', 'ألمانيا', '2026-03-01', '2026-02-25', 'ضمان الجفالي الممتد', 5, 'مباشر', 'M7312Z289410A1123', 'matching', 'م ب ز 5000', 'خصوصي', 'جديد (أصفار)', 620000.00, 550000.00, 93000.00, 10000.00, 703000.00, 'ر.س', 10, 'أوتوماتيك', 'هجين خفيف', 'sold', 'b-1', 'الجفالي للمحركات', 'مباشر', 'not_leased', 'أحمد الحربي', 1, 'مرسيدس يخت إس 500 كاملة المواصفات الفاخرة، تم بيعها وتسليم اللوحات للعميل بالفرع الرئيسي.'),
('c-5', 'هيونداي', 'سوناتا', 'Smart', 2023, 'رمادي غامق', 'رمادي فاتح', 'سيدان', 4, 5, 4, 180, 'دفع أمامي FWD', 'كوريا الجنوبية', 'كوريا الجنوبية', '2026-01-15', '2026-01-10', 'ضمان المجدوعي والوعلان المتبادل', 5, 'مباشر', 'H8391Q289410A2214', 'matching', 'ح م ر 5678', 'خصوصي', 'جديد (أصفار)', 98000.00, 85000.00, 14700.00, 3000.00, 109700.00, 'ر.س', 8, 'أوتوماتيك', 'بنزين', 'available', 'b-2', 'الوعلان للسيارات', 'مباشر', 'not_leased', 'سامي القحطاني', 1, 'سوناتا سمارت اقتصادية وعملية جداً، سقف بانوراما، حساسات وكاميرا خلفية.'),
('c-6', 'فورد', 'F-150', 'Raptor', 2024, 'أحمر ناري', 'أسود رياضى الكانتارا', 'بيك أب دبل', 4, 5, 6, 450, 'دفع رباعي مستمر', 'أمريكا', 'أمريكا', '2026-04-10', '2026-04-01', 'ضمان توكيلات الجزيرة ممتد', 5, 'مباشر', 'F8102A289410A9944', 'matching', 'ف ر د 1500', 'خصوصي', 'جديد (أصفار)', 380000.00, 330000.00, 57000.00, 5000.00, 432000.00, 'ر.س', 45, 'أوتوماتيك', 'بنزين توربو', 'available', 'b-1', 'توكيلات الجزيرة للسيارات', 'مباشر', 'not_leased', 'ياسر الشهري', 1, 'فورد رابتور وحش الطرق الوعرة، مساعدات فوكس لايف فالف، مواصفات تصدير خليجي كاملة.'),
('c-7', 'تويوتا', 'لاندكروزر LC300', 'VX', 2024, 'برونزي', 'بيج', 'SUV', 5, 7, 6, 409, 'دفع رباعي 4WD', 'اليابان', 'اليابان', '2026-03-05', '2026-03-01', 'ضمان الوكيل عبد اللطيف جميل', 5, 'مباشر', 'L3002W289410A7712', 'matching', 'ط ر ق 3000', 'خصوصي', 'جديد (أصفار)', 360000.00, 315000.00, 54000.00, 0.00, 414000.00, 'ر.س', 12, 'أوتوماتيك', 'بنزين توربو', 'reserved', 'b-1', 'عبد اللطيف جميل', 'مباشر', 'not_leased', 'أحمد الحربي', 1, 'لاندكروزر الجيل الجديد في إكس، محرك توربو قوي للغاية، عزل ممتاز، حجز نشط ومستندات كاملة.'),
('c-8', 'تسلا', 'Model Y', 'Long Range', 2023, 'أبيض', 'أسود جلد', 'كروس أوفر', 5, 5, 0, 384, 'دفع كلي AWD', 'أمريكا', 'الصين', '2026-02-10', '2026-02-05', 'ضمان البطارية والمحركات ممتد', 8, 'مباشر', 'T1023X289410A5522', 'mismatch', 'ت س ل 1111', 'خصوصي', 'مستعمل مميز', 220000.00, 195000.00, 33000.00, 0.00, 253000.00, 'ر.س', 15000, 'أوتوماتيك', 'كهربائي بالكامل', 'not_for_sale', 'b-2', 'استيراد شخصي معتمد', 'شخصي', 'not_leased', 'سامي القحطاني', 1, 'تسلا موديل واي كهربائية، أداء مذهل ومدى يتجاوز 500 كم للشحنة، معروضة للعرض والدعاية بالفرع.'),
('c-9', 'بي إم دبليو', '7 Series', '740i', 2024, 'أسود كربوني', 'بني كوجناك عميق', 'سيدان فاخرة', 4, 5, 6, 380, 'دفع خلفي RWD', 'ألمانيا', 'ألمانيا', '2026-03-12', '2026-03-08', 'ضمان الناغي الشامل الممتد', 5, 'مباشر', 'B7402Y289410A6641', 'matching', 'ب م و 7400', 'خصوصي', 'جديد (أصفار)', 540000.00, 470000.00, 81000.00, 10000.00, 611000.00, 'ر.س', 18, 'أوتوماتيك', 'هجين خفيف', 'available', 'b-1', 'الناغي للسيارات', 'مباشر', 'not_leased', 'ياسر الشهري', 1, 'بي إم دبليو الفئة السابعة الجيل الأحدث، إضاءة داخلية تفاعلية، شاشة عملاقة في الخلف، قمة التكنولوجيا الألمانية.'),
('c-10', 'أودي', 'A8 L', 'Premium', 2023, 'فضي ديجيتال', 'أزرق ملكي فاخر', 'سيدان فاخرة طوية', 4, 5, 6, 340, 'دفع كلي Quattro', 'ألمانيا', 'ألمانيا', '2026-01-28', '2026-01-22', 'ضمان ساماكو الممتد', 5, 'مباشر', 'A8023V289410A3399', 'matching', 'أ و د 8800', 'خصوصي', 'جديد (أصفار)', 450000.00, 390000.00, 67500.00, 5000.00, 512500.00, 'ر.س', 25, 'أوتوماتيك', 'بنزين', 'available', 'b-2', 'ساماكو أودي السعودية', 'مباشر', 'not_leased', 'أحمد الحربي', 1, 'أودي إيه 8 شاسيه طويل، قيادة مريحة جداً، رادار نشط ونظام تعليق هوائي متكامل.');

INSERT IGNORE INTO `reservations` (`id`, `car_id`, `customer_name`, `customer_phone`, `customer_national_id`, `start_date`, `duration`, `created_at`, `created_by_user_id`, `status`) VALUES
('r-1', 'c-2', 'فيصل السديري', '0554321098', '1029384756', '2026-06-28', 5, '2026-06-28 10:30:00', 'u-3', 'active'),
('r-2', 'c-7', 'محمد القحطاني', '0569876543', '1092837465', '2026-06-29', 3, '2026-06-29 11:15:00', 'u-2', 'active'),
('r-3', 'c-4', 'عبدالله الخالدي', '0543210987', '1012938475', '2026-06-25', 4, '2026-06-25 09:00:00', 'u-2', 'completed');

INSERT IGNORE INTO `system_logs` (`id`, `user_id`, `user_name`, `action`, `details`, `risk_level`, `ip`, `created_at`) VALUES
(1, 'u-1', 'المدير العام', 'تسجيل دخول', 'تم تسجيل دخول المدير العام بنجاح من عنوان IP معتمد وجدار حماية WAF نشط', 'low', '192.168.1.5', '2026-06-30 08:00:00'),
(2, 'u-2', 'أحمد الحربي', 'إضافة سيارة', 'تم إضافة سيارة تويوتا كامري 2024 بالهيكل T7831Y289410A8129 بالنجاح وتخزينها بالفرع الرئيسي', 'low', '192.168.1.12', '2026-06-30 08:15:00'),
(3, 'u-3', 'سامي القحطاني', 'إنشاء حجز', 'تم إنشاء حجز نشط للسيارة لكزس LX500 برقم الحجز r-1 للعميل فيصل السديري ومراجعة تفاصيل العقد', 'low', '192.168.1.18', '2026-06-30 08:30:00'),
(4, 'u-1', 'المدير العام', 'تصدير تقرير', 'تم تصدير تقرير المخزون الحى والسيارات المتاحة بصيغة Excel المتكاملة مع الجداول والملخصات الحسابية', 'medium', '192.168.1.5', '2026-06-30 08:45:00'),
(5, 'u-4', 'ياسر الشهري', 'تحديث سيارة', 'تم تعديل ومطابقة رقم الهيكل للسيارة نيسان باترول بنجاح وتحديث كفاءة استهلاك الوقود', 'low', '192.168.1.25', '2026-06-30 09:00:00'),
(6, 'u-1', 'المدير العام', 'تحديث الإعدادات', 'تم تحديث الشعار والوصف العام والروابط الاجتماعية لمؤسسة المخزون للمحركات عبر لوحة الإعدادات', 'low', '192.168.1.5', '2026-06-30 09:15:00'),
(7, 'u-2', 'أحمد الحربي', 'إغلاق حجز', 'تم إكمال الحجز r-3 للسيارة مرسيدس إس 500 بنجاح ونقل حالة السيارة إلى مباعة للعميل عبدالله الخالدي', 'low', '192.168.1.12', '2026-06-30 09:30:00');

SET FOREIGN_KEY_CHECKS = 1;

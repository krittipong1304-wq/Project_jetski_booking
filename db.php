<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "boat_booking";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->query("CREATE TABLE IF NOT EXISTS boats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    boat_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    image_path VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_booked TINYINT(1) NOT NULL DEFAULT 0,
    hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS booking_duration_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(50) NOT NULL,
    minutes INT NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0
)");

$conn->query("CREATE TABLE IF NOT EXISTS booking_name_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    option_name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0
)");

$conn->query("CREATE TABLE IF NOT EXISTS booking_location_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    option_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0
)");

$conn->query("CREATE TABLE IF NOT EXISTS booking_agency_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    option_name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0
)");

$conn->query("CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS booking_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_booking_id INT NOT NULL,
    boat_id INT NULL,
    boat VARCHAR(100) NULL,
    name VARCHAR(100) NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(20) NULL,
    agency VARCHAR(100) NULL,
    location VARCHAR(100) NULL,
    booking_date DATE NULL,
    time_slot ENUM('morning','afternoon') NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    total_price DECIMAL(10,2) NULL,
    status VARCHAR(20) NULL,
    admin_note VARCHAR(255) NULL,
    approved_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS booking_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    title VARCHAR(255) NULL,
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_booking_attachments_booking_id (booking_id)
)");

$conn->query("ALTER TABLE boats ADD COLUMN IF NOT EXISTS description TEXT NULL AFTER boat_name");
$conn->query("ALTER TABLE boats ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) NULL AFTER description");
$conn->query("ALTER TABLE boats ADD COLUMN IF NOT EXISTS is_booked TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");
$conn->query("ALTER TABLE boats ADD COLUMN IF NOT EXISTS hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER is_booked");
$conn->query("ALTER TABLE boats ADD COLUMN IF NOT EXISTS stock INT NOT NULL DEFAULT 1 AFTER hourly_rate");
$conn->query("ALTER TABLE booking_duration_options ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER minutes");
$conn->query("ALTER TABLE booking_location_options ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER option_name");

$conn->query("ALTER TABLE boats ADD COLUMN IF NOT EXISTS hourly_rate_usd DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER hourly_rate");
$conn->query("ALTER TABLE booking_location_options ADD COLUMN IF NOT EXISTS price_usd DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER price");

$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS email VARCHAR(150) AFTER name");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS boat_id INT NULL AFTER phone");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS status ENUM('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending' AFTER time");
$conn->query("ALTER TABLE bookings MODIFY COLUMN status ENUM('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending'");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS admin_note VARCHAR(255) NULL AFTER status");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL AFTER admin_note");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER approved_at");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS booking_date DATE NULL AFTER boat");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS location VARCHAR(100) NULL AFTER phone");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS agency VARCHAR(100) NULL AFTER phone");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS time_slot ENUM('morning','afternoon') NULL AFTER booking_date");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS start_time TIME NULL AFTER booking_date");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS end_time TIME NULL AFTER start_time");
$conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS total_price DECIMAL(10,2) NULL AFTER end_time");
$conn->query("ALTER TABLE booking_history ADD COLUMN IF NOT EXISTS location VARCHAR(100) NULL AFTER phone");
$conn->query("ALTER TABLE booking_history ADD COLUMN IF NOT EXISTS agency VARCHAR(100) NULL AFTER phone");
$conn->query("ALTER TABLE booking_history ADD COLUMN IF NOT EXISTS time_slot ENUM('morning','afternoon') NULL AFTER booking_date");
$conn->query("ALTER TABLE booking_attachments ADD COLUMN IF NOT EXISTS booking_id INT NOT NULL AFTER id");
$conn->query("ALTER TABLE booking_attachments ADD COLUMN IF NOT EXISTS title VARCHAR(255) NULL AFTER booking_id");
$conn->query("ALTER TABLE booking_attachments ADD COLUMN IF NOT EXISTS file_path VARCHAR(255) NOT NULL AFTER title");
$conn->query("ALTER TABLE booking_attachments ADD COLUMN IF NOT EXISTS original_name VARCHAR(255) NULL AFTER file_path");

$conn->query("UPDATE bookings SET booking_date = date WHERE booking_date IS NULL AND date IS NOT NULL");
$conn->query("UPDATE bookings SET start_time = time WHERE start_time IS NULL AND time IS NOT NULL");
$conn->query("UPDATE bookings SET end_time = ADDTIME(start_time, '01:00:00') WHERE end_time IS NULL AND start_time IS NOT NULL");
$conn->query("UPDATE bookings SET time_slot = CASE WHEN start_time < '12:00:00' THEN 'morning' ELSE 'afternoon' END WHERE time_slot IS NULL AND start_time IS NOT NULL");
$conn->query("UPDATE booking_history SET time_slot = CASE WHEN start_time < '12:00:00' THEN 'morning' ELSE 'afternoon' END WHERE time_slot IS NULL AND start_time IS NOT NULL");
$conn->query("UPDATE boats SET stock = 1 WHERE stock IS NULL OR stock < 0");
$conn->query("UPDATE booking_location_options SET price = 0 WHERE price IS NULL OR price < 0");

$durationSeedResult = $conn->query("SELECT COUNT(*) AS total FROM booking_duration_options");
$durationCount = 0;
if ($durationSeedResult && $durationRow = $durationSeedResult->fetch_assoc()) {
    $durationCount = (int) $durationRow['total'];
}

if ($durationCount === 0) {
    $conn->query("INSERT INTO booking_duration_options (label, minutes, price, is_active, sort_order) VALUES
        ('30 minutes', 30, 500.00, 1, 1),
        ('1 hour', 60, 1000.00, 1, 2),
        ('2 hours', 120, 2000.00, 1, 3),
        ('3 hours', 180, 3000.00, 1, 4),
        ('4 hours', 240, 4000.00, 1, 5)");
}

$nameSeedResult = $conn->query("SELECT COUNT(*) AS total FROM booking_name_options");
$nameOptionCount = 0;
if ($nameSeedResult && $nameSeedRow = $nameSeedResult->fetch_assoc()) {
    $nameOptionCount = (int) $nameSeedRow['total'];
}

if ($nameOptionCount === 0) {
    $conn->query("INSERT INTO booking_name_options (option_name, is_active, sort_order) VALUES
        ('Guest A', 1, 1),
        ('Guest B', 1, 2),
        ('Guest C', 1, 3)");
}

$locationSeedResult = $conn->query("SELECT COUNT(*) AS total FROM booking_location_options");
$locationOptionCount = 0;
if ($locationSeedResult && $locationSeedRow = $locationSeedResult->fetch_assoc()) {
    $locationOptionCount = (int) $locationSeedRow['total'];
}

if ($locationOptionCount === 0) {
    $conn->query("INSERT INTO booking_location_options (option_name, price, is_active, sort_order) VALUES
        ('Pier A', 0.00, 1, 1),
        ('Pier B', 0.00, 1, 2),
        ('Pier C', 0.00, 1, 3)");
}

$agencySeedResult = $conn->query("SELECT COUNT(*) AS total FROM booking_agency_options");
$agencyOptionCount = 0;
if ($agencySeedResult && $agencySeedRow = $agencySeedResult->fetch_assoc()) {
    $agencyOptionCount = (int) $agencySeedRow['total'];
}

if ($agencyOptionCount === 0) {
    $conn->query("INSERT INTO booking_agency_options (option_name, is_active, sort_order) VALUES
        ('Direct', 1, 1),
        ('Agency A', 1, 2),
        ('Agency B', 1, 3)");
}

$conn->query("INSERT INTO app_settings (setting_key, setting_value)
    VALUES
    ('booking_enabled', '1'),
    ('agency_dropdown_enabled', '1'),
    ('currency_rate_source_url', ''),
    ('usd_thb_rate', '35.000000'),
    ('usd_thb_rate_fetched_at', '')
    ON DUPLICATE KEY UPDATE setting_key = setting_key");

$conn->query("UPDATE booking_duration_options SET price = ROUND(minutes * 16.67, 2) WHERE price IS NULL OR price <= 0");

$conn->query("UPDATE boats b
SET b.is_booked = (
    CASE
        WHEN b.stock <= 0 THEN 1
        WHEN (
            SELECT COUNT(*)
            FROM bookings bk
            WHERE bk.boat_id = b.id
            AND bk.is_archived = 0
            AND bk.status IN ('pending', 'approved')
        ) >= b.stock THEN 1
        ELSE 0
    END
)");

$seedResult = $conn->query("SELECT COUNT(*) AS total FROM boats");
$boatCount = 0;

if ($seedResult && $row = $seedResult->fetch_assoc()) {
    $boatCount = (int) $row['total'];
}

if ($boatCount === 0) {
    $conn->query("INSERT INTO boats (boat_name, description, is_active, is_booked, hourly_rate, hourly_rate_usd, stock) VALUES
        ('Speedboat A', 'Fast and compact boat for island hopping.', 1, 0, 2500.00, 75.00, 1),
        ('Speedboat B', 'Comfortable speedboat for small groups.', 1, 0, 2200.00, 65.00, 1),
        ('Catamaran C', 'Stable twin-hull boat for family trips.', 1, 0, 3500.00, 100.00, 1),
        ('Yacht D', 'Luxury yacht for premium private charter.', 1, 0, 6000.00, 180.00, 1)");
}

function appSettingGet(mysqli $conn, string $key, string $defaultValue = ''): string
{
    $value = $defaultValue;
    $stmt = $conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $stmt->bind_result($valueFromDb);
        if ($stmt->fetch()) {
            $value = (string) $valueFromDb;
        }
        $stmt->close();
    }

    return $value;
}

function appSettingSet(mysqli $conn, string $key, string $value): void
{
    $stmt = $conn->prepare("INSERT INTO app_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    if ($stmt) {
        $stmt->bind_param("ss", $key, $value);
        $stmt->execute();
        $stmt->close();
    }
}

function normalizeUsdThbRate(float $rawRate): ?float
{
    if ($rawRate <= 0) {
        return null;
    }

    if ($rawRate >= 10 && $rawRate <= 200) {
        return $rawRate;
    }

    if ($rawRate > 0 && $rawRate < 1) {
        $converted = 1 / $rawRate;
        if ($converted >= 10 && $converted <= 200) {
            return $converted;
        }
    }

    return null;
}

function extractUsdThbRateFromJsonValue($value): ?float
{
    if (is_numeric($value)) {
        return normalizeUsdThbRate((float) $value);
    }

    if (is_array($value)) {
        if (isset($value['base']) && isset($value['rates']) && is_array($value['rates'])) {
            $base = strtoupper((string) $value['base']);
            $rates = $value['rates'];
            if ($base === 'USD' && isset($rates['THB']) && is_numeric($rates['THB'])) {
                return normalizeUsdThbRate((float) $rates['THB']);
            }
            if ($base === 'THB' && isset($rates['USD']) && is_numeric($rates['USD']) && (float) $rates['USD'] > 0) {
                return normalizeUsdThbRate(1 / (float) $rates['USD']);
            }
        }

        $priorityKeys = ['usd_thb', 'thb_per_usd', 'usdthb', 'exchange_rate', 'conversion_rate', 'rate'];
        foreach ($priorityKeys as $key) {
            if (isset($value[$key]) && is_numeric($value[$key])) {
                $normalized = normalizeUsdThbRate((float) $value[$key]);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        $inverseKeys = ['thb_usd', 'usd_per_thb', 'thbusd'];
        foreach ($inverseKeys as $key) {
            if (isset($value[$key]) && is_numeric($value[$key]) && (float) $value[$key] > 0) {
                $normalized = normalizeUsdThbRate(1 / (float) $value[$key]);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        foreach ($value as $nestedValue) {
            $found = extractUsdThbRateFromJsonValue($nestedValue);
            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

function fetchUsdThbRateFromSourceUrl(string $url): ?float
{
    if ($url === '') {
        return null;
    }

    $responseBody = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch !== false) {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'BoatBooking/1.0');
            $curlResult = curl_exec($ch);
            if (is_string($curlResult)) {
                $responseBody = $curlResult;
            }
            curl_close($ch);
        }
    }

    if ($responseBody === '') {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "User-Agent: BoatBooking/1.0\r\n",
            ],
        ]);
        $fileResult = @file_get_contents($url, false, $context);
        if (is_string($fileResult)) {
            $responseBody = $fileResult;
        }
    }

    if ($responseBody === '') {
        return null;
    }

    $decoded = json_decode($responseBody, true);
    if (is_array($decoded)) {
        $jsonRate = extractUsdThbRateFromJsonValue($decoded);
        if ($jsonRate !== null) {
            return $jsonRate;
        }
    }

    if (preg_match('/([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]+)?)/', $responseBody, $matches)) {
        $raw = str_replace(',', '', $matches[1]);
        if (is_numeric($raw)) {
            return normalizeUsdThbRate((float) $raw);
        }
    }

    return null;
}

function getUsdThbRate(mysqli $conn, bool $forceRefresh = false): float
{
    $fallbackRate = 35.00;
    $cachedRate = (float) appSettingGet($conn, 'usd_thb_rate', (string) $fallbackRate);
    if ($cachedRate <= 0) {
        $cachedRate = $fallbackRate;
    }

    $sourceUrl = trim(appSettingGet($conn, 'currency_rate_source_url', ''));
    $lastFetchedAt = appSettingGet($conn, 'usd_thb_rate_fetched_at', '');
    $shouldRefresh = false;

    if ($sourceUrl !== '') {
        if ($forceRefresh) {
            $shouldRefresh = true;
        } elseif ($lastFetchedAt === '') {
            $shouldRefresh = true;
        } else {
            $lastFetchedTs = strtotime($lastFetchedAt);
            if ($lastFetchedTs === false || (time() - $lastFetchedTs) > 3600) {
                $shouldRefresh = true;
            }
        }
    }

    if ($shouldRefresh) {
        $freshRate = fetchUsdThbRateFromSourceUrl($sourceUrl);
        if ($freshRate !== null && $freshRate > 0) {
            $cachedRate = $freshRate;
            appSettingSet($conn, 'usd_thb_rate', number_format($cachedRate, 6, '.', ''));
            appSettingSet($conn, 'usd_thb_rate_fetched_at', date('Y-m-d H:i:s'));
        }
    }

    return $cachedRate;
}
?>

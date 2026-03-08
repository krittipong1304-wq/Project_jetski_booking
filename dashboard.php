<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "db.php";

$allowedLangs = ['th', 'en'];
if (isset($_GET['lang']) && in_array($_GET['lang'], $allowedLangs, true)) {
    $_SESSION['lang'] = $_GET['lang'];
    header("Location: dashboard.php");
    exit;
}

$lang = $_SESSION['lang'] ?? 'en';
if (!in_array($lang, $allowedLangs, true)) {
    $lang = 'en';
}
$isUsdCurrency = $lang === 'en';
$usdThbRate = getUsdThbRate($conn, false);
if ($usdThbRate <= 0) {
    $usdThbRate = 35.00;
}

function t(string $key, string $lang): string
{
    $text = [
        'en' => [
            'page_title' => 'Jetski Booking',
            'heading' => 'Jetski Booking',
            'subtitle' => 'Choose a Jetski and book by time range.',
            'no_image' => 'No Image',
            'out_of_stock' => 'OUT OF STOCK',
            'available' => 'AVAILABLE',
            'rate_format' => 'Rate: %s / hour',
            'stock_format' => 'Stock left: %d / %d',
            'booked_btn' => 'Booked',
            'book_now_btn' => 'Book This Jetski',
            'booking_closed_notice' => 'Booking system is currently closed by admin.',
            'booking_closed_btn' => 'Closed',
            'empty_text' => 'No boats available right now.',
            'lang_label' => 'Language',
            'lang_th' => 'ไทย',
            'lang_en' => 'English'
        ],
        'th' => [
            'page_title' => 'จองเจ็ทสกี',
            'heading' => 'จองเจ็ทสกี',
            'subtitle' => 'เลือกเจ็ทสกีและจองตามช่วงเวลาได้ทันที',
            'no_image' => 'ไม่มีรูปภาพ',
            'booked_now' => 'ถูกจองแล้ว',
            'available' => 'ว่าง',
            'rate_format' => 'ราคา: %s / ชั่วโมง',
            'booked_btn' => 'จองแล้ว',
            'book_now_btn' => 'จองเจ็ทสกีลำนี้',
            'empty_text' => 'ขณะนี้ยังไม่มีเจ็ทสกีที่เปิดให้จอง',
            'lang_label' => 'ภาษา',
            'lang_th' => 'ไทย',
            'lang_en' => 'English'
        ]
    ];

    return $text[$lang][$key] ?? $text['en'][$key] ?? $key;
}

$isBookingEnabled = 1;
$bookingSettingStmt = $conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'booking_enabled' LIMIT 1");
if ($bookingSettingStmt) {
    $bookingSettingStmt->execute();
    $bookingSettingStmt->bind_result($bookingSettingValue);
    if ($bookingSettingStmt->fetch()) {
        $isBookingEnabled = ((string) $bookingSettingValue === '0') ? 0 : 1;
    }
    $bookingSettingStmt->close();
}

$boats = [];
$result = $conn->query("SELECT b.id, b.boat_name, b.description, b.image_path, b.hourly_rate, b.hourly_rate_usd, b.stock,
    COALESCE(ba.active_count, 0) AS active_count,
    GREATEST(b.stock - COALESCE(ba.active_count, 0), 0) AS available_stock
    FROM boats b
    LEFT JOIN (
        SELECT boat_id, COUNT(*) AS active_count
        FROM bookings
        WHERE is_archived = 0
        AND status IN ('pending', 'approved')
        GROUP BY boat_id
    ) ba ON ba.boat_id = b.id
    WHERE b.is_active = 1
    ORDER BY b.boat_name ASC");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $boats[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang === 'th' ? 'th' : 'en'; ?>">
<head>
<title><?php echo htmlspecialchars(t('page_title', $lang), ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="stylesheet" href="css/style.css?v=20260305">
<style>
    .lang-switch {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
    }
    .lang-switch .btn {
        margin-top: 0;
        padding: 6px 12px;
    }
    .lang-switch .active {
        background: #0057b8;
        pointer-events: none;
    }
</style>
</head>

<body class="user-theme">
<main class="page page-home">
    <section class="card card-wide card-dashboard">
        <div class="lang-switch">
            <span><?php echo htmlspecialchars(t('lang_label', $lang), ENT_QUOTES, 'UTF-8'); ?>:</span>
            <a href="dashboard.php?lang=th" class="btn btn-secondary <?php echo $lang === 'th' ? 'active' : ''; ?>"><?php echo htmlspecialchars(t('lang_th', $lang), ENT_QUOTES, 'UTF-8'); ?></a>
            <a href="dashboard.php?lang=en" class="btn btn-secondary <?php echo $lang === 'en' ? 'active' : ''; ?>"><?php echo htmlspecialchars(t('lang_en', $lang), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>

        <h1><?php echo htmlspecialchars(t('heading', $lang), ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="subtitle"><?php echo htmlspecialchars(t('subtitle', $lang), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if ($isBookingEnabled !== 1): ?>
            <p class="system-notice"><?php echo htmlspecialchars(t('booking_closed_notice', $lang), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <?php if (count($boats) > 0): ?>
            <div class="boat-grid">
                <?php foreach ($boats as $boat): ?>
                    <?php
                    $availableStock = (int) ($boat['available_stock'] ?? 0);
                    $totalStock = (int) ($boat['stock'] ?? 0);
                    $isOutOfStock = $availableStock <= 0;
                    ?>
                    <article class="boat-card">
                        <?php if (!empty($boat['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($boat['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($boat['boat_name'], ENT_QUOTES, 'UTF-8'); ?>" class="boat-card-image" style="display:block;width:100%;height:140px;object-fit:cover;border-radius:8px;border:1px solid #d4deea;">
                        <?php else: ?>
                            <div class="boat-card-image boat-card-image-empty"><?php echo htmlspecialchars(t('no_image', $lang), ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>

                        <div class="boat-card-head">
                            <h3 class="boat-card-title"><?php echo htmlspecialchars($boat['boat_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <span class="status-pill <?php echo $isOutOfStock ? 'status-booked' : 'status-available'; ?>">
                                <?php echo $isOutOfStock ? htmlspecialchars(t('out_of_stock', $lang), ENT_QUOTES, 'UTF-8') : htmlspecialchars(t('available', $lang), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>

                        <p class="boat-card-desc"><?php echo htmlspecialchars((string) ($boat['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php
                        $displayRate = $isUsdCurrency ? (float) ($boat['hourly_rate_usd'] ?? 0) : (float) $boat['hourly_rate'];
                        $rateWithCurrency = number_format($displayRate, 2) . ' ' . ($isUsdCurrency ? 'USD' : 'THB');
                        ?>
                        <p class="boat-rate"><?php echo htmlspecialchars(sprintf(t('rate_format', $lang), $rateWithCurrency), ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="boat-stock"><?php echo htmlspecialchars(sprintf(t('stock_format', $lang), $availableStock, $totalStock), ENT_QUOTES, 'UTF-8'); ?></p>

                        <?php if ($isBookingEnabled !== 1): ?>
                            <button class="btn btn-disabled" type="button" disabled><?php echo htmlspecialchars(t('booking_closed_btn', $lang), ENT_QUOTES, 'UTF-8'); ?></button>
                        <?php elseif ($isOutOfStock): ?>
                            <button class="btn btn-disabled" type="button" disabled><?php echo htmlspecialchars(t('booked_btn', $lang), ENT_QUOTES, 'UTF-8'); ?></button>
                        <?php else: ?>
                            <a href="booking.php?boat_id=<?php echo (int) $boat['id']; ?>" class="btn"><?php echo htmlspecialchars(t('book_now_btn', $lang), ENT_QUOTES, 'UTF-8'); ?></a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-text"><?php echo htmlspecialchars(t('empty_text', $lang), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <!-- <a href="booking.php" class="btn btn-secondary">Manual Booking</a>
        <a href="admin_login.php" class="btn btn-secondary">Admin Panel</a> -->
    </section>
</main>

</body>
</html>

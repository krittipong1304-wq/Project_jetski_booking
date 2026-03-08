<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "db.php";

function t(string $key, string $lang): string
{
    $text = [
        'en' => [
            'page_title' => 'Book Jetski',
            'back' => 'Back',
            'heading' => 'Jetski Booking Form',
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'agency' => 'Agency',
            'select_agency' => '-- Select Agency --',
            'location' => 'Location',
            'select_location' => '-- Select Location --',
            'boat_name' => 'Jetski Name',
            'select_boat' => '-- Select Jetski --',
            'rate_per_hour_short' => '%s/hr',
            'date' => 'Date',
            'time_slot' => 'Time Slot',
            'select_time_slot' => '-- Select Time Slot --',
            'morning' => 'Morning',
            'afternoon' => 'Afternoon',
            'duration' => 'Duration',
            'no_duration' => 'No duration options available. Please contact admin.',
            'boat_price' => 'Jetski Price',
            'location_fee' => 'Location Fee (+)',
            'estimated_price' => 'Estimated Price',
            'photo_section' => 'Upload ID Card / Passport',
            'photo_name' => 'Name on ID Card / Passport',
            'photo_name_placeholder' => 'Enter name on ID Card / Passport',
            'photo_file' => 'Photo File',
            'add_photo' => 'Add Photo',
            'remove_photo' => 'Remove',
            'book_now' => 'Book Now',
            'booking_closed_notice' => 'Booking system is currently closed by admin.'
        ],
        'th' => [
            'page_title' => 'แบบฟอร์มจองเจ็ทสกี',
            'back' => 'กลับ',
            'heading' => 'แบบฟอร์มจองเจ็ทสกี',
            'name' => 'ชื่อ',
            'email' => 'อีเมล',
            'phone' => 'เบอร์โทร',
            'agency' => 'Agency',
            'select_agency' => '-- เลือก Agency --',
            'location' => 'สถานที่',
            'select_location' => '-- เลือกสถานที่ --',
            'boat_name' => 'ชื่อเจ็ทสกี',
            'select_boat' => '-- เลือกเจ็ทสกี --',
            'rate_per_hour_short' => '%s/ชม.',
            'date' => 'วันที่',
            'time_slot' => 'ช่วงเวลา',
            'select_time_slot' => '-- เลือกช่วงเวลา --',
            'morning' => 'เช้า',
            'afternoon' => 'บ่าย',
            'duration' => 'ระยะเวลา',
            'no_duration' => 'ยังไม่มีตัวเลือกระยะเวลา กรุณาติดต่อแอดมิน',
            'boat_price' => 'ราคาเจ็ทสกี',
            'location_fee' => 'ค่าสถานที่ (+)',
            'estimated_price' => 'ราคาประเมิน',
            'photo_section' => 'อัพโหลดบัตรประชาชน / Passport',
            'photo_name' => 'ชื่อบนบัตรประชาชน / Passport',
            'photo_name_placeholder' => 'กรอกชื่อบนบัตรประชาชน',
            'photo_file' => 'ไฟล์รูปภาพ',
            'add_photo' => 'เพิ่มรูปภาพ',
            'remove_photo' => 'ลบรูปภาพ',
            'book_now' => 'จองตอนนี้',
            'booking_closed_notice' => 'ระบบจองถูกปิดการใช้งานชั่วคราวโดยผู้ดูแลระบบ'
        ]
    ];

    return $text[$lang][$key] ?? $text['en'][$key] ?? $key;
}

$lang = $_SESSION['lang'] ?? 'en';
if (!in_array($lang, ['th', 'en'], true)) {
    $lang = 'en';
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

$isAgencyDropdownEnabled = 1;
$agencyDropdownSettingStmt = $conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'agency_dropdown_enabled' LIMIT 1");
if ($agencyDropdownSettingStmt) {
    $agencyDropdownSettingStmt->execute();
    $agencyDropdownSettingStmt->bind_result($agencyDropdownSettingValue);
    if ($agencyDropdownSettingStmt->fetch()) {
        $isAgencyDropdownEnabled = ((string) $agencyDropdownSettingValue === '0') ? 0 : 1;
    }
    $agencyDropdownSettingStmt->close();
}
$isUsdCurrency = $lang === 'en';
$usdThbRate = getUsdThbRate($conn, false);
if ($usdThbRate <= 0) {
    $usdThbRate = 35.00;
}
$displayCurrencyCode = $isUsdCurrency ? 'USD' : 'THB';

$boats = [];
$boatResult = $conn->query("SELECT b.id, b.boat_name, b.hourly_rate, b.hourly_rate_usd
    FROM boats b
    LEFT JOIN (
        SELECT boat_id, COUNT(*) AS active_count
        FROM bookings
        WHERE is_archived = 0
        AND status IN ('pending', 'approved')
        GROUP BY boat_id
    ) ba ON ba.boat_id = b.id
    WHERE b.is_active = 1
    AND b.stock > COALESCE(ba.active_count, 0)
    ORDER BY b.boat_name ASC");
if ($boatResult) {
    while ($row = $boatResult->fetch_assoc()) {
        $boats[] = $row;
    }
}

$agencyOptions = [];
$agencyResult = $conn->query("SELECT id, option_name FROM booking_agency_options WHERE is_active = 1 ORDER BY sort_order ASC, option_name ASC");
if ($agencyResult) {
    while ($row = $agencyResult->fetch_assoc()) {
        $agencyOptions[] = $row;
    }
}

$locationOptions = [];
$locationResult = $conn->query("SELECT id, option_name, price, price_usd FROM booking_location_options WHERE is_active = 1 ORDER BY sort_order ASC, option_name ASC");
if ($locationResult) {
    while ($row = $locationResult->fetch_assoc()) {
        $locationOptions[] = $row;
    }
}
$hasAgencyOptions = $isAgencyDropdownEnabled === 1 && count($agencyOptions) > 0;
$hasLocationOptions = count($locationOptions) > 0;

$selectedBoatId = isset($_GET['boat_id']) ? (int) $_GET['boat_id'] : 0;
$selectedBoatName = '';
$selectedBoatRate = 0.00;

if ($selectedBoatId > 0) {
    $stmt = $conn->prepare("SELECT b.boat_name, b.hourly_rate, b.hourly_rate_usd
        FROM boats b
        LEFT JOIN (
            SELECT boat_id, COUNT(*) AS active_count
            FROM bookings
            WHERE is_archived = 0
            AND status IN ('pending', 'approved')
            GROUP BY boat_id
        ) ba ON ba.boat_id = b.id
        WHERE b.id = ?
        AND b.is_active = 1
        AND b.stock > COALESCE(ba.active_count, 0)");
    if ($stmt) {
        $stmt->bind_param("i", $selectedBoatId);
        $stmt->execute();
        $stmt->bind_result($boatNameFromDb, $boatRateFromDb, $boatRateUsdFromDb);
        if ($stmt->fetch()) {
            $selectedBoatName = $boatNameFromDb;
            $selectedBoatRate = $isUsdCurrency ? (float) $boatRateUsdFromDb : (float) $boatRateFromDb;
        } else {
            $selectedBoatId = 0;
            $selectedBoatRate = 0.00;
        }
        $stmt->close();
    }
}

$isBoatLocked = $selectedBoatId > 0 && $selectedBoatName !== '';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang === 'th' ? 'th-TH' : 'en-US'; ?>">
<head>
<title><?php echo htmlspecialchars(t('page_title', $lang), ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="stylesheet" href="css/style.css?v=20260305">
</head>
<body class="user-theme">
<main class="page page-booking">
    <section class="card">
        <a href="dashboard.php" class="btn btn-secondary"><?php echo htmlspecialchars(t('back', $lang), ENT_QUOTES, 'UTF-8'); ?></a>
        <h2><?php echo htmlspecialchars(t('heading', $lang), ENT_QUOTES, 'UTF-8'); ?></h2>

        <?php if ($isBookingEnabled !== 1): ?>
            <p class="system-notice"><?php echo htmlspecialchars(t('booking_closed_notice', $lang), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php else: ?>
    <form action="save_booking.php" method="POST" class="booking-form" id="bookingForm" enctype="multipart/form-data">

            <label for="name"><?php echo htmlspecialchars(t('name', $lang), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="name" type="text" name="name" required>

            <label for="email"><?php echo htmlspecialchars(t('email', $lang), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="email" type="email" name="email" required>

            <label for="phone"><?php echo htmlspecialchars(t('Phone & Whatapp', $lang), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="phone" type="text" name="phone" required>

            <?php if ($hasAgencyOptions): ?>
                <label for="agency_option_id"><?php echo htmlspecialchars(t('agency', $lang), ENT_QUOTES, 'UTF-8'); ?></label>
                <select id="agency_option_id" name="agency_option_id" required>
                    <option value=""><?php echo htmlspecialchars(t('select_agency', $lang), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php foreach ($agencyOptions as $agencyOption): ?>
                        <option value="<?php echo (int) $agencyOption['id']; ?>"><?php echo htmlspecialchars($agencyOption['option_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <label for="boat"><?php echo htmlspecialchars(t('boat_name', $lang), ENT_QUOTES, 'UTF-8'); ?></label>
            <?php if ($isBoatLocked): ?>
                <input id="boat" type="text" value="<?php echo htmlspecialchars($selectedBoatName, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                <input type="hidden" id="boat_id_hidden" name="boat_id" value="<?php echo (int) $selectedBoatId; ?>">
                <input type="hidden" id="boat_base_price" value="<?php echo htmlspecialchars(number_format($selectedBoatRate, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
            <?php else: ?>
                <select id="boat_id" name="boat_id" required>
                    <option value="" data-base-price="0.00"><?php echo htmlspecialchars(t('select_boat', $lang), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php foreach ($boats as $boat): ?>
                        <?php $displayRate = $isUsdCurrency ? (float) ($boat['hourly_rate_usd'] ?? 0) : (float) $boat['hourly_rate']; ?>
                        <option value="<?php echo (int) $boat['id']; ?>" data-base-price="<?php echo htmlspecialchars(number_format($displayRate, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php
                                $displayCurrency = $isUsdCurrency ? 'USD' : 'THB';
                                echo htmlspecialchars($boat['boat_name'], ENT_QUOTES, 'UTF-8');
                                echo ' (' . htmlspecialchars(sprintf(t('rate_per_hour_short', $lang), number_format($displayRate, 2) . ' ' . $displayCurrency), ENT_QUOTES, 'UTF-8') . ')';
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <label for="booking_date"><?php echo htmlspecialchars(t('date', $lang), ENT_QUOTES, 'UTF-8'); ?></label>
            <input
                id="booking_date"
                type="date"
                name="booking_date"
                lang="<?php echo $lang === 'th' ? 'th-TH' : 'en-US'; ?>"
                required
            >

            <label for="time_slot"><?php echo htmlspecialchars(t('time_slot', $lang), ENT_QUOTES, 'UTF-8'); ?></label>
            <select id="time_slot" name="time_slot" required>
                <option value=""><?php echo htmlspecialchars(t('select_time_slot', $lang), ENT_QUOTES, 'UTF-8'); ?></option>
                <option value="morning"><?php echo htmlspecialchars(t('morning', $lang), ENT_QUOTES, 'UTF-8'); ?></option>
                <option value="afternoon"><?php echo htmlspecialchars(t('afternoon', $lang), ENT_QUOTES, 'UTF-8'); ?></option>
            </select>

            <label for="location_option_id"><?php echo htmlspecialchars(t('location', $lang), ENT_QUOTES, 'UTF-8'); ?></label>
            <select id="location_option_id" name="location_option_id" <?php echo $hasLocationOptions ? 'required' : ''; ?>>
                <option value="" data-price="0.00"><?php echo htmlspecialchars(t('select_location', $lang), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php foreach ($locationOptions as $locationOption): ?>
                    <?php $displayLocationPrice = $isUsdCurrency ? (float) ($locationOption['price_usd'] ?? 0) : (float) ($locationOption['price'] ?? 0); ?>
                    <option value="<?php echo (int) $locationOption['id']; ?>" data-price="<?php echo htmlspecialchars(number_format($displayLocationPrice, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php
                            $displayCurrency = $isUsdCurrency ? 'USD' : 'THB';
                            echo htmlspecialchars((string) $locationOption['option_name'], ENT_QUOTES, 'UTF-8');
                            echo ' - ' . htmlspecialchars(number_format($displayLocationPrice, 2) . ' ' . $displayCurrency, ENT_QUOTES, 'UTF-8');
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="boat_base_price_display"><?php echo htmlspecialchars(t('boat_price', $lang) . ' (' . $displayCurrencyCode . ')', ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="boat_base_price_display" type="text" value="0.00" readonly>

            <label for="location_fee_display"><?php echo htmlspecialchars(t('location_fee', $lang) . ' (' . $displayCurrencyCode . ')', ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="location_fee_display" type="text" value="0.00" readonly>

            <label for="estimated_price"><?php echo htmlspecialchars(t('estimated_price', $lang) . ' (' . $displayCurrencyCode . ')', ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="estimated_price" type="text" value="0.00" readonly>

            <label><?php echo htmlspecialchars(t('photo_section', $lang), ENT_QUOTES, 'UTF-8'); ?></label>
            <div class="attachment-row attachment-row-head">
                <span class="attachment-col-label"><?php echo htmlspecialchars(t('photo_name', $lang), ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="attachment-col-label"><?php echo htmlspecialchars(t('photo_file', $lang), ENT_QUOTES, 'UTF-8'); ?></span>
                <span></span>
            </div>
            <div id="attachmentContainer" class="attachment-list">
                <div class="attachment-row">
                    <input type="text" name="attachment_title[]" placeholder="<?php echo htmlspecialchars(t('photo_name_placeholder', $lang), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="file" name="attachment_file[]" accept="image/*">
                    <button type="button" class="btn btn-secondary attachment-remove-btn js-remove-attachment"><?php echo htmlspecialchars(t('remove_photo', $lang), ENT_QUOTES, 'UTF-8'); ?></button>
                </div>
            </div>
            <button type="button" class="btn btn-secondary attachment-add-btn" id="addAttachmentBtn"><?php echo htmlspecialchars(t('add_photo', $lang), ENT_QUOTES, 'UTF-8'); ?></button>

            <button type="submit" class="btn"><?php echo htmlspecialchars(t('book_now', $lang), ENT_QUOTES, 'UTF-8'); ?></button>
        </form>
        <?php endif; ?>
    </section>
</main>

<script>
(function () {
    var estimate = document.getElementById('estimated_price');
    var boatPriceDisplay = document.getElementById('boat_base_price_display');
    var locationFeeDisplay = document.getElementById('location_fee_display');
    var boatSelect = document.querySelector('select#boat_id');
    var boatBasePriceHidden = document.getElementById('boat_base_price');
    var locationSelect = document.getElementById('location_option_id');
    var attachmentContainer = document.getElementById('attachmentContainer');
    var addAttachmentBtn = document.getElementById('addAttachmentBtn');

    if (!estimate || !locationSelect || !boatPriceDisplay || !locationFeeDisplay) {
        return;
    }

    function selectedBoatBasePrice() {
        if (boatSelect) {
            var selectedBoatOption = boatSelect.options[boatSelect.selectedIndex];
            if (!selectedBoatOption) {
                return 0;
            }

            return parseFloat(selectedBoatOption.getAttribute('data-base-price') || '0');
        }

        if (boatBasePriceHidden) {
            return parseFloat(boatBasePriceHidden.value || '0');
        }

        return 0;
    }

    function selectedLocationPrice() {
        var selectedOption = locationSelect.options[locationSelect.selectedIndex];
        if (!selectedOption) {
            return 0;
        }
        return parseFloat(selectedOption.getAttribute('data-price') || '0');
    }

    function updateEstimate() {
        var boatBasePrice = selectedBoatBasePrice();
        var locationPrice = selectedLocationPrice();
        var totalPrice = boatBasePrice + locationPrice;

        boatPriceDisplay.value = boatBasePrice.toFixed(2);
        locationFeeDisplay.value = locationPrice.toFixed(2);
        estimate.value = totalPrice.toFixed(2);
    }

    locationSelect.addEventListener('change', updateEstimate);
    if (boatSelect) {
        boatSelect.addEventListener('change', updateEstimate);
    }

    function createAttachmentRow() {
        var row = document.createElement('div');
        row.className = 'attachment-row';
        row.innerHTML =
            '<input type="text" name="attachment_title[]" placeholder="<?php echo htmlspecialchars(t('photo_name_placeholder', $lang), ENT_QUOTES, 'UTF-8'); ?>">' +
            '<input type="file" name="attachment_file[]" accept="image/*">' +
            '<button type="button" class="btn btn-secondary attachment-remove-btn js-remove-attachment"><?php echo htmlspecialchars(t('remove_photo', $lang), ENT_QUOTES, 'UTF-8'); ?></button>';
        return row;
    }

    if (addAttachmentBtn && attachmentContainer) {
        addAttachmentBtn.addEventListener('click', function () {
            attachmentContainer.appendChild(createAttachmentRow());
        });

        attachmentContainer.addEventListener('click', function (event) {
            if (!event.target.classList.contains('js-remove-attachment')) {
                return;
            }

            var row = event.target.closest('.attachment-row');
            if (!row) {
                return;
            }

            row.remove();
        });
    }

    updateEstimate();
})();
</script>

</body>
</html>

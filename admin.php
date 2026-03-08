<?php
include "admin_auth.php";
include "db.php";

function uploadBoatImage(string $fieldName, string $currentImagePath = ''): string
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return $currentImagePath;
    }

    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return $currentImagePath;
    }

    $originalName = $_FILES[$fieldName]['name'];
    $tmpPath = $_FILES[$fieldName]['tmp_name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (!in_array($extension, $allowed, true)) {
        return $currentImagePath;
    }

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'boats';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $newFileName = uniqid('boat_', true) . '.' . $extension;
    $destination = $uploadDir . DIRECTORY_SEPARATOR . $newFileName;

    if (!move_uploaded_file($tmpPath, $destination)) {
        return $currentImagePath;
    }

    return 'uploads/boats/' . $newFileName;
}

function syncBoatBookedStatus(mysqli $conn, int $boatId): void
{
    if ($boatId <= 0) {
        return;
    }

    $stock = -1;
    $stockStmt = $conn->prepare("SELECT stock FROM boats WHERE id = ?");
    if ($stockStmt) {
        $stockStmt->bind_param("i", $boatId);
        $stockStmt->execute();
        $stockStmt->bind_result($stockFromDb);
        if ($stockStmt->fetch()) {
            $stock = (int) $stockFromDb;
        }
        $stockStmt->close();
    }

    if ($stock < 0) {
        return;
    }

    $countActiveBookings = 0;
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM bookings
        WHERE boat_id = ?
        AND is_archived = 0
        AND status IN ('pending', 'approved')");

    if ($countStmt) {
        $countStmt->bind_param("i", $boatId);
        $countStmt->execute();
        $countStmt->bind_result($countActiveBookings);
        $countStmt->fetch();
        $countStmt->close();
    }

    $isBooked = ($stock <= 0 || $countActiveBookings >= $stock) ? 1 : 0;
    $updateStmt = $conn->prepare("UPDATE boats SET is_booked = ? WHERE id = ?");

    if ($updateStmt) {
        $updateStmt->bind_param("ii", $isBooked, $boatId);
        $updateStmt->execute();
        $updateStmt->close();
    }
}

function isBookingSystemEnabled(mysqli $conn): int
{
    $enabled = 1;
    $stmt = $conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'booking_enabled' LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $stmt->bind_result($settingValue);
        if ($stmt->fetch()) {
            $enabled = ((string) $settingValue === '0') ? 0 : 1;
        }
        $stmt->close();
    }

    return $enabled;
}

function isAgencyDropdownEnabled(mysqli $conn): int
{
    $enabled = 1;
    $stmt = $conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'agency_dropdown_enabled' LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $stmt->bind_result($settingValue);
        if ($stmt->fetch()) {
            $enabled = ((string) $settingValue === '0') ? 0 : 1;
        }
        $stmt->close();
    }

    return $enabled;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_booking_system') {
        $bookingEnabled = (int) ($_POST['booking_enabled'] ?? 1);
        $bookingEnabled = $bookingEnabled === 0 ? 0 : 1;

        $stmt = $conn->prepare("INSERT INTO app_settings (setting_key, setting_value)
            VALUES ('booking_enabled', ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        if ($stmt) {
            $settingValue = (string) $bookingEnabled;
            $stmt->bind_param("s", $settingValue);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($action === 'update_agency_dropdown_setting') {
        $agencyDropdownEnabled = (int) ($_POST['agency_dropdown_enabled'] ?? 1);
        $agencyDropdownEnabled = $agencyDropdownEnabled === 0 ? 0 : 1;

        $stmt = $conn->prepare("INSERT INTO app_settings (setting_key, setting_value)
            VALUES ('agency_dropdown_enabled', ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        if ($stmt) {
            $settingValue = (string) $agencyDropdownEnabled;
            $stmt->bind_param("s", $settingValue);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($action === 'update_usd_rate') {
        $usdRate = (float) ($_POST['usd_thb_rate'] ?? 0);
        if ($usdRate > 0) {
            appSettingSet($conn, 'usd_thb_rate', number_format($usdRate, 6, '.', ''));
            appSettingSet($conn, 'usd_thb_rate_fetched_at', date('Y-m-d H:i:s'));
        }
    }

    if ($action === 'add_boat') {
        $boatName = trim($_POST['boat_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $hourlyRate = (float) ($_POST['hourly_rate'] ?? 0);
        $hourlyRateUsd = (float) ($_POST['hourly_rate_usd'] ?? 0);
        $stock = max(0, (int) ($_POST['stock'] ?? 0));
        $imagePath = uploadBoatImage('boat_image');

        if ($boatName !== '') {
            $stmt = $conn->prepare("INSERT INTO boats (boat_name, description, image_path, is_active, is_booked, hourly_rate, hourly_rate_usd, stock) VALUES (?, ?, ?, 1, 0, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sssddi", $boatName, $description, $imagePath, $hourlyRate, $hourlyRateUsd, $stock);
                $stmt->execute();
                $newBoatId = (int) $stmt->insert_id;
                $stmt->close();
                syncBoatBookedStatus($conn, $newBoatId);
            }
        }
    }

    if ($action === 'update_boat') {
        $boatId = (int) ($_POST['boat_id'] ?? 0);
        if (isset($_POST['delete_item']) && $_POST['delete_item'] === '1' && $boatId > 0) {
            $stmt = $conn->prepare("DELETE FROM boats WHERE id = ?");
            $stmt && $stmt->bind_param("i", $boatId) && $stmt->execute() && $stmt->close();
        } else {
            $boatName = trim($_POST['boat_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $hourlyRate = (float) ($_POST['hourly_rate'] ?? 0);
            $hourlyRateUsd = (float) ($_POST['hourly_rate_usd'] ?? 0);
            $stock = max(0, (int) ($_POST['stock'] ?? 0));
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $currentImagePath = trim($_POST['current_image_path'] ?? '');
            $imagePath = uploadBoatImage('boat_image', $currentImagePath);

            if ($boatId > 0 && $boatName !== '') {
                $stmt = $conn->prepare("UPDATE boats SET boat_name = ?, description = ?, image_path = ?, hourly_rate = ?, hourly_rate_usd = ?, stock = ?, is_active = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("sssddiii", $boatName, $description, $imagePath, $hourlyRate, $hourlyRateUsd, $stock, $isActive, $boatId);
                    $stmt->execute();
                    $stmt->close();
                    syncBoatBookedStatus($conn, $boatId);
                }
            }
        }
    }

    if ($action === 'add_duration_option') {
        $label = trim($_POST['label'] ?? '');
        $minutes = (int) ($_POST['minutes'] ?? 0);
        $price = (float) ($_POST['price'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($label !== '' && $minutes > 0 && $price >= 0) {
            $maxSort = 0;
            $sortResult = $conn->query("SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM booking_duration_options");
            if ($sortResult && $sortRow = $sortResult->fetch_assoc()) {
                $maxSort = (int) $sortRow['max_sort'];
            }
            $sortOrder = $maxSort + 1;

            $stmt = $conn->prepare("INSERT INTO booking_duration_options (label, minutes, price, is_active, sort_order) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sidii", $label, $minutes, $price, $isActive, $sortOrder);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    if ($action === 'update_duration_option') {
        $optionId = (int) ($_POST['option_id'] ?? 0);
        $label = trim($_POST['label'] ?? '');
        $minutes = (int) ($_POST['minutes'] ?? 0);
        $price = (float) ($_POST['price'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($optionId > 0 && $label !== '' && $minutes > 0 && $price >= 0) {
            $stmt = $conn->prepare("UPDATE booking_duration_options SET label = ?, minutes = ?, price = ?, is_active = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("sidii", $label, $minutes, $price, $isActive, $optionId);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    if ($action === 'add_agency_option') {
        $optionName = trim($_POST['option_name'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($optionName !== '') {
            $maxSort = 0;
            $sortResult = $conn->query("SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM booking_agency_options");
            if ($sortResult && $sortRow = $sortResult->fetch_assoc()) {
                $maxSort = (int) $sortRow['max_sort'];
            }
            $sortOrder = $maxSort + 1;

            $stmt = $conn->prepare("INSERT INTO booking_agency_options (option_name, is_active, sort_order) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sii", $optionName, $isActive, $sortOrder);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    if ($action === 'update_agency_option') {
        $optionId = (int) ($_POST['option_id'] ?? 0);
        if (isset($_POST['delete_item']) && $_POST['delete_item'] === '1' && $optionId > 0) {
            $stmt = $conn->prepare("DELETE FROM booking_agency_options WHERE id = ?");
            $stmt && $stmt->bind_param("i", $optionId) && $stmt->execute() && $stmt->close();
        } else {
            $optionName = trim($_POST['option_name'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($optionId > 0 && $optionName !== '') {
                $stmt = $conn->prepare("UPDATE booking_agency_options SET option_name = ?, is_active = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("sii", $optionName, $isActive, $optionId);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }

    if ($action === 'add_location_option') {
        $optionName = trim($_POST['option_name'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $priceUsd = (float) ($_POST['price_usd'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($optionName !== '' && $price >= 0) {
            $maxSort = 0;
            $sortResult = $conn->query("SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM booking_location_options");
            if ($sortResult && $sortRow = $sortResult->fetch_assoc()) {
                $maxSort = (int) $sortRow['max_sort'];
            }
            $sortOrder = $maxSort + 1;

            $stmt = $conn->prepare("INSERT INTO booking_location_options (option_name, price, price_usd, is_active, sort_order) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sddii", $optionName, $price, $priceUsd, $isActive, $sortOrder);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    if ($action === 'update_location_option') {
        $optionId = (int) ($_POST['option_id'] ?? 0);
        if (isset($_POST['delete_item']) && $_POST['delete_item'] === '1' && $optionId > 0) {
            $stmt = $conn->prepare("DELETE FROM booking_location_options WHERE id = ?");
            $stmt && $stmt->bind_param("i", $optionId) && $stmt->execute() && $stmt->close();
        } else {
            $optionName = trim($_POST['option_name'] ?? '');
            $price = (float) ($_POST['price'] ?? 0);
            $priceUsd = (float) ($_POST['price_usd'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($optionId > 0 && $optionName !== '' && $price >= 0) {
                $stmt = $conn->prepare("UPDATE booking_location_options SET option_name = ?, price = ?, price_usd = ?, is_active = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("sddii", $optionName, $price, $priceUsd, $isActive, $optionId);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }

    if ($action === 'update_booking_status') {
        $bookingId = (int) ($_POST['booking_id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        $adminNote = trim($_POST['admin_note'] ?? '');

        if ($bookingId > 0 && in_array($status, ['approved', 'rejected'], true)) {
            $boatId = 0;
            $bookingDate = '';
            $startTime = '';
            $endTime = '';

            $bookingStmt = $conn->prepare("SELECT boat_id, booking_date, start_time, end_time FROM bookings WHERE id = ?");
            if ($bookingStmt) {
                $bookingStmt->bind_param("i", $bookingId);
                $bookingStmt->execute();
                $bookingStmt->bind_result($boatIdFromDb, $bookingDateFromDb, $startTimeFromDb, $endTimeFromDb);
                if ($bookingStmt->fetch()) {
                    $boatId = (int) $boatIdFromDb;
                    $bookingDate = (string) $bookingDateFromDb;
                    $startTime = (string) $startTimeFromDb;
                    $endTime = (string) $endTimeFromDb;
                }
                $bookingStmt->close();
            }

            if ($status === 'approved') {
                $boatStock = 0;
                $stockStmt = $conn->prepare("SELECT stock FROM boats WHERE id = ?");
                if ($stockStmt) {
                    $stockStmt->bind_param("i", $boatId);
                    $stockStmt->execute();
                    $stockStmt->bind_result($boatStockFromDb);
                    if ($stockStmt->fetch()) {
                        $boatStock = (int) $boatStockFromDb;
                    }
                    $stockStmt->close();
                }

                $overlapCount = 0;
                $overlapStmt = $conn->prepare("SELECT COUNT(*) FROM bookings
                    WHERE boat_id = ?
                    AND booking_date = ?
                    AND status = 'approved'
                    AND id <> ?
                    AND start_time < ?
                    AND end_time > ?");

                if ($overlapStmt) {
                    $overlapStmt->bind_param("isiss", $boatId, $bookingDate, $bookingId, $endTime, $startTime);
                    $overlapStmt->execute();
                    $overlapStmt->bind_result($overlapCount);
                    $overlapStmt->fetch();
                    $overlapStmt->close();
                }

                if ($boatStock <= 0) {
                    $status = 'rejected';
                    $adminNote = 'Auto rejected: boat stock is 0.';
                } elseif ($overlapCount >= $boatStock) {
                    $status = 'rejected';
                    $adminNote = 'Auto rejected: no stock left for the selected time range.';
                }
            }

            $stmt = $conn->prepare("UPDATE bookings SET status = ?, admin_note = ?, approved_at = NOW() WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("ssi", $status, $adminNote, $bookingId);
                $stmt->execute();
                $stmt->close();
            }

            syncBoatBookedStatus($conn, $boatId);
        }
    }

    if ($action === 'complete_booking') {
        $bookingId = (int) ($_POST['booking_id'] ?? 0);

        if ($bookingId > 0) {
            $bookingDataStmt = $conn->prepare("SELECT id, boat_id, boat, name, email, phone, agency, location, booking_date, time_slot, start_time, end_time, total_price, status, admin_note, approved_at
                FROM bookings
                WHERE id = ? AND is_archived = 0
                LIMIT 1");

            if ($bookingDataStmt) {
                $bookingDataStmt->bind_param("i", $bookingId);
                $bookingDataStmt->execute();
                $bookingDataStmt->bind_result(
                    $sourceBookingId,
                    $boatId,
                    $boatName,
                    $customerName,
                    $customerEmail,
                    $customerPhone,
                    $bookingAgency,
                    $bookingLocation,
                    $bookingDate,
                    $bookingTimeSlot,
                    $startTime,
                    $endTime,
                    $totalPrice,
                    $bookingStatus,
                    $adminNote,
                    $approvedAt
                );

                if ($bookingDataStmt->fetch()) {
                    $bookingDataStmt->close();

                    $historyStmt = $conn->prepare("INSERT INTO booking_history
                        (source_booking_id, boat_id, boat, name, email, phone, agency, location, booking_date, time_slot, start_time, end_time, total_price, status, admin_note, approved_at, completed_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

                    if ($historyStmt) {
                        $finalStatus = 'completed';
                        $historyStmt->bind_param(
                            "iissssssssssdsss",
                            $sourceBookingId,
                            $boatId,
                            $boatName,
                            $customerName,
                            $customerEmail,
                            $customerPhone,
                            $bookingAgency,
                            $bookingLocation,
                            $bookingDate,
                            $bookingTimeSlot,
                            $startTime,
                            $endTime,
                            $totalPrice,
                            $finalStatus,
                            $adminNote,
                            $approvedAt
                        );
                        $historyStmt->execute();
                        $historyStmt->close();
                    }

                    $archiveStmt = $conn->prepare("UPDATE bookings SET status = 'completed', is_archived = 1, approved_at = COALESCE(approved_at, NOW()) WHERE id = ?");
                    if ($archiveStmt) {
                        $archiveStmt->bind_param("i", $bookingId);
                        $archiveStmt->execute();
                        $archiveStmt->close();
                    }

                    syncBoatBookedStatus($conn, (int) $boatId);
                } else {
                    $bookingDataStmt->close();
                }
            }
        }
    }

    header("Location: admin.php");
    exit;
}

$boats = [];
$boatsResult = $conn->query("SELECT id, boat_name, description, image_path, is_active, is_booked, hourly_rate, hourly_rate_usd, stock FROM boats ORDER BY id DESC");
if ($boatsResult) {
    while ($row = $boatsResult->fetch_assoc()) {
        $boats[] = $row;
    }
}

$bookings = [];
$bookingSql = "SELECT id, name, email, phone, agency, location, boat, booking_date, time_slot, start_time, end_time, total_price, status, admin_note, approved_at
               FROM bookings
               WHERE is_archived = 0
               ORDER BY id DESC";
$bookingsResult = $conn->query($bookingSql);
if ($bookingsResult) {
    while ($row = $bookingsResult->fetch_assoc()) {
        $bookings[] = $row;
    }
}

$bookingAttachmentsMap = [];
if (count($bookings) > 0) {
    $bookingIds = [];
    foreach ($bookings as $bookingRow) {
        $bookingId = (int) ($bookingRow['id'] ?? 0);
        if ($bookingId > 0) {
            $bookingIds[] = $bookingId;
        }
    }

    $bookingIds = array_values(array_unique($bookingIds));
    if (count($bookingIds) > 0) {
        $bookingIdList = implode(',', $bookingIds);
        $attachmentResult = $conn->query("SELECT id, booking_id, title, file_path, original_name
            FROM booking_attachments
            WHERE booking_id IN ($bookingIdList)
            ORDER BY id ASC");

        if ($attachmentResult) {
            while ($attachmentRow = $attachmentResult->fetch_assoc()) {
                $attachmentBookingId = (int) ($attachmentRow['booking_id'] ?? 0);
                if ($attachmentBookingId <= 0) {
                    continue;
                }

                if (!isset($bookingAttachmentsMap[$attachmentBookingId])) {
                    $bookingAttachmentsMap[$attachmentBookingId] = [];
                }
                $bookingAttachmentsMap[$attachmentBookingId][] = $attachmentRow;
            }
        }
    }
}

$durationOptions = [];
$durationOptionsResult = $conn->query("SELECT id, label, minutes, price, is_active FROM booking_duration_options ORDER BY sort_order ASC, minutes ASC");
if ($durationOptionsResult) {
    while ($row = $durationOptionsResult->fetch_assoc()) {
        $durationOptions[] = $row;
    }
}

$agencyOptions = [];
$agencyOptionsResult = $conn->query("SELECT id, option_name, is_active FROM booking_agency_options ORDER BY sort_order ASC, option_name ASC");
if ($agencyOptionsResult) {
    while ($row = $agencyOptionsResult->fetch_assoc()) {
        $agencyOptions[] = $row;
    }
}

$locationOptions = [];
$locationOptionsResult = $conn->query("SELECT id, option_name, price, price_usd, is_active FROM booking_location_options ORDER BY sort_order ASC, option_name ASC");
if ($locationOptionsResult) {
    while ($row = $locationOptionsResult->fetch_assoc()) {
        $locationOptions[] = $row;
    }
}

$isBookingEnabled = isBookingSystemEnabled($conn);
$isAgencyDropdownEnabled = isAgencyDropdownEnabled($conn);
$currencyRateSourceUrl = appSettingGet($conn, 'currency_rate_source_url', '');
$usdThbRate = getUsdThbRate($conn, false);
$usdThbRateFetchedAt = appSettingGet($conn, 'usd_thb_rate_fetched_at', '');
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin Panel</title>
<link rel="stylesheet" href="css/style.css?v=20260305">
</head>
<body>
<main class="page admin-page">
    <section class="card card-admin">
        <div class="admin-header">
            <h2>Admin Panel</h2>
            <div class="admin-nav-actions">
                <!-- <a href="#booking-approvals" class="btn">Booking Approvals</a> -->
                <a href="reports.php" class="btn">Reports</a>
                <a href="admin_logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </div>

        <div class="admin-layout-grid">
            <div class="admin-layout-col">

        <div class="admin-system-box admin-zone">
            <div>
                <h3 class="admin-system-title">Booking System</h3>
                <p class="admin-system-text">
                    Current status:
                    <span class="status-pill <?php echo $isBookingEnabled === 1 ? 'status-available' : 'status-booked'; ?>">
                        <?php echo $isBookingEnabled === 1 ? 'OPEN' : 'CLOSED'; ?>
                    </span>
                </p>
            </div>
            <form method="POST" class="admin-system-form">
                <input type="hidden" name="action" value="update_booking_system">
                <input type="hidden" name="booking_enabled" value="<?php echo $isBookingEnabled === 1 ? '0' : '1'; ?>">
                <button type="submit" class="btn <?php echo $isBookingEnabled === 1 ? 'btn-danger' : ''; ?>">
                    <?php echo $isBookingEnabled === 1 ? 'Close Booking' : 'Open Booking'; ?>
                </button>
            </form>
        </div>



        <div class="admin-zone">
            <h3>Manage Boats</h3>
            <form method="POST" enctype="multipart/form-data" class="boat-admin-form boat-admin-form-add">
            <input type="hidden" name="action" value="add_boat">

            <div class="boat-admin-fields">
                <label>Boat Name</label>
                <input type="text" name="boat_name" placeholder="New boat name" required>
            </div>

            <div class="boat-admin-fields">
                <label>Hourly Rate (THB)</label>
                <input type="number" name="hourly_rate" min="0" step="0.01" value="0.00" required>
            </div>

            <div class="boat-admin-fields">
                <label>Hourly Rate (USD)</label>
                <input type="number" name="hourly_rate_usd" min="0" step="0.01" value="0.00" required>
            </div>

            <div class="boat-admin-fields">
                <label>Stock</label>
                <input type="number" name="stock" min="0" step="1" value="1" required>
            </div>

            <div class="boat-admin-fields">
                <label>Detail</label>
                <textarea name="description" rows="3" placeholder="Boat detail"></textarea>
            </div>

            <div class="boat-admin-fields">
                <label>Image</label>
                <input type="file" name="boat_image" accept="image/*">
            </div>

            <button type="submit" class="btn">Add Boat</button>
        </form>

        <div class="boat-admin-grid">
            <?php if (count($boats) === 0): ?>
                <p class="empty-text">No boats found.</p>
            <?php else: ?>
                <?php foreach ($boats as $boat): ?>
                    <form method="POST" enctype="multipart/form-data" class="boat-admin-form">
                        <input type="hidden" name="action" value="update_boat">
                        <input type="hidden" name="boat_id" value="<?php echo (int) $boat['id']; ?>">
                        <input type="hidden" name="current_image_path" value="<?php echo htmlspecialchars((string) ($boat['image_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

                        <p class="boat-admin-id">Boat ID: <?php echo (int) $boat['id']; ?></p>
                        <p class="boat-admin-id">Stock: <?php echo (int) ($boat['stock'] ?? 0); ?></p>

                        <span class="status-pill <?php echo ((int) $boat['is_booked'] === 1) ? 'status-booked' : 'status-available'; ?>">
                            <?php echo ((int) $boat['is_booked'] === 1) ? 'OUT OF STOCK' : 'AVAILABLE'; ?>
                        </span>

                        <?php if (!empty($boat['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($boat['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Boat image" class="boat-preview">
                        <?php else: ?>
                            <div class="boat-preview boat-preview-empty">No image</div>
                        <?php endif; ?>

                        <label>Boat Name</label>
                        <input type="text" name="boat_name" value="<?php echo htmlspecialchars($boat['boat_name'], ENT_QUOTES, 'UTF-8'); ?>" required>

                        <label>Hourly Rate (THB)</label>
                        <input type="number" name="hourly_rate" min="0" step="0.01" value="<?php echo htmlspecialchars(number_format((float) $boat['hourly_rate'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" required>

                        <label>Hourly Rate (USD)</label>
                        <input type="number" name="hourly_rate_usd" min="0" step="0.01" value="<?php echo htmlspecialchars(number_format((float) ($boat['hourly_rate_usd'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" required>

                        <label>Stock</label>
                        <input type="number" name="stock" min="0" step="1" value="<?php echo (int) ($boat['stock'] ?? 0); ?>" required>

                        <label>Detail</label>
                        <textarea name="description" rows="4"><?php echo htmlspecialchars((string) ($boat['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>

                        <label>Replace Image</label>
                        <input type="file" name="boat_image" accept="image/*">

                        <label class="checkbox-wrap">
                            <input type="checkbox" name="is_active" <?php echo ((int) $boat['is_active'] === 1) ? 'checked' : ''; ?>>
                            Show on dashboard
                        </label>

                        <div style="display: flex; gap: var(--space-2); margin-top: var(--space-2);">
                            <button type="submit" class="btn" style="flex: 1;">Save Boat</button>
                            <button type="submit" name="delete_item" value="1" class="btn btn-danger" onclick="return confirm('Delete this boat?');">Delete</button>
                        </div>
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        </div>

            </div>
            <div class="admin-layout-col">

        <!-- Duration options hidden to clean up view
        <h3>Duration Options</h3>
        <form method="POST" class="inline-form">
            <input type="hidden" name="action" value="add_duration_option">
            <input type="text" name="label" placeholder="Label (e.g. 2 hours)" required>
            <input type="number" name="minutes" min="1" step="1" placeholder="Minutes" required>
            <input type="number" name="price" min="0" step="0.01" placeholder="Price" required>
            <label class="checkbox-wrap">
                <input type="checkbox" name="is_active" checked>
                Active
            </label>
            <button type="submit" class="btn">Add Option</button>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Edit Option</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($durationOptions) === 0): ?>
                        <tr>
                            <td colspan="2">No duration options found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($durationOptions as $option): ?>
                            <tr>
                                <td><?php echo (int) $option['id']; ?></td>
                                <td>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="update_duration_option">
                                        <input type="hidden" name="option_id" value="<?php echo (int) $option['id']; ?>">
                                        <input type="text" name="label" value="<?php echo htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                        <input type="number" name="minutes" min="1" step="1" value="<?php echo (int) $option['minutes']; ?>" required>
                                        <input type="number" name="price" min="0" step="0.01" value="<?php echo htmlspecialchars(number_format((float) $option['price'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                        <label class="checkbox-wrap">
                                            <input type="checkbox" name="is_active" <?php echo ((int) $option['is_active'] === 1) ? 'checked' : ''; ?>>
                                            Active
                                        </label>
                                        <button type="submit" class="btn">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        -->

        <div class="admin-zone">
            <div class="section-head">
                <h3>Agency Dropdown Options</h3>
            <form method="POST" class="inline-form">
                <input type="hidden" name="action" value="update_agency_dropdown_setting">
                <input type="hidden" name="agency_dropdown_enabled" value="<?php echo $isAgencyDropdownEnabled === 1 ? '0' : '1'; ?>">
                <button type="submit" class="btn <?php echo $isAgencyDropdownEnabled === 1 ? 'btn-danger' : ''; ?>">
                    <?php echo $isAgencyDropdownEnabled === 1 ? 'Hide Agency Field' : 'Show Agency Field'; ?>
                </button>
            </form>
        </div>
        <form method="POST" class="inline-form">
            <input type="hidden" name="action" value="add_agency_option">
            <input type="text" name="option_name" placeholder="Agency option" required>
            <label class="checkbox-wrap">
                <input type="checkbox" name="is_active" checked>
                Active
            </label>
            <button type="submit" class="btn">Add Agency</button>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Edit Agency Option</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($agencyOptions) === 0): ?>
                        <tr>
                            <td colspan="2">No agency options found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($agencyOptions as $option): ?>
                            <tr>
                                <td><?php echo (int) $option['id']; ?></td>
                                <td>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="update_agency_option">
                                        <input type="hidden" name="option_id" value="<?php echo (int) $option['id']; ?>">
                                        <input type="text" name="option_name" value="<?php echo htmlspecialchars((string) $option['option_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                        <label class="checkbox-wrap">
                                            <input type="checkbox" name="is_active" <?php echo ((int) $option['is_active'] === 1) ? 'checked' : ''; ?>>
                                            Active
                                        </label>
                                        <div style="display: flex; gap: var(--space-2);">
                                            <button type="submit" class="btn">Save</button>
                                            <button type="submit" name="delete_item" value="1" class="btn btn-danger" onclick="return confirm('Delete this agency?');">Delete</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </div>

        <div class="admin-zone">
            <h3>Location Dropdown Options</h3>
            <form method="POST" class="inline-form">
            <input type="hidden" name="action" value="add_location_option">
            <input type="text" name="option_name" placeholder="Location option" required>
            <input type="number" name="price" min="0" step="0.01" placeholder="Price (THB)" value="0.00" required>
            <input type="number" name="price_usd" min="0" step="0.01" placeholder="Price (USD)" value="0.00" required>
            <label class="checkbox-wrap">
                <input type="checkbox" name="is_active" checked>
                Active
            </label>
            <button type="submit" class="btn">Add Location</button>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Edit Location Option</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($locationOptions) === 0): ?>
                        <tr>
                            <td colspan="2">No location options found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($locationOptions as $option): ?>
                            <tr>
                                <td><?php echo (int) $option['id']; ?></td>
                                <td>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="update_location_option">
                                        <input type="hidden" name="option_id" value="<?php echo (int) $option['id']; ?>">
                                        <input type="text" name="option_name" value="<?php echo htmlspecialchars((string) $option['option_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                        <input type="number" name="price" min="0" step="0.01" placeholder="THB" value="<?php echo htmlspecialchars(number_format((float) ($option['price'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                        <input type="number" name="price_usd" min="0" step="0.01" placeholder="USD" value="<?php echo htmlspecialchars(number_format((float) ($option['price_usd'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                        <label class="checkbox-wrap">
                                            <input type="checkbox" name="is_active" <?php echo ((int) $option['is_active'] === 1) ? 'checked' : ''; ?>>
                                            Active
                                        </label>
                                        <div style="display: flex; gap: var(--space-2);">
                                            <button type="submit" class="btn">Save</button>
                                            <button type="submit" name="delete_item" value="1" class="btn btn-danger" onclick="return confirm('Delete this location?');">Delete</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </div>

            </div>
        </div>

        <div class="admin-zone">
            <div class="section-head" id="booking-approvals">
                <h3>Booking Approvals</h3>
            </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Agency</th>
                        <th>Location</th>
                        <th>Boat</th>
                        <th>Date/Slot</th>
                        <th>Time Range</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Admin Note</th>
                        <th>Photos</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($bookings) === 0): ?>
                        <tr>
                            <td colspan="12">No bookings found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?php echo (int) $booking['id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8'); ?><br>
                                    <?php echo htmlspecialchars($booking['email'], ENT_QUOTES, 'UTF-8'); ?><br>
                                    <?php echo htmlspecialchars($booking['phone'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td><?php echo htmlspecialchars((string) ($booking['agency'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($booking['location'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($booking['boat'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php echo htmlspecialchars((string) ($booking['booking_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><br>
                                    <?php
                                    $slotLabel = ((string) ($booking['time_slot'] ?? '')) === 'morning'
                                        ? 'Morning'
                                        : ((((string) ($booking['time_slot'] ?? '')) === 'afternoon') ? 'Afternoon' : '-');
                                    echo htmlspecialchars($slotLabel, ENT_QUOTES, 'UTF-8');
                                    ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars((string) ($booking['start_time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string) ($booking['end_time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td><?php echo number_format((float) ($booking['total_price'] ?? 0), 2); ?></td>
                                <td>
                                    <span class="status-pill status-<?php echo htmlspecialchars($booking['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars(strtoupper($booking['status']), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($booking['admin_note'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php
                                        $currentBookingId = (int) ($booking['id'] ?? 0);
                                        $attachments = $bookingAttachmentsMap[$currentBookingId] ?? [];
                                    ?>
                                    <?php if (count($attachments) === 0): ?>
                                        -
                                    <?php else: ?>
                                        <div class="booking-attachment-links">
                                            <?php foreach ($attachments as $attachment): ?>
                                                <?php
                                                    $attachmentPath = (string) ($attachment['file_path'] ?? '');
                                                    $attachmentTitle = trim((string) ($attachment['title'] ?? ''));
                                                    $attachmentOriginalName = trim((string) ($attachment['original_name'] ?? ''));
                                                    $attachmentDisplayName = $attachmentTitle !== ''
                                                        ? $attachmentTitle
                                                        : ($attachmentOriginalName !== '' ? $attachmentOriginalName : 'Photo');
                                                ?>
                                                <?php if ($attachmentPath !== ''): ?>
                                                    <a
                                                        href="<?php echo htmlspecialchars($attachmentPath, ENT_QUOTES, 'UTF-8'); ?>"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        <?php echo htmlspecialchars($attachmentDisplayName, ENT_QUOTES, 'UTF-8'); ?>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-stack">
                                        <form method="POST" class="approval-form">
                                            <input type="hidden" name="action" value="update_booking_status">
                                            <input type="hidden" name="booking_id" value="<?php echo (int) $booking['id']; ?>">
                                            <select name="status" required>
                                                <option value="approved">Approve</option>
                                                <option value="rejected">Reject</option>
                                            </select>
                                            <input type="text" name="admin_note" placeholder="Note">
                                            <button type="submit" class="btn">Update</button>
                                        </form>
                                        <?php if (($booking['status'] ?? '') === 'approved'): ?>
                                            <form method="POST" class="approval-form">
                                                <input type="hidden" name="action" value="complete_booking">
                                                <input type="hidden" name="booking_id" value="<?php echo (int) $booking['id']; ?>">
                                                <button type="submit" class="btn">Complete & Archive</button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="export_bookings_pdf.php?booking_id=<?php echo (int) $booking['id']; ?>" class="btn btn-secondary">Export Receipt</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </div>
    </section>
</main>
<script>
    document.addEventListener("DOMContentLoaded", function(event) { 
        var scrollpos = sessionStorage.getItem('adminScrollPos');
        if (scrollpos) window.scrollTo(0, scrollpos);
    });

    window.addEventListener("beforeunload", function(e) {
        sessionStorage.setItem('adminScrollPos', window.scrollY);
    });
</script>
</body>
</html>

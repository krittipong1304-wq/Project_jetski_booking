<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "db.php";

function t(string $key, string $lang): string
{
    $text = [
        'en' => [
            'error' => 'Error',
            'invalid_name' => 'Invalid name.',
            'invalid_duration' => 'Invalid duration option.',
            'invalid_agency' => 'Invalid agency option.',
            'invalid_location' => 'Invalid location option.',
            'invalid_time_slot' => 'Invalid time slot option.',
            'invalid_photo_file' => 'Invalid photo file. Please upload JPG, PNG, WEBP, or GIF only.',
            'photo_upload_failed' => 'Photo upload failed. Please try again.',
            'overlap' => 'Selected time range is already booked.',
            'submitted_total' => 'Booking submitted. Waiting for admin approval. Total: %s',
            'boat_unavailable' => 'Selected boat is unavailable.',
            'booking_closed' => 'Booking system is currently closed by admin.',
            'title' => 'Booking Status',
            'back_home' => 'Back to Home'
        ],
        'th' => [
            'error' => 'เกิดข้อผิดพลาด',
            'invalid_name' => 'ชื่อไม่ถูกต้อง',
            'invalid_duration' => 'ตัวเลือกระยะเวลาไม่ถูกต้อง',
            'invalid_agency' => 'ตัวเลือก Agency ไม่ถูกต้อง',
            'invalid_location' => 'ตัวเลือกสถานที่ไม่ถูกต้อง',
            'invalid_time_slot' => 'ตัวเลือกช่วงเวลาไม่ถูกต้อง',
            'overlap' => 'ช่วงเวลานี้มีการจองแล้ว',
            'submitted_total' => 'ส่งคำขอจองเรียบร้อย รอแอดมินอนุมัติ ยอดรวม: %s',
            'boat_unavailable' => 'เรือที่เลือกไม่พร้อมใช้งาน',
            'title' => 'สถานะการจอง',
            'back_home' => 'กลับหน้าหลัก'
        ]
    ];

    return $text[$lang][$key] ?? $text['en'][$key] ?? $key;
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

    $activeBookingCount = 0;
    $activeStmt = $conn->prepare("SELECT COUNT(*) FROM bookings
        WHERE boat_id = ?
        AND is_archived = 0
        AND status IN ('pending', 'approved')");
    if ($activeStmt) {
        $activeStmt->bind_param("i", $boatId);
        $activeStmt->execute();
        $activeStmt->bind_result($activeBookingCount);
        $activeStmt->fetch();
        $activeStmt->close();
    }

    $isBooked = ($stock <= 0 || $activeBookingCount >= $stock) ? 1 : 0;
    $updateStmt = $conn->prepare("UPDATE boats SET is_booked = ? WHERE id = ?");
    if ($updateStmt) {
        $updateStmt->bind_param("ii", $isBooked, $boatId);
        $updateStmt->execute();
        $updateStmt->close();
    }
}

function cleanupSavedAttachmentFiles(array $relativePaths): void
{
    foreach ($relativePaths as $relativePath) {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '') {
            continue;
        }

        $fullPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}

function saveBookingAttachments(
    mysqli $conn,
    int $bookingId,
    array $titles,
    array $files,
    string &$errorMessageKey,
    array &$savedFilePaths
): bool {
    $errorMessageKey = '';
    $savedFilePaths = [];

    if ($bookingId <= 0) {
        return true;
    }

    $fileNames = $files['name'] ?? null;
    if (!is_array($fileNames)) {
        return true;
    }

    $fileErrors = $files['error'] ?? [];
    $fileTmpNames = $files['tmp_name'] ?? [];
    $fileSizes = $files['size'] ?? [];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $maxFileSize = 10 * 1024 * 1024;

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'booking_attachments';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        $errorMessageKey = 'photo_upload_failed';
        return false;
    }

    foreach ($fileNames as $index => $rawName) {
        $errorCode = (int) ($fileErrors[$index] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            $errorMessageKey = 'photo_upload_failed';
            return false;
        }

        $tmpPath = (string) ($fileTmpNames[$index] ?? '');
        $originalName = trim((string) $rawName);
        $fileSize = (int) ($fileSizes[$index] ?? 0);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (
            $tmpPath === '' ||
            $originalName === '' ||
            $fileSize <= 0 ||
            $fileSize > $maxFileSize ||
            !is_uploaded_file($tmpPath) ||
            !in_array($extension, $allowedExtensions, true)
        ) {
            $errorMessageKey = 'invalid_photo_file';
            return false;
        }

        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false) {
            $errorMessageKey = 'invalid_photo_file';
            return false;
        }

        $imageMimeType = strtolower((string) ($imageInfo['mime'] ?? ''));
        if ($imageMimeType !== '' && !in_array($imageMimeType, $allowedMimeTypes, true)) {
            $errorMessageKey = 'invalid_photo_file';
            return false;
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detectedMime = strtolower((string) finfo_file($finfo, $tmpPath));
                finfo_close($finfo);
                if ($detectedMime !== '' && !in_array($detectedMime, $allowedMimeTypes, true)) {
                    $errorMessageKey = 'invalid_photo_file';
                    return false;
                }
            }
        }

        $title = trim((string) ($titles[$index] ?? ''));
        if ($title === '') {
            $title = pathinfo($originalName, PATHINFO_FILENAME);
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($title, 'UTF-8') > 255) {
                $title = mb_substr($title, 0, 255, 'UTF-8');
            }
            if (mb_strlen($originalName, 'UTF-8') > 255) {
                $originalName = mb_substr($originalName, 0, 255, 'UTF-8');
            }
        } else {
            if (strlen($title) > 255) {
                $title = substr($title, 0, 255);
            }
            if (strlen($originalName) > 255) {
                $originalName = substr($originalName, 0, 255);
            }
        }

        $newFileName = uniqid('booking_attach_', true) . '.' . $extension;
        $destinationPath = $uploadDir . DIRECTORY_SEPARATOR . $newFileName;
        if (!move_uploaded_file($tmpPath, $destinationPath)) {
            $errorMessageKey = 'photo_upload_failed';
            return false;
        }

        $relativePath = 'uploads/booking_attachments/' . $newFileName;
        $savedFilePaths[] = $relativePath;

        $attachmentStmt = $conn->prepare("INSERT INTO booking_attachments (booking_id, title, file_path, original_name) VALUES (?, ?, ?, ?)");
        if (!$attachmentStmt) {
            $errorMessageKey = 'photo_upload_failed';
            return false;
        }

        $attachmentStmt->bind_param("isss", $bookingId, $title, $relativePath, $originalName);
        $inserted = $attachmentStmt->execute();
        $attachmentStmt->close();

        if (!$inserted) {
            $errorMessageKey = 'photo_upload_failed';
            return false;
        }
    }

    return true;
}

$lang = $_SESSION['lang'] ?? 'en';
if (!in_array($lang, ['th', 'en'], true)) {
    $lang = 'en';
}

$customerName = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$agencyOptionId = (int) ($_POST['agency_option_id'] ?? 0);
$locationOptionId = (int) ($_POST['location_option_id'] ?? 0);
$boatId = (int) ($_POST['boat_id'] ?? 0);
$bookingDate = $_POST['booking_date'] ?? '';
$timeSlot = $_POST['time_slot'] ?? '';

$isSuccess = false;
$message = t('error', $lang);
$statusClass = "status-error";
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

if ($isBookingEnabled !== 1) {
    $message = t('booking_closed', $lang);
} elseif ($boatId > 0 && $bookingDate !== '') {
    if ($customerName === '') {
        $message = t('invalid_name', $lang);
    } else {
        $hasAgencyOptions = false;
        if ($isAgencyDropdownEnabled === 1) {
            $agencyCountResult = $conn->query("SELECT COUNT(*) AS total FROM booking_agency_options WHERE is_active = 1");
            if ($agencyCountResult && $agencyCountRow = $agencyCountResult->fetch_assoc()) {
                $hasAgencyOptions = ((int) $agencyCountRow['total']) > 0;
            }
        }

        $hasLocationOptions = false;
        $locationCountResult = $conn->query("SELECT COUNT(*) AS total FROM booking_location_options WHERE is_active = 1");
        if ($locationCountResult && $locationCountRow = $locationCountResult->fetch_assoc()) {
            $hasLocationOptions = ((int) $locationCountRow['total']) > 0;
        }

        $agencyName = '';
        if ($hasAgencyOptions) {
            $agencyStmt = $conn->prepare("SELECT option_name FROM booking_agency_options WHERE id = ? AND is_active = 1");
            if ($agencyStmt) {
                $agencyStmt->bind_param("i", $agencyOptionId);
                $agencyStmt->execute();
                $agencyStmt->bind_result($agencyFromDb);
                if ($agencyStmt->fetch()) {
                    $agencyName = (string) $agencyFromDb;
                }
                $agencyStmt->close();
            }
        }

        if ($hasAgencyOptions && $agencyName === '') {
            $message = t('invalid_agency', $lang);
        } else {
            $locationName = '';
            $locationPrice = 0.00;
            if ($hasLocationOptions) {
                $locationStmt = $conn->prepare("SELECT option_name, price, price_usd FROM booking_location_options WHERE id = ? AND is_active = 1");
                if ($locationStmt) {
                    $locationStmt->bind_param("i", $locationOptionId);
                    $locationStmt->execute();
                    $locationStmt->bind_result($locationFromDb, $locationPriceFromDb, $locationPriceUsdFromDb);
                    if ($locationStmt->fetch()) {
                        $locationName = (string) $locationFromDb;
                        $locationPrice = $isUsdCurrency ? (float) $locationPriceUsdFromDb : (float) $locationPriceFromDb;
                    }
                    $locationStmt->close();
                }
            }

            if ($hasLocationOptions && $locationName === '') {
                $message = t('invalid_location', $lang);
            } else {
                if (!in_array($timeSlot, ['morning', 'afternoon'], true)) {
                    $message = t('invalid_time_slot', $lang);
                } else {
                    $startTime = $timeSlot === 'morning' ? '08:00:00' : '13:00:00';
                    $startDateTime = strtotime($bookingDate . ' ' . $startTime);
                    $endDateTime = $startDateTime + 3600;
                    $endTime = date('H:i:s', $endDateTime);

                    $boatName = '';
                    $boatStock = 0;
                    $boatBasePrice = 0.00;
                    $activeBookingCount = 0;
                    $overlapCount = 0;

                    $conn->begin_transaction();

                    $boatStmt = $conn->prepare("SELECT boat_name, stock, hourly_rate, hourly_rate_usd FROM boats WHERE id = ? AND is_active = 1 FOR UPDATE");
                    if ($boatStmt) {
                        $boatStmt->bind_param("i", $boatId);
                        $boatStmt->execute();
                        $boatStmt->bind_result($boatNameFromDb, $boatStockFromDb, $boatRateFromDb, $boatRateUsdFromDb);
                        if ($boatStmt->fetch()) {
                            $boatName = (string) $boatNameFromDb;
                            $boatStock = (int) $boatStockFromDb;
                            $boatBasePrice = $isUsdCurrency ? (float) $boatRateUsdFromDb : (float) $boatRateFromDb;
                        }
                        $boatStmt->close();
                    }

                    if ($boatName === '' || $boatStock <= 0) {
                        $conn->rollback();
                        $message = t('boat_unavailable', $lang);
                    } else {
                        $activeStmt = $conn->prepare("SELECT COUNT(*) FROM bookings
                            WHERE boat_id = ?
                            AND is_archived = 0
                            AND status IN ('pending', 'approved')");

                        if ($activeStmt) {
                            $activeStmt->bind_param("i", $boatId);
                            $activeStmt->execute();
                            $activeStmt->bind_result($activeBookingCount);
                            $activeStmt->fetch();
                            $activeStmt->close();
                        }

                        if ($activeBookingCount >= $boatStock) {
                            $conn->rollback();
                            $message = t('boat_unavailable', $lang);
                        } else {
                            $overlapStmt = $conn->prepare("SELECT COUNT(*) FROM bookings
                                WHERE boat_id = ?
                                AND booking_date = ?
                                AND status = 'approved'
                                AND start_time < ?
                                AND end_time > ?");

                            if ($overlapStmt) {
                                $overlapStmt->bind_param("isss", $boatId, $bookingDate, $endTime, $startTime);
                                $overlapStmt->execute();
                                $overlapStmt->bind_result($overlapCount);
                                $overlapStmt->fetch();
                                $overlapStmt->close();
                            }

                            if ($overlapCount >= $boatStock) {
                                $conn->rollback();
                                $message = t('overlap', $lang);
                            } else {
                                $totalPrice = $boatBasePrice + $locationPrice;
                                $bookingId = 0;

                                $stmt = $conn->prepare("INSERT INTO bookings(name, email, phone, agency, location, boat_id, boat, booking_date, time_slot, start_time, end_time, total_price, date, time, status)
                                    VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                                if ($stmt) {
                                    $legacyDate = $bookingDate;
                                    $legacyTime = $startTime;
                                    $stmt->bind_param(
                                        "sssssisssssdss",
                                        $customerName,
                                        $email,
                                        $phone,
                                        $agencyName,
                                        $locationName,
                                        $boatId,
                                        $boatName,
                                        $bookingDate,
                                        $timeSlot,
                                        $startTime,
                                        $endTime,
                                        $totalPrice,
                                        $legacyDate,
                                        $legacyTime
                                    );
                                    $isSuccess = $stmt->execute();
                                    if ($isSuccess) {
                                        $bookingId = (int) $stmt->insert_id;
                                    }
                                    $stmt->close();
                                }

                                if ($isSuccess) {
                                    $attachmentTitles = $_POST['attachment_title'] ?? [];
                                    if (!is_array($attachmentTitles)) {
                                        $attachmentTitles = [];
                                    }
                                    $attachmentFiles = $_FILES['attachment_file'] ?? [];
                                    $attachmentErrorKey = '';
                                    $savedAttachmentPaths = [];

                                    $attachmentsSaved = saveBookingAttachments(
                                        $conn,
                                        $bookingId,
                                        $attachmentTitles,
                                        is_array($attachmentFiles) ? $attachmentFiles : [],
                                        $attachmentErrorKey,
                                        $savedAttachmentPaths
                                    );

                                    if (!$attachmentsSaved) {
                                        $conn->rollback();
                                        cleanupSavedAttachmentFiles($savedAttachmentPaths);
                                        $message = t($attachmentErrorKey !== '' ? $attachmentErrorKey : 'photo_upload_failed', $lang);
                                    } else {
                                        syncBoatBookedStatus($conn, $boatId);
                                        $conn->commit();

                                        $displayTotalPrice = $totalPrice;
                                        $currencyCode = $isUsdCurrency ? 'USD' : 'THB';
                                        $message = sprintf(t('submitted_total', $lang), number_format($displayTotalPrice, 2) . ' ' . $currencyCode);
                                        $statusClass = "status-success";

                                        unset($_SESSION['lang']);
                                    }
                                } else {
                                    $conn->rollback();
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang === 'th' ? 'th' : 'en'; ?>">
<head>
<title><?php echo htmlspecialchars(t('title', $lang), ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="stylesheet" href="css/style.css?v=20260305">
</head>
<body class="user-theme">
<main class="page page-status">
    <section class="card">
        <h2><?php echo htmlspecialchars(t('title', $lang), ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="status-message <?php echo $statusClass; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="dashboard.php" class="btn"><?php echo htmlspecialchars(t('back_home', $lang), ENT_QUOTES, 'UTF-8'); ?></a>
    </section>
</main>
</body>
</html>

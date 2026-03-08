<?php
include "admin_auth.php";
include "db.php";

function findFpdfPath(): string
{
    $candidates = [
        __DIR__ . DIRECTORY_SEPARATOR . 'fpdf.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'fpdf186' . DIRECTORY_SEPARATOR . 'fpdf.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'fpdf' . DIRECTORY_SEPARATOR . 'fpdf.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'setasign' . DIRECTORY_SEPARATOR . 'fpdf' . DIRECTORY_SEPARATOR . 'fpdf.php'
    ];

    foreach ($candidates as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }

    return '';
}

function pdfText(string $text): string
{
    $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
    if ($converted === false) {
        $converted = preg_replace('/[^\x20-\x7E]/', '?', $text);
    }

    return $converted;
}

function renderHtmlMessage(string $title, string $message): void
{
    ?>
    <!DOCTYPE html>
    <html>
    <head>
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="css/style.css?v=20260305">
    </head>
    <body>
    <main class="page">
        <section class="card">
            <h2><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="subtitle"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <a href="admin.php" class="btn">Back to Admin</a>
        </section>
    </main>
    </body>
    </html>
    <?php
    exit;
}

$bookingId = isset($_GET['booking_id']) ? (int) $_GET['booking_id'] : 0;
if ($bookingId <= 0) {
    renderHtmlMessage('Invalid Booking', 'Please choose a booking record to export.');
}

$fpdfPath = findFpdfPath();
if ($fpdfPath === '') {
    renderHtmlMessage(
        'FPDF Library Not Found',
        'Please place fpdf.php in boat/fpdf.php, boat/fpdf186/fpdf.php, boat/fpdf/fpdf.php, or boat/vendor/setasign/fpdf/fpdf.php'
    );
}

require_once $fpdfPath;

$stmt = $conn->prepare("SELECT id, name, email, phone, agency, location, boat, booking_date, time_slot, start_time, end_time, total_price, status, admin_note, approved_at FROM bookings WHERE id = ? LIMIT 1");
if (!$stmt) {
    renderHtmlMessage('Database Error', 'Unable to prepare booking query.');
}

$stmt->bind_param("i", $bookingId);
$stmt->execute();
$stmt->bind_result(
    $id,
    $name,
    $email,
    $phone,
    $agency,
    $location,
    $boat,
    $bookingDate,
    $timeSlot,
    $startTime,
    $endTime,
    $totalPrice,
    $status,
    $adminNote,
    $approvedAt
);
$booking = null;
if ($stmt->fetch()) {
    $booking = [
        'id' => $id,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'agency' => $agency,
        'location' => $location,
        'boat' => $boat,
        'booking_date' => $bookingDate,
        'time_slot' => $timeSlot,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'total_price' => $totalPrice,
        'status' => $status,
        'admin_note' => $adminNote,
        'approved_at' => $approvedAt
    ];
}
$stmt->close();

if (!$booking) {
    renderHtmlMessage('Booking Not Found', 'The selected booking record does not exist.');
}

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);

$pdf->SetDrawColor(55, 73, 93);
$pdf->SetLineWidth(0.6);
$pdf->Rect(10, 10, 190, 277);

$pdf->SetFont('Arial', 'B', 20);
$pdf->SetTextColor(24, 50, 86);
$pdf->Cell(0, 12, pdfText('BOAT BOOKING RECEIPT'), 0, 1, 'C');

$pdf->SetTextColor(40, 40, 40);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, pdfText('Boat Booking System'), 0, 1, 'C');
$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(35, 7, 'Receipt No:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(60, 7, pdfText('REC-' . str_pad((string) $booking['id'], 6, '0', STR_PAD_LEFT)), 0, 0);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(32, 7, 'Issue Date:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 7, pdfText(date('Y-m-d H:i:s')), 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(35, 7, 'Booking ID:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(60, 7, pdfText((string) $booking['id']), 0, 0);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(32, 7, 'Status:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 7, pdfText(strtoupper((string) $booking['status'])), 0, 1);

$pdf->Ln(3);

$pdf->SetFillColor(230, 238, 250);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(190, 8, 'Customer Information', 1, 1, 'L', true);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(40, 8, 'Customer Name', 1, 0);
$pdf->Cell(150, 8, pdfText((string) $booking['name']), 1, 1);
$pdf->Cell(40, 8, 'Email', 1, 0);
$pdf->Cell(150, 8, pdfText((string) $booking['email']), 1, 1);
$pdf->Cell(40, 8, 'Phone', 1, 0);
$pdf->Cell(150, 8, pdfText((string) $booking['phone']), 1, 1);
$pdf->Cell(40, 8, 'Agency', 1, 0);
$pdf->Cell(150, 8, pdfText((string) ($booking['agency'] ?? '')), 1, 1);
$pdf->Cell(40, 8, 'Location', 1, 0);
$pdf->Cell(150, 8, pdfText((string) ($booking['location'] ?? '')), 1, 1);

$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(190, 8, 'Booking Details', 1, 1, 'L', true);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(40, 8, 'Boat Name', 1, 0);
$pdf->Cell(150, 8, pdfText((string) $booking['boat']), 1, 1);
$pdf->Cell(40, 8, 'Booking Date', 1, 0);
$pdf->Cell(150, 8, pdfText((string) $booking['booking_date']), 1, 1);
$slotLabel = ((string) ($booking['time_slot'] ?? '')) === 'morning' ? 'Morning' : ((((string) ($booking['time_slot'] ?? '')) === 'afternoon') ? 'Afternoon' : '-');
$pdf->Cell(40, 8, 'Time Slot', 1, 0);
$pdf->Cell(150, 8, pdfText($slotLabel), 1, 1);
$pdf->Cell(40, 8, 'Time Range', 1, 0);
$pdf->Cell(150, 8, pdfText((string) $booking['start_time'] . ' - ' . (string) $booking['end_time']), 1, 1);
$pdf->Cell(40, 8, 'Total Price', 1, 0);
$pdf->Cell(150, 8, pdfText(number_format((float) $booking['total_price'], 2)), 1, 1);
$pdf->Cell(40, 8, 'Approved At', 1, 0);
$pdf->Cell(150, 8, pdfText((string) ($booking['approved_at'] ?? '')), 1, 1);

$pdf->Cell(40, 10, 'Admin Note', 1, 0);
$x = $pdf->GetX();
$y = $pdf->GetY();
$pdf->MultiCell(150, 5, pdfText((string) ($booking['admin_note'] ?? '')), 1);
if ($pdf->GetY() < $y + 10) {
    $pdf->SetXY($x, $y);
    $pdf->Cell(150, 10, '', 1, 1);
}

$pdf->Ln(8);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(95, 6, pdfText('Authorized Signature: _____________________'), 0, 0, 'L');
$pdf->Cell(95, 6, pdfText('Customer Signature: _____________________'), 0, 1, 'R');

$pdf->Ln(5);
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 5, pdfText('This receipt is system-generated for booking reference.'), 0, 1, 'C');

$filename = 'receipt_booking_' . (int) $booking['id'] . '.pdf';
if (ob_get_length()) {
    ob_clean();
}
$pdf->Output('D', $filename);
exit;
?>

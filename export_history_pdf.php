<?php
include "admin_auth.php";
include "db.php";

function findFpdfPathForHistory(): string
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

function historyPdfText(string $text): string
{
    $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
    if ($converted === false) {
        $converted = preg_replace('/[^\x20-\x7E]/', '?', $text);
    }

    return $converted;
}

function renderHistoryError(string $title, string $message): void
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
            <a href="reports.php" class="btn">Back to Reports</a>
        </section>
    </main>
    </body>
    </html>
    <?php
    exit;
}

function drawHeader(FPDF $pdf): void
{
    $pdf->SetFillColor(237, 244, 255);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(20, 9, 'Booking ID', 1, 0, 'C', true);
    $pdf->Cell(35, 9, 'Boat', 1, 0, 'C', true);
    $pdf->Cell(30, 9, 'Customer', 1, 0, 'C', true);
    $pdf->Cell(30, 9, 'Agency', 1, 0, 'C', true);
    $pdf->Cell(30, 9, 'Location', 1, 0, 'C', true);
    $pdf->Cell(52, 9, 'Date/Slot', 1, 0, 'C', true);
    $pdf->Cell(20, 9, 'Price', 1, 0, 'C', true);
    $pdf->Cell(50, 9, 'Completed At', 1, 1, 'C', true);
}

$selectedIdsRaw = $_POST['selected_ids'] ?? [];
if (!is_array($selectedIdsRaw) || count($selectedIdsRaw) === 0) {
    renderHistoryError('No Selection', 'Please select one or more rows from Reports before exporting.');
}

$selectedIds = [];
foreach ($selectedIdsRaw as $rawId) {
    $id = (int) $rawId;
    if ($id > 0) {
        $selectedIds[$id] = $id;
    }
}

if (count($selectedIds) === 0) {
    renderHistoryError('Invalid Selection', 'Selected rows are invalid.');
}

$fpdfPath = findFpdfPathForHistory();
if ($fpdfPath === '') {
    renderHistoryError(
        'FPDF Library Not Found',
        'Please place fpdf.php in boat/fpdf.php, boat/fpdf186/fpdf.php, boat/fpdf/fpdf.php, or boat/vendor/setasign/fpdf/fpdf.php'
    );
}

require_once $fpdfPath;

$idsSql = implode(',', $selectedIds);
$rows = [];
$result = $conn->query("SELECT id, source_booking_id, boat, name, agency, location, booking_date, time_slot, start_time, end_time, total_price, completed_at
    FROM booking_history
    WHERE id IN ($idsSql)
    ORDER BY id DESC");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

if (count($rows) === 0) {
    renderHistoryError('No Data', 'No matching history records found.');
}

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 12);

$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(24, 50, 86);
$pdf->Cell(0, 10, historyPdfText('Archived Booking Report'), 0, 1, 'L');

$pdf->SetTextColor(40, 40, 40);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 7, historyPdfText('Generated at: ' . date('Y-m-d H:i:s')), 0, 1, 'L');
$pdf->Ln(2);

drawHeader($pdf);

$totalRevenue = 0.0;
$pdf->SetFont('Arial', '', 9);
foreach ($rows as $row) {
    if ($pdf->GetY() > 188) {
        $pdf->AddPage();
        drawHeader($pdf);
        $pdf->SetFont('Arial', '', 9);
    }

    $slotLabel = ((string) ($row['time_slot'] ?? '')) === 'morning' ? 'Morning' : ((((string) ($row['time_slot'] ?? '')) === 'afternoon') ? 'Afternoon' : '-');
    $dateTimeText = (string) $row['booking_date'] . ' ' . $slotLabel . ' ' . (string) $row['start_time'] . ' - ' . (string) $row['end_time'];
    $price = (float) ($row['total_price'] ?? 0);
    $totalRevenue += $price;

    $pdf->Cell(20, 8, historyPdfText((string) $row['source_booking_id']), 1, 0, 'C');
    $pdf->Cell(35, 8, historyPdfText((string) $row['boat']), 1, 0, 'L');
    $pdf->Cell(30, 8, historyPdfText((string) $row['name']), 1, 0, 'L');
    $pdf->Cell(30, 8, historyPdfText((string) ($row['agency'] ?? '')), 1, 0, 'L');
    $pdf->Cell(30, 8, historyPdfText((string) ($row['location'] ?? '')), 1, 0, 'L');
    $pdf->Cell(52, 8, historyPdfText($dateTimeText), 1, 0, 'L');
    $pdf->Cell(20, 8, historyPdfText(number_format($price, 2)), 1, 0, 'R');
    $pdf->Cell(50, 8, historyPdfText((string) $row['completed_at']), 1, 1, 'L');
}

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(197, 9, 'Total Revenue', 1, 0, 'R', true);
$pdf->Cell(20, 9, historyPdfText(number_format($totalRevenue, 2)), 1, 0, 'R', true);
$pdf->Cell(50, 9, historyPdfText('Records: ' . (string) count($rows)), 1, 1, 'C', true);

$filename = 'archived_bookings_selected_' . date('Ymd_His') . '.pdf';
if (ob_get_length()) {
    ob_clean();
}
$pdf->Output('D', $filename);
exit;
?>

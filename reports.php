<?php
include "admin_auth.php";
include "db.php";

$flashType = '';
$flashMessage = '';

if (isset($_GET['msg'])) {
    $flashMessage = trim((string) $_GET['msg']);
    $flashType = (string) ($_GET['type'] ?? 'success');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete_selected') {
        $selectedIdsRaw = $_POST['selected_ids'] ?? [];
        $selectedIds = [];

        if (is_array($selectedIdsRaw)) {
            foreach ($selectedIdsRaw as $rawId) {
                $id = (int) $rawId;
                if ($id > 0) {
                    $selectedIds[$id] = $id;
                }
            }
        }

        if (count($selectedIds) === 0) {
            header("Location: reports.php?type=error&msg=" . urlencode('Please select at least one record to delete.'));
            exit;
        }

        $idsSql = implode(',', $selectedIds);
        $deleteSql = "DELETE FROM booking_history WHERE id IN ($idsSql)";
        $deletedCount = 0;
        if ($conn->query($deleteSql) === true) {
            $deletedCount = (int) $conn->affected_rows;
        }

        header("Location: reports.php?type=success&msg=" . urlencode("Deleted {$deletedCount} record(s)."));
        exit;
    }

    if ($action === 'delete_one') {
        $historyId = (int) ($_POST['history_id'] ?? 0);

        if ($historyId <= 0) {
            header("Location: reports.php?type=error&msg=" . urlencode('Invalid history record.'));
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM booking_history WHERE id = ? LIMIT 1");
        $deletedCount = 0;
        if ($stmt) {
            $stmt->bind_param("i", $historyId);
            $stmt->execute();
            $deletedCount = (int) $stmt->affected_rows;
            $stmt->close();
        }

        header("Location: reports.php?type=success&msg=" . urlencode("Deleted {$deletedCount} record(s)."));
        exit;
    }
}

$totalRevenue = 0.0;
$totalBookings = 0;
$totalsResult = $conn->query("SELECT
    COUNT(*) AS total_bookings,
    COALESCE(SUM(total_price), 0) AS total_revenue
FROM booking_history");
if ($totalsResult && $totalsRow = $totalsResult->fetch_assoc()) {
    $totalBookings = (int) ($totalsRow['total_bookings'] ?? 0);
    $totalRevenue = (float) ($totalsRow['total_revenue'] ?? 0);
}

$monthlySummary = [];
$monthlyResult = $conn->query("SELECT
    DATE_FORMAT(completed_at, '%Y-%m') AS month_key,
    DATE_FORMAT(completed_at, '%M %Y') AS month_label,
    COUNT(*) AS total_bookings,
    COALESCE(SUM(total_price), 0) AS total_revenue
FROM booking_history
GROUP BY month_key, month_label
ORDER BY month_key DESC");
if ($monthlyResult) {
    while ($row = $monthlyResult->fetch_assoc()) {
        $row['total_bookings'] = (int) $row['total_bookings'];
        $row['total_revenue'] = (float) $row['total_revenue'];
        $monthlySummary[] = $row;
    }
}

$summary = [];
$summaryResult = $conn->query("SELECT
    boat,
    COUNT(*) AS total_bookings,
    COALESCE(SUM(total_price), 0) AS total_revenue
FROM booking_history
GROUP BY boat
ORDER BY total_revenue DESC");

$maxRevenue = 0.0;
if ($summaryResult) {
    while ($row = $summaryResult->fetch_assoc()) {
        $row['total_revenue'] = (float) $row['total_revenue'];
        $row['total_bookings'] = (int) $row['total_bookings'];
        if ($row['total_revenue'] > $maxRevenue) {
            $maxRevenue = $row['total_revenue'];
        }
        $summary[] = $row;
    }
}

$historyRows = [];
$historyResult = $conn->query("SELECT id, source_booking_id, boat, name, agency, location, booking_date, time_slot, start_time, end_time, total_price, completed_at
    FROM booking_history
    ORDER BY id DESC
    LIMIT 50");
if ($historyResult) {
    while ($row = $historyResult->fetch_assoc()) {
        $historyRows[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Booking Reports</title>
<link rel="stylesheet" href="css/style.css?v=20260305">
</head>
<body>
<main class="page admin-page">
    <section class="card card-admin">
        <div class="admin-header">
            <h2>Booking Reports</h2>
            <a href="admin.php" class="btn btn-secondary">Back to Admin</a>
        </div>

        <?php if ($flashMessage !== ''): ?>
            <p class="status-message <?php echo $flashType === 'error' ? 'status-error' : 'status-success'; ?>">
                <?php echo htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8'); ?>
            </p>
        <?php endif; ?>

        <h3>Total Revenue Summary</h3>
        <div class="report-summary-grid">
            <div class="report-summary-card">
                <p class="report-summary-label">All Archived Bookings</p>
                <p class="report-summary-value"><?php echo number_format($totalBookings); ?></p>
            </div>
            <div class="report-summary-card">
                <p class="report-summary-label">All-Time Revenue</p>
                <p class="report-summary-value"><?php echo number_format($totalRevenue, 2); ?></p>
            </div>
        </div>

        <h3>Monthly Revenue</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Total Bookings</th>
                        <th>Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($monthlySummary) === 0): ?>
                        <tr>
                            <td colspan="3">No monthly data found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($monthlySummary as $month): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) $month['month_label'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo (int) $month['total_bookings']; ?></td>
                                <td><?php echo number_format((float) $month['total_revenue'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h3>Revenue by Boat</h3>
        <?php if (count($summary) === 0): ?>
            <p class="empty-text">No archived booking data yet.</p>
        <?php else: ?>
            <div class="report-chart">
                <?php foreach ($summary as $item): ?>
                    <?php $percent = $maxRevenue > 0 ? ($item['total_revenue'] / $maxRevenue) * 100 : 0; ?>
                    <div class="report-row">
                        <div class="report-label"><?php echo htmlspecialchars((string) $item['boat'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="report-bar-wrap">
                            <div class="report-bar" style="width: <?php echo number_format($percent, 2, '.', ''); ?>%;"></div>
                        </div>
                        <div class="report-value">
                            <?php echo number_format((float) $item['total_revenue'], 2); ?>
                            (<?php echo (int) $item['total_bookings']; ?>)
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h3>Recent Archived Bookings</h3>
        <div class="section-head">
            <div class="report-actions">
                <button type="submit" name="action" value="delete_selected" form="historyBulkForm" class="btn btn-danger" onclick="return confirm('Delete selected records?')">Delete Selected</button>
                <button type="submit" form="historyBulkForm" formaction="export_history_pdf.php" formmethod="POST" formtarget="_blank" class="btn">Export Selected PDF</button>
            </div>
        </div>
        <div class="table-wrap">
            <form method="POST" id="historyBulkForm">
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAllRows"></th>
                        <th>Booking ID</th>
                        <th>Boat</th>
                        <th>Customer</th>
                        <th>Agency</th>
                        <th>Location</th>
                        <th>Date/Slot</th>
                        <th>Time</th>
                        <th>Price</th>
                        <th>Completed At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($historyRows) === 0): ?>
                        <tr>
                            <td colspan="11">No archived bookings found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($historyRows as $row): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_ids[]" class="history-row-checkbox" value="<?php echo (int) $row['id']; ?>">
                                </td>
                                <td><?php echo (int) $row['source_booking_id']; ?></td>
                                <td><?php echo htmlspecialchars((string) $row['boat'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($row['agency'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php echo htmlspecialchars((string) ($row['location'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars((string) $row['booking_date'], ENT_QUOTES, 'UTF-8'); ?><br>
                                    <?php
                                    $slotLabel = ((string) ($row['time_slot'] ?? '')) === 'morning'
                                        ? 'Morning'
                                        : ((((string) ($row['time_slot'] ?? '')) === 'afternoon') ? 'Afternoon' : '-');
                                    echo htmlspecialchars($slotLabel, ENT_QUOTES, 'UTF-8');
                                    ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars((string) $row['start_time'], ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string) $row['end_time'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td><?php echo number_format((float) $row['total_price'], 2); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['completed_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <button
                                        type="submit"
                                        name="action"
                                        value="delete_one"
                                        form="historyBulkForm"
                                        class="btn btn-danger"
                                        onclick="document.getElementById('deleteHistoryId').value='<?php echo (int) $row['id']; ?>'; return confirm('Delete this record?');"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <input type="hidden" name="history_id" id="deleteHistoryId" value="0">
            </form>
        </div>
    </section>
</main>
<script>
(function () {
    var selectAll = document.getElementById('selectAllRows');
    if (!selectAll) {
        return;
    }

    var rowCheckboxes = document.querySelectorAll('.history-row-checkbox');
    selectAll.addEventListener('change', function () {
        rowCheckboxes.forEach(function (checkbox) {
            checkbox.checked = selectAll.checked;
        });
    });
})();
</script>
</body>
</html>

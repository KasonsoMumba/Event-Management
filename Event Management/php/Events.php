<?php
// Events.php - Admin Events Monitoring page with essential functionality
session_start();
include 'db_connect.php';

// Ensure Admin
if (!isset($_SESSION['UserID']) || ($_SESSION['Role'] ?? '') !== 'Admin') {
    header("Location: ../HTML/Login.html");
    exit();
}

// If the session doesn't contain names, try to fetch from DB and populate
if (empty($_SESSION['FirstName']) || empty($_SESSION['LastName'])) {
    $stmt = $conn->prepare("SELECT * FROM Users WHERE UserID = ?");
    $stmt->bind_param("i", $_SESSION['UserID']);
    $stmt->execute();
    $userRow = $stmt->get_result()->fetch_assoc() ?: [];
    // Try a few common column name variations
    $first = $userRow['Firstname'] ?? $userRow['FirstName'] ?? $userRow['firstname'] ?? $userRow['first_name'] ?? '';
    $last  = $userRow['Lastname']  ?? $userRow['LastName']  ?? $userRow['lastname']  ?? $userRow['last_name']  ?? '';
    if ($first) $_SESSION['FirstName'] = $first;
    if ($last)  $_SESSION['LastName']  = $last;
    $stmt->close();
}

// Handle internal AJAX endpoint for registrations (returns JSON)
if (isset($_GET['action']) && $_GET['action'] === 'registrations' && isset($_GET['event_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $eventId = intval($_GET['event_id']);
    // Fetch registration rows (adapt to your Registrations columns)
    $stmt = $conn->prepare("
        SELECT RegistrationID, First_Name, Last_Name, Email, Phone, Status, Payment_Status, Registration_Date
        FROM Registrations
        WHERE EventID = ?
        ORDER BY Registration_Date DESC
        LIMIT 500
    ");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'rows' => $rows]);
    $stmt->close();
    exit();
}

/* ---------------------
   Detect Events table columns (safe queries)
   --------------------- */
$eventsColsRes = $conn->query("
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Events'
");
$existingCols = [];
if ($eventsColsRes) {
    while ($r = $eventsColsRes->fetch_assoc()) {
        $existingCols[] = $r['COLUMN_NAME'];
    }
}

// helper to pick first available column name
function pickCol(array $candidates, array $existing) {
    foreach ($candidates as $c) {
        if (in_array($c, $existing, true)) return $c;
    }
    return null;
}

// preferred names for fields (adjust as necessary)
$colEventID = pickCol(['EventID', 'event_id', 'id', 'ID'], $existingCols);
$colTitle   = pickCol(['Title','EventName','Name','title','name'], $existingCols);
$colStatus  = pickCol(['Status','status','event_status'], $existingCols);
$colStart   = pickCol(['Start_Date','StartDate','start_date','startdate','Date','EventDate'], $existingCols);
$colEnd     = pickCol(['End_Date','EndDate','end_date','enddate'], $existingCols);
$colVenue   = pickCol(['Venue_Name','Venue','Location','VenueName','location','venue_name'], $existingCols);
$colOrganizer = pickCol(['OrganizerID','OrganizerId','organizer_id','organizerid'], $existingCols);
$colCreated = pickCol(['Created_at','CreatedAt','created_at','createdat','created'], $existingCols);

// Build SELECT list (use NULL AS alias when column missing)
$selectParts = [];
$selectParts[] = $colEventID ? "e.`{$colEventID}` AS EventID" : "NULL AS EventID";
$selectParts[] = $colTitle   ? "e.`{$colTitle}` AS Title" : "NULL AS Title";
$selectParts[] = $colStatus  ? "e.`{$colStatus}` AS Status" : "NULL AS Status";
$selectParts[] = $colStart   ? "e.`{$colStart}` AS Start_Date" : "NULL AS Start_Date";
$selectParts[] = $colEnd     ? "e.`{$colEnd}` AS End_Date" : "NULL AS End_Date";
$selectParts[] = $colVenue   ? "e.`{$colVenue}` AS Venue_Name" : "NULL AS Venue_Name";
$selectParts[] = $colCreated ? "e.`{$colCreated}` AS Created_at" : "NULL AS Created_at";

// subqueries for registration counts (safe because Registrations table exists)
$selectParts[] = "(SELECT COUNT(*) FROM Registrations r WHERE r.EventID = e." . ($colEventID ?? 'EventID') . ") AS TotalRegistrations";
$selectParts[] = "(SELECT COUNT(*) FROM Registrations r WHERE r.EventID = e." . ($colEventID ?? 'EventID') . " AND r.Status='Pending') AS PendingRegistrations";

// If organizer column exists we will join users to get name
$joinOrganizer = "";
if ($colOrganizer) {
    $joinOrganizer = "LEFT JOIN Users u ON e.`{$colOrganizer}` = u.UserID";
    $selectParts[] = "CONCAT(u.Firstname, ' ', u.Lastname) AS OrganizerName";
} else {
    $selectParts[] = "NULL AS OrganizerName";
}

// Build base query
$sqlBase = "SELECT " . implode(", ", $selectParts) . " FROM Events e {$joinOrganizer} WHERE 1=1 ";

// Filters from GET
$status_filter = $_GET['status_filter'] ?? 'all';
$organizer_filter = $_GET['organizer_filter'] ?? 'all';
$search_term = trim($_GET['search'] ?? '');

// Add filtering only if relevant columns exist
$whereParts = [];
$params = [];
$types = "";

// status filter
if ($status_filter !== 'all' && $colStatus) {
    $whereParts[] = "e.`{$colStatus}` = ?";
    $types .= "s";
    $params[] = $status_filter;
}

// organizer filter
if ($organizer_filter !== 'all' && $colOrganizer) {
    $whereParts[] = "e.`{$colOrganizer}` = ?";
    $types .= "i";
    $params[] = intval($organizer_filter);
}

// search filter - search title and venue if they exist
if ($search_term !== '') {
    $searchConds = [];
    if ($colTitle) {
        $searchConds[] = "e.`{$colTitle}` LIKE ?";
        $types .= "s";
        $params[] = "%{$search_term}%";
    }
    if ($colVenue) {
        $searchConds[] = "e.`{$colVenue}` LIKE ?";
        $types .= "s";
        $params[] = "%{$search_term}%";
    }
    if (!empty($searchConds)) {
        $whereParts[] = "(" . implode(" OR ", $searchConds) . ")";
    }
}

// combine
if (!empty($whereParts)) {
    $sqlBase .= " AND " . implode(" AND ", $whereParts);
}
$sqlBase .= " ORDER BY " . ($colStart ? "e.`{$colStart}` DESC" : ($colEventID ? "e.`{$colEventID}` DESC" : "e.EventID DESC"));

// Prepare + execute safely
$events_result = false;
if ($stmt = $conn->prepare($sqlBase)) {
    if (!empty($params)) {
        // bind params - use references for bind_param in older PHP
        $bindNames = [];
        $bindNames[] = $types;
        for ($i = 0; $i < count($params); $i++) {
            $bindNames[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindNames);
    }
    $stmt->execute();
    $events_result = $stmt->get_result();
    $stmt->close();
} else {
    // fallback: run a simple query without filters
    $events_result = $conn->query("SELECT EventID, Title, Status FROM Events ORDER BY EventID DESC");
}

// Organizers list (for filter) - safe and simple
$organizers_result = $conn->query("SELECT UserID, Firstname, Lastname FROM Users WHERE Role='Organizer' ORDER BY Firstname, Lastname");

// Overall stats (count queries)
$events_total = $conn->query("SELECT COUNT(*) AS total FROM Events")->fetch_assoc()['total'] ?? 0;
$registrations_total = $conn->query("SELECT COUNT(*) AS total FROM Registrations")->fetch_assoc()['total'] ?? 0;
$registrations_pending = $conn->query("SELECT COUNT(*) AS total FROM Registrations WHERE Status='Pending'")->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Events Monitoring</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ---------- Full Page Styling (clean, responsive) ---------- */
:root{--primary:#4361ee;--secondary:#3f37c9;--success:#4cc9f0;--info:#4895ef;--danger:#e63946;--light:#f8f9fa;--dark:#212529;--gray:#6c757d;--light-gray:#e9ecef;}
*{box-sizing:border-box;margin:0;padding:0;font-family:Segoe UI, Tahoma, Geneva, Verdana, sans-serif}
body{background:#f5f7fb;color:#333}
.container{display:flex;min-height:100vh}
/* Sidebar */
.sidebar{width:230px;background:var(--dark);color:#fff;padding:20px 12px}
.sidebar h2{font-size:1.25rem;display:flex;gap:10px;align-items:center}
.sidebar ul{list-style:none;margin-top:20px}
.sidebar a{display:flex;align-items:center;padding:10px 12px;color:#fff;text-decoration:none;border-radius:6px;margin-bottom:6px;gap:10px}
.sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,0.06)}
/* Main */
.main{flex:1;padding:20px;overflow:auto}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.user-info{display:flex;align-items:center;gap:12px}
.user-avatar{width:40px;height:40px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:18px}
.stat{background:#fff;padding:16px;border-radius:8px;box-shadow:0 6px 12px rgba(0,0,0,0.04);text-align:center}
.stat i{font-size:24px;margin-bottom:8px;color:var(--primary);display:block}
.stat .value{font-size:1.6rem;font-weight:700}
.filters{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px}
.filter-card{background:#fff;padding:12px;border-radius:8px;box-shadow:0 4px 10px rgba(0,0,0,0.04);display:flex;gap:8px;align-items:center}
.filter-card select,.filter-card input{padding:8px;border:1px solid var(--light-gray);border-radius:6px}
.btn{background:var(--primary);color:#fff;padding:8px 12px;border-radius:6px;border:none;cursor:pointer}
.table-card{background:#fff;padding:16px;border-radius:8px;box-shadow:0 6px 12px rgba(0,0,0,0.04);overflow:auto}
.table-card h3{margin-bottom:8px}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid var(--light-gray);text-align:left}
th{background:var(--light);font-weight:600}
.actions a{display:inline-block;padding:6px 10px;border-radius:6px;color:#fff;text-decoration:none;font-size:0.85rem;margin-right:6px}
.actions a.view{background:var(--info)}.actions a.edit{background:var(--success)}.actions a.regs{background:var(--primary)}
.status-badge{padding:5px 10px;border-radius:20px;font-size:0.8rem}
.status-published{background:#e8f5e9;color:#2e7d32}.status-upcoming{background:#e3f2fd;color:#1565c0}.status-cancelled{background:#ffebee;color:#c62828}.status-draft{background:#f5f5f5;color:#616161}
.calendar{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:12px;margin-top:16px}
.card-event{background:#fff;padding:12px;border-left:4px solid var(--primary);border-radius:8px;box-shadow:0 4px 8px rgba(0,0,0,0.04)}
.card-event h4{margin-bottom:6px}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:50;align-items:center;justify-content:center}
.modal .content{background:#fff;padding:18px;border-radius:8px;width:min(900px,95%);max-height:80%;overflow:auto}
.close-btn{float:right;background:#eee;border:0;padding:6px 10px;border-radius:6px;cursor:pointer}
@media(max-width:768px){.container{flex-direction:column}.sidebar{width:100%}}
</style>
</head>
<body>
<div class="container">

    <!-- Sidebar -->
    <aside class="sidebar">
        <h2><i class="fas fa-calendar-alt"></i> EventManager</h2>
        <ul>
            <li><a href="Admin-Dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="Events.php" class="active"><i class="fas fa-calendar-check"></i> Events</a></li>
            <li><a href="user-management.php"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="Reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="security.php"><i class="fas fa-shield-alt"></i> Security</a></li>
            <li><a href="Backups.php"><i class="fas fa-database"></i> Backups</a></li>
            <li><a href="AuditLogs.php"><i class="fas fa-history"></i> Audit Logs</a></li>
            <li><a href="../HTML/Login.html"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <!-- Main -->
    <main class="main">
        <div class="header">
            <div>
                <h1>Events Monitoring</h1>
                <small>Monitor events, organizers and registrations</small>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php
                    $fn = $_SESSION['FirstName'] ?? null;
                    $ln = $_SESSION['LastName'] ?? null;
                    if ($fn && $ln) {
                        echo htmlspecialchars(substr($fn,0,1) . substr($ln,0,1));
                    } elseif ($fn) {
                        echo htmlspecialchars(substr($fn,0,1) . 'A');
                    } else {
                        echo 'AD';
                    }
                    ?>
                </div>
                <div>
                    <div><?= htmlspecialchars( ($fn || $ln) ? trim(($fn ?? '') . ' ' . ($ln ?? '')) : 'Admin' ) ?></div>
                    <small><?= htmlspecialchars($_SESSION['Role'] ?? 'Admin') ?></small>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats">
            <div class="stat"><i class="fas fa-calendar"></i><div class="value"><?= number_format($events_total) ?></div><div class="title">Total Events</div></div>
            <div class="stat"><i class="fas fa-user-friends"></i><div class="value"><?= number_format($registrations_total) ?></div><div class="title">Total Registrations</div></div>
            <div class="stat"><i class="fas fa-clock"></i><div class="value"><?= number_format($registrations_pending) ?></div><div class="title">Pending Registrations</div></div>
            <div class="stat"><i class="fas fa-search"></i><div class="value"><?= htmlentities($search_term ?: '—') ?></div><div class="title">Current Search</div></div>
        </div>

        <!-- Filters -->
        <form method="GET" class="filters" onsubmit="return true">
            <div class="filter-card">
                <label for="status_filter">Status</label>
                <select id="status_filter" name="status_filter">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All</option>
                    <?php
                    // try to gather existing statuses present in DB if Status column exists
                    if ($colStatus) {
                        $sRes = $conn->query("SELECT DISTINCT `{$colStatus}` AS st FROM Events");
                        $statuses = [];
                        if ($sRes) {
                            while ($r = $sRes->fetch_assoc()) $statuses[] = $r['st'];
                            foreach ($statuses as $st) {
                                if ($st === null) continue;
                                $selected = ($status_filter === $st) ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($st) . "\" $selected>" . htmlspecialchars($st) . "</option>";
                            }
                        }
                    } else {
                        // fallback statuses
                        $fallback = ['Draft','Upcoming','Published','Cancelled'];
                        foreach ($fallback as $st) {
                            $selected = ($status_filter === $st) ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($st) . "\" $selected>$st</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="filter-card">
                <label for="organizer_filter">Organizer</label>
                <select id="organizer_filter" name="organizer_filter">
                    <option value="all" <?= $organizer_filter === 'all' ? 'selected' : '' ?>>All</option>
                    <?php
                    if ($organizers_result) {
                        while ($org = $organizers_result->fetch_assoc()) {
                            $id = $org['UserID'];
                            $name = trim(($org['Firstname'] ?? '') . ' ' . ($org['Lastname'] ?? ''));
                            $sel = ($organizer_filter == $id) ? 'selected' : '';
                            echo "<option value=\"" . intval($id) . "\" $sel>" . htmlspecialchars($name) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="filter-card" style="flex:1;">
                <label for="search">Search</label>
                <div style="display:flex;gap:8px;">
                    <input id="search" name="search" type="text" placeholder="Title or venue..." value="<?= htmlspecialchars($search_term) ?>">
                    <button type="submit" class="btn"><i class="fas fa-search"></i> Search</button>
                    <a href="Events.php" class="btn" style="background:#6c757d;margin-left:6px"><i class="fas fa-times"></i> Clear</a>
                </div>
            </div>
        </form>

        <!-- Table View -->
        <div class="table-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <h3>Events</h3>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Organizer</th>
                        <th>Date</th>
                        <th>Venue</th>
                        <th>Status</th>
                        <th>Registrations</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($events_result && $events_result->num_rows):
                    while ($row = $events_result->fetch_assoc()):
                        $evtId = $row['EventID'] ?? '';
                        $title = $row['Title'] ?? ($row['EventName'] ?? '—');
                        $orgName = $row['OrganizerName'] ?? '';
                        $dateLabel = $row['Start_Date'] ?? ($row['Created_at'] ?? '—');
                        $venue = $row['Venue_Name'] ?? '—';
                        $status = $row['Status'] ?? '—';
                        $totalRegs = $row['TotalRegistrations'] ?? 0;
                        $pendingRegs = $row['PendingRegistrations'] ?? 0;
                        // status class
                        $sClass = 'status-draft';
                        if (stripos($status,'publish') !== false || stripos($status,'published') !== false) $sClass = 'status-published';
                        if (stripos($status,'upcoming') !== false) $sClass = 'status-upcoming';
                        if (stripos($status,'cancel') !== false) $sClass = 'status-cancelled';
                ?>
                    <tr>
                        <td><?= htmlspecialchars($evtId) ?></td>
                        <td><?= htmlspecialchars($title) ?></td>
                        <td><?= htmlspecialchars($orgName ?: '—') ?></td>
                        <td><?= htmlspecialchars($dateLabel) ?></td>
                        <td><?= htmlspecialchars($venue) ?></td>
                        <td><span class="status-badge <?= $sClass ?>"><?= htmlspecialchars($status) ?></span></td>
                        <td><?= intval($totalRegs) ?> total / <span style="color:#ef6c00"><?= intval($pendingRegs) ?> pending</span></td>
                        <td class="actions">
                            <a class="view" href="EventDetails.php?event_id=<?= urlencode($evtId) ?>"><i class="fas fa-eye"></i> View</a>
                            <a class="regs" href="#" onclick="openRegistrations(<?= htmlspecialchars(json_encode($evtId)) ?>);return false;"><i class="fas fa-users"></i> Registrations</a>
                        </td>
                    </tr>
                <?php
                    endwhile;
                else:
                ?>
                    <tr><td colspan="8" style="text-align:center">No events found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Calendar / Upcoming -->
        <div class="table-card" style="margin-top:16px;">
            <h3>Upcoming (recent) Events</h3>
            <div class="calendar">
                <?php
                // attempt to fetch a few upcoming entries (use Start_Date if present)
                $calSql = $colStart ? "SELECT " . ($colEventID ? "`{$colEventID}` AS EventID," : "NULL AS EventID,") . ($colTitle ? "`{$colTitle}` AS Title, " : "NULL AS Title,") . ($colStart ? "`{$colStart}` AS Start_Date, " : "NULL AS Start_Date,") . ($colStatus ? "`{$colStatus}` AS Status " : "NULL AS Status ") . "FROM Events WHERE " . ($colStart ? "`{$colStart}` >= DATE_SUB(NOW(), INTERVAL 1 MONTH) " : "1=1 ") . "ORDER BY " . ($colStart ? "`{$colStart}` ASC" : ($colEventID ? "`{$colEventID}` DESC" : "1")) . " LIMIT 12" : "SELECT " . ($colEventID ? "`{$colEventID}` AS EventID," : "NULL AS EventID,") . ($colTitle ? "`{$colTitle}` AS Title, " : "NULL AS Title,") . "NULL AS Start_Date, NULL AS Status FROM Events ORDER BY " . ($colEventID ? "`{$colEventID}` DESC" : "1") . " LIMIT 12";
                $calRes = $conn->query($calSql);
                if ($calRes) {
                    while ($ev = $calRes->fetch_assoc()) {
                        $evTitle = $ev['Title'] ?? 'Untitled';
                        $evStart = $ev['Start_Date'] ?? '—';
                        $evStatus = $ev['Status'] ?? '';
                        $statusClass = '';
                        if (stripos($evStatus,'publish')!==false) $statusClass='status-published';
                        if (stripos($evStatus,'upcoming')!==false) $statusClass='status-upcoming';
                        if (stripos($evStatus,'cancel')!==false) $statusClass='status-cancelled';
                ?>
                <div class="card-event">
                    <h4><?= htmlspecialchars($evTitle) ?></h4>
                    <div class="event-date"><?= htmlspecialchars($evStart) ?></div>
                    <div class="event-status <?= $statusClass ?>"><?= htmlspecialchars(ucfirst($evStatus)) ?></div>
                    <div style="margin-top:8px">
                        <?php if (!empty($ev['EventID'])): ?>
                        <a class="btn" href="EventDetails.php?event_id=<?= urlencode($ev['EventID']) ?>">View</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                    }
                }
                ?>
            </div>
        </div>

        <!-- Registrations Modal -->
        <div id="registrationsModal" class="modal" aria-hidden="true">
            <div class="content">
                <button class="close-btn" onclick="closeModal()">Close</button>
                <h3>Event Registrations</h3>
                <div id="modalBody">Loading...</div>
            </div>
        </div>
    </main>
</div>

<script>
// open registrations modal - calls this same page with ?action=registrations&event_id=...
async function openRegistrations(eventId) {
    const modal = document.getElementById('registrationsModal');
    const body = document.getElementById('modalBody');
    body.innerHTML = '<p>Loading…</p>';
    modal.style.display = 'flex';
    try {
        const resp = await fetch(`Events.php?action=registrations&event_id=${encodeURIComponent(eventId)}`);
        const data = await resp.json();
        if (!data.success) {
            body.innerHTML = '<p>Error loading registrations.</p>';
            return;
        }
        if (data.rows.length === 0) {
            body.innerHTML = '<p>No registrations.</p>';
            return;
        }
        // build table
        let html = '<table style="width:100%;border-collapse:collapse">';
        html += '<thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Payment</th><th>When</th></tr></thead><tbody>';
        for (const r of data.rows) {
            html += `<tr>
                <td>${escapeHtml(r.RegistrationID || '')}</td>
                <td>${escapeHtml(((r.First_Name||'') + ' ' + (r.Last_Name||'')).trim())}</td>
                <td>${escapeHtml(r.Email || '')}</td>
                <td>${escapeHtml(r.Phone || '')}</td>
                <td>${escapeHtml(r.Status || '')}</td>
                <td>${escapeHtml(r.Payment_Status || '')}</td>
                <td>${escapeHtml(r.Registration_Date || '')}</td>
            </tr>`;
        }
        html += '</tbody></table>';
        body.innerHTML = html;
    } catch (err) {
        body.innerHTML = '<p>Error fetching registrations.</p>';
        console.error(err);
    }
}

function closeModal() {
    const modal = document.getElementById('registrationsModal');
    modal.style.display = 'none';
}

function escapeHtml(s) {
    if (!s) return '';
    return String(s).replace(/[&<>"]/g, function (m) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m]);
    });
}

// close modal via ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>
</body>
</html>
<?php $conn->close(); ?>
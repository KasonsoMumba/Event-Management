<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is an organizer
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'Organizer') {
    header("Location: ../HTML/Login.html");
    exit();
}

$organizerID = $_SESSION['UserID'];
$userName = $_SESSION['FirstName'] ?? 'Organizer';

// Handle logout if requested
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../HTML/Login.html");
    exit();
}

// Fetch pending cash payments for events organized by this user
$query = "SELECT 
            r.*,
            e.Title AS EventTitle,
            u.FirstName,
            u.LastName,
            u.Email,
            tt.Type AS TicketType,
            tt.Price AS TicketPrice
          FROM Registrations r
          JOIN Events e ON r.EventID = e.EventID
          JOIN Users u ON r.UserID = u.UserID
          JOIN TicketTypes tt ON r.TicketTypeID = tt.TicketTypeID
          WHERE e.OrganizerID = ?
            AND r.Payment_Method = 'Cash'
            AND r.Payment_Status = 'Awaiting Payment'
            AND r.Status = 'Pending'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $organizerID);
$stmt->execute();
$result = $stmt->get_result();
$pendingPayments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registrationID = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($registrationID <= 0 || ($action !== 'approve' && $action !== 'reject')) {
        $message = "Invalid request.";
        $messageType = "error";
        header("Location: Organizer-Approval.php?message=" . urlencode($message) . "&type=" . urlencode($messageType));
        exit();
    }

    if ($action === 'approve') {
        // Generate unique ticket key
        $ticketKey = generateTicketKeyForApproval($registrationID);

        // Update registration: mark as Paid + Confirmed, set TicketKey
        $updateQuery = "UPDATE Registrations
                        SET Payment_Status = 'Paid',
                            Status = 'Confirmed',
                            TicketKey = ?
                        WHERE RegistrationID = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("si", $ticketKey, $registrationID);

        if ($updateStmt->execute()) {
            $updateStmt->close();

            // Record confirmation - USE 'Approved' for PaymentConfirmations table
            $confirmQuery = "INSERT INTO PaymentConfirmations (RegistrationID, OrganizerID, Status)
                             VALUES (?, ?, 'Approved')";
            $confirmStmt = $conn->prepare($confirmQuery);
            $confirmStmt->bind_param("ii", $registrationID, $organizerID);
            $confirmStmt->execute();
            $confirmStmt->close();

            // Send confirmation email to attendee (implement mail() as needed)
            sendApprovalConfirmation($registrationID, $ticketKey);

            $message = "Payment approved successfully! Ticket key generated: " . $ticketKey;
            $messageType = "success";
        } else {
            $message = "Error approving payment: " . $conn->error;
            $messageType = "error";
            $updateStmt->close();
        }
    } elseif ($action === 'reject') {
        // Update registration: mark as Refunded + Cancelled
        $updateQuery = "UPDATE Registrations
                        SET Payment_Status = 'Refunded',
                            Status = 'Cancelled'
                        WHERE RegistrationID = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("i", $registrationID);

        if ($updateStmt->execute()) {
            $updateStmt->close();

            // Record rejection - USE 'Rejected' for PaymentConfirmations table
            $confirmQuery = "INSERT INTO PaymentConfirmations (RegistrationID, OrganizerID, Status)
                             VALUES (?, ?, 'Rejected')";
            $confirmStmt = $conn->prepare($confirmQuery);
            $confirmStmt->bind_param("ii", $registrationID, $organizerID);
            $confirmStmt->execute();
            $confirmStmt->close();

            $message = "Payment rejected.";
            $messageType = "success";
        } else {
            $message = "Error rejecting payment: " . $conn->error;
            $messageType = "error";
            $updateStmt->close();
        }
    }

    // Refresh page to show updated list
    header("Location: Organizer-Approval.php?message=" . urlencode($message) . "&type=" . urlencode($messageType));
    exit();
}

// Helper functions
function generateTicketKeyForApproval($registrationID) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $random = substr(str_shuffle($chars), 0, 8);
    return 'TICKET-' . $registrationID . '-' . $random;
}

function sendApprovalConfirmation($registrationID, $ticketKey) {
    include 'db_connect.php';

    // Get registration details
    $query = "SELECT 
                r.*, 
                u.Email, 
                u.FirstName, 
                u.LastName, 
                e.Title AS EventTitle
              FROM Registrations r
              JOIN Users u ON r.UserID = u.UserID
              JOIN Events e ON r.EventID = e.EventID
              WHERE r.RegistrationID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $registrationID);
    $stmt->execute();
    $registration = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$registration) return;

    // Compose email
    $to = $registration['Email'];
    $subject = "Your Payment Has Been Approved - " . $registration['EventTitle'];
    $message = "Hello " . $registration['FirstName'] . ",\n\n";
    $message .= "Your cash payment for " . $registration['EventTitle'] . " has been approved.\n";
    $message .= "Ticket Key: " . $ticketKey . "\n\n";
    $message .= "You can now print your ticket from the 'My Tickets' section of your account.\n\n";
    $message .= "Thank you for your purchase!\n\n";
    $message .= "Best regards,\nEvent Management System";

    // Send email (uncomment and configure as needed)
    // mail($to, $subject, $message);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Approval - Event Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Navigation Styles */
        .navbar {
            background: #1e3a8a;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 6px 10px -4px rgba(0,0,0,0.25);
            margin-bottom: 2rem;
            border-bottom: 1px solid #9ca3af;
        }
        
        .nav-brand {
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            text-decoration: none;
        }
        
        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
            margin: 0;
        }
        
        .nav-item a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.3s;
        }
        
        .nav-item a:hover {
            opacity: 0.8;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
        }
        
        .btn-logout {
            background: rgba(255, 255, 255, 0.18);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        :root {
            --primary: #1e3a8a;
            --primary-dark: #162a66;
            --secondary: #1f2937;
            --success: #16a34a;
            --warning: #b45309;
            --danger: #b91c1c;
            --light: #f3f4f6;
            --dark: #111827;
            --gray: #4b5563;
            --border: #9ca3af;
            --shadow: 0 6px 10px -4px rgba(0,0,0,0.25);
            --radius: 6px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fb;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: var(--primary-dark);
            color: white;
            padding: 2rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
            box-shadow: none;
            border: 1px solid var(--border);
        }
        
        .header-title {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .header-subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background-color: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .alert-error {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .card {
            background: white;
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: none;
            margin-bottom: 2rem;
            border: 1px solid var(--border);
        }
        
        .card-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border);
        }
        
        .card-title {
            font-size: 1.5rem;
            color: var(--primary);
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: var(--radius);
            border: 1px solid var(--border);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background-color: #f9fafb;
        }
        
        th {
            text-align: left;
            padding: 1rem;
            font-weight: 600;
            color: var(--gray);
            border-bottom: 2px solid var(--border);
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        
        .btn-approve {
            background-color: var(--success);
            color: white;
        }
        
        .btn-approve:hover {
            background-color: #15803d;
        }
        
        .btn-reject {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-reject:hover {
            background-color: #991b1b;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
        }
        
        .amount {
            font-weight: 600;
            color: var(--primary);
        }
        
        .action-form {
            display: flex;
            gap: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                padding: 1rem;
            }
            
            .nav-menu {
                margin-top: 1rem;
                gap: 1rem;
            }
            
            .table-container {
                border: none;
            }
            
            table, thead, tbody, th, td, tr {
                display: block;
            }
            
            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            
            tr {
                margin-bottom: 1rem;
                border: 1px solid var(--border);
                border-radius: var(--radius);
            }
            
            td {
                border: none;
                border-bottom: 1px solid #eee;
                position: relative;
                padding-left: 50%;
            }
            
            td:before {
                position: absolute;
                top: 1rem;
                left: 1rem;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: 600;
                color: var(--gray);
            }
            
            td:nth-of-type(1):before { content: "Event"; }
            td:nth-of-type(2):before { content: "Attendee"; }
            td:nth-of-type(3):before { content: "Ticket Type"; }
            td:nth-of-type(4):before { content: "Amount"; }
            td:nth-of-type(5):before { content: "Registered"; }
            td:nth-of-type(6):before { content: "Actions"; }
            
            .action-form {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <a href="../HTML/Index.html" class="nav-brand">
            Event Management System
        </a>
        
        <ul class="nav-menu">
            <li class="nav-item"><a href="Organizer-dashboard.php"><i class="fas fa-calendar-alt"></i> Events</a></li>
            <li class="nav-item"><a href="Organizer-Approval.php"><i class="fas fa-check-circle"></i> Approvals</a></li>
            <li class="nav-item"><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
        </ul>
        
        <div class="user-menu">
            <span><i class="fas fa-user-circle"></i> Welcome, <?= htmlspecialchars($userName) ?></span>
            <a href="?logout=true" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="header">
            <h1 class="header-title">Payment Approvals</h1>
            <p class="header-subtitle">Review and approve or reject cash payments for your events</p>
        </div>
        
        <?php if (isset($_GET['message']) && $_GET['message'] !== ''): ?>
            <div class="alert <?= (isset($_GET['type']) && $_GET['type'] === 'error') ? 'alert-error' : 'alert-success' ?>">
                <i class="fas <?= (isset($_GET['type']) && $_GET['type'] === 'error') ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i>
                <?= htmlspecialchars($_GET['message']) ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Pending Cash Payments</h2>
            </div>
            
            <?php if (!empty($pendingPayments)): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Attendee</th>
                                <th>Ticket Type</th>
                                <th>Amount</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pendingPayments as $payment): ?>
                            <?php
                                // Fallback to ticket price if Amount_Paid is null/zero
                                $amount = isset($payment['Amount_Paid']) && $payment['Amount_Paid'] > 0
                                          ? $payment['Amount_Paid']
                                          : (isset($payment['TicketPrice']) ? $payment['TicketPrice'] : 0);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($payment['EventTitle']) ?></td>
                                <td><?= htmlspecialchars(($payment['FirstName'] ?? '') . ' ' . ($payment['LastName'] ?? '')) ?></td>
                                <td><?= htmlspecialchars($payment['TicketType']) ?></td>
                                <td class="amount">ZMW <?= number_format((float)$amount, 2) ?></td>
                                <td><?= htmlspecialchars($payment['Registration_Date']) ?></td>
                                <td>
                                    <form method="POST" class="action-form">
                                        <input type="hidden" name="registration_id" value="<?= (int)$payment['RegistrationID'] ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-approve">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-reject">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h3>No pending cash payments</h3>
                    <p>All cash payments have been processed</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
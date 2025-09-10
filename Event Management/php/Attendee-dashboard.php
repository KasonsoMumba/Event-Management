<?php
session_start();
include 'db_connect.php';

// Check if the user is logged in and is an attendee
if (!isset($_SESSION['UserID']) || $_SESSION['Role'] !== 'Attendee') {
    header("Location: ../HTML/Login.html");
    exit();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['UserID']);
$userName = $isLoggedIn ? ($_SESSION['FirstName'] ?? 'User') : '';

// Handle logout if requested
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../HTML/Login.html");
    exit();
}

// Add Image_Path column if it doesn't exist
$checkImageColumn = $conn->query("SHOW COLUMNS FROM Events LIKE 'Image_Path'");
if ($checkImageColumn->num_rows == 0) {
    $conn->query("ALTER TABLE Events ADD COLUMN Image_Path VARCHAR(255) AFTER Description");
}

// Updated query to use TicketTypes table instead of Tickets
$query = "SELECT 
            e.EventID, 
            e.Title, 
            e.Description, 
            e.Image_Path,
            e.Start_Date, 
            e.End_Date, 
            e.Venue_Name, 
            e.Capacity, 
            e.Status,
            -- Calculate sold tickets based on the difference between capacity and available tickets
            (e.Capacity - COALESCE(SUM(tt.Quantity_Available), 0)) AS TicketsSold,
            -- Get available tickets by summing quantity available from TicketTypes
            COALESCE(SUM(tt.Quantity_Available), 0) AS TicketsAvailable,
            -- Get minimum price from TicketTypes table
            MIN(tt.Price) AS MinPrice
          FROM Events e
          LEFT JOIN TicketTypes tt ON e.EventID = tt.EventID
          WHERE e.Status IN ('Upcoming', 'Published')
          GROUP BY e.EventID
          ORDER BY 
            FIELD(e.Status, 'Upcoming', 'Published'),
            e.Start_Date ASC";

$result = $conn->query($query);

$events = [
    'Upcoming' => [],
    'Published' => []
];

if ($result->num_rows > 0) {
    while ($event = $result->fetch_assoc()) {
        $events[$event['Status']][] = $event;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events Dashboard - Event Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1e3a8a; /* deeper indigo */
            --primary-light: #213e94;
            --primary-dark: #162a66;
            --secondary: #1f2937; /* slate */
            --secondary-light: #374151;
            --success: #16a34a; /* darker green */
            --warning: #b45309; /* amber-700 */
            --danger: #b91c1c; /* red-700 */
            --light: #f3f4f6; /* slightly darker than before */
            --dark: #111827; /* near-black slate */
            --gray: #4b5563; /* slate-600 */
            --border: #9ca3af; /* darker border */
            --shadow: 0 6px 10px -4px rgba(0, 0, 0, 0.25);
            --radius: 6px; /* straighter corners */
            --transition: all 0.2s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f8fafc;
            color: var(--dark);
            line-height: 1.6;
            padding-bottom: 2rem;
        }
        
        /* Navigation Styles */
        .navbar {
            background: var(--primary);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border);
        }
        
        .nav-brand {
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            transition: var(--transition);
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .nav-item a:hover {
            background: rgba(255, 255, 255, 0.12);
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
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: var(--gray);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .status-tabs {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2.5rem;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            background: white;
            color: var(--gray);
            border: 1px solid var(--border);
            padding: 0.8rem 1.5rem;
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .tab-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .tab-btn.active {
            background: var(--secondary);
            color: white;
            border-color: var(--secondary);
        }
        
        .events-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 2rem;
        }
        
        .event-card {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: none;
            transition: var(--transition);
            position: relative;
            border: 1px solid var(--border);
        }
        
        .event-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 16px -8px rgba(0, 0, 0, 0.35);
        }
        
        .event-image {
            width: 100%;
            height: 200px;
            overflow: hidden;
            position: relative;
        }
        
        .event-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .event-card:hover .event-image img {
            transform: scale(1.05);
        }
        
        .event-header {
            background: var(--primary-dark);
            color: white;
            padding: 1.2rem 1.5rem;
            position: relative;
            border-bottom: 1px solid var(--border);
        }
        
        /* Remove soft scallop decoration for a straighter look */
        .event-header::after { display: none; }
        
        .event-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .event-title {
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
        }
        
        .event-body {
            padding: 1.5rem;
        }
        
        .event-description {
            color: #374151;
            margin-bottom: 1rem;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .event-details {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        
        .detail-icon {
            width: 40px;
            height: 40px;
            background: var(--light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .detail-info {
            flex: 1;
        }
        
        .detail-label {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 0.2rem;
        }
        
        .detail-value {
            font-weight: 500;
            color: var(--dark);
        }
        
        .ticket-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
            padding: 0.9rem 1rem;
            background: var(--light);
            border-radius: var(--radius);
            border: 1px solid var(--border);
        }
        
        .price-tag {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .availability {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .sold-out {
            color: var(--danger);
            font-weight: 600;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 1rem;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            text-align: center;
            border: none;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: var(--secondary);
            color: white;
            border: 1px solid var(--border);
        }
        
        .btn-primary:hover {
            background: #111827;
            transform: translateY(-1px);
        }
        
        .btn-disabled {
            background: var(--gray);
            color: white;
            cursor: not-allowed;
        }
        
        .btn-disabled:hover {
            transform: none;
        }
        
        .no-events {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: var(--radius);
            box-shadow: none;
            border: 1px solid var(--border);
        }
        
        .no-events-icon {
            font-size: 3.5rem;
            color: var(--gray);
            margin-bottom: 1.2rem;
        }
        
        .no-events-title {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            color: var(--dark);
        }
        
        .no-events-text {
            color: var(--gray);
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .section-title {
            grid-column: 1 / -1;
            text-align: left;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--secondary);
            margin: 1.5rem 0 0.75rem 0;
            padding: 0.75rem 0.25rem;
            background: transparent;
            border-radius: 0;
            box-shadow: none;
            border-bottom: 1px solid var(--border);
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
            
            .events-container {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .status-tabs {
                flex-direction: column;
                align-items: center;
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
        <span><i class="fas fa-user-circle"></i> Welcome, <?= htmlspecialchars($userName) ?></span>
        <ul class="nav-menu">
            <li class="nav-item"><a href="Attendee-dashboard.php"><i class="fas fa-calendar-alt"></i> Events</a></li>
            <li class="nav-item"><a href="my_tickets.php"><i class="fas fa-ticket-alt"></i> My Tickets</a></li>
            <li class="nav-item"><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
        </ul>
        
        <div class="user-menu">
            <?php if ($isLoggedIn): ?>
               
                <a href="?logout=true" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <?php else: ?>
                <a href="../HTML/Login.html">Login</a>
                <a href="../HTML/User-Registration.html">Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Events Dashboard</h1>
            <p class="page-subtitle">Discover and register for amazing events happening around you</p>
        </div>
        
        <div class="status-tabs">
            <button class="tab-btn active" data-tab="all">
                <i class="fas fa-th-large"></i> All Events
            </button>
            <button class="tab-btn" data-tab="upcoming">
                <i class="fas fa-clock"></i> Upcoming
            </button>
            <button class="tab-btn" data-tab="published">
                <i class="fas fa-check-circle"></i> Published
            </button>
        </div>

        <div class="events-container">
            <?php if (empty($events['Upcoming']) && empty($events['Published'])): ?>
                <div class="no-events">
                    <div class="no-events-icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <h2 class="no-events-title">No Events Available</h2>
                    <p class="no-events-text">No events are available at the moment. Please check back later for exciting events!</p>
                </div>
            <?php else: ?>
                <!-- Upcoming Events Section -->
                <?php if (!empty($events['Upcoming'])): ?>
                    <?php foreach ($events['Upcoming'] as $event): ?>
                        <div class="event-card" data-status="upcoming">
                            <?php if (!empty($event['Image_Path'])): ?>
                                <div class="event-image">
                                    <img src="../<?= htmlspecialchars($event['Image_Path']) ?>" alt="<?= htmlspecialchars($event['Title']) ?> Event Image">
                                </div>
                            <?php endif; ?>
                            <div class="event-header">
                                <span class="event-status">Upcoming</span>
                                <h2 class="event-title"><?= htmlspecialchars($event['Title']) ?></h2>
                            </div>
                            
                            <div class="event-body">
                                <p class="event-description"><?= htmlspecialchars($event['Description']) ?></p>
                                
                                <div class="event-details">
                                    <div class="detail-item">
                                        <div class="detail-icon">
                                            <i class="fas fa-calendar-day"></i>
                                        </div>
                                        <div class="detail-info">
                                            <div class="detail-label">Event Date</div>
                                            <div class="detail-value">
                                                <?= date('F j, Y', strtotime($event['Start_Date'])) ?>
                                                <?= $event['End_Date'] ? ' - ' . date('F j, Y', strtotime($event['End_Date'])) : '' ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <div class="detail-icon">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </div>
                                        <div class="detail-info">
                                            <div class="detail-label">Venue</div>
                                            <div class="detail-value"><?= htmlspecialchars($event['Venue_Name']) ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="ticket-info">
                                    <div class="price-tag">
                                        <?= isset($event['MinPrice']) && $event['MinPrice'] > 0 ? 'From ZMK ' . number_format($event['MinPrice'], 2) : 'Free' ?>
                                    </div>
                                    <div class="availability">
                                        <?php if ($event['TicketsAvailable'] > 0): ?>
                                            <i class="fas fa-ticket-alt"></i> <?= $event['TicketsAvailable'] ?> tickets available
                                        <?php else: ?>
                                            <span class="sold-out"><i class="fas fa-times-circle"></i> Sold Out</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <a href="Ticket-Registration.php?event_id=<?= $event['EventID'] ?>" 
                                   class="btn <?= $event['TicketsAvailable'] <= 0 ? 'btn-disabled' : 'btn-primary' ?>" 
                                   <?= $event['TicketsAvailable'] <= 0 ? 'onclick="return false;"' : '' ?>>
                                    <?php if ($event['TicketsAvailable'] <= 0): ?>
                                        <i class="fas fa-times-circle"></i> Sold Out
                                    <?php else: ?>
                                        <i class="fas fa-ticket-alt"></i> Register Now
                                    <?php endif; ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Published Events Section -->
                <?php if (!empty($events['Published'])): ?>
                    <div class="section-title">
                        <i class="fas fa-check-circle"></i> Published Events
                    </div>
                    <?php foreach ($events['Published'] as $event): ?>
                        <div class="event-card" data-status="published">
                            <?php if (!empty($event['Image_Path'])): ?>
                                <div class="event-image">
                                    <img src="../<?= htmlspecialchars($event['Image_Path']) ?>" alt="<?= htmlspecialchars($event['Title']) ?> Event Image">
                                </div>
                            <?php endif; ?>
                            <div class="event-header">
                                <span class="event-status">Published</span>
                                <h2 class="event-title"><?= htmlspecialchars($event['Title']) ?></h2>
                            </div>
                            
                            <div class="event-body">
                                <p class="event-description"><?= htmlspecialchars($event['Description']) ?></p>
                                
                                <div class="event-details">
                                    <div class="detail-item">
                                        <div class="detail-icon">
                                            <i class="fas fa-calendar-day"></i>
                                        </div>
                                        <div class="detail-info">
                                            <div class="detail-label">Event Date</div>
                                            <div class="detail-value">
                                                <?= date('F j, Y', strtotime($event['Start_Date'])) ?>
                                                <?= $event['End_Date'] ? ' - ' . date('F j, Y', strtotime($event['End_Date'])) : '' ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <div class="detail-icon">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </div>
                                        <div class="detail-info">
                                            <div class="detail-label">Venue</div>
                                            <div class="detail-value"><?= htmlspecialchars($event['Venue_Name']) ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="ticket-info">
                                    <div class="price-tag">
                                        <?= isset($event['MinPrice']) && $event['MinPrice'] > 0 ? 'From ZMK ' . number_format($event['MinPrice'], 2) : 'Free' ?>
                                    </div>
                                    <div class="availability">
                                        <?php if ($event['TicketsAvailable'] > 0): ?>
                                            <i class="fas fa-ticket-alt"></i> <?= $event['TicketsAvailable'] ?> tickets available
                                        <?php else: ?>
                                            <span class="sold-out"><i class="fas fa-times-circle"></i> Sold Out</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <a href="Ticket-Registration.php?event_id=<?= $event['EventID'] ?>" 
                                   class="btn <?= $event['TicketsAvailable'] <= 0 ? 'btn-disabled' : 'btn-primary' ?>" 
                                   <?= $event['TicketsAvailable'] <= 0 ? 'onclick="return false;"' : '' ?>>
                                    <?php if ($event['TicketsAvailable'] <= 0): ?>
                                        <i class="fas fa-times-circle"></i> Sold Out
                                    <?php else: ?>
                                        <i class="fas fa-ticket-alt"></i> Register Now
                                    <?php endif; ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.querySelectorAll('.tab-btn').forEach(button => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                button.classList.add('active');
                
                const tab = button.dataset.tab;
                const eventCards = document.querySelectorAll('.event-card');
                
                if (tab === 'all') {
                    eventCards.forEach(card => card.style.display = 'block');
                } else {
                    eventCards.forEach(card => {
                        card.style.display = card.dataset.status === tab ? 'block' : 'none';
                    });
                }
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
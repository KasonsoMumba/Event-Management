<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['UserID'])) {
    header("Location: Login.html");
    exit();
}

// Updated query to use TicketTypes table instead of Tickets
$query = "SELECT 
            e.EventID, 
            e.Title, 
            e.Description, 
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
    <title>Attendee Dashboard</title>
    <link rel="stylesheet" href="../stylesheets.css">
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .events-container {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .events-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .status-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .tab-btn {
            padding: 12px 24px;
            background: none;
            border: none;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            color: #2563eb;
            border-bottom: 3px solid #2563eb;
        }
        
        .events-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .event-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .event-header {
            padding: 16px;
            background-color: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .event-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-upcoming {
            background-color: #3b82f6;
            color: white;
        }
        
        .status-published {
            background-color: #10b981;
            color: white;
        }
        
        .event-body {
            padding: 16px;
        }
        
        .event-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1f2937;
        }
        
        .event-description {
            color: #4b5563;
            margin-bottom: 16px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .event-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            color: #4b5563;
        }
        
        .detail-icon {
            margin-right: 8px;
            color: #6b7280;
        }
        
        .ticket-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 16px;
        }
        
        .price-tag {
            font-weight: 600;
            color: #1f2937;
        }
        
        .availability {
            font-size: 0.9rem;
        }
        
        .sold-out {
            color: #ef4444;
            font-weight: 600;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background-color: #2563eb;
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            background-color: #1d4ed8;
        }
        
        .btn-disabled {
            background-color: #9ca3af;
            color: white;
            border: none;
            cursor: not-allowed;
        }
        
        .no-events {
            text-align: center;
            padding: 40px 20px;
            grid-column: 1 / -1;
        }
        
        .section-title {
            padding: 16px 20px;
            background-color: #f3f4f6;
            font-size: 1.2rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .tab-btn a {
            text-decoration: none;
            color: inherit;
            display: block;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Event Dashboard</h1>
        </div>
        
        <div class="events-container">
            <div class="events-header">
                <h2>Available Events</h2>
            </div>
            
            <div class="status-tabs">
                <button class="tab-btn active" data-tab="all">All Events</button>
                <button class="tab-btn" data-tab="upcoming">Upcoming</button>
                <button class="tab-btn" data-tab="published">Published</button>
                <button class="tab-btn">
                    <a href="my_tickets.php">My Tickets</a>
                </button>
            </div>
            
            <div class="events-content">
                <?php if (empty($events['Upcoming']) && empty($events['Published'])): ?>
                    <div class="no-events">
                        <p>No events available at the moment. Please check back later!</p>
                        <a href="#" class="btn btn-primary">Browse All Events</a>
                    </div>
                <?php else: ?>
                    <!-- Upcoming Events Section -->
                    <?php if (!empty($events['Upcoming'])): ?>
                        <div class="section-title">Upcoming Events</div>
                        <div class="events-list">
                            <?php foreach ($events['Upcoming'] as $event): ?>
                                <div class="event-card" data-status="upcoming">
                                    <div class="event-header">
                                        <span class="event-status status-upcoming">Upcoming</span>
                                    </div>
                                    <div class="event-body">
                                        <h3 class="event-title"><?= htmlspecialchars($event['Title']) ?></h3>
                                        <p class="event-description"><?= htmlspecialchars($event['Description']) ?></p>
                                        
                                        <div class="event-details">
                                            <div class="detail-item">
                                                <span class="detail-icon">📅</span>
                                                <?= date('M j, Y', strtotime($event['Start_Date'])) ?>
                                                <?= $event['End_Date'] ? ' - ' . date('M j, Y', strtotime($event['End_Date'])) : '' ?>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-icon">📍</span>
                                                <?= htmlspecialchars($event['Venue_Name']) ?>
                                            </div>
                                        </div>
                                        
                                        <div class="ticket-info">
                                            <div class="price-tag">
                                                <?= isset($event['MinPrice']) && $event['MinPrice'] > 0 ? 'From ZMK ' . number_format($event['MinPrice'], 2) : 'Free' ?>
                                            </div>
                                            <div class="availability">
                                                <?php if ($event['TicketsAvailable'] > 0): ?>
                                                    <?= $event['TicketsAvailable'] ?> tickets available
                                                <?php else: ?>
                                                    <span class="sold-out">Sold Out</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <a href="Ticket-Registration.php?event_id=<?= $event['EventID'] ?>" 
                                           class="btn btn-primary <?= $event['TicketsAvailable'] <= 0 ? 'btn-disabled' : '' ?>" 
                                           <?= $event['TicketsAvailable'] <= 0 ? 'disabled' : '' ?>>
                                            Register Now
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Published Events Section -->
                    <?php if (!empty($events['Published'])): ?>
                        <div class="section-title">Published Events</div>
                        <div class="events-list">
                            <?php foreach ($events['Published'] as $event): ?>
                                <div class="event-card" data-status="published">
                                    <div class="event-header">
                                        <span class="event-status status-published">Published</span>
                                    </div>
                                    <div class="event-body">
                                        <h3 class="event-title"><?= htmlspecialchars($event['Title']) ?></h3>
                                        <p class="event-description"><?= htmlspecialchars($event['Description']) ?></p>
                                        
                                        <div class="event-details">
                                            <div class="detail-item">
                                                <span class="detail-icon">📅</span>
                                                <?= date('M j, Y', strtotime($event['Start_Date'])) ?>
                                                <?= $event['End_Date'] ? ' - ' . date('M j, Y', strtotime($event['End_Date'])) : '' ?>
                                            </div>
                                            <div class="detail-item">
                                                <span class="detail-icon">📍</span>
                                                <?= htmlspecialchars($event['Venue_Name']) ?>
                                            </div>
                                        </div>
                                        
                                        <div class="ticket-info">
                                            <div class="price-tag">
                                                <?= isset($event['MinPrice']) && $event['MinPrice'] > 0 ? 'From ZMK ' . number_format($event['MinPrice'], 2) : 'Free' ?>
                                            </div>
                                            <div class="availability">
                                                <?php if ($event['TicketsAvailable'] > 0): ?>
                                                    <?= $event['TicketsAvailable'] ?> tickets available
                                                <?php else: ?>
                                                    <span class="sold-out">Sold Out</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <a href="Ticket-Registration.php?event_id=<?= $event['EventID'] ?>" 
                                           class="btn btn-primary <?= $event['TicketsAvailable'] <= 0 ? 'btn-disabled' : '' ?>" 
                                           <?= $event['TicketsAvailable'] <= 0 ? 'disabled' : '' ?>>
                                            Register Now
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.tab-btn').forEach(button => {
            button.addEventListener('click', () => {
                // Skip if it's the "My Tickets" button with a link
                if (button.querySelector('a')) return;
                
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
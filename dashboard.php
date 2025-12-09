<?php
// dashboard.php - Dashboard page
require_once 'init.php';
requireLogin();

// Get current user
$user = getCurrentUser();

// Connect to database
$conn = connectDB();

// Get stats - adjust queries based on your actual table structure
$stats = [
    'total_rooms' => 0,
    'available_rooms' => 0,
    'today_checkins' => 0,
    'today_checkouts' => 0
];

// Get table info
$tables = $conn->query("SHOW TABLES")->fetch_all(MYSQLI_ASSOC);

// Try to get room count if rooms table exists
$hasRoomsTable = false;
foreach ($tables as $table) {
    if (in_array('rooms', $table) || strpos(json_encode($table), 'rooms') !== false) {
        $hasRoomsTable = true;
        break;
    }
}

if ($hasRoomsTable) {
    $rooms_result = $conn->query("SELECT COUNT(*) as total FROM rooms");
    if ($rooms_result) {
        $stats['total_rooms'] = $rooms_result->fetch_assoc()['total'] ?? 0;
    }
    
    $available_result = $conn->query("SELECT COUNT(*) as available FROM rooms WHERE status = 'available'");
    if ($available_result) {
        $stats['available_rooms'] = $available_result->fetch_assoc()['available'] ?? 0;
    }
}

// Try to get reservations if table exists
$hasReservationsTable = false;
foreach ($tables as $table) {
    if (in_array('reservations', $table) || strpos(json_encode($table), 'reservations') !== false || 
        in_array('bookings', $table) || strpos(json_encode($table), 'bookings') !== false) {
        $hasReservationsTable = true;
        break;
    }
}

if ($hasReservationsTable) {
    $today = date('Y-m-d');
    
    // Try reservations table
    $checkins_result = $conn->query("SELECT COUNT(*) as checkins FROM reservations WHERE check_in = '$today'");
    if ($checkins_result) {
        $stats['today_checkins'] = $checkins_result->fetch_assoc()['checkins'] ?? 0;
    } else {
        // Try bookings table
        $checkins_result = $conn->query("SELECT COUNT(*) as checkins FROM bookings WHERE check_in_date = '$today'");
        if ($checkins_result) {
            $stats['today_checkins'] = $checkins_result->fetch_assoc()['checkins'] ?? 0;
        }
    }
    
    // Try checkouts
    $checkouts_result = $conn->query("SELECT COUNT(*) as checkouts FROM reservations WHERE check_out = '$today'");
    if ($checkouts_result) {
        $stats['today_checkouts'] = $checkouts_result->fetch_assoc()['checkouts'] ?? 0;
    } else {
        $checkouts_result = $conn->query("SELECT COUNT(*) as checkouts FROM bookings WHERE check_out_date = '$today'");
        if ($checkouts_result) {
            $stats['today_checkouts'] = $checkouts_result->fetch_assoc()['checkouts'] ?? 0;
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - EvoDesk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4a6fa5;
            --primary-dark: #3a5980;
            --secondary: #f5f7fa;
            --text: #333;
            --text-light: #666;
            --border: #ddd;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        body {
            background: #f5f7fa;
            min-height: 100vh;
        }
        
        /* Top Navigation */
        .top-nav {
            background: white;
            padding: 0 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-details {
            text-align: right;
        }
        
        .user-name {
            font-weight: 500;
            color: var(--text);
        }
        
        .user-role {
            font-size: 0.85rem;
            color: var(--text-light);
        }
        
        .logout-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .logout-btn:hover {
            background: #c82333;
        }
        
        /* Main Content */
        .main-content {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .welcome-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .welcome-title {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .welcome-subtitle {
            opacity: 0.9;
            font-size: 1rem;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .stat-content h3 {
            font-size: 2rem;
            color: var(--text);
            margin-bottom: 5px;
        }
        
        .stat-content p {
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        /* Quick Actions */
        .quick-actions {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1.3rem;
            color: var(--text);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }
        
        .action-btn {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            color: var(--text);
            text-decoration: none;
            display: block;
        }
        
        .action-btn:hover {
            background: white;
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 111, 165, 0.1);
        }
        
        .action-icon {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .action-btn h4 {
            font-size: 1rem;
            margin-bottom: 5px;
        }
        
        .action-btn p {
            font-size: 0.85rem;
            color: var(--text-light);
        }
        
        /* Recent Activity */
        .recent-activity {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .activity-list {
            list-style: none;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: var(--primary);
        }
        
        .activity-content h4 {
            font-size: 1rem;
            color: var(--text);
            margin-bottom: 5px;
        }
        
        .activity-content p {
            color: var(--text-light);
            font-size: 0.85rem;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            color: var(--text-light);
            font-size: 0.9rem;
            border-top: 1px solid var(--border);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
            
            .top-nav {
                flex-direction: column;
                height: auto;
                padding: 15px;
                gap: 15px;
            }
            
            .user-info {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="logo">EvoDesk</div>
        <div class="user-info">
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($user['role']); ?></div>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h1>
            <p class="welcome-subtitle"><?php echo date('l, F j, Y'); ?> • <span id="currentTime"><?php echo date('h:i A'); ?></span></p>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-hotel"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_rooms']; ?></h3>
                    <p>Total Rooms</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-bed"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['available_rooms']; ?></h3>
                    <p>Available Rooms</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['today_checkins']; ?></h3>
                    <p>Today's Check-ins</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['today_checkouts']; ?></h3>
                    <p>Today's Check-outs</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2 class="section-title">Quick Actions</h2>
            <div class="actions-grid">
                <a href="#" class="action-btn">
                    <div class="action-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <h4>New Booking</h4>
                    <p>Create reservation</p>
                </a>
                
                <a href="#" class="action-btn">
                    <div class="action-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h4>Check Availability</h4>
                    <p>Room availability</p>
                </a>
                
                <a href="#" class="action-btn">
                    <div class="action-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <h4>Check-in Guest</h4>
                    <p>Guest registration</p>
                </a>
                
                <a href="#" class="action-btn">
                    <div class="action-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <h4>Generate Report</h4>
                    <p>Daily reports</p>
                </a>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="recent-activity">
            <h2 class="section-title">Recent Activity</h2>
            <ul class="activity-list">
                <li class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="activity-content">
                        <h4>You logged in</h4>
                        <p>Just now • System access</p>
                    </div>
                </li>
                <li class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <div class="activity-content">
                        <h4>Password Secured</h4>
                        <p>MD5 upgraded to secure hash</p>
                    </div>
                </li>
                <li class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="activity-content">
                        <h4>Database Connected</h4>
                        <p>FrontDeskOS database active</p>
                    </div>
                </li>
            </ul>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            © 2025 EvoDesk - Manage Smarter • FrontDeskOS PMS Rebuild v1.0
        </div>
    </div>

    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
            document.getElementById('currentTime').textContent = timeString;
        }
        
        // Update time every minute
        setInterval(updateTime, 60000);
        
        // Auto-refresh dashboard every 5 minutes
        setTimeout(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
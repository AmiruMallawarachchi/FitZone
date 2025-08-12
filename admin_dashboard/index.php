<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get total members count
$query = "SELECT COUNT(*) as total_members FROM members";
$result = $conn->query($query);
$members_count = $result->fetch_assoc()['total_members'];

// Get active trainers count
$query = "SELECT COUNT(*) as total_trainers FROM trainers WHERE status = 'active'";
$result = $conn->query($query);
$trainers_count = $result->fetch_assoc()['total_trainers'];

// Get today's classes count
$query = "SELECT COUNT(*) as today_classes FROM classes 
          WHERE DATE(schedule_time) = CURDATE() AND status = 'active'";
$result = $conn->query($query);
$classes_count = $result->fetch_assoc()['today_classes'];

// Get pending queries count
$query = "SELECT COUNT(*) as pending_queries FROM queries WHERE status = 'pending'";
$result = $conn->query($query);
$queries_count = $result->fetch_assoc()['pending_queries'];

// Get recent activities
$query = "SELECT 'member' as type, m.member_id, u.full_name, u.created_at as date, 'New Member Registration' as activity
          FROM members m
          JOIN users u ON m.user_id = u.id
          UNION ALL
          SELECT 'class' as type, cb.class_id, CONCAT(u.full_name, ' - ', c.name) as full_name, 
                 cb.booking_date as date, 'New Class Booking' as activity
          FROM class_bookings cb
          JOIN classes c ON cb.class_id = c.class_id
          JOIN members m ON cb.member_id = m.member_id
          JOIN users u ON m.user_id = u.id
          UNION ALL
          SELECT 'query' as type, q.query_id, CONCAT(u.full_name, ' - ', q.subject) as full_name, 
                 q.query_date as date, 'New Query' as activity
          FROM queries q
          JOIN members m ON q.member_id = m.member_id
          JOIN users u ON m.user_id = u.id
          WHERE q.status = 'pending'
          ORDER BY date DESC
          LIMIT 5";

$recent_activities = $conn->query($query);

if (!$recent_activities) {
    // Log the error for debugging
    error_log("Query failed: " . $conn->error);
    // Create an empty result set
    $recent_activities = new class {
        public $num_rows = 0;
        public function fetch_assoc() { return null; }
    };
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FitZone</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #4a90e2;
            --secondary: #50c878;
            --dark: #2c3e50;
            --light: #f8f9fa;
            --danger: #e74c3c;
            --warning: #f1c40f;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f0f2f5;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: var(--dark);
            color: white;
            padding: 20px;
        }

        .sidebar-header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h2 {
            color: var(--primary);
            font-size: 24px;
        }

        .nav-menu {
            margin-top: 30px;
        }

        .nav-item {
            padding: 15px;
            margin: 5px 0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            text-decoration: none;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
        }

        .nav-item.active {
            background: var(--primary);
        }

        .nav-item i {
            width: 20px;
        }

        .main-content {
            flex: 1;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideIn 0.5s ease-out forwards;
            opacity: 0;
        }

        .header h1 {
            position: relative;
            padding-bottom: 10px;
            color: var(--dark);
        }

        .header h1::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--primary);
            animation: slideIn 0.5s ease-out forwards;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            animation: slideIn 0.5s ease-out forwards;
            opacity: 0;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--primary);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            transition: all 0.3s ease;
            animation: pulse 2s infinite;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(10deg);
        }

        .stat-info h3 {
            font-size: 28px;
            margin-bottom: 5px;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: slideUp 0.5s ease-out forwards;
            opacity: 0;
        }

        .stat-info p {
            color: #666;
            font-weight: 500;
            animation: slideUp 0.5s ease-out 0.2s forwards;
            opacity: 0;
        }

        .recent-activity {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            animation: fadeIn 0.5s ease-out forwards;
            opacity: 0;
        }

        .activity-list {
            margin-top: 20px;
        }

        .activity-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            animation: slideIn 0.5s ease-out forwards;
            opacity: 0;
            transform: translateX(-20px);
        }

        .activity-item:hover {
            background: #f8f9fa;
            transform: translateX(5px);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s ease;
        }

        .activity-item:hover .activity-icon {
            transform: scale(1.1) rotate(15deg);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        .logout-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }

        .logout-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .logout-btn i {
            transition: transform 0.3s ease;
        }

        .logout-btn:hover i {
            transform: translateX(3px);
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">
            <?php 
            require_once 'admin_nav.php';
            echo getAdminNav('index.php');
            ?>
        </div>
        <div class="main-content">
            <div class="header">
                <h1>Dashboard Overview</h1>
                <button class="logout-btn" onclick="window.location.href='../logout.php'">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--primary);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $members_count; ?></h3>
                        <p>Total Members</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--secondary);">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $trainers_count; ?></h3>
                        <p>Active Trainers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--warning);">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $classes_count; ?></h3>
                        <p>Today's Classes</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--danger);">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $queries_count; ?></h3>
                        <p>Pending Queries</p>
                    </div>
                </div>
            </div>
            <div class="recent-activity">
                <h2>Recent Activity</h2>
                <div class="activity-list">
                    <?php if ($recent_activities->num_rows > 0): ?>
                        <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="activity-icon" style="background: <?php 
                                    echo $activity['type'] === 'member' ? 'var(--primary)' : 
                                         ($activity['type'] === 'class' ? 'var(--secondary)' : 'var(--warning)'); 
                                ?>;">
                                    <i class="<?php 
                                        echo $activity['type'] === 'member' ? 'fas fa-user-plus' : 
                                             ($activity['type'] === 'class' ? 'fas fa-calendar-check' : 'fas fa-question-circle'); 
                                    ?>"></i>
                                </div>
                                <div>
                                    <h4><?php echo $activity['activity']; ?></h4>
                                    <p><?php echo htmlspecialchars($activity['full_name']); ?></p>
                                    <small><?php echo date('F j, Y g:i A', strtotime($activity['date'])); ?></small>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="activity-item">
                            <p>No recent activities</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add animation delay to stat cards
        document.querySelectorAll('.stat-card').forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
        });

        // Add animation delay to activity items
        document.querySelectorAll('.activity-item').forEach((item, index) => {
            item.style.animationDelay = `${index * 0.1}s`;
        });
    </script>
</body>
</html> 
<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a member
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: ../login.php');
    exit();
}

// Get member ID
$query = "SELECT member_id FROM members WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$member = $result->fetch_assoc();
$member_id = $member['member_id'];

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : 'upcoming';

// Build the query based on filters
$query = "SELECT cb.*, c.name as class_name, c.description, c.schedule_time, 
          t.specialization, u.full_name as trainer_name 
          FROM class_bookings cb 
          JOIN classes c ON cb.class_id = c.class_id 
          JOIN trainers t ON c.trainer_id = t.trainer_id 
          JOIN users u ON t.user_id = u.id 
          WHERE cb.member_id = ?";

if ($status_filter !== 'all') {
    $query .= " AND cb.status = ?";
}

if ($date_filter === 'upcoming') {
    $query .= " AND cb.booking_date >= CURDATE()";
} elseif ($date_filter === 'past') {
    $query .= " AND cb.booking_date < CURDATE()";
}

$query .= " ORDER BY cb.booking_date DESC, c.schedule_time ASC";

$stmt = $conn->prepare($query);

if ($status_filter !== 'all') {
    $stmt->bind_param("is", $member_id, $status_filter);
} else {
    $stmt->bind_param("i", $member_id);
}

$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Classes - FitZone</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/animations.css">
    <style>
        :root {
            --primary: #ff6b6b;
            --secondary: #4ecdc4;
            --dark: #2c3e50;
            --light: #f8f9fa;
            --success: #2ecc71;
            --warning: #f1c40f;
            --danger: #e74c3c;
            --gradient-1: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            --gradient-2: linear-gradient(135deg, #4ecdc4, #45b7ae);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f0f2f5;
            color: var(--dark);
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: white;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 20px 0;
            text-align: center;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            color: var(--primary);
            font-size: 1.5em;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--dark);
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 5px;
            transition: all 0.3s ease;
        }

        .nav-item:hover {
            background: #f0f2f5;
        }

        .nav-item.active {
            background: var(--primary);
            color: white;
        }

        .nav-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            flex: 1;
            padding: 20px;
        }

        .page-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            color: var(--dark);
            font-size: 1.5em;
        }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .filter-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
            color: var(--dark);
        }

        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .class-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .class-header {
            background: var(--primary);
            color: white;
            padding: 15px;
        }

        .class-header h3 {
            margin-bottom: 5px;
        }

        .class-time {
            font-size: 0.9em;
            opacity: 0.9;
        }

        .class-body {
            padding: 20px;
        }

        .class-info {
            margin-bottom: 15px;
        }

        .class-info p {
            margin-bottom: 8px;
            color: #666;
        }

        .class-info strong {
            color: var(--dark);
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 500;
        }

        .status-upcoming {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-completed {
            background: #e3f2fd;
            color: #1565c0;
        }

        .status-cancelled {
            background: #ffebee;
            color: #c62828;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .no-classes {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .no-classes i {
            font-size: 3em;
            color: #ddd;
            margin-bottom: 15px;
        }

        .no-classes p {
            color: #666;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                margin-bottom: 20px;
            }

            .page-header {
                flex-direction: column;
                gap: 15px;
            }

            .filters {
                flex-direction: column;
            }
        }

        /* Animation classes */
        .animate-fadeIn {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        .animate-slideIn {
            animation: slideIn 0.5s ease-in-out;
        }
        
        .animate-scaleIn {
            animation: scaleIn 0.3s ease-in-out;
        }
        
        .animate-bounce {
            animation: bounce 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes scaleIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        /* Apply animations to elements */
        .page-header {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        .filter-section {
            animation: slideIn 0.5s ease-in-out 0.2s backwards;
        }
        
        .class-card {
            animation: scaleIn 0.3s ease-in-out backwards;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .class-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary {
            transition: transform 0.2s ease, background-color 0.2s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            background-color: #0056b3;
        }
        
        .status-badge {
            transition: transform 0.2s ease;
        }
        
        .status-badge:hover {
            transform: scale(1.05);
        }
        
        .empty-state {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        .empty-state i {
            animation: bounce 1s ease-in-out infinite;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'member_nav.php'; ?>

        <div class="main-content">
            <div class="page-header animate-fadeIn">
                <h1 class="page-title">My Classes</h1>
                <a href="book_class.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Book New Class
                </a>
            </div>

            <div class="filters">
                <select class="filter-select" onchange="window.location.href='my_classes.php?status=' + this.value + '&date=<?php echo $date_filter; ?>'">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="upcoming" <?php echo $status_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>

                <select class="filter-select" onchange="window.location.href='my_classes.php?status=<?php echo $status_filter; ?>&date=' + this.value">
                    <option value="upcoming" <?php echo $date_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming Classes</option>
                    <option value="past" <?php echo $date_filter === 'past' ? 'selected' : ''; ?>>Past Classes</option>
                </select>
            </div>

            <?php if (empty($bookings)): ?>
                <div class="no-classes">
                    <i class="fas fa-calendar-times"></i>
                    <p>No classes found.</p>
                    <a href="book_class.php" class="btn btn-primary">Book a Class</a>
                </div>
            <?php else: ?>
                <div class="classes-grid">
                    <?php foreach ($bookings as $booking): ?>
                        <div class="class-card">
                            <div class="class-header">
                                <h3><?php echo htmlspecialchars($booking['class_name']); ?></h3>
                                <div class="class-time">
                                    <?php echo date('F j, Y g:i A', strtotime($booking['booking_date'] . ' ' . $booking['schedule_time'])); ?>
                                </div>
                            </div>
                            <div class="class-body">
                                <div class="class-info">
                                    <p><strong>Trainer:</strong> <?php echo htmlspecialchars($booking['trainer_name'] ?? ''); ?></p>
                                    <p><strong>Specialization:</strong> <?php echo htmlspecialchars($booking['specialization'] ?? ''); ?></p>
                                    <p><strong>Description:</strong> <?php echo htmlspecialchars($booking['description'] ?? ''); ?></p>
                                    <p>
                                        <strong>Status:</strong>
                                        <span class="status-badge status-<?php echo strtolower($booking['status'] ?? 'unknown'); ?>">
                                            <?php echo ucfirst($booking['status'] ?? 'unknown'); ?>
                                        </span>
                                    </p>
                                </div>
                                <?php if ($booking['status'] === 'upcoming'): ?>
                                    <div class="action-buttons">
                                        <a href="cancel_class.php?id=<?php echo $booking['booking_id']; ?>" 
                                           class="btn btn-danger"
                                           onclick="return confirm('Are you sure you want to cancel this class?')">
                                            <i class="fas fa-times"></i> Cancel Booking
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 
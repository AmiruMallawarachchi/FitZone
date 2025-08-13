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

// Get active membership
$query = "SELECT ms.*, m.name as membership_name, m.price, m.duration_months 
          FROM member_subscriptions ms 
          JOIN memberships m ON ms.membership_id = m.membership_id 
          WHERE ms.member_id = ? AND ms.status = 'active' 
          ORDER BY ms.end_date DESC 
          LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$active_membership = $stmt->get_result()->fetch_assoc();

// Get recent appointments
$query = "SELECT a.*, t.specialization, u.full_name as trainer_name 
          FROM appointments a 
          JOIN trainers t ON a.trainer_id = t.trainer_id
          JOIN users u ON t.user_id = u.id
          WHERE a.member_id = ? 
          ORDER BY a.appointment_date DESC 
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$recent_appointments = $stmt->get_result();

// Get upcoming classes
$query = "SELECT c.*, t.specialization, u.full_name as trainer_name 
          FROM classes c 
          JOIN trainers t ON c.trainer_id = t.trainer_id
          JOIN users u ON t.user_id = u.id
          WHERE c.status = 'active' 
          AND c.schedule_time > NOW() 
          ORDER BY c.schedule_time ASC 
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute();
$upcoming_classes = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        @keyframes slideIn {
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
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
            animation: slideIn 0.5s ease-out;
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
            position: relative;
            overflow: hidden;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 0;
            background: var(--gradient-1);
            transition: width 0.3s ease;
            z-index: -1;
        }

        .nav-item:hover::before {
            width: 100%;
        }

        .nav-item:hover {
            color: white;
            transform: translateX(5px);
        }

        .nav-item.active {
            background: var(--gradient-1);
            color: white;
            transform: translateX(5px);
        }

        .nav-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        .page-header {
            margin-bottom: 30px;
            animation: fadeIn 0.5s ease-out;
        }

        .page-title {
            font-size: 1.8em;
            color: var(--dark);
            margin-bottom: 10px;
            position: relative;
            display: inline-block;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--gradient-1);
            animation: pulse 2s infinite;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
        }

        .dashboard-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            min-height: 200px;
            animation: fadeIn 0.5s ease-out;
            position: relative;
            overflow: hidden;
        }

        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--gradient-1);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .dashboard-card:hover::before {
            transform: scaleX(1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .card-title {
            font-size: 1.2em;
            color: var(--dark);
            margin: 0;
        }

        .card-icon {
            width: 40px;
            height: 40px;
            background: var(--gradient-1);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
            animation: float 3s ease-in-out infinite;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .card-icon:hover {
            transform: scale(1.1);
            box-shadow: 0 0 15px rgba(255,107,107,0.3);
        }

        .membership-info {
            background: var(--gradient-1);
            color: white;
            padding: 20px;
            border-radius: 10px;
            transform: translateY(0);
            transition: transform 0.3s ease;
        }

        .membership-info:hover {
            transform: translateY(-5px);
        }

        .membership-name {
            font-size: 1.2em;
            margin-bottom: 10px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }

        .membership-details p {
            margin-bottom: 10px;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .membership-details i {
            width: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .membership-details i:hover {
            transform: rotate(-15deg);
            color: var(--secondary);
        }

        .membership-actions {
            margin-top: 15px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.1);
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.3s ease;
        }

        .btn:hover::before {
            transform: scaleX(1);
            transform-origin: left;
        }

        .btn-primary {
            background: var(--gradient-1);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255,107,107,0.3);
        }

        .btn-sm {
            padding: 8px 15px;
            font-size: 0.9em;
        }

        .appointment-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .appointment-item {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 10px;
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.1);
        }

        .appointment-item:hover {
            transform: translateX(5px);
            background: rgba(78, 205, 196, 0.1);
            border-color: var(--secondary);
        }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .appointment-title {
            font-size: 1.1em;
            margin: 0;
            color: var(--dark);
        }

        .appointment-date {
            font-size: 0.9em;
            color: var(--primary);
            font-weight: 600;
        }

        .appointment-info p {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--dark);
        }

        .appointment-info i {
            width: 20px;
            color: var(--primary);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .appointment-info i:hover {
            transform: rotate(15deg);
            color: var(--secondary);
        }

        .card-actions {
            margin-top: 15px;
            text-align: right;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .action-button {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: var(--gradient-1);
            color: white;
            text-decoration: none;
            border-radius: 15px;
            text-align: center;
            transition: all 0.3s ease;
            min-height: 120px;
            position: relative;
            overflow: hidden;
        }

        .action-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.1);
            transform: scale(0);
            border-radius: 50%;
            transition: transform 0.5s ease;
        }

        .action-button:hover::before {
            transform: scale(2);
        }

        .action-button:nth-child(even) {
            background: var(--gradient-2);
        }

        .action-button:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .action-button i {
            font-size: 24px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .action-button i:hover {
            transform: scale(1.2) rotate(10deg);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .stat-item {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.2);
            min-height: 100px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
            background: rgba(255,255,255,0.2);
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 5px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }

        .stat-label {
            font-size: 14px;
            color: var(--dark);
            font-weight: 500;
        }

        .gym-info {
            padding: 20px;
            background: var(--gradient-2);
            color: white;
            border-radius: 15px;
            transition: all 0.3s ease;
        }

        .gym-info:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .gym-info h3 {
            margin-bottom: 15px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }

        .gym-info p {
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            opacity: 0.9;
        }

        .gym-info i {
            width: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .gym-info i:hover {
            transform: rotate(15deg);
            color: var(--primary);
        }

        .badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            animation: pulse 2s infinite;
        }

        .badge-success {
            background: var(--success);
            color: white;
        }

        .no-data {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }

        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                margin-bottom: 20px;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }
        }

        .icon-rotate {
            animation: rotate 0.5s ease-out;
        }

        .icon-bounce {
            animation: bounce 0.5s ease-out;
        }

        .appointment-actions {
            margin-top: 1rem;
            display: flex;
            justify-content: flex-end;
        }

        .appointment-actions .btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 0.5rem;
            color: white;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .appointment-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .appointment-actions .btn i {
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'member_nav.php'; ?>
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Welcome to Your Dashboard</h1>
            </div>

            <div class="dashboard-grid">
                <!-- Membership Status -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2 class="card-title">Membership Status</h2>
                        <div class="card-icon">
                            <i class="fas fa-id-card"></i>
                        </div>
                    </div>
                    <?php if ($active_membership): ?>
                        <div class="membership-info">
                            <h3 class="membership-name"><?php echo htmlspecialchars($active_membership['membership_name']); ?></h3>
                            <div class="membership-details">
                                <p><i class="fas fa-calendar-check"></i> Valid until: <?php echo date('F d, Y', strtotime($active_membership['end_date'])); ?></p>
                                <p><i class="fas fa-clock"></i> Duration: <?php echo $active_membership['duration_months']; ?> months</p>
                                <p><i class="fas fa-tag"></i> Price: $<?php echo number_format($active_membership['price'], 2); ?></p>
                            </div>
                            <div class="membership-actions">
                                <a href="membership.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-sync"></i> Renew Membership
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="membership-info">
                            <p class="no-membership">No active membership found.</p>
                            <div class="membership-actions">
                                <a href="membership.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> Get Membership
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Appointments -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2 class="card-title">Recent Appointments</h2>
                        <div class="card-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <?php if ($recent_appointments->num_rows > 0): ?>
                        <div class="appointment-list">
                            <?php while ($appointment = $recent_appointments->fetch_assoc()): ?>
                                <div class="appointment-item">
                                    <div class="appointment-header">
                                        <h3 class="appointment-title"><?php echo htmlspecialchars($appointment['purpose'] ?? 'Training Session'); ?></h3>
                                        <span class="appointment-date">
                                            <?php echo date('M j, Y', strtotime($appointment['appointment_date'] ?? 'now')); ?>
                                            <?php 
                                            $appointment_time = $appointment['appointment_time'] ?? '12:00:00';
                                            echo date('g:i A', strtotime($appointment_time)); 
                                            ?>
                                        </span>
                                    </div>
                                    <div class="appointment-info">
                                        <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($appointment['trainer_name'] ?? 'Not specified'); ?></p>
                                        <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($appointment['specialization'] ?? 'Not specified'); ?></p>
                                        <p><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($appointment['status'] ?? 'Pending'); ?></p>
                                        <div class="appointment-actions">
                                            <a href="my_appointments.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <div class="card-actions">
                            <a href="my_appointments.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-list"></i> View All Appointments
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="no-data">No recent appointments found.</p>
                        <div class="card-actions">
                            <a href="book_appointment.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Book Appointment
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2 class="card-title">Quick Actions</h2>
                        <div class="card-icon">
                            <i class="fas fa-bolt"></i>
                        </div>
                    </div>
                    <div class="quick-actions">
                        <a href="book_appointment.php" class="action-button">
                            <i class="fas fa-calendar-plus"></i>
                            <span>Book Appointment</span>
                        </a>
                        <a href="my_appointments.php" class="action-button">
                            <i class="fas fa-calendar-check"></i>
                            <span>My Appointments</span>
                        </a>
                        <a href="membership.php" class="action-button">
                            <i class="fas fa-id-card"></i>
                            <span>Membership</span>
                        </a>
                        <a href="profile.php" class="action-button">
                            <i class="fas fa-user-cog"></i>
                            <span>Profile Settings</span>
                        </a>
                    </div>
                </div>

                <!-- Gym Stats -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2 class="card-title">Your Fitness Journey</h2>
                        <div class="card-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-value">12</div>
                            <div class="stat-label">Workouts</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">5</div>
                            <div class="stat-label">Classes</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">3</div>
                            <div class="stat-label">Trainers</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">8</div>
                            <div class="stat-label">Hours</div>
                        </div>
                    </div>
                </div>

                <!-- Gym Information -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2 class="card-title">Gym Information</h2>
                        <div class="card-icon">
                            <i class="fas fa-dumbbell"></i>
                        </div>
                    </div>
                    <div class="gym-info">
                        <h3>FitZone Gym</h3>
                        <p><i class="fas fa-clock"></i> Open Hours: 6:00 AM - 10:00 PM</p>
                        <p><i class="fas fa-phone"></i> Contact: (123) 456-7890</p>
                        <p><i class="fas fa-envelope"></i> Email: info@fitzone.com</p>
                        <p><i class="fas fa-map-marker-alt"></i> Location: 123 Fitness Street</p>
                                    </div>
                                    </div>

                <!-- Fitness Tips -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2 class="card-title">Today's Fitness Tip</h2>
                        <div class="card-icon">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                    </div>
                    <div class="gym-info" style="background: var(--gradient-1);">
                        <h3>Stay Hydrated!</h3>
                        <p><i class="fas fa-tint"></i> Drink at least 8 glasses of water daily</p>
                        <p><i class="fas fa-apple-alt"></i> Eat a balanced diet</p>
                        <p><i class="fas fa-bed"></i> Get 7-8 hours of sleep</p>
                        <p><i class="fas fa-running"></i> Exercise regularly</p>
                    </div>
                </div>

                <!-- Upcoming Classes -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2 class="card-title">Upcoming Classes</h2>
                        <div class="card-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    <?php if ($upcoming_classes->num_rows > 0): ?>
                        <div class="appointment-list">
                            <?php while ($class = $upcoming_classes->fetch_assoc()): ?>
                                <div class="appointment-item">
                                    <div class="appointment-header">
                                        <h3 class="appointment-title"><?php echo htmlspecialchars($class['name']); ?></h3>
                                        <span class="appointment-date">
                                            <?php echo date('M j, Y', strtotime($class['schedule_time'])); ?>
                                            <?php echo date('g:i A', strtotime($class['schedule_time'])); ?>
                                        </span>
                                    </div>
                                    <div class="appointment-info">
                                        <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($class['trainer_name']); ?></p>
                                        <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($class['specialization']); ?></p>
                                        <p><i class="fas fa-clock"></i> Duration: <?php echo $class['duration_minutes']; ?> minutes</p>
                                        <p><i class="fas fa-users"></i> Capacity: <?php echo $class['capacity']; ?> spots</p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <div class="card-actions">
                            <a href="book_class.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Book a Class
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="no-data">No upcoming classes available.</p>
                        <div class="card-actions">
                            <a href="book_class.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Book a Class
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Add hover animations to cards
        document.querySelectorAll('.dashboard-card').forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
            });
        });

        // Add hover and click effects to action buttons
        document.querySelectorAll('.action-button').forEach(button => {
            const icon = button.querySelector('i');
            
            button.addEventListener('mouseenter', () => {
                icon.style.transform = 'scale(1.2)';
            });
            
            button.addEventListener('mouseleave', () => {
                icon.style.transform = 'scale(1)';
            });

            icon.addEventListener('click', (e) => {
                e.preventDefault();
                icon.classList.add('icon-rotate');
                setTimeout(() => {
                    icon.classList.remove('icon-rotate');
                }, 500);
            });
        });

        // Add hover and click effects to stats
        document.querySelectorAll('.stat-item').forEach(stat => {
            stat.addEventListener('mouseenter', () => {
                stat.style.transform = 'translateY(-5px)';
            });
            
            stat.addEventListener('mouseleave', () => {
                stat.style.transform = 'translateY(0)';
            });
        });

        // Add click effects to all icons
        document.querySelectorAll('i').forEach(icon => {
            icon.addEventListener('click', function(e) {
                e.preventDefault();
                this.classList.add('icon-rotate');
                setTimeout(() => {
                    this.classList.remove('icon-rotate');
                }, 500);
            });
        });

        // Add hover effects to card icons
        document.querySelectorAll('.card-icon').forEach(icon => {
            icon.addEventListener('click', function(e) {
                e.preventDefault();
                this.classList.add('icon-bounce');
                setTimeout(() => {
                    this.classList.remove('icon-bounce');
                }, 500);
            });
        });
    </script>
</body>
</html> 
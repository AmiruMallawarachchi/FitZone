<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a trainer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'trainer') {
    header("Location: ../login.php");
    exit();
}

// Get trainer ID from the trainers table
$query = "SELECT trainer_id FROM trainers WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$trainer_data = $result->fetch_assoc();
$trainer_id = $trainer_data['trainer_id'];

// Fetch trainer's information
$stmt = $conn->prepare("SELECT t.*, u.full_name, u.email FROM trainers t 
                       JOIN users u ON t.user_id = u.id 
                       WHERE t.trainer_id = ?");
$stmt->bind_param("i", $trainer_id);
$stmt->execute();
$trainer = $stmt->get_result()->fetch_assoc();

// Fetch upcoming appointments
$stmt = $conn->prepare("SELECT a.*, u.full_name as member_name 
          FROM appointments a 
          JOIN members m ON a.member_id = m.member_id 
          JOIN users u ON m.user_id = u.id
                       WHERE a.trainer_id = ? AND a.appointment_date >= CURDATE() 
                       ORDER BY a.appointment_date ASC 
                       LIMIT 5");
$stmt->bind_param("i", $trainer_id);
$stmt->execute();
$upcoming_appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch recent classes
$stmt = $conn->prepare("SELECT c.*, COUNT(cb.booking_id) as total_bookings 
          FROM classes c 
                       LEFT JOIN class_bookings cb ON c.class_id = cb.class_id 
                       WHERE c.trainer_id = ? 
                       GROUP BY c.class_id 
                       ORDER BY c.schedule_time DESC 
                       LIMIT 5");
$stmt->bind_param("i", $trainer_id);
$stmt->execute();
$recent_classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total_appointments FROM appointments WHERE trainer_id = ?");
$stmt->bind_param("i", $trainer_id);
$stmt->execute();
$total_appointments = $stmt->get_result()->fetch_assoc()['total_appointments'];

$stmt = $conn->prepare("SELECT COUNT(*) as total_classes FROM classes WHERE trainer_id = ?");
$stmt->bind_param("i", $trainer_id);
$stmt->execute();
$total_classes = $stmt->get_result()->fetch_assoc()['total_classes'];

$stmt = $conn->prepare("SELECT COUNT(DISTINCT member_id) as total_members FROM appointments WHERE trainer_id = ?");
$stmt->bind_param("i", $trainer_id);
$stmt->execute();
$total_members = $stmt->get_result()->fetch_assoc()['total_members'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trainer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="trainer_styles.css">
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Trainer Dashboard</h2>
            </div>
            <a href="index.php" class="nav-item active">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="manage_appointments.php" class="nav-item">
                <i class="fas fa-calendar-check"></i> Appointments
            </a>
            <a href="manage_classes.php" class="nav-item">
                <i class="fas fa-dumbbell"></i> Classes
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="../logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Welcome, <?php echo htmlspecialchars($trainer['full_name']); ?></h1>
                <div class="header-actions">
                    <a href="profile.php" class="btn btn-primary">
                        <i class="fas fa-user-edit"></i> Edit Profile
                    </a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="card">
                    <div class="card-header">
                    <h3 class="card-title">Statistics</h3>
                </div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-calendar-check card-icon"></i>
                        <div class="stat-info">
                            <h4>Total Appointments</h4>
                            <p><?php echo $total_appointments; ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-dumbbell card-icon"></i>
                        <div class="stat-info">
                            <h4>Total Classes</h4>
                            <p><?php echo $total_classes; ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-users card-icon"></i>
                        <div class="stat-info">
                            <h4>Total Members</h4>
                            <p><?php echo $total_members; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Appointments -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Upcoming Appointments</h3>
                    <a href="manage_appointments.php" class="btn btn-primary">View All</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_appointments as $appointment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($appointment['member_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($appointment['appointment_date'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                                        <?php echo $appointment['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                                </div>
                            </div>

            <!-- Recent Classes -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Classes</h3>
                    <a href="manage_classes.php" class="btn btn-primary">View All</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Class Name</th>
                                <th>Schedule</th>
                                <th>Capacity</th>
                                <th>Bookings</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_classes as $class): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($class['name']); ?></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($class['schedule_time'])); ?></td>
                                <td><?php echo $class['capacity']; ?></td>
                                <td><?php echo $class['total_bookings']; ?></td>
                                <td>
                                    <a href="view_class.php?id=<?php echo $class['class_id']; ?>" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
            </div>
        </div>
    </div>

    <script>
        // Add any JavaScript functionality here
    </script>
</body>
</html> 
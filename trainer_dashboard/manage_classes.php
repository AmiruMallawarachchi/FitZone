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

// Fetch all classes with booking counts
$stmt = $conn->prepare("SELECT c.*, COUNT(cb.booking_id) as total_bookings 
                       FROM classes c 
                       LEFT JOIN class_bookings cb ON c.class_id = cb.class_id 
                       WHERE c.trainer_id = ? 
                       GROUP BY c.class_id 
                       ORDER BY c.schedule_time DESC");
$stmt->bind_param("i", $trainer_id);
$stmt->execute();
$classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle class status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['class_id']) && isset($_POST['status'])) {
    $class_id = $_POST['class_id'];
    $status = $_POST['status'];
    
    $update_stmt = $conn->prepare("UPDATE classes SET status = ? WHERE class_id = ? AND trainer_id = ?");
    $update_stmt->bind_param("sii", $status, $class_id, $trainer_id);
        
    if ($update_stmt->execute()) {
            $_SESSION['success'] = "Class status updated successfully";
    } else {
        $_SESSION['error'] = "Failed to update class status";
    }
    
    header("Location: manage_classes.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes - Trainer Dashboard</title>
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
            <a href="index.php" class="nav-item">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="manage_appointments.php" class="nav-item">
                <i class="fas fa-calendar-check"></i> Appointments
            </a>
            <a href="manage_classes.php" class="nav-item active">
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
                <h1 class="page-title">Manage Classes</h1>
                <div class="header-actions">
                    <a href="add_class.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Class
                    </a>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Classes Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">All Classes</h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Class Name</th>
                                <th>Schedule</th>
                                <th>Duration</th>
                                <th>Capacity</th>
                                <th>Bookings</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $class): ?>
                            <tr>
                                <td>
                                    <div class="class-info">
                                        <span class="class-name"><?php echo htmlspecialchars($class['name']); ?></span>
                                        <span class="class-description"><?php echo htmlspecialchars($class['description']); ?></span>
            </div>
                                </td>
                                <td><?php echo date('M d, Y h:i A', strtotime($class['schedule_time'])); ?></td>
                                <td><?php echo $class['duration_minutes']; ?> minutes</td>
                                <td><?php echo $class['capacity']; ?></td>
                                <td><?php echo $class['total_bookings']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($class['status']); ?>">
                                        <?php echo $class['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <form method="POST" class="status-form">
                                        <input type="hidden" name="class_id" value="<?php echo $class['class_id']; ?>">
                                            <select name="status" onchange="this.form.submit()" class="status-select">
                                                <option value="scheduled" <?php echo $class['status'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                <option value="in_progress" <?php echo $class['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                <option value="completed" <?php echo $class['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo $class['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                    </form>
                                        <a href="view_class.php?id=<?php echo $class['class_id']; ?>" 
                                           class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_class.php?id=<?php echo $class['class_id']; ?>" 
                                           class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                            </div>
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
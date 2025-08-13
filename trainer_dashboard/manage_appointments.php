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

// Fetch all appointments with member details
$stmt = $conn->prepare("SELECT a.*, u.full_name as member_name, u.email as member_email 
                       FROM appointments a 
                       JOIN members m ON a.member_id = m.member_id 
                       JOIN users u ON m.user_id = u.id
                       WHERE a.trainer_id = ? 
                       ORDER BY a.appointment_date DESC");
$stmt->bind_param("i", $trainer_id);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle appointment status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id']) && isset($_POST['status'])) {
    $appointment_id = $_POST['appointment_id'];
    $status = $_POST['status'];
    
    $update_stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ? AND trainer_id = ?");
    $update_stmt->bind_param("sii", $status, $appointment_id, $trainer_id);
        
    if ($update_stmt->execute()) {
            $_SESSION['success'] = "Appointment status updated successfully";
    } else {
        $_SESSION['error'] = "Failed to update appointment status";
    }
    
    header("Location: manage_appointments.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - Trainer Dashboard</title>
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
            <a href="manage_appointments.php" class="nav-item active">
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
                <h1 class="page-title">Manage Appointments</h1>
                <div class="header-actions">
                    <a href="add_appointment.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Appointment
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

            <!-- Appointments Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">All Appointments</h3>
            </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td>
                                    <div class="user-info">
                                        <span class="user-name"><?php echo htmlspecialchars($appointment['member_name']); ?></span>
                                        <span class="user-email"><?php echo htmlspecialchars($appointment['member_email']); ?></span>
                                    </div>
                                    </td>
                                <td><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($appointment['appointment_date'])); ?></td>
                                <td><?php echo $appointment['duration_minutes']; ?> minutes</td>
                                    <td>
                                    <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                                        <?php echo $appointment['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                    <div class="action-buttons">
                                        <form method="POST" class="status-form">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                            <select name="status" onchange="this.form.submit()" class="status-select">
                                                <option value="pending" <?php echo $appointment['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="confirmed" <?php echo $appointment['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                <option value="completed" <?php echo $appointment['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo $appointment['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                            </form>
                                        <a href="view_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" 
                                           class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i>
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
<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a trainer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'trainer') {
    header('Location: ../login.php');
    exit();
}

// Get trainer ID
$query = "SELECT trainer_id FROM trainers WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$trainer = $result->fetch_assoc();
$trainer_id = $trainer['trainer_id'];

// Handle booking status update
if (isset($_POST['update_status'])) {
    $booking_id = (int)$_POST['booking_id'];
    $new_status = $_POST['new_status'];
    
    $update_query = "UPDATE class_bookings SET status = ? WHERE booking_id = ? AND class_id IN (SELECT class_id FROM classes WHERE trainer_id = ?)";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sii", $new_status, $booking_id, $trainer_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Booking status updated successfully.";
    } else {
        $_SESSION['error'] = "Error updating booking status.";
    }
    
    header('Location: manage_class_bookings.php');
    exit();
}

// Handle booking deletion
if (isset($_POST['delete_booking'])) {
    $booking_id = (int)$_POST['booking_id'];
    
    $delete_query = "DELETE FROM class_bookings WHERE booking_id = ? AND class_id IN (SELECT class_id FROM classes WHERE trainer_id = ?)";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("ii", $booking_id, $trainer_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Booking deleted successfully.";
    } else {
        $_SESSION['error'] = "Error deleting booking.";
    }
    
    header('Location: manage_class_bookings.php');
    exit();
}

// Get all bookings for trainer's classes
$query = "SELECT cb.*, c.name as class_name, c.schedule_time, c.duration_minutes,
          m.member_id, u.full_name as member_name, u.email as member_email
          FROM class_bookings cb
          JOIN classes c ON cb.class_id = c.class_id
          JOIN members m ON cb.member_id = m.member_id
          JOIN users u ON m.user_id = u.id
          WHERE c.trainer_id = ?
          ORDER BY c.schedule_time DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $trainer_id);
$stmt->execute();
$bookings = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Class Bookings - Trainer Dashboard</title>
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
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 1.8em;
            color: var(--dark);
        }

        .bookings-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .bookings-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .bookings-table th,
        .bookings-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .bookings-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .bookings-table tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 600;
        }

        .status-booked {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .status-completed {
            background: #e2e3e5;
            color: #383d41;
        }

        .btn {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            color: white;
        }

        .btn-danger {
            background: var(--danger);
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                margin-bottom: 20px;
            }

            .bookings-table {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Trainer Dashboard</h2>
            </div>
            <a href="index.php" class="nav-item">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
            <a href="manage_appointments.php" class="nav-item">
                <i class="fas fa-calendar-check"></i>
                Appointments
            </a>
            <a href="manage_class_bookings.php" class="nav-item active">
                <i class="fas fa-users"></i>
                Class Bookings
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user"></i>
                Profile
            </a>
            <a href="../logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Manage Class Bookings</h1>
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
                <div class="alert alert-error">
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="bookings-table">
                <table>
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Member</th>
                            <th>Schedule</th>
                            <th>Duration</th>
                            <th>Booking Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($booking = $bookings->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['class_name']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($booking['member_name']); ?><br>
                                    <small><?php echo htmlspecialchars($booking['member_email']); ?></small>
                                </td>
                                <td><?php echo date('F j, Y g:i A', strtotime($booking['schedule_time'])); ?></td>
                                <td><?php echo $booking['duration_minutes']; ?> minutes</td>
                                <td><?php echo date('F j, Y', strtotime($booking['booking_date'])); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                        <select name="new_status" onchange="this.form.submit()" class="form-select">
                                            <option value="booked" <?php echo $booking['status'] == 'booked' ? 'selected' : ''; ?>>Booked</option>
                                            <option value="cancelled" <?php echo $booking['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            <option value="completed" <?php echo $booking['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this booking?');">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                        <button type="submit" name="delete_booking" class="btn btn-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html> 
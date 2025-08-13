<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a trainer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trainer') {
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

// Get class ID from URL
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

// Verify the class belongs to this trainer
$query = "SELECT * FROM classes WHERE class_id = ? AND trainer_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $class_id, $trainer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Unauthorized access";
    header('Location: manage_classes.php');
    exit();
}

$class = $result->fetch_assoc();

// Handle booking status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $booking_id = (int)$_POST['booking_id'];
    $status = $_POST['status'];
    
    // Verify the booking belongs to this class
    $query = "SELECT booking_id FROM class_bookings WHERE booking_id = ? AND class_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $booking_id, $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $query = "UPDATE class_bookings SET status = ? WHERE booking_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $status, $booking_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Booking status updated successfully";
        } else {
            $_SESSION['error'] = "Error updating booking status";
        }
    } else {
        $_SESSION['error'] = "Unauthorized action";
    }
    
    header('Location: view_bookings.php?class_id=' . $class_id);
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build the query based on filters
$query = "SELECT cb.*, m.first_name, m.last_name, m.email, m.phone 
          FROM class_bookings cb 
          JOIN members m ON cb.member_id = m.member_id 
          WHERE cb.class_id = ?";
$params = [$class_id];
$types = "i";

if ($status_filter) {
    $query .= " AND cb.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$query .= " ORDER BY cb.booking_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$bookings = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bookings - Trainer Dashboard</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 1.8em;
            color: var(--dark);
        }

        .class-info {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .class-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .class-title {
            font-size: 1.5em;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .class-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 600;
        }

        .status-active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-cancelled {
            background: #ffebee;
            color: #c62828;
        }

        .status-completed {
            background: #e3f2fd;
            color: #1565c0;
        }

        .class-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            color: #666;
        }

        .detail-item i {
            width: 20px;
            margin-right: 10px;
            color: var(--primary);
        }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-label {
            font-weight: 600;
            color: var(--dark);
        }

        .filter-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9em;
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
            font-size: 0.9em;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #ff5252;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #27ae60;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: #f39c12;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .bookings-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .bookings-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .bookings-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
        }

        .bookings-table td {
            padding: 15px;
            border-top: 1px solid #eee;
        }

        .bookings-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 600;
        }

        .status-confirmed {
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

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
        }

        .no-bookings {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .no-bookings i {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .no-bookings h3 {
            font-size: 1.5em;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .no-bookings p {
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
                align-items: flex-start;
                gap: 10px;
            }

            .class-details {
                grid-template-columns: 1fr;
            }

            .filters {
                flex-direction: column;
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
                Manage Appointments
            </a>
            <a href="manage_classes.php" class="nav-item active">
                <i class="fas fa-chalkboard-teacher"></i>
                Manage Classes
            </a>
            <a href="../logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">View Bookings</h1>
                <a href="manage_classes.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Classes
                </a>
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

            <div class="class-info">
                <div class="class-header">
                    <div>
                        <h2 class="class-title"><?php echo htmlspecialchars($class['class_name']); ?></h2>
                        <span class="class-status status-<?php echo $class['status']; ?>">
                            <?php echo ucfirst($class['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="class-details">
                    <div class="detail-item">
                        <i class="fas fa-info-circle"></i>
                        <?php echo htmlspecialchars($class['description']); ?>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-calendar"></i>
                        <?php echo date('F j, Y', strtotime($class['schedule_date'])); ?>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-clock"></i>
                        <?php echo date('g:i A', strtotime($class['schedule_time'])); ?>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-hourglass-half"></i>
                        <?php echo $class['duration']; ?> minutes
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-users"></i>
                        Capacity: <?php echo $class['capacity']; ?> students
                    </div>
                </div>
            </div>

            <div class="filters">
                <form method="GET" action="" class="filter-group">
                    <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                    <label class="filter-label">Status:</label>
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="">All</option>
                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </form>
            </div>

            <div class="bookings-table">
                <?php if ($bookings->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Contact</th>
                                <th>Booking Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($booking = $bookings->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($booking['email']); ?></div>
                                        <div><?php echo htmlspecialchars($booking['phone']); ?></div>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $booking['status']; ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($booking['status'] === 'confirmed'): ?>
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                                <input type="hidden" name="status" value="completed">
                                                <button type="submit" name="update_status" class="btn btn-success">
                                                    <i class="fas fa-check"></i> Complete
                                                </button>
                                            </form>
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                                <input type="hidden" name="status" value="cancelled">
                                                <button type="submit" name="update_status" class="btn btn-danger">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-bookings">
                        <i class="fas fa-users-slash"></i>
                        <h3>No Bookings Found</h3>
                        <p>There are no bookings matching your current filters.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 
<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle appointment status updates
if (isset($_POST['update_status'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    $new_status = $_POST['new_status'];
    
    $query = "UPDATE appointments SET status = ? WHERE appointment_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $new_status, $appointment_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Appointment status updated successfully";
    } else {
        $_SESSION['error'] = "Error updating appointment status";
    }
    
    header('Location: manage_appointments.php');
    exit();
}

// Handle appointment deletion
if (isset($_POST['delete_appointment'])) {
    $appointment_id = (int)$_POST['appointment_id'];
    
    $query = "DELETE FROM appointments WHERE appointment_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $appointment_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Appointment deleted successfully";
    } else {
        $_SESSION['error'] = "Error deleting appointment";
    }
    
    header('Location: manage_appointments.php');
    exit();
}

// Fetch all appointments with member and trainer details
$query = "SELECT a.*, 
          m_u.full_name as member_name, m_u.email as member_email,
          t_u.full_name as trainer_name, t_u.email as trainer_email
          FROM appointments a 
          JOIN members m ON a.member_id = m.member_id 
          JOIN users m_u ON m.user_id = m_u.id
          JOIN trainers t ON a.trainer_id = t.trainer_id 
          JOIN users t_u ON t.user_id = t_u.id
          ORDER BY a.appointment_date DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - Admin Dashboard</title>
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

        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #ff5252;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .appointments-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .appointments-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .appointments-table th,
        .appointments-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .appointments-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
        }

        .appointments-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 600;
        }

        .status-scheduled {
            background: #e3f2fd;
            color: #1976d2;
        }

        .status-completed {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-cancelled {
            background: #ffebee;
            color: #c62828;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
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

        .no-appointments {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .no-appointments i {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .no-appointments h3 {
            font-size: 1.5em;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .no-appointments p {
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

            .appointments-table {
                overflow-x: auto;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Admin Dashboard</h2>
            </div>
            <a href="index.php" class="nav-item">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
            <a href="manage_members.php" class="nav-item">
                <i class="fas fa-users"></i>
                Manage Members
            </a>
            <a href="manage_trainers.php" class="nav-item">
                <i class="fas fa-dumbbell"></i>
                Manage Trainers
            </a>
            <a href="manage_membership.php" class="nav-item">
                <i class="fas fa-id-card"></i>
                Manage Memberships
            </a>
            <a href="manage_appointments.php" class="nav-item active">
                <i class="fas fa-calendar-check"></i>
                Manage Appointments
            </a>
            <a href="../logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Manage Appointments</h1>
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

            <div class="search-bar">
                <input type="text" class="search-input" id="searchInput" placeholder="Search appointments...">
            </div>

            <div class="appointments-table">
                <?php if ($result && $result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Member</th>
                                <th>Trainer</th>
                                <th>Session Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($appointment = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php 
                                        echo date('F j, Y', strtotime($appointment['appointment_date'])) . '<br>';
                                        echo date('g:i A', strtotime($appointment['appointment_date']));
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        echo htmlspecialchars($appointment['member_name']) . '<br>';
                                        echo htmlspecialchars($appointment['member_email']);
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        echo htmlspecialchars($appointment['trainer_name']) . '<br>';
                                        echo htmlspecialchars($appointment['trainer_email']);
                                        ?>
                                    </td>
                                    <td><?php echo isset($appointment['session_type']) ? htmlspecialchars($appointment['session_type']) : 'N/A'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($appointment['status'] === 'scheduled'): ?>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                                    <select name="new_status" onchange="this.form.submit()" style="padding: 5px; margin-right: 5px;">
                                                        <option value="">Update Status</option>
                                                        <option value="completed">Mark as Completed</option>
                                                        <option value="cancelled">Cancel Appointment</option>
                                                    </select>
                                                    <input type="hidden" name="update_status" value="1">
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this appointment?');">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                                <button type="submit" name="delete_appointment" class="btn btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-appointments">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Appointments Found</h3>
                        <p>There are no appointments in the system.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const rows = document.querySelectorAll('.appointments-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
    </script>
</body>
</html> 
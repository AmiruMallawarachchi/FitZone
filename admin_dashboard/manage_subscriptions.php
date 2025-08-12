<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle subscription status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $subscription_id = (int)$_POST['subscription_id'];
    $status = $_POST['status'];
    
    $query = "UPDATE subscriptions SET status = ? WHERE subscription_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $status, $subscription_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Subscription status updated successfully";
    } else {
        $_SESSION['error'] = "Error updating subscription status";
    }
    
    header('Location: manage_subscriptions.php');
    exit();
}

// Handle subscription deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_subscription'])) {
    $subscription_id = (int)$_POST['subscription_id'];
    
    $query = "DELETE FROM subscriptions WHERE subscription_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $subscription_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Subscription deleted successfully";
    } else {
        $_SESSION['error'] = "Error deleting subscription";
    }
    
    header('Location: manage_subscriptions.php');
    exit();
}

// Fetch all subscriptions with member details
$query = "SELECT s.*, m.first_name, m.last_name, m.email, m.phone 
          FROM subscriptions s 
          JOIN members m ON s.member_id = m.member_id 
          ORDER BY s.start_date DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subscriptions - Admin Dashboard</title>
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

        .subscriptions-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .subscriptions-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .subscriptions-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
        }

        .subscriptions-table td {
            padding: 15px;
            border-top: 1px solid #eee;
        }

        .subscriptions-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 600;
        }

        .status-active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-expired {
            background: #ffebee;
            color: #c62828;
        }

        .status-pending {
            background: #fff3e0;
            color: #ef6c00;
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

        .no-subscriptions {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .no-subscriptions i {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .no-subscriptions h3 {
            font-size: 1.5em;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .no-subscriptions p {
            color: #666;
            margin-bottom: 20px;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
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

            .subscriptions-table {
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
                <i class="fas fa-user-tie"></i>
                Manage Trainers
            </a>
            <a href="manage_classes.php" class="nav-item">
                <i class="fas fa-chalkboard-teacher"></i>
                Manage Classes
            </a>
            <a href="manage_subscriptions.php" class="nav-item active">
                <i class="fas fa-id-card"></i>
                Manage Subscriptions
            </a>
            <a href="manage_appointments.php" class="nav-item">
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
                <h1 class="page-title">Manage Subscriptions</h1>
                <a href="add_subscription.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Subscription
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

            <div class="search-bar">
                <input type="text" class="search-input" id="searchInput" placeholder="Search subscriptions...">
            </div>

            <div class="subscriptions-table">
                <?php if ($result && $result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Contact</th>
                                <th>Plan</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($subscription = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subscription['first_name'] . ' ' . $subscription['last_name']); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($subscription['email']); ?></div>
                                        <div><?php echo htmlspecialchars($subscription['phone']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($subscription['plan_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($subscription['start_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($subscription['end_date'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $subscription['status']; ?>">
                                            <?php echo ucfirst($subscription['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit_subscription.php?id=<?php echo $subscription['subscription_id']; ?>" class="btn btn-primary">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <?php if ($subscription['status'] === 'active'): ?>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="subscription_id" value="<?php echo $subscription['subscription_id']; ?>">
                                                    <input type="hidden" name="status" value="expired">
                                                    <button type="submit" name="update_status" class="btn btn-warning">
                                                        <i class="fas fa-pause"></i> Expire
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="subscription_id" value="<?php echo $subscription['subscription_id']; ?>">
                                                    <input type="hidden" name="status" value="active">
                                                    <button type="submit" name="update_status" class="btn btn-success">
                                                        <i class="fas fa-play"></i> Activate
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this subscription? This action cannot be undone.');">
                                                <input type="hidden" name="subscription_id" value="<?php echo $subscription['subscription_id']; ?>">
                                                <button type="submit" name="delete_subscription" class="btn btn-danger">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-subscriptions">
                        <i class="fas fa-id-card"></i>
                        <h3>No Subscriptions Found</h3>
                        <p>There are no subscriptions in the system yet.</p>
                        <a href="add_subscription.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Subscription
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const rows = document.querySelectorAll('.subscriptions-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
    </script>
</body>
</html> 
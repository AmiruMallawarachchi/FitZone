<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get subscription ID from URL
$subscription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch subscription data
$query = "SELECT s.*, m.first_name, m.last_name, m.email 
          FROM subscriptions s 
          JOIN members m ON s.member_id = m.member_id 
          WHERE s.subscription_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $subscription_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Subscription not found";
    header('Location: manage_subscriptions.php');
    exit();
}

$subscription = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_name = trim($_POST['plan_name']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $status = $_POST['status'];
    $price = (float)$_POST['price'];
    $description = trim($_POST['description']);
    
    // Validate required fields
    $errors = [];
    if (empty($plan_name)) $errors[] = "Plan name is required";
    if (empty($start_date)) $errors[] = "Start date is required";
    if (empty($end_date)) $errors[] = "End date is required";
    if (empty($status)) $errors[] = "Status is required";
    if ($price <= 0) $errors[] = "Price must be greater than 0";
    
    // Validate dates
    if (strtotime($end_date) <= strtotime($start_date)) {
        $errors[] = "End date must be after start date";
    }
    
    if (empty($errors)) {
        $query = "UPDATE subscriptions 
                  SET plan_name = ?, start_date = ?, end_date = ?, 
                      status = ?, price = ?, description = ? 
                  WHERE subscription_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssdsi", $plan_name, $start_date, $end_date, $status, $price, $description, $subscription_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Subscription updated successfully";
            header('Location: manage_subscriptions.php');
            exit();
        } else {
            $errors[] = "Error updating subscription: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Subscription - Admin Dashboard</title>
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
            margin-bottom: 10px;
        }

        .subscription-form {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 800px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
        }

        .form-text {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
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
            font-size: 1em;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #ff5252;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background: #ffebee;
            color: #c62828;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .member-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .member-info h3 {
            color: var(--dark);
            margin-bottom: 10px;
        }

        .member-info p {
            color: #666;
            margin-bottom: 5px;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                margin-bottom: 20px;
            }

            .subscription-form {
                padding: 20px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
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
                <h1 class="page-title">Edit Subscription</h1>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul style="margin-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="subscription-form">
                <div class="member-info">
                    <h3>Member Information</h3>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($subscription['first_name'] . ' ' . $subscription['last_name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($subscription['email']); ?></p>
                </div>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="plan_name">Plan Name</label>
                        <input type="text" name="plan_name" id="plan_name" class="form-control" 
                               value="<?php echo htmlspecialchars($subscription['plan_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"><?php echo htmlspecialchars($subscription['description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="price">Price</label>
                        <input type="number" name="price" id="price" class="form-control" step="0.01" min="0" 
                               value="<?php echo htmlspecialchars($subscription['price']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" 
                               value="<?php echo htmlspecialchars($subscription['start_date']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" 
                               value="<?php echo htmlspecialchars($subscription['end_date']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control" required>
                            <option value="active" <?php echo $subscription['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="expired" <?php echo $subscription['status'] === 'expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="pending" <?php echo $subscription['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Subscription
                        </button>
                        <a href="manage_subscriptions.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Update end_date minimum when start_date changes
        document.getElementById('start_date').addEventListener('change', function() {
            document.getElementById('end_date').min = this.value;
        });
    </script>
</body>
</html> 
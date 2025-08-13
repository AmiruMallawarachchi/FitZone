<?php
session_start();
require_once '../config.php';

// Debug information
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a member
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: ../login.php');
    exit();
}

// Debug: Print session information
echo "<!-- Debug: User ID: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not set') . " -->";
echo "<!-- Debug: User Type: " . (isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'Not set') . " -->";

// Check if plan_id is provided
if (!isset($_GET['plan_id']) || !is_numeric($_GET['plan_id'])) {
    $_SESSION['error'] = "Invalid membership plan selected.";
    header('Location: membership.php');
    exit();
}

$plan_id = (int)$_GET['plan_id'];
echo "<!-- Debug: Plan ID: " . $plan_id . " -->";

// Get member ID
$query = "SELECT member_id FROM members WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$member = $result->fetch_assoc();
$member_id = $member['member_id'];
echo "<!-- Debug: Member ID: " . $member_id . " -->";

// Get membership plan details
$query = "SELECT * FROM memberships WHERE membership_id = ? AND status = 'active'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $plan_id);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();

if (!$plan) {
    $_SESSION['error'] = "Selected membership plan is not available.";
    header('Location: membership.php');
    exit();
}

echo "<!-- Debug: Plan found: " . ($plan ? 'Yes' : 'No') . " -->";
if ($plan) {
    echo "<!-- Debug: Plan name: " . htmlspecialchars($plan['name']) . " -->";
}

// Store subscription data in session
$_SESSION['pending_subscription'] = [
    'membership_id' => $plan_id,
    'plan_name' => $plan['name'],
    'price' => $plan['price'],
    'duration_months' => $plan['duration_months']
];

// Redirect to confirmation page
header('Location: confirm_subscription.php');
exit();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe to Membership - Member Dashboard</title>
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

        .subscription-details {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 800px;
            margin-bottom: 30px;
        }

        .plan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .plan-name {
            font-size: 1.5em;
            color: var(--dark);
        }

        .plan-price {
            font-size: 1.8em;
            color: var(--primary);
            font-weight: bold;
        }

        .plan-description {
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .plan-features {
            margin-bottom: 30px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .feature-item i {
            color: var(--success);
            margin-right: 10px;
        }

        .subscription-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .summary-item:last-child {
            margin-bottom: 0;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-weight: bold;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
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
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Member Dashboard</h2>
            </div>
            <a href="index.php" class="nav-item">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
            <a href="my_classes.php" class="nav-item">
                <i class="fas fa-calendar"></i>
                My Classes
            </a>
            <a href="book_class.php" class="nav-item">
                <i class="fas fa-plus-circle"></i>
                Book Class
            </a>
            <a href="membership.php" class="nav-item active">
                <i class="fas fa-id-card"></i>
                Membership
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
                <h1 class="page-title">Subscribe to Membership Plan</h1>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="subscription-details">
                <div class="plan-header">
                    <h2 class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></h2>
                    <div class="plan-price">$<?php echo number_format($plan['price'], 2); ?></div>
                </div>

                <p class="plan-description"><?php echo htmlspecialchars($plan['description']); ?></p>

                <div class="plan-features">
                    <?php 
                    $features = explode(',', $plan['features']);
                    foreach ($features as $feature): 
                    ?>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span><?php echo htmlspecialchars(trim($feature)); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="subscription-summary">
                    <div class="summary-item">
                        <span>Duration:</span>
                        <span><?php echo $plan['duration_months']; ?> months</span>
                    </div>
                    <div class="summary-item">
                        <span>Start Date:</span>
                        <span><?php echo date('F j, Y'); ?></span>
                    </div>
                    <div class="summary-item">
                        <span>End Date:</span>
                        <span><?php echo date('F j, Y', strtotime("+{$plan['duration_months']} months")); ?></span>
                    </div>
                    <div class="summary-item">
                        <span>Total Amount:</span>
                        <span>$<?php echo number_format($plan['price'], 2); ?></span>
                    </div>
                </div>

                <form method="POST" action="">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Confirm Subscription
                    </button>
                    <a href="membership.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 
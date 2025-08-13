<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a member
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: ../login.php');
    exit();
}

// Check if subscription data exists in session
if (!isset($_SESSION['pending_subscription'])) {
    $_SESSION['error'] = "No subscription pending confirmation.";
    header('Location: membership.php');
    exit();
}

$subscription_data = $_SESSION['pending_subscription'];

// Get member ID
$query = "SELECT member_id FROM members WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$member = $result->fetch_assoc();
$member_id = $member['member_id'];

// Get membership plan details
$query = "SELECT * FROM memberships WHERE membership_id = ? AND status = 'active'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $subscription_data['membership_id']);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();

if (!$plan) {
    $_SESSION['error'] = "Selected membership plan is no longer available.";
    unset($_SESSION['pending_subscription']);
    header('Location: membership.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm'])) {
        // Calculate subscription dates
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+{$plan['duration_months']} months"));
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Insert new subscription
            $query = "INSERT INTO member_subscriptions (member_id, membership_id, start_date, end_date, status) 
                     VALUES (?, ?, ?, ?, 'active')";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iiss", $member_id, $subscription_data['membership_id'], $start_date, $end_date);
            
            if ($stmt->execute()) {
                // Clear pending subscription
                unset($_SESSION['pending_subscription']);
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['success'] = "Subscription confirmed successfully! Welcome to " . htmlspecialchars($plan['name']);
                header('Location: membership.php');
                exit();
            } else {
                throw new Exception("Error creating subscription");
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $_SESSION['error'] = "Error confirming subscription. Please try again.";
        }
    } elseif (isset($_POST['cancel'])) {
        // Clear pending subscription
        unset($_SESSION['pending_subscription']);
        header('Location: membership.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Subscription - FitZone</title>
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }

        .confirmation-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .card-header h1 {
            color: var(--dark);
            font-size: 2em;
            margin-bottom: 10px;
        }

        .card-header p {
            color: #666;
        }

        .subscription-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #666;
            font-weight: 600;
        }

        .detail-value {
            color: var(--dark);
            font-weight: 600;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-top: 2px solid #eee;
            margin-top: 10px;
            font-size: 1.2em;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn {
            padding: 12px 30px;
            border-radius: 25px;
            border: none;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #ff5252;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #e9ecef;
            color: var(--dark);
        }

        .btn-secondary:hover {
            background: #dee2e6;
            transform: translateY(-2px);
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            animation: fadeIn 0.5s ease-out;
        }

        .alert-danger {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="confirmation-card">
            <div class="card-header">
                <h1>Confirm Your Subscription</h1>
                <p>Please review your subscription details before confirming</p>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="subscription-details">
                <div class="detail-row">
                    <span class="detail-label">Membership Plan</span>
                    <span class="detail-value"><?php echo htmlspecialchars($plan['name']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Duration</span>
                    <span class="detail-value"><?php echo $plan['duration_months']; ?> months</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Start Date</span>
                    <span class="detail-value"><?php echo date('F d, Y'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">End Date</span>
                    <span class="detail-value"><?php echo date('F d, Y', strtotime("+{$plan['duration_months']} months")); ?></span>
                </div>
                <div class="total-row">
                    <span class="detail-label">Total Amount</span>
                    <span class="detail-value">$<?php echo number_format($plan['price'], 2); ?></span>
                </div>
            </div>

            <form method="POST" action="">
                <div class="action-buttons">
                    <button type="submit" name="confirm" class="btn btn-primary">
                        <i class="fas fa-check"></i> Confirm Subscription
                    </button>
                    <button type="submit" name="cancel" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 
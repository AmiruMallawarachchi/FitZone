<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a member
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: ../login.php');
    exit();
}

// Get member ID
$query = "SELECT member_id FROM members WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$member = $result->fetch_assoc();
$member_id = $member['member_id'];

// Get active membership
$query = "SELECT ms.*, m.name as membership_name, m.description, m.price, m.duration_months 
          FROM member_subscriptions ms 
          JOIN memberships m ON ms.membership_id = m.membership_id 
          WHERE ms.member_id = ? AND ms.status = 'active' 
          ORDER BY ms.end_date DESC 
          LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$active_membership = $stmt->get_result()->fetch_assoc();

// Get membership history
$query = "SELECT ms.*, m.name as membership_name, m.price, m.duration_months 
          FROM member_subscriptions ms 
          JOIN memberships m ON ms.membership_id = m.membership_id 
          WHERE ms.member_id = ? 
          ORDER BY ms.start_date DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$membership_history = $stmt->get_result();

// Get available membership plans
$query = "SELECT * FROM memberships WHERE status = 'active' ORDER BY price";
$result = $conn->query($query);
$membership_plans = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership - Member Dashboard</title>
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

        .membership-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .membership-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .membership-title {
            font-size: 1.2em;
            color: var(--dark);
        }

        .membership-status {
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

        .membership-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
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

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .plan-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .plan-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .plan-name {
            font-size: 1.2em;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .plan-price {
            font-size: 1.5em;
            color: var(--primary);
            font-weight: 600;
        }

        .plan-features {
            margin-bottom: 20px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            color: #666;
        }

        .feature-item i {
            width: 20px;
            margin-right: 10px;
            color: var(--success);
        }

        .plan-actions {
            text-align: center;
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

        .btn-secondary {
            background: var(--secondary);
            color: white;
        }

        .btn-secondary:hover {
            background: #3dbeb6;
        }

        .history-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .history-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table th,
        .history-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .history-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .history-table tr:last-child td {
            border-bottom: none;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                margin-bottom: 20px;
            }

            .membership-details {
                grid-template-columns: 1fr;
            }

            .plans-grid {
                grid-template-columns: 1fr;
            }

            .history-table {
                overflow-x: auto;
            }
        }

        /* Animation classes */
        .animate-fadeIn {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        .animate-slideIn {
            animation: slideIn 0.5s ease-in-out;
        }
        
        .animate-scaleIn {
            animation: scaleIn 0.3s ease-in-out;
        }
        
        .animate-pulse {
            animation: pulse 1.5s infinite;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes scaleIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        /* Apply animations to elements */
        .page-header {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        .active-membership {
            animation: slideIn 0.5s ease-in-out 0.2s backwards;
        }
        
        .membership-card {
            animation: scaleIn 0.3s ease-in-out backwards;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .membership-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .membership-plans {
            animation: slideIn 0.5s ease-in-out 0.4s backwards;
        }
        
        .plan-card {
            animation: scaleIn 0.3s ease-in-out backwards;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .membership-history {
            animation: slideIn 0.5s ease-in-out 0.6s backwards;
        }
        
        .history-item {
            animation: fadeIn 0.5s ease-in-out backwards;
        }
        
        .btn-primary {
            transition: transform 0.2s ease, background-color 0.2s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            background-color: #0056b3;
        }
        
        .alert {
            animation: slideIn 0.5s ease-in-out;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'member_nav.php'; ?>
        <div class="main-content">
            <div class="page-header animate-fadeIn">
                <h1>My Membership</h1>
                <p>View and manage your gym membership</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger animate-slideIn">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success animate-slideIn">
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>
            
            <div class="active-membership animate-slideIn">
                <?php if ($active_membership): ?>
                    <div class="membership-card">
                        <div class="membership-header">
                            <h2 class="membership-title">Current Membership</h2>
                            <span class="membership-status status-active">Active</span>
                        </div>
                        <div class="membership-details">
                            <div class="detail-item">
                                <i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($active_membership['membership_name']); ?>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-dollar-sign"></i>
                                $<?php echo number_format($active_membership['price'], 2); ?>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-calendar"></i>
                                Valid until <?php echo date('F j, Y', strtotime($active_membership['end_date'])); ?>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-clock"></i>
                                <?php echo $active_membership['duration_months']; ?> months
                            </div>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-info-circle"></i>
                            <?php echo htmlspecialchars($active_membership['description']); ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-membership">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>You don't have an active membership.</p>
                        <a href="#membership-plans" class="btn btn-primary animate-pulse">View Membership Plans</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="membership-plans animate-slideIn" id="membership-plans">
                <h2>Available Membership Plans</h2>
                <div class="plans-grid">
                    <?php 
                    $delay = 0;
                    foreach ($membership_plans as $plan): 
                        $delay += 0.1;
                    ?>
                        <div class="plan-card animate-scaleIn" style="animation-delay: <?php echo $delay; ?>s">
                            <div class="plan-header">
                                <h3 class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></h3>
                                <div class="plan-price">$<?php echo number_format($plan['price'], 2); ?></div>
                                <div class="plan-duration"><?php echo $plan['duration_months']; ?> months</div>
                            </div>
                            <div class="plan-features">
                                <div class="feature-item">
                                    <i class="fas fa-check"></i>
                                    <?php echo htmlspecialchars($plan['description']); ?>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-check"></i>
                                    Access to all classes
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-check"></i>
                                    Priority booking
                                </div>
                            </div>
                            <div class="plan-actions">
                                <a href="subscribe.php?plan_id=<?php echo $plan['membership_id']; ?>" class="btn btn-primary">
                                    Subscribe Now
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="membership-history animate-slideIn">
                <h2>Membership History</h2>
                <?php if ($membership_history->num_rows > 0): ?>
                    <div class="history-list">
                        <?php 
                        $delay = 0;
                        while ($history = $membership_history->fetch_assoc()): 
                            $delay += 0.1;
                        ?>
                            <div class="history-item animate-fadeIn" style="animation-delay: <?php echo $delay; ?>s">
                                <div class="history-item-content">
                                    <div class="history-item-header">
                                        <h3 class="history-item-title"><?php echo htmlspecialchars($history['membership_name']); ?></h3>
                                        <span class="history-item-status status-<?php echo $history['status']; ?>">
                                            <?php echo ucfirst($history['status']); ?>
                                        </span>
                                    </div>
                                    <div class="history-item-details">
                                        <div class="history-item-detail">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo date('F j, Y', strtotime($history['start_date'])); ?>
                                        </div>
                                        <div class="history-item-detail">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo date('F j, Y', strtotime($history['end_date'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-history animate-fadeIn">
                        <p>No membership history found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 
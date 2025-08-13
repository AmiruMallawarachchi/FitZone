<?php
session_start();
ob_start(); // Start output buffering
require_once '../config.php';

// Check if user is logged in and is a member
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: ../login.php');
    exit();
}

// Get member details
$query = "SELECT m.*, u.full_name, u.email, u.created_at 
          FROM members m 
          JOIN users u ON m.user_id = u.id 
          WHERE m.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();

// Get booked classes
$query = "SELECT cb.*, c.name as class_name, c.description, c.schedule_time, c.duration_minutes,
          t.specialization, u.full_name as trainer_name
          FROM class_bookings cb
          JOIN classes c ON cb.class_id = c.class_id
          JOIN trainers t ON c.trainer_id = t.trainer_id
          JOIN users u ON t.user_id = u.id
          WHERE cb.member_id = ?
          ORDER BY cb.booking_date DESC, c.schedule_time DESC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $member['member_id']);
$stmt->execute();
$booked_classes = $stmt->get_result();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $emergency_phone = trim($_POST['emergency_phone'] ?? '');
    
    // Update users table
    $query = "UPDATE users SET full_name = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $full_name, $_SESSION['user_id']);
    $stmt->execute();
    
    // Update members table
    $query = "UPDATE members SET phone = ?, address = ?, emergency_contact = ?, emergency_phone = ? WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssi", $phone, $address, $emergency_contact, $emergency_phone, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Profile updated successfully.";
        // Refresh member data
        $query = "SELECT m.*, u.full_name, u.email, u.created_at 
                 FROM members m 
                 JOIN users u ON m.user_id = u.id 
                 WHERE m.user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $member = $stmt->get_result()->fetch_assoc();
    } else {
        $_SESSION['error'] = "Error updating profile.";
    }
    
    header('Location: profile.php');
    exit();
}

// Handle query submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_query'])) {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    
    if (!empty($subject) && !empty($message)) {
        $query = "INSERT INTO queries (member_id, subject, message, is_public) 
                 VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("issi", $member['member_id'], $subject, $message, $is_public);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Your query has been submitted successfully.";
        } else {
            $_SESSION['error'] = "Error submitting query. Please try again.";
        }
    } else {
        $_SESSION['error'] = "Please fill in all required fields.";
    }
    
    header('Location: profile.php');
    exit();
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $query = "SELECT password FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 8) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $query = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Password updated successfully.";
                } else {
                    $_SESSION['error'] = "Error updating password.";
                }
            } else {
                $_SESSION['error'] = "New password must be at least 8 characters long.";
            }
        } else {
            $_SESSION['error'] = "New passwords do not match.";
        }
    } else {
        $_SESSION['error'] = "Current password is incorrect.";
    }
    
    header('Location: profile.php');
    exit();
}

// Get member's queries
$query = "SELECT * FROM queries WHERE member_id = ? ORDER BY query_date DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $member['member_id']);
$stmt->execute();
$queries = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Member Dashboard</title>
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
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 40px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .page-title {
            font-size: 2em;
            color: var(--dark);
            margin-bottom: 15px;
        }

        .section-title {
            font-size: 1.5em;
            color: var(--dark);
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary);
        }

        .profile-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .profile-title {
            font-size: 1.2em;
            color: var(--dark);
        }

        .profile-details {
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

        .query-form {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 500;
            font-size: 1.1em;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
            margin-bottom: 5px;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-top: 10px;
        }

        .checkbox-group input {
            margin-right: 10px;
        }

        .btn {
            display: inline-block;
            padding: 12px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            margin: 10px 0;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #ff5252;
        }

        .queries-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .queries-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .queries-table th,
        .queries-table td {
            padding: 20px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .queries-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .queries-table tr:last-child td {
            border-bottom: none;
        }

        .query-reply {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }

        .query-reply strong {
            color: var(--primary);
        }

        .query-reply small {
            color: #666;
            display: block;
            margin-top: 5px;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-replied {
            background: #d4edda;
            color: #155724;
        }

        .status-closed {
            background: #f8d7da;
            color: #721c24;
        }

        .alert {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            font-size: 1.1em;
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

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                margin-bottom: 20px;
            }

            .profile-details {
                grid-template-columns: 1fr;
            }
        }

        .edit-profile-form {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .booked-classes {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .class-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary);
            transition: transform 0.2s ease;
        }

        .class-card:hover {
            transform: translateX(5px);
        }

        .class-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .class-title {
            font-weight: 600;
            color: var(--dark);
            font-size: 1.2em;
        }

        .class-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 15px 0;
        }

        .class-detail {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: white;
            border-radius: 5px;
        }

        .queries-section {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .queries-table {
            margin-top: 20px;
        }

        .queries-table th,
        .queries-table td {
            padding: 20px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .query-reply {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }

        .btn {
            display: inline-block;
            padding: 12px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            margin: 10px 0;
        }

        .alert {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            font-size: 1.1em;
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
        
        .profile-section {
            animation: slideIn 0.5s ease-in-out backwards;
        }
        
        .profile-section:nth-child(1) { animation-delay: 0.2s; }
        .profile-section:nth-child(2) { animation-delay: 0.3s; }
        .profile-section:nth-child(3) { animation-delay: 0.4s; }
        
        .form-group {
            animation: slideIn 0.5s ease-in-out backwards;
        }
        
        .form-group:nth-child(1) { animation-delay: 0.3s; }
        .form-group:nth-child(2) { animation-delay: 0.4s; }
        .form-group:nth-child(3) { animation-delay: 0.5s; }
        .form-group:nth-child(4) { animation-delay: 0.6s; }
        
        .stats-card {
            animation: scaleIn 0.3s ease-in-out backwards;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .recent-activity {
            animation: slideIn 0.5s ease-in-out 0.5s backwards;
        }
        
        .activity-item {
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

        .profile-section {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .profile-section h2 {
            color: var(--dark);
            font-size: 1.5em;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--primary);
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .activity-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .class-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .class-header h3 {
            color: var(--dark);
            font-size: 1.2em;
            margin: 0;
        }

        .class-date {
            color: var(--primary);
            font-weight: 600;
            font-size: 0.9em;
        }

        .class-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .class-info p {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
            font-size: 0.95em;
        }

        .class-info i {
            color: var(--primary);
            width: 20px;
            text-align: center;
        }

        .class-description {
            color: #666;
            font-size: 0.95em;
            line-height: 1.5;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #ddd;
        }

        .empty-state i {
            font-size: 3em;
            color: #ccc;
            margin-bottom: 15px;
        }

        .empty-state p {
            color: #666;
            margin-bottom: 20px;
            font-size: 1.1em;
        }

        .empty-state .btn {
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .class-info {
                grid-template-columns: 1fr;
            }

            .class-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .class-date {
                font-size: 0.85em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'member_nav.php'; ?>
        <div class="main-content">
            <div class="page-header animate-fadeIn">
                <h1>My Profile</h1>
                <p>View and update your personal information</p>
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
            
            <div class="profile-grid">
                <div class="profile-section animate-slideIn">
                    <h2>Personal Information</h2>
                    <form method="POST" action="" class="profile-form">
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($member['full_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($member['email']); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($member['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary animate-pulse">
                            Update Profile
                        </button>
                    </form>
                </div>
                
                <div class="profile-section animate-slideIn">
                    <h2>Emergency Contact</h2>
                    <form method="POST" action="" class="emergency-form">
                        <div class="form-group">
                            <label for="emergency_contact">Emergency Contact Name</label>
                            <input type="text" id="emergency_contact" name="emergency_contact" class="form-control" 
                                   value="<?php echo htmlspecialchars($member['emergency_contact'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="emergency_phone">Emergency Contact Phone</label>
                            <input type="tel" id="emergency_phone" name="emergency_phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($member['emergency_phone'] ?? ''); ?>">
                        </div>
                        
                        <button type="submit" name="update_emergency" class="btn btn-primary animate-pulse">
                            Update Emergency Contact
                        </button>
                    </form>
                </div>
                
                <div class="profile-section animate-slideIn">
                    <h2>Change Password</h2>
                    <form method="POST" action="" class="password-form">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                            <small class="text-muted">Password must be at least 8 characters long</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" name="update_password" class="btn btn-primary animate-pulse">
                            Update Password
                        </button>
                    </form>
                </div>
                
                <div class="profile-section animate-slideIn">
                    <h2>Recent Activity</h2>
                    <div class="activity-list">
                        <?php if ($booked_classes->num_rows > 0): ?>
                            <?php 
                            $delay = 0;
                            while ($class = $booked_classes->fetch_assoc()): 
                                $delay += 0.1;
                            ?>
                                <div class="activity-item animate-fadeIn" style="animation-delay: <?php echo $delay; ?>s">
                                    <div class="class-header">
                                        <h3><?php echo htmlspecialchars($class['class_name']); ?></h3>
                                        <span class="class-date">
                                            <?php echo date('M j, Y', strtotime($class['booking_date'])); ?>
                                            <?php echo date('g:i A', strtotime($class['schedule_time'])); ?>
                                        </span>
                                    </div>
                                    <div class="class-info">
                                        <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($class['trainer_name'] ?? 'Not specified'); ?></p>
                                        <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($class['specialization'] ?? 'Not specified'); ?></p>
                                        <p><i class="fas fa-clock"></i> <?php echo $class['duration_minutes']; ?> minutes</p>
                                        <p><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($class['status'] ?? 'Pending'); ?></p>
                                    </div>
                                    <div class="class-description">
                                        <?php echo htmlspecialchars($class['description'] ?? 'No description available'); ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state animate-fadeIn">
                                <i class="fas fa-calendar-times"></i>
                                <p>No recent activity found.</p>
                                <a href="book_class.php" class="btn btn-primary">Book a Class</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="query-form">
                <h2>Submit a Query</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" class="form-control" required></textarea>
                    </div>
                    <button type="submit" name="submit_query" class="btn btn-primary">Submit Query</button>
                </form>
            </div>

            <h2 style="margin-bottom: 20px;">My Queries</h2>
            <div class="queries-table">
                <table>
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Message</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Reply</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($query = $queries->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($query['subject']); ?></td>
                                <td><?php echo htmlspecialchars($query['message']); ?></td>
                                <td><?php echo date('F j, Y', strtotime($query['query_date'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $query['status']; ?>">
                                        <?php echo ucfirst($query['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($query['reply']): ?>
                                        <div class="query-reply">
                                            <strong>Admin Reply:</strong>
                                            <p><?php echo htmlspecialchars($query['reply']); ?></p>
                                            <small>Replied on: <?php echo date('F j, Y H:i', strtotime($query['reply_date'])); ?></small>
                                        </div>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">No reply yet</span>
                                    <?php endif; ?>
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
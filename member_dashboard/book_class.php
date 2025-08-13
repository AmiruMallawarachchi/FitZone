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

// Get all active classes
$query = "SELECT c.*, t.specialization, u.full_name as trainer_name, c.duration_minutes as duration 
          FROM classes c 
          JOIN trainers t ON c.trainer_id = t.trainer_id 
          JOIN users u ON t.user_id = u.id 
          WHERE c.status = 'active' 
          ORDER BY c.schedule_time ASC";
$result = $conn->query($query);
$classes = $result->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
    $booking_date = isset($_POST['booking_date']) ? $_POST['booking_date'] : '';
    
    $errors = [];
    
    // Validate inputs
    if ($class_id <= 0) {
        $errors[] = "Please select a class.";
    }
    
    if (empty($booking_date)) {
        $errors[] = "Please select a booking date.";
    } else {
        // Check if date is in the future
        $selected_date = new DateTime($booking_date);
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        
        if ($selected_date < $today) {
            $errors[] = "Booking date must be in the future.";
        }
    }
    
    // Check if class is already booked for the selected date
    if (empty($errors)) {
        $query = "SELECT COUNT(*) as count FROM class_bookings 
                  WHERE class_id = ? AND booking_date = ? AND status != 'cancelled'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $class_id, $booking_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $errors[] = "You have already booked this class for the selected date.";
        }
    }
    
    // If no errors, insert the booking
    if (empty($errors)) {
        $query = "INSERT INTO class_bookings (member_id, class_id, booking_date, status) 
                  VALUES (?, ?, ?, 'upcoming')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iis", $member_id, $class_id, $booking_date);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Class booked successfully!";
            header('Location: my_classes.php');
            exit();
        } else {
            $errors[] = "Error booking class. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Class - FitZone</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/animations.css">
    <style>
        :root {
            --primary: #ff6b6b;
            --secondary: #4ecdc4;
            --dark: #2c3e50;
            --light: #f8f9fa;
            --success: #2ecc71;
            --warning: #f1c40f;
            --danger: #e74c3c;
            --gradient-1: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            --gradient-2: linear-gradient(135deg, #4ecdc4, #45b7ae);
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
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .page-title {
            color: var(--dark);
            font-size: 1.5em;
            margin-bottom: 10px;
        }

        .booking-form {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 800px;
            margin-bottom: 30px;
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
            padding: 12px;
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

        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .class-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .class-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .class-card.selected {
            border-color: var(--primary);
            background: #fff5f5;
        }

        .class-name {
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }

        .class-info {
            color: #666;
            margin-bottom: 5px;
        }

        .class-time {
            color: var(--primary);
            font-weight: 500;
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

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                margin-bottom: 20px;
            }

            .classes-grid {
                grid-template-columns: 1fr;
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
        
        .class-card {
            animation: scaleIn 0.3s ease-in-out backwards;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .class-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .class-card.selected {
            animation: pulse 1.5s infinite;
            border-color: #007bff;
        }
        
        .form-group {
            animation: slideIn 0.5s ease-in-out backwards;
        }
        
        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        
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
                <h1 class="page-title">Book a Class</h1>
                <p>Select a class and date to make a booking</p>
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

            <div class="booking-form">
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Select a Class</label>
                        <div class="classes-grid">
                            <?php foreach ($classes as $class): ?>
                                <div class="class-card" data-class-id="<?php echo $class['class_id']; ?>">
                                    <div class="class-name"><?php echo htmlspecialchars($class['name']); ?></div>
                                    <div class="class-info">
                                        <strong>Trainer:</strong> <?php echo htmlspecialchars($class['trainer_name']); ?>
                                    </div>
                                    <div class="class-info">
                                        <strong>Specialization:</strong> <?php echo htmlspecialchars($class['specialization'] ?? 'Not specified'); ?>
                                    </div>
                                    <div class="class-info">
                                        <strong>Time:</strong> <span class="class-time"><?php echo date('g:i A', strtotime($class['schedule_time'])); ?></span>
                                    </div>
                                    <div class="class-info">
                                        <strong>Duration:</strong> <?php echo $class['duration'] ?? 'Not specified'; ?> minutes
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="class_id" id="class_id" value="<?php echo isset($_POST['class_id']) ? $_POST['class_id'] : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="booking_date">Select Date</label>
                        <input type="date" id="booking_date" name="booking_date" class="form-control" 
                               value="<?php echo isset($_POST['booking_date']) ? $_POST['booking_date'] : ''; ?>" 
                               min="<?php echo date('Y-m-d'); ?>">
                        <div class="form-text">Please select a date for your class booking.</div>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary animate-pulse">
                            <i class="fas fa-check"></i> Book Class
                        </button>
                        <a href="my_classes.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const classCards = document.querySelectorAll('.class-card');
            const classInput = document.getElementById('class_id');
            
            classCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove selected class from all cards
                    classCards.forEach(c => c.classList.remove('selected'));
                    
                    // Add selected class to clicked card
                    this.classList.add('selected');
                    
                    // Update hidden input
                    classInput.value = this.getAttribute('data-class-id');
                });
            });
            
            // Set initial selection if there's a value
            if (classInput.value) {
                const selectedCard = document.querySelector(`.class-card[data-class-id="${classInput.value}"]`);
                if (selectedCard) {
                    selectedCard.classList.add('selected');
                }
            }
        });
    </script>
    <script src="js/animations.js"></script>
</body>
</html> 
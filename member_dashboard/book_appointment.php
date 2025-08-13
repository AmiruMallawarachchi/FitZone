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

// Get all trainers
$query = "SELECT t.trainer_id, t.specialization, u.full_name, u.email 
          FROM users u 
          JOIN trainers t ON u.id = t.user_id 
          WHERE u.user_type = 'trainer' 
          ORDER BY u.full_name";
$result = $conn->query($query);
$trainers = $result->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trainer_id = isset($_POST['trainer_id']) ? (int)$_POST['trainer_id'] : 0;
    $appointment_date = isset($_POST['appointment_date']) ? $_POST['appointment_date'] : '';
    $appointment_time = isset($_POST['appointment_time']) ? $_POST['appointment_time'] : '';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    
    $errors = [];
    
    // Validate inputs
    if ($trainer_id <= 0) {
        $errors[] = "Please select a trainer.";
    }
    
    if (empty($appointment_date)) {
        $errors[] = "Please select an appointment date.";
    } else {
        // Check if date is in the future
        $selected_date = new DateTime($appointment_date);
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        
        if ($selected_date < $today) {
            $errors[] = "Appointment date must be in the future.";
        }
    }
    
    if (empty($appointment_time)) {
        $errors[] = "Please select an appointment time.";
    }
    
    if (empty($reason)) {
        $errors[] = "Please provide a reason for the appointment.";
    }
    
    // Check if trainer is available at the selected date and time
    if (empty($errors)) {
        $appointment_datetime = $appointment_date . ' ' . $appointment_time;
        
        $query = "SELECT COUNT(*) as count FROM appointments 
                  WHERE trainer_id = ? AND appointment_date = ? AND status != 'cancelled'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $trainer_id, $appointment_datetime);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $errors[] = "The selected trainer is not available at this date and time. Please choose another time.";
        }
    }
    
    // If no errors, insert the appointment
    if (empty($errors)) {
        $appointment_datetime = $appointment_date . ' ' . $appointment_time;
        
        $query = "INSERT INTO appointments (member_id, trainer_id, appointment_date, notes, status) 
                  VALUES (?, ?, ?, ?, 'upcoming')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiss", $member_id, $trainer_id, $appointment_datetime, $reason);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Appointment booked successfully!";
            header('Location: my_appointments.php');
            exit();
        } else {
            $errors[] = "Error booking appointment. Please try again.";
        }
    }
}

// Get available time slots (9 AM to 5 PM, 1-hour intervals)
$time_slots = [];
for ($hour = 9; $hour <= 17; $hour++) {
    $time_slots[] = sprintf("%02d:00:00", $hour);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - FitZone</title>
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
            --gradient-1: linear-gradient(135deg, var(--primary), var(--secondary));
            --gradient-2: linear-gradient(135deg, var(--secondary), var(--primary));
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

        .trainers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .trainer-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .trainer-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .trainer-card.selected {
            border-color: var(--primary);
            background: #fff5f5;
        }

        .trainer-name {
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }

        .trainer-info {
            color: #666;
            margin-bottom: 5px;
        }

        .trainer-specialization {
            color: var(--primary);
            font-weight: 500;
        }

        .date-time-container {
            display: flex;
            gap: 20px;
        }

        .date-time-container .form-group {
            flex: 1;
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

            .trainers-grid {
                grid-template-columns: 1fr;
            }

            .date-time-container {
                flex-direction: column;
                gap: 0;
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
        
        .form-container {
            animation: slideIn 0.5s ease-in-out 0.2s backwards;
        }
        
        .form-group {
            animation: slideIn 0.5s ease-in-out backwards;
        }
        
        .form-group:nth-child(1) { animation-delay: 0.3s; }
        .form-group:nth-child(2) { animation-delay: 0.4s; }
        .form-group:nth-child(3) { animation-delay: 0.5s; }
        .form-group:nth-child(4) { animation-delay: 0.6s; }
        
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
        
        .trainer-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .trainer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'member_nav.php'; ?>

        <div class="main-content">
            <div class="page-header animate-fadeIn">
                <h1>Book Appointment</h1>
                <p>Schedule a session with one of our expert trainers</p>
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

            <div class="form-container animate-slideIn">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="trainer">Select Trainer</label>
                        <div class="trainers-grid">
                            <?php if (!empty($trainers)): ?>
                                <?php foreach ($trainers as $trainer): ?>
                                    <div class="trainer-card" data-trainer-id="<?php echo $trainer['trainer_id']; ?>">
                                        <div class="trainer-name"><?php echo htmlspecialchars($trainer['full_name']); ?></div>
                                        <div class="trainer-info">
                                            <strong>Specialization:</strong> 
                                            <span class="trainer-specialization">
                                                <?php echo htmlspecialchars($trainer['specialization'] ?? 'Not specified'); ?>
                                            </span>
                                        </div>
                                        <div class="trainer-info">
                                            <strong>Email:</strong> <?php echo htmlspecialchars($trainer['email']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-trainers">
                                    <p>No active trainers available at the moment.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="trainer_id" id="trainer_id" value="<?php echo isset($_POST['trainer_id']) ? $_POST['trainer_id'] : ''; ?>">
                    </div>

                    <div class="date-time-container">
                        <div class="form-group">
                            <label for="appointment_date">Select Date</label>
                            <input type="date" id="appointment_date" name="appointment_date" class="form-control" 
                                   value="<?php echo isset($_POST['appointment_date']) ? $_POST['appointment_date'] : ''; ?>" 
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="appointment_time">Select Time</label>
                            <select id="appointment_time" name="appointment_time" class="form-control">
                                <option value="">Select a time</option>
                                <?php foreach ($time_slots as $time): ?>
                                    <option value="<?php echo $time; ?>" <?php echo (isset($_POST['appointment_time']) && $_POST['appointment_time'] === $time) ? 'selected' : ''; ?>>
                                        <?php echo date('h:i A', strtotime($time)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="reason">Reason for Appointment</label>
                        <textarea id="reason" name="reason" class="form-control" rows="4"><?php echo isset($_POST['reason']) ? htmlspecialchars($_POST['reason']) : ''; ?></textarea>
                        <div class="form-text">Please provide details about what you'd like to discuss or work on during the appointment.</div>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary animate-pulse">
                            <i class="fas fa-check"></i> Book Appointment
                        </button>
                        <a href="my_appointments.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const trainerCards = document.querySelectorAll('.trainer-card');
            const trainerInput = document.getElementById('trainer_id');
            
            trainerCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove selected class from all cards
                    trainerCards.forEach(c => c.classList.remove('selected'));
                    
                    // Add selected class to clicked card
                    this.classList.add('selected');
                    
                    // Update hidden input
                    trainerInput.value = this.getAttribute('data-trainer-id');
                });
            });
            
            // Set initial selection if there's a value
            if (trainerInput.value) {
                const selectedCard = document.querySelector(`.trainer-card[data-trainer-id="${trainerInput.value}"]`);
                if (selectedCard) {
                    selectedCard.classList.add('selected');
                }
            }
        });
    </script>
</body>
</html> 
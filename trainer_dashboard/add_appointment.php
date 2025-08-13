<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a trainer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'trainer') {
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

// Fetch all active members for the dropdown
$members_query = "SELECT m.member_id, u.full_name, u.email 
                 FROM members m 
                 JOIN users u ON m.user_id = u.id 
                 WHERE m.membership_status = 'active' 
                 ORDER BY u.full_name";
$members_result = $conn->query($members_query);

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $member_id = (int)$_POST['member_id'];
    $appointment_date = $_POST['appointment_date'];
    $duration_minutes = (int)$_POST['duration_minutes'];
    $notes = trim($_POST['notes']);
    $status = $_POST['status'];

    // Validate input
    if ($member_id <= 0) {
        $errors[] = "Please select a member";
    }
    
    if (empty($appointment_date)) {
        $errors[] = "Appointment date and time is required";
    } else {
        // Check if the appointment time is in the future
        $appointment_timestamp = strtotime($appointment_date);
        if ($appointment_timestamp < time()) {
            $errors[] = "Appointment time must be in the future";
        }
    }
    
    if ($duration_minutes <= 0) {
        $errors[] = "Duration must be greater than 0";
    }
    
    if (empty($status)) {
        $errors[] = "Status is required";
    }

    // If no errors, proceed with insertion
    if (empty($errors)) {
        try {
            // Insert into appointments table
            $query = "INSERT INTO appointments (member_id, trainer_id, appointment_date, duration_minutes, notes, status) 
                     VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iisiss", $member_id, $trainer['trainer_id'], $appointment_date, $duration_minutes, $notes, $status);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Appointment added successfully!";
                header('Location: manage_appointments.php');
                exit();
            } else {
                $errors[] = "Failed to add appointment. Please try again.";
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Appointment - Trainer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="trainer_styles.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .form-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .form-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-section h3 i {
            color: #3498db;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #34495e;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52,152,219,0.2);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-1px);
        }
        
        .help-text {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: #fee;
            color: #c0392b;
            border: 1px solid #fcc;
        }
        
        .member-select {
            position: relative;
        }
        
        .member-select select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Trainer Dashboard</h2>
            </div>
            <a href="index.php" class="nav-item">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="manage_appointments.php" class="nav-item active">
                <i class="fas fa-calendar-check"></i> Appointments
            </a>
            <a href="manage_classes.php" class="nav-item">
                <i class="fas fa-dumbbell"></i> Classes
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="../logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Add New Appointment</h1>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" action="">
                    <div class="form-section">
                        <h3><i class="fas fa-user"></i> Member Selection</h3>
                        <div class="form-group member-select">
                            <label for="member_id">Select Member</label>
                            <select id="member_id" name="member_id" class="form-control" required>
                                <option value="" disabled selected>Choose a member</option>
                                <?php while ($member = $members_result->fetch_assoc()): ?>
                                    <option value="<?php echo $member['member_id']; ?>" <?php echo (isset($_POST['member_id']) && $_POST['member_id'] == $member['member_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($member['full_name'] . ' (' . $member['email'] . ')'); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-clock"></i> Appointment Details</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="appointment_date">Date & Time</label>
                                <input type="datetime-local" id="appointment_date" name="appointment_date" class="form-control" required
                                       value="<?php echo isset($_POST['appointment_date']) ? htmlspecialchars($_POST['appointment_date']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="duration_minutes">Duration (minutes)</label>
                                <input type="number" id="duration_minutes" name="duration_minutes" class="form-control" min="15" step="15" required
                                       value="<?php echo isset($_POST['duration_minutes']) ? htmlspecialchars($_POST['duration_minutes']) : '60'; ?>">
                                <div class="help-text">Minimum 15 minutes, in 15-minute increments</div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="scheduled" <?php echo (isset($_POST['status']) && $_POST['status'] === 'scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="cancelled" <?php echo (isset($_POST['status']) && $_POST['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-sticky-note"></i> Additional Notes</h3>
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" class="form-control"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                            <div class="help-text">Add any special instructions or information for this appointment</div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Appointment
                        </button>
                        <a href="manage_appointments.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Set minimum datetime to current time
        const appointmentDateInput = document.getElementById('appointment_date');
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        appointmentDateInput.min = `${year}-${month}-${day}T${hours}:${minutes}`;
    </script>
</body>
</html> 
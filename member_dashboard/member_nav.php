<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Include animation CSS -->
<link rel="stylesheet" href="css/animations.css">

<div class="sidebar slide-in">
    <div class="sidebar-header">
        <h2>Member Dashboard</h2>
    </div>
    <a href="index.php" class="nav-item <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
        <i class="fas fa-home"></i>
        Dashboard
    </a>
    <a href="my_classes.php" class="nav-item <?php echo $current_page === 'my_classes.php' ? 'active' : ''; ?>">
        <i class="fas fa-calendar"></i>
        My Classes
    </a>
    <a href="book_class.php" class="nav-item <?php echo $current_page === 'book_class.php' ? 'active' : ''; ?>">
        <i class="fas fa-plus-circle"></i>
        Book Class
    </a>
    <a href="my_appointments.php" class="nav-item <?php echo $current_page === 'my_appointments.php' ? 'active' : ''; ?>">
        <i class="fas fa-calendar-check"></i>
        My Appointments
    </a>
    <a href="book_appointment.php" class="nav-item <?php echo $current_page === 'book_appointment.php' ? 'active' : ''; ?>">
        <i class="fas fa-calendar-plus"></i>
        Book Appointment
    </a>
    <a href="membership.php" class="nav-item <?php echo $current_page === 'membership.php' || $current_page === 'subscribe.php' ? 'active' : ''; ?>">
        <i class="fas fa-id-card"></i>
        Membership
    </a>
    <a href="profile.php" class="nav-item <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
        <i class="fas fa-user"></i>
        Profile
    </a>
    <a href="../logout.php" class="nav-item">
        <i class="fas fa-sign-out-alt"></i>
        Logout
    </a>
</div>

<!-- Include animation JavaScript -->
<script src="js/animations.js"></script> 
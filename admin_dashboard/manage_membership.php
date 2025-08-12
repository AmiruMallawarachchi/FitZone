<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle membership deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $membership_id = $_GET['delete'];
    
    // Check if any members are subscribed to this membership
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM member_subscriptions WHERE membership_id = ?");
    $stmt->bind_param("i", $membership_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        $_SESSION['error_message'] = "Cannot delete this membership plan as it has active subscribers.";
    } else {
        // Delete the membership plan
        $stmt = $conn->prepare("DELETE FROM memberships WHERE membership_id = ?");
        $stmt->bind_param("i", $membership_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Membership plan deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Error deleting membership plan: " . $conn->error;
        }
    }
    
    header("Location: manage_membership.php");
    exit();
}

// Fetch all membership plans
$query = "SELECT * FROM memberships ORDER BY price";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Membership - FitZone Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #4a90e2;
            --secondary: #50c878;
            --dark: #2c3e50;
            --light: #f8f9fa;
            --danger: #e74c3c;
            --warning: #f1c40f;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f0f2f5;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: var(--dark);
            color: white;
            padding: 20px;
        }

        .sidebar-header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h2 {
            color: var(--primary);
            font-size: 24px;
        }

        .nav-menu {
            margin-top: 30px;
        }

        .nav-item {
            padding: 15px;
            margin: 5px 0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            text-decoration: none;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
        }

        .nav-item.active {
            background: var(--primary);
        }

        .nav-item i {
            width: 20px;
        }

        .main-content {
            flex: 1;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-title i {
            font-size: 24px;
            color: var(--primary);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 15px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #357abd;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: #d4ac0d;
        }

        .membership-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            animation: slideIn 0.5s ease-out forwards;
            opacity: 0;
        }

        .membership-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }

        .membership-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .membership-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .membership-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, rgba(255,255,255,0.1), transparent);
            transform: skewY(-5deg);
        }

        .membership-header h3 {
            font-size: 24px;
            margin-bottom: 10px;
            position: relative;
        }

        .membership-price {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
            position: relative;
        }

        .membership-period {
            font-size: 14px;
            opacity: 0.8;
            position: relative;
        }

        .membership-body {
            padding: 20px;
        }

        .membership-features {
            margin-bottom: 20px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            transition: all 0.3s ease;
        }

        .feature-item:last-child {
            border-bottom: none;
        }

        .feature-item:hover {
            background: #f8f9fa;
            padding-left: 10px;
        }

        .feature-item i {
            color: var(--primary);
            font-size: 18px;
        }

        .feature-item span {
            color: var(--dark);
            font-size: 14px;
        }

        .membership-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 600;
            position: absolute;
            top: 10px;
            right: 10px;
        }

        .status-active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-inactive {
            background: #ffebee;
            color: #c62828;
        }

        @keyframes slideIn {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .action-icons {
            display: flex;
            gap: 10px;
        }

        .action-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .action-icon:hover {
            transform: scale(1.1);
        }

        .icon-edit {
            background: var(--warning);
        }

        .icon-delete {
            background: var(--danger);
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            background: #357abd;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: #555;
        }

        .empty-state p {
            color: #777;
            margin-bottom: 20px;
        }

        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-height: 200px;
            overflow-y: auto;
            display: none;
            z-index: 1000;
        }

        .suggestion-item {
            padding: 10px 15px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .suggestion-item:hover {
            background-color: #f8f9fa;
        }

        .search-bar {
            position: relative;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">
            <?php 
            require_once 'admin_nav.php';
            echo getAdminNav('manage_membership.php');
            ?>
        </div>
        <div class="main-content">
            <div class="header">
                <div class="page-title">
                    <i class="fas fa-id-card"></i>
                    <h1>Manage Membership Plans</h1>
                </div>
                <div class="action-buttons">
                    <a href="add_membership.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Plan
                    </a>
                </div>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="search-bar">
                <input type="text" class="search-input" id="searchInput" placeholder="Search membership plans..." onkeyup="searchMemberships()">
                <button class="search-btn">
                    <i class="fas fa-search"></i> Search
                </button>
                <div class="search-suggestions" id="searchSuggestions"></div>
            </div>

            <?php if ($result->num_rows > 0): ?>
                <div class="membership-cards" id="membershipCards">
                    <?php while ($membership = $result->fetch_assoc()): ?>
                        <div class="membership-card">
                            <span class="status-badge <?php echo $membership['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo ucfirst($membership['status']); ?>
                            </span>
                            <div class="membership-header">
                                <h3><?php echo htmlspecialchars($membership['name']); ?></h3>
                                <div class="membership-price">Rs. <?php echo number_format($membership['price'], 2); ?></div>
                                <div class="membership-period">per <?php echo htmlspecialchars($membership['duration_months']); ?> months</div>
                            </div>
                            <div class="membership-body">
                                <div class="membership-features">
                                    <?php 
                                    $features = explode("\n", $membership['features']);
                                    foreach ($features as $feature): 
                                        if (trim($feature)): 
                                    ?>
                                        <div class="feature-item">
                                            <i class="fas fa-check-circle"></i>
                                            <span><?php echo htmlspecialchars(trim($feature)); ?></span>
                                        </div>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                                <div class="membership-actions">
                                    <a href="edit_membership.php?id=<?php echo $membership['membership_id']; ?>" 
                                       class="btn btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="manage_membership.php?delete=<?php echo $membership['membership_id']; ?>" 
                                       class="btn btn-danger"
                                       onclick="return confirm('Are you sure you want to delete this membership plan?');">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-id-card"></i>
                    <h3>No Membership Plans Found</h3>
                    <p>There are no membership plans available. Add your first plan to get started.</p>
                    <a href="add_membership.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Plan
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function searchMemberships() {
        const searchInput = document.getElementById('searchInput');
        const searchTerm = searchInput.value.toLowerCase();
        const suggestionsContainer = document.getElementById('searchSuggestions');
        const membershipCards = document.querySelectorAll('.membership-card');
        let suggestions = [];

        // Clear previous suggestions
        suggestionsContainer.innerHTML = '';
        suggestionsContainer.style.display = 'none';

        if (searchTerm.length > 0) {
            // Get all membership names and descriptions
            membershipCards.forEach(card => {
                const name = card.querySelector('.membership-header h3').textContent.toLowerCase();
                const price = card.querySelector('.membership-price').textContent.toLowerCase();
                const features = Array.from(card.querySelectorAll('.feature-item span')).map(span => span.textContent.toLowerCase());
                
                if (name.includes(searchTerm) || price.includes(searchTerm) || features.some(feature => feature.includes(searchTerm))) {
                    suggestions.push({
                        name: card.querySelector('.membership-header h3').textContent,
                        price: card.querySelector('.membership-price').textContent,
                        element: card
                    });
                }
            });

            // Display suggestions
            if (suggestions.length > 0) {
                suggestions.forEach(suggestion => {
                    const suggestionItem = document.createElement('div');
                    suggestionItem.className = 'suggestion-item';
                    suggestionItem.innerHTML = `
                        <div class="suggestion-name">${suggestion.name}</div>
                        <div class="suggestion-price">Rs. ${suggestion.price.replace('$', '')}</div>
                    `;
                    suggestionItem.addEventListener('click', () => {
                        // Scroll to the membership card
                        suggestion.element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        // Highlight the card
                        suggestion.element.style.animation = 'highlight 1s ease';
                        // Clear search and suggestions
                        searchInput.value = '';
                        suggestionsContainer.style.display = 'none';
                    });
                    suggestionsContainer.appendChild(suggestionItem);
                });
                suggestionsContainer.style.display = 'block';
            }
        }

        // Filter membership cards
        membershipCards.forEach(card => {
            const name = card.querySelector('.membership-header h3').textContent.toLowerCase();
            const price = card.querySelector('.membership-price').textContent.toLowerCase();
            const features = Array.from(card.querySelectorAll('.feature-item span')).map(span => span.textContent.toLowerCase());
            
            if (name.includes(searchTerm) || price.includes(searchTerm) || features.some(feature => feature.includes(searchTerm))) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Close suggestions when clicking outside
    document.addEventListener('click', (e) => {
        const suggestionsContainer = document.getElementById('searchSuggestions');
        const searchInput = document.getElementById('searchInput');
        
        if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
            suggestionsContainer.style.display = 'none';
        }
    });

    // Add highlight animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes highlight {
            0% { background-color: #fff; }
            50% { background-color: #f8f9fa; }
            100% { background-color: #fff; }
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html> 
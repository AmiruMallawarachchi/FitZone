<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle item status updates
if (isset($_POST['update_status'])) {
    $item_id = $_POST['item_id'];
    $new_status = $_POST['new_status'];
    
    $update_query = "UPDATE products SET status = ? WHERE product_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $new_status, $item_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Item status updated successfully.";
    } else {
        $_SESSION['error'] = "Error updating item status.";
    }
    
    header('Location: manage_shop.php');
    exit();
}

// Handle item deletion
if (isset($_POST['delete_item'])) {
    $item_id = $_POST['item_id'];
    
    $delete_query = "DELETE FROM products WHERE product_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $item_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Item deleted successfully.";
    } else {
        $_SESSION['error'] = "Error deleting item.";
    }
    
    header('Location: manage_shop.php');
    exit();
}

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
if (!empty($search)) {
    $search = '%' . $search . '%';
    $where_clause = "WHERE name LIKE ? OR category LIKE ? OR description LIKE ?";
}

// Fetch all shop items
$query = "SELECT * FROM products " . $where_clause . " ORDER BY product_id DESC";
$stmt = $conn->prepare($query);
if (!empty($search)) {
    $stmt->bind_param("sss", $search, $search, $search);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Shop - FitZone</title>
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

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-form {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-input {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .search-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            background: var(--primary);
            color: white;
        }

        .add-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .add-btn:hover {
            background: #357abd;
        }

        .items-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .items-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th,
        .items-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .items-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .items-table tr:last-child td {
            border-bottom: none;
        }

        .action-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 5px;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .edit-btn {
            background: var(--warning);
        }

        .delete-btn {
            background: var(--danger);
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background: #e1f7e1;
            color: #2ecc71;
        }

        .status-inactive {
            background: #fde8e8;
            color: #e74c3c;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
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

        .search-container {
            position: relative;
            width: 300px;
        }

        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-top: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            max-height: 300px;
            overflow-y: auto;
            display: none;
        }

        .suggestion-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .suggestion-item:last-child {
            border-bottom: none;
        }

        .suggestion-item:hover {
            background: #f8f9fa;
        }

        .suggestion-name {
            font-weight: 500;
        }

        .suggestion-category {
            color: #666;
            font-size: 0.9em;
        }

        .suggestion-price {
            color: var(--primary);
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">
            <?php 
            require_once 'admin_nav.php';
            echo getAdminNav('manage_shop.php');
            ?>
        </div>
        <div class="main-content">
            <div class="header">
                <h1>Manage Shop Items</h1>
                <div class="header-actions">
                    <div class="search-container">
                        <form action="" method="GET" class="search-form" id="searchForm">
                            <input type="text" name="search" id="searchInput" placeholder="Search items..." value="<?php echo htmlspecialchars($search); ?>" class="search-input" autocomplete="off">
                            <button type="submit" class="search-btn">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                        <div class="search-suggestions" id="searchSuggestions"></div>
                    </div>
                    <a href="add_shop_item.php" class="add-btn">
                        <i class="fas fa-plus"></i>
                        Add New Item
                    </a>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="items-table">
                <table>
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($item = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                                    <td><?php echo $item['stock']; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $item['status']; ?>">
                                            <?php echo ucfirst($item['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="edit_shop_item.php?id=<?php echo $item['product_id']; ?>" class="action-btn edit-btn">
                                            <i class="fas fa-edit"></i>
                                            Edit
                                        </a>
                                        <form action="" method="POST" style="display: inline;">
                                            <input type="hidden" name="item_id" value="<?php echo $item['product_id']; ?>">
                                            <button type="submit" name="delete_item" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this item?')">
                                                <i class="fas fa-trash"></i>
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No shop items found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const searchSuggestions = document.getElementById('searchSuggestions');
            const searchForm = document.getElementById('searchForm');
            let timeoutId;

            searchInput.addEventListener('input', function() {
                clearTimeout(timeoutId);
                const query = this.value.trim();
                
                if (query.length < 2) {
                    searchSuggestions.style.display = 'none';
                    return;
                }

                timeoutId = setTimeout(() => {
                    fetch(`get_shop_suggestions.php?query=${encodeURIComponent(query)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.length > 0) {
                                searchSuggestions.innerHTML = data.map(item => `
                                    <div class="suggestion-item" onclick="window.location.href='manage_shop.php?search=${encodeURIComponent(item.name)}'">
                                        <div>
                                            <div class="suggestion-name">${item.name}</div>
                                            <div class="suggestion-category">${item.category}</div>
                                        </div>
                                        <div class="suggestion-price">$${parseFloat(item.price).toFixed(2)}</div>
                                    </div>
                                `).join('');
                                searchSuggestions.style.display = 'block';
                            } else {
                                searchSuggestions.style.display = 'none';
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching suggestions:', error);
                            searchSuggestions.style.display = 'none';
                        });
                }, 300);
            });

            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
                    searchSuggestions.style.display = 'none';
                }
            });

            // Show suggestions when focusing on input if there's a value
            searchInput.addEventListener('focus', function() {
                if (this.value.trim().length >= 2) {
                    searchSuggestions.style.display = 'block';
                }
            });
        });
    </script>
</body>
</html> 
<?php
function getAdminNav($active_page = '') {
    $nav_items = [
        'index.php' => [
            'icon' => 'fas fa-tachometer-alt',
            'text' => 'Dashboard',
            'color' => '#4a90e2'
        ],
        'manage_members.php' => [
            'icon' => 'fas fa-users',
            'text' => 'Manage Members',
            'color' => '#50c878'
        ],
        'manage_trainers.php' => [
            'icon' => 'fas fa-dumbbell',
            'text' => 'Manage Trainers',
            'color' => '#ff6b6b'
        ],
        'manage_membership.php' => [
            'icon' => 'fas fa-id-card',
            'text' => 'Manage Membership',
            'color' => '#9b59b6'
        ],
        'manage_shop.php' => [
            'icon' => 'fas fa-shopping-cart',
            'text' => 'Manage Shop',
            'color' => '#f39c12'
        ],
        'manage_classes.php' => [
            'icon' => 'fas fa-calendar-alt',
            'text' => 'Manage Classes',
            'color' => '#3498db'
        ],
        'manage_vlogs.php' => [
            'icon' => 'fas fa-video',
            'text' => 'Manage Vlog',
            'color' => '#e74c3c'
        ],
        'manage_queries.php' => [
            'icon' => 'fas fa-question-circle',
            'text' => 'Manage Queries',
            'color' => '#2ecc71'
        ]
    ];

    $output = '<div class="sidebar-header">
        <h2><i class="fas fa-dumbbell"></i> FitZone Admin</h2>
    </div>
    <div class="nav-menu">';

    foreach ($nav_items as $page => $item) {
        $active_class = ($active_page === $page) ? ' active' : '';
        $style = ($active_page === $page) ? ' style="background: ' . $item['color'] . ';"' : '';
        $output .= sprintf(
            '<a href="%s" class="nav-item%s" data-color="%s"%s>
                <i class="%s"></i>
                <span>%s</span>
            </a>',
            $page,
            $active_class,
            $item['color'],
            $style,
            $item['icon'],
            $item['text']
        );
    }

    $output .= '<a href="../logout.php" class="nav-item logout-item">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
    </div>
    
    <style>
        .sidebar-header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .sidebar-header h2 {
            color: white;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .sidebar-header h2 i {
            color: #4a90e2;
            animation: pulse 2s infinite;
        }
        
        .nav-menu {
            margin-top: 20px;
        }
        
        .nav-item {
            padding: 15px;
            margin: 8px 0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }
        
        .nav-item::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background: rgba(255,255,255,0.1);
            transition: width 0.3s ease;
            z-index: 1;
        }
        
        .nav-item:hover::before {
            width: 100%;
        }
        
        .nav-item i {
            width: 20px;
            font-size: 18px;
            transition: transform 0.3s ease;
            z-index: 2;
        }
        
        .nav-item span {
            z-index: 2;
            transition: transform 0.3s ease;
        }
        
        .nav-item:hover i {
            transform: scale(1.2);
        }
        
        .nav-item:hover span {
            transform: translateX(5px);
        }
        
        .nav-item.active {
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .logout-item {
            margin-top: 30px;
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid rgba(231, 76, 60, 0.3);
        }
        
        .logout-item:hover {
            background: rgba(231, 76, 60, 0.4);
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
    </style>
    
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const navItems = document.querySelectorAll(".nav-item:not(.logout-item)");
            
            navItems.forEach(item => {
                item.addEventListener("mouseenter", function() {
                    if (!this.classList.contains("active")) {
                        this.style.background = this.getAttribute("data-color");
                    }
                });
                
                item.addEventListener("mouseleave", function() {
                    if (!this.classList.contains("active")) {
                        this.style.background = "";
                    }
                });
            });
        });
    </script>';

    return $output;
}
?> 
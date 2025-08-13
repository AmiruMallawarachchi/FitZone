// Add hover animations to cards
document.querySelectorAll('.dashboard-card').forEach((card, index) => {
    card.style.animationDelay = `${index * 0.1}s`;
    
    card.addEventListener('mouseenter', () => {
        card.style.transform = 'translateY(-5px)';
    });
    
    card.addEventListener('mouseleave', () => {
        card.style.transform = 'translateY(0)';
    });
});

// Add hover and click effects to action buttons
document.querySelectorAll('.action-button').forEach(button => {
    const icon = button.querySelector('i');
    
    button.addEventListener('mouseenter', () => {
        icon.style.transform = 'scale(1.2)';
    });
    
    button.addEventListener('mouseleave', () => {
        icon.style.transform = 'scale(1)';
    });

    icon.addEventListener('click', (e) => {
        e.preventDefault();
        icon.classList.add('icon-rotate');
        setTimeout(() => {
            icon.classList.remove('icon-rotate');
        }, 500);
    });
});

// Add hover and click effects to stats
document.querySelectorAll('.stat-item').forEach(stat => {
    stat.addEventListener('mouseenter', () => {
        stat.style.transform = 'translateY(-5px)';
    });
    
    stat.addEventListener('mouseleave', () => {
        stat.style.transform = 'translateY(0)';
    });
});

// Add click effects to all icons
document.querySelectorAll('i').forEach(icon => {
    icon.addEventListener('click', function(e) {
        e.preventDefault();
        this.classList.add('icon-rotate');
        setTimeout(() => {
            this.classList.remove('icon-rotate');
        }, 500);
    });
});

// Add hover effects to card icons
document.querySelectorAll('.card-icon').forEach(icon => {
    icon.addEventListener('click', function(e) {
        e.preventDefault();
        this.classList.add('icon-bounce');
        setTimeout(() => {
            this.classList.remove('icon-bounce');
        }, 500);
    });
});

// Add staggered animations to lists
document.querySelectorAll('.appointment-list, .class-list, .membership-list').forEach(list => {
    list.classList.add('stagger-animation');
});

// Add animation to form elements
document.querySelectorAll('form').forEach(form => {
    form.classList.add('fade-in');
});

// Add animation to tables
document.querySelectorAll('table').forEach(table => {
    table.classList.add('fade-in');
});

// Add animation to alerts
document.querySelectorAll('.alert').forEach(alert => {
    alert.classList.add('fade-in');
});

// Add animation to modals
document.querySelectorAll('.modal').forEach(modal => {
    modal.classList.add('fade-in');
});

// Add animation to buttons
document.querySelectorAll('.btn').forEach(button => {
    button.classList.add('fade-in');
});

// Add animation to badges
document.querySelectorAll('.badge').forEach(badge => {
    badge.classList.add('pulse');
}); 
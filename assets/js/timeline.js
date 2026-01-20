/**
 * Timeline Interactions and Mobile Enhancements
 */

document.addEventListener('DOMContentLoaded', function() {
    // Touch-friendly interactions
    enhanceTouchInteractions();
    
    // Swipe gestures for mobile navigation
    initSwipeGestures();
    
    // Optimize timeline for mobile
    optimizeTimelineForMobile();
});

function enhanceTouchInteractions() {
    // Increase touch target sizes on mobile
    if (window.innerWidth <= 768) {
        const buttons = document.querySelectorAll('.btn, .btn-small');
        buttons.forEach(btn => {
            btn.style.minHeight = '44px';
            btn.style.minWidth = '44px';
        });
        
        // Add touch feedback
        buttons.forEach(btn => {
            btn.addEventListener('touchstart', function() {
                this.style.opacity = '0.7';
            });
            btn.addEventListener('touchend', function() {
                this.style.opacity = '1';
            });
        });
    }
}

function initSwipeGestures() {
    let touchStartX = 0;
    let touchEndX = 0;
    
    document.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });
    
    document.addEventListener('touchend', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, { passive: true });
    
    function handleSwipe() {
        const swipeThreshold = 50;
        const diff = touchStartX - touchEndX;
        
        // Swipe left (go back) - only on trip detail page
        if (diff > swipeThreshold && window.location.pathname.includes('trip_detail.php')) {
            const backButton = document.querySelector('.back-button');
            if (backButton) {
                backButton.click();
            }
        }
    }
}

function optimizeTimelineForMobile() {
    if (window.innerWidth <= 768) {
        // Collapse timeline items on mobile with expand/collapse
        // Note: Using .timeline-item-wrapper to match actual HTML structure
        const timelineItems = document.querySelectorAll('.timeline-item-wrapper');
        
        timelineItems.forEach(item => {
            const details = item.querySelectorAll('.timeline-details');
            if (details.length > 2) {
                // Add expand/collapse functionality
                const title = item.querySelector('.timeline-title');
                if (title && !title.querySelector('.expand-toggle')) {
                    const toggle = document.createElement('span');
                    toggle.className = 'expand-toggle';
                    toggle.textContent = ' ▼';
                    toggle.style.cursor = 'pointer';
                    toggle.style.fontSize = '0.8rem';
                    title.appendChild(toggle);
                    
                    let expanded = false;
                    const extraDetails = Array.from(details).slice(2);
                    
                    extraDetails.forEach(detail => {
                        detail.style.display = 'none';
                    });
                    
                    toggle.addEventListener('click', function(e) {
                        e.stopPropagation();
                        expanded = !expanded;
                        toggle.textContent = expanded ? ' ▲' : ' ▼';
                        extraDetails.forEach(detail => {
                            detail.style.display = expanded ? '' : 'none';
                        });
                    });
                }
            }
        });
    }
}

// Prevent zoom on double tap (iOS)
let lastTouchEnd = 0;
document.addEventListener('touchend', function(event) {
    const now = Date.now();
    if (now - lastTouchEnd <= 300) {
        event.preventDefault();
    }
    lastTouchEnd = now;
}, false);

// Optimize image loading for mobile
if (window.innerWidth <= 768) {
    const images = document.querySelectorAll('img');
    images.forEach(img => {
        img.loading = 'lazy';
    });
}



/**
 * Main JavaScript for Travel Planner
 */

// Set active nav item based on current page
document.addEventListener('DOMContentLoaded', function() {
    const currentPath = window.location.pathname;
    const currentSearch = window.location.search;
    const navItems = document.querySelectorAll('.nav-item');
    
    // First, check for "Add Trip" specifically (most specific match)
    const hasActionAdd = currentSearch.includes('action=add');
    
    navItems.forEach(item => {
        const href = item.getAttribute('href');
        if (!href) return;
        
        // Remove any existing active class first
        item.classList.remove('active');
        
        // Extract the page name from href (e.g., 'dashboard.php' or 'profile.php')
        const hrefPage = href.split('?')[0].split('/').pop();
        const hrefHasAction = href.includes('action=add');
        const currentPage = currentPath.split('/').pop();
        
        // Special case for "Add Trip" - must match exactly
        if (hrefHasAction && hasActionAdd) {
            item.classList.add('active');
            return; // Don't check other conditions
        }
        
        // If we're on Add Trip page, don't activate regular dashboard link
        if (hasActionAdd && hrefPage === 'dashboard.php' && !hrefHasAction) {
            return; // Skip this item
        }
        
        // Check if current page matches
        if (currentPage === hrefPage) {
            // For dashboard.php, only activate if NOT on action=add
            if (hrefPage === 'dashboard.php' && !hasActionAdd && !hrefHasAction) {
                item.classList.add('active');
            } else if (hrefPage !== 'dashboard.php') {
                // For other pages (like profile.php), activate normally
                item.classList.add('active');
            }
        }
        
        // Special case: trip_detail.php should activate "Trips" (dashboard)
        if (currentPage === 'trip_detail.php' && hrefPage === 'dashboard.php' && !hrefHasAction && !hasActionAdd) {
            item.classList.add('active');
        }
    });
    
    // Handle form submissions with loading states
    const forms = document.querySelectorAll('form[data-async]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Loading...';
            }
        });
    });
    
    // Back to Top Button Functionality
    const backToTopButton = document.getElementById('backToTop');
    if (backToTopButton) {
        // Add class to body if bottom nav is not present
        const bottomNav = document.querySelector('.bottom-nav');
        if (!bottomNav) {
            document.body.classList.add('no-bottom-nav');
        }
        
        // Show/hide button based on scroll position
        function toggleBackToTop() {
            if (window.pageYOffset > 300) {
                backToTopButton.classList.add('show');
            } else {
                backToTopButton.classList.remove('show');
            }
        }
        
        // Initial check
        toggleBackToTop();
        
        // Listen for scroll events (throttled for performance)
        let scrollTimeout;
        window.addEventListener('scroll', function() {
            if (scrollTimeout) {
                window.cancelAnimationFrame(scrollTimeout);
            }
            scrollTimeout = window.requestAnimationFrame(toggleBackToTop);
        });
        
        // Smooth scroll to top on click
        backToTopButton.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
});

// Utility function for API calls
// Note: Currently unused - consider using this for consistency or remove
async function apiCall(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        return data;
    } catch (error) {
        if (typeof console !== 'undefined' && console.error) {
            console.error('API call failed:', error);
        }
        throw error;
    }
}



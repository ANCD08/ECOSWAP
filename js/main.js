document.addEventListener('DOMContentLoaded', function() {
    // Image gallery functionality
    const thumbnails = document.querySelectorAll('.thumbnail');
    const mainImage = document.querySelector('.main-image');
    
    if (thumbnails.length && mainImage) {
        thumbnails.forEach(thumb => {
            thumb.addEventListener('click', function() {
                mainImage.src = this.src;
            });
        });
    }
    
    // Swap request modal
    const requestSwapBtn = document.getElementById('requestSwapBtn');
    const swapModal = document.getElementById('swapModal');
    const closeModal = document.querySelector('.close-modal');
    
    if (requestSwapBtn && swapModal) {
        requestSwapBtn.addEventListener('click', function() {
            swapModal.style.display = 'block';
        });
        
        closeModal.addEventListener('click', function() {
            swapModal.style.display = 'none';
        });
        
        window.addEventListener('click', function(e) {
            if (e.target === swapModal) {
                swapModal.style.display = 'none';
            }
        });
    }
    
    // Form submission handling
    const forms = document.querySelectorAll('form:not(#loginForm):not(#signupForm)');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Add any additional form validation here
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = 'red';
                } else {
                    field.style.borderColor = '';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields');
            }
        });
    });
    
    // Rating stars
    const ratingStars = document.querySelectorAll('.rating-star');
    ratingStars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = this.getAttribute('data-rating');
            const ratingInput = document.getElementById('ratingInput');
            if (ratingInput) {
                ratingInput.value = rating;
            }
            
            // Update star display
            ratingStars.forEach(s => {
                if (s.getAttribute('data-rating') <= rating) {
                    s.textContent = '★';
                    s.style.color = '#FFC107';
                } else {
                    s.textContent = '☆';
                    s.style.color = '#ccc';
                }
            });
        });
    });
    
    // Category filter
    const categoryFilter = document.getElementById('categoryFilter');
    if (categoryFilter) {
        categoryFilter.addEventListener('change', function() {
            const selectedCategory = this.value;
            const items = document.querySelectorAll('.item-card');
            
            items.forEach(item => {
                const itemCategory = item.getAttribute('data-category');
                if (selectedCategory === 'all' || itemCategory === selectedCategory) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
});
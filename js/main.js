// PrimeCast Main JavaScript - E-transfer Only Version

// ============================================
// UTILITY FUNCTIONS
// ============================================

/**
 * Generate order reference
 */
function generateReference() {
    const timestamp = Date.now();
    const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
    return `PC-${timestamp}-${random}`;
}

/**
 * Validate email address
 */
function validateEmailAddress(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Sanitize input to prevent XSS
 */
function sanitizeInput(input) {
    const temp = document.createElement('div');
    temp.textContent = input;
    return temp.innerHTML;
}

// ============================================
// MOBILE MENU
// ============================================

const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const navMenu = document.getElementById('navMenu');

if (mobileMenuBtn && navMenu) {
    mobileMenuBtn.addEventListener('click', () => {
        navMenu.classList.toggle('active');
        mobileMenuBtn.classList.toggle('active');
    });
}

// Close mobile menu when clicking a link
const navLinks = document.querySelectorAll('.nav-menu a');
navLinks.forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            navMenu.classList.remove('active');
            mobileMenuBtn.classList.remove('active');
        }
    });
});

// ============================================
// NAVBAR SCROLL EFFECT
// ============================================

let lastScroll = 0;
const navbar = document.querySelector('.nav-bar');

if (navbar) {
    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;
        
        if (currentScroll > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
        
        lastScroll = currentScroll;
    });
}

// ============================================
// ACTIVE NAVIGATION LINK
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    const navLinks = document.querySelectorAll('.nav-menu a');
    
    navLinks.forEach(link => {
        link.classList.remove('active');
        const linkPage = link.getAttribute('href');
        if (linkPage === currentPage) {
            link.classList.add('active');
        }
    });
});

// ============================================
// FAQ TOGGLE
// ============================================

const faqItems = document.querySelectorAll('.faq-item');
faqItems.forEach(item => {
    const question = item.querySelector('.faq-question');
    if (question) {
        question.addEventListener('click', () => {
            // Close other items
            faqItems.forEach(otherItem => {
                if (otherItem !== item) {
                    otherItem.classList.remove('active');
                }
            });
            // Toggle current item
            item.classList.toggle('active');
        });
    }
});

// ============================================
// CONTACT FORM SUBMISSION (WITH CSRF)
// ============================================

const contactForm = document.getElementById('contactForm');
if (contactForm) {
    contactForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const submitBtn = document.getElementById('submitBtn');
        const successMessage = document.getElementById('successMessage');
        const errorMessage = document.getElementById('errorMessage');
        const errorText = document.getElementById('errorText');
        const originalText = submitBtn.textContent;
        
        // Hide previous messages
        if (successMessage) successMessage.style.display = 'none';
        if (errorMessage) errorMessage.style.display = 'none';
        
        // Validate all fields
        const nameValid = typeof validateName === 'function' ? validateName() : true;
        const emailValid = typeof validateEmail === 'function' ? validateEmail() : true;
        const subjectValid = typeof validateSubject === 'function' ? validateSubject() : true;
        const messageValid = typeof validateMessage === 'function' ? validateMessage() : true;
        
        if (!nameValid || !emailValid || !subjectValid || !messageValid) {
            if (errorMessage && errorText) {
                errorText.textContent = 'Please fix the errors above before submitting.';
                errorMessage.style.display = 'block';
            }
            return;
        }
        
        // Check CSRF token
        const csrfToken = document.getElementById('csrfToken');
        if (!csrfToken || !csrfToken.value) {
            if (errorMessage && errorText) {
                errorText.textContent = 'Security token missing. Please refresh the page and try again.';
                errorMessage.style.display = 'block';
            }
            return;
        }
        
        // Show loading state
        submitBtn.textContent = 'Sending...';
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        
        const formData = new FormData(contactForm);
        
        try {
            const response = await fetch('php/contact.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success message
                if (successMessage) {
                    successMessage.style.display = 'block';
                    successMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
                
                // Reset form
                contactForm.reset();
                
                // Remove success classes from inputs
                document.querySelectorAll('.form-group input, .form-group textarea').forEach(input => {
                    input.classList.remove('success', 'error');
                });
                
                // Refresh CSRF token
                try {
                    const tokenResponse = await fetch('php/get_csrf_token.php');
                    const tokenData = await tokenResponse.json();
                    if (tokenData.success && csrfToken) {
                        csrfToken.value = tokenData.token;
                    }
                } catch (tokenError) {
                    console.error('Failed to refresh CSRF token:', tokenError);
                }
            } else {
                throw new Error(result.message || 'Failed to send message');
            }
        } catch (error) {
            console.error('Contact form error:', error);
            
            if (errorMessage && errorText) {
                errorText.textContent = error.message || 'Sorry, there was an error. Please email us directly at info@primecast.world';
                errorMessage.style.display = 'block';
                errorMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                alert('Sorry, there was an error. Please email us directly at info@primecast.world');
            }
        } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
            submitBtn.classList.remove('loading');
        }
    });
}

// ============================================
// SMOOTH SCROLL FOR ANCHOR LINKS
// ============================================

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;
        
        const target = document.querySelector(targetId);
        if (target) {
            e.preventDefault();
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// ============================================
// ANIMATION ON SCROLL
// ============================================

const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

// Observe elements
document.querySelectorAll('.feature-card, .pricing-card').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(30px)';
    el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    observer.observe(el);
});

// ============================================
// ACCESSIBILITY IMPROVEMENTS
// ============================================

// Trap focus in mobile menu when open
if (navMenu && mobileMenuBtn) {
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && navMenu.classList.contains('active')) {
            navMenu.classList.remove('active');
            mobileMenuBtn.classList.remove('active');
            mobileMenuBtn.focus();
        }
    });
}

// Add keyboard navigation for FAQ items
faqItems.forEach(item => {
    const question = item.querySelector('.faq-question');
    if (question) {
        question.setAttribute('tabindex', '0');
        question.setAttribute('role', 'button');
        question.setAttribute('aria-expanded', 'false');
        
        question.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                question.click();
            }
        });
        
        // Update aria-expanded when toggled
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'class') {
                    const isActive = item.classList.contains('active');
                    question.setAttribute('aria-expanded', isActive.toString());
                }
            });
        });
        
        observer.observe(item, { attributes: true });
    }
});

// ============================================
// ERROR LOGGING
// ============================================

window.addEventListener('error', (e) => {
    console.error('JavaScript Error:', {
        message: e.message,
        source: e.filename,
        line: e.lineno,
        column: e.colno
    });
});

window.addEventListener('unhandledrejection', (e) => {
    console.error('Unhandled Promise Rejection:', e.reason);
});

// Mobile Menu Toggle
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const navMenu = document.getElementById('navMenu');

if (mobileMenuBtn) {
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

// Navbar scroll effect
let lastScroll = 0;
const navbar = document.querySelector('.nav-bar');

window.addEventListener('scroll', () => {
    const currentScroll = window.pageYOffset;
    
    if (currentScroll > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
    
    lastScroll = currentScroll;
});

// Set active navigation link based on current page
document.addEventListener('DOMContentLoaded', () => {
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    const navLinks = document.querySelectorAll('.nav-menu a');
    
    navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === currentPage) {
            link.classList.add('active');
        }
    });
});

// FAQ Toggle
const faqItems = document.querySelectorAll('.faq-item');
faqItems.forEach(item => {
    const question = item.querySelector('.faq-question');
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
});

// Copy Email Function
function copyEmail() {
    const emailInput = document.getElementById('etransferEmail');
    if (emailInput) {
        emailInput.select();
        emailInput.setSelectionRange(0, 99999); // For mobile
        
        navigator.clipboard.writeText(emailInput.value).then(() => {
            const copyBtn = document.getElementById('copyEmailBtn');
            const originalText = copyBtn.textContent;
            copyBtn.textContent = 'Copied!';
            copyBtn.style.background = '#4CAF50';
            
            setTimeout(() => {
                copyBtn.textContent = originalText;
                copyBtn.style.background = '';
            }, 2000);
        });
    }
}

// Generate Order Reference
function generateReference() {
    const timestamp = Date.now();
    const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
    return `PC-${timestamp}-${random}`;
}

// Display reference on checkout page
document.addEventListener('DOMContentLoaded', () => {
    const referenceDisplay = document.getElementById('orderReference');
    if (referenceDisplay) {
        const reference = generateReference();
        referenceDisplay.textContent = reference;
        
        // Store reference for later use
        sessionStorage.setItem('orderReference', reference);
    }
});

// Contact Form Submission
const contactForm = document.getElementById('contactForm');
if (contactForm) {
    contactForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const submitBtn = contactForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Sending...';
        submitBtn.disabled = true;
        
        const formData = new FormData(contactForm);
        
        try {
            const response = await fetch('php/contact.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('Thank you! Your message has been sent successfully.');
                contactForm.reset();
            } else {
                alert('Sorry, there was an error sending your message. Please try again or email us directly at info@primecast.world');
            }
        } catch (error) {
            alert('Sorry, there was an error. Please email us directly at info@primecast.world');
        } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    });
}

// PayPal Button Configuration
function initPayPalButtons() {
    const plans = {
        '1month': { price: '25.00', plan: '1-Month' },
        '3month': { price: '60.00', plan: '3-Month' },
        '12month': { price: '150.00', plan: '12-Month' }
    };
    
    Object.keys(plans).forEach(planId => {
        const container = document.getElementById(`paypal-button-${planId}`);
        if (container && window.paypal) {
            window.paypal.Buttons({
                createOrder: function(data, actions) {
                    return actions.order.create({
                        purchase_units: [{
                            description: `PrimeCast ${plans[planId].plan} Subscription`,
                            amount: {
                                value: plans[planId].price
                            }
                        }]
                    });
                },
                onApprove: async function(data, actions) {
                    const order = await actions.order.capture();
                    
                    // Get customer email from PayPal
                    const email = order.payer.email_address;
                    const reference = sessionStorage.getItem('orderReference') || generateReference();
                    
                    // Send to activation script
                    try {
                        const response = await fetch('php/activate.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                email: email,
                                plan: plans[planId].plan,
                                reference: reference,
                                transaction_id: order.id,
                                amount: plans[planId].price
                            })
                        });
                        
                        if (response.ok) {
                            window.location.href = 'thankyou.html';
                        } else {
                            alert('Payment successful but there was an issue with activation. We will contact you shortly.');
                        }
                    } catch (error) {
                        alert('Payment successful! You will receive a confirmation email shortly.');
                        setTimeout(() => {
                            window.location.href = 'thankyou.html';
                        }, 3000);
                    }
                },
                onError: function(err) {
                    console.error('PayPal Error:', err);
                    alert('There was an error processing your payment. Please try again or use E-transfer.');
                }
            }).render(`#paypal-button-${planId}`);
        }
    });
}

// Initialize PayPal when script loads
if (document.querySelector('[id^="paypal-button-"]')) {
    if (window.paypal) {
        initPayPalButtons();
    } else {
        console.warn('PayPal SDK not loaded. Please add your Client ID to checkout.html');
    }
}

// E-transfer Form Submission
const etransferForm = document.getElementById('etransferForm');
if (etransferForm) {
    etransferForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const email = document.getElementById('customerEmail').value;
        const plan = document.getElementById('selectedPlan').value;
        const reference = sessionStorage.getItem('orderReference') || generateReference();
        
        try {
            const response = await fetch('php/activate.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: email,
                    plan: plan,
                    reference: reference,
                    payment_method: 'etransfer'
                })
            });
            
            if (response.ok) {
                window.location.href = 'thankyou.html';
            } else {
                alert('There was an issue submitting your order. Please contact us at info@primecast.world');
            }
        } catch (error) {
            alert('Your order has been received. You will receive a confirmation email shortly.');
            setTimeout(() => {
                window.location.href = 'thankyou.html';
            }, 2000);
        }
    });
}

// Smooth Scroll
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Animation on Scroll
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

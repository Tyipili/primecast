# PrimeCast IPTV Website System

Complete, fully functional IPTV website with payment processing and admin dashboard.

## 📁 Complete File Structure

```
primecast/
├── index.html
├── pricing.html
├── checkout.html
├── channels.html
├── movies.html
├── faq.html
├── contact.html
├── thankyou.html
├── css/
│   └── style.css
├── js/
│   └── main.js
├── php/
│   ├── activate.php
│   ├── contact.php
│   └── payment_log.txt (auto-generated)
└── admin/
    ├── login.php
    ├── dashboard.php
    └── .htpasswd (auto-generated)
```

## 🚀 Installation Instructions

### 1. Upload Files to Your Server
Upload all files to your web hosting server via FTP or cPanel File Manager.

### 2. Set Permissions
```bash
chmod 755 php/
chmod 666 php/payment_log.txt (will be created automatically)
chmod 755 admin/
chmod 600 admin/.htpasswd (will be created automatically)
```

### 3. PayPal Integration Setup

**IMPORTANT:** Edit `checkout.html` line 8:

```html
<!-- Replace YOUR_CLIENT_ID_HERE with your actual PayPal Client ID -->
<script src="https://www.paypal.com/sdk/js?client-id=YOUR_CLIENT_ID_HERE&currency=USD"></script>
```

**How to get PayPal Client ID:**
1. Go to https://developer.paypal.com
2. Log in with your PayPal Business account
3. Click "Apps & Credentials"
4. Create a new app or use existing
5. Copy the "Client ID"
6. Replace `YOUR_CLIENT_ID_HERE` in checkout.html

### 4. Email Configuration

**PHP Mail Function:**
The system uses PHP's built-in `mail()` function. This works on most shared hosting.

**If emails don't send, you may need to:**
- Contact your hosting provider to enable PHP mail
- Or use SMTP (requires modifying PHP files)

**To test email functionality:**
```php
<?php
mail('your@email.com', 'Test', 'This is a test email');
?>
```

### 5. Admin Dashboard Access

**Default Login Credentials:**
- Username: `admin`
- Password: `primecast2024`

**Access URL:**
```
https://yourdomain.com/admin/login.php
```

**IMPORTANT: Change Default Password**

Edit `admin/.htpasswd` after first login or create it manually:

```php
<?php
$credentials = [
    'username' => 'admin',
    'password' => password_hash('YOUR_NEW_PASSWORD', PASSWORD_DEFAULT)
];
file_put_contents('.htpasswd', json_encode($credentials));
?>
```

### 6. Domain Configuration

Update these email addresses throughout the site:
- `info@primecast.world` → Your actual email

Files to update:
- All HTML footer sections
- `php/activate.php`
- `php/contact.php`
- `checkout.html`

## 🎨 Customization Guide

### Change Colors
Edit `css/style.css` root variables:
```css
:root {
    --gold: #C49B2A;        /* Primary gold color */
    --neon-blue: #00E5FF;   /* Neon blue accent */
    --dark-blue: #0091EA;   /* Dark blue gradient */
    --dark-bg: #0a0a0a;     /* Background color */
}
```

### Update Prices
Edit `pricing.html` and `checkout.html`:
- Change dollar amounts in pricing cards
- Update PayPal prices in `js/main.js`

### Modify Content
All content is in plain HTML and easily editable:
- Channel descriptions: `channels.html`
- Movie content: `movies.html`
- FAQ answers: `faq.html`

## 📧 How the Payment System Works

### 1. Customer Places Order
- Customer selects plan on `pricing.html`
- Redirected to `checkout.html`
- Order reference generated (timestamp-based)

### 2. Payment Options

**PayPal/Credit Card:**
1. Customer clicks PayPal button
2. Completes payment through PayPal
3. `activate.php` receives payment data
4. Confirmation email sent automatically
5. Payment logged to `payment_log.txt`
6. Redirect to `thankyou.html`

**E-transfer (Canada):**
1. Customer sends e-transfer to info@primecast.world
2. Includes order reference in message
3. Clicks "I've Sent the E-transfer"
4. `activate.php` logs the order
5. Confirmation email sent
6. Admin manually verifies e-transfer in email
7. Admin sends login credentials manually

### 3. Admin Processes Orders
1. Login to admin dashboard
2. View all payments in real-time
3. See customer email, plan, reference
4. Manually send login credentials via email

## 🔒 Security Features

### Session Management
- Admin dashboard uses PHP sessions
- Auto-logout on browser close
- Secure password hashing (bcrypt)

### Password Storage
- Passwords stored as bcrypt hashes
- Never stored in plain text
- Salted automatically

### File Security
- `.htpasswd` file protected
- Payment log not web-accessible
- Admin area requires authentication

### Recommended Additional Security
Add to `.htaccess` in admin folder:
```apache
# Restrict admin access by IP (optional)
<Files "dashboard.php">
    Order deny,allow
    Deny from all
    Allow from YOUR.IP.ADDRESS
</Files>

# Prevent directory listing
Options -Indexes

# Protect sensitive files
<FilesMatch "^\.htpasswd$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

## 📱 Mobile Responsive

The entire site is fully responsive:
- Hamburger menu on mobile
- Touch-friendly buttons
- Optimized layouts for all screen sizes
- Tested on iOS and Android

## 🧪 Testing Checklist

### Before Going Live:
- [ ] PayPal Client ID configured
- [ ] Test PayPal payment (use sandbox mode first)
- [ ] Test email delivery
- [ ] Test contact form
- [ ] Change default admin password
- [ ] Update all email addresses
- [ ] Test on mobile devices
- [ ] Test admin dashboard login
- [ ] Verify payment log is created
- [ ] Test all navigation links

### PayPal Sandbox Testing:
1. Create sandbox account at developer.paypal.com
2. Use sandbox Client ID in checkout.html
3. Use test PayPal accounts for payments
4. Switch to live Client ID when ready

## 🛠️ Troubleshooting

### Emails Not Sending
**Solution:**
- Check hosting supports PHP mail()
- Verify email addresses are correct
- Check spam folder
- Contact hosting support
- Consider using SMTP

### PayPal Button Not Showing
**Solution:**
- Check Client ID is correct
- Open browser console for errors
- Verify internet connection
- Check PayPal SDK URL is accessible

### Admin Login Not Working
**Solution:**
- Check .htpasswd file exists
- Verify file permissions
- Check PHP sessions are enabled
- Clear browser cookies

### Payment Log Not Writing
**Solution:**
- Check folder permissions (666)
- Verify PHP can write files
- Check disk space
- Review error logs

### Checkout Page Errors
**Solution:**
- Check PHP errors in browser console
- Verify activate.php has correct permissions
- Check file paths are correct
- Review server error logs

## 📊 Admin Dashboard Features

### View All Payments
- Real-time payment tracking
- Customer email addresses
- Order references
- Transaction IDs
- Payment amounts
- Payment methods

### Statistics Dashboard
- Total payments count
- Total revenue
- Today's payments
- Today's revenue

### Export Data
Currently manual - copy from dashboard. Future updates can add CSV export.

## 🔄 Regular Maintenance

### Daily Tasks:
- Check admin dashboard for new orders
- Send login credentials to new customers
- Respond to support emails

### Weekly Tasks:
- Review payment log
- Backup payment_log.txt
- Check for any issues

### Monthly Tasks:
- Update content if needed
- Review pricing
- Backup entire site

## 📞 Support & Contact

For questions about this code:
- Review this README thoroughly
- Check troubleshooting section
- Test on local server first

For PrimeCast customer support:
- info@primecast.world

## 🎯 Features Summary

✅ Modern, responsive design
✅ Animated neon grid effects
✅ PayPal integration
✅ E-transfer support (Canada)
✅ Automatic email confirmations
✅ Admin dashboard with statistics
✅ Mobile-friendly navigation
✅ Contact form
✅ FAQ with expandable sections
✅ Payment logging system
✅ Secure authentication
✅ Order reference generation
✅ Multi-device compatible

## 📝 License & Usage

This code is provided for educational purposes. Ensure you have proper licensing for any IPTV content you stream. It is the responsibility of the website owner to comply with all applicable laws and regulations.

## 🚨 Important Legal Notice

IPTV services must comply with copyright laws and content licensing requirements in your jurisdiction. This code provides the technical infrastructure but does NOT include:
- Content licenses
- Streaming server
- IPTV backend
- Content delivery network

You must provide your own legal content sources and streaming infrastructure.

---

## Quick Start Summary

1. Upload all files to server
2. Edit `checkout.html` - add PayPal Client ID
3. Update email addresses to your domain
4. Set file permissions
5. Change admin password
6. Test payment flow
7. Go live!

**Default Admin Access:**
- URL: https://yourdomain.com/admin/login.php
- Username: admin
- Password: primecast2024

**Remember to change the password immediately after first login!**

---

Good luck with your PrimeCast website! 🎬✨
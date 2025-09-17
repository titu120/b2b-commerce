# Email Setup for B2B Commerce

## Current Status
Email functionality has been **disabled** for localhost/XAMPP compatibility. The response button now only saves responses to the database without sending emails.

## Quick Fix (Current Setup)
- ✅ Response button works and saves responses
- ✅ No email errors on localhost
- ✅ Responses are stored and viewable in admin panel
- ❌ No email notifications sent to customers

## To Enable Email Functionality

### Option 1: Gmail SMTP (Recommended for Testing)

1. **Enable 2-Factor Authentication** on your Gmail account
2. **Generate App Password**:
   - Go to Google Account Settings
   - Security > 2-Step Verification > App Passwords
   - Generate a new app password for "Mail"
3. **Configure the plugin**:
   - Edit `wp-content/plugins/b2b-commerce-pro/includes/email-config.php`
   - Uncomment the Gmail configuration section
   - Replace `your-email@gmail.com` with your Gmail address
   - Replace `your-app-password` with the generated app password
   - Set `B2B_ENABLE_SMTP` to `true`

### Option 2: SendGrid (Free Tier Available)

1. **Sign up** for a free SendGrid account
2. **Verify your sender email** address
3. **Get your API key** from SendGrid dashboard
4. **Configure the plugin**:
   - Edit `wp-content/plugins/b2b-commerce-pro/includes/email-config.php`
   - Uncomment the SendGrid configuration section
   - Replace `your-sendgrid-api-key` with your actual API key
   - Replace `your-verified-sender@yourdomain.com` with your verified email
   - Set `B2B_ENABLE_SMTP` to `true`

### Option 3: Mailgun (Free Tier Available)

1. **Sign up** for a free Mailgun account
2. **Verify your domain** or use the sandbox domain
3. **Get your SMTP credentials** from Mailgun dashboard
4. **Configure the plugin**:
   - Edit `wp-content/plugins/b2b-commerce-pro/includes/email-config.php`
   - Uncomment the Mailgun configuration section
   - Replace credentials with your actual Mailgun SMTP details
   - Set `B2B_ENABLE_SMTP` to `true`

## Testing Email Functionality

1. **Configure your chosen email service** (see options above)
2. **Test with a real inquiry**:
   - Create a test inquiry from the frontend
   - Go to admin panel > B2B Inquiries
   - Click "Respond" on the inquiry
   - Write a response and click "Save Response"
   - Check if email is received

## Troubleshooting

### Common Issues:

1. **"Email not sending"**
   - Check SMTP credentials
   - Verify sender email is authorized
   - Check firewall/antivirus blocking SMTP

2. **"Authentication failed"**
   - For Gmail: Use App Password, not regular password
   - For SendGrid: Use API key, not account password
   - Check if 2FA is enabled (Gmail)

3. **"Connection timeout"**
   - Check internet connection
   - Verify SMTP host and port
   - Check if your hosting provider blocks SMTP

### Debug Mode:
To enable email debugging, add this to your `wp-config.php`:
```php
define('SMTP_DEBUG', true);
```

## Alternative Solutions

### For Development Only:
If you only need email functionality for development/testing:

1. **Use Mailtrap.io** (free email testing service)
2. **Use MailHog** (local email testing tool)
3. **Use Gmail with App Passwords** (as described above)

### For Production:
1. **Use your hosting provider's SMTP** settings
2. **Use a dedicated email service** (SendGrid, Mailgun, etc.)
3. **Configure WordPress SMTP plugin** (like WP Mail SMTP)

## Current Configuration

The plugin is currently configured to:
- ✅ Save responses to database
- ✅ Display responses in admin panel
- ✅ Allow viewing response history
- ❌ Send email notifications (disabled for localhost)

To re-enable email functionality, follow the setup instructions above.

# Security Policy

## Supported Versions

| Version | Supported |
| ------- | --------- |
| 0.9.8+  | ✅ Yes    |
| < 0.9.8 | ❌ No     |

## Reporting a Vulnerability

### 🚨 Please Do NOT Report Security Issues Publicly

To protect our users, **do not** open a public issue for security vulnerabilities.

### How to Report

**Preferred Method**: Use GitHub's private vulnerability reporting

1. Go to our [Security Advisories](https://github.com/firstelementjp/swift-csv/security/advisories)
2. Click "Report a vulnerability"
3. Fill in the details with as much information as possible

**Alternative Method**: Email us directly

- Send to: security@firstelement.co.jp
- Use subject: `[Swift CSV Security] Brief description of issue`

### What to Include

Please include:

- **Type of vulnerability** (XSS, SQL injection, CSRF, etc.)
- **Affected versions** of the plugin
- **Steps to reproduce** the issue
- **Proof of concept** if available
- **Potential impact** assessment
- **Suggested mitigation** (if you have one)

### Response Timeline

- **Initial response**: Within 48 hours
- **Assessment**: Within 5 business days
- **Resolution**: As soon as possible, based on severity
- **Public disclosure**: After fix is released and users have had time to update

### Security Best Practices

#### For Users

- **Keep updated**: Always use the latest version
- **Review permissions**: Only grant import/export permissions to trusted users
- **Backup data**: Before importing large datasets
- **Review CSV files**: Ensure CSV files are from trusted sources

#### For Developers

- **Validate input**: All CSV data should be properly sanitized
- **Use nonce**: Protect AJAX requests with WordPress nonces
- **Check capabilities**: Verify user permissions before operations
- **Escape output**: Use WordPress escaping functions

#### For Site Administrators

- **Regular updates**: Enable automatic plugin updates
- **User roles**: Limit import/export capabilities to necessary users
- **Monitor logs**: Check import/export logs for suspicious activity
- **Backup strategy**: Maintain regular site backups

### Security Features in Swift CSV

Swift CSV includes several security measures:

- **WordPress nonce protection** for all AJAX requests
- **Capability checking** for import/export operations
- **Input sanitization** for CSV data
- **File type validation** for uploaded CSV files
- **Memory limits** to prevent resource exhaustion
- **Batch processing** to avoid timeout issues

### Known Limitations

- **Large file processing**: Very large CSV files may require increased memory limits
- **User permissions**: Plugin respects WordPress user roles, which should be properly configured
- **Third-party dependencies**: Security depends on WordPress core security

### Security Updates

Security updates are:

- **Priority**: Released as soon as possible
- **Backported**: To supported minor versions when necessary
- **Documented**: In release notes with security implications

### Acknowledgments

We thank security researchers who help us keep Swift CSV secure. All valid security reports will be acknowledged in our release notes (with reporter's permission).

### Legal

This security policy is provided as-is without warranty. We reserve the right to modify this policy at any time.

---

**Remember**: If you discover a security vulnerability, please report it privately first. This helps us protect all our users while we work on a fix.

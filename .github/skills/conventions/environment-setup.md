# Environment Setup Conventions

## Overview

This document outlines the environment setup conventions for Swift CSV development, focusing on the integration between `.envrc`, `wp-config.php`, and secure API key management.

## direnv + wp-config.php Pattern

### Standard Pattern

Use `.envrc` for environment variables and `wp-config.php` to convert them to WordPress constants:

```bash
# .envrc (never commit this file)
export OPENAI_API_KEY="sk-your-api-key-here"
export AI_MODEL="gpt-4"
export AI_TEMPERATURE="0.7"
```

```php
// wp-config.php
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
define('AI_MODEL', getenv('AI_MODEL') ?: 'gpt-3.5-turbo');
define('AI_TEMPERATURE', (float)(getenv('AI_TEMPERATURE') ?: '0.7'));
```

```php
// Plugin code
if (defined('OPENAI_API_KEY') && !empty(OPENAI_API_KEY)) {
    $response = $this->call_ai_api(OPENAI_API_KEY, AI_MODEL);
}
```

### Why This Pattern?

1. **Security**: API keys never committed to Git
2. **Flexibility**: Easy to change values without touching code
3. **WordPress Standard**: Uses familiar constant pattern
4. **Testing**: Easy to override values in tests
5. **Team Sharing**: `.envrc.example` provides template

## Security Guidelines

### ✅ Do

- Use `.envrc.example` for templates
- Add `.envrc` to `.gitignore`
- Use environment-specific values
- Test with different configurations

### ❌ Don't

- Commit actual API keys
- Hard-code values in plugin files
- Use `getenv()` directly in plugin code
- Share sensitive values in team chats

## File Structure

```
project/
├── .envrc.example         # Template (commit)
├── .envrc                 # Actual values (don't commit)
├── .gitignore             # Includes .envrc
├── wp-config.php          # Converts env vars to constants
└── plugin-files/          # Use constants only
```

## Development Workflow

### 1. Initial Setup

```bash
# Copy template
cp .envrc.example .envrc

# Edit with actual values
nano .envrc

# Allow direnv
direnv allow
```

### 2. Testing Environment Variables

```bash
# Verify variables are set
env | grep -E "(API_KEY|AI_)"

# Test WordPress constants
wp --path="$WP_PATH" eval "
echo 'API Key: ' . OPENAI_API_KEY . PHP_EOL;
echo 'Model: ' . AI_MODEL . PHP_EOL;
"
```

### 3. Development

```bash
# Change values without touching code
export AI_MODEL="gpt-4-turbo"

# Test immediately
wp swift-csv test-ai
```

## AI Development Example

### Phase 1: Backend Testing (No UI)

```bash
# .envrc
export OPENAI_API_KEY="sk-test-key"
export AI_MODEL="gpt-4"
export AI_TEMPERATURE="0.7"
```

```php
// AI processor class
class Swift_CSV_AI_Processor {
    public function test_connection() {
        $api_key = OPENAI_API_KEY;
        $model = AI_MODEL;

        return $this->call_api($api_key, $model, "Test connection");
    }
}
```

### Phase 2: PHPUnit Testing

```php
// tests/Unit/AITest.php
public function test_ai_connection() {
    $processor = new Swift_CSV_AI_Processor();
    $result = $processor->test_connection();

    $this->assertNotEmpty($result);
}
```

### Phase 3: WP-CLI Testing

```bash
# Terminal testing
wp swift-csv test-ai --provider=openai
wp swift-csv test-ai --provider=anthropic
```

### Phase 4: UI Implementation

```php
// Admin interface (constants already available)
if (defined('OPENAI_API_KEY') && !empty(OPENAI_API_KEY)) {
    // Show AI features
}
```

## Best Practices

### Environment Variable Naming

- Use `UPPER_CASE` with underscores
- Include project prefix for common names: `SWIFT_CSV_AI_MODEL`
- Group related variables: `OPENAI_*`, `ANTHROPIC_*`

### Default Values

```php
// Provide sensible defaults
define('AI_MODEL', getenv('AI_MODEL') ?: 'gpt-3.5-turbo');
define('AI_TEMPERATURE', (float)(getenv('AI_TEMPERATURE') ?: '0.7');
```

### Error Handling

```php
// Graceful fallbacks
if (!defined('OPENAI_API_KEY') || empty(OPENAI_API_KEY)) {
    return new WP_Error('no_api_key', 'OpenAI API key not configured');
}
```

## Troubleshooting

### Common Issues

1. **Variables not available**: Check direnv is loaded (`direnv status`)
2. **Constants not defined**: Verify wp-config.php changes
3. **API calls failing**: Test API key validity first

### Debug Commands

```bash
# Check direnv status
direnv status

# Verify environment variables
env | grep -E "(OPENAI|AI_)"

# Test WordPress constants
wp --path="$WP_PATH" eval "var_dump(OPENAI_API_KEY);"
```

## Team Collaboration

### Sharing Environment Setup

1. Update `.envrc.example` with new variables
2. Document required values in this file
3. Notify team members of changes

### Onboarding New Developers

```bash
# Standard onboarding commands
git clone repository
cd repository
cp .envrc.example .envrc
# Edit .envrc with personal values
direnv allow
composer install
composer test  # Verify setup
```

## References

- [WordPress Environment Constants](https://wordpress.org/support/article/editing-wp-config-php/#wordpress-debug-mode)
- [direnv Documentation](https://direnv.net/)
- [Security Best Practices](../troubleshooting/security-pitfalls.md)

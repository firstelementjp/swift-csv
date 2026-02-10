# Environment Pitfalls

## #004 direnv Environment Setup Issues (2026-02-07)

**Symptom**: WP-CLI cannot connect to database, or environment variables not working properly.

**Cause**: Two common issues with `.envrc` configuration:
1. Database variables with double quotes cause WP-CLI connection failures
2. Paths with spaces break WP-CLI commands

**Fix** (`.envrc`):

```bash
# BEFORE — problematic
export DB_HOST="localhost"        # ❌ Double quotes break WP-CLI
export WP_PATH=/Users/name/Local Sites/app  # ❌ Spaces break command

# AFTER — working configuration
export DB_HOST=localhost         # ✅ Unquoted for database vars
export DB_NAME=local            # ✅ Unquoted for database vars
export DB_USER=root
export DB_PASSWORD=root
export WP_PATH="/Users/name/Local Sites/app"  # ✅ Quoted for spaces
```

**Test commands**:

```bash
wp db query "SELECT 1;" --path="$WP_PATH"
env | grep -E "(DB_|WP_)"
```

**Lesson**: Database variables: unquoted. Paths with spaces: quoted. Always test WP-CLI connection after setup.

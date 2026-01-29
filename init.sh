#!/bin/bash
# WordPress Plugin Template Initializer

TEXT_DOMAIN=$1
PLUGIN_NAME=${2:-$TEXT_DOMAIN}

if [ -z "$TEXT_DOMAIN" ]; then
    echo "Usage: ./init.sh <text-domain> [plugin-name]"
    echo "Example: ./init.sh my-awesome-plugin \"My Awesome Plugin\""
    exit 1
fi

echo "🚀 Initializing WordPress plugin..."
echo "   Text Domain: $TEXT_DOMAIN"
echo "   Plugin Name: $PLUGIN_NAME"
echo ""

# Replace text domain
echo "🔧 Configuring text domain..."
sed -i '' "s/YOUR-TEXT-DOMAIN/$TEXT_DOMAIN/g" phpcs.xml.dist

# Replace placeholders in plugin.php
echo "📝 Updating plugin.php..."
sed -i '' "s/Your Plugin Name/$PLUGIN_NAME/g" plugin.php
sed -i '' "s/your-plugin-slug/$TEXT_DOMAIN/g" plugin.php
sed -i '' "s/YOUR_PLUGIN_/$(echo $TEXT_DOMAIN | tr '-' '_' | tr '[:lower:]' '[:upper:]')_/g" plugin.php
sed -i '' "s/YourPlugin/$(echo $TEXT_DOMAIN | sed 's/-//g' | sed 's/^\(\w\)/\U\1/' | sed 's/\b\w/\U&/g')/g" plugin.php

# Update .envrc prompt
echo "🔧 Updating .envrc prompt..."
sed -i '' "s/(wp-plugin)/($TEXT_DOMAIN)/g" .envrc

# Update README placeholders
echo "📖 Updating README.md..."
sed -i '' "s/WordPress Plugin Template/$PLUGIN_NAME/g" README.md
sed -i '' "s/A modern WordPress plugin development template/$PLUGIN_NAME - A WordPress plugin/g" README.md

# Update composer.json
echo "📦 Updating composer.json..."
sed -i '' "s/\"name\": \"vendor\/wordpress-plugin\"/\"name\": \"vendor\/$TEXT_DOMAIN\"/g" composer.json
sed -i '' "s/\"description\": \"WordPress plugin template\"/\"description\": \"$PLUGIN_NAME\"/g" composer.json

# Update package.json
echo "📦 Updating package.json..."
sed -i '' "s/\"name\": \"wordpress-plugin\"/\"name\": \"$TEXT_DOMAIN\"/g" package.json
sed -i '' "s/\"description\": \"WordPress plugin template\"/\"description\": \"$PLUGIN_NAME\"/g" package.json

# Create include files
echo "📁 Creating include files..."

# core/class-main.php
cat > includes/core/class-main.php << 'EOF'
<?php
/**
 * Main plugin class.
 *
 * @package           YourPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Your_Plugin_Main {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialize plugin.
	 */
	public function init() {
		// Initialize plugin functionality
	}
}
EOF

# admin/class-admin.php
cat > includes/admin/class-admin.php << 'EOF'
<?php
/**
 * Admin functionality.
 *
 * @package           YourPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Your_Plugin_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'init' ) );
	}

	/**
	 * Initialize admin functionality.
	 */
	public function init() {
		// Initialize admin functionality
	}
}
EOF

# i18n/class-i18n.php
cat > includes/i18n/class-i18n.php << 'EOF'
<?php
/**
 * Internationalization functionality.
 *
 * @package           YourPlugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Your_Plugin_I18n {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'your-plugin-slug',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages'
		);
	}
}
EOF

# Update include files
echo "🔧 Updating include files..."
find includes -name "*.php" -exec sed -i '' "s/YourPlugin/$(echo $TEXT_DOMAIN | sed 's/-//g' | sed 's/^\(\w\)/\U\1/' | sed 's/\b\w/\U&/g')/g" {} \;
find includes -name "*.php" -exec sed -i '' "s/your-plugin-slug/$TEXT_DOMAIN/g" {} \;

# Install Composer dependencies
if command -v composer &> /dev/null; then
    echo "⬇️  Installing Composer dependencies..."
    composer install --quiet
else
    echo "⚠️  Composer not found. Please run 'composer install' manually."
fi

# Initialize Git repository if not already initialized
if [ ! -d ".git" ]; then
    echo "�� Initializing Git repository..."
    git init
    git add .
    git commit -m "Initial commit from wordpress-plugin-template"
fi

# Remove this script
echo "🗑️  Cleaning up..."
rm -- "$0"

echo ""
echo "✅ WordPress plugin '$PLUGIN_NAME' initialized successfully!"
echo ""
echo "📋 Next steps:"
echo "   1. Review phpcs.xml.dist and composer.json"
echo "   2. Install VSCode extensions:"
echo "      - PHP Sniffer & Beautifier (valeryan-m.vscode-phpsab)"
echo "      - Prettier (esbenp.prettier-vscode)"
echo "      - ESLint (dbaeumer.vscode-eslint)"
echo "   3. Start coding!"
echo ""
echo "   code ."

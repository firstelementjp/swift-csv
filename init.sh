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

# テキストドメインを置換
echo "🔧 Configuring text domain..."
sed -i '' "s/YOUR-TEXT-DOMAIN/$TEXT_DOMAIN/g" phpcs.xml.dist

# composer.jsonのnameを更新
echo "📦 Updating composer.json..."
sed -i '' "s/\"name\": \"vendor\/wordpress-plugin\"/\"name\": \"vendor\/$TEXT_DOMAIN\"/g" composer.json
sed -i '' "s/\"description\": \"WordPress plugin template\"/\"description\": \"$PLUGIN_NAME\"/g" composer.json

# Composer依存関係をインストール
if command -v composer &> /dev/null; then
    echo "⬇️  Installing Composer dependencies..."
    composer install --quiet
else
    echo "⚠️  Composer not found. Please run 'composer install' manually."
fi

# Gitリポジトリを初期化（まだの場合）
if [ ! -d ".git" ]; then
    echo "�� Initializing Git repository..."
    git init
    git add .
    git commit -m "Initial commit from wordpress-plugin-template"
fi

# このスクリプトを削除
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

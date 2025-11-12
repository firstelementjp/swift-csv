# WordPress Plugin Template

A modern WordPress plugin development template with pre-configured code quality tools.

## ✨ 特徴

- ✅ **PHPCS/PHPCBF**: WordPressコーディング規約準拠（短縮配列構文対応）
- ✅ **Prettier**: JavaScript/JSONの自動フォーマット
- ✅ **ESLint**: WordPressルールに基づくJavaScriptリンティング
- ✅ **VSCode設定**: すぐに開発を始められる設定ファイル付き
- 🚀 **ワンコマンドセットアップ**: 初期化スクリプトで簡単設定

### 🚀 クイックスタート

#### 1. テンプレートから作成

#### GitHubの「Use this template」ボタンをクリックするか、以下のコマンドを実行:

```bash
git clone [https://github.com/YOUR-USERNAME/wordpress-plugin-template.git](https://github.com/YOUR-USERNAME/wordpress-plugin-template.git) my-plugin
cd my-plugin
```

### 2. プラグインを初期化

初期化スクリプトを実行:

```bash
./init.sh your-plugin-slug "Your Plugin Name"
```

### 3. 依存関係をインストール

```bash
# PHP依存関係
composer install

# JavaScript依存関係
npm install
```

### 🛠 開発環境セットアップ

#### 必要なもの:

- PHP 7.4+
- Node.js 16+
- Composer
- VSCode（推奨）
- VSCode拡張機能

#### 最適な開発体験のために以下の拡張機能をインストール:

- PHP Sniffer & Beautifier
- ESLint
- Prettier

### 🧪 利用可能なスクリプト

PHP

```bash
# PHPの構文チェック
composer phpcs

# 自動修正
composer phpcbf
```

JavaScript

```bash
# リントチェック
npm run lint:js

# 自動修正
npm run lint:js:fix
```

### 🏗 プロジェクト構成

.
├── .vscode/ # VSCode設定
├── includes/ # プラグインクラス
├── languages/ # 翻訳ファイル
├── src/ # JavaScriptソース
├── vendor/ # Composer依存関係
├── .eslintrc.json # ESLint設定
├── .prettierrc # Prettier設定
├── phpcs.xml.dist # PHP_CodeSniffer設定
├── plugin.php # メインプラグインファイル
└── README.md # このファイル

### 🤝 コントリビューション

リポジトリをフォーク
機能ブランチを作成 (git checkout -b feature/AmazingFeature)
変更をコミット (git commit -m 'Add some AmazingFeature')
ブランチにプッシュ (git push origin feature/AmazingFeature)
プルリクエストを作成

### 📄 ライセンス

このプロジェクトはMITライセンスの下で公開されています - 詳細はLICENSEファイルを参照してください。

## ❤️ 作成: [あなたの名前]

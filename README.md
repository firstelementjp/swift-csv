# WordPress Plugin Template

A modern WordPress plugin development template with pre-configured code quality tools and development environment automation.

## ✨ 特徴

- ✅ **PHPCS/PHPCBF** — WordPress コーディング規約準拠（短縮配列構文対応）
- ✅ **Prettier** — JavaScript/JSON の自動フォーマット
- ✅ **ESLint** — WordPress ルールに基づく JavaScript リンティング
- ✅ **VSCode 設定** — すぐに開発を始められる設定ファイル付き
- ✅ **direnv 統合** — プロジェクト固有の環境変数とエイリアス管理
- 🚀 **ワンコマンドセットアップ** — 初期化スクリプトで簡単設定

## 🚀 クイックスタート

### 1. テンプレートから作成

GitHub の「Use this template」ボタンをクリックするか、以下を実行します。

```bash
git clone https://github.com/YOUR-USERNAME/wordpress-plugin-template.git my-plugin
cd my-plugin
```

### 2. プラグインを初期化

初期化スクリプトを実行します。

```bash
./init.sh your-plugin-slug "Your Plugin Name"
```

### 3. 依存関係をインストール

```bash
# PHP 依存関係
composer install

# JavaScript 依存関係
npm install
```

### 4. direnv のセットアップ

```bash
# direnv がインストールされていない場合
brew install direnv

# direnv をシェルに統合
echo 'eval "$(direnv hook zsh)"' >> ~/.zshrc # zsh の場合
# または
echo 'eval "$(direnv hook bash)"' >> ~/.bashrc # bash の場合

# シェルを再読み込み
exec $SHELL

# .envrc を有効化
direnv allow
```

## 🛠 開発環境セットアップ

### 必要なもの

- PHP 7.4+
- Node.js 16+
- Composer
- direnv（開発環境の自動設定用）
- VSCode（推奨）

### 推奨 VSCode 拡張機能

- PHP Sniffer & Beautifier
- ESLint
- Prettier

## 🔄 開発ワークフロー

### 便利なエイリアス

`.envrc` 内で以下のエイリアスが利用可能です。

```bash
cdcore   # includes/core に移動
cdi18n   # includes/i18n に移動
cdadmin  # includes/admin に移動
cdassets # assets に移動
```

### 利用可能なスクリプト

#### PHP

```bash
# PHP の構文チェック
composer phpcs

# 自動修正
composer phpcbf
```

#### JavaScript

```bash
# リントチェック
npm run lint:js

# 自動修正
npm run lint:js:fix
```

## 🏗 プロジェクト構成

```
.
├── .vscode/          # VSCode 設定
├── includes/         # プラグインクラス
├── languages/        # 翻訳ファイル
├── src/              # JavaScript ソース
├── vendor/           # Composer 依存関係
├── .envrc            # direnv 設定（.gitignore に追加済み）
├── .envrc.example    # 環境設定のテンプレート
├── .eslintrc.json    # ESLint 設定
├── .prettierrc       # Prettier 設定
├── phpcs.xml.dist    # PHP_CodeSniffer 設定
├── plugin.php        # メインプラグインファイル
└── README.md         # このファイル
```

## 🤝 コントリビューション

1. リポジトリをフォーク
2. 機能ブランチを作成
    ```bash
    git checkout -b feature/AmazingFeature
    ```
3. 変更をコミット
    ```bash
    git commit -m 'Add some AmazingFeature'
    ```
4. ブランチをプッシュ
    ```bash
    git push origin feature/AmazingFeature
    ```
5. プルリクエストを作成

## 📄 ライセンス

このプロジェクトは MIT ライセンスの下で公開されています。詳細は `LICENSE` ファイルを参照してください。

## ❤️ 作成者

Made with ❤️ by あなたの名前

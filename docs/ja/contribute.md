# 🤝 貢献

Swift CSV への貢献を歓迎します！

## 貢献方法

### バグ報告

1. [Issue](https://github.com/firstelementjp/swift-csv/issues) を開く
2. バグを詳細に説明
3. 再現手順を含める
4. 利用可能な場合はデバッグログを追加（`WP_DEBUG` を true に設定）

### 機能リクエスト

1. [Issue](https://github.com/firstelementjp/swift-csv/issues) を開く
2. ユースケースを説明
3. 実装アイデアを共有
4. 既存機能への影響を考慮

### コード貢献

1. リポジトリをフォーク
2. 機能ブランチを作成（`git checkout -b feature/amazing-feature`）
3. 変更をコミット（`git commit -m 'Add amazing feature'`）
4. ブランチにプッシュ（`git push origin feature/amazing-feature`）
5. プルリクエストを作成

## 開発環境

### 前提条件

- Node.js 18.0.0 以降
- PHP 8.1 以降
- WordPress 6.6 以降
- Composer
- direnv（環境設定に推奨）

### セットアップ

```bash
# リポジトリをクローン
git clone https://github.com/firstelementjp/swift-csv.git
cd swift-csv

# PHP 依存関係をインストール
composer install

# Node.js 依存関係をインストール
npm install

# 環境を設定（オプションだが推奨）
cp .envrc.example .envrc
# ローカル設定で .envrc を編集
direnv allow
```

### 開発ワークフロー

```bash
# 開発中の変更を監視
npm run dev

# プロダクション用アセットをビルド
npm run build

# JavaScript をリント
npm run lint:js

# コードをフォーマット
npm run format

# PHP コーディング標準をチェック
composer run phpcs
```

### コーディング標準

- WordPress コーディング標準に従う
- すべての関数とクラスに PHPDoc コメントを書く
- デバッグログには `[Swift CSV]` プレフィックスを使用
- PHPCS でコードをチェック: `composer run phpcs`
- Prettier で JavaScript をフォーマット: `npm run format`

### デバッグログ

一貫した形式でデバッグログを追加：

```php
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[Swift CSV] Your message here');
}
```

### テスト

- 様々な CSV 形式でインポート/エクスポートをテスト
- 異なる投稿タイプとカスタムフィールドでテスト
- ブラウザコンソールで AJAX 機能を確認
- モバイルデバイスでレスポンシブデザインをチェック

## プルリクエスト

### 提出前チェックリスト

- [ ] コードが WordPress コーディング標準に従っている
- [ ] JavaScript がリントされフォーマットされている
- [ ] デバッグログが `[Swift CSV]` プレフィックスを使用している
- [ ] アセットがビルドされている（`npm run build`）
- [ ] 必要に応じてドキュメントが更新されている
- [ ] 新機能のために変更履歴が更新されている
- [ ] WordPress 管理画面でテストされている

### レビュープロセス

1. 自動チェックが実行（リント、標準）
2. 機能と標準のコードレビュー
3. テスト検証
4. マージ決定

### コミットメッセージ

明確で説明的なコミットメッセージを使用：

```
feat: 複数値カスタムフィールドサポートを追加
fix: 複雑な CSV の区切り文字検出を解決
docs: 設定ドキュメントを更新
refactor: 重複 CSS ルールをクリーンアップ
```

## プロジェクト構造

```
swift-csv/
├── assets/          # フロントエンドアセット（CSS、JS）
├── includes/        # PHP クラス
├── languages/       # 翻訳ファイル
├── docs/            # ドキュメント
├── .github/         # GitHub ワークフロー
├── package.json     # Node.js 依存関係
├── composer.json    # PHP 依存関係
└── swift-csv.php    # メインプラグインファイル
```

## ヘルプ

- トラブルシューティングについては [SKILL.md](../.github/skills/SKILL.md) を確認
- 類似の問題については既存の Issue を確認
- バグ報告や機能リクエストが必要な場合は GitHub Issue を開く
- WordPress コーディング標準ドキュメントを参照

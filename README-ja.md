# Swift CSV

![Swift CSV Banner](https://github.com/firstelementjp/swift-csv/blob/main/assets/images/swift-csv-banner.jpeg?raw=true)

[![License](https://img.shields.io/badge/license-GPL--2.0%2B-blue.svg)](LICENSE)
[![Version](https://img.shields.io/badge/version-0.9.8-green.svg)](https://github.com/firstelementjp/swift-csv/releases)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)

<!-- WordPress.org badges - Uncomment when plugin is accepted to official directory -->
<!-- [![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/swift-csv.svg?style=flat-square)](https://wordpress.org/plugins/swift-csv/) -->
<!-- [![WordPress Requires At Least](https://img.shields.io/wordpress/plugin/tested/swift-csv.svg?style=flat-square)](https://wordpress.org/plugins/swift-csv/) -->
<!-- [![WordPress Tested Up To](https://img.shields.io/wordpress/plugin/tested/swift-csv.svg?style=flat-square)](https://wordpress.org/plugins/swift-csv/) -->
<!-- [![WordPress Requires PHP](https://img.shields.io/wordpress/plugin/php-version/swift-csv.svg?style=flat-square)](https://wordpress.org/plugins/swift-csv/) -->

[![License](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Contributors](https://img.shields.io/badge/Contributors-firstelement%2C%20dxd5001-blue.svg)](https://github.com/firstelementjp/swift-csv/graphs/contributors)
[![Donate](https://img.shields.io/badge/Donate-PayPal-blue.svg)](https://paypal.me/fejp?country.x=JP&locale.x=ja_JP)

WordPress向けの軽量でシンプルなCSVインポート／エクスポートプラグインです。カスタム投稿タイプ、カスタムタクソノミー、カスタムフィールドを完全にサポートしています。

## ✨ 特長

- **国際化対応** - 日本語を含む多言語サポート
- **階層型タクソノミー対応** - 親子関係を含むエクスポート／インポート
- **ブロックエディター対応** - Gutenbergブロック構造を完全に保持
- **バッチ処理** - タイムアウトせずに大きなCSVファイルを処理
- **リアルタイムログ** - インポート／エクスポートの進行状況をライブログで監視
- **強化されたセキュリティ** - ライセンスキーを暗号化して安全に保存
- **データ管理** - アンインストール時のデータ削除を制御
- **自動アップデート** - WordPress管理画面からワンクリック更新
- **レスポンシブUI** - モバイルフレンドリーな管理画面

## 🚀 インストール

### WordPress.orgからインストール（推奨）

1. **管理画面 → プラグイン → 新規追加** に移動
2. 「Swift CSV」を検索
3. **今すぐインストール** をクリック

### 手動インストール

1. [swift-csv-v0.9.8.zip をダウンロード](https://github.com/firstelementjp/swift-csv/releases/download/v0.9.8/swift-csv-v0.9.8.zip) ⭐ **推奨**
2. ZIPファイルを展開して `swift-csv/` フォルダを取得
3. `/wp-content/plugins/` ディレクトリにアップロード
4. 管理画面からプラグインを有効化

⚠️ **重要**: 正しくインストールするため、上記の手動インストール用ZIPファイルを使用してください。「Source code (zip)」は使用しないでください。

## 📖 使い方

### CSVエクスポート

1. **管理画面 → Swift CSV → Export** に移動
2. エクスポートオプションを設定
    - **投稿数**: エクスポートする投稿数を設定（初期値: 1000、0で無制限）
    - **リアルタイムログ**: ライブログでエクスポートの進行状況を監視
3. 投稿タイプを選択
4. **Export CSV** をクリック

大規模データセットは、タイムアウトを防ぐため自動的にバッチ処理されます。

### CSVインポート

1. **管理画面 → Swift CSV → Import** に移動
2. 対象の投稿タイプを選択
3. UTF-8でエンコードされたCSVファイルを選択
4. インポートオプションを設定
    - **ドライラン**: 投稿を作成せずにインポートをテスト
    - **リアルタイムログ**: ライブログでインポートの進行状況を監視
5. **Import CSV** をクリック

## 📖 ドキュメント

詳細なドキュメント、APIリファレンス、サンプルについては以下をご覧ください。

📚 **[完全ドキュメント](https://firstelementjp.github.io/swift-csv/)**

### 開発者向けメモ

開発者向けの内部メモ（エンドユーザー向けドキュメントではありません）:

- **Import AJAX architecture**: [`dev-notes/import-ajax-handler-architecture.md`](dev-notes/import-ajax-handler-architecture.md)

### クイックリンク

- [インストールガイド](https://firstelementjp.github.io/swift-csv/#/installation)
- [はじめに](https://firstelementjp.github.io/swift-csv/#/getting-started)
- [設定](https://firstelementjp.github.io/swift-csv/#/configuration)
- [使用例](https://firstelementjp.github.io/swift-csv/#/examples)
- [トラブルシューティング](https://firstelementjp.github.io/swift-csv/#/troubleshooting)

## 📋 CSVフォーマット

### 基本構造

| 列名 | 必須 | 説明 |
| --- | --- | --- |
| post_title | ✅ | 投稿タイトル |
| post_content | ❌ | 投稿本文（HTML対応） |
| post_excerpt | ❌ | 抜粋 |
| post_status | ❌ | 投稿ステータス（publish、draftなど） |
| post_name | ❌ | 投稿スラッグ |

### 階層型タクソノミー

```
Category A > Subcategory A > Grandchild
Technology > WordPress > Plugin Development
```

**複数値対応**: 複数の値を区切るには `|`（パイプ）を使用します。

```
category: "category-a|subcategory-a|grandchild"
```

### カスタムフィールド

カスタムフィールドには `cf_` プレフィックスを使用します。

```
cf_price, cf_color, cf_size
```

**複数値対応**: 複数の値を区切るには `|`（パイプ）を使用します。

```
cf_tags: "wordpress|php|developer"
```

## 🔧 高度な設定

### セキュリティ機能

- **ライセンスキー暗号化**: AES-256-CBCを使用してライセンスキーを暗号化し、安全に保存
- **ユーザー権限管理**: どのユーザーロールがインポート／エクスポートを実行できるかを制御

### データ管理

- **アンインストール時のデータ制御**: アンインストール時にすべてのプラグインデータを削除するか選択可能
- **ログ管理**: パフォーマンス最適化のため、リアルタイムログの有効／無効を切り替え可能

### パフォーマンスオプション

- **バッチ処理**: タイムアウトを防ぐため、大規模データセットを自動処理
- **メモリ管理**: 大きなCSVファイル向けにメモリ使用量を最適化
- **リアルタイム進行状況**: インポート／エクスポート処理をライブで監視

## 🔧 要件

- **WordPress**: 6.0以上
- **PHP**: 8.0以上
- **メモリ**: 128MB以上（大規模CSV処理時）
- **拡張機能**: OpenSSL（ライセンス暗号化用）

## 🌍 国際化

現在サポートしている言語:

- 🇯🇵 日本語
- 🇺🇸 英語（デフォルト）

## 🆕 v0.9.8 の新機能

### セキュリティ強化
- **ライセンスキー暗号化**: AES-256-CBCによるライセンスキーの安全な保存

### ユーザー体験の改善
- **リアルタイムログ**: インポート／エクスポート処理の進行状況をライブで監視
- **データ管理制御**: アンインストール時のデータ削除をユーザーが選択可能
- **独立した設定UI**: 高度な設定セクションをより整理された構成に改善

### 技術的改善
- **エラーハンドリング強化**: より分かりやすいエラーメッセージと復旧対応
- **パフォーマンス最適化**: バッチ処理効率の改善
- **コード品質**: コーディング標準への準拠を更新
- **翻訳更新**: 日本語ローカライズを改善

翻訳への協力に興味がありますか？ [GitHub](https://github.com/firstelementjp/swift-csv) からお問い合わせください。

## 🧪 テスト

### テストの実行

#### 標準環境

```bash
composer test
composer run test-coverage
```

#### ユニット／統合テスト

```bash
composer run test-unit        # ユニットテストのみ
composer run test-integration # 統合テストのみ
```

#### Local by Flywheel

Local by Flywheel環境でテストする場合、bootstrapファイルの絶対パスを設定する必要があることがあります。

```bash
export PHPUNIT_BOOTSTRAP="/app/public/wp-content/plugins/swift-csv/tests/bootstrap.php"
composer run test-coverage
```

あるいは、`phpunit.xml` の bootstrap パスを、使用している Local 環境に合わせて絶対パスに更新してください。

### テストカバレッジ

カバレッジレポートは `tests/coverage/` ディレクトリに生成されます。詳細なカバレッジ情報を確認するには、ブラウザで `tests/coverage/index.html` を開いてください。

### テスト構成

- **ユニットテスト**: `tests/Unit/` - 分離されたコンポーネントのテスト
- **統合テスト**: `tests/Integration/` - WordPress環境でのテスト
- **Bootstrap**: `tests/bootstrap.php` - WordPressテスト環境のセットアップ

### CI/CD

テストは、完全なWordPress環境サポート付きで GitHub Actions 上で自動実行されます。

## 🤝 コントリビューション

コントリビューションを歓迎します。以下の開発フローに従ってください。

### 開発ワークフロー

1. リポジトリを Fork
2. 機能ブランチを作成: `git checkout -b feature/batch-export-ui`
3. 変更を加え、十分にテスト
4. `develop` にPush: `git push origin develop`
5. `develop` ブランチ宛てにPull Requestを作成
6. レビュー後、`develop` にマージ
7. リリース: `develop` を `main` にバージョンタグ付きでマージ

### ブランチ戦略

- **main**: 安定版リリース（v0.9.4、v0.9.5 など）
- **develop**: 最新機能を含む開発ブランチ
- **feature/***: 個別機能の開発用

### 現在の開発状況

- **v0.9.7 リリース済み**: 詳細ログ付きリアルタイムエクスポート進行表示、ライセンス状態検出の強化、完全な日本語ローカライズ、適応型バッチ処理
- **今後**: 機能強化や各種連携の提案は GitHub Issues から歓迎します。

## 📄 ライセンス

GPLv2+ - 詳細は [LICENSE](LICENSE) ファイルをご覧ください。

## 🎯 開発者向けフック

Swift CSV は、フックを通じて幅広いカスタマイズオプションを提供しています。完全なドキュメントは [Hooks Documentation](https://firstelementjp.github.io/swift-csv/#/hooks) を参照してください。

### よく使われるフック

- `swift_csv_export_headers` - エクスポートヘッダーをフィルタ
- `swift_csv_export_row` - 各エクスポート行をフィルタ
- `swift_csv_export_process_custom_header` - カスタムエクスポートヘッダーの値を提供
- `swift_csv_export_phase_headers` - エクスポートヘッダー確定後に発火するアクション
- `swift_csv_import_row_validation` - インポート行を検証
- `swift_csv_import_data_filter` - 生のインポート行データを正規化／フィルタ
- `swift_csv_prepare_import_fields` - 保存前にメタフィールドを準備
- `swift_csv_import_phase_map_prepared` - フィールドのマッピング／準備完了後に発火するアクション
- `swift_csv_import_phase_post_persist` - 保存後に発火するアクション
- `swift_csv_import_batch_size` - インポートのバッチサイズをフィルタ

📚 [すべてのフックを見る](https://firstelementjp.github.io/swift-csv/#/hooks) - サンプル付き完全APIリファレンス

## 👨‍💻 開発者

FirstElement, Inc., Daijiro Miyazawa

---

⭐ このプラグインが役に立ったら、ぜひ[レビュー](https://wordpress.org/support/plugin/swift-csv/reviews/)をご検討ください。

#!/bin/bash

# 追加ドキュメント生成スクリプト
# インストールガイド、使用例、変更履歴などを生成

set -e

# 出力ディレクトリ
OUTPUT_DIR="docs"
mkdir -p "$OUTPUT_DIR"

# インストールガイド
cat > "$OUTPUT_DIR/installation.md" << 'EOF'
# 📦 インストール

## WordPress管理画面からインストール

1. WordPress管理画面にログイン
2. 「プラグイン」→「新規追加」をクリック
3. 「Swift CSV」を検索
4. 「今すぐインストール」をクリック
5. 「有効化」をクリック

## 手動インストール

1. [最新版をダウンロード](https://github.com/firstelementjp/swift-csv/releases/latest)
2. ダウンロードしたZIPファイルを解凍
3. `swift-csv`フォルダを`/wp-content/plugins/`にアップロード
4. WordPress管理画面からプラグインを有効化

## 要件

- WordPress 5.0以上
- PHP 7.4以上
- メモリ制限: 64MB以上（大容量CSV処理の場合）

## 初期設定

プラグイン有効化後、管理画面の「Swift CSV」メニューから基本設定を行ってください。
EOF

# 使用例
cat > "$OUTPUT_DIR/examples.md" << 'EOF'
# 💡 使用例

## 基本的なCSVインポート

```php
// CSVファイルをインポート
$batch = new Swift_CSV_Batch();
$result = $batch->import_csv('/path/to/file.csv');

if ($result['success']) {
    echo "インポート成功: {$result['imported_count']}件";
} else {
    echo "エラー: {$result['error']}";
}
```

## カスタムフィールドへのインポート

```php
// カスタムフィールドマッピング
$options = array(
    'field_mapping' => array(
        'name' => 'post_title',
        'email' => 'user_email',
        'phone' => 'custom_phone_field'
    ),
    'post_type' => 'custom_post_type'
);

$batch = new Swift_CSV_Batch();
$result = $batch->import_csv('/path/to/file.csv', $options);
```

## CSVエクスポート

```php
// 投稿データをCSVエクスポート
$exporter = new Swift_CSV_Exporter();
$csv_data = $exporter->export_posts(array(
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => -1
));

// CSVファイルとして保存
file_put_contents('/path/to/export.csv', $csv_data);
```
EOF

# 変更履歴
cat > "$OUTPUT_DIR/changelog.md" << 'EOF'
# 📋 変更履歴

## [0.9.1] - 2024-01-31

### 新機能
- Docsifyベースのドキュメントシステム導入
- APIドキュメントの自動生成
- GitHub Pagesでのデプロイ対応

### 改善
- CSV処理パフォーマンスの向上
- メモリ使用量の最適化
- エラーハンドリングの強化

### 修正
- 大容量ファイル処理時のメモリリーク修正
- 日本語文字列のエンコーディング問題修正

## [0.9.0] - 2024-01-15

### 新機能
- バッチ処理機能
- カスタムフィールド対応
- エクスポート機能

---

完全な変更履歴は[GitHubリポジトリ](https://github.com/firstelementjp/swift-csv/commits/main)で確認できます。
EOF

# その他のドキュメント
cat > "$OUTPUT_DIR/getting-started.md" << 'EOF'
# 🚀 はじめに

Swift CSVへようこそ！このプラグインはWordPressサイトでのCSVデータ管理を簡単にします。

## このドキュメントについて

- [インストール](installation.md) - プラグインのセットアップ
- [APIドキュメント](api.md) - 開発者向けリファレンス
- [使用例](examples.md) - 実装例
- [設定](configuration.md) - 詳細設定

## クイックスタート

1. プラグインをインストールして有効化
2. 管理画面の「Swift CSV」を開く
3. CSVファイルをアップロード
4. インポート設定を確認して実行

これだけで、CSVデータのインポートが完了します！

## サポート

問題が発生した場合は：
- [トラブルシューティング](troubleshooting.md)を確認
- [GitHub Issues](https://github.com/firstelementjp/swift-csv/issues)で報告
EOF

cat > "$OUTPUT_DIR/configuration.md" << 'EOF'
# 🔧 設定

Swift CSVの詳細設定について説明します。

## 基本設定

### インポート設定
- **エンコーディング**: UTF-8, Shift-JIS, EUC-JP対応
- **区切り文字**: カンマ, タブ, セミコロン選択可能
- **囲み文字**: ダブルクォート, シングルクォート

### エクスポート設定
- **出力形式**: CSV, TSV選択可能
- **文字コード**: UTF-8, Shift-JIS選択可能
- **BOM**: 有効/無効選択可能

## 詳細設定

### メモリ制限
大容量ファイル処理のためのメモリ設定：

```php
// wp-config.phpに追加
define('SWIFT_CSV_MEMORY_LIMIT', '256M');
define('SWIFT_CSV_MAX_EXECUTION_TIME', 300);
```

### フィールドマッピング
CSV列とWordPressフィールドの対応：

```php
$mapping = array(
    'csv_column_1' => 'post_title',
    'csv_column_2' => 'post_content',
    'csv_column_3' => 'custom_field_name'
);
```
EOF

cat > "$OUTPUT_DIR/troubleshooting.md" << 'EOF'
# 🐛 トラブルシューティング

よくある問題と解決策を紹介します。

## 一般的な問題

### インポートが失敗する
- **原因**: メモリ不足
- **解決策**: 
  - PHPのmemory_limitを増やす
  - CSVファイルを分割する

### 文字化けが発生する
- **原因**: 文字コードの不一致
- **解決策**: 
  - CSVファイルをUTF-8に変換
  - インポート時に文字コードを指定

### 大容量ファイルが処理できない
- **原因**: タイムアウト
- **解決策**: 
  - max_execution_timeを増やす
  - バッチ処理を有効にする

## エラーコード一覧

| コード | 説明 | 解決策 |
|--------|------|--------|
| 1001 | ファイルが見つからない | ファイルパスを確認 |
| 1002 | メモリ不足 | memory_limitを増やす |
| 1003 | ファイル形式エラー | CSV形式を確認 |

## サポート

問題が解決しない場合は：
- [GitHub Issues](https://github.com/firstelementjp/swift-csv/issues)で報告
- エラーログを添付してください
EOF

cat > "$OUTPUT_DIR/contributing.md" << 'EOF'
# 🤝 貢献

Swift CSVへの貢献を歓迎します！

## 貢献方法

### バグ報告
1. [Issues](https://github.com/firstelementjp/swift-csv/issues)を開く
2. バグの詳細を記述
3. 再現手順を添付

### 機能提案
1. [Issues](https://github.com/firstelementjp/swift-csv/issues)で提案
2. ユースケースを説明
3. 実装アイデアを共有

### コード貢献
1. リポジトリをフォーク
2. ブランチを作成
3. 変更をコミット
4. プルリクエストを作成

## 開発環境

### セットアップ
```bash
git clone https://github.com/firstelementjp/swift-csv.git
cd swift-csv
composer install
```

### コーディング規約
- WordPressコーディング規約に準拠
- PHPDocコメントを記述
- PHPCSでコードチェック

## プルリクエスト

### 提出前の確認
- [ ] テストが通る
- [ ] コードが規約に準拠
- [ ] ドキュメントを更新
- [ ] 変更ログを記述

### レビュープロセス
1. 自動テスト実行
2. コードレビュー
3. マージ判断
EOF

cat > "$OUTPUT_DIR/license.md" << 'EOF'
# 📄 ライセンス

Swift CSVはGPL-2.0+ライセンスの下で提供されています。

## ライセンス条文

```
Swift CSV WordPress Plugin
Copyright (C) 2024 FirstElement

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
```

## ライセンスの意味

- ✅ 自由に使用・改変・配布可能
- ✅ 商利利用可能
- ✅ 特許クレームなし
- ❌ 同一ライセンスでの配布が必要
- ❌ 保証なし

## 第三者ライブラリ

このプラグインは以下のオープンソースライブラリを使用しています：

- [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards)
- [PHP Compatibility](https://github.com/PHPCompatibility/PHPCompatibility)

詳細は[composer.json](composer.json)を参照してください。
EOF

echo "Additional documentation generated successfully!"
echo "Files created in: $OUTPUT_DIR/"

# Swift CSV Plugin - Development Skills & Standards

## WordPress Coding Standards

### Yoda Conditions (重要ルール)

**Yoda記法が推奨されるケース**：
```php
// ✅ 推奨 - true/false/null の比較
if ( true === $some_condition ) {
    // 処理
}

if ( false === $some_condition ) {
    // 処理
}

if ( null === $some_variable ) {
    // 処理
}

if ( 0 === $some_number ) {
    // 処理
}
```

**Yoda記法が必須ではないケース**：
```php
// ✅ OK - 文字列比較
if ( $export_scope === 'custom' ) {
    // 処理
}

// ✅ OK - 数値比較
if ( $count > 0 ) {
    // 処理
}

// ✅ OK - 関数呼び出し
if ( isset($variable) ) {
    // 処理
}
```

### 統一ルール

**このプロジェクトでの統一方針**：
- **必須**: `true`/`false`/`null` の比較はYoda記法を使用
- **推奨**: 文字列比較は可読性を優先（通常の記法でもOK）
- **一貫性**: 既存のコードスタイルを尊重する

### 実装例

```php
// ✅ このプロジェクトでの実装例
if ( true === $is_enabled ) {
    // 処理
}

if ( null === $post_id ) {
    // 処理
}

if ( $export_scope === 'custom' ) {  // 文字列は通常の記法でもOK
    // 処理
}

if ( $count > 0 ) {  // 数値比較は通常の記法
    // 処理
}
```

## プライベートメタフィールド

### 定義
アンダースコア(`_`)で始まるカスタムフィールドを「プライベートメタ」と呼ぶ

### 特徴
- **非表示**: 通常の投稿編集画面では表示されない
- **内部使用**: WordPressシステムやプラグインが内部的に使用
- **保護**: ユーザーが直接編集することを想定していない

### 例
```php
// プライベートメタ
'_thumbnail_id'        // アイキャッチ画像ID
'_wp_page_template'    // ページテンプレート
'_edit_lock'          // 編集ロック

// 通常のカスタムフィールド
'price'               // 商品価格
'location'            // 場所
'event_date'          // イベント日付
```

### このプラグインでの扱い
- **デフォルト除外**: エクスポート時にプライベートメタを含めない
- **オプトイン**: ユーザーが明示的に選択した場合のみ含める
- **UIラベル**: "Include fields starting with '_'"

## PHP配列関数

### 重要な関数
```php
// 先頭に追加
array_unshift($array, $element);  // 返り値: 新しい要素数

// 先頭を削除して取得
$first = array_shift($array);    // 返り値: 削除された要素

// 末尾に追加
array_push($array, $element);    // 返り値: 新しい要素数
$array[] = $element;             // 短縮構文

// 末尾を削除して取得
$last = array_pop($array);       // 返り値: 削除された要素
```

## 国際化 (i18n)

### WordPress標準実装
```php
// PHP側
wp_localize_script('swift-csv-admin', 'swiftCSV', [
    'message' => esc_html__('Hello World', 'swift-csv'),
]);

// JavaScript側
console.log(swiftCSV.message);
```

### 翻訳ファイル
- **POT**: 翻訳テンプレート (languages/swift-csv.pot)
- **PO**: 各言語の翻訳ソース (languages/swift-csv-ja.po)
- **MO**: コンパイル済み翻訳 (languages/swift-csv-ja.mo)

### 翻訳関数
```php
// 基本的な翻訳
__('Text', 'swift-csv')

// HTMLエスケープ付き翻訳
esc_html__('Text', 'swift-csv')

// パラメータ付き翻訳
sprintf(
    /* translators: 1: post ID, 2: post title */
    __('Post ID: %1$s, Title: %2$s', 'swift-csv'),
    $post_id,
    $post_title
)
```

## デバッグとロギング

### エラーログ
```php
// WordPress標準のエラーログ
error_log('[Swift CSV] Message: ' . $variable);

// 条件付きデバッグログ
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[Swift CSV] Debug info');
}
```

### 本番環境
- デバッグログ設定は本番環境で無効化
- 開発用コードは本番に含めない

## セキュリティ

### データサニタイズ
```php
// POSTデータのサニタイズ
$post_type = sanitize_text_field($_POST['post_type'] ?? '');
$include_private = (bool) ($_POST['include_private'] ?? false);

// SQLクエリの準備
$wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $post_id);
```

### 権限チェック
```php
// 管理者権限チェック
if (!current_user_can('manage_options')) {
    wp_die(__('Permission denied', 'swift-csv'));
}
```

## パフォーマンス

### バッチ処理
- 大量データ処理はバッチに分割
- メモリ使用量を制限
- タイムアウトを考慮

### キャッシュ
- 重複処理を避ける
- 適切なキャッシュ戦略を実装

---

*このドキュメントはプロジェクトの開発スキルと標準を記録し、チーム内での一貫性を確保するために維持されます。*

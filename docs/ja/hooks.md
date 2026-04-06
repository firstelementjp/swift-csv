# Swift CSV フック

このドキュメントは、現在のコードベースで実装されている Swift CSV の利用可能なフックを一覧表示します。

以下に焦点を当てています：

- 正確なフック名とシグネチャ
- 実用的な使用パターン
- 実際の拡張（WooCommerce、カスタムビジネスルールなど）を反映した例

## 目次

- [エクスポートフック](#エクスポートフック)
  - [ヘッダー生成](#ヘッダー生成)
  - [行生成](#行生成)
  - [バッチ/パフォーマンス](#バッチパフォーマンス)
- [インポートフック](#インポートフック)
  - [権限と検証](#権限と検証)
  - [フィールド準備とマッピング](#フィールド準備とマッピング)
  - [バッチ処理](#バッチ処理)
  - [ログと診断](#ログと診断)
- [管理/UI フック](#管理ui-フック)
- [機能フラグ](#機能フラグ)
- [ベストプラクティス](#ベストプラクティス)

---

## エクスポートフック

### ヘッダー生成

#### `swift_csv_export_filter_taxonomy_objects`

`tax_{taxonomy}` ヘッダーの構築に使用されるタクソノミーオブジェクトをフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('swift_csv_export_filter_taxonomy_objects', array $taxonomies, array $args): array
```

**パラメータ:**

- `$taxonomies` (`array`) `get_object_taxonomies($post_type, 'objects')` によって返されるタクソノミーオブジェクト
- `$args` (`array`) コンテキスト引数
  - `post_type` (`string`)
  - `export_scope` (`string`)
  - `include_private_meta` (`bool`)
  - `context` (`string`) 現在 `taxonomy_objects_filter`

**例:** (内部タクソノミーを除外)

```php
add_filter('swift_csv_export_filter_taxonomy_objects', 'my_swiftcsv_filter_taxonomies', 10, 2);

function my_swiftcsv_filter_taxonomies($taxonomies, $args) {
    // 英語コメントのみ
    if (!is_array($taxonomies)) {
        return [];
    }

    foreach ($taxonomies as $key => $tax) {
        if (!isset($tax->name)) {
            continue;
        }

        // 例: エクスポートからタクソノミーを非表示
        if ('post_format' === $tax->name) {
            unset($taxonomies[$key]);
        }
    }

    return $taxonomies;
}
```

#### `swift_csv_export_sample_query_args`

メタキー発見のための「サンプル投稿」を選択するために使用される WP クエリ引数をフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('swift_csv_export_sample_query_args', array $query_args, array $args): array
```

**パラメータ:**

- `$query_args` (`array`) サンプル ID を取得するために使用される WP クエリ引数
- `$args` (`array`) コンテキスト引数
  - `post_type` (`string`)
  - `context` (`string`) 現在 `meta_discovery`

**例:** (メタ付きの最近の投稿を優先)

```php
add_filter('swift_csv_export_sample_query_args', 'my_swiftcsv_sample_query_args', 10, 2);

function my_swiftcsv_sample_query_args($query_args, $args) {
    // 例: カスタムフィールドを持つ可能性が高い投稿を優先
    $query_args['orderby'] = 'modified';
    $query_args['order'] = 'DESC';
    return $query_args;
}
```

#### `swift_csv_export_classify_meta_keys`

サンプル投稿から発見されたメタキーを分類します。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('swift_csv_export_classify_meta_keys', array $all_meta_keys, array $args): array
```

**パラメータ:**

- `$all_meta_keys` (`array<string>`) 生の発見されたメタキー
- `$args` (`array`) コンテキスト
  - `post_type` (`string`)
  - `export_scope` (`string`)
  - `include_private_meta` (`bool`)
  - `context` (`string`) 現在 `meta_key_classification`

**期待される戻り値:**

```php
[
  'regular' => array<string>,
  'private' => array<string>,
]
```

**例:** (ノイズの多いキーを除外)

```php
add_filter('swift_csv_export_classify_meta_keys', 'my_swiftcsv_classify_meta_keys', 10, 2);

function my_swiftcsv_classify_meta_keys($all_meta_keys, $args) {
    $regular = [];
    $private = [];

    foreach ((array) $all_meta_keys as $key) {
        $key = (string) $key;
        if ('' === $key) {
            continue;
        }

        // 例: WordPress 内部キーを削除
        if (in_array($key, ['_edit_lock', '_edit_last'], true)) {
            continue;
        }

        if (0 === strpos($key, '_')) {
            $private[] = $key;
        } else {
            $regular[] = $key;
        }
    }

    return [
        'regular' => $regular,
        'private' => $private,
    ];
}
```

#### `swift_csv_export_generate_custom_field_headers`

分類されたメタキーからカスタムフィールド（メタ）ヘッダーを生成します。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('swift_csv_export_generate_custom_field_headers', array $headers, array $classified_meta_keys, array $args): array
```

**パラメータ:**

- `$headers` (`array<string>`) 空の配列から開始
- `$classified_meta_keys` (`array`) `swift_csv_export_classify_meta_keys` の結果
- `$args` (`array`) コンテキスト
  - `post_type` (`string`)
  - `export_scope` (`string`)
  - `include_private_meta` (`bool`)
  - `context` (`string`) 現在 `custom_field_headers_generation`

**例:** (許可リストのメタキーのみ)

```php
add_filter('swift_csv_export_generate_custom_field_headers', 'my_swiftcsv_custom_field_headers', 10, 3);

function my_swiftcsv_custom_field_headers($headers, $classified_meta_keys, $args) {
    $allow = ['price', 'color', 'size'];
    $out = [];

    foreach ((array) ($classified_meta_keys['regular'] ?? []) as $meta_key) {
        $meta_key = (string) $meta_key;
        if (in_array($meta_key, $allow, true)) {
            $out[] = 'cf_' . $meta_key;
        }
    }

    return $out;
}
```

#### `swift_csv_export_headers`

最終的なヘッダーリストをフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('swift_csv_export_headers', array $headers, array $config, string $context): array
```

**パラメータ:**

- `$headers` (`array<string>`) 最終ヘッダー
- `$config` (`array`) エクスポート設定
- `$context` (`string`) 現在 `standard` (WP 互換) または `direct_sql`

**例:** (カスタム計算カラムを追加)

```php
add_filter('swift_csv_export_headers', 'my_swiftcsv_add_custom_header', 10, 3);

function my_swiftcsv_add_custom_header($headers, $config, $context) {
    // 標準以外のヘッダーを追加。その値は swift_csv_export_process_custom_header によって提供される
    $headers[] = 'my_permalink';
    return $headers;
}
```

#### `swift_csv_export_phase_headers`

ヘッダーが確定した後に発火されるアクション。

**タイプ:** action

**シグネチャ:**

```php
do_action('swift_csv_export_phase_headers', array $headers, array $config, string $context): void
```

**例:** (ヘッダーをログ記録)

```php
add_action('swift_csv_export_phase_headers', 'my_swiftcsv_log_headers', 10, 3);

function my_swiftcsv_log_headers($headers, $config, $context) {
    error_log('[Swift CSV] Export headers finalized (' . $context . '): ' . implode(',', (array) $headers));
}
```

### 行生成

#### `swift_csv_export_row`

エクスポート生成中に各行をフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('swift_csv_export_row', array $row, int $post_id, array $config, string $context): array
```

**パラメータ:**

- `$row` (`array`) 行データ
  - WP 互換エクスポートの場合: 通常、ヘッダーに合わせたインデックス配列
  - Direct SQL エクスポートの場合: CSV に変換する前に連想配列がよく使用される
- `$post_id` (`int`) 投稿 ID
- `$config` (`array`) エクスポート設定
- `$context` (`string`) `wp_compatible`, `direct_sql`、またはスコープ値

**例:** (商品価格をフォーマット)

```php
add_filter('swift_csv_export_row', 'my_swiftcsv_export_row_format', 10, 4);

function my_swiftcsv_export_row_format($row, $post_id, $config, $context) {
    // WP 互換エクスポートは通常、ヘッダーに合わせたインデックス行を渡す
    // Direct SQL エクスポートは通常、連想行を渡す

    if ('direct_sql' === $context && is_array($row) && isset($row['cf_price'])) {
        $row['cf_price'] = number_format((float) $row['cf_price'], 2, '.', '');
        return $row;
    }

    // 例: wp_compatible の場合、インデックスで更新（形状を維持）
    // ここでヘッダー対応の編集が必要な場合、インデックスを特定するために swift_csv_export_headers もフックする
    if ('wp_compatible' === $context && is_array($row) && isset($row[0])) {
        // 何もしない例: 行をそのまま返す
        return $row;
    }

    return $row;
}
```

#### `swift_csv_export_process_custom_header`

標準の `post_*`, `tax_*`, `cf_*`, `ID` ではないカスタムヘッダーの値を提供します。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('swift_csv_export_process_custom_header', string $value, string $header, int $post_id, array $args): string
```

**パラメータ:**

- `$value` (`string`) デフォルトは空
- `$header` (`string`) ヘッダー名
- `$post_id` (`int`) 投稿 ID
- `$args` (`array`) コンテキスト
  - `post_type` (`string`)
  - `context` (`string`) 現在 `export_data_processing`

**例:** (`my_permalink` ヘッダーを実装)

```php
add_filter('swift_csv_export_process_custom_header', 'my_swiftcsv_custom_header_value', 10, 4);

function my_swiftcsv_custom_header_value($value, $header, $post_id, $args) {
    if ('my_permalink' === $header) {
        return (string) get_permalink($post_id);
    }

    return (string) $value;
}
```

### Direct SQL エクスポートクエリのカスタマイズ

これらのフックは主に `Swift_CSV_Export_Direct_SQL` によって使用されます。

#### `swift_csv_export_query_spec`

エクスポートに適用できる統一クエリ仕様（tax_query/meta_query スタイル）を提供します。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('swift_csv_export_query_spec', array $query_spec, array $config, string $context): array
```

**パラメータ:**

- `$query_spec` (`array`) デフォルトは空
- `$config` (`array`) エクスポート設定
- `$context` (`string`) 現在 `direct_sql`

**例:** (メタフラグ付きのアイテムのみをエクスポート)

```php
add_filter('swift_csv_export_query_spec', 'my_swiftcsv_export_query_spec', 10, 3);

function my_swiftcsv_export_query_spec($query_spec, $config, $context) {
    if ('direct_sql' !== $context) {
        return $query_spec;
    }

    // 例: メタキー "export_enabled" が "1" の投稿のみをエクスポート
    return [
        'meta_query' => [
            [
                'key'     => 'export_enabled',
                'compare' => '=',
                'value'   => '1',
            ],
        ],
    ];
}
```

#### `swift_csv_export_batch_size`

エクスポートバッチサイズをフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('swift_csv_export_batch_size', int $batch_size, int $total_count, string $post_type, array $config): int
```

**例:**

```php
add_filter('swift_csv_export_batch_size', 'my_swiftcsv_export_batch_size', 10, 4);

function my_swiftcsv_export_batch_size($batch_size, $total_count, $post_type, $config) {
    // 例: 重い投稿タイプのバッチサイズを削減
    if ('product' === $post_type) {
        return max(100, min((int) $batch_size, 500));
    }
    return (int) $batch_size;
}
```

---

## インポートフック

### 権限と検証

#### `swift_csv_user_can_import`

インポートを実行するユーザー権限をフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('swift_csv_user_can_import', bool $can_import): bool
```

**パラメータ:**

- `$can_import` (`bool`) `current_user_can('import')` に基づくデフォルト権限

**例:** (カスタム権限を要求)

```php
add_filter('swift_csv_user_can_import', 'my_swiftcsv_import_permission', 10, 1);

function my_swiftcsv_import_permission($can_import) {
    return current_user_can('manage_options') || current_user_can('import_csv');
}
```

#### `swift_csv_pre_ajax_import`

インポート前検証結果をフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('swift_csv_pre_ajax_import', bool|WP_Error $result, array $post_data): bool|WP_Error
```

**パラメータ:**

- `$result` (`bool|WP_Error`) 検証結果
- `$post_data` (`array`) インポートリクエストからの POST データ

**例:** (営業時間を検証)

```php
add_filter('swift_csv_pre_ajax_import', 'my_swiftcsv_business_hours_check', 10, 2);

function my_swiftcsv_business_hours_check($result, $post_data) {
    $hour = (int) date('H');
    if ($hour < 9 || $hour > 17) {
        return new WP_Error('business_hours', 'Import only allowed during business hours (9AM-5PM)');
    }
    return $result;
}
```

### フィールド準備とマッピング

#### `swift_csv_prepare_import_fields`

インポート前に準備されたメタフィールドをフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('swift_csv_prepare_import_fields', array $meta_fields, int $post_id, array $args): array
```

**パラメータ:**

- `$meta_fields` (`array`) 準備されたメタフィールド
- `$post_id` (`int`) インポート中の投稿 ID
- `$args` (`array`) post_type とコンテキストを含むコンテキスト引数

**例:** (カスタムフィールド形式を処理)

```php
add_filter('swift_csv_prepare_import_fields', 'my_swiftcsv_process_custom_fields', 10, 3);

function my_swiftcsv_process_custom_fields($meta_fields, $post_id, $args) {
    foreach ($meta_fields as $key => $value) {
        if (strpos($key, 'price_') === 0) {
            $meta_fields[$key] = floatval($value);
        }
    }
    return $meta_fields;
}
```

#### `swift_csv_import_batch_size`

インポートバッチサイズをフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('swift_csv_import_batch_size', int $batch_size, int $total_rows, array $config): int
```

**パラメータ:**

- `$batch_size` (`int`) 計算されたバッチサイズ
- `$total_rows` (`int`) 処理する総行数
- `$config` (`array`) インポート設定

**例:** (サーバーパフォーマンスを最適化)

```php
add_filter('swift_csv_import_batch_size', 'my_swiftcsv_optimize_batch_size', 10, 3);

function my_swiftcsv_optimize_batch_size($batch_size, $total_rows, $config) {
    // メモリ制約のあるサーバーのバッチサイズを削減
    $memory_limit = ini_get('memory_limit');
    if ($memory_limit === '128M') {
        return min(5, $batch_size);
    }
    return $batch_size;
}
```

---

## 管理/UI フック

#### `swift_csv_settings_tabs`

設定タブのレンダリング時に発火されるアクション。

**タイプ:** action

**シグネチャ:**

```php
do_action('swift_csv_settings_tabs', string $tab): void
```

#### `swift_csv_settings_tabs_content`

タブコンテンツのレンダリング時に発火されるアクション。

**タイプ:** action

**シグネチャ:**

```php
do_action('swift_csv_settings_tabs_content', string $tab, array $import_results): void
```

#### `swift_csv_export_form_action`

エクスポートフォームのアクション URL をフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('swift_csv_export_form_action', string $action_url): string
```

**パラメータ:**

- `$action_url` (`string`) デフォルトは空（現在のページを使用）

**例:** (カスタムハンドラにリダイレクト)

```php
add_filter('swift_csv_export_form_action', 'my_swiftcsv_custom_export_handler', 10, 1);

function my_swiftcsv_custom_export_handler($action_url) {
    return 'https://my-api.com/handle-swift-csv-export';
}
```

**⚠️ セキュリティ注意:** ご注意ください。外部エンドポイントへのリダイレクトは機密データを露出する可能性があります。カスタムハンドラを実装する際は、適切な認証、HTTPS、アクセス制御を確保してください。

**安全な実装例:**

```php
add_filter('swift_csv_export_form_action', 'secure_export_handler', 10, 1);

function secure_export_handler($action_url) {
    // 管理権限を持つユーザーのみ許可
    if (!current_user_can('manage_options')) {
        return $action_url; // 権限が不十分な場合は元の URL を返す
    }

    // セキュリティ検証用の nonce を生成
    $nonce = wp_create_nonce('secure_export_' . get_current_user_id());

    return home_url("/secure-export?nonce={$nonce}&user_id=" . get_current_user_id());
}
```

#### `swift_csv_tools_page_capability`

Swift CSV 管理ページにアクセスするために必要な権限をフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('swift_csv_tools_page_capability', string $capability): string
```

**パラメータ:**

- `$capability` (`string`) デフォルト `manage_options`

**例:** (編集者がアクセスできるようにする)

```php
add_filter('swift_csv_tools_page_capability', 'my_swiftcsv_tools_capability', 10, 1);

function my_swiftcsv_tools_capability($capability) {
    return 'edit_posts'; // 投稿を編集できるすべてのユーザーを許可
}
```

---

## 機能フラグ / 診断

#### `swift_csv_enable_direct_sql_import`

Direct SQL インポートを有効にする機能フラグ。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('swift_csv_enable_direct_sql_import', bool $enabled): bool
```

#### `swift_csv_max_log_entries`

保存/表示されるログエントリ数を制御します。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('swift_csv_max_log_entries', int $max_entries): int
```

#### `swift_csv_user_can_export`

エクスポートを実行するユーザー権限をフィルターします。

**タイプ:** filter

**シグネチャ:**

```php
apply_filters('swift_csv_user_can_export', bool $can_export): bool
```

**パラメータ:**

- `$can_export` (`bool`) `current_user_can('export')` に基づくデフォルト権限

**例:** (カスタム権限を要求)

```php
add_filter('swift_csv_user_can_export', 'my_swiftcsv_export_permission', 10, 1);

function my_swiftcsv_export_permission($can_export) {
    return current_user_can('manage_options') || current_user_can('export_csv');
}
```

---

## ベストプラクティス

### 開発ガイドライン

1. **英語コメントのみ**: フック実装では英語コメントを使用してください
2. **エラーハンドリング**: 常に適切なエラーハンドリングを実装してください
3. **パフォーマンス**: 大量データ処理ではバッチ処理を考慮してください
4. **セキュリティ**: ユーザー権限とデータ検証を常に確認してください

### 一般的なパターン

- **エクスポート**: ヘッダー生成 → 行処理 → バッチ処理
- **インポート**: 検証 → フィールドマッピング → 投稿永続化 → 後処理
- **UI**: 設定タブ → フォーム処理 → 権限チェック

### デバッグ

開発中は `WP_DEBUG` を有効にして、詳細なログ情報を取得してください。

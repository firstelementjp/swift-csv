# class-swift-csv-batch

## Methods

### __construct

```php
public function __construct() {
```

**Since:** 0.9.0

**Returns:**
- (void) void

---

### create_batch_table

```php
public function create_batch_table() {
```

**Since:** 0.9.0

**Returns:**
- (void) void

---

### start_batch

```php
public function start_batch( $file_path, $post_type, $update_existing ) {
```

**Since:** 0.9.0

**Parameters:**
- `string` ($file_path) -       Path to CSV file.

**Parameters:**
- `string` ($post_type) -       Target post type.

**Parameters:**
- `bool` ($update_existing) -  $update_existing Whether to update existing posts.

**Returns:**
- (string) Batch ID for tracking.

---

### process_batch

```php
public function process_batch( $batch_id ) {
```

**Since:** 0.9.0

**Parameters:**
- `string` ($batch_id) - Batch ID to process.

**Returns:**
- (void) void

---

### get_batch_progress

```php
public function get_batch_progress( $batch_id ) {
```

**Since:** 0.9.0

**Parameters:**
- `string` ($batch_id) - Batch ID.

**Returns:**
- (array) Progress data.

---

### ajax_batch_progress

```php
public function ajax_batch_progress() {
```

**Since:** 0.9.0

**Returns:**
- (void) void

---

### ajax_start_batch

```php
public function ajax_start_batch() {
```

**Since:** 0.9.0

**Returns:**
- (void) void

---


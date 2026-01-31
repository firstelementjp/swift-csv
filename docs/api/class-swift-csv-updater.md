# class-swift-csv-updater

## Methods

### __construct

```php
public function __construct( $plugin_file ) {
```

**Since:** 0.9.0

**Parameters:**
- `string` ($plugin_file) - Plugin file path.

**Returns:**
- (void) void

---

### schedule_update_check

```php
public function schedule_update_check() {
```

**Since:** 0.9.0

**Returns:**
- (void) void

---

### force_update_check

```php
public function force_update_check() {
```

**Since:** 0.9.0

**Returns:**
- (void) void

---

### check_for_updates

```php
public function check_for_updates( $transient ) {
```

**Since:** 0.9.0

**Parameters:**
- `object` ($transient) - Update transient.

**Returns:**
- (object) Modified transient.

---

### plugins_api_info

```php
public function plugins_api_info( $res, $action, $args ) {
```

**Since:** 0.9.0

**Parameters:**
- `bool|object` ($res) -     The result object.

**Parameters:**
- `string` ($action) -     $action   The type of information being requested.

**Parameters:**
- `object` ($args) -     $args     Plugin API arguments.

**Returns:**
- (bool|object) Modified result.

---

### after_update

```php
public function after_update( $upgrader, $options ) {
```

**Since:** 0.9.0

**Parameters:**
- `WP_Upgrader` ($upgrader) - Upgrader instance.

**Parameters:**
- `array` ($options) -      $options  Update options.

**Returns:**
- (void) void

---

### get_update_status

```php
public function get_update_status() {
```

**Since:** 0.9.0

**Returns:**
- (array) Update status information.

---


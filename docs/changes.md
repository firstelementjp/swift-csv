# 📋 Changelog

## [0.9.9] - 2026-03-28

### 🚀 Import Reliability and Performance

- **Lower memory usage for large imports** - Import batches now read CSV data incrementally and reuse cached file offsets instead of keeping full CSV content in memory.
- **Reduced duplicate CSV parsing** - Batch processing reuses parsed row data within the same batch to cut repeated parsing overhead.
- **Improved batch diagnostics** - Import profiling logs now make it easier to identify time and memory hotspots while debugging large imports.

### 🛠️ CSV Parsing and Import UI Fixes

- **RFC 4180-aligned CSV parsing** - Core `str_getcsv()` usage now disables backslash escaping so quoted fields are interpreted more predictably.
- **Import log stability improvements** - Import log tabs initialize more reliably, recent logs remain available at completion, and error logs stay aligned with error counts.
- **Safer log cleanup behavior** - UI log clearing now targets the current import panels more precisely.

### 📦 Release Workflow Updates

- **Branch-aware release script** - `test-release.sh` can build a release ZIP from the current branch or an explicitly provided branch.
- **Automatic artifact cleanup** - Temporary `test-release` output and generated minified assets are removed after local release builds.
- **Cleaner repository state** - Generated release artifacts and minified distribution files are no longer expected to remain tracked in Git.

---

## [0.9.8] - 2026-03-19

### 🏗️ Architecture Improvements

- **Interface-based import system** - New base classes and WP-compatible implementations
- **Enhanced taxonomy handling** - Dedicated taxonomy writer interface and utilities
- **Object-type PHPDoc** - IDE compatibility improvements with generic object types
- **Modular JavaScript architecture** - Organized export modules with 6 separate files

### 🛠️ Development Experience

- **Environment setup guide** - Comprehensive direnv + wp-config.php integration documentation
- **PHPUnit configuration fix** - Local development testing now works out of the box
- **Enhanced developer documentation** - Updated skills/ directory with latest patterns
- **Build process improvements** - Streamlined npm scripts and development workflow

### 📚 Documentation Updates

- **Complete hooks reference** - Updated for current implementation with new import hooks
- **Environment setup conventions** - New guide for AI feature development and API key management
- **Troubleshooting guides** - Updated for current architecture and common issues
- **Coding standards refresh** - Object-type PHPDoc guidelines and modern practices

### 🔧 Technical Improvements

- **WordPress 6.6+ compatibility** - Updated minimum requirements and tested compatibility
- **PHP 8.1+ optimization** - Modern PHP features and performance improvements
- **Enhanced error handling** - Better cleanup on error paths and exception management
- **Improved logging system** - Transient-based log storage with configurable limits

### 🎯 Breaking Changes

- **ACF references removed** - Documentation updated to use "enhanced features" terminology
- **PHPUnit bootstrap path** - Fixed for local development (may require configuration update)
- **Hook signature updates** - Some import hooks have updated parameters for better extensibility

---

## [0.9.7] - 2026-02-15

### 🎉 Major Features

- **Progress bar UI overhaul** - Complete redesign with beautiful shimmer animations
- **Real-time export details** - 1-by-1 processing status with post titles
- **Enhanced license detection** - Accurate status detection for 3 different scenarios
- **Complete Japanese localization** - Full translation support with proper HTML rendering

### 🎨 UI/UX Improvements

- **Shimmer animations** - 6 different animation patterns (standard, reverse, alternate, pulse, fast, slow)
- **Progress tracking** - Accurate 1% increment updates during processing
- **Visual feedback** - Processing state with animated gradients, completed state with green color
- **Consistent experience** - Unified UI between import and export operations

### 🌐 Internationalization

- **Complete Japanese translation** - All interface elements translated to natural Japanese
- **HTML tag rendering** - Proper `<code>` tag display for technical documentation
- **Natural terminology** - 'All statuses' instead of 'All posts' for better clarity
- **Context-aware messaging** - Different messages for different license states

### 🔧 Technical Improvements

- **Export batch size fix** - Correctly respects export limits instead of total post count
- **License status detection** - Distinguishes between not installed, inactive, and server unconfigured
- **Error handling** - Enhanced error messages and state management
- **Code cleanup** - Removed all debug code for clean production build

### 📊 User Experience

- **Detailed processing display** - Shows individual post titles during export/import
- **Natural Japanese messaging** - User-friendly messages in proper Japanese
- **Visual progress indicators** - Beautiful animations and color transitions
- **Intuitive operation flow** - Clear next-step instructions for each scenario

### 🛠️ Development

- **Asset management** - Updated .gitattributes with correct file names
- **Build system** - Proper minified asset inclusion for distribution
- **Version requirements** - Updated to WordPress 6.0+ and PHP 8.0+
- **Documentation** - Updated README and changelog with latest features

### 🐛 Bug Fixes

- **Progress bar completion** - Fixed import progress bar not turning green on completion
- **Button state management** - Fixed export button text inconsistency after completion
- **Message display** - Fixed undefined messages in license handling
- **Translation rendering** - Fixed HTML tag display in translated strings

---

## [0.9.6] - 2026-02-13

### Improvements

- **Import refactoring** - Split responsibilities into utility classes for better maintainability

### Technical Debt

- **Release cleanup** - Removed unconditional server/client debug logs for normal usage

---

## [0.9.5] - 2026-02-08

### Improvements

- **Code cleanup and optimization** - Removed duplicate code and unnecessary comments across all PHP files
- **Debug logging system** - Added comprehensive debug logging with `[Swift CSV]` prefix for better troubleshooting
- **JavaScript debugging** - Implemented conditional debug logging in admin scripts based on WP_DEBUG
- **Documentation improvements** - Updated SKILL.md with troubleshooting knowledge base
- **Build system enhancements** - Improved GitHub Actions workflows for better automation

### Bug Fixes

- **CSS optimization issues** - Fixed design breaks caused by aggressive CSS cleanup
- **File naming consistency** - Updated references to use correct JavaScript file names
- **Translation file cleanup** - Removed outdated translation JSON files

### Technical Debt

- **Removed duplicate functions** - Cleaned up redundant PHP functions and CSS rules
- **Code standardization** - Applied consistent coding standards across all files
- **Documentation updates** - Ensured all documentation reflects current implementation

---

## [0.9.4] - 2026-02-07

### New Features

- **Environment setup improvements** - Enhanced direnv configuration for better development experience
- **Debug logging enhancements** - Improved debug output with consistent formatting
- **Build process optimization** - Streamlined npm build scripts

### Improvements

- **Development environment** - Better WP-CLI compatibility and database connection handling
- **Error handling** - Enhanced error messages and debugging information
- **Code quality** - Improved code organization and maintainability

### Bug Fixes

- **WP-CLI connection issues** - Fixed database variable quoting problems
- **Environment variable handling** - Resolved path issues with spaces in directory names

---

## [0.9.3] - 2026-02-06

### New Features

- **AJAX import/export system** - Complete rewrite with modern AJAX handling
- **Real-time progress tracking** - Dynamic progress bars for large file processing
- **Enhanced error handling** - Better error reporting and user feedback
- **WordPress coding standards** - Full compliance with WPCS

### Improvements

- **Performance optimization** - Chunked processing for large CSV files
- **User interface** - Modern responsive design with better UX
- **Memory management** - Optimized memory usage for large datasets
- **Internationalization** - Complete Japanese translation support

### Bug Fixes

- **ACF field export** - Fixed empty ACF columns in exported CSV
- **JavaScript progress elements** - Resolved DOM selector issues after UI refactor
- **Date formatting** - Fixed filename date format bug
- **AJAX response format** - Added proper success flag for WordPress compatibility

---

## [0.9.2] - 2024-01-31

### New Features

- **Multi-value custom fields support** - Import/export multiple values using pipe separator (e.g., "value1|value2|value3")
- **WordPress coding standards compliance** - Applied WPCS to exporter class

### Improvements

- Backward compatible with single-value custom fields
- Automatic empty value filtering in multi-value processing
- Updated documentation with usage examples
- Enhanced code quality and maintainability

## [0.9.1] - 2024-01-31

### New Features

- Docsify-based documentation system
- Automatic API documentation generation
- GitHub Pages deployment support

### Improvements

- CSV processing performance improvements
- Memory usage optimization
- Enhanced error handling

### Bug Fixes

- Memory leak fix for large file processing
- Japanese character encoding issue fix

## [0.9.0] - 2024-01-15

### New Features

- Batch processing functionality
- Custom field support
- Export functionality

---

For complete changelog, see [GitHub repository](https://github.com/firstelementjp/swift-csv/commits/main).

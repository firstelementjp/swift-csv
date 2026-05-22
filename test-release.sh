#!/bin/bash

set -euo pipefail

BRANCH="${1:-$(git rev-parse --abbrev-ref HEAD)}"
RELEASE_DIR="test-release"
ZIP_NAME="fe-csv-import-export-dev.zip"
GENERATED_MINIFIED_FILES=(
	"assets/css/fe-csv-import-export-style.min.css"
	"assets/js/export/fe-csv-import-export/ajax.min.js"
	"assets/js/export/fe-csv-import-export/download.min.js"
	"assets/js/export/fe-csv-import-export/form.min.js"
	"assets/js/export/fe-csv-import-export/logs.min.js"
	"assets/js/export/fe-csv-import-export/original.min.js"
	"assets/js/export/fe-csv-import-export/ui.min.js"
	"assets/js/fe-csv-import-export-core.min.js"
	"assets/js/fe-csv-import-export-export-unified.min.js"
	"assets/js/fe-csv-import-export-import.min.js"
	"assets/js/fe-csv-import-export-license.min.js"
	"assets/js/fe-csv-import-export-main.min.js"
)

cleanup() {
	rm -rf "$RELEASE_DIR"
	rm -f "${GENERATED_MINIFIED_FILES[@]}"
}

trap cleanup EXIT

echo "Testing release process for branch: $BRANCH (dev)"

echo "=== Build assets in working directory ==="
npm run build >/dev/null

rm -rf "$RELEASE_DIR"
mkdir -p "$RELEASE_DIR"

echo "=== Create release tree from git archive (worktree attributes) ==="
git archive --format=tar --prefix=fe-csv-import-export/ --worktree-attributes "$BRANCH" | tar -x -C "$RELEASE_DIR"

echo "=== Inject built minified assets into release tree ==="
mkdir -p "$RELEASE_DIR/fe-csv-import-export/assets/js/export/fe-csv-import-export"
mkdir -p "$RELEASE_DIR/fe-csv-import-export/assets/css"
cp -f assets/js/*.min.js "$RELEASE_DIR/fe-csv-import-export/assets/js/" 2>/dev/null || true
cp -f assets/css/*.min.css "$RELEASE_DIR/fe-csv-import-export/assets/css/" 2>/dev/null || true
cp -f assets/js/export/fe-csv-import-export/*.min.js "$RELEASE_DIR/fe-csv-import-export/assets/js/export/fe-csv-import-export/" 2>/dev/null || true

echo "=== Sanity checks in release tree ==="
echo "Minified JS files:"
find "$RELEASE_DIR/fe-csv-import-export/assets" -name "*.min.js" | wc -l
echo "Minified CSS files:"
find "$RELEASE_DIR/fe-csv-import-export/assets" -name "*.min.css" | wc -l
echo "Source JS files (should be 0):"
find "$RELEASE_DIR/fe-csv-import-export/assets" -name "*.js" -not -name "*.min.js" | wc -l
echo "Source CSS files (should be 0):"
find "$RELEASE_DIR/fe-csv-import-export/assets" -name "*.css" -not -name "*.min.css" | wc -l

echo "=== Create ZIP ==="
rm -f "$ZIP_NAME"
( cd "$RELEASE_DIR" && zip -qr "../$ZIP_NAME" fe-csv-import-export )

echo "Release archive created: $ZIP_NAME"
echo "Size: $(du -h "$ZIP_NAME" | cut -f1)"

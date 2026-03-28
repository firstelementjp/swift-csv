#!/bin/bash

set -euo pipefail

BRANCH="${1:-$(git rev-parse --abbrev-ref HEAD)}"
RELEASE_DIR="test-release"
TAG="v0.9.8-dev"
ZIP_NAME="swift-csv-$TAG.zip"
GENERATED_MINIFIED_FILES=(
	"assets/css/swift-csv-style.min.css"
	"assets/js/export/swift-csv/ajax.min.js"
	"assets/js/export/swift-csv/download.min.js"
	"assets/js/export/swift-csv/form.min.js"
	"assets/js/export/swift-csv/logs.min.js"
	"assets/js/export/swift-csv/original.min.js"
	"assets/js/export/swift-csv/ui.min.js"
	"assets/js/swift-csv-core.min.js"
	"assets/js/swift-csv-export-unified.min.js"
	"assets/js/swift-csv-import.min.js"
	"assets/js/swift-csv-license.min.js"
	"assets/js/swift-csv-main.min.js"
)

cleanup() {
	rm -rf "$RELEASE_DIR"
	rm -f "${GENERATED_MINIFIED_FILES[@]}"
}

trap cleanup EXIT

echo "Testing release process for branch: $BRANCH (tag: $TAG)"

echo "=== Build assets in working directory ==="
npm run build >/dev/null

rm -rf "$RELEASE_DIR"
mkdir -p "$RELEASE_DIR"

echo "=== Create release tree from git archive (worktree attributes) ==="
git archive --format=tar --prefix=swift-csv/ --worktree-attributes "$BRANCH" | tar -x -C "$RELEASE_DIR"

echo "=== Inject built minified assets into release tree ==="
mkdir -p "$RELEASE_DIR/swift-csv/assets/js/export/swift-csv"
cp -f assets/js/*.min.js "$RELEASE_DIR/swift-csv/assets/js/" 2>/dev/null || true
cp -f assets/css/*.min.css "$RELEASE_DIR/swift-csv/assets/css/" 2>/dev/null || true
cp -f assets/js/export/swift-csv/*.min.js "$RELEASE_DIR/swift-csv/assets/js/export/swift-csv/" 2>/dev/null || true

echo "=== Sanity checks in release tree ==="
echo "Minified JS files:"
find "$RELEASE_DIR/swift-csv/assets" -name "*.min.js" | wc -l
echo "Minified CSS files:"
find "$RELEASE_DIR/swift-csv/assets" -name "*.min.css" | wc -l
echo "Source JS files (should be 0):"
find "$RELEASE_DIR/swift-csv/assets" -name "*.js" -not -name "*.min.js" | wc -l
echo "Source CSS files (should be 0):"
find "$RELEASE_DIR/swift-csv/assets" -name "*.css" -not -name "*.min.css" | wc -l

echo "=== Create ZIP ==="
rm -f "$ZIP_NAME"
( cd "$RELEASE_DIR" && zip -qr "../$ZIP_NAME" swift-csv )

echo "Release archive created: $ZIP_NAME"
echo "Size: $(du -h "$ZIP_NAME" | cut -f1)"

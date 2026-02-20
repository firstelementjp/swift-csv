#!/bin/bash

set -euo pipefail

TAG="v0.9.8-test"
RELEASE_DIR="test-release"
ZIP_NAME="swift-csv-$TAG.zip"

echo "Testing release process for tag: $TAG"

echo "=== Build assets in working directory ==="
npm run build >/dev/null

rm -rf "$RELEASE_DIR"
mkdir -p "$RELEASE_DIR"

echo "=== Create release tree from git archive (worktree attributes) ==="
git archive --format=tar --prefix=swift-csv/ --worktree-attributes "$TAG" | tar -x -C "$RELEASE_DIR"

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

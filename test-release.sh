#!/bin/bash

# Test release script
TAG="v0.9.8-test"
RELEASE_DIR="test-release"

echo "Testing release process for tag: $TAG"

# Clean up
rm -rf $RELEASE_DIR
mkdir $RELEASE_DIR

# Extract archive
git archive --format=tar --prefix=swift-csv/ $TAG | tar -x -C $RELEASE_DIR

cd $RELEASE_DIR/swift-csv

echo "=== Before filtering ==="
echo "JS files:"
find assets -name "*.js" | wc -l
echo "CSS files:"
find assets -name "*.css" | wc -l
echo "Minified JS files:"
find assets -name "*.min.js" | wc -l
echo "Minified CSS files:"
find assets -name "*.min.css" | wc -l

# Remove development files
find assets -name "*.js" -not -name "*.min.js" -delete
find assets -name "*.css" -not -name "*.min.css" -delete

# Remove development directories
rm -rf node_modules vendor .github _deprecated

# Remove development files
rm -f package.json package-lock.json composer.json composer.lock
rm -f .gitignore .gitattributes .eslintrc.json .prettierrc phpcs.xml*
rm -f debug.php* .envrc*

# Remove documentation
rm -rf docs phpdoc
rm -f CONTRIBUTING.md

echo "=== After filtering ==="
echo "JS files:"
find assets -name "*.js" | wc -l
echo "CSS files:"
find assets -name "*.css" | wc -l
echo "Minified JS files:"
find assets -name "*.min.js" | wc -l
echo "Minified CSS files:"
find assets -name "*.min.css" | wc -l

# Create final archive
cd ..
tar -czf swift-csv-$TAG.tar.gz swift-csv

echo "Release archive created: swift-csv-$TAG.tar.gz"
echo "Size: $(du -h swift-csv-$TAG.tar.gz | cut -f1)"

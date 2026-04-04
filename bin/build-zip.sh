#!/usr/bin/env bash

# Exit on error
set -e

# Configuration
PLUGIN_SLUG="polski"
BUILD_DIR="dist"
ZIP_NAME="${PLUGIN_SLUG}.zip"

echo "Step 1: Compiling translations..."
if command -v msgfmt >/dev/null 2>&1; then
    for f in languages/*.po; do
        msgfmt "$f" -o "${f%.po}.mo"
        echo " - Compiled: $f"
    done
else
    echo "Warning: msgfmt not found, skipping MO compilation."
fi

echo "Step 2: Cleaning up old build..."
rm -rf "$BUILD_DIR"
rm -f "$ZIP_NAME"
mkdir -p "$BUILD_DIR/$PLUGIN_SLUG"

echo "Step 3: Copying production files..."
# Using rsync with .distignore exclusions if available
if [ -f ".distignore" ]; then
    rsync -rc --exclude-from='.distignore' ./ "$BUILD_DIR/$PLUGIN_SLUG/"
else
    # Fallback to simple copy if .distignore is missing
    cp -r ./ "$BUILD_DIR/$PLUGIN_SLUG/"
fi

echo "Step 4: Final cleanup in dist..."
find "$BUILD_DIR/$PLUGIN_SLUG" -name ".DS_Store" -delete
find "$BUILD_DIR/$PLUGIN_SLUG" -type d -empty -delete

echo "Step 5: Creating ZIP archive..."
cd "$BUILD_DIR"
zip -r "../$ZIP_NAME" "$PLUGIN_SLUG"
cd ..

echo "Step 6: Cleaning temporary files..."
rm -rf "$BUILD_DIR"

echo "Success! Produced: $ZIP_NAME"

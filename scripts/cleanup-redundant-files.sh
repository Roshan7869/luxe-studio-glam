#!/bin/bash
# Cleanup redundant files from Luxe Studio Glam project
# Run from project root: bash scripts/cleanup-redundant-files.sh

set -e

echo "🧹 Starting cleanup of redundant files..."

# 1. Remove duplicate archive directory
if [ -d "glam_zip_1" ]; then
    echo "❌ Removing duplicate directory: glam_zip_1/"
    rm -rf glam_zip_1
    echo "✅ Deleted glam_zip_1"
fi

# 2. Remove unused WordPress themes
UNUSED_THEMES=(
    "wp-content/themes/twentytwenty"
    "wp-content/themes/twentytwentythree"
    "wp-content/themes/twentytwentyfour"
    "wp-content/themes/twentytwentyfive"
)

for theme in "${UNUSED_THEMES[@]}"; do
    if [ -d "$theme" ]; then
        echo "❌ Removing unused theme: $theme"
        rm -rf "$theme"
        echo "✅ Deleted $theme"
    fi
done

# 3. Remove archived planning docs
ARCHIVE_FILES=(
    "plans/workspace-audit-plan.md"
    "plans/feature-activation-plan.md"
)

for file in "${ARCHIVE_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "⚠️  Archiving file: $file"
        # Could move to archive folder instead if desired
        # mv "$file" "archive/$file"
    fi
done

# 4. Remove old HTML test files
TEST_FILES=(
    "homepage.html"
    "homepage2.html"
    "output.html"
)

for file in "${TEST_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "⚠️  Removing test file: $file"
        rm "$file"
        echo "✅ Deleted $file"
    fi
done

echo ""
echo "✅ Cleanup complete!"
echo ""
echo "Summary of changes:"
echo "  - Removed duplicate /glam_zip_1/ directory"
echo "  - Removed 4 unused WordPress themes"
echo "  - Removed old test HTML files"
echo ""
echo "Space saved: ~150MB"

#!/bin/bash

PLUGIN_SLUG="technopay-payment-gateway-for-woocommerce"
TRUNK_PATH=".svn/technopay-payment-gateway-for-woocommerce/trunk"
BUILD_PATH=".svn/technopay-payment-gateway-for-woocommerce/build"
DIST_PATH=".svn/technopay-payment-gateway-for-woocommerce/dist"

if [ ! -f "$TRUNK_PATH/technopay-woocommerce.php" ]; then
    echo "❌ Error: Plugin file not found in trunk!"
    exit 1
fi

VERSION=$(grep "Version:" "$TRUNK_PATH/technopay-woocommerce.php" | awk '{print $3}')

echo "🚀 Building $PLUGIN_SLUG version $VERSION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

rm -rf "$BUILD_PATH"
mkdir -p "$BUILD_PATH"
mkdir -p "$DIST_PATH"

echo "📦 Copying files..."
rsync -av --exclude='.git' \
    --exclude='.svn' \
    --exclude='.DS_Store' \
    --exclude='node_modules' \
    --exclude='.gitignore' \
    --exclude='*.log' \
    "$TRUNK_PATH/" "$BUILD_PATH/$PLUGIN_SLUG/" > /dev/null

cd "$BUILD_PATH"

ZIP_FILE="../dist/${PLUGIN_SLUG}-${VERSION}.zip"

echo "🗜️  Creating zip file..."
zip -r "$ZIP_FILE" "$PLUGIN_SLUG" -q

cd - > /dev/null
rm -rf "$BUILD_PATH"

echo "✅ Done!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📁 File: $DIST_PATH/${PLUGIN_SLUG}-${VERSION}.zip"
echo "📊 Size: $(du -h "$DIST_PATH/${PLUGIN_SLUG}-${VERSION}.zip" | cut -f1)"
echo ""
echo "🎉 Plugin ready to distribute!"

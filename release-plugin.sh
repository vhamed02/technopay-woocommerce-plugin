#!/bin/bash

PLUGIN_SLUG="technopay-payment-gateway-for-woocommerce"
SOURCE_PATH="wp-content/plugins/$PLUGIN_SLUG"
SVN_PATH=".svn/technopay-payment-gateway-for-woocommerce"
SVN_URL="https://plugins.svn.wordpress.org/$PLUGIN_SLUG"

if [ ! -f "$SOURCE_PATH/technopay-woocommerce.php" ]; then
    echo "❌ Error: Plugin source not found!"
    exit 1
fi

if [ ! -d "$SVN_PATH/.svn" ]; then
    echo "📥 Creating local SVN release checkout..."
    mkdir -p "$(dirname "$SVN_PATH")"
    svn checkout "$SVN_URL" "$SVN_PATH" || exit 1
else
    echo "📥 Updating local SVN release checkout..."
    svn update "$SVN_PATH" || exit 1
fi

VERSION=$(grep "Version:" "$SOURCE_PATH/technopay-woocommerce.php" | awk '{print $3}')

echo "🚀 Release TechnoPay Plugin v$VERSION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

echo "🔄 Syncing Git source to SVN trunk..."
rsync -av --delete \
    --exclude='.git' \
    --exclude='.svn' \
    --exclude='.DS_Store' \
    --exclude='*.log' \
    "$SOURCE_PATH/" "$SVN_PATH/trunk/" > /dev/null

cd "$SVN_PATH"

svn add --force trunk > /dev/null
while IFS= read -r status_line; do
    if [ "${status_line:0:1}" = "!" ]; then
        svn rm -- "${status_line:8}"
    fi
done < <(svn status trunk)

echo "📋 SVN Status:"
svn status trunk
echo ""

read -p "📝 Enter commit message (or press Enter for default): " COMMIT_MSG

if [ -z "$COMMIT_MSG" ]; then
    COMMIT_MSG="Version $VERSION - Bug fixes and improvements"
fi

echo ""
echo "💾 Committing to trunk..."
svn ci trunk -m "$COMMIT_MSG"

if [ $? -ne 0 ]; then
    echo "❌ SVN commit failed!"
    cd - > /dev/null
    exit 1
fi

echo ""
read -p "🏷️  Create tag $VERSION? (y/n): " TAG_CONFIRM

if [ "$TAG_CONFIRM" = "y" ] || [ "$TAG_CONFIRM" = "Y" ]; then
    if [ -d "tags/$VERSION" ]; then
        echo "⚠️  Tag $VERSION already exists!"
        echo "⏸️  Existing release tags are immutable; bump the plugin version first."
        cd - > /dev/null
        exit 1
    fi
    
    echo "🏷️  Creating tag $VERSION..."
    svn cp trunk "tags/$VERSION"
    svn ci -m "Tagging version $VERSION"
    
    echo ""
    echo "✅ Release complete!"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "📦 Version: $VERSION"
    echo "🏷️  Tag: tags/$VERSION"
else
    echo "⏸️  Skipped tagging"
fi

cd - > /dev/null

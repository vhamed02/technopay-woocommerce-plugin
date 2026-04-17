#!/bin/bash

PLUGIN_SLUG="technopay-payment-gateway-for-woocommerce"
SVN_PATH=".svn/technopay-payment-gateway-for-woocommerce"

if [ ! -d "$SVN_PATH" ]; then
    echo "❌ Error: SVN directory not found!"
    exit 1
fi

VERSION=$(grep "Version:" "$SVN_PATH/trunk/technopay-woocommerce.php" | awk '{print $3}')

echo "🚀 Release TechnoPay Plugin v$VERSION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

cd "$SVN_PATH"

echo "📋 SVN Status:"
svn status
echo ""

read -p "📝 Enter commit message (or press Enter for default): " COMMIT_MSG

if [ -z "$COMMIT_MSG" ]; then
    COMMIT_MSG="Version $VERSION - Bug fixes and improvements"
fi

echo ""
echo "💾 Committing to trunk..."
svn ci -m "$COMMIT_MSG"

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
        read -p "🔄 Overwrite? (y/n): " OVERWRITE
        if [ "$OVERWRITE" = "y" ] || [ "$OVERWRITE" = "Y" ]; then
            svn rm "tags/$VERSION" -m "Remove old tag $VERSION"
        else
            echo "⏸️  Skipped tagging"
            cd - > /dev/null
            exit 0
        fi
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

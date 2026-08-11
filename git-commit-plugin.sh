#!/bin/bash

PLUGIN_PATH="wp-content/plugins/technopay-payment-gateway-for-woocommerce"

if [ ! -d "$PLUGIN_PATH" ] || [ -L "$PLUGIN_PATH" ]; then
    echo "❌ Error: Tracked plugin source directory not found!"
    exit 1
fi

VERSION=$(grep "Version:" "$PLUGIN_PATH/technopay-woocommerce.php" | awk '{print $3}')

echo "📝 Git Commit for TechnoPay Plugin v$VERSION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

git add "$PLUGIN_PATH"

echo ""
echo "📋 Changed files:"
git status --short "$PLUGIN_PATH"

echo ""
read -p "💬 Enter commit message (or press Enter for default): " COMMIT_MSG

if [ -z "$COMMIT_MSG" ]; then
    COMMIT_MSG="Update TechnoPay plugin to version $VERSION"
fi

git commit -m "$COMMIT_MSG"

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Committed successfully!"
    echo ""
    read -p "🚀 Push to remote? (y/n): " PUSH_CONFIRM
    
    if [ "$PUSH_CONFIRM" = "y" ] || [ "$PUSH_CONFIRM" = "Y" ]; then
        git push
        echo "✅ Pushed to remote!"
    else
        echo "⏸️  Skipped push. Run 'git push' manually when ready."
    fi
else
    echo "❌ Commit failed or nothing to commit"
fi

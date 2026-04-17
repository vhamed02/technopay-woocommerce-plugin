#!/bin/bash

PLUGIN_PATH="wp-content/plugins/technopay-payment-gateway-for-woocommerce"

if [ ! -L "$PLUGIN_PATH" ]; then
    echo "❌ Error: Plugin is not a symlink!"
    exit 1
fi

VERSION=$(grep "Version:" "$PLUGIN_PATH/technopay-woocommerce.php" | awk '{print $3}')

echo "📝 Git Commit for TechnoPay Plugin v$VERSION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

git add .svn/technopay-payment-gateway-for-woocommerce/trunk/

echo ""
echo "📋 Changed files:"
git status --short .svn/technopay-payment-gateway-for-woocommerce/trunk/

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

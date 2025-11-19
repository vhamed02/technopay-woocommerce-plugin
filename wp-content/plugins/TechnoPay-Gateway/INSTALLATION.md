# TechnoPay Gateway Installation Guide

## Quick Installation

1. **Upload Plugin Files**
   - Upload the entire `technopay-gateway` folder to `/wp-content/plugins/`
   - Or use WordPress admin: Plugins > Add New > Upload Plugin

2. **Activate Plugin**
   - Go to WordPress Admin > Plugins
   - Find "TechnoPay Gateway for WooCommerce" and click "Activate"

3. **Configure Gateway**
   - Go to WooCommerce > Settings > Payments
   - Find "TechnoPay" in the payment methods list
   - Click "Set up" or "Manage"

4. **Enter Merchant Details**
   - **Merchant Name**: Your business name
   - **Merchant ID**: Your TechnoPay merchant ID
   - **Merchant Secret**: Your TechnoPay merchant secret
   - **Title**: Payment method title (default: "TechnoPay")
   - **Description**: Payment method description
   - **Test Mode**: Enable for testing
   - **Currency Mode**: Select based on your store currency

5. **Save Settings**
   - Click "Save changes"
   - Enable the gateway by checking the checkbox

## Testing the Integration

### Test Signature Generation
You can test the signature generation using the test file:
```bash
php tests/test-signature.php
```

### Test Currency Conversion
You can test the currency conversion using:
```bash
php tests/test-currency-conversion.php
```

### Test Payment Flow
1. Create a test product in WooCommerce
2. Add it to cart and proceed to checkout
3. Select TechnoPay as payment method
4. Complete the order (use test credentials)

## Callback URLs

The plugin automatically creates these callback URLs:
- **Success**: `https://yoursite.com/technopay-callback/`
- **Failure**: `https://yoursite.com/technopay-fallback/`

Make sure these URLs are accessible and not blocked by security plugins.

## Troubleshooting

### Common Issues

1. **"Gateway not properly configured"**
   - Check that Merchant ID and Secret are entered correctly
   - Verify the secret is in base64 format

2. **"Signature generation failed"**
   - Ensure OpenSSL extension is enabled
   - Check PHP version (7.4+ required)

3. **"Order not found" on callback**
   - Check that rewrite rules are working
   - Verify callback URLs are accessible

4. **Payment verification fails**
   - Check API credentials
   - Verify network connectivity to api.technopay.ir

### Debug Mode

To enable debug logging:
1. Go to WooCommerce > Settings > Advanced > Logs
2. Enable logging for "TechnoPay Gateway"
3. Check logs for detailed error messages

## Security Notes

- Keep your merchant secret secure
- Use HTTPS for your website
- Regularly update the plugin
- Test in staging environment first

## Support

For technical support:
- Check the plugin logs for error details
- Verify API credentials with TechnoPay
- Contact TechnoPay support for API issues

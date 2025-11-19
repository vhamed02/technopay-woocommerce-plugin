# TechnoPay Gateway for WooCommerce

A WordPress plugin that integrates TechnoPay payment gateway with WooCommerce.

## Features

- Complete TechnoPay payment gateway integration
- Secure signature generation using AES-128-CBC encryption
- Payment ticket creation and verification
- Automatic order status management
- Support for Iranian mobile numbers with validation
- Automatic mobile number retrieval from user profile
- Detailed error handling and logging
- Checkout validation for required mobile numbers
- Persian (Farsi) language support
- TechnoPay logo display in checkout
- WooCommerce HPOS (High-Performance Order Storage) compatible
- Quick settings access from plugins page

## Installation

1. Upload the plugin files to `/wp-content/plugins/technopay-gateway/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Settings > Payments
   - **OR** click the "Settings" link next to the plugin in the plugins list
4. Enable TechnoPay Gateway
5. Configure your merchant settings:
   - Merchant Name
   - Merchant ID
   - Merchant Secret

## Configuration

### Required Settings

- **Merchant Name**: Your business name
- **Merchant ID**: Your TechnoPay merchant ID
- **Merchant Secret**: Your TechnoPay merchant secret key

### Optional Settings

- **Title**: Payment method title shown to customers
- **Description**: Payment method description
- **Test Mode**: Enable for testing purposes
- **Currency Mode**: How amounts should be processed (Auto-detect, IRR, IRT)

## Testing

You can test the signature generation using:
```bash
php tests/test-signature.php
```

You can test mobile number validation using:
```bash
php tests/test-mobile-validation.php
```

You can test currency conversion using:
```bash
php tests/test-currency-conversion.php
```

## Mobile Number Requirements

The plugin requires Iranian mobile numbers for payment processing. It automatically:

1. **Retrieves mobile numbers** from:
   - Billing phone field (primary)
   - User profile `billing_phone` meta
   - User profile `mobile` meta
   - User profile `phone` meta

2. **Validates Iranian mobile format**:
   - 11 digits starting with `09` (e.g., `09123456789`)
   - 10 digits starting with `9` (e.g., `9123456789`)
   - Automatically cleans formatting (removes spaces, dashes, etc.)

3. **Enforces validation** at checkout when TechnoPay is selected

## Language Support

The plugin includes Persian (Farsi) language support as the default language:

- **Persian (fa_IR)**: Default language with complete translation
- **English**: Available as fallback
- **Automatic detection**: Uses WordPress language setting
- **RTL support**: Proper right-to-left text display

Default interface text:
- **Title**: تکنوپی (TechnoPay)
- **Description**: پرداخت اقساطی از طریق تکنوپی (Installment payment via TechnoPay)

To use English language:
1. Set WordPress language to English in Settings > General
2. The plugin will automatically display in English
3. All error messages and interface text will be in English

## Currency Handling

The plugin automatically handles different Iranian currencies:

### **Auto-Detection Mode** (Default)
- **IRR (Iranian Rial)**: Amounts are divided by 10 to convert to Toman
- **IRT (Iranian Toman)**: No conversion needed
- **Other Currencies**: Not supported (will show error)

### **Manual Currency Mode**
You can override auto-detection by selecting:
- **IRR Mode**: Force division by 10 for all amounts
- **IRT Mode**: No conversion for all amounts

### **Examples**
- `100,000 IRR` → `10,000` (sent to API)
- `10,000 IRT` → `10,000` (sent to API)
- `$10.00 USD` → Error (unsupported currency)

## API Integration

The plugin integrates with TechnoPay API endpoints:

- **Purchase**: `https://api.technopay.ir/payment/purchase`
- **Verify**: `https://api.technopay.ir/payment/verify`

## Callback URLs

The plugin automatically handles these callback URLs:
- Success: `yoursite.com/technopay-callback/`
- Failure: `yoursite.com/technopay-fallback/`

## Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- OpenSSL extension enabled
- WooCommerce HPOS (High-Performance Order Storage) compatible

## Error Handling

The plugin provides detailed error messages for:
- Invalid merchant credentials
- Signature generation failures
- API communication errors
- Payment verification failures

## Support

For support and documentation, visit [TechnoPay](https://technopay.ir)

## License

GPL v2 or later

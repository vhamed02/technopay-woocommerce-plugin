<?php
/**
 * Test file for TechnoPay mobile number validation
 */

// Test mobile number validation function
function is_valid_iranian_mobile($mobile_number) {
    // Remove any non-numeric characters
    $mobile_number = preg_replace('/[^0-9]/', '', $mobile_number);
    
    // Iranian mobile numbers should be 11 digits starting with 09
    if (strlen($mobile_number) === 11 && substr($mobile_number, 0, 2) === '09') {
        return true;
    }
    
    // Also accept 10 digits starting with 9 (without leading 0)
    if (strlen($mobile_number) === 10 && substr($mobile_number, 0, 1) === '9') {
        return true;
    }
    
    return false;
}

// Test cases
$test_numbers = array(
    '09123456789',    // Valid 11-digit format
    '9123456789',     // Valid 10-digit format
    '0912-345-6789',  // Valid with dashes
    '0912 345 6789',  // Valid with spaces
    '+989123456789',  // Valid with country code
    '08123456789',    // Invalid (landline)
    '1234567890',     // Invalid (not Iranian)
    '0912345678',     // Invalid (too short)
    '091234567890',   // Invalid (too long)
    '',               // Empty
    'abc123',         // Invalid characters
);

echo "Testing Iranian mobile number validation:\n\n";

foreach ($test_numbers as $number) {
    $is_valid = is_valid_iranian_mobile($number);
    $status = $is_valid ? '✓ VALID' : '✗ INVALID';
    echo sprintf("%-20s -> %s\n", $number, $status);
}

echo "\nTest completed!\n";

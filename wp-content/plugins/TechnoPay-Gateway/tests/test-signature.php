<?php
/**
 * Test file for TechnoPay signature generation
 * This file can be used to test the signature generation independently
 */

// Test data (replace with your actual values)
$merchant_id = '01J2VERKYMEDA8Z14TCTNY4CPF';
$merchant_secret = '5dn7D/0wHjVCk8wR+MHd1g==';
$timestamp = 1721502446;
$payment_type = 'cpg';

/**
 * Generate signature function (copied from the main plugin)
 */
function generate_signature($merchant_id, $merchant_secret, $timestamp, $payment_type) {
    $plain_signature = $merchant_id . ';' . $timestamp . ';' . $payment_type . ';' . $merchant_secret;
    
    // Decode merchant secret from base64
    $key = base64_decode($merchant_secret);
    
    // Ensure key is exactly 16 bytes
    if (strlen($key) < 16) {
        $key = str_pad($key, 16, "\0");
    } else {
        $key = substr($key, 0, 16);
    }
    
    // Generate random IV
    $iv = openssl_random_pseudo_bytes(16);
    
    // Encrypt the plain signature
    $encrypted = openssl_encrypt($plain_signature, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
    
    if ($encrypted === false) {
        throw new Exception('Failed to generate signature.');
    }
    
    // Create JSON payload
    $json_data = json_encode(array(
        'iv' => base64_encode($iv),
        'value' => base64_encode($encrypted)
    ));
    
    // Return base64 encoded JSON
    return base64_encode($json_data);
}

// Test the signature generation
try {
    echo "Testing TechnoPay signature generation...\n";
    echo "Merchant ID: " . $merchant_id . "\n";
    echo "Merchant Secret: " . $merchant_secret . "\n";
    echo "Timestamp: " . $timestamp . "\n";
    echo "Payment Type: " . $payment_type . "\n";
    echo "\n";
    
    $signature = generate_signature($merchant_id, $merchant_secret, $timestamp, $payment_type);
    echo "Generated Signature: " . $signature . "\n";
    
    // Decode and verify the signature structure
    $decoded_json = base64_decode($signature);
    $signature_data = json_decode($decoded_json, true);
    
    echo "\nSignature Structure:\n";
    echo "IV: " . $signature_data['iv'] . "\n";
    echo "Value: " . $signature_data['value'] . "\n";
    
    echo "\nTest completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

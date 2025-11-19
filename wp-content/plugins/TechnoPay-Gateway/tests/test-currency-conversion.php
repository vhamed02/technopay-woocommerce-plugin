<?php
/**
 * Test file for TechnoPay currency conversion
 */

// Test currency conversion function
function convert_amount_to_api_format($amount, $currency) {
    // Convert to integer (remove decimals)
    $amount = intval($amount);
    
    // Handle different Iranian currencies
    switch (strtoupper($currency)) {
        case 'IRR':
            // IRR: Divide by 10 (convert to Toman equivalent)
            return intval($amount / 10);
            
        case 'IRT':
            // IRT: No conversion needed (already in Toman)
            return $amount;
            
        default:
            // For unsupported currencies, throw an error
            throw new Exception('Only IRR and IRT currencies are supported by TechnoPay.');
    }
}

// Test cases
$test_cases = array(
    // IRR tests
    array('amount' => 100000, 'currency' => 'IRR', 'expected' => 10000, 'description' => '100,000 IRR -> 10,000 (Toman)'),
    array('amount' => 50000, 'currency' => 'IRR', 'expected' => 5000, 'description' => '50,000 IRR -> 5,000 (Toman)'),
    array('amount' => 1000, 'currency' => 'IRR', 'expected' => 100, 'description' => '1,000 IRR -> 100 (Toman)'),
    
    // IRT tests
    array('amount' => 10000, 'currency' => 'IRT', 'expected' => 10000, 'description' => '10,000 IRT -> 10,000 (no change)'),
    array('amount' => 5000, 'currency' => 'IRT', 'expected' => 5000, 'description' => '5,000 IRT -> 5,000 (no change)'),
    array('amount' => 100, 'currency' => 'IRT', 'expected' => 100, 'description' => '100 IRT -> 100 (no change)'),
    
    // Unsupported currency (should throw error)
    array('amount' => 10, 'currency' => 'USD', 'expected' => 'ERROR', 'description' => '10 USD -> ERROR (unsupported)'),
);

echo "Testing TechnoPay currency conversion:\n\n";

foreach ($test_cases as $test) {
    try {
        $result = convert_amount_to_api_format($test['amount'], $test['currency']);
        
        if ($test['expected'] === 'ERROR') {
            $status = '✗ FAIL (Should have thrown error)';
            echo sprintf("%-50s -> %s\n", $test['description'], $status);
        } else {
            $status = ($result === $test['expected']) ? '✓ PASS' : '✗ FAIL';
            echo sprintf("%-50s -> %s (Got: %d, Expected: %d)\n", 
                $test['description'], 
                $status, 
                $result, 
                $test['expected']
            );
        }
    } catch (Exception $e) {
        if ($test['expected'] === 'ERROR') {
            $status = '✓ PASS (Error thrown as expected)';
            echo sprintf("%-50s -> %s\n", $test['description'], $status);
        } else {
            $status = '✗ FAIL (Unexpected error)';
            echo sprintf("%-50s -> %s (%s)\n", $test['description'], $status, $e->getMessage());
        }
    }
}

echo "\nTest completed!\n";

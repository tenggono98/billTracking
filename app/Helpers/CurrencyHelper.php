<?php

namespace App\Helpers;

class CurrencyHelper
{
    /**
     * Sanitize currency input from Indonesian format to numeric value
     * 
     * Handles formats like:
     * - "260.000" -> 260000
     * - "260.000,00" -> 260000.00
     * - "260,000.00" -> 260000.00 (US format)
     * - "260000" -> 260000
     * 
     * @param mixed $value Input value (string or numeric)
     * @return float|null Sanitized numeric value or null if invalid
     */
    public static function sanitize($value): ?float
    {
        if (empty($value) && $value !== '0' && $value !== 0) {
            return null;
        }

        // Convert to string for processing
        $stringValue = (string) $value;
        
        // Remove currency symbols and extra spaces
        $cleaned = preg_replace('/[^\d.,]/', '', $stringValue);
        $cleaned = trim($cleaned);
        
        if (empty($cleaned)) {
            return null;
        }

        // Handle Indonesian format: dot (.) = thousand separator, comma (,) = decimal separator
        // Example: 260.000,00 or 260.000
        if (preg_match('/^(\d{1,3}(?:\.\d{3})*(?:,\d{2})?)$/', $cleaned)) {
            // Indonesian format: 260.000,00
            $cleaned = str_replace('.', '', $cleaned); // Remove thousand separators
            $cleaned = str_replace(',', '.', $cleaned); // Convert comma to dot for decimal
        }
        // Handle US format: comma (,) = thousand separator, dot (.) = decimal separator
        // Example: 260,000.00 or 260,000
        elseif (preg_match('/^(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)$/', $cleaned)) {
            // US format: 260,000.00
            $cleaned = str_replace(',', '', $cleaned); // Remove thousand separators
        }
        // If no clear format, try to detect
        else {
            // Count dots and commas
            $dotCount = substr_count($cleaned, '.');
            $commaCount = substr_count($cleaned, ',');
            
            if ($dotCount > 0 && $commaCount > 0) {
                // Both present - determine which is decimal
                $lastDot = strrpos($cleaned, '.');
                $lastComma = strrpos($cleaned, ',');
                
                if ($lastDot > $lastComma) {
                    // Dot is decimal: 260,000.00
                    $cleaned = str_replace(',', '', $cleaned);
                } else {
                    // Comma is decimal: 260.000,00
                    $cleaned = str_replace('.', '', $cleaned);
                    $cleaned = str_replace(',', '.', $cleaned);
                }
            } elseif ($dotCount > 1) {
                // Multiple dots - likely thousand separators: 260.000.00
                $parts = explode('.', $cleaned);
                $lastPart = end($parts);
                if (strlen($lastPart) <= 2) {
                    // Last part is decimal
                    $cleaned = str_replace('.', '', $cleaned);
                    $cleaned = substr_replace($cleaned, '.', -strlen($lastPart), 0);
                } else {
                    // All dots are thousand separators
                    $cleaned = str_replace('.', '', $cleaned);
                }
            } elseif ($commaCount > 1) {
                // Multiple commas - likely thousand separators: 260,000,00
                $parts = explode(',', $cleaned);
                $lastPart = end($parts);
                if (strlen($lastPart) <= 2) {
                    // Last part is decimal
                    $cleaned = str_replace(',', '', $cleaned);
                    $cleaned = substr_replace($cleaned, '.', -strlen($lastPart), 0);
                } else {
                    // All commas are thousand separators
                    $cleaned = str_replace(',', '', $cleaned);
                }
            } elseif ($dotCount == 1) {
                // Single dot - could be decimal or thousand
                $parts = explode('.', $cleaned);
                if (strlen($parts[1] ?? '') <= 2) {
                    // Likely decimal: 260000.00
                    // Keep as is
                } else {
                    // Likely thousand separator: 260.000
                    $cleaned = str_replace('.', '', $cleaned);
                }
            } elseif ($commaCount == 1) {
                // Single comma - could be decimal or thousand
                $parts = explode(',', $cleaned);
                if (strlen($parts[1] ?? '') <= 2) {
                    // Likely decimal: 260000,00
                    $cleaned = str_replace(',', '.', $cleaned);
                } else {
                    // Likely thousand separator: 260,000
                    $cleaned = str_replace(',', '', $cleaned);
                }
            } else {
                // No separators, just numbers
                // Keep as is
            }
        }
        
        // Remove any remaining non-numeric characters except decimal point
        $cleaned = preg_replace('/[^\d.]/', '', $cleaned);
        
        // Convert to float
        $amount = (float) $cleaned;
        
        // Validate amount is reasonable (not too large, positive)
        if ($amount >= 0 && $amount < 1000000000000) { // Max 1 trillion
            return $amount;
        }

        return null;
    }

    /**
     * Format number to Indonesian currency format for display
     * 
     * @param float|int|string $value Numeric value
     * @param int $decimals Number of decimal places (default: 0)
     * @return string Formatted string like "260.000" or "260.000,00"
     */
    public static function format($value, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        $numericValue = (float) $value;
        
        // Use number_format with Indonesian format: dot (.) = thousand separator, comma (,) = decimal separator
        return number_format($numericValue, $decimals, ',', '.');
    }
}


<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AiExtractionService
{
    /**
     * Default prompts
     */
    private const DEFAULT_TRANSFER_PROMPT = "You are an AI system specialized in payment proof verification using image understanding and OCR.

Your task is to analyze images that represent PROOF OF PAYMENT, such as:
- Mobile banking transfer confirmation
- QRIS payment success screen
- Card payment summary
- E-wallet transaction history

────────────────────────
STEP 1 — PAYMENT TYPE IDENTIFICATION
────────────────────────
Determine the payment method shown:
- Bank transfer
- QRIS
- Debit / Credit Card
- E-wallet

This helps identify which numeric value represents the ACTUAL paid amount.

────────────────────────
STEP 2 — TRANSACTION STATUS VALIDATION
────────────────────────
Only extract amounts from payments with:
- Status: SUCCESS / BERHASIL / COMPLETED

Ignore:
- Pending
- Failed
- Reversed
- Estimated amounts

────────────────────────
STEP 3 — AMOUNT EXTRACTION (HIGH PRECISION)
────────────────────────
Locate the ACTUAL PAID AMOUNT, typically labeled as:
- \"Rp\"
- \"Amount\"
- \"Total\"
- \"Paid\"
- \"Total Pembayaran\"

Rules:
- Use Indonesian numeric format.
- Carefully distinguish:
  - Thousand separator (.)
  - Decimal separator (,)
- Normalize all values into integer IDR.

Examples:
- \"Rp 341.634\" → 341634
- \"Rp. 260,000.00\" → 260000

────────────────────────
STEP 4 — DUPLICATE & MULTI-PAYMENT HANDLING
────────────────────────
If multiple payment proofs exist:
- Treat each transaction independently.
- Do NOT sum unless explicitly instructed.
- Output each payment separately.

────────────────────────
STEP 5 — CONTEXTUAL CROSS-CHECK
────────────────────────
Extract supporting metadata:
- Transaction date & time
- Transaction ID / Reference number
- Merchant name (if visible)

This is used ONLY for validation, not calculation.

────────────────────────
OUTPUT FORMAT (STRICT)
────────────────────────
Return JSON ONLY.

{
  \"payment_type\": \"bank_transfer | qris | card | e_wallet\",
  \"transaction_status\": \"success | failed | pending\",
  \"paid_amount_idr\": 260000,
  \"currency\": \"IDR\",
  \"transaction_id\": \"TRX123456789\",
  \"transaction_date\": \"2024-01-15 14:30:00\",
  \"merchant_name\": \"Merchant Name (if visible)\",
  \"confidence\": \"high | medium | low\",
  \"notes\": [
    \"Any ambiguity, missing text, or assumptions made\"
  ]
}

DO NOT:
- Extract amounts from failed or pending transactions
- Use account balances or available balance
- Sum multiple transactions unless explicitly shown as total
- Guess unclear amounts

CRITICAL: After returning the JSON, also provide the FINAL NUMERIC VALUE on a separate line for easy parsing. Format: PAID_AMOUNT=260000";

    private const DEFAULT_BILL_PROMPT = "You are a financial document analysis AI using image understanding + OCR.
Your task is to extract and aggregate bill totals from ONE OR MORE receipts
that may appear together in a single image.

This prompt uses HARD RULES. You are NOT allowed to simplify or ignore them.

════════════════════════════════════
GLOBAL ASSUMPTION (MANDATORY)
════════════════════════════════════
NEVER assume that one image equals one receipt.

An image MAY contain:
- 1 receipt
- 2 receipts
- 5 receipts
- 10 or more receipts

All receipts MUST be detected and processed independently.

════════════════════════════════════
STEP 1 — SPATIAL SEGMENTATION (NON-NEGOTIABLE)
════════════════════════════════════
You MUST first segment the image into receipt candidates using VISUAL layout.

Create a new receipt candidate WHEN ANY of the following are detected:
1. Separate vertical text columns (left vs right)
2. Repeated header patterns (even if partially visible)
3. Repeated date/time formats
4. Repeated merchant names
5. Repeated cashier / transaction ID / \"Nomor Struk\"
6. Repeated keyword blocks such as:
   - \"Cetak Asli\"
   - \"Cetak Ulang\"
   - \"Subtotal\"
   - \"Total\"
   - \"QRIS\"
   - \"Kasir\"

IMPORTANT:
- Overlapping paper does NOT mean same receipt.
- Physical attachment does NOT mean same receipt.
- Different text flow direction = different receipt.

════════════════════════════════════
STEP 2 — RECEIPT COUNT CONFIRMATION
════════════════════════════════════
After segmentation, COUNT how many receipt candidates exist.

CRITICAL RULE:
If more than ONE \"Total\" block exists in different spatial regions,
YOU MUST treat them as DIFFERENT receipts.

You are FORBIDDEN from merging them.

════════════════════════════════════
STEP 3 — OCR EXTRACTION (HIGH PRECISION MODE)
════════════════════════════════════
For EACH receipt candidate:
- Extract all readable text (printed and handwritten)
- Preserve dots (.) and commas (,)
- Use Indonesian currency rules:
  - Dot (.) = thousand separator
  - Comma (,) = decimal separator

NEVER convert numbers before context validation.

════════════════════════════════════
STEP 4 — FINANCIAL STRUCTURE PARSING
════════════════════════════════════
For EACH receipt candidate, identify:
- Line items (if visible)
- Subtotal
- Discount (negative values)
- Tax / service
- FINAL TOTAL

VALID TOTAL SELECTION RULES:
1. Prefer explicit labels:
   - \"Total\"
   - \"Total Pembayaran\"
   - \"Grand Total\"
2. If subtotal == total, use that value.
3. If multiple totals appear within ONE receipt candidate,
   choose the LAST payable amount.
4. NEVER mix totals between receipt candidates.

════════════════════════════════════
STEP 5 — HANDWRITTEN SAFETY RULES
════════════════════════════════════
If handwritten numbers exist:
- Cross-check with item prices or subtotal if possible
- If ambiguity remains, lower confidence
- NEVER guess unclear digits

════════════════════════════════════
STEP 6 — AGGREGATION (STRICT)
════════════════════════════════════
Once ALL receipt candidates are processed:
- Convert each FINAL TOTAL to integer IDR
- Sum ALL receipt totals
- This sum is the ONLY aggregated bill amount

════════════════════════════════════
FORBIDDEN BEHAVIORS
════════════════════════════════════
You MUST NOT:
- Select only one receipt
- Select the largest total only
- Ignore secondary receipts
- Merge subtotals from different receipts
- Assume one receipt per image

════════════════════════════════════
OUTPUT FORMAT (JSON ONLY)
════════════════════════════════════
Return ONLY valid JSON.

{
  \"receipt_count\": 3,
  \"receipts\": [
    {
      \"receipt_index\": 1,
      \"detected_markers\": [\"Cetak Asli\", \"Total\", \"QRIS\"],
      \"final_total_idr\": 75000,
      \"confidence\": \"high\"
    },
    {
      \"receipt_index\": 2,
      \"detected_markers\": [\"Cetak Ulang\", \"Total\"],
      \"final_total_idr\": 93500,
      \"confidence\": \"high\"
    }
  ],
  \"aggregated_bill_total_idr\": 168500,
  \"currency\": \"IDR\",
  \"warnings\": []
}

CRITICAL: After returning the JSON, also provide the FINAL NUMERIC VALUE on a separate line for easy parsing. Format: FINAL_TOTAL=168500";

    private const DEFAULT_FALLBACK_PROMPT = "Extract the MONETARY AMOUNT from this image.

CRITICAL INSTRUCTIONS:
1. Find the largest money amount in the image
2. Look for numbers with currency symbols like 'Rp', 'Rp.', 'IDR', or near words like 'Total', 'Jumlah', 'Amount'
3. IGNORE these completely:
   - Account numbers (any sequence of 8+ digits that might be account numbers)
   - Phone numbers
   - Transaction IDs or reference numbers
   - Dates or times
   - Any numbers that are clearly NOT money amounts
4. The amount should be a reasonable money value (not too small like 13.42, not account numbers)
5. Return ONLY the numeric value (no currency symbols, no commas, no dots). Example: if you see 'Rp. 260,000.00', return '260000'";

    /**
     * Extract amount from image using Google Vision API
     *
     * @param string $imagePath Full path to the image
     * @param string $type 'transfer' for payment proof, 'bill' for bill proof
     * @return float|null Extracted amount or null on failure
     */
    public function extractAmountFromImage(string $imagePath, string $type = 'transfer'): ?float
    {
        if ($type === 'bill') {
            return $this->extractTotalFromBill($imagePath);
        }

        return $this->extractAmountFromTransfer($imagePath);
    }

    /**
     * Extract amount from image content (for Livewire temporary files)
     *
     * @param string $imageContent Raw image file content
     * @param string $type 'transfer' for payment proof, 'bill' for bill proof
     * @return float|null Extracted amount or null on failure
     */
    public function extractAmountFromImageContent(string $imageContent, string $type = 'transfer'): ?float
    {
        try {
            $apiKey = Setting::get('ai_api_key');
            if (!$apiKey) {
                Log::error('AI API key not configured');
                throw new \Exception('AI API key is not configured. Please set it in Settings > AI Settings.');
            }

            if (empty($imageContent)) {
                Log::error('Image content is empty');
                throw new \Exception('Image file is empty or corrupted');
            }
            
            $base64Image = base64_encode($imageContent);
            
            if (empty($base64Image)) {
                Log::error('Failed to encode image');
                throw new \Exception('Failed to process image');
            }

            // Get appropriate prompt
            if ($type === 'bill') {
                $prompt = Setting::get('ai_prompt_bill', self::DEFAULT_BILL_PROMPT);
            } else {
                $prompt = Setting::get('ai_prompt_transfer', self::DEFAULT_TRANSFER_PROMPT);
            }

            // Use Google Vision API with Gemini for better text extraction
            // First, try OCR with Vision API
            $ocrResult = $this->performOCR($apiKey, $base64Image);
            
            if ($ocrResult) {
                // Then use Gemini API for intelligent extraction with prompt
                $extractedAmount = $this->extractWithGemini($apiKey, $base64Image, $ocrResult, $prompt);
                if ($extractedAmount) {
                    return $extractedAmount;
                }
            }
            
            // Fallback: try to parse directly from image using Gemini even without OCR
            Log::info('Trying direct Gemini extraction without OCR');
            $extractedAmount = $this->extractWithGemini($apiKey, $base64Image, '', $prompt);
            
            // If bill type and still no result, try fallback prompt
            if ($type === 'bill' && !$extractedAmount) {
                $fallbackPrompt = Setting::get('ai_fallback_prompt', self::DEFAULT_FALLBACK_PROMPT);
                $extractedAmount = $this->extractWithGemini($apiKey, $base64Image, $ocrResult ?? '', $fallbackPrompt);
            }
            
            return $extractedAmount;
            
        } catch (\Exception $e) {
            Log::error('AI Extraction from content failed: ' . $e->getMessage(), [
                'type' => $type,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Extract amount from transfer proof
     *
     * @param string $imagePath
     * @return float|null
     */
    public function extractAmountFromTransfer(string $imagePath): ?float
    {
        $prompt = Setting::get('ai_prompt_transfer', self::DEFAULT_TRANSFER_PROMPT);
        return $this->extractWithPrompt($imagePath, $prompt);
    }

    /**
     * Extract total from bill proof (handles handwritten and multiple payments)
     *
     * @param string $imagePath
     * @return float|null
     */
    public function extractTotalFromBill(string $imagePath): ?float
    {
        $prompt = Setting::get('ai_prompt_bill', self::DEFAULT_BILL_PROMPT);
        $amount = $this->extractWithPrompt($imagePath, $prompt);

        // If extraction fails, try fallback prompt
        if ($amount === null) {
            $fallbackPrompt = Setting::get('ai_fallback_prompt', self::DEFAULT_FALLBACK_PROMPT);
            $amount = $this->extractWithPrompt($imagePath, $fallbackPrompt);
        }

        return $amount;
    }

    /**
     * Extract amount using custom prompt with Google Vision API
     *
     * @param string $imagePath
     * @param string $prompt
     * @return float|null
     */
    private function extractWithPrompt(string $imagePath, string $prompt): ?float
    {
        try {
            $apiKey = Setting::get('ai_api_key');
            if (!$apiKey) {
                Log::error('AI API key not configured');
                throw new \Exception('AI API key is not configured. Please set it in Settings > AI Settings.');
            }

            // Read image file (full size, no compression)
            if (!Storage::exists($imagePath)) {
                Log::error("Image not found: {$imagePath}");
                throw new \Exception("Image file not found: {$imagePath}");
            }

            $imageContent = Storage::get($imagePath);
            
            if (empty($imageContent)) {
                Log::error("Image content is empty: {$imagePath}");
                throw new \Exception("Image file is empty or corrupted");
            }
            
            $base64Image = base64_encode($imageContent);
            
            if (empty($base64Image)) {
                Log::error("Failed to encode image: {$imagePath}");
                throw new \Exception("Failed to process image");
            }

            // Use Google Vision API with Gemini for better text extraction
            // First, try OCR with Vision API
            $ocrResult = $this->performOCR($apiKey, $base64Image);
            
            if ($ocrResult) {
                // Then use Gemini API for intelligent extraction with prompt
                $extractedAmount = $this->extractWithGemini($apiKey, $base64Image, $ocrResult, $prompt);
                if ($extractedAmount) {
                    return $extractedAmount;
                }
            }
            
            // Fallback: try to parse directly from image using Gemini even without OCR
            Log::info('Trying direct Gemini extraction without OCR');
            $extractedAmount = $this->extractWithGemini($apiKey, $base64Image, '', $prompt);
            return $extractedAmount;
            
        } catch (\Exception $e) {
            Log::error('AI Extraction failed: ' . $e->getMessage(), [
                'imagePath' => $imagePath,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Re-throw to show error to user
        }
    }

    /**
     * Perform OCR using Google Vision API
     *
     * @param string $apiKey
     * @param string $base64Image
     * @return string|null
     */
    private function performOCR(string $apiKey, string $base64Image): ?string
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://vision.googleapis.com/v1/images:annotate?key={$apiKey}", [
                'requests' => [
                    [
                        'image' => [
                            'content' => $base64Image,
                        ],
                        'features' => [
                            [
                                'type' => 'TEXT_DETECTION',
                                'maxResults' => 1,
                            ],
                        ],
                    ],
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['responses'][0]['textAnnotations'][0]['description'])) {
                    $text = $data['responses'][0]['textAnnotations'][0]['description'];
                    Log::info('OCR extracted text', ['length' => strlen($text)]);
                    return $text;
                } else {
                    Log::warning('OCR response has no text annotations', ['response' => $data]);
                }
            } else {
                $error = $response->json();
                Log::error('OCR API error', ['error' => $error]);
                throw new \Exception('OCR API error: ' . ($error['error']['message'] ?? 'Unknown error'));
            }

            return null;
        } catch (\Exception $e) {
            Log::error('OCR failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    /**
     * Extract amount using Gemini API with custom prompt
     *
     * @param string $apiKey
     * @param string $base64Image
     * @param string $ocrText
     * @param string $prompt
     * @return float|null
     */
    private function extractWithGemini(string $apiKey, string $base64Image, string $ocrText, string $prompt): ?float
    {
        try {
            // Use Gemini API for intelligent extraction
            $geminiApiKey = Setting::get('ai_gemini_api_key', $apiKey);
            $model = Setting::get('ai_gemini_model', 'gemini-1.5-flash');
            
            if (empty($geminiApiKey)) {
                Log::warning('Gemini API key not set, using Vision API key');
                $geminiApiKey = $apiKey;
            }
            
            // Detect if this is a bill extraction (may have multiple bills) or transfer/payment
            $isBillExtraction = str_contains($prompt, 'TOTAL BILL AMOUNT') || 
                                str_contains($prompt, 'bill document') ||
                                str_contains($prompt, 'invoice/bill') ||
                                str_contains($prompt, 'financial document analysis') ||
                                str_contains($prompt, 'SPATIAL SEGMENTATION') ||
                                str_contains($prompt, 'RECEIPT COUNT CONFIRMATION') ||
                                str_contains($prompt, 'financial document analysis AI');
            
            $isPaymentExtraction = str_contains($prompt, 'payment proof verification') ||
                                   str_contains($prompt, 'PROOF OF PAYMENT') ||
                                   str_contains($prompt, 'PAYMENT TYPE IDENTIFICATION');
            
            if ($isBillExtraction) {
                // Bill extraction - may have multiple bills/receipts
                // Check if using new strict prompt format
                $isNewStrictFormat = str_contains($prompt, 'SPATIAL SEGMENTATION') || 
                                     str_contains($prompt, 'RECEIPT COUNT CONFIRMATION') ||
                                     str_contains($prompt, 'FORBIDDEN BEHAVIORS');
                
                if ($isNewStrictFormat) {
                    // New strict format with spatial segmentation
                    $parts = [
                        [
                            'text' => "{$prompt}\n\nCRITICAL REMINDER:\n- Follow ALL steps STRICTLY IN ORDER\n- Perform SPATIAL SEGMENTATION first before extracting any numbers\n- Count receipt_count accurately\n- Extract final_total_idr from EACH receipt\n- Sum ALL receipts into aggregated_bill_total_idr\n- Return JSON format as specified\n- Also provide FINAL_TOTAL=<numeric_value> on separate line",
                        ],
                    ];
                    
                    if (!empty($ocrText)) {
                        $parts[] = [
                            'text' => "\n\nOCR Text extracted from image:\n{$ocrText}\n\nANALYSIS STEPS (STRICT ORDER):\n1. SPATIAL SEGMENTATION: Identify separate receipt regions by looking for:\n   - Repeated headers, dates, merchant names\n   - Multiple 'Total' blocks in different regions\n   - Keywords like 'Cetak Asli', 'Cetak Ulang', 'QRIS', 'Kasir'\n   - Different text columns or flow directions\n\n2. RECEIPT COUNT: Count how many distinct receipt candidates exist\n\n3. FOR EACH RECEIPT:\n   - Extract all text preserving dots (.) and commas (,)\n   - Identify line items, subtotal, discount, tax, FINAL TOTAL\n   - Use Indonesian format: dot (.) = thousand, comma (,) = decimal\n   - Select the LAST payable amount if multiple totals exist\n\n4. AGGREGATION:\n   - Convert each final_total_idr to integer\n   - Sum ALL receipt totals\n   - This is aggregated_bill_total_idr\n\nFORBIDDEN: Do NOT select only one receipt, ignore secondary receipts, merge subtotals from different receipts, or assume one receipt per image.",
                        ];
                    }
                } else {
                    // Legacy format
                    $parts = [
                        [
                            'text' => "{$prompt}\n\nCRITICAL INSTRUCTIONS FOR MULTIPLE BILLS:\n1. This image may contain MULTIPLE BILLS/RECEIPTS side by side or stacked together\n2. You MUST identify ALL separate bills/receipts in the image\n3. For EACH bill, extract its TOTAL amount\n4. SUM all the totals together to get the FINAL TOTAL\n5. Return ONLY the FINAL TOTAL (sum of all bills)\n\nFormat: just numbers, no currency symbols, no commas, no dots.\nExample: If you find 2 receipts with totals 75.000 and 93.500, return '168500' (the sum).",
                        ],
                    ];
                    
                    if (!empty($ocrText)) {
                        $parts[] = [
                            'text' => "\n\nOCR Text extracted from image:\n{$ocrText}\n\nANALYSIS STEPS:\n1. Count how many separate bills/receipts are in this image (look for different receipt numbers like 'TRX-...', different dates, different transaction IDs, or labels like 'Cetak Ulang' and 'Cetak Asli')\n2. For EACH bill/receipt found:\n   - Find its TOTAL amount (look for 'Total', 'Total Tagihan', final amount at bottom of receipt)\n   - Write down each total\n3. Add all totals together: Total Bill 1 + Total Bill 2 + Total Bill 3 + ... = FINAL TOTAL\n4. Return ONLY the FINAL TOTAL (the sum of all bills)\n\nIGNORE: account numbers, phone numbers, dates, times, transaction IDs, individual item prices. Only use the FINAL TOTAL of each bill/receipt.",
                        ];
                    }
                }
            } elseif ($isPaymentExtraction) {
                // Payment proof extraction - may return JSON
                $parts = [
                    [
                        'text' => "{$prompt}\n\nCRITICAL INSTRUCTIONS FOR PAYMENT PROOF:\n1. Only extract amounts from SUCCESSFUL transactions\n2. Identify the ACTUAL PAID AMOUNT (not account balance or available balance)\n3. Return JSON format as specified\n4. Also provide PAID_AMOUNT= value on a separate line for easy parsing\n\nFormat: Return JSON with paid_amount_idr field, then on next line: PAID_AMOUNT=<numeric_value>",
                    ],
                ];
                
                if (!empty($ocrText)) {
                    $parts[] = [
                        'text' => "\n\nOCR Text extracted from image:\n{$ocrText}\n\nANALYSIS STEPS:\n1. Verify transaction status is SUCCESS/BERHASIL/COMPLETED\n2. Identify payment type (bank transfer, QRIS, card, e-wallet)\n3. Find the ACTUAL PAID AMOUNT (look for 'Rp', 'Amount', 'Total', 'Paid', 'Total Pembayaran')\n4. Extract transaction metadata (ID, date, merchant) for validation\n5. Return JSON format with paid_amount_idr field\n\nIGNORE: account numbers, phone numbers, available balance, pending/failed transactions. Only extract from successful payment confirmations.",
                    ];
                }
            } else {
                // Transfer extraction (legacy format)
                $parts = [
                    [
                        'text' => "{$prompt}\n\nCRITICAL: Return ONLY the numeric money amount. Do NOT return account numbers, phone numbers, dates, transaction IDs, or any other numbers. Only return the transfer/payment amount. Format: just numbers, no currency symbols, no commas, no dots. Example: if amount is 'Rp. 260,000.00', return '260000' only.",
                    ],
                ];
                
                if (!empty($ocrText)) {
                    $parts[] = [
                        'text' => "\n\nOCR Text extracted from image:\n{$ocrText}\n\nFrom the OCR text above, identify and extract ONLY the transfer/payment amount. IGNORE account numbers (like 6800674491, 6041149385), dates, times, transaction IDs, and other non-amount numbers. Only return the money amount.",
                    ];
                }
            }
            
            $parts[] = [
                'inline_data' => [
                    'mime_type' => 'image/jpeg',
                    'data' => $base64Image,
                ],
            ];
            
            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$geminiApiKey}", [
                'contents' => [
                    [
                        'parts' => $parts,
                    ],
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    $text = $data['candidates'][0]['content']['parts'][0]['text'];
                    Log::info('Gemini extracted text', ['text' => $text]);
                    $amount = $this->parseAmount($text);
                    if ($amount) {
                        return $amount;
                    }
                } else {
                    Log::warning('Gemini response has no text', ['response' => $data]);
                }
            } else {
                $error = $response->json();
                Log::error('Gemini API error', ['error' => $error]);
                // Don't throw, try fallback
            }

            // Fallback: parse from OCR text directly
            if (!empty($ocrText)) {
                Log::info('Falling back to OCR text parsing');
                return $this->parseAmountFromText($ocrText);
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Gemini extraction failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            // Fallback to OCR text parsing if available
            if (!empty($ocrText)) {
                return $this->parseAmountFromText($ocrText);
            }
            throw $e;
        }
    }

    /**
     * Parse amount from text response
     *
     * @param string $text
     * @return float|null
     */
    private function parseAmount(string $text): ?float
    {
        // First, try to extract from JSON format (new prompts return JSON)
        // For bill extraction: aggregated_bill_total_idr
        if (preg_match('/"aggregated_bill_total_idr"\s*:\s*(\d+)/', $text, $jsonMatch)) {
            $amount = (float) $jsonMatch[1];
            if ($amount > 0 && $amount < 1000000000000) {
                Log::info('Extracted amount from JSON aggregated_bill_total_idr', ['amount' => $amount]);
                return $amount;
            }
        }
        
        // For payment proof extraction: paid_amount_idr
        if (preg_match('/"paid_amount_idr"\s*:\s*(\d+)/', $text, $jsonMatch)) {
            $amount = (float) $jsonMatch[1];
            if ($amount > 0 && $amount < 1000000000000) {
                Log::info('Extracted amount from JSON paid_amount_idr', ['amount' => $amount]);
                return $amount;
            }
        }
        
        // Try to extract from FINAL_TOTAL= pattern (bill fallback format)
        if (preg_match('/FINAL_TOTAL\s*=\s*(\d+)/i', $text, $finalMatch)) {
            $amount = (float) $finalMatch[1];
            if ($amount > 0 && $amount < 1000000000000) {
                Log::info('Extracted amount from FINAL_TOTAL pattern', ['amount' => $amount]);
                return $amount;
            }
        }
        
        // Try to extract from PAID_AMOUNT= pattern (payment proof fallback format)
        if (preg_match('/PAID_AMOUNT\s*=\s*(\d+)/i', $text, $paidMatch)) {
            $amount = (float) $paidMatch[1];
            if ($amount > 0 && $amount < 1000000000000) {
                Log::info('Extracted amount from PAID_AMOUNT pattern', ['amount' => $amount]);
                return $amount;
            }
        }
        
        // Remove currency symbols and extra spaces
        $cleaned = preg_replace('/[^\d.,]/', '', $text);
        $cleaned = trim($cleaned);
        
        // Handle Indonesian format: 260.000,00 or 260,000.00 or 260000
        // Remove thousand separators (dots or commas before decimal)
        // Keep decimal separator (comma or dot after last separator)
        
        // Check if it's Indonesian format (dot as thousand separator, comma as decimal)
        // Example: 260.000,00 or 260.000
        if (preg_match('/^(\d{1,3}(?:\.\d{3})*(?:,\d{2})?)$/', $cleaned)) {
            // Indonesian format: 260.000,00
            $cleaned = str_replace('.', '', $cleaned); // Remove thousand separators
            $cleaned = str_replace(',', '.', $cleaned); // Convert comma to dot for decimal
        }
        // Check if it's US format (comma as thousand separator, dot as decimal)
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
                // Check if last dot is decimal (has 2 digits after)
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
                // Check if last comma is decimal (has 2 digits after)
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
        if ($amount > 0 && $amount < 1000000000000) { // Max 1 trillion
            return $amount;
        }

        return null;
    }

    /**
     * Parse amount directly from OCR text
     *
     * @param string $ocrText
     * @return float|null
     */
    private function parseAmountFromText(string $ocrText): ?float
    {
        // Look for common patterns with proper format handling
        // Prioritize amounts with currency symbols or near amount-related keywords
        $patterns = [
            // Rp 260.000,00 or Rp 260,000.00 or Rp 260000 (with currency symbol - highest priority)
            '/Rp\s*[:\s.]*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)/i',
            // Amount near keywords like "Transfer", "Jumlah", "Total", "Amount"
            '/(?:Transfer|Jumlah|Total|Amount|Nominal|Bayar|Paid)[\s:]*Rp?\s*[:\s.]*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)/i',
            // Amount in transaction details (after "Ke" or "Dari" but not account numbers)
            '/(?:Transfer|Jumlah|Total)[\s:]*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d{2})?)/i',
            // Amount with currency format (has separators - likely money)
            '/(\d{1,3}(?:[.,]\d{3})+(?:[.,]\d{2})?)/',
            // Amount with decimal (2 decimal places - likely money)
            '/(\d+[.,]\d{2})/',
        ];

        $bestMatch = null;
        $bestAmount = null;

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $ocrText, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $rawAmount = $match[1];
                    
                    // Use the same parsing logic as parseAmount
                    $cleaned = preg_replace('/[^\d.,]/', '', $rawAmount);
                    
                    // Handle Indonesian format: 260.000,00
                    if (preg_match('/^(\d{1,3}(?:\.\d{3})*(?:,\d{2})?)$/', $cleaned)) {
                        $cleaned = str_replace('.', '', $cleaned);
                        $cleaned = str_replace(',', '.', $cleaned);
                    }
                    // Handle US format: 260,000.00
                    elseif (preg_match('/^(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)$/', $cleaned)) {
                        $cleaned = str_replace(',', '', $cleaned);
                    }
                    // Single separator detection
                    else {
                        $dotCount = substr_count($cleaned, '.');
                        $commaCount = substr_count($cleaned, ',');
                        
                        if ($dotCount == 1 && $commaCount == 0) {
                            $parts = explode('.', $cleaned);
                            if (strlen($parts[1] ?? '') <= 2) {
                                // Decimal: 260000.00
                            } else {
                                // Thousand: 260.000
                                $cleaned = str_replace('.', '', $cleaned);
                            }
                        } elseif ($commaCount == 1 && $dotCount == 0) {
                            $parts = explode(',', $cleaned);
                            if (strlen($parts[1] ?? '') <= 2) {
                                // Decimal: 260000,00
                                $cleaned = str_replace(',', '.', $cleaned);
                            } else {
                                // Thousand: 260,000
                                $cleaned = str_replace(',', '', $cleaned);
                            }
                        } else {
                            // Remove all separators if ambiguous
                            $cleaned = str_replace([',', '.'], '', $cleaned);
                        }
                    }
                    
                    $cleaned = preg_replace('/[^\d.]/', '', $cleaned);
                    $amount = (float) $cleaned;
                    
                    // Filter out account numbers and invalid amounts
                    // Account numbers are usually 8-16 digits with no separators or specific patterns
                    // Valid amounts usually have separators or are reasonable money values
                    $isValidAmount = false;
                    
                    // Check if it looks like a valid money amount
                    if ($amount >= 1000 && $amount < 1000000000000) { // Between 1k and 1 trillion
                        // If original had separators (dots/commas), it's likely money
                        if (preg_match('/[.,]/', $rawAmount)) {
                            $isValidAmount = true;
                        }
                        // If it's a round number in reasonable range (1000-999999999), could be money
                        elseif ($amount >= 1000 && $amount <= 999999999 && $amount % 1000 == 0) {
                            $isValidAmount = true;
                        }
                        // If it has decimal places, likely money
                        elseif (preg_match('/[.,]\d{2}$/', $rawAmount)) {
                            $isValidAmount = true;
                        }
                        // If it's near currency symbol or amount keywords, likely money
                        elseif (preg_match('/(?:Rp|Transfer|Jumlah|Total|Amount)/i', $ocrText, $contextMatch, PREG_OFFSET_CAPTURE)) {
                            $contextPos = $contextMatch[0][1];
                            $matchPos = strpos($ocrText, $rawAmount);
                            // If amount is within 50 chars of currency/keyword, it's likely money
                            if ($matchPos !== false && abs($matchPos - $contextPos) < 50) {
                                $isValidAmount = true;
                            }
                        }
                    }
                    
                    if ($isValidAmount) {
                        if ($bestAmount === null || $amount > $bestAmount) {
                            $bestAmount = $amount;
                            $bestMatch = $amount;
                        }
                    }
                }
            }
        }

        return $bestMatch;
    }

    /**
     * Extract bills from text using Gemini API
     *
     * @param string $text User input text containing bill information
     * @return array Array of extracted bills with structure: [['total_amount' => float, 'date' => string|null, 'payment_amount' => float], ...]
     */
    public function extractBillsFromText(string $text): array
    {
        try {
            $apiKey = Setting::get('ai_api_key');
            if (!$apiKey) {
                Log::error('AI API key not configured');
                throw new \Exception('AI API key is not configured. Please set it in Settings > AI Settings.');
            }

            $geminiApiKey = Setting::get('ai_gemini_api_key', $apiKey);
            $model = Setting::get('ai_gemini_model', 'gemini-1.5-flash');
            
            if (empty($geminiApiKey)) {
                Log::warning('Gemini API key not set, using Vision API key');
                $geminiApiKey = $apiKey;
            }

            $prompt = "Anda adalah AI yang ahli dalam mengekstrak informasi tagihan dari teks.

Tugas Anda:
1. Identifikasi jumlah tagihan/bill dalam teks yang diberikan
2. Untuk setiap tagihan, ekstrak informasi berikut:
   - total_amount: Jumlah total tagihan (WAJIB, dalam format angka tanpa separator, contoh: 100000 untuk Rp 100.000)
   - date: Tanggal tagihan (OPSIONAL, format YYYY-MM-DD, jika tidak ada gunakan tanggal hari ini: " . now()->format('Y-m-d') . ")
   - payment_amount: Jumlah pembayaran (OPSIONAL, default 0 jika tidak disebutkan)

ATURAN PENTING:
- Jika teks menyebutkan beberapa tagihan (contoh: 'Tagihan 1: Rp 100.000, Tagihan 2: Rp 200.000'), ekstrak SEMUA tagihan tersebut
- Jika hanya ada 1 tagihan, tetap buat array dengan 1 elemen
- Format angka: hapus semua separator (titik, koma) kecuali untuk desimal
- Jika ada kata 'Rp', 'rupiah', atau simbol mata uang, ekstrak angka setelahnya
- Jika tanggal tidak disebutkan, gunakan tanggal hari ini: " . now()->format('Y-m-d') . "
- Jika payment_amount tidak disebutkan, gunakan 0

FORMAT OUTPUT (WAJIB JSON):
{
  \"bills\": [
    {
      \"total_amount\": 100000,
      \"date\": \"2024-01-15\",
      \"payment_amount\": 0
    },
    {
      \"total_amount\": 200000,
      \"date\": \"2024-01-15\",
      \"payment_amount\": 50000
    }
  ]
}

Hanya kembalikan JSON, tidak ada teks tambahan.";

            $parts = [
                [
                    'text' => $prompt . "\n\nTeks yang diberikan user:\n\n" . $text . "\n\nEkstrak informasi tagihan dari teks di atas dan kembalikan dalam format JSON yang diminta.",
                ],
            ];

            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$geminiApiKey}", [
                'contents' => [
                    [
                        'parts' => $parts,
                    ],
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    $responseText = $data['candidates'][0]['content']['parts'][0]['text'];
                    Log::info('Gemini extracted bills from text', ['response' => $responseText]);
                    
                    // Try to extract JSON from response
                    $jsonMatch = [];
                    if (preg_match('/\{[\s\S]*\}/', $responseText, $jsonMatch)) {
                        $jsonData = json_decode($jsonMatch[0], true);
                        
                        if (json_last_error() === JSON_ERROR_NONE && isset($jsonData['bills']) && is_array($jsonData['bills'])) {
                            $bills = [];
                            
                            foreach ($jsonData['bills'] as $bill) {
                                // Validate and sanitize bill data
                                $totalAmount = isset($bill['total_amount']) ? (float) $bill['total_amount'] : 0;
                                $paymentAmount = isset($bill['payment_amount']) ? (float) ($bill['payment_amount'] ?? 0) : 0;
                                $date = isset($bill['date']) && !empty($bill['date']) 
                                    ? $bill['date'] 
                                    : now()->format('Y-m-d');
                                
                                // Validate date format
                                try {
                                    $dateObj = \Carbon\Carbon::parse($date);
                                    $date = $dateObj->format('Y-m-d');
                                } catch (\Exception $e) {
                                    $date = now()->format('Y-m-d');
                                }
                                
                                // Only add if total_amount is valid
                                if ($totalAmount > 0) {
                                    $bills[] = [
                                        'total_amount' => $totalAmount,
                                        'date' => $date,
                                        'payment_amount' => max(0, $paymentAmount),
                                    ];
                                }
                            }
                            
                            if (!empty($bills)) {
                                Log::info('Successfully extracted bills', ['count' => count($bills)]);
                                return $bills;
                            }
                        }
                    }
                    
                    Log::warning('Failed to parse JSON from Gemini response', ['response' => $responseText]);
                    throw new \Exception('Gagal memparse respons dari AI. Format tidak valid.');
                } else {
                    Log::warning('Gemini response has no text', ['response' => $data]);
                    throw new \Exception('AI tidak mengembalikan respons yang valid.');
                }
            } else {
                $error = $response->json();
                Log::error('Gemini API error', ['error' => $error]);
                throw new \Exception('Error API: ' . ($error['error']['message'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            Log::error('AI Extraction from text failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}


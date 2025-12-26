<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Branch;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class PdfExportService
{
    /**
     * Export bills to PDF for a specific branch with filters
     *
     * @param int|null $branchId Branch ID or null for all branches
     * @param array $filters ['date_from', 'date_to', 'status']
     * @return string PDF content
     */
    public function exportBranchBills(?int $branchId = null, array $filters = []): string
    {
        $effectiveFilters = $filters;
        if ($branchId) {
            $effectiveFilters['branch_id'] = $branchId;
        }

        $query = Bill::filtered($effectiveFilters);

        $bills = $query->orderBy('date', 'desc')->get();
        
        $branch = $branchId ? Branch::find($branchId) : null;

        // Calculate summary
        $totalBills = $bills->sum('total_amount');
        $totalPayments = $bills->sum('payment_amount');
        $outstanding = $totalBills - $totalPayments;
        $count = $bills->count();

        $html = $this->generateHtml($bills, $branch, $filters, [
            'total_bills' => $totalBills,
            'total_payments' => $totalPayments,
            'outstanding' => $outstanding,
            'count' => $count,
        ]);

        return $this->generatePdf($html);
    }

    /**
     * Generate HTML content for PDF
     *
     * @param \Illuminate\Database\Eloquent\Collection $bills
     * @param Branch|null $branch
     * @param array $filters
     * @param array $summary
     * @return string
     */
    private function generateHtml($bills, ?Branch $branch, array $filters, array $summary): string
    {
        $dateFrom = $filters['date_from'] ?? 'All';
        $dateTo = $filters['date_to'] ?? 'All';
        $branchName = $branch ? $branch->name : 'All Branches';

        // Group bills by branch
        $billsByBranch = $bills->groupBy('branch_id');

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.6;
            color: #000000;
            background: #ffffff;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #000000;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .header h1 {
            margin: 0 0 8px 0;
            font-size: 22pt;
            color: #000000;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .header-info {
            margin-top: 6px;
            color: #000000;
            font-size: 9pt;
        }
        .header-info p {
            margin: 2px 0;
            line-height: 1.3;
        }
        .summary {
            background: #f8f9fa;
            padding: 10px 12px;
            margin-bottom: 12px;
            border-top: 1px solid #dee2e6;
            border-bottom: 1px solid #dee2e6;
        }
        .summary-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }
        .summary-row {
            display: table-row;
        }
        .summary-item {
            display: table-cell;
            padding: 6px 10px;
            vertical-align: middle;
        }
        .summary-label {
            font-weight: 600;
            color: #000000;
            font-size: 9pt;
            margin-right: 8px;
        }
        .summary-value {
            font-weight: 700;
            color: #000000;
            font-size: 11pt;
        }
        .branch-section {
            margin-bottom: 0;
            page-break-inside: avoid;
        }
        .branch-header {
            background: #f8f9fa;
            color: #000000;
            padding: 8px 12px;
            font-size: 10pt;
            font-weight: 700;
            margin-bottom: 0;
            border-bottom: 2px solid #000000;
        }
        .branch-summary {
            background: #f8f9fa;
            padding: 4px 12px;
            font-size: 8pt;
            color: #000000;
            border-bottom: 1px solid #dee2e6;
        }
        .bill-card {
            background: #ffffff;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 0;
            page-break-after: always;
            page-break-inside: avoid;
        }
        .bill-card.first-bill {
            page-break-before: auto;
        }
        .bill-card.last-bill {
            page-break-after: auto;
        }
        .bill-data {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f3f5;
        }
        .bill-info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .bill-info-table td {
            padding: 4px 8px;
            vertical-align: top;
        }
        .bill-info-table td:first-child {
            padding-left: 0;
        }
        .bill-info-table td:last-child {
            padding-right: 0;
            text-align: right;
        }
        .bill-label {
            font-size: 8pt;
            color: #000000;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 2px;
            letter-spacing: 0.3px;
            display: block;
        }
        .bill-value {
            font-size: 10pt;
            color: #000000;
            font-weight: 600;
            display: block;
        }
        .bill-date {
            font-size: 10pt;
            color: #000000;
        }
        .status {
            padding: 5px 12px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
            letter-spacing: 0.5px;
        }
        .status.paid {
            background: #d5f4e6;
            color: #000000;
        }
        .status.partial {
            background: #fef9e7;
            color: #000000;
        }
        .status.pending {
            background: #fadbd8;
            color: #000000;
        }
        .images-section {
            padding: 10px 12px;
            background: #ffffff;
        }
        .images-title {
            font-size: 8pt;
            font-weight: 700;
            color: #000000;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .images-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin: 0;
        }
        .images-table td {
            width: 50%;
            vertical-align: top;
            padding: 0;
        }
        .image-cell {
            text-align: center;
            background: #ffffff;
            border: 1px solid #dee2e6;
            padding: 8px;
        }
        .image-cell img {
            max-width: 100%;
            max-height: 420px;
            width: auto;
            height: auto;
            display: block;
            margin: 0 auto;
            object-fit: contain;
            border: 1px solid #e9ecef;
        }
        .image-label {
            font-size: 8pt;
            color: #000000;
            margin-top: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .footer {
            margin-top: 12px;
            padding-top: 8px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            color: #000000;
            font-size: 8pt;
        }
        .no-images {
            text-align: center;
            color: #000000;
            font-style: italic;
            padding: 12px 8px;
            font-size: 8pt;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Laporan Pelacakan Tagihan</h1>
        <div class="header-info">
            <p><strong>Cabang:</strong> ' . htmlspecialchars($branchName) . '</p>
            <p><strong>Rentang Tanggal:</strong> ' . htmlspecialchars($dateFrom) . ' sampai ' . htmlspecialchars($dateTo) . '</p>
            <p><strong>Dibuat:</strong> ' . now()->format('d-m-Y H:i:s') . '</p>
        </div>
    </div>

    <div class="summary">
        <table class="summary-grid">
            <tr class="summary-row">
                <td class="summary-item">
                    <span class="summary-label">Total Tagihan:</span>
                    <span class="summary-value">Rp ' . number_format($summary['total_bills'], 0, ',', '.') . '</span>
                </td>
                <td class="summary-item">
                    <span class="summary-label">Total Pembayaran:</span>
                    <span class="summary-value">Rp ' . number_format($summary['total_payments'], 0, ',', '.') . '</span>
                </td>
                <td class="summary-item">
                    <span class="summary-label">Sisa:</span>
                    <span class="summary-value">Rp ' . number_format($summary['outstanding'], 0, ',', '.') . '</span>
                </td>
                <td class="summary-item">
                    <span class="summary-label">Jumlah Transaksi:</span>
                    <span class="summary-value">' . $summary['count'] . '</span>
                </td>
            </tr>
        </table>
    </div>';

        // Group bills by branch and render
        $isFirstBill = true;
        $allBills = $bills->all();
        $totalAllBills = count($allBills);
        $currentBillIndex = 0;
        
        foreach ($billsByBranch as $branchId => $branchBills) {
            $firstBill = $branchBills->first();
            $branchName = $firstBill->branch->name;
            
            // Calculate branch summary
            $branchTotalBills = $branchBills->sum('total_amount');
            $branchTotalPayments = $branchBills->sum('payment_amount');
            $branchOutstanding = $branchTotalBills - $branchTotalPayments;
            
            $html .= '<div class="branch-section">
        <div class="branch-header">' . htmlspecialchars($branchName) . '</div>
        <div class="branch-summary">
            Total: Rp ' . number_format($branchTotalBills, 0, ',', '.') . ' | 
            Pembayaran: Rp ' . number_format($branchTotalPayments, 0, ',', '.') . ' | 
            Sisa: Rp ' . number_format($branchOutstanding, 0, ',', '.') . '
        </div>';

            foreach ($branchBills as $bill) {
                $currentBillIndex++;
                $isLastBillOverall = ($currentBillIndex === $totalAllBills);
                
                $cardClass = 'bill-card';
                if ($isFirstBill) {
                    $cardClass .= ' first-bill';
                }
                if ($isLastBillOverall) {
                    $cardClass .= ' last-bill';
                }
                $transferImageData = $bill->payment_proof_image_path 
                    ? $this->getImageBase64($bill->payment_proof_image_path) 
                    : null;
                $billImageData = $bill->bill_image_path 
                    ? $this->getImageBase64($bill->bill_image_path) 
                    : null;

                // Translate status
                $statusText = [
                    'paid' => 'LUNAS',
                    'partial' => 'SEBAGIAN',
                    'pending' => 'MENUNGGU'
                ];
                $statusDisplay = $statusText[$bill->status] ?? strtoupper($bill->status);
                
                $html .= '<div class="' . $cardClass . '">
            <div class="bill-data">
                <table class="bill-info-table">
                    <tr>
                        <td style="width: 20%;">
                            <span class="bill-label">Tanggal</span>
                            <span class="bill-value bill-date">' . $bill->date->format('d M Y') . '</span>
                            <span class="bill-label" style="margin-top: 4px; display: block;">Cabang</span>
                            <span class="bill-value" style="font-size: 9pt; color: #000000;">' . htmlspecialchars($bill->branch->name) . '</span>
                        </td>
                        <td style="width: 20%;">
                            <span class="bill-label">Jumlah Tagihan</span>
                            <span class="bill-value">Rp ' . number_format($bill->total_amount, 0, ',', '.') . '</span>
                        </td>
                        <td style="width: 20%;">
                            <span class="bill-label">Jumlah Pembayaran</span>
                            <span class="bill-value">Rp ' . number_format($bill->payment_amount, 0, ',', '.') . '</span>
                        </td>
                        <td style="width: 20%;">
                            <span class="bill-label">Sisa</span>
                            <span class="bill-value">Rp ' . number_format($bill->outstanding_amount, 0, ',', '.') . '</span>
                        </td>
                        <td style="width: 20%;">
                            <span class="status ' . $bill->status . '">' . $statusDisplay . '</span>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="images-section">';

                if ($transferImageData || $billImageData) {
                    $html .= '<div class="images-title">Bukti Gambar</div>
                <table class="images-table">
                    <tr>';
                    
                    if ($transferImageData && isset($transferImageData['data'])) {
                        $html .= '<td>
                            <div class="image-cell">
                                <img src="data:' . $transferImageData['mime'] . ';base64,' . $transferImageData['data'] . '" />
                                <div class="image-label">Bukti Transfer</div>
                            </div>
                        </td>';
                    } else {
                        $html .= '<td>
                            <div class="image-cell">
                                <div class="no-images" style="padding: 20px;">Tidak Ada Bukti Transfer</div>
                            </div>
                        </td>';
                    }
                    
                    if ($billImageData && isset($billImageData['data'])) {
                        $html .= '<td>
                            <div class="image-cell">
                                <img src="data:' . $billImageData['mime'] . ';base64,' . $billImageData['data'] . '" />
                                <div class="image-label">Bukti Tagihan</div>
                            </div>
                        </td>';
                    } else {
                        $html .= '<td>
                            <div class="image-cell">
                                <div class="no-images" style="padding: 20px;">Tidak Ada Bukti Tagihan</div>
                            </div>
                        </td>';
                    }
                    
                    $html .= '</tr>
                </table>';
                } else {
                    $html .= '<div class="no-images">Tidak ada gambar tersedia</div>';
                }

                $html .= '</div>
        </div>';
                
                $isFirstBill = false;
            }

            $html .= '</div>';
        }

        $html .= '<div class="footer">
        <p>Dibuat oleh Sistem Pelacakan Tagihan pada ' . now()->format('d-m-Y H:i:s') . '</p>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Get image as base64 string
     *
     * @param string $path
     * @return array|null Returns ['data' => base64_string, 'mime' => mime_type] or null
     */
    private function getImageBase64(string $path): ?array
    {
        try {
            // Normalize path - remove 'public/' prefix if exists
            $normalizedPath = preg_replace('#^public/#', '', $path);
            
            // Try public disk first (for 'public' storage)
            $disk = Storage::disk('public');
            
            if ($disk->exists($normalizedPath)) {
                $imageContent = $disk->get($normalizedPath);
                if (empty($imageContent)) {
                    return null;
                }
                
                // Detect MIME type from file extension
                $extension = strtolower(pathinfo($normalizedPath, PATHINFO_EXTENSION));
                $mimeTypes = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                ];
                $mimeType = $mimeTypes[$extension] ?? 'image/jpeg';
                
                return [
                    'data' => base64_encode($imageContent),
                    'mime' => $mimeType
                ];
            }
            
            // Fallback: try with original path on public disk
            if ($disk->exists($path)) {
                $imageContent = $disk->get($path);
                if (empty($imageContent)) {
                    return null;
                }
                
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mimeTypes = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                ];
                $mimeType = $mimeTypes[$extension] ?? 'image/jpeg';
                
                return [
                    'data' => base64_encode($imageContent),
                    'mime' => $mimeType
                ];
            }
            
            // Fallback: try default storage
            if (Storage::exists($path)) {
                $imageContent = Storage::get($path);
                if (empty($imageContent)) {
                    return null;
                }
                
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mimeTypes = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                ];
                $mimeType = $mimeTypes[$extension] ?? 'image/jpeg';
                
                return [
                    'data' => base64_encode($imageContent),
                    'mime' => $mimeType
                ];
            }
        } catch (\Exception $e) {
            // Log error but continue
            \Log::error('Failed to load image for PDF: ' . $e->getMessage(), [
                'path' => $path,
                'normalized_path' => $normalizedPath ?? null
            ]);
        }

        return null;
    }

    /**
     * Generate PDF from HTML
     *
     * @param string $html
     * @return string PDF content
     */
    private function generatePdf(string $html): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}


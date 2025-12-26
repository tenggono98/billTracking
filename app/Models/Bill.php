<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'user_id',
        'bill_image_path',
        'total_amount',
        'payment_proof_image_path',
        'payment_amount',
        'status',
        'date',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'payment_amount' => 'decimal:2',
        'date' => 'date',
    ];

    /**
     * Get the branch that owns the bill
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the user that created the bill
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate outstanding amount
     */
    public function getOutstandingAmountAttribute(): float
    {
        return max(0, $this->total_amount - $this->payment_amount);
    }

    /**
     * Get status based on amounts
     */
    public function getCalculatedStatusAttribute(): string
    {
        if ($this->payment_amount == 0) {
            return 'pending';
        }

        if ($this->payment_amount >= $this->total_amount) {
            return 'paid';
        }

        return 'partial';
    }

    /**
     * Build a filtered query for bills used by dashboard, master list, and exports.
     *
     * @param Builder $query
     * @param array<string, mixed> $filters ['date_from', 'date_to', 'branch_id', 'status']
     * @return Builder
     */
    public function scopeFiltered(Builder $query, array $filters = []): Builder
    {
        $query->with(['branch', 'user']);

        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->whereDate('date', '>=', $filters['date_from'])
                ->whereDate('date', '<=', $filters['date_to']);
        }

        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by image existence
        if (isset($filters['has_bill_image']) && $filters['has_bill_image'] !== null && $filters['has_bill_image'] !== '') {
            if ($filters['has_bill_image'] === 'yes') {
                $query->whereNotNull('bill_image_path');
            } elseif ($filters['has_bill_image'] === 'no') {
                $query->whereNull('bill_image_path');
            }
        }

        if (isset($filters['has_payment_image']) && $filters['has_payment_image'] !== null && $filters['has_payment_image'] !== '') {
            if ($filters['has_payment_image'] === 'yes') {
                $query->whereNotNull('payment_proof_image_path');
            } elseif ($filters['has_payment_image'] === 'no') {
                $query->whereNull('payment_proof_image_path');
            }
        }

        return $query;
    }
}

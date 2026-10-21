<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;

/**
 * Phiếu lương một người trong một kỳ (bước 5.4). Không dùng LogsActivity để số tiền lương không lọt vào nhật ký hoạt động;
 * thao tác trên phiếu được ghi nhật ký thủ công, không kèm số tiền.
 */
class Payslip extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['payroll_period_id', 'user_id', 'allowance', 'bonus', 'other_deduction', 'note'];

    protected $casts = [
        'hourly_rate' => 'decimal:2', 'base_pay' => 'decimal:2', 'ot_hours' => 'decimal:2', 'ot_pay' => 'decimal:2', 'ot_exempt' => 'decimal:2',
        'allowance' => 'decimal:2', 'bonus' => 'decimal:2', 'other_deduction' => 'decimal:2', 'gross_pay' => 'decimal:2',
        'insurance_base' => 'decimal:2', 'social_insurance' => 'decimal:2', 'health_insurance' => 'decimal:2', 'unemployment_insurance' => 'decimal:2',
        'taxable_income' => 'decimal:2', 'personal_income_tax' => 'decimal:2', 'net_pay' => 'decimal:2', 'meta' => 'array',
    ];

    public function period()
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getInsuranceTotalAttribute(): string
    {
        return Decimal::sum([$this->social_insurance, $this->health_insurance, $this->unemployment_insurance]);
    }

    public function getDeductionTotalAttribute(): string
    {
        return Decimal::sum([$this->insurance_total, $this->personal_income_tax, $this->other_deduction]);
    }

    public function getWorkedHoursAttribute(): float
    {
        return round($this->worked_minutes / 60, 2);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationSession extends Model
{
    protected $guarded = [];

    protected $casts = [
        'session_date' => 'date',
        'has_job_offer' => 'boolean',
        'financial_capability' => 'integer',
        'age' => 'integer',
        'gpa' => 'decimal:2',
        'graduation_year' => 'integer',
    ];

    /**
     * 👥 ارتباط با لید
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * 👥 ارتباط با کارشناس/مشاور
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}

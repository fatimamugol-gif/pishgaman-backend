<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    protected $fillable = [
        'perfex_staff_id', 'name', 'email', 'is_active', 
        'max_capacity', 'current_active_leads', 'conversion_rate', 'specialties',
        'voip_extension', 'role', 'daily_talk_time_seconds', 'daily_successful_calls',
        'daily_unanswered_calls', 'mac_address_1', 'mac_address_2'
    ];

    protected $casts = [
        'specialties' => 'array',
        'is_active' => 'boolean',
    ];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
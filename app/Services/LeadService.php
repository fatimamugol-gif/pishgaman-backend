<?php

namespace App\Services;

class LeadService
{
    public static function generatePendingBehavioralData($leadId, $source, $channel, $message, $userId)
    {
        return [
            'lead_id' => (string)$leadId,
            'source' => $source,
            'channel' => $channel,
            'visit_frequency' => 1,
            'last_chat' => $message,
            'intent' => 'pending',
            'destination' => 'در حال تحلیل...',
            'urgency' => 'medium',
            'interest_level' => 'low',
            'conversation_summary' => "لید جدید از طریق {$source} وارد سیستم شد و در صف پردازش است.",
            'keywords' => ['در_حال_پردازش'],
            'recommended_action' => 'سیستم در پس‌زمینه در حال استخراج اطلاعات است.',
            'entry' => [
                [
                    'messaging' => [
                        [
                            'sender' => ['id' => (string)$userId],
                            'message' => ['text' => $message]
                        ]
                    ]
                ]
            ]
        ];
    }
}
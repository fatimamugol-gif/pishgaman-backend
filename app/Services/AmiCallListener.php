<?php

namespace App\Services;

use PAMI\Listener\IEventListener;
use PAMI\Message\Event\EventMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Lead;

class AmiCallListener implements IEventListener
{
    public function handle(EventMessage $event)
    {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        try {
            $payload = $event->getKeys();
            $eventName = $payload['event'] ?? 'Unknown';

            $validRingGroups = ['1021','1022','1023','1031','1032','1033','1601','1600','1602','1603'];
            $validExtensions = ['200','300','400','500','206','306','203','303','503','210','310','202','302','208','308','205','305','304','401','402','403','404','311','307','3001','900'];
            $invalidExtensions = ['1600', '1601', '1602', '1603', '1021', '1022', '1023', '1031', '1032', '1033'];
            $allCompanyNumbers = array_merge($validRingGroups, $validExtensions);

            $agent = null;
            $customer = null;
            $type = 'unknown';

            $safeString = function($key) use ($payload) {
                $k = strtolower($key);
                if (!isset($payload[$k])) return '';
                $val = $payload[$k];
                if (is_array($val)) {
                    $flat = [];
                    array_walk_recursive($val, function($a) use (&$flat) {
                        if (is_scalar($a)) $flat[] = (string)$a;
                    });
                    return trim(implode(' ', $flat));
                }
                return is_scalar($val) ? trim((string)$val) : '';
            };

            $cleanPhone = function($number) {
                $clean = preg_replace('/[^0-9]/', '', (string)$number);
                if (str_starts_with($clean, '989')) $clean = '0' . substr($clean, 2);
                elseif (str_starts_with($clean, '9') && strlen($clean) === 10) $clean = '0' . $clean;
                elseif (str_starts_with($clean, '00989')) $clean = '0' . substr($clean, 4);
                return $clean;
            };

            $companyPrefixes = ['2191', '5191', '02191', '05191', '2191018028'];
            $isCompanyTrunk = function($number) use ($companyPrefixes) {
                foreach ($companyPrefixes as $prefix) {
                    if (str_starts_with($number, $prefix)) return true;
                }
                return false;
            };

            $extractAgentFromChannel = function($channelStr) use ($allCompanyNumbers) {
                if (preg_match('/^(?:SIP|PJSIP|Local|IAX2|DAHDI)\/([0-9]{3,4})/i', $channelStr, $m)) {
                    if (isset($m[1]) && in_array($m[1], $allCompanyNumbers)) {
                        return $m[1];
                    }
                }
                return null;
            };

            // =========================================================================
            // 🚨 ۱. پاپ‌آ‌پ لایو (DialBegin)
            // =========================================================================
            if ($eventName === 'DialBegin' || $eventName === 'Dial') {
                $chAgent = $extractAgentFromChannel($safeString('Channel'));
                $dstChAgent = $extractAgentFromChannel($safeString('DestChannel') ?: $safeString('DestinationChannel'));

                if ($chAgent) {
                    $agent = $chAgent;
                    $type = 'outbound';
                } elseif ($dstChAgent) {
                    $agent = $dstChAgent;
                    $type = 'inbound';
                }

                $numbersToCheck = [
                    $safeString('CallerIDNum'),
                    $safeString('DestCallerIDNum'),
                    $safeString('ConnectedLineNum'),
                    $safeString('Exten'),
                    $safeString('DestExten')
                ];

                foreach ($numbersToCheck as $num) {
                    $cleaned = $cleanPhone($num);
                    if (strlen($cleaned) >= 10 && !$isCompanyTrunk($cleaned)) {
                        $customer = $cleaned;
                        break;
                    }
                }

                if ($agent && $customer) {
                    $cleanPhoneSearch = substr($customer, -10);
                    $lead = Lead::where('phone', 'like', "%{$cleanPhoneSearch}%")->first();

                    $callData = [
                        'lead_id' => $lead ? $lead->id : null,
                        'customer_name' => $lead ? ($lead->name ?: $lead->first_name . ' ' . $lead->last_name) : 'متقاضی ناشناس',
                        'phone' => $customer, 
                        'status' => 'ringing',
                        'call_type' => $type,
                        'agent_extension' => $agent
                    ];

                    cache()->put("live_call_ext_{$agent}", $callData, 3600);
                    $agentRecord = DB::table('agents')->where('voip_extension', $agent)->first();
                    $targetUserId = $agentRecord ? (DB::table('users')->where('email', $agentRecord->email)->value('id') ?? 1) : 1;

                    event(new \App\Events\IncomingCallEvent($targetUserId, $callData));
                }
            }

            // =========================================================================
            // 📞 ۲. انبار داده لایو (Cdr)
            // =========================================================================
            if ($eventName === 'Cdr') {
                $src = $safeString('Source') ?: $safeString('Src');
                $dst = $safeString('Destination') ?: $safeString('Dst');
                $channel = $safeString('Channel');
                $dstChannel = $safeString('DestinationChannel') ?: $safeString('DstChannel');
                
                $duration = (int)$safeString('Duration');
                $billable = (int)($safeString('BillableSeconds') ?: $safeString('Billsec'));
                $disp = strtoupper($safeString('Disposition') ?: 'NO ANSWER');
                $uniqueId = $safeString('UniqueID') ?: uniqid('cdr_');

                // ۱. کشف هویت مشتری
                if (strlen($cleanPhone($src)) >= 10 && !$isCompanyTrunk($cleanPhone($src))) {
                    $customer = $cleanPhone($src);
                } elseif (strlen($cleanPhone($dst)) >= 10 && !$isCompanyTrunk($cleanPhone($dst))) {
                    $customer = $cleanPhone($dst);
                }

                // ۲. استخراج هویت کارشناس
                $chAgent = $extractAgentFromChannel($channel);
                $dstChAgent = $extractAgentFromChannel($dstChannel);

                // 🎯 هوش تجاری تشخیص جهت تماس ورودی/خروجی
                if (in_array($src, $validExtensions) || $chAgent) {
                    $agent = $chAgent ?: $src;
                    $type = 'outbound';
                } else {
                    $agent = $dstChAgent ?: $dst;
                    $type = 'inbound';
                }

                // اگر داخلی به دست آمده جزو کدهای مخابراتی معتبر شرکت نبود، از فیلدهای معکوس استفاده کن
                if (!in_array($agent, $allCompanyNumbers)) {
                    if (in_array($src, $allCompanyNumbers)) { $agent = $src; $type = 'outbound'; }
                    elseif (in_array($dst, $allCompanyNumbers)) { $agent = $dst; $type = 'inbound'; }
                }

                // ثبت نهایی با تضمین صحت داده
                if ($agent && $customer && $type !== 'unknown') {
                    $finalDisposition = ($disp === 'ANSWERED' && $duration > 0) ? 'ANSWERED' : 'NO ANSWER';
                    $isSuccess = ($finalDisposition === 'ANSWERED');

                    $customStorageId = $uniqueId . '_' . $agent;
                    $exists = DB::table('voip_call_stats')->where('unique_id', $customStorageId)->exists();
                    
                    if (!$exists) {
                        $cleanPhoneSearch = substr($customer, -10);
                        $lead = Lead::where('phone', 'LIKE', "%{$cleanPhoneSearch}%")->first();

                        DB::table('voip_call_stats')->insert([
                            'unique_id' => $customStorageId,
                            'lead_id' => $lead ? $lead->id : null,
                            'agent_extension' => $agent,
                            'customer_phone' => $customer,
                            'duration_seconds' => $duration,
                            'billable_seconds' => $billable,
                            'disposition' => $finalDisposition,
                            'is_outcome_submitted' => 0,
                            'call_type' => $type,
                            'call_date' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        // ثبت چت لاگ RAG برای مکالمات واقعی پاسخ‌داده‌شده
                        if (!in_array($agent, $invalidExtensions) && $isSuccess) {
                            $safeLeadId = $lead ? $lead->id : 999;
                            $ragMessage = "📞 گزارش مکالمه موفق با دپارتمان:\n";
                            $ragMessage .= "جهت: " . ($type === 'inbound' ? 'ورودی (Inbound)' : 'خروجی (Outbound)') . "\n";
                            $ragMessage .= "مشتری: {$customer} | پاسخ‌دهنده: داخلی {$agent}\n";
                            $ragMessage .= "مدت مکالمه مفید: {$billable} ثانیه (کل ارتباط: {$duration} ثانیه)";

                            DB::table('chat_logs')->insert([
                                'lead_id'     => $safeLeadId,
                                'sender_type' => 'bot', 
                                'channel'     => 'voip',
                                'message'     => $ragMessage,
                                'is_analyzed' => true,
                                'created_at'  => now(),
                                'updated_at'  => now()
                            ]);
                        }

                        // بروزرسانی راندمان روزانه کارشناسان
                        if (in_array($agent, $validExtensions)) {
                            $agentUpdate = [
                                'daily_talk_time_seconds' => DB::raw("daily_talk_time_seconds + {$billable}"),
                                'daily_successful_calls' => DB::raw("daily_successful_calls + " . ($isSuccess ? 1 : 0)),
                                'daily_unanswered_calls' => DB::raw("daily_unanswered_calls + " . ($isSuccess ? 0 : 1))
                            ];
                            if ($type === 'outbound') {
                                $agentUpdate['daily_outbound_calls'] = DB::raw("daily_outbound_calls + 1");
                            } else {
                                $agentUpdate['daily_inbound_calls'] = DB::raw("daily_inbound_calls + 1");
                            }
                            DB::table('agents')->where('voip_extension', $agent)->update($agentUpdate);
                        }
                    }
                }
            }

        } catch (\Throwable $e) {
            Log::error("🚨 [AMI LISTENER EXCEPTION]: " . $e->getMessage() . " | LINE: " . $e->getLine());
        }
    }
}
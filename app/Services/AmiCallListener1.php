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
        // 🛡️ آزادسازی رم و جلوگیری از Memory Leak
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        try {
            // گرفتن تمام دیتای خام به صورت یک آرایه امن (دور زدن متدهای باگ‌دار PAMI)
            $payload = $event->getKeys();
            $eventName = $payload['event'] ?? 'Unknown';

            // 📋 پایگاه داده خطوط پیشگامان
            $validRingGroups = ['1021','1022','1023','1031','1032','1033','1601','1600','1602','1603'];
            $validExtensions = ['200','300','400','500','206','306','203','303','503','210','310','202','302','208','308','205','305','304','401','402','403','404','311','307'];
            $invalidExtensions = ['1600', '1601', '1602', '1603', '1021', '1022', '1023', '1031', '1032', '1033'];
            $allCompanyNumbers = array_merge($validRingGroups, $validExtensions);

            // 🧠 تعریف متغیرهای پایه در بالاترین سطح کورتکس متد برای فرار قطعی از خطای Undefined Variable
            $agent = null;
            $customer = null;
            $type = 'unknown';

            // 🛡️ کلوژر ۱۰۰٪ ضد گلوله: عبور مستقیم از آرایه خام به جای استفاده از متد getKey
            $safeString = function($key) use ($payload) {
                $k = strtolower($key);
                if (!isset($payload[$k])) return '';
                
                $val = $payload[$k];
                if (is_array($val)) {
                    $flat = [];
                    array_walk_recursive($val, function($a) use (&$flat) {
                        if (is_scalar($a)) $flat[] = (string)$a;
                    });
                    return trim($flat ?? '');
                }
                if (is_scalar($val)) {
                    return trim((string)$val);
                }
                return '';
            };

            // 🧠 توابع کمکی تصفیه
            $cleanPhone = function($number) {
                $clean = preg_replace('/[^0-9]/', '', (string)$number);
                if (str_starts_with($clean, '989')) $clean = '0' . substr($clean, 2);
                elseif (str_starts_with($clean, '9') && strlen($clean) === 10) $clean = '0' . $clean;
                elseif (str_starts_with($clean, '00989')) $clean = '0' . substr($clean, 4);
                return $clean;
            };

            $companyPrefixes = ['2191', '5191', '02191', '05191'];
            $isCompanyTrunk = function($number) use ($companyPrefixes) {
                foreach ($companyPrefixes as $prefix) {
                    if (str_starts_with($number, $prefix)) return true;
                }
                return false;
            };

            $extractAgentFromChannel = function($channelStr) use ($validExtensions) {
                if (preg_match('/^(?:SIP|PJSIP|Local|IAX2|DAHDI)\/([0-9]{3,4})/i', $channelStr, $m)) {
                    // 🎯 فیکس نهایی: بررسی خانه اول آرایهm به جای کل آرایه برای مچ‌گیری دقیق داخلی
                    if (isset($m) && in_array($m, $validExtensions)) return $m;
                }
                return null;
            };

            // =========================================================================
            // 🚨 ۱. لایه اتوماسیون پاپ‌آ‌پ لایو (هوش مصنوعی کانال‌یاب)
            // =========================================================================
            if ($eventName === 'DialBegin' || $eventName === 'Dial') {
                
                // شکار کارشناس از کانال فیزیکی
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

                // شکار مشتری
                foreach ($numbersToCheck as $num) {
                    $cleaned = $cleanPhone($num);
                    if (strlen($cleaned) >= 10 && !$isCompanyTrunk($cleaned)) {
                        $customer = $cleaned;
                        break;
                    }
                }

                // فالبک برای رینگ‌گروپ‌ها
                if (!$agent && $type === 'inbound') {
                    foreach ($numbersToCheck as $num) {
                        if (in_array($num, $validRingGroups) || in_array($num, $validExtensions)) {
                            $agent = $num;
                            $type = 'inbound';
                            break;
                        }
                    }
                }

                if ($agent && $customer) {
                    Log::info("🎯 [VoIP LIVE MATCH] Type: {$type} | Cust: {$customer} -> Agent: {$agent}");

                    $cleanPhoneSearch = substr($customer, -10);
                    $lead = Lead::where('phone', 'like', "%{$cleanPhoneSearch}%")->first();

                    $callData = [
                        'lead_id' => $lead ? $lead->id : null,
                        'customer_name' => $lead ? ($lead->name ?: $lead->first_name . ' ' . $lead->last_name) : 'متقاضی ناشناس / لید جدید',
                        'phone' => $customer, 
                        'status' => 'ringing',
                        'call_type' => $type,
                        'agent_extension' => $agent
                    ];

                    cache()->put("live_call_ext_{$agent}", $callData, 30);

                    $agentRecord = DB::table('agents')->where('voip_extension', $agent)->first();
                    $targetUserId = $agentRecord ? (DB::table('users')->where('email', $agentRecord->email)->value('id') ?? 1) : 1;

                    event(new \App\Events\IncomingCallEvent($targetUserId, $callData));
                }
            }

            // =========================================================================
            // 📞 ۲. لایه انبار داده و دیتاماینینگ کامل (CDR)
            // =========================================================================
            if ($eventName === 'Cdr') {
                
                $src = $safeString('Source') ?: $safeString('Src');
                $dst = $safeString('Destination') ?: $safeString('Dst');
                $channel = $safeString('Channel');
                $dstChannel = $safeString('DestinationChannel') ?: $safeString('DstChannel');
                
                // ⛏️ متغیرهای ارزشمند برای داده‌کاوی
                $duration = (int)$safeString('Duration');
                $billable = (int)($safeString('BillableSeconds') ?: $safeString('Billsec'));
                $disp = strtoupper($safeString('Disposition') ?: 'NO ANSWER');
                $uniqueId = $safeString('UniqueID') ?: uniqid('cdr_');
                $lastApp = $safeString('LastApplication');
                $startTime = $safeString('StartTime');
                $endTime = $safeString('EndTime');

                // ۱. استخراج مشتری
                $numbersToCheck = [$src, $dst];
                foreach ($numbersToCheck as $num) {
                    $cleaned = $cleanPhone($num);
                    if (strlen($cleaned) >= 10 && !$isCompanyTrunk($cleaned)) {
                        $customer = $cleaned;
                        break;
                    }
                }

                // ۲. استخراج کارشناس و جهت از روی کانال‌ها
                $chAgent = $extractAgentFromChannel($channel);
                $dstChAgent = $extractAgentFromChannel($dstChannel);

                if ($chAgent) {
                    $agent = $chAgent;
                    $type = 'outbound';
                } elseif ($dstChAgent) {
                    $agent = $dstChAgent;
                    $type = 'inbound';
                }

                // ۳. فالبک: اگر کارشناس از کانال پیدا نشد
                if (!$agent) {
                    if (in_array($dst, $allCompanyNumbers)) {
                        $agent = $dst;
                        $type = 'inbound';
                    } elseif (in_array($src, $allCompanyNumbers)) {
                        $agent = $src;
                        $type = 'outbound';
                    }
                }

                // ۴. ثبت و ذخیره‌سازی رسمی در جدول آماری
                if ($agent && $customer && $type !== 'unknown') {
                    
                    $finalDisposition = ($disp === 'ANSWERED' && $duration > 0) ? 'ANSWERED' : 'NO ANSWER';
                    $isSuccess = ($finalDisposition === 'ANSWERED');

                    // گارد جلوگیری از ثبت تکراری بوق‌های صف
                    $customStorageId = $uniqueId . '_' . $agent;
                    $exists = DB::table('voip_call_stats')->where('unique_id', $customStorageId)->exists();
                    
                    if (!$exists) {
                        Log::info("💾 [CDR DB SAVED] Type: {$type} | Phone: {$customer} | Ext: {$agent} | Dur: {$duration}s | Bill: {$billable}s");

                        $cleanPhoneSearch = substr($customer, -10);
                        $lead = Lead::where('phone', 'LIKE', "%{$cleanPhoneSearch}%")->first();

                        DB::table('voip_call_stats')->insert([
                            'unique_id' => $customStorageId,
                            'lead_id' => $lead ? $lead->id : null,
                            'agent_extension' => $agent,
                            'customer_phone' => $customer,
                            'duration_seconds' => $duration,
                            'disposition' => $finalDisposition,
                            'is_outcome_submitted' => 0,
                            'call_type' => $type,
                            'call_date' => now()->format('Y-m-d'),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        // 🛡️ گارد جلوگیری از نشت بوق‌های صف رینگ‌گروپ به چت‌لاگ RAG
                        if (!in_array($agent, $invalidExtensions)) {
                            
                            $safeLeadId = $lead ? $lead->id : 999;
                            
                            $ragMessage = "📞 گزارش جامع تماس (CDR):\n";
                            $ragMessage .= "جهت: " . ($type === 'inbound' ? 'ورودی (Inbound)' : 'خروجی (Outbound)') . "\n";
                            $ragMessage .= "مشتری: {$customer} | داخلی: {$agent}\n";
                            $ragMessage .= "وضعیت نهایی: " . ($isSuccess ? "موفق" : "بی‌پاسخ (Missed)") . "\n";
                            $ragMessage .= "مدت درگیری خط: {$duration} ثانیه | مکالمه مفید: {$billable} ثانیه\n";
                            if ($lastApp) $ragMessage .= "آخرین عملیات سیستم: {$lastApp}\n";
                            if ($startTime) $ragMessage .= "شروع: {$startTime}\n";
                            if ($endTime) $ragMessage .= "پایان: {$endTime}";

                            DB::table('chat_logs')->insert([
                                'lead_id'     => $safeLeadId,
                                'sender_type' => 'bot', 
                                'channel'     => 'voip',
                                'message'     => $ragMessage,
                                'is_analyzed' => true,
                                'created_at'  => now(),
                                'updated_at'  => now()
                            ]);
                        } else {
                            Log::info("🛡️ [CHAT_LOG FILTERED] Call for Queue/Extension {$agent} ignored from chat_logs.");
                        }

                        // آپدیت پنل کارشناسان فقط برای داخلی‌های معتبر
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

                        if ($lead && $isSuccess) {
                            $lead->update(['unanswered_calls_count' => 0]);
                        }
                    }
                }
            }

        } catch (\Throwable $e) {
            Log::error("🚨 [AMI LISTENER EXCEPTION]: " . $e->getMessage() . " | LINE: " . $e->getLine());
        }
    }
}
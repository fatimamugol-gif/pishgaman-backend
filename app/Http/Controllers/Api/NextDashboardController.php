<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;
use App\Services\SmartLeadEngine;
use App\Services\AsteriskVoipService;
use App\Services\NotificationService;

class NextDashboardController extends Controller
{
    public function getAgentsVoipStatus()
    {
        $agents = DB::table('agents')->get();

        return response()->json([
            'status' => 'success',
            'data' => $agents->map(function($agent) {
                $seconds = (int)($agent->daily_talk_time_seconds ?? 0);
                $hours = floor($seconds / 3600);
                $minutes = floor(($seconds / 60) % 60);
                
                $talkTimeText = "0 ثانیه";
                if ($hours > 0) {
                    $talkTimeText = "{$hours} ساعت و {$minutes} دقیقه";
                } elseif ($minutes > 0) {
                    $talkTimeText = "{$minutes} دقیقه";
                }

                return [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'extension' => $agent->voip_extension ?? '---',
                    'role' => $agent->role,
                    'talkTime' => $talkTimeText,
                    'status' => $seconds > 0 ? 'active' : 'offline'
                ];
            })
        ]);
    }

    public function getActiveCalls()
    {
        $options = [
            'host' => env('ASTERISK_AMI_HOST', '192.168.1.5'),
            'port' => (int)env('ASTERISK_AMI_PORT', 5038),
            'username' => env('ASTERISK_AMI_USER', 'laravel_brain'),
            'secret' => env('ASTERISK_AMI_SECRET'),
        ];

        $activeCalls = [];

        try {
            $client = new \PAMI\Client\Impl\ClientImpl($options);
            $client->open();
            
            $response = $client->send(new \PAMI\Message\Action\CoreShowChannelsAction());
            $events = $response->getEvents();

            foreach ($events as $event) {
                if ($event->getName() === 'CoreShowChannel') {
                    $channelStateText = $event->getKey('ChannelStateDesc');
                    $callerId = $event->getKey('CallerIDNum');
                    $exten = $event->getKey('Exten');

                    if (!empty($callerId) && !empty($exten) && $callerId !== '<unknown>') {
                        $cleanPhone = str_replace(['+', ' '], '', $callerId);
                        $lead = Lead::where('phone', 'like', "%{$cleanPhone}%")->first();

                        $activeCalls[] = [
                            'channel' => $event->getKey('Channel'),
                            'customer_phone' => $callerId,
                            'customer_name' => $lead ? $lead->name : 'متقاضی ناشناس',
                            'agent_extension' => $exten,
                            'state' => $channelStateText === 'Up' ? 'talking' : 'ringing',
                            'duration' => $event->getKey('Duration') ?: '00:00'
                        ];
                    }
                }
            }
            $client->close();
        } catch (\Exception $e) {
            \Log::error("PAMI Active Calls Error: " . $e->getMessage());
        }

        return response()->json(['status' => 'success', 'data' => $activeCalls]);
    }
    
    /**
     * 👥 ۱. واکشی پرسنل انحصاری دپارتمان مشاوره عالی (Department ID: 7) جهت فید منوی فرانت
     */
    public function getSeniorConsultants()
    {
        try {
            // دپارتمان شماره ۷ طبق دیتابیس شما متعلق به مشاوره عالی است
            $consultants = DB::table('agents')
                ->where('department_id', 7)
                ->where('is_active', 1)
                ->select('id', 'name', 'voip_extension')
                ->get();

            return response()->json(['status' => 'success', 'data' => $consultants]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 🔄 ۱. آپدیت سریع و درون‌خطی فیلدها (فقط برای وضعیت پرونده فعال است)
     */
    public function updateInlineField(Request $request, $id)
    {
        $request->validate([
            'field' => 'required|string|in:initial_consultation_status',
            'value' => 'required|string'
        ]);

        try {
            $value = $request->value;

            $validStatuses = [
                'مشاوره 1', 'مشاوره عالی 1', 'پیگیری', 'ساسپند', 'مشاوره 2', 'بی پاسخ', 
                'هدف', 'نظر مدیر', 'لید فوری', 'مساعد نبود', 'ارزیابی پرونده', 'رها شده', 
                'مشاوره عالی 2', 'پیگیری 2', 'پیگیری 3', 'پیگیری 4', 'پیگیری 5', 'واتساپی', 'تلگرام'
            ];

            if (!in_array($value, $validStatuses)) {
                return response()->json(['status' => 'error', 'message' => 'وضعیت ارسالی نامعتبر است.'], 400);
            }

            DB::table('leads')->where('id', $id)->update([
                'initial_consultation_status' => $value,
                'updated_at' => now()
            ]);

            return response()->json(['status' => 'success', 'message' => '✓ وضعیت پرونده با موفقیت آپدیت شد.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

  /**
     * 👑 مگا-متد مدیریت، پایش، فیلتراسیون هوشمند و سورت فراگیر متقاضیان (AND / OR) بر پایه تاریخ جلالی
     */
    public function getLeadsForDashboard(Request $request)
    {
        $user = auth()->user();
        if (!$user) return response()->json(['status' => 'error', 'message' => 'عدم دسترسی'], 401);

        $sortBy = $request->query('sort_by', 'id');
        $sortDir = $request->query('sort_dir', 'desc');
        $viewMode = $request->query('view_mode', 'leads');

        $sortMapping = [
            'name' => 'name',
            'phone' => 'phone',
            'score' => 'lead_score',
            'status' => 'initial_consultation_status',
            'source' => 'source',
            'created_at_text' => 'created_at',
            'session_date_shamsi' => 'session_date_shamsi',
            'next_call_date_shamsi' => 'next_call_date_shamsi'
        ];
        
        $actualSortKey = $sortMapping[$sortBy] ?? 'id';

        $query = Lead::with('agent')->orderBy($actualSortKey, $sortDir);
        
        if ($viewMode === 'clients') {
            $query->where('status', '=', 'official_client');
        } else {
            $query->where('status', '!=', 'official_client');
        }

        // ۱. فیلترهای اختصاصی سرستون‌ها
        if ($request->filled('filter_name')) $query->where('name', 'LIKE', "%{$request->filter_name}%");
        if ($request->filled('filter_phone')) $query->where('phone', 'LIKE', "%{$request->filter_phone}%");
        if ($request->filled('filter_score')) $query->where('lead_score', '=', $request->filter_score);
        if ($request->filled('filter_status')) $query->where('initial_consultation_status', '=', $request->filter_status);
        if ($request->filled('filter_source')) $query->where('source', '=', $request->filter_source);

        // ۲. موتور پیشرفته فیلتراسیون چندپارامتری
        if ($request->filled('advanced_filters')) {
            $advancedFilters = json_decode($request->advanced_filters, true);
            
            if (is_array($advancedFilters) && isset($advancedFilters['rules'])) {
                $conjunction = strtoupper($advancedFilters['conjunction'] ?? 'AND');
                
                $query->where(function($subQuery) use ($advancedFilters, $conjunction) {
                    foreach ($advancedFilters['rules'] as $index => $rule) {
                        $field = $rule['field'] ?? null;
                        $operator = $rule['operator'] ?? '=';
                        $value = $rule['value'] ?? null;
                        
                        if ($operator === 'contains') {
                            $operator = 'LIKE';
                            $value = "%{$value}%";
                        }

                        if ($field === 'score') $field = 'lead_score';
                        if ($field === 'status') $field = 'initial_consultation_status';
                        if ($field === 'level') $field = 'education_level';
                        if ($field === 'plan') $field = 'requested_plan';
                        if ($field === 'country') $field = 'target_country';
                        if ($field === 'session_date') $field = 'session_date_shamsi';
                        if ($field === 'next_call_date') $field = 'next_call_date_shamsi';
                        if ($field === 'persona') $field = 'persona'; // 🎯 الحاق فیلتر مگا-پرسونا برای هوش مصنوعی

                        if ($field && $value !== null) {
                            if ($conjunction === 'OR' && $index > 0) {
                                $subQuery->orWhere($field, $operator, $value);
                            } else {
                                $subQuery->where($field, $operator, $value);
                            }
                        }
                    }
                });
            }
        }

        $perPage = (int)$request->query('per_page', 15);
        $paginated = $query->paginate($perPage);
        $agentsList = DB::table('agents')->get();

        $transformedLeads = collect($paginated->items())->map(function ($lead) use ($agentsList) {
            $cleanPhone = preg_replace('/[^0-9]/', '', (string)$lead->phone);
            $searchPhone = strlen($cleanPhone) > 10 ? substr($cleanPhone, -10) : $cleanPhone;

            $lastCall = null;
            $agentNameText = 'در انتظار تخصیص';

            if (!empty($searchPhone)) {
                $lastCallRecord = DB::table('voip_call_stats')
                    ->where('lead_id', $lead->id)
                    ->orWhere('customer_phone', 'LIKE', "%{$searchPhone}%")
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($lastCallRecord) {
                    $lastCall = $lastCallRecord->created_at;
                    $ext = $lastCallRecord->agent_extension;

                    foreach ($agentsList as $ag) {
                        $extensions = array_map('trim', explode(',', $ag->voip_extension ?? ''));
                        if (in_array($ext, $extensions) || $ag->voip_extension == $ext) {
                            $agentNameText = $ag->name;
                            break;
                        }
                    }
                }
            }

            if ($agentNameText === 'در इंतजार تخصیص' && $lead->agent) {
                $agentNameText = $lead->agent->name;
            }

            return [
                'id' => $lead->id,
                'name' => $lead->name ?: 'متقاضی ناشناس',
                'phone' => $lead->phone ?? 'ثبت نشده',
                'score' => $lead->lead_score ?? 70, 
                'level' => ($lead->lead_score >= 80) ? '🔥 مستعد' : (($lead->lead_score >= 60) ? '☀️ متوسط' : '❄️ کم'), 
                'assigned_agent' => $agentNameText,
                'status' => $lead->initial_consultation_status ?? 'مشاوره 1',
                'source' => $lead->source ?: 'سایت اصلی',
                'persona' => $lead->persona ?? 'تعیین نشده', // 🎯 واکشی مستقیم و نیتو از جدول اصلی
                'form_link' => $lead->web_form_link ?? 'https://pishgamanapply.com/form-p',
                'last_contact' => $lastCall ? \Carbon\Carbon::parse($lastCall)->diffForHumans() : 'بدون تماس',
                'created_at_text' => $lead->created_at ? $lead->created_at->format('Y/m/d') : '---',
                'session_date_shamsi' => $lead->session_date_shamsi ?? '---',
                'next_call_date_shamsi' => $lead->next_call_date_shamsi ?? '---',
                'is_excellent_lead' => (int)($lead->is_excellent_lead ?? 0), 
                'total_call_duration' => DB::table('voip_call_stats')->where('customer_phone', 'LIKE', "%{$searchPhone}%")->where('disposition', 'ANSWERED')->sum('duration_seconds')
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $transformedLeads,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total()
            ]
        ]);
    }

    /**
     * 🏆 ۱. پلمب خطای ۵۰۰: متد ارزیابی و محاسبه مجدد امتیاز کیس بر پایه الگوریتم SmartLeadEngine
     */
    public function recalculateScore($id)
    {
        try {
            $lead = Lead::find($id);
            if (!$lead) {
                return response()->json(['status' => 'error', 'message' => 'پرونده متقاضی یافت نشد.'], 404);
            }

            $smartEngine = new \App\Services\SmartLeadEngine();
            // ارسال تمام دیتای متقاضی به موتور هوشمند جهت ارزیابی جدید رنک امتیاز
            $newScore = $smartEngine->calculateScore($lead->toArray());

            DB::table('leads')->where('id', $id)->update([
                'lead_score' => $newScore,
                'updated_at' => now()
            ]);

            return response()->json([
                'status' => 'success',
                'new_score' => $newScore,
                'message' => '🏆 امتیاز متقاضی با موفقیت مجدداً ارزیابی شد.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 🚀 ثبت قطعی پرونده ۳۶۰ درجه متقاضی جدید با پشتیبانی کامل از تاهل، جنسیت و تمکن به میلیون
     */
    public function storeNextLead(Request $request)
    {
        $request->validate([
          'name' => 'required|string|max:255',
          'phone' => 'required|string',
          'gender' => 'required|string|in:male,female',
          'initial_consultation_status' => 'required|string'
        ]);

        try {
            $rawData = $request->all();

            // مپ کردن امتیاز و تبدیل تمکن مالی از میلیون تومان به عدد خام دیتابیس
            $rawData['lead_score'] = $rawData['score'] ?? 70;
            if (isset($rawData['financial_capability_million']) && $rawData['financial_capability_million'] !== '') {
                $rawData['financial_capability_toman'] = floatval($rawData['financial_capability_million']) * 1000000;
            }

            // واکشی داینامیک کل ستون‌های فیزیکی دیتابیس leads
            $schemaColumns = \Schema::getColumnListing('leads');
            $safeInsertData = collect($rawData)->filter(fn($v, $k) => in_array($k, $schemaColumns))->toArray();

            $safeInsertData['import_source'] = 'next_front';
            $safeInsertData['perfex_lead_id'] = rand(100000, 999999); // فیک اتمیک برای لیدهای فرانت
            $safeInsertData['created_at'] = now();
            $safeInsertData['updated_at'] = now();

            $insertedId = DB::table('leads')->insertGetId($safeInsertData);

            return response()->json([
                'status' => 'success',
                'lead_id' => $insertedId,
                'message' => '✅ پرونده متقاضی با موفقیت ثبت شد و به کارتابل ارجاع یافت.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

   /**
     * 💬 ثبت چت دستی مجهز به الصاق داینامیک نام مشاور به متن ارتباطی (بدون وابستگی به ستون ناموجود description)
     */
    public function storeManualChat(Request $request)
    {
        $request->validate([
            'lead_id' => 'required|integer',
            'message' => 'required|string',
            'sender_type' => 'required|string|in:agent,user'
        ]);

        try {
            $user = auth()->user();
            $rawMessage = $request->message;

            // 🎯 اصلاح ساختار: نام مشاور لاگین شده را به ابتدا یا انتهای پیام الحاق می‌کنیم 
            // تا بدون نیاز به ستون فیزیکی، در چت‌لاگ با نام تفکیک‌شده رندر شود
            if ($request->sender_type === 'agent' && $user) {
                $formattedMessage = "💬 [ثبت شده توسط مشاور: {$user->name}]: \n" . $rawMessage;
            } else {
                $formattedMessage = $rawMessage;
            }

            // درج اتمیک در جدول چت لاگ‌ها کاملاً منطبق بر ساختار واقعی فیلدهای دیتابیس شما
            DB::table('chat_logs')->insert([
                'lead_id' => $request->lead_id,
                'channel' => 'crm_manual_entry',
                'sender_type' => $request->sender_type,
                'message' => $formattedMessage,
                'is_analyzed' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json(['status' => 'success', 'message' => '✓ پیام ارتباطی با موفقیت ثبت شد.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
   **/public function triggerSessionDeadline(Request $request)
{
    $request->validate([
        'lead_id' => 'required',
        'agent_id' => 'required',
        'client_name' => 'required|string',
        'initial_agent_name' => 'required|string',
        'senior_consultant_name' => 'required|string',
        'current_session_shamsi' => 'required|string',
        'session_start_at' => 'required',
        'session_end_at' => 'required',
    ]);

    $exists = DB::table('next_session_reports')
        ->where('lead_id', $request->lead_id)
        ->where('status', 'completed')
        ->where('created_at', '>=', now()->subDays(30)) // یا بررسی مستقیم فیلدهای متناظر
        ->exists();

        if ($exists) {
        return response()->json([
            'status' => 'error',
            'message' => '❌ خطای سیستم نظارتی: گزارش این جلسه قبلاً پلمب شده است. جهت ثبت فرم جدید، ابتدا باید تاریخ مشاوره تخصصی جدیدی برای متقاضی در کارتابل زمان‌بندی کنید.'
        ], 422);
    }

    // 🎯 پچ فوق‌العاده طلایی: لایروبی رشته ISO فرانت‌آند و تبدیل به فرمت کامپایل شده MySQL
    // این متد کاراکترهای T و Z را کلاً خنثی و تراز می‌کند
    $startAt = \Carbon\Carbon::parse($request->session_start_at)->toDateTimeString();
    $endAt = \Carbon\Carbon::parse($request->session_end_at)->toDateTimeString();
    
    // ددلاین دقیق ۲ ساعته ناظر هوشمند
    $deadline = \Carbon\Carbon::parse($endAt)->copy()->addHours(2)->toDateTimeString(); 

    $reportId = DB::table('next_session_reports')->insertGetId([
        'lead_id' => $request->lead_id,
        'agent_id' => $request->agent_id,
        'client_name' => $request->client_name,
        'initial_agent_name' => $request->initial_agent_name,
        'senior_consultant_name' => $request->senior_consultant_name,
        'session_start_at' => $startAt,
        'session_end_at' => $endAt,
        'deadline_at' => $deadline,
        'target_plan' => '',
        'session_outcome' => '',
        'senior_consultant_opinion' => '',
        'recommended_plans' => '',
        'delay_reason' => null, // 📝 فیلد جدید در ساختار دیتابیس (nullable text)
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return response()->json([
        'status' => 'success',
        'message' => '🚨 ددلاین ۲ ساعته دقیقاً از زمان اتمام جلسه فعال شد.',
        'report_id' => $reportId,
        'deadline_at' => $deadline // برگشت به فرانت برای همگام‌سازی تایمر
    ]);
}

//     public function submitSessionReport(Request $request, $id)
// {
//     $request->validate([
//         'target_plan'               => 'required|string|max:255',
//         'special_conditions'        => 'required|string',
//         'strengths'                 => 'required|string',
//         'weaknesses'                => 'required|string',
//         'client_questions'          => 'required|string',
//         'previous_actions'          => 'required|string',
//         'session_outcome'           => 'required|string',
//         'next_session_documents'    => 'required|string',
//         'senior_consultant_opinion' => 'required|string',
//         'recommended_plans'         => 'required|string', // رشته‌ای از پلن‌های سلکت شده
//         'delay_reason'              => 'nullable|string', // 🎯 علت تاخیر ارسالی از فرانت
//         'is_spouse_better'          => 'nullable|boolean',
//         'spouse_name'               => 'required_if:is_spouse_better,true|nullable|string|max:255',
//         'spouse_phone'              => 'required_if:is_spouse_better,true|nullable|string',
//     ]);

//     $report = DB::table('next_session_reports')->where('id', $id)->first();

//     if (!$report) {
//         return response()->json(['status' => 'error', 'message' => 'گزارش جلسه یافت نشد.'], 404);
//     }

//     // ۱. بررسی و ثبت خودکار لید همسر در دژ دیتابیس در صورت فعال بودن فلگ
//     $newSpouseLeadId = null;
//     if ($request->is_spouse_better) {
//         // ساخت لید جدید اتمیک برای همسر با تخصیص به همین مشاور عالی فعلی
//         $newSpouseLeadId = DB::table('leads')->insertGetId([
//             'name' => $request->spouse_name,
//             'phone' => $request->spouse_phone,
//             'status' => 'ارزیابی پرونده', // وضعیت شروع به کار همسر
//             'source' => 'ورود دستی فرانت', 
//             'agent_id' => $report->agent_id, // واگذاری خودکار به همین مشاور ارشد
//             'persona' => 'Goal Oriented', // پیش‌فرض تا بعدا تعیین شود
//             'score' => 50, // امتیاز اولیه پایه
//             'created_at' => now(),
//             'updated_at' => now(),
//         ]);

//         // ذخیره سازی کانتکست ارجاع متقابل در سیستم لاگ پیام‌ها یا پرونده لید قبلی
//         Log::info("🔄 [Spouse Pivot Link]: Lead ID {$report->lead_id} connected to New Spouse Lead ID {$newSpouseLeadId}");
//     }

//     $isExpired = now()->greaterThan($report->deadline_at);

//     // 🛡️ اگر وقت تمام شده و علت تاخیر را پر نکرده، سیستم اجازه ثبت نمی‌دهد
//     if ($isExpired && empty($request->delay_reason)) {
//         return response()->json([
//             'status' => 'require_reason',
//             'message' => '❌ ددلاین ۲ ساعته منقضی شده است. جهت ثبت فرم، مکتوب کردن علت تاخیر برای ناظر الزامی است.'
//         ], 422);
//     }

//     DB::table('next_session_reports')->where('id', $id)->update([
//         'target_plan'               => $request->target_plan,
//         'special_conditions'        => $request->special_conditions,
//         'strengths'                 => $request->strengths,
//         'weaknesses'                => $request->weaknesses,
//         'client_questions'          => $request->client_questions,
//         'previous_actions'          => $request->previous_actions,
//         'session_outcome'           => $request->session_outcome,
//         'next_session_documents'    => $request->next_session_documents,
//         'senior_consultant_opinion' => $request->senior_consultant_opinion,
//         'recommended_plans'         => $request->recommended_plans,
//         'delay_reason'              => $request->delay_reason,
//         'status'                    => $isExpired ? 'expired' : 'completed',
//         'submitted_at'              => now(), // ⏳ ثبت فیزیکی ساعت دقیق پر کردن فرم برای مانیتورینگ ناظر
//         'updated_at'                => now(),
//     ]);

//     return response()->json([
//         'status' => 'success',
//         'message' => $request->is_spouse_better 
//             ? '💎 گزارش ثبت شد و لید جدید همسر با موفقیت در کارتابل فروش ایجاد گردید.' 
//             : '💎 فرم ارزیابی جلسه با موفقیت ثبت شد.'
//     ]);
// }
// public function submitSessionReport(Request $request, $id)
// {
//     $report = DB::table('next_session_reports')->where('id', $id)->first();
//     if (!$report) {
//         return response()->json(['status' => 'error', 'message' => 'گزارش جلسه یافت نشد'], 404);
//     }

//     // بررسی انقضای تایمر ۲ ساعته
//     $isExpired = now()->greaterThan(\Carbon\Carbon::parse($report->deadline_at));
//     if ($isExpired && empty($request->input('delay_reason'))) {
//         return response()->json([
//             'status' => 'require_reason',
//             'message' => 'مهلت قانونی ثبت فرم گذشته است. علت تاخیر را مکتوب کنید.'
//         ], 400);
//     }

//     // پلمب دیتای جدید بر پایه آیدی مشاوران به جای استرینگ
//     DB::table('next_session_reports')->where('id', $id)->update([
//         'target_plan' => $request->input('target_plan'),
//         'recommended_plans' => $request->input('recommended_plans'),
//         'special_conditions' => $request->input('special_conditions'),
//         'strengths' => $request->input('strengths'),
//         'weaknesses' => $request->input('weaknesses'),
//         'client_questions' => $request->input('client_questions'),
//         'previous_actions' => $request->input('previous_actions'),
//         'session_outcome' => $request->input('session_outcome'),
//         'next_session_documents' => $request->input('next_session_documents'),
//         'senior_consultant_opinion' => $request->input('senior_consultant_opinion'),
//         'delay_reason' => $request->input('delay_reason'),
        
//         // 🎯 ذخیره آیدی هوشمند دریافتی از فرانت‌اِند
//         'initial_agent_id' => $request->input('initial_agent_id'),
//         'senior_agent_id' => $request->input('senior_agent_id'),

//         // همسر متقاضی
//         'is_spouse_better' => $request->input('is_spouse_better') ? 1 : 0,
//         'spouse_name' => $request->input('spouse_name'),
//         'spouse_phone' => $request->input('spouse_phone'),
        
//         'status' => 'done',
//         'submitted_at' => now(),
//         'updated_at' => now(),
//     ]);

//     // سناریو ساخت لید موازی خودکار برای همسر در صورت مناسب‌تر بودن رزومه
//     if ($request->input('is_spouse_better') && $request->input('spouse_phone')) {
//         // نمونه کد شما برای ساخت لید موازی در اینجا اجرا می‌شود...
//     }

//     return response()->json([
//         'status' => 'success',
//         'message' => 'گزارش ارزیابی با موفقیت در پرونده متقاضی پلمب و قفل نهایی شد.'
//     ]);
// }

public function submitSessionReport(Request $request, $id)
{
    $report = DB::table('next_session_reports')->where('id', $id)->first();
    if (!$report) {
        return response()->json(['status' => 'error', 'message' => 'گزارش جلسه یافت نشد'], 404);
    }

    // دیباگ سریع: بررسی پِی‌لود ارسالی از فرانت
    // اگر فیلدها تهی باشند، دیتابیس آپدیت نمی‌شود یا ستون‌ها خالی می‌مانند
    $updateData = [
        'target_plan' => $request->input('target_plan'),
        'recommended_plans' => $request->input('recommended_plans'),
        'special_conditions' => $request->input('special_conditions'),
        'strengths' => $request->input('strengths'),
        'weaknesses' => $request->input('weaknesses'),
        'client_questions' => $request->input('client_questions'),
        'previous_actions' => $request->input('previous_actions'),
        'session_outcome' => $request->input('session_outcome'),
        'next_session_documents' => $request->input('next_session_documents'),
        'senior_consultant_opinion' => $request->input('senior_consultant_opinion'),
        'delay_reason' => $request->input('delay_reason'),
        'initial_agent_id' => $request->input('initial_agent_id'),
        'senior_agent_id' => $request->input('senior_agent_id'),
        'status' => 'done',
        'submitted_at' => now(),
        'updated_at' => now(),
    ];

    // اجرای آپدیت و گرفتن تعداد ردیف‌های تغییر یافته
    $affected = DB::table('next_session_reports')->where('id', $id)->update($updateData);

    return response()->json([
        'status' => 'success',
        'message' => 'تست دیباگ ثبت فرم',
        'debug_info' => [
            'requested_id' => $id,
            'rows_affected' => $affected, // اگر این عدد 0 باشد یعنی آپدیت روی دیتابیس اعمال نشده است
            'received_payload' => $request->all() // بررسی کن ببین فرانت‌آند اصلاً فیلدها را فرستاده یا خیر
        ]
    ]);
}

public function getAllSessionReports(Request $request)
{
    // 👑 پیوند چندگانه با جدول agents جهت استخراج بدون خطای نام‌ها از روی آیدی
    $reports = DB::table('next_session_reports')
        ->join('leads', 'next_session_reports.lead_id', '=', 'leads.id')
        // پیوند اول: نام مشاور اولیه
        ->leftJoin('agents as initial_agents', 'next_session_reports.initial_agent_id', '=', 'initial_agents.id')
        // پیوند دوم: نام مشاور عالی
        ->leftJoin('agents as senior_agents', 'next_session_reports.senior_agent_id', '=', 'senior_agents.id')
        ->select([
            'next_session_reports.*',
            'leads.phone as client_phone',
            'leads.persona as client_persona',
            'initial_agents.name as real_initial_agent_name', 
            'senior_agents.name as real_senior_consultant_name' 
        ])
        ->orderBy('next_session_reports.id', 'desc');

    // فیلتر دسترسی ناظر/کارشناس
    $localUser = $request->user();
    if ($localUser && $localUser->role !== 'supervisor' && $localUser->role !== 'admin') {
        $reports->where('next_session_reports.agent_id', $localUser->id);
    }

    $reportData = $reports->get();

    // پردازش فید خروجی فرانت‌آند
    $processed = collect($reportData)->map(function ($report) {
        $isExpired = now()->greaterThan(\Carbon\Carbon::parse($report->deadline_at));
        $currentStatus = ($report->status === 'pending' && $isExpired) ? 'expired' : $report->status;

        return [
            'id' => $report->id,
            'client_name' => $report->client_name,
            'client_phone' => $report->client_phone,
            // فیکس نهایی: استفاده مستقیم از نام حقیقی استخراج شده بر اساس ID دیتابیس
            'initial_agent' => $report->real_initial_agent_name ?: 'مشاور اولیه سیستم',
            'senior_consultant' => $report->real_senior_consultant_name ?: 'مشاور عالی سیستم',
            'session_start' => $report->session_start_at,
            'session_end' => $report->session_end_at,
            'deadline' => $report->deadline_at,
            'submitted_at' => $report->submitted_at,
            'status' => $currentStatus,
            'target_plan' => $report->target_plan,
            'has_delay_reason' => !empty($report->delay_reason),
            'delay_reason' => $report->delay_reason
        ];
    });

    return response()->json([
        'status' => 'success',
        'data' => $processed
    ]);
}
   /**
     * 🎯 سوییچ و به‌روزرسانی هوشمند ۱۱ پرسونای روان‌شناختی درون هسته فیزیکی لید
     */
    public function updateLeadPersona(Request $request, $id)
    {
        $request->validate([
            'persona' => 'required|string|in:Goal Oriented,Analytical,Safety Oriented,Explorer,Skeptic,Budget-Conscious,Family-First,Fast-Track,Undecided/Passive,Opportunity-Driven,Case Study Seeker'
        ]);

        try {
            DB::table('leads')->where('id', $id)->update([
                'persona' => $request->persona,
                'updated_at' => now()
            ]);

            return response()->json([
                'status' => 'success', 
                'message' => '✓ پرسونا روان‌شناختی با موفقیت در هسته لید پلمب شد.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
    /**
     * 💬 ۳. ثبت سریع خلاصه مکالمه تلفنی کارشناس و انتقال مستقیم به لاگ پیام‌های چت
     */
    public function storeCallSummary(Request $request, $id)
    {
        $request->validate(['summary' => 'required|string']);
        try {
            DB::table('chat_logs')->insert([
                'lead_id' => $id,
                'channel' => 'crm_system',
                'sender_type' => 'agent',
                'message' => "📞 [خلاصه مکالمه تلفنی کارشناس]: " . $request->summary,
                'is_analyzed' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            return response()->json(['status' => 'success', 'message' => '✓ خلاصه مکالمه در تاریخچه ارتباطات پلمب شد.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
    

        public function getLeadCallLogs($leadId) {
        $lead = Lead::find($leadId);
        if (!$lead) return response()->json(['status' => 'error', 'message' => 'پرونده یافت نشد'], 404);
        $searchPhone = substr(preg_replace('/[^0-9]/', '', $lead->phone), -10);
        $logs = DB::table('voip_call_stats')->where('lead_id', $leadId)->orWhere('customer_phone', 'LIKE', "%{$searchPhone}%")->orderBy('created_at', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $logs]);
    }

    public function getLeadDetailsForFront($id)
    {
        $lead = Lead::find($id);
        if (!$lead) return response()->json(['status' => 'error', 'message' => 'لید یافت نشد'], 404);

        $chatLogs = DB::table('chat_logs')->where('lead_id', $id)->orderBy('created_at', 'asc')->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'sender' => $log->sender_type ?? 'user', 
                    'message' => $log->message,
                    'description' => $log->description ?? '', // الحاق نام مشاور ثبت‌کننده
                    'time' => \Carbon\Carbon::parse($log->created_at)->format('H:i'),
                    'date' => \Carbon\Carbon::parse($log->created_at)->format('Y/m/d')
                ];
            });

        $insights = DB::table('customer_insights')->where('customer_id', $lead->perfex_lead_id)->first() 
            ?? DB::table('customer_insights')->where('customer_id', $lead->id)->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $lead->id,
                'name' => $lead->name ?: 'کاربر جدید تلگرام / ناشناس',
                'gender' => $lead->gender ?? 'male', // 🎯 الحاق فیلد جنسیت متقاضی اصلی
                'persona' => $lead->persona ?? 'تعیین نشده', // 🎯 پلمب باگ: ارسال مستقیم فیلد فیزیکی پرسونا به دراور فرانت
                'phone' => $lead->phone ?: 'در انتظار ثبت شماره...',
                'secondary_phone' => $lead->secondary_phone,
                'source' => $lead->source ?: 'Telegram Bot',
                'current_city' => $lead->current_city ?: '---',
                'age' => $lead->age ?: '---',
                'military_status' => $lead->military_status ?: '---',
                'marital_status' => $lead->marital_status ?: 'single',
                'children_count' => $lead->children_count ?: 0,
                'spouse_name' => $lead->spouse_name,
                'spouse_age' => $lead->spouse_age,
                'spouse_education' => $lead->spouse_education,
                'spouse_language_level' => $lead->spouse_language_level,
                'spouse_accompanying' => $lead->spouse_accompanying ?? 'yes',
                'work_and_insurance_history' => $lead->work_and_insurance_history,
                'target_country' => $lead->target_country ?: 'آلمان',
                'requested_plan' => $lead->requested_plan ?: 'مهاجرت تحصیلی',
                'financial_capability_toman' => $lead->financial_capability_toman ?: 0,
                'discovery_channel' => $lead->discovery_channel ?: '---',
                'english_level' => $lead->english_level,
                'german_level' => $lead->german_level,
                'educational_background' => [
                    'degree' => $lead->education_level ?: 'ثبت نشده',
                    'field' => $lead->field_of_study ?: 'ثبت نشده',
                    'gpa' => $lead->gpa ?: '---',
                ],
                'ai_insights' => [
                    'destination' => $insights->likely_destination ?? 'در حال تحلیل...',
                    'intent' => $insights->last_intent ?? 'pending',
                    'summary' => $insights->recommended_action ?? 'سیستم در حال ارزیابی پیام‌های لید است.'
                ],
                'chat_history' => $chatLogs
            ]
        ]);
    }


   /**
     * 🗓️ ۲. ست کردن جلسه + فعال‌سازی اتوماتیک "مشاوره عالی" پس از برنامه‌ریزی جلسه
     */
    public function storeLeadEvent(Request $request, $id)
    {
        $request->validate([
            'session_date_shamsi' => 'required|string',
            'next_call_date_shamsi' => 'nullable|string',
            'assigned_agent_id' => 'required|integer',
            'session_type' => 'required|in:online,in_person,phone',
            'form_type' => 'nullable|string'
        ]);

        try {
            // ۱. ذخیره تواریخ، مشاور عالی و تغییر اتوماتیک لید به "مشاوره عالی" (is_excellent_lead = 1)
            DB::table('leads')->where('id', $id)->update([
                'session_date_shamsi' => $request->session_date_shamsi,
                'next_call_date_shamsi' => $request->next_call_date_shamsi,
                'senior_consultant_id' => $request->assigned_agent_id,
                'is_excellent_lead' => 1, // 🎯 فعال‌سازی اتوماتیک طبق دستور شما
                'updated_at' => now()
            ]);

            // ۲. ثبت تسک پیگیری در کارتابل مشاور عالی
            DB::table('next_tasks')->insert([
                'lead_id' => $id,
                'task_title' => "📝 تکمیل صورتجلسه و آپلود فایل اسکن جلسه متقاضی ({$request->session_date_shamsi})",
                'description' => "نوع فرم تخصصی: {$request->form_type} \n مود برگزاری: {$request->session_type} \n مشاور گرامی، لطفا پس از اتمام جلسه، مفاد را مکتوب و فایل اسکن شده را الصاق فرمایید.",
                'due_date_shamsi' => $request->session_date_shamsi,
                'status' => 'pending',
                'priority' => 'high',
                'target_audience' => 'staff',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json(['status' => 'success', 'message' => '✓ جلسه ثبت و فیلد مشاوره عالی خودکار فعال گردید.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    
    /**
     * 📤 ۴. اندپوینت اختصاصی آپلود فایل اسکن صورتجلسه و ثبت نهایی مفاد توسط مشاور عالی
     */
    // public function submitSessionReport(Request $request, $taskId)
    // {
    //     $request->validate([
    //         'minutes_report' => 'required|string',
    //         'scanned_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:20480' // حد مجاز ۲۰ مگابایت
    //     ]);

    //     try {
    //         $task = DB::table('next_tasks')->where('id', $taskId)->first();
    //         if (!$task) return response()->json(['status' => 'error', 'message' => 'تسک یافت نشد.'], 404);

    //         // ذخیره امن فایل در استوریج سرور
    //         $path = $request->file('scanned_file')->store("session_scans/{$task->lead_id}", 'public');

    //         // آپدیت و بستن تسک مشاور عالی به وضعیت Done
    //         DB::table('next_tasks')->where('id', $taskId)->update([
    //             'description' => $task->description . "\n\n Real Minutes: " . $request->minutes_report,
    //             'client_file_path' => $path,
    //             'status' => 'done',
    //             'updated_at' => now()
    //         ]);

    //         return response()->json(['status' => 'success', 'message' => '✓ صورتجلسه مکتوب و اسکن فایل با موفقیت پلمب شد.']);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    //     }
    // }

    /**
     * ☎️ پلمب هوشمند متد چک پاپ‌آ‌پ لایو بر پایه احراز هویت کارشناس صادرکننده توکن
     */
    public function checkLivePopup()
    {
        $user = auth()->user();
        if (!$user) return response()->json(['status' => 'error'], 401);

        // واکشی داخلی کارشناس از روی جدول مشاورین سانترال
        $agentRecord = DB::table('agents')->where('email', $user->email)->first();
        if (!$agentRecord || empty($agentRecord->voip_extension)) {
            // فالبک از روی فیلد مستقیم یوزر در صورت وجود
            $extension = $user->voip_extension ?? null;
        } else {
            $extension = $agentRecord->voip_extension;
        }

        if (!$extension) {
            return response()->json(['status' => 'success', 'active_call' => null]);
        }

        // واکشی لایو کش ذخیره شده توسط لیسنر مخابرات
        $liveCall = cache()->get("live_call_ext_{$extension}");

        if ($liveCall && isset($liveCall['status']) && $liveCall['status'] === 'ringing') {
            return response()->json([
                'status' => 'success',
                'active_call' => $liveCall
            ]);
        }

        return response()->json(['status' => 'success', 'active_call' => null]);
    }

    /**
     * 📊 هاب متمرکز دشبورد نظارتی + تجمیع داینامیک داخلی‌های چندگانه به نام کارشناس
     */
    public function getAgentDashboardHub(Request $request)
    {
        $user = auth()->user();
        if (!$user) return response()->json(['status' => 'error', 'message' => 'عدم دسترسی'], 401);

        $today = now()->format('Y-m-d');

        $agentRecord = DB::table('agents')->where('email', $user->email)->first();
        $agentId = $agentRecord ? $agentRecord->id : 0;
        $isSupervisor = ($user->role === 'supervisor');

        $startDate = $request->query('start_date', $today);
        $endDate = $request->query('end_date', $today);

        // ۱. کوئری پایه تماس‌ها
        $callStatsQuery = DB::table('voip_call_stats')
            ->whereNotIn('agent_extension', ['1600', '1601', '1602', '1603', '1021', '1022', '1023', '1031', '1032', '1033'])
            ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate]);

        $allCalls = $callStatsQuery->get();
        
        // واکشی لیست مشاورین جهت مپینگ معکوس خطوط
        $agentsList = DB::table('agents')->get();

        // ۲. 🎯 موتور تجمیع داینامیک داخلی‌ها به نام کارشناس
        $aggregatedPerformance = [];

        foreach ($allCalls as $call) {
            $ext = $call->agent_extension;
            $matchedAgentName = "داخلی ناشناخته ({$ext})";

            // پیدا کردن کارشناس صاحب این داخلی (پشتیبانی از کاما)
            foreach ($agentsList as $ag) {
                $extensions = array_map('trim', explode(',', $ag->voip_extension ?? ''));
                if (in_array($ext, $extensions) || $ag->voip_extension == $ext) {
                    $matchedAgentName = $ag->name;
                    break;
                }
            }

            if (!isset($aggregatedPerformance[$matchedAgentName])) {
                $aggregatedPerformance[$matchedAgentName] = [
                    'agent_name' => $matchedAgentName,
                    'total_calls' => 0,
                    'answered_calls' => 0,
                    'inbound_seconds' => 0,
                    'outbound_seconds' => 0,
                ];
            }

            $aggregatedPerformance[$matchedAgentName]['total_calls']++;
            if ($call->disposition === 'ANSWERED') {
                $aggregatedPerformance[$matchedAgentName]['answered_calls']++;
                if ($call->call_type === 'inbound') {
                    $aggregatedPerformance[$matchedAgentName]['inbound_seconds'] += $call->duration_seconds;
                } else {
                    $aggregatedPerformance[$matchedAgentName]['outbound_seconds'] += $call->duration_seconds;
                }
            }
        }

        // فرمت‌دهی نهایی آرایه تجمیع‌شده برای خروجی تمیز فرانت‌آند
        $extensionsPerformance = collect($aggregatedPerformance)->map(function ($item) {
            $totalSuccessSeconds = $item['inbound_seconds'] + $item['outbound_seconds'];
            $avgSeconds = $item['answered_calls'] > 0 ? round($totalSuccessSeconds / $item['answered_calls']) : 0;
            
            $avgText = $avgSeconds >= 60 ? floor($avgSeconds / 60) . 'm ' . ($avgSeconds % 60) . 's' : $avgSeconds . 's';

            return [
                'extension' => $item['agent_name'], // 🎯 جایگزینی شماره داخلی با نام تجمیع‌شده کارشناس
                'total_calls' => $item['total_calls'],
                'answered_calls' => $item['answered_calls'],
                'inbound_minutes' => round($item['inbound_seconds'] / 60, 1),
                'outbound_minutes' => round($item['outbound_seconds'] / 60, 1),
                'total_minutes' => round($totalSuccessSeconds / 60, 1),
                'avg_talk_time' => $avgText
            ];
        })->values();

        // ۳. آمار عملکرد کارشناسان حقیقی برای کارت‌های زیرین
        $agentsPerformance = $agentsList->map(function ($agent) use ($allCalls) {
            $extensions = array_map('trim', explode(',', $agent->voip_extension ?? ''));
            
            // فیلتر کردن تمام تماس‌های مربوط به تمام داخلی‌های این کارشناس
            $agentCalls = $allCalls->filter(fn($c) => in_array($c->agent_extension, $extensions));

            $successCalls = $agentCalls->where('disposition', 'ANSWERED');
            $seconds = $successCalls->sum('duration_seconds');
            
            $avgSeconds = $successCalls->count() > 0 ? round($seconds / $successCalls->count()) : 0;
            $avgText = $avgSeconds >= 60 ? floor($avgSeconds / 60) . 'm ' . ($avgSeconds % 60) . 's' : $avgSeconds . 's';

            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'extension' => $agent->voip_extension ?? '---',
                'total_calls' => $agentCalls->count(),
                'talk_minutes' => round($seconds / 60, 1),
                'avg_talk_time' => $avgText
            ];
        })->where('total_calls', '>', 0)->values();

        // مابقی محاسبات شاخص‌های دشبورد بدون تغییر
        $totalLeads = DB::table('leads')->where('status', '!=', 'official_client')->count();
        $pendingTasks = DB::table('next_tasks')->where('status', 'pending')->count();
        
        $activeTasks = DB::table('next_tasks')->join('leads', 'next_tasks.lead_id', '=', 'leads.id')
            ->where('next_tasks.status', 'pending')
            ->select('next_tasks.*', 'leads.name as lead_name', 'leads.phone as lead_phone', 'leads.id as lead_real_id')
            ->orderBy('next_tasks.id', 'desc')->limit(5)->get();

        $recentReminders = DB::table('next_reminders')->where('status', 'pending')->orderBy('id', 'desc')->limit(5)->get();

        $inboundDuration = $allCalls->where('call_type', 'inbound')->where('disposition', 'ANSWERED')->sum('duration_seconds');
        $outboundDuration = $allCalls->where('call_type', 'outbound')->where('disposition', 'ANSWERED')->sum('duration_seconds');

        $todayTicketsCount = DB::table('client_tickets')->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])->count();
        $pendingInvoicesCount = DB::table('client_invoices')->where('status', 'pending_review')->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])->count();
        $todayPaidCount = DB::table('invoice_payments')->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])->count();
        $pendingTasksCount = DB::table('next_tasks')->where('status', 'pending')->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])->count();

        return response()->json([
            'status' => 'success',
            'is_supervisor' => $isSupervisor,
            'selected_start_date' => $startDate,
            'selected_end_date' => $endDate,
            'metrics' => [
                'total_leads' => $totalLeads, 
                'pending_tasks_count' => $pendingTasks,
                'today_tickets' => $todayTicketsCount,
                'pending_invoices' => $pendingInvoicesCount,
                'today_paid_invoices' => $todayPaidCount,
                'pending_tasks' => $pendingTasksCount,
                'today_reminders_count' => count($recentReminders),
                'total_calls' => $allCalls->count(),
                'answered_calls' => $allCalls->where('disposition', 'ANSWERED')->count(),
                'no_answer_calls' => $allCalls->where('disposition', 'NO ANSWER')->count(),
                'outbound_count' => $allCalls->where('call_type', 'outbound')->count(),
                'inbound_count' => $allCalls->where('call_type', 'inbound')->count(),
                'inbound_duration_minutes' => round($inboundDuration / 60, 1),
                'outbound_duration_minutes' => round($outboundDuration / 60, 1),
            ],
            'recent_tasks' => $activeTasks,
            'recent_reminders' => $recentReminders,
            'extensions_performance' => $extensionsPerformance,
            'agents_performance' => $agentsPerformance
        ]);
    }

    public function clickToDial(Request $request)
    {
        $request->validate(['customer_phone' => 'required|string']);
        $user = auth()->user();
        $voipExtension = null;

        if (!$user) {
            $fallbackAgent = DB::table('agents')->whereNotNull('voip_extension')->where('voip_extension', '!=', '')->first();
            $voipExtension = $fallbackAgent ? $fallbackAgent->voip_extension : '101';
        } else {
            if (!empty($user->voip_extension)) {
                $voipExtension = $user->voip_extension;
            } else {
                $agent = DB::table('agents')->where('email', $user->email)->first();
                $voipExtension = ($agent && !empty($agent->voip_extension)) ? $agent->voip_extension : '101';
            }
        }

        $voipService = new AsteriskVoipService();
        $isOriginated = $voipService->originateCall($voipExtension, $request->customer_phone);

        if ($isOriginated) {
            // در انتهای متد clickToDial جایی که متد insert اجرا می‌شود:
$taskId = DB::table('next_tasks')->insertGetId([
    'lead_id' => $request->lead_id ?? 1,
    'task_title' => '📞 تماس خروجی سیستماتیک با متقاضی',
    'status' => 'pending',
    'due_date_shamsi' => class_exists('\Morilog\Jalali\Jalalian') ? \Morilog\Jalali\Jalalian::now()->format('Y/m/d') : now()->format('Y/m/d'),
    'due_date_at' => now(), // 🎯 پر کردن همزمان تایم‌استمپ میلادی برای تسک تماس فوری
    'created_at' => now(), 
    'updated_at' => now()
]);

            return response()->json(['status' => 'success', 'message' => "سیگنال به داخلی {$voipExtension} ارسال شد.", 'task_id' => $taskId]);
        }

        return response()->json(['status' => 'error', 'message' => 'خطا در ارتباط با AMI آستریسک.'], 500);
    }

    public function getAdvancedVoipReport(Request $request)
    {
        $startDate = $request->query('start_date', now()->format('Y-m-d'));
        $endDate = $request->query('end_date', now()->format('Y-m-d'));
        $filterAgentExtension = $request->query('extension');

        $query = DB::table('voip_call_stats');
        if (!empty($filterAgentExtension)) {
            $query->where('agent_extension', $filterAgentExtension);
        } else {
            $query->whereBetween('call_date', [$startDate, $endDate]);
        }

        $allCalls = $query->get();
        $totalCallsCount = $allCalls->count();
        $answeredCallsCount = $allCalls->where('disposition', 'ANSWERED')->count();
        $totalDurationSeconds = $allCalls->sum('duration_seconds');
        $totalBillableSeconds = $allCalls->sum('billable_seconds'); 

        $outboundCount = $allCalls->where('call_type', 'outbound')->count();
        $inboundCount = $allCalls->where('call_type', 'inbound')->count();

        $agentsPerformance = DB::table('agents')->get()->map(function ($agent) use ($allCalls) {
            $agentCalls = $allCalls->where('agent_extension', $agent->voip_extension);
            $seconds = $agentCalls->sum('duration_seconds');
            $billable = $agentCalls->sum('billable_seconds'); 
            $hours = floor($billable / 3600);
            $minutes = floor(($billable / 60) % 60);
            
            return [
                'agent_id' => $agent->id, 'agent_name' => $agent->name, 'extension' => $agent->voip_extension,
                'total_calls' => $agentCalls->count(), 'successful_calls' => $agentCalls->where('disposition', 'ANSWERED')->count(),
                'failed_calls' => $agentCalls->where('disposition', 'NO ANSWER')->count(),
                'outbound_calls_count' => $agentCalls->where('call_type', 'outbound')->count(),
                'inbound_calls_count' => $agentCalls->where('call_type', 'inbound')->count(),
                'total_talk_time_seconds' => $seconds, 'total_billable_seconds' => $billable, 
                'formatted_talk_time' => "{$hours} ساعت و {$minutes} دقیقه مکالمه مفید",
                'average_call_duration_seconds' => $agentCalls->count() > 0 ? round($billable / $agentCalls->count()) : 0
            ];
        });

        return response()->json([
            'status' => 'success',
            'summary' => [
                'total_calls' => $totalCallsCount, 'total_answered' => $answeredCallsCount,
                'total_unanswered' => $totalCallsCount - $answeredCallsCount, 'total_outbound' => $outboundCount,
                'total_inbound' => $inboundCount, 'total_duration_hours' => round($totalDurationSeconds / 3600, 2),
                'total_billable_hours' => round($totalBillableSeconds / 3600, 2), 
            ],
            'agents_performance' => $agentsPerformance
        ]);
    }

    public function getInitialConsultants() 
{
    // اینجا هر دپارتمانی که مشاور اولیه دارد را فیلتر کن
    $agents = DB::table('agents')->where('role', 'call_center')->where('is_active', 1)->get();
    return response()->json(['status' => 'success', 'data' => $agents]);
}


    public function getSupervisorReports()
    {
        $departmentStats = DB::table('next_departments')
            ->leftJoin('leads', 'next_departments.id', '=', 'leads.department_id')
            ->select('next_departments.name', DB::raw('count(leads.id) as total_leads'))
            ->groupBy('next_departments.id', 'next_departments.name')->get();

        $statusStats = DB::table('leads')
            ->select('initial_consultation_status as status', DB::raw('count(id) as count'))
            ->groupBy('initial_consultation_status')->get();

        $agentPerformance = DB::table('agents')->where('is_active', 1)->get()->map(function($agent) {
            $totalLeads = DB::table('leads')->where('agent_id', $agent->id)->count();
            $doneTasks = DB::table('next_tasks')->join('leads', 'next_tasks.lead_id', '=', 'leads.id')->where('leads.agent_id', $agent->id)->where('next_tasks.status', 'done')->count();
            $pendingTasks = DB::table('next_tasks')->join('leads', 'next_tasks.lead_id', '=', 'leads.id')->where('leads.agent_id', $agent->id)->where('next_tasks.status', 'pending')->count();
            $unreportedCalls = DB::table('next_tasks')->join('leads', 'next_tasks.lead_id', '=', 'leads.id')->where('leads.agent_id', $agent->id)->where('next_tasks.status', 'pending')->where('next_tasks.task_title', 'LIKE', '%عدم پاسخ%')->count();

            return [
                'name' => $agent->name, 'total_leads' => $totalLeads, 'done_tasks' => $doneTasks, 'pending_tasks' => $pendingTasks, 'unreported_calls' => $unreportedCalls,
                'efficiency' => ($totalLeads + $pendingTasks) > 0 ? round(($doneTasks / ($totalLeads + $pendingTasks)) * 100) : 70
            ];
        });

        return response()->json(['status' => 'success', 'department_distribution' => $departmentStats, 'status_distribution' => $statusStats, 'agent_performance' => $agentPerformance]);
    } 

    /**
 * 👑 برنامه‌ریزی اختصاصی جلسه مشاور عالی و ایجاد خودکار پلمب صورتجلسه
 */
/**
 * 👑 برنامه‌ریزی اختصاصی جلسه مشاور عالی و ایجاد خودکار پلمب صورتجلسه
 */
public function scheduleSeniorConsultation(Request $request, $leadId)
{
    $request->validate([
        'session_date_shamsi' => 'required|string',
        'next_call_date_shamsi' => 'nullable|string',
        'assigned_agent_id' => 'required|integer', 
        'session_type' => 'required|in:online,in_person,phone',
    ]);

    DB::beginTransaction();
    try {
        // ۱. پیدا کردن اطلاعات کلاینت و مشاور اولیه لید
        $lead = DB::table('leads')->where('id', $leadId)->first();
        if (!$lead) {
            return response()->json(['status' => 'error', 'message' => 'متقاضی یافت نشد'], 404);
        }

        $deadlineAt = now()->addHours(2); 

        /// درون بلاک Transaction متد scheduleSeniorConsultation بخش ثبت تسک پیگیری:
$sessionDateMiladi = null;
if (class_exists('\Morilog\Jalali\Jalalian')) {
    try {
        $sessionDateMiladi = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $request->session_date_shamsi)->toCarbon();
    } catch(\Exception $e) {}
}

DB::table('next_tasks')->insert([
    'lead_id' => $leadId,
    'task_title' => "📝 تکمیل صورتجلسه مشاور عالی ({$request->session_date_shamsi})",
    'description' => "مود برگزاری: {$request->session_type} \n مشاور گرامی، لطفا صورتجلسه شماره {$reportId} را تا قبل از اتمام ددلاین ۲ ساعته ثبت کنید.",
    'due_date_shamsi' => $request->session_date_shamsi,
    'due_date_at' => $sessionDateMiladi ?? now()->addDay(), // 🎯 سینک دقیق تایم‌استمپ سررسید برای ناظر و مشاور
    'status' => 'pending',
    'priority' => 'high',
    'target_audience' => 'staff',
    'created_at' => now(),
    'updated_at' => now()
]);

        // 🎯 فیکس نهایی و هوشمند: استخراج آیدی واقعی دیتابیس بر اساس perfex_staff_id
        // چون لیدها با کد پرسنلیِ پرفکس ست شده‌اند، ابتدا رکورد هم‌تراز را در جدول agents پیدا می‌کنیم
        $realInitialAgent = DB::table('agents')->where('perfex_staff_id', $lead->agent_id)->first();
        $initialAgentId = $realInitialAgent ? $realInitialAgent->id : null;

        // بررسی وجود مشاور عالی (که از فرانت مستقیماً آیدی دیتابیسی آن ارسال می‌شود)
        $seniorAgentExists = DB::table('agents')->where('id', $request->assigned_agent_id)->exists();
        $seniorAgentId = $seniorAgentExists ? $request->assigned_agent_id : null;

        // خاموش کردن موقت Strict Mode برای عبور بدون خطای فیلدهای ارزیابی خالی
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'STRICT_TRANS_TABLES',''))");
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'STRICT_ALL_TABLES',''))");

        // ۳. ایجاد خودکار رکورد ارزیابی جلسه با کلیدهای خارجی دقیق و واقعی
        $reportId = DB::table('next_session_reports')->insertGetId([
            'lead_id'           => $leadId,
            'agent_id'          => $initialAgentId ?? $seniorAgentId ?? 1, 
            'client_name'       => $lead->name ?? 'متقاضی سیستم',
            'initial_agent_id'  => $initialAgentId, // حالا آیدی درست (مثلاً ۱۱) جایگزین آیدی پرفکس (۲) می‌شود
            'senior_agent_id'   => $seniorAgentId,   
            'session_start_at'  => now(), 
            'session_end_at'    => now()->addHour(),
            'deadline_at'       => $deadlineAt,
            'status'            => 'pending',
            'created_at'        => now(),
            'updated_at'        => now()
        ]);

        // ۴. درج تسک پیگیری در کارتابل مشاور
        DB::table('next_tasks')->insert([
            'lead_id' => $leadId,
            'task_title' => "📝 تکمیل صورتجلسه مشاور عالی ({$request->session_date_shamsi})",
            'description' => "مود برگزاری: {$request->session_type} \n مشاور گرامی، لطفا صورتجلسه شماره {$reportId} را تا قبل از اتمام ددلاین ۲ ساعته پلمب کنید.",
            'due_date_shamsi' => $request->session_date_shamsi,
            'status' => 'pending',
            'priority' => 'high',
            'target_audience' => 'staff',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        DB::commit();
        return response()->json([
            'status' => 'success', 
            'message' => '✓ جلسه مشاور عالی با موفقیت زمان‌بندی و پلمب گزارش فعال گردید.'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}
}
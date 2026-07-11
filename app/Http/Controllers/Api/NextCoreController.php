<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Services\SmartLeadEngine;


class NextCoreController extends Controller
{
    /**
     * 🔑 ورود متمرکز کاربران سازمانی و متقاضیان رسمی
     */
    public function login(Request $request)
    {
        $request->validate(['username' => 'required|string', 'password' => 'required|string', 'portal' => 'required|string']);
        $user = null;

        if ($request->portal === 'client') {
            $cleanInput = preg_replace('/[^0-9]/', '', $request->username);
            $shortInput = strlen($cleanInput) >= 9 ? substr($cleanInput, -9) : $cleanInput;

            $user = \App\Models\User::where('role', 'client')
                ->where(function($query) use ($request, $shortInput) {
                    $query->where('email', $request->username)->orWhere('name', $request->username);
                    if (!empty($shortInput)) {
                        $query->orWhere('email', 'LIKE', "%{$shortInput}%")->orWhere('name', 'LIKE', "%{$shortInput}%");
                    }
                })->first();
        } else {
            $user = \App\Models\User::where('email', $request->username)->first();
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['status' => 'error', 'message' => 'مشخصات ورود یا کلمه عبور اشتباه است.'], 401);
        }

        if ($request->portal === 'staff' && $user->role === 'client') {
            return response()->json(['status' => 'error', 'message' => 'شما دسترسی به کارتابل کارکنان را ندارید.'], 403);
        }

        $token = $user->createToken('pishgaman_auth_token')->plainTextToken;
        $department = DB::table('next_departments')->where('id', $user->department_id)->first();
        $deptPermissions = $department ? json_decode($department->permissions, true) : [];

        return response()->json([
            'status' => 'success', 'token' => $token,
            'user' => ['id' => $user->id, 'name' => $user->name, 'role' => $user->role, 'email' => $user->email, 'user_permissions' => $deptPermissions ?: []]
        ]);
    }

    /**
     * 🦾 استقرار و داده‌کاوی پرونده مشاوره اولیه جدید
     */
    public function storeLead(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255', 'phone' => 'required|string']);

        try {
            $smartEngine = new SmartLeadEngine();
            $calculatedScore = $smartEngine->calculateScore($request->all());

            $assignedAgentId = $request->assigned_agent_id ?: $this->findBestAgentForInitialCall();
            $safePerfexId = substr(time() . rand(10, 99), 0, 9);

            $rawInsertData = [
                'name' => $request->name, 'phone' => $request->phone, 'secondary_phone' => $request->secondary_phone, 'email' => $request->email, 
                'current_city' => $request->current_city, 'age' => $request->age === '' ? null : $request->age, 'military_status' => $request->military_status,
                'education_level' => $request->education_level, 'field_of_study' => $request->field_of_study, 'gpa' => $request->gpa,
                'lead_score' => $calculatedScore, 'work_and_insurance_history' => $request->work_and_insurance_history, 'requested_plan' => $request->requested_plan,
                'target_country' => $request->target_country, 'financial_capability_toman' => $request->financial_capability_toman ?: 0,
                'discovery_channel' => $request->discovery_channel, 'web_form_link' => $request->form_link ?? 'https://pishgamanapply.com/manual-entry', 
                'is_excellent_lead' => $request->is_excellent ? 1 : 0, 'english_level' => $request->english_level, 'german_level' => $request->german_level,
                'initial_consultation_status' => $request->initial_consultation_status ?? 'مشاوره جدید', 'supervisor_status' => $request->supervisor_status ?? 'تایید اولیه ناظر',
                'department_id' => $request->department_id ? (int)$request->department_id : 1, 'agent_id' => $assignedAgentId, 
                'perfex_lead_id' => $safePerfexId, 'description' => $request->description, 'status' => 'customer_service', 'import_source' => 'next_front',
                'created_at' => now(), 'updated_at' => now()
            ];

            $schemaColumns = \Schema::getColumnListing('leads');
            $safeInsertData = collect($rawInsertData)->filter(fn($v, $k) => in_array($k, $schemaColumns))->toArray();

            $leadId = DB::table('leads')->insertGetId($safeInsertData);
            if ($assignedAgentId) { $smartEngine->generateInitialTask($leadId, $assignedAgentId); }

            return response()->json(['status' => 'success', 'message' => 'پرونده با موفقیت امتیازدهی و ارجاع شد.', 'lead_id' => $leadId]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

//    /**
//      * 🔄 به‌روزرسانی مشخصات و شناسنامه ۳۶۰ درجه متقاضیان + مانیتورینگ زنده لاگ‌ها
//      */
//     public function updateNextLead(Request $request, $id)
//     {
//         try {
//             // 📝 لاگ سطح ۱: رصد اطلاعات خام ورودی مستقیم از کلاینت فرانت‌آند
//             \Log::info("📥 [RADAR STAGE 1] Raw Request Data Received for Lead ID: {$id}", [
//                 'ip'   => $request->ip(),
//                 'method' => $request->method(),
//                 'payload' => $request->all()
//             ]);

//             $lead = Lead::findOrFail($id);
//             $rawData = $request->all();

//             // ۱. مپ کردن فیلدهای فرانت به ساختار فیزیکی دیتابیس شما
//             if (isset($rawData['score'])) $rawData['lead_score'] = $rawData['score'];
//             if (isset($rawData['level'])) $rawData['education_level'] = $rawData['level'];
//             if (isset($rawData['initial_consultation_status'])) $rawData['initial_consultation_status'] = $rawData['initial_consultation_status'];
            
//             // ۲. تبدیل تمکن مالی از میلیون تومان به ریال/تومان خام دیتابیس
//             if (isset($rawData['financial_capability_million']) && $rawData['financial_capability_million'] !== '') {
//                 $rawData['financial_capability_toman'] = floatval($rawData['financial_capability_million']) * 1000000;
//             }

//             // ۳. لایروبی و فیلتر فیلدها بر اساس ستون‌های فیزیکی واقعی جدول leads شما
//             $schemaColumns = \Schema::getColumnListing('leads');
//             $safeUpdateData = collect($rawData)->filter(function($v, $k) use ($schemaColumns) {
//                 return in_array($k, $schemaColumns) && !in_array($k, ['id', 'perfex_lead_id']);
//             })->toArray();

//             $safeUpdateData['updated_at'] = now();

//             // 📝 لاگ سطح ۲: رصد اطلاعات فیلتر شده که دقیقاً قرار است روی هارد دیتابیس کوئری شوند
//             \Log::info("🔍 [RADAR STAGE 2] Filtered Safe Data for SQL Update:", [
//                 'lead_id' => $id,
//                 'schema_columns_count' => count($schemaColumns),
//                 'data_to_write' => $safeUpdateData
//             ]);

//             // ۴. اجرای عملیات فیزیکی قفل روی دیتابیس
//             $affectedRows = DB::table('leads')->where('id', $id)->update($safeUpdateData);

//             // 📝 لاگ سطح ۳: بررسی تعداد ردیف‌های تغییر یافته در لایه MySQL
//             \Log::info("💾 [RADAR STAGE 3] Database Execution Completed.", [
//                 'lead_id' => $id,
//                 'affected_rows' => $affectedRows, // اگر 0 باشد یعنی دیتای جدید با قبلی فرقی نداشته یا شرط کاندیشن چفت نشده
//             ]);

//             return response()->json([
//                 'status' => 'success', 
//                 'message' => '✓ شناسنامه ۳۶۰ درجه پرونده با موفقیت به‌روزرسانی شد و در هسته دیتابیس قفل گردید.'
//             ]);

//         } catch (\Exception $e) {
//             // 🚨 لاگ سطح ۴: مهار خطاهای پیش‌بینی نشده یا کرش‌های سیستمی
//             \Log::error("🚨 [RADAR CRASH] Lead Update Failed: " . $e->getMessage(), [
//                 'lead_id' => $id,
//                 'trace' => $e->getTraceAsString()
//             ]);

//             return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
//         }
//     }

/**
     * 🔄 به‌روزرسانی مشخصات و شناسنامه ۳۶۰ درجه متقاضیان + لاگ رادار لایو دیتابیس (نسخه پلمب‌شده و قطعی)
     */
    public function updateNextLead(Request $request, $id)
    {
        try {
            // لاگ ورود ریکوئست فرانت
            \Log::info("📥 [OTP-360 UPDATE] Request arrived for Lead ID: {$id}", $request->all());

            $lead = Lead::findOrFail($id);
            $rawData = $request->all();

            // همگام‌سازی نام ستون‌های مجازی با فیزیکی دیتابیس
            if (isset($rawData['score'])) $rawData['lead_score'] = $rawData['score'];
            if (isset($rawData['level'])) $rawData['education_level'] = $rawData['level'];
            if (isset($rawData['initial_consultation_status'])) $rawData['initial_consultation_status'] = $rawData['initial_consultation_status'];
            
            // 🎯 مپ کردن تمکن مالی از میلیون تومان به عدد خام دیتابیس
            if (isset($rawData['financial_capability_million']) && $rawData['financial_capability_million'] !== '') {
                $rawData['financial_capability_toman'] = floatval($rawData['financial_capability_million']) * 1000000;
            }

            // لایروبی فیلدها بر اساس اسکیمای واقعی فایل SQL شما
            $schemaColumns = \Schema::getColumnListing('leads');
            
            // 🎯 پچ طلایی: اعمال فیلتر روی آرایه‌ای که شروط بالا به آن تزریق شده‌اند تا هیچ فیلدی جا نیفتد
            $safeUpdateData = collect($rawData)->filter(function($v, $k) use ($schemaColumns) {
                return in_array($k, $schemaColumns) && !in_array($k, ['id', 'perfex_lead_id']);
            })->toArray();

            $safeUpdateData['updated_at'] = now();

            // اجرای عملیات نهایی روی جدول leads
            $affected = \DB::table('leads')->where('id', $id)->update($safeUpdateData);

            \Log::info("💾 [SQL WRITE SUCCESS] Affected Rows: {$affected} for Lead ID: {$id}");

            return response()->json([
                'status' => 'success', 
                'message' => '✓ شناسنامه ۳۶۰ درجه پرونده با موفقیت به‌روزرسانی شد و در هسته دیتابیس قفل گردید.'
            ]);

        } catch (\Exception $e) {
            \Log::error("🚨 [SQL WRITE CRASH] Error: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 📲 ۱. درخواست ارسال کد تایید OTP (ورود دو مرحله‌ای مخابراتی)
     */
    public function requestOtp(Request $request)
    {
        $request->validate([
            'phone'  => 'required|string',
            'portal' => 'required|string|in:staff,client'
        ]);

        try {
            $phone = preg_replace('/[^0-9]/', '', $request->phone);
            if (str_starts_with($phone, '98') && strlen($phone) > 10) {
                $phone = '0' . substr($phone, 2);
            }

            // بررسی فیزیکی وجود شماره تلفن در هسته سیستم پیشگامان
            if ($request->portal === 'client') {
                $userExists = DB::table('leads')->where('phone', 'LIKE', "%{$phone}%")->exists();
            } else {
                $userExists = DB::table('users')->where('role', '!=', 'client')->where('phone', 'LIKE', "%{$phone}%")->exists();
            }

            if (!$userExists) {
                return response()->json(['status' => 'error', 'message' => 'شماره تلفن وارد شده در این کارتابل ثبت نشده است.'], 404);
            }

            //  نسخه پلمب‌شده و اصلاح‌یافته:
            $otpCode = strval(rand(10000, 99999));
            $expiryTime = now()->addMinutes(3); // اعتبار ۳ دقیقه‌ای شلیک کد

            // پاکسازی کدهای قبلی این شماره برای سبک ماندن دیتابیس
            DB::table('next_otp_codes')->where('phone', $phone)->delete();

            // ثبت کد تایید جدید در جدول واسط
            DB::table('next_otp_codes')->insert([
                'phone'      => $phone,
                'code'       => $otpCode,
                'portal'     => $request->portal,
                'expires_at' => $expiryTime,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // شلیک لایو پیامک از طریق سرویس IPPanel
            $ipPanel = new \App\Services\IPPanelService();
            // ⚠️ مهندس جان پترن کد زیر (مثلاً xxxx) را از پنل ippanel خودت بگیر و اینجا بذار
            // $patternCode = env('IPPANEL_PATTERN_CODE', 'YOUR_PATTERN_CODE'); 
            $patternCode = env('IPPANEL_PATTERN_CODE', 'IPPANEL_PATTERN_CODE'); 
            
            try {
                $smsSent = $ipPanel->sendOtpPattern($phone, $patternCode, [
                    'code' => $otpCode // ⚠️ نام این کلید باید دقیقاً نام متغیر داخل پنل ippanel باشد
                ]);
            } catch (\Exception $smsException) {
                \Log::warning("⚠️ [IPPanel Exception]: " . $smsException->getMessage());
                $smsSent = false;
            }
            
            // $smsSent = $ipPanel->sendOtpPattern($phone, $patternCode, [
            //     'code' => $otpCode // متغیری که در متن پترن پنل تعریف کرده‌ای
            // ]);

            if ($smsSent) {
                \Log::info("🟢 [OTP DEVELOPER MODE] Code for {$phone} is: {$otpCode}");
                return response()->json([
                    'status' => 'success', 
                    'message' => '🟢 کد تایید با موفقیت صادر شد.' . (app()->environment('local') ? ' (محیط لوکال: کد را از لاگ لاراول بردارید)' : '')
                ]);
            }

            return response()->json(['status' => 'error', 'message' => 'خطا در شلیک سامانه پیامکی IPPanel.'], 500);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 🔑 ۲. تایید نهایی کد OTP و صدور توکن متمرکز Sanctum
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'code'  => 'required|string',
            'portal'=> 'required|string|in:staff,client'
        ]);

        try {
            $phone = preg_replace('/[^0-9]/', '', $request->phone);
            if (str_starts_with($phone, '98') && strlen($phone) > 10) {
                $phone = '0' . substr($phone, 2);
            }

            // یافتن رکورد معتبر کد تایید در دیتابیس
            $otpRecord = DB::table('next_otp_codes')
                ->where('phone', $phone)
                ->where('code', $request->code)
                ->where('portal', $request->portal)
                ->where('expires_at', '>=', now())
                ->first();

            if (!$otpRecord) {
                return response()->json(['status' => 'error', 'message' => '❌ کد وارد شده اشتباه است یا منقضی شده است.'], 422);
            }

            // واکشی کاربر حقیقی جهت صدور رسمی توکن سیستم
            if ($request->portal === 'client') {
                $lead = DB::table('leads')->where('phone', 'LIKE', "%{$phone}%")->first();
                $user = \App\Models\User::where('role', 'client')->where('name', $lead->name)->first();
            } else {
                $user = \App\Models\User::where('role', '!=', 'client')->where('phone', 'LIKE', "%{$phone}%")->first();
            }

            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'کاربر متناظر یافت نشد.'], 404);
            }

            // مصرف شدن و حذف کد جهت جلوگیری از استفاده مجدد (Replay Attack)
            DB::table('next_otp_codes')->where('id', $otpRecord->id)->delete();

            // صدور رسمی توکن امن سانکتوم
            $token = $user->createToken('pishgaman_auth_token')->plainTextToken;
            $department = DB::table('next_departments')->where('id', $user->department_id)->first();
            $deptPermissions = $department ? json_decode($department->permissions, true) : [];

            return response()->json([
                'status' => 'success',
                'token'  => $token,
                'user'   => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                    'email' => $user->email,
                    'user_permissions' => $deptPermissions ?: []
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 👑 تبدیل لید به کلاینت رسمی و فعال‌سازی خودکار پورتال کلاینت
     */
    public function convertLeadToClient(Request $request)
    {
        $request->validate(['lead_id' => 'required|integer']);

        try {
            DB::beginTransaction();
            $lead = Lead::find($request->lead_id);
            if (!$lead) return response()->json(['status' => 'error', 'message' => 'پرونده متقاضی یافت نشد.'], 404);

            DB::table('leads')->where('id', $lead->id)->update([
                'status' => 'official_client',
                'initial_consultation_status' => 'عقد قرارداد نهایی',
                'supervisor_status' => 'تایید و مستقر در پرتال کلاینت ✔️',
                'updated_at' => now()
            ]);

            $userExists = DB::table('users')->where('email', $lead->email ?: $lead->phone)->exists();
            if (!$userExists) {
                DB::table('users')->insert([
                    'name' => $lead->name, 'email' => $lead->email ?: $lead->phone,
                    'password' => Hash::make(substr($lead->phone, -6)), 'role' => 'client',
                    'department_id' => $lead->department_id ?: 1, 'created_at' => now(), 'updated_at' => now()
                ]);
            }

            DB::table('chat_logs')->insert([
                'lead_id' => $lead->id, 'channel' => 'system', 'sender_type' => 'bot',
                'message' => "🎉 پرتال اختصاصی متقاضی فعال گردید.", 'is_analyzed' => true, 'created_at' => now()
            ]);

            DB::commit();
            return response()->json(['status' => 'success', 'message' => "متقاضی با موفقیت به کلاینت رسمی تبدیل شد. رمز ورود: ۶ رقم آخر تلفن."]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 🔗 ادغام پرونده‌های تکراری مخابرات و کلاینت‌ها
     */
    public function mergeLeads(Request $request)
    {
        $request->validate(['target_lead_id' => 'required|integer', 'master_lead_id' => 'required|integer']);
        try {
            DB::beginTransaction();
            $target = DB::table('leads')->where('id', $request->target_lead_id)->first();
            $master = DB::table('leads')->where('id', $request->master_lead_id)->orWhere('perfex_lead_id', $request->master_lead_id)->first();

            if (!$target || !$master) return response()->json(['status' => 'error', 'message' => 'پرونده اصلی یافت نشد.'], 404);
            if ($target->id === $master->id) return response()->json(['status' => 'error', 'message' => 'امکان ادغام یک لید با خودش وجود ندارد.'], 400);

            DB::table('chat_logs')->where('lead_id', $target->id)->update(['lead_id' => $master->id]);
            DB::table('next_tasks')->where('lead_id', $target->id)->update(['lead_id' => $master->id]);
            DB::table('next_reminders')->where('lead_id', $target->id)->update(['lead_id' => $master->id]);

            if (!empty($target->telegram_chat_id)) DB::table('leads')->where('id', $master->id)->update(['telegram_chat_id' => $target->telegram_chat_id]);
            DB::table('leads')->where('id', $target->id)->delete();

            DB::commit();
            return response()->json(['status' => 'success', 'message' => "ادغام با موفقیت روی پرونده اصلی اعمال شد."]);
        } catch (\Exception $e) { DB::rollBack(); return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500); }
    }

    // --- سیستم تسک‌ها، یادآورها و دپارتمان‌ها ---
    public function getReminders($lead_id) { return response()->json(['status' => 'success', 'data' => DB::table('next_reminders')->where('lead_id', $lead_id)->orderBy('id', 'desc')->get()]); }
    public function getTasks($lead_id) { return response()->json(['status' => 'success', 'data' => DB::table('next_tasks')->where('lead_id', $lead_id)->orderBy('id', 'desc')->get()]); }
    
    public function getLeadCallLogs($leadId) {
        $lead = Lead::find($leadId);
        if (!$lead) return response()->json(['status' => 'error', 'message' => 'پرونده یافت نشد'], 404);
        $searchPhone = substr(preg_replace('/[^0-9]/', '', $lead->phone), -10);
        $logs = DB::table('voip_call_stats')->where('lead_id', $leadId)->orWhere('customer_phone', 'LIKE', "%{$searchPhone}%")->orderBy('created_at', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $logs]);
    }

    public function storeTask(Request $request) {
        $request->validate(['lead_id' => 'required', 'task_title' => 'required|string']);
        $taskId = DB::table('next_tasks')->insertGetId(['lead_id' => $request->lead_id, 'task_title' => $request->task_title, 'due_date_shamsi' => $request->due_date_shamsi, 'status' => 'pending', 'priority' => $request->priority ?? 'medium', 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['status' => 'success', 'task_id' => $taskId]);
    }

    public function storeReminder(Request $request) {
        $request->validate(['lead_id' => 'required', 'title' => 'required|string', 'reminder_date_shamsi' => 'required|string', 'reminder_time' => 'required|string']);
        $timestamp = $this->jalaliToTimestamp($request->reminder_date_shamsi, $request->reminder_time);
        DB::table('next_reminders')->insert(['lead_id' => $request->lead_id, 'title' => $request->title, 'description' => $request->description, 'reminder_date_shamsi' => $request->reminder_date_shamsi, 'reminder_time' => $request->reminder_time, 'reminder_timestamp' => $timestamp, 'notification_channels' => json_encode($request->notification_channels ?? ['in_app']), 'is_notified' => false, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['status' => 'success']);
    }

    public function checkNow() {
        $reminder = DB::table('next_reminders')->where('is_notified', false)->where('status', 'pending')->where('reminder_timestamp', '<=', now()->timestamp)->first();
        if ($reminder) {
            $lead = DB::table('leads')->where('id', $reminder->lead_id)->first();
            $channels = json_decode($reminder->notification_channels ?? '["in_app"]', true);
            \App\Jobs\SendNotificationJob::dispatch($channels, $reminder->title, $reminder->description, $lead ? $lead->phone : null);
            if (!in_array('in_app', $channels)) { DB::table('next_reminders')->where('id', $reminder->id)->update(['is_notified' => true, 'status' => 'success']); return response()->json(['status' => 'none']); }
            return response()->json(['status' => 'success', 'reminder' => $reminder]);
        }
        return response()->json(['status' => 'none']);
    }

    public function updateReminderStatus(Request $request, $id) { DB::table('next_reminders')->where('id', $id)->update(['status' => $request->status, 'is_notified' => true, 'updated_at' => now()]); return response()->json(['status' => 'success']); }
    public function updateTaskStatus(Request $request, $id) { DB::table('next_tasks')->where('id', $id)->update(['status' => $request->status, 'updated_at' => now()]); return response()->json(['status' => 'success']); }
    public function checkLivePopup(Request $request) { $ext = $request->query('extension'); if (empty($ext)) return response()->json(['has_call' => false]); $call = cache()->get("live_call_ext_{$ext}"); return response()->json(['has_call' => (bool)$call, 'data' => $call]); }

    /**
     * 🏢 واکشی لیست دپارتمان‌ها 
     */
    public function getDepartments() 
    { 
        try {
            $deps = DB::table('next_departments')->get(); 
            
            $mappedDeps = $deps->map(fn($d) => [
                'id' => $d->id, 
                'name' => $d->name, 
                'slug' => $d->slug ?? '', 
                'permissions' => json_decode($d->permissions ?? '[]', true)
            ]);

            return response()->json(['status' => 'success', 'data' => $mappedDeps]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 👥 ثبت کارشناس جدید + پلمب خودکار خطوط داخلی (حل قطعی باگ Duplicate Entry شناسه پرفکس)
     */
    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6',
            'voip_extension' => 'required|string'
        ]);

        try {
            DB::beginTransaction();

            $rawUserData = [
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role ?? 'agent',
                'department_id' => $request->department_id ? (int)$request->department_id : 1,
                'voip_extension' => $request->voip_extension,
                'created_at' => now(),
                'updated_at' => now()
            ];

            // گارد اسکن لایو دیتابیس برای فیلد وضعیت
            $userColumns = \Schema::getColumnListing('users');
            if (in_array('status', $userColumns)) {
                $rawUserData['status'] = 'active';
            }

            // ۱. ثبت یوزر و دریافت شناسه ۱۰۰٪ منحصر‌به‌فرد و ترتیبی
            $userId = DB::table('users')->insertGetId($rawUserData);

            // ۲. 🎯 حل باگ: استفاده از آی‌دی یونیک یوزر برای پرفکس استاف آی‌دی جهت تضمین عدم تکرار
            $safePerfexId = (int)('178' . $userId);

            DB::table('agents')->insert([
                'perfex_staff_id' => $safePerfexId, // کاملاً داینامیک و بدون تداخل
                'name' => $request->name,
                'email' => $request->email,
                'voip_extension' => $request->voip_extension,
                'role' => ($request->role === 'supervisor') ? 'supervisor' : 'call_center',
                'department_id' => $request->department_id ? (int)$request->department_id : 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();
            return response()->json(['status' => 'success', 'message' => '✓ کارشناس جدید با موفقیت ایجاد و داخلی‌های مخابرات پلمب شدند.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 👥 ویرایش جامع پروفایل کارشناسان و پرمیشن‌ها (رفع باگ تکرار متد و خطای ۴۰۴)
     */
    public function updateUserComplete(Request $request, $id)
    {
        try {
            $rawData = $request->all();

            // مهار هوشمند نوع داده دسترسی‌ها
            if (isset($rawData['permissions'])) {
                if (is_array($rawData['permissions']) || is_object($rawData['permissions'])) {
                    $rawData['permissions'] = json_encode($rawData['permissions'], JSON_UNESCAPED_UNICODE);
                }
            }

            if (!empty($rawData['password'])) {
                $rawData['password'] = Hash::make($rawData['password']);
            } else {
                unset($rawData['password']);
            }

            // لایروبی و پاکسازی ستون‌های ارسالی بر اساس اسکیما واقعی دیتابیس شما
            $schemaColumns = \Schema::getColumnListing('users');
            $safeUpdateData = collect($rawData)->filter(fn($v, $k) => in_array($k, $schemaColumns))->toArray();

            DB::table('users')->where('id', $id)->update($safeUpdateData);

            // همگام‌سازی خطوط داخلی در جدول سنترال مشاورین (agents)
            $userEmail = DB::table('users')->where('id', $id)->value('email');
            if ($userEmail && isset($rawData['voip_extension'])) {
                DB::table('agents')
                    ->where('email', $userEmail)
                    ->update([
                        'name' => $rawData['name'] ?? DB::table('users')->where('id', $id)->value('name'),
                        'voip_extension' => $rawData['voip_extension'],
                        'role' => (isset($rawData['role']) && $rawData['role'] === 'supervisor') ? 'supervisor' : 'call_center',
                        'department_id' => $rawData['department_id'] ?? null,
                        'updated_at' => now()
                    ]);
            }

            return response()->json(['status' => 'success', 'message' => '✓ تغییرات با موفقیت اعمال شد.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 👑 مدیریت دپارتمان: اختصاص یا جابجایی یک کارشناس به دپارتمان هدف
     */
    public function assignAgentToDepartment(Request $request)
    {
        // گارد امنیتی: فقط ادمین یا سوپروایزر حق جابجایی پرسنل را دارند
        if (auth()->user()->role !== 'supervisor' && auth()->user()->role !== 'admin') {
            return response()->json(['status' => 'error', 'message' => 'شما سطح دسترسی لازم برای ویرایش دپارتمان‌ها را ندارید.'], 403);
        }

        $request->validate([
            'agent_id' => 'required|integer',
            'department_id' => 'required|integer'
        ]);

        try {
            // ۱. بررسی وجود کاربر در دیتابیس
            $userExists = DB::table('users')->where('id', $request->agent_id)->exists();
            if (!$userExists) {
                return response()->json(['status' => 'error', 'message' => 'کارشناس مورد نظر در هسته سیستم یافت نشد.'], 404);
            }

            // ۲. به‌روزرسانی اتمیک دپارتمان کارشناس
            DB::table('users')
                ->where('id', $request->agent_id)
                ->update([
                    'department_id' => $request->department_id,
                    'updated_at' => now()
                ]);

            return response()->json([
                'status' => 'success', 
                'message' => '✓ کارشناس با موفقیت به دپارتمان جدید منتقل و دسترسی‌های او همگام‌سازی شد.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

   /**
     * 👥 واکشی اتمیک تمام کاربران و کارشناسان سیستم با ترکیب داینامیک جداول users و agents
     */
    public function getUsersList()
    {
        try {
            // ۱. دریافت تمام کاربران از جدول users
            $users = DB::table('users')->orderBy('id', 'desc')->get();
            
            // ۲. دریافت تمام رکوردهای سازمانی از جدول agents
            $agents = DB::table('agents')->get()->keyBy('email');

            $mappedUsers = $users->map(function($user) use ($agents) {
                // تلاش برای پیدا کردن رکورد متناظر مخابراتی از روی ایمیل
                $agentRecord = $agents->get($user->email);
                
                // تجمیع و ترامپ داینامیک داخلی‌ها (اولویت با دیتای جدول agents است)
                $voipExtension = $user->voip_extension;
                if ($agentRecord && !empty($agentRecord->voip_extension)) {
                    $voipExtension = $agentRecord->voip_extension;
                }

                return [
                    'id'             => $user->id,
                    'name'           => $user->name ?? 'کارشناس بدون نام',
                    'email'          => $user->email ?? '---',
                    'role'           => $user->role ?? 'agent',
                    'department_id'  => $user->department_id,
                    'voip_extension' => $voipExtension ?: '---',
                    'status'         => $user->status ?? 'active',
                    'permissions'    => $user->permissions ?? null,
                ];
            });

            return response()->json(['status' => 'success', 'data' => $mappedUsers]);
        } catch (\Exception $e) {
            \Log::error("🚨 [getUsersList Aggregation Error]: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getLeadSources() { return response()->json(['status' => 'success', 'data' => ['سرچ گوگل', 'اینستاگرام شرکت', 'کمپین تلگرام', 'تبلیغات یکتانت', 'توصیه دوستان / معرف', 'سایت اصلی']]); }
    public function destroyNextLead($id) { Lead::findOrFail($id)->delete(); return response()->json(['status' => 'success']); }
    
    public function findBestAgentForInitialCall() {
        $agents = DB::table('agents')->where('is_active', 1)->get();
        if ($agents->isEmpty()) return null;
        $bestId = null; $minLoad = PHP_INT_MAX;
        foreach ($agents as $agent) {
            $load = DB::table('next_tasks')->join('leads', 'next_tasks.lead_id', '=', 'leads.id')->where('leads.agent_id', $agent->id)->where('next_tasks.status', 'pending')->count();
            if ($load < $minLoad) { $minLoad = $load; $bestId = $agent->id; }
        }
        return $bestId;
    }

    private function jalaliToTimestamp($jalaliDate, $time = '00:00') {
        list($jy, $jm, $jd) = explode('/', $jalaliDate); list($hour, $minute) = explode(':', $time);
        $jy -= 979; $jm -= 1; $jd -= 1;
        $j_day_no = 365 * $jy + floor($jy / 33) * 8 + floor(($jy % 33 + 3) / 4);
        for ($i = 0; $i < $jm; ++$i) $j_day_no += ($i < 6) ? 31 : 30;
        $g_day_no = $j_day_no + $jd + 79; $gy = 1600 + 400 * floor($g_day_no / 146097); $g_day_no %= 146097; $leap = true;
        if ($g_day_no >= 36525) { $g_day_no--; $gy += 100 * floor($g_day_no / 36524); $g_day_no %= 36524; if ($g_day_no >= 365) $g_day_no++; else $leap = false; }
        $gy += 4 * floor($g_day_no / 1461); $g_day_no %= 1461;
        if ($g_day_no >= 366) { $g_day_no--; $gy += floor($g_day_no / 365); $g_day_no %= 365; $leap = false; }
        $g_day_no++; $g_days_in_month = [31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        for ($gm = 0; $gm < 12; ++$gm) { $v = $g_days_in_month[$gm]; if ($g_day_no <= $v) break; $g_day_no -= $v; } $gm++;
        return \Carbon\Carbon::create($gy, $gm, $g_day_no, (int)$hour, (int)$minute, 0, 'Asia/Tehran')->timestamp;
    }

    /**
     * ⚖️ ویرایش دستی و اصلاح کارکرد پرسنل توسط ناظر ارشد (بدون خطای ۵۰۰)
     */
   
    /**
     * ⚖️ ویرایش دستی و اصلاح کارکرد پرسنل توسط ناظر ارشد (رفع قطعی خطای ۱۱۴۶)
     */
    public function updateManualAttendance(Request $request)
    {
        if (auth()->user()->role !== 'supervisor' && auth()->user()->role !== 'admin') {
            return response()->json(['status' => 'error', 'message' => 'شما سطح دسترسی لازم برای ویرایش دستی کارکرد را ندارید.'], 403);
        }

        $request->validate([
            'attendance_id' => 'required|integer',
            'clock_in_time'  => 'required|string',
            'clock_out_time' => 'required|string',
        ]);

        try {
            // ۱. 🎯 واکشی معتبر از جدول اصلی و بومی کورتکس شما
            $attendance = DB::table('next_attendance_clocks')->where('id', $request->attendance_id)->first();
            
            if (!$attendance) {
                return response()->json(['status' => 'error', 'message' => 'رکورد تردد مورد نظر در جدول کورتکس یافت نشد.'], 404);
            }

            // مپ کردن زمان بر پایه تایم‌استمپ روز ثبت شده اولیه
            $baseDate = date('Y-m-d', $attendance->clock_in_timestamp);
            
            $startTs = strtotime($baseDate . ' ' . $request->clock_in_time);
            $endTs = strtotime($baseDate . ' ' . $request->clock_out_time);

            if ($endTs < $startTs) {
                return response()->json(['status' => 'error', 'message' => 'ساعت خروج نمی‌تواند قبل از ساعت ورود باشد.'], 400);
            }

            $durationSeconds = $endTs - $startTs;

            // ۲. 🎯 پچ قطعی: تغییر نام جدول به next_attendance_clocks در لایه بروزرسانی MySQL
            DB::table('next_attendance_clocks')->where('id', $request->attendance_id)->update([
                'clock_in_timestamp'  => $startTs,
                'clock_out_timestamp' => $endTs,
                'duration_seconds'    => $durationSeconds,
                'updated_at'          => now()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => '✓ کارکرد کارشناس با موفقیت اصلاح و مجموع ثانیه‌های مفید بازنویسی شد.'
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
    /**
     * 🌐 وب‌هوک متمرکز دریافت و دکود کردن لیدهای ورودی از سایت اصلی (پیشگامان)
     */
    // public function handleWebsiteWebhook(Request $request)
    // {
    //     // ۱. رصد و لاگ کردن جیسون خام ورودی در storage/logs/laravel.log جهت مانیتورینگ
    //     \Log::info("📥 [WEBHOOK RECEIVED] Raw Payload From Website:", $request->all());

    //     // ۲. ولیدیشن ساختار داده (مطمئن شو نام و شماره همراه در پکت ارسال شده باشد)
    //     if (!$request->has('phone') || !$request->has('name')) {
    //         return response()->json(['status' => 'error', 'message' => 'پکت JSON نامعتبر است. فیلدهای name و phone الزامی هستند.'], 400);
    //     }

    //     try {
    //         $name  = $request->input('name');
    //         $phone = preg_replace('/[^0-9]/', '', $request->input('phone'));

    //         // ۳. بررسی عدم تکراری بودن لید در جدول leads جهت جلوگیری از اسپم دیتابیس
    //         $exists = DB::table('leads')->where('phone', 'LIKE', "%{$phone}%")->exists();
    //         if ($exists) {
    //             return response()->json(['status' => 'success', 'message' => 'این لید قبلاً در کارتابل ثبت شده است و نیازی به ادغام مجدد ندارد.']);
    //         }

    //         // ۴. امتیازدهی و تخصیص هوشمند کارشناس به لیدهای سایت
    //         $smartEngine = new \App\Services\SmartLeadEngine();
    //         $calculatedScore = $smartEngine->calculateScore($request->all());
    //         $assignedAgentId = $this->findBestAgentForInitialCall();
    //         $safePerfexId = substr(time() . rand(10, 99), 0, 9);

    //         // ۵. پلمب فیزیکی لید دکود شده در جدول leads
    //         $leadId = DB::table('leads')->insertGetId([
    //             'name' => $name,
    //             'phone' => $phone,
    //             'email' => $request->input('email'),
    //             'target_country' => $request->input('target_country') ?? 'تعیین نشده',
    //             'requested_plan' => $request->input('requested_plan'),
    //             'lead_score' => $calculatedScore,
    //             'agent_id' => $assignedAgentId,
    //             'perfex_lead_id' => $safePerfexId,
    //             'discovery_channel' => $request->input('source') ?? 'رزرو سایت اصلی',
    //             'initial_consultation_status' => 'مشاوره جدید',
    //             'created_at' => now(),
    //             'updated_at' => now()
    //         ]);

    //         // تولید خودکار تسک برای کارشناس مسئول
    //         if ($assignedAgentId) { 
    //             $smartEngine->generateInitialTask($leadId, $assignedAgentId); 
    //         }

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => '✓ لید سایت اصلی دکود شد و در کارتابل متمرکز مشاوران قرار گرفت.',
    //             'lead_id' => $leadId
    //         ], 201);

    //     } catch (\Exception $e) {
    //         \Log::error("🚨 [Webhook Crash]: " . $e->getMessage());
    //         return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    //     }
    // }

    public function handleWebsiteWebhook(Request $request)
    {
        if (!$request->has('lead.name') || !$request->has('lead.phonenumber')) {
            return response()->json([
                'status' => 'error', 
                'message' => 'پکت JSON نامعتبر است. فیلدهای لید الزامی هستند.'
            ], 400);
        }

        try {
            $leadData = $request->input('lead');
            $name  = $leadData['name'];
            $phone = preg_replace('/[^0-9]/', '', $leadData['phonenumber']);
            $age   = isset($leadData['age']) ? (int)$leadData['age'] : null;
            $education = $leadData['education'] ?? 'نامشخص';

            // ۱. گارد اتمیک ضد اسپم و تکرار بر پایه شماره همراه
            $exists = DB::table('leads')->where('phone', 'LIKE', "%{$phone}%")->exists();
            if ($exists) {
                return response()->json([
                    'status' => 'success', 
                    'message' => '✓ این لید با موفقیت در گذشته ثبت شده است؛ پردازش تکراری لغو شد.'
                ], 200);
            }

            // 🎯 ۲. کورتکس هوشمند و ۳ لایه تفکیک منبع ورودی (تهران ویزا / پیشگامان)
            $discoverySource = 'پیشگامان'; // مقدار پیش‌فرض دکستاپ
            $rawSourceCode = isset($leadData['source']) ? trim($leadData['source']) : '';

            if ($rawSourceCode === '18') {
                // 🔹 لایه اول: تطبیق کد اختصاصی ۱۸ برای تهران ویزا
                $discoverySource = 'تهران ویزا';
            } elseif ($rawSourceCode === '12') {
                // 🔹 لایه دوم: تطبیق کد اختصاصی ۱۲ برای پیشگامان
                $discoverySource = 'پیشگامان';
            } else {
                // 🔹 لایه سوم: گارد بک‌آپ (فال‌بک در صورت عدم ارسال کد عددی از فرم‌های خاص)
                if ($request->input('is_tehranvisa') === true || str_contains($request->input('origin'), 'tehranvisa.com')) {
                    $discoverySource = 'تهران ویزا';
                }
            }

            // ۳. کورتکس موتور امتیازدهی هوشمند پورتال
            $smartEngine = new \App\Services\SmartLeadEngine();
            $normalizedDataForEngine = [
                'name' => $name,
                'phone' => $phone,
                'age' => $age,
                'education_level' => $education,
                'requested_plan' => $request->input('endpoint') ?? 'فرم عمومی'
            ];
            
            $calculatedScore = $smartEngine->calculateScore($normalizedDataForEngine);
            $assignedAgentId = $this->findBestAgentForInitialCall();
            $safePerfexId = substr(time() . rand(10, 99), 0, 9);
            
            // تعیین تگ الماس مشاوره عالی (فقط برای امتیازهای طلایی بالای ۸۰)
            // $isExcellent = ($calculatedScore >= 80 || ($request->input('is_excellent') == true)) ? 1 : 0;

            // ۴. پلمب فیزیکی داده‌های دکود شده روی هارد دیتابیس
            $leadId = DB::table('leads')->insertGetId([
                'name' => $name,
                'phone' => $phone,
                'age' => $age,
                'education_level' => $education,
                'lead_score' => $calculatedScore,
                'agent_id' => $assignedAgentId,
                'perfex_lead_id' => $safePerfexId,
                'discovery_channel' => $discoverySource, // 🎯 ثبت سورس داینامیک و تایید شده قطعی
                'web_form_link' => $leadData['formlink'] ?? $request->input('origin'),
                'initial_consultation_status' => 'مشاوره جدید',
                'supervisor_status' => 'استقرار خودکار از وب‌هوک 🌐',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // ۵. صدور خودکار نخستین تسک برای مشاور مسئول کال‌سنتر
            if ($assignedAgentId) { 
                $smartEngine->generateInitialTask($leadId, $assignedAgentId); 
            }

            return response()->json([
                'status' => 'success',
                'message' => "✓ لید با موفقیت در دپارتمان {$discoverySource} پلمب شد.",
                'lead_id' => $leadId
            ], 201);

        } catch (\Exception $e) {
            \Log::error("🚨 [Website Webhook Processing Crash]: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
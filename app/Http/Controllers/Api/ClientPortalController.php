<?php



namespace App\Http\Controllers\Api;



use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use App\Models\Lead;



class ClientPortalController extends Controller

{

    /**

     * 🧠 متد کمکی استخراج لید متصل به کاربر لاگین شده بدون وابستگی اجباری به ستون email

     */



    private function getAuthenticatedClientLead()

    {

        $user = auth()->user();

        if (!$user) return null;



        // استخراج ارقام عددی برای مچ کردن شماره تلفن‌ها (فرار از تداخل ساختار ۰۹ یا +۹۸)

        $cleanUserEmail = preg_replace('/[^0-9]/', '', $user->email);

        $cleanUserName = preg_replace('/[^0-9]/', '', $user->name);



        $shortEmail = strlen($cleanUserEmail) > 10 ? substr($cleanUserEmail, -10) : $cleanUserEmail;

        $shortName = strlen($cleanUserName) > 10 ? substr($cleanUserName, -10) : $cleanUserName;



        $query = \App\Models\Lead::query();



        // اسکن همه‌جانبه زنجیره خطوط بر اساس ساختار یوزر لاگین شده

        $query->where(function($q) use ($user, $shortEmail, $shortName) {

            $q->where('phone', $user->email)

              ->orWhere('phone', $user->name);



            if (!empty($shortEmail)) {

                $q->orWhere('phone', 'LIKE', "%{$shortEmail}%");

            }

            if (!empty($shortName)) {

                $q->orWhere('phone', 'LIKE', "%{$shortName}%");

            }

        });



        return $query->first();

    }



//    /**
//      * 📊 ۱. دریافت اطلاعات اصلی دشبورد کلاینت + مانیتورینگ خودکار ثانیه‌ها و مشاوران فعال پرونده
//      */
//     public function getDashboardMetrics()
//     {
//         // 🧠 استخراج لید واقعی پرونده با متد اختصاصی شما جهت فرار از تداخل دیتابیس
//         $lead = $this->getAuthenticatedClientLead();
//         if (!$lead) {
//             return response()->json(['status' => 'error', 'message' => 'پرونده مهاجرتی شما یافت نشد.'], 404);
//         }

//         // ۱. تجمیع لایو کل زمان مکالمات تلفنی این پرونده بر پایه دقیقه
//         $totalCallMinutes = DB::table('next_calls_logs')
//             ->where('lead_id', $lead->id) // پیوند دقیق با شناسه لید پرونده کلاینت
//             ->sum('duration_minutes');

//         // ۲. تجمیع خودکار زمان صرف شده برای تیکت‌های پشتیبانی کلاینت (کف ۵ دقیقه + زمان واقعی مشاور)
//         $totalTicketMinutes = DB::table('client_tickets')
//             ->where('lead_id', $lead->id)
//             ->sum('spent_time_minutes');

//         // ۳. استخراج متمایز تمام کارشناسان و مشاورانی که برای این کلاینت اقدام یا تیکت ثبت کرده‌اند
//         $activeConsultants = DB::table('client_tickets')
//             ->join('users', 'client_tickets.assigned_agent_id', '=', 'users.id')
//             ->where('client_tickets.lead_id', $lead->id)
//             ->distinct()
//             ->select('users.name', 'users.role', 'users.agent_extension')
//             ->get();

//         // ۴. خروجی نهایی ساختاریافته و اتمیک جهت رندر در فرانت‌اند لوکس شما
//         return response()->json([
//             'status' => 'success',
//             'client_info' => [
//                 'name' => $lead->name,
//                 'phone' => $lead->phone,
//                 'plan' => $lead->requested_plan ?? 'مشاوره مهاجرت',
//                 'country' => $lead->target_country ?? 'آلمان',
//                 'status_label' => $lead->initial_consultation_status ?? 'تشکیل پرونده اولیه',
//                 'score' => $lead->lead_score ?? 70,
//             ],
//             'timeline' => [
//                 'current_stage' => $lead->status === 'official_client' ? 2 : 1,
//                 'stages' => ['ارزیابی اولیه', 'عقد قرارداد رسمی', 'ترجمه و تایید مدارک', 'صدور ویزا / مهاجرت']
//             ],
//             // 🎯 کورتکس پایش عملکرد؛ بدون کوچکترین وابستگی متفرقه، پلمب در لایه فرانت
//             'tracking_summary' => [
//                 'total_call_minutes' => (int)$totalCallMinutes,
//                 'total_ticket_minutes' => (int)$totalTicketMinutes,
//                 'active_consultants' => $activeConsultants
//             ]
//         ]);
//     }



/**
     * 📊 ۱. دریافت اطلاعات اصلی دشبورد کلاینت + پایش هوشمند مشاوران بر پایه تاریخچه تماس واقعی
     */
    public function getDashboardMetrics()
    {
        // استخراج لید واقعی پرونده مهاجرتی کلاینت با متد اختصاصی شما
        $lead = $this->getAuthenticatedClientLead();
        if (!$lead) {
            return response()->json(['status' => 'error', 'message' => 'پرونده مهاجرتی شما یافت نشد.'], 404);
        }

        try {
            // ۱. تجمیع ثانیه‌های تماس از جدول voip_call_stats و تبدیل آن به دقیقه
            $totalCallSeconds = DB::table('voip_call_stats')
                ->where('lead_id', $lead->id)
                ->sum('duration_seconds');

            $totalCallMinutes = $totalCallSeconds > 0 ? ceil($totalCallSeconds / 60) : 0;

            // ۲. محاسبه زمان تیکت‌ها بر اساس تعداد تیکت‌های موجود در جدول client_tickets (هر تیکت ۵ دقیقه پیش‌فرض)
            $ticketCount = DB::table('client_tickets')->where('lead_id', $lead->id)->count();
            $totalTicketMinutes = $ticketCount * 5;

            // ۳. 🎯 پلمب نهایی و حل باگ: استخراج مشاوران فعال از روی لاگ تماس‌های voip_call_stats و پیوند با voip_extension جدول agents
           $activeConsultants = DB::table('voip_call_stats')
                ->join('agents', function($join) {
                    $join->on(DB::raw("FIND_IN_SET(voip_call_stats.agent_extension, REPLACE(agents.voip_extension, ' ', ''))"), '>', DB::raw('0'));
                })
                ->where('voip_call_stats.lead_id', $lead->id)
                ->where('voip_call_stats.disposition', 'ANSWERED') // 👈 شلیک به هدف: فیلتر کردن قطعی تماس‌های موفق و پاسخ‌داده‌شده
                ->distinct()
                ->select(
                    'agents.name', 
                    'agents.role', 
                    'voip_call_stats.agent_extension'
                )
                ->get();

            // ۴. خروجی نهایی ساختاریافته و اتمیک جهت رندر در فرانت‌آند لوکس شما
            return response()->json([
                'status' => 'success',
                'client_info' => [
                    'name' => $lead->name,
                    'phone' => $lead->phone,
                    'plan' => $lead->requested_plan ?? 'مشاوره مهاجرت',
                    'country' => $lead->target_country ?? 'آلمان',
                    'status_label' => $lead->initial_consultation_status ?? 'تشکیل پرونده اولیه',
                    'score' => $lead->lead_score ?? 70,
                ],
                'timeline' => [
                    'current_stage' => $lead->status === 'official_client' ? 2 : 1,
                    'stages' => ['ارزیابی اولیه', 'عقد قرارداد رسمی', 'ترجمه و تایید مدارک', 'صدور ویزا / مهاجرت']
                ],
                'tracking_summary' => [
                    'total_call_minutes' => (int)$totalCallMinutes,
                    'total_ticket_minutes' => (int)$totalTicketMinutes,
                    'active_consultants' => $activeConsultants
                ]
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("خطای پایش دشبورد کلاینت: " . $e->getMessage());
            
            return response()->json([
                'status' => 'success',
                'client_info' => [
                    'name' => $lead->name,
                    'phone' => $lead->phone,
                    'plan' => $lead->requested_plan ?? 'مشاوره مهاجرت',
                    'country' => $lead->target_country ?? 'آلمان',
                    'status_label' => $lead->initial_consultation_status ?? 'تشکیل پرونده اولیه',
                    'score' => $lead->lead_score ?? 70,
                ],
                'timeline' => ['current_stage' => 1, 'stages' => ['ارزیابی اولیه', 'عقد قرارداد رسمی']],
                'tracking_summary' => ['total_call_minutes' => 0, 'total_ticket_minutes' => 0, 'active_consultants' => []]
            ]);
        }
    }

   /**

     * 📋 ۱. واکشی اختصاصی وظایف متقاضی (تزریق فیلد کلیدی global_doc_id جهت تفکیک مودال هوشمند فرانت)
     */
   public function getMyTasks()
{
    $lead = $this->getAuthenticatedClientLead();
    if (!$lead) return response()->json(['status' => 'error', 'message' => 'پرونده یافت نشد'], 404);

    $tasks = DB::table('next_tasks')
        ->where('lead_id', $lead->id)
        ->where('target_audience', 'client') 
        ->orderBy('id', 'desc')
        ->get()
        ->map(function($task) {
            return [
                'id' => $task->id,
                'global_doc_id' => $task->global_doc_id ?? null,
                'task_title' => $task->task_title,
                'description' => $task->description ?? '',
                'due_date_shamsi' => $task->due_date_shamsi ?? '---',
                
                // 🎯 پچ جدید: ارسال تایم‌استمپ استاندارد میلادی برای کامپوننت‌های فرانت
                'due_date_iso' => $task->due_date_at ? \Carbon\Carbon::parse($task->due_date_at)->toIso8601String() : null,
                'start_date_iso' => $task->start_date_at ? \Carbon\Carbon::parse($task->start_date_at)->toIso8601String() : null,
                'reminder_iso' => $task->reminder_at ? \Carbon\Carbon::parse($task->reminder_at)->toIso8601String() : null,
                
                'status' => $task->status, 
                'priority' => $task->priority,
                'has_reminder' => $task->has_reminder ?? 0,
                'client_file_url' => $task->client_file_path ? asset('storage/' . $task->client_file_path) : null,
                'created_at' => \Carbon\Carbon::parse($task->created_at)->format('Y/m/d')
            ];
        });

    return response()->json(['status' => 'success', 'data' => $tasks]);
}



    /**

     * 💰 ۳. واکشی فاکتورهای مالی کلاینت

     */

    public function getMyInvoices()

    {

        $lead = $this->getAuthenticatedClientLead();

        if (!$lead) return response()->json(['status' => 'error', 'message' => 'پرونده یافت نشد'], 404);



        $invoices = DB::table('client_invoices')

            ->where('lead_id', $lead->id)

            ->orderBy('id', 'desc')

            ->get();



        return response()->json(['status' => 'success', 'data' => $invoices]);

    }



    /**

     * 🎯 ثبت فایل پاسخ کلاینت برای تسک خاص (ساختار ترلو - فیکس ارور Column not found)

     */

    public function answerTaskWithFile(Request $request, $taskId)

    {

        $request->validate([

            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,zip,rar|max:25480',

            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,zip,rar|max:25480'

        ]);



        try {

            DB::beginTransaction();



            $uploadedFile = $request->file('file') ?? $request->file('attachment');



            if (!$uploadedFile) {

                return response()->json(['status' => 'error', 'message' => 'فایلی دریافت نشد رفیق.'], 400);

            }



            // ۱. ذخیره فیزیکی فایل روی استوریج سرور

            $path = $uploadedFile->store('task_attachments', 'public');



            // ۲. ثبت سند در جدول واسط پیوست‌های تسک (پشتیبانی از چندین فایل مثل ترلو)

            DB::table('task_attachments')->insert([

                'task_id' => $taskId,

                'file_name' => $uploadedFile->getClientOriginalName(),

                'file_path' => $path,

                'uploaded_by' => 'client',

                'created_at' => now(),

                'updated_at' => now()

            ]);



            // ۳. آپدیت وضعیت کارت تسک به حالت Done

            DB::table('next_tasks')->where('id', $taskId)->update([

                'status' => 'done',

                'updated_at' => now()

            ]);



            DB::commit();

            return response()->json([

                'status' => 'success',

                'message' => '✓ مدرک شما با موفقیت در پیوست‌های این کارت صادر شد.'

            ]);



        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);

        }

    }



   /**
     * 📂 واکشی اسناد عمومی ابلاغی به همراه بررسی وضعیت تایید تسک متصل (رفع باگ عدم نمایش فایل)
     */
    public function getSharedDocumentsForClient()
    {
        try {
            // 🎯 اصلاح شد: استخراج لید واقعی پرونده مهاجرتی کلاینت با متد اختصاصی شما
            $lead = $this->getAuthenticatedClientLead();
            if (!$lead) {
                return response()->json(['status' => 'error', 'message' => 'پرونده مهاجرتی شما یافت نشد.'], 404);
            }

            // واکشی لایو پیوند میان فایل‌های مخزن عمومی و تسک‌های ابلاغ شده برای این شخص
            $docs = DB::table('global_vault')
                ->join('next_tasks', 'global_vault.id', '=', 'next_tasks.global_doc_id')
                ->where('next_tasks.lead_id', $lead->id) // 🎯 مچ شدن دقیق بر روی آیدی لید پرونده
                ->select(
                    'global_vault.id',
                    'global_vault.title',
                    'global_vault.file_path',
                    'next_tasks.id as task_id',
                    'next_tasks.status as task_status'
                )
                ->orderBy('global_vault.id', 'desc')
                ->get()
                ->map(function($d) {
                    // قفل فایل تنها زمانی باز می‌شود که تسک مربوطه در حالت done باشد
                    $isUnlocked = ($d->task_status === 'done');

                    return [
                        'id' => $d->id,
                        'task_id' => $d->task_id,
                        'title' => $d->title,
                        // 🔒 گارد امنیتی: اگر تایید نکرده بود آدرس فایل تهی ارسال شود، اگر done بود لینک صادر شود
                        'url' => $isUnlocked ? asset('storage/' . $d->file_path) : null, 
                        'is_unlocked' => $isUnlocked
                    ];
                });

            return response()->json(['status' => 'success', 'data' => $docs]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function signDocumentByClient(Request $request, $id) {

        DB::table('shared_documents')->where('id', $id)->update([

            'is_signed_by_client' => 1,

            'client_signed_at' => now(),

            'client_ip' => $request->ip()

        ]);

        return response()->json(['status' => 'success', 'message' => '✓ رسید امضای دیجیتال فایل با موفقیت ثبت شد.']);

    }

    // app/Http/Controllers/Api/ClientPortalController.php

/**
 * 📤 آپلود فیش بانکی مکتوب برای فاکتور خاص
 */
public function uploadPaymentReceipt(Request $request, $invoiceId)
{
    $request->validate([
        'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:15360'
    ]);

    $lead = $this->getAuthenticatedClientLead();
    if (!$lead) return response()->json(['status' => 'error', 'message' => 'پرونده یافت نشد'], 404);

    $invoice = DB::table('client_invoices')->where('id', $invoiceId)->where('lead_id', $lead->id)->first();
    if (!$invoice) return response()->json(['status' => 'error', 'message' => 'فاکتور یافت نشد'], 404);

    $path = $request->file('file')->store("payment_receipts/{$lead->id}", 'public');

    DB::transaction(function () use ($invoiceId, $path, $invoice) {
        // ثبت در لاگ پرداخت‌ها
        DB::table('invoice_payments')->insert([
            'invoice_id' => $invoiceId,
            'gateway' => 'bank_receipt',
            'file_path' => $path,
            'amount_paid' => $invoice->amount,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // تغییر وضعیت فاکتور به در انتظار بررسی حسابدار
        DB::table('client_invoices')->where('id', $invoiceId)->update([
            'status' => 'pending_review',
            'updated_at' => now()
        ]);
    });

    return response()->json(['status' => 'success', 'message' => '✓ فیش پرداخت شما با موفقیت برای حسابداری ارسال شد.']);
}

/**
 * 💳 اتصال هوشمند و چنددرگاهی به بانک (Zarinpal & NextPay Multi-Gateway Hub)
 */
public function requestOnlinePayment(Request $request, $invoiceId)
{
    // ۱. ولیدیشن انتخاب درگاه (پیش‌فرض اگر فرستاده نشد زرین‌پال است)
    $request->validate([
        'gateway' => 'nullable|in:zarinpal,nextpay'
    ]);

    $gateway = $request->input('gateway', 'zarinpal');

    $lead = $this->getAuthenticatedClientLead();
    $invoice = DB::table('client_invoices')->where('id', $invoiceId)->where('lead_id', $lead->id)->first();
    
    if (!$invoice) {
        return response()->json(['status' => 'error', 'message' => 'فاکتور معتبر یافت نشد'], 404);
    }

    $amount = intval($invoice->amount); // به تومان

    // 🎯 سناریوی اول: شلیک به درگاه زرین‌پال
    if ($gateway === 'zarinpal') {
        $merchantId = "YOUR-ZARINPAL-MERCHANT-ID";
        $callbackUrl = "http://localhost:3000/client/payment-callback?invoice_id={$invoiceId}&gateway=zarinpal";

        $response = \Illuminate\Support\Facades\Http::post('https://api.zarinpal.com/pg/v4/payment/request.json', [
            'merchant_id' => $merchantId,
            'amount' => $amount,
            'description' => 'پرداخت آنلاین فاکتور #' . $invoice->invoice_number,
            'callback_url' => $callbackUrl,
        ]);

        $resData = $response->json();
        if (isset($resData['data']['code']) && $resData['data']['code'] == 100) {
            $authority = $resData['data']['authority'];
            
            // لاگ تراکنش با ثبت نوع درگاه
            DB::table('invoice_payments')->insert([
                'invoice_id' => $invoiceId,
                'gateway' => 'zarinpal',
                'authority_token' => $authority,
                'amount_paid' => $amount,
                'created_at' => now()
            ]);

            return response()->json([
                'status' => 'success',
                'payment_url' => 'https://www.zarinpal.com/pg/StartPay/' . $authority
              ]);
        }
    } 
    
    // 🎯 سناریوی دوم: شلیک به درگاه نکست‌پی (NextPay)
    if ($gateway === 'nextpay') {
        $apiKey = "YOUR-NEXTPAY-API-KEY";
        $callbackUrl = "http://localhost:3000/client/payment-callback?invoice_id={$invoiceId}&gateway=nextpay";

        $response = \Illuminate\Support\Facades\Http::post('https://nextpay.org/api/payment/token', [
            'api_key' => $apiKey,
            'amount' => $amount,
            'order_id' => $invoice->invoice_number,
            'callback_uri' => $callbackUrl,
        ]);

        $resData = $response->json();
        // در نکست‌پی کد ۱ نشان‌دهنده موفقیت و صدور توکن است
        if (isset($resData['code']) && $resData['code'] == -1) { 
            $transId = $resData['trans_id'];
            
            DB::table('invoice_payments')->insert([
                'invoice_id' => $invoiceId,
                'gateway' => 'nextpay',
                'authority_token' => $transId,
                'amount_paid' => $amount,
                'created_at' => now()
            ]);

            return response()->json([
                'status' => 'success',
                'payment_url' => 'https://nextpay.org/api/payment/start/' . $transId
            ]);
        }
    }

    return response()->json(['status' => 'error', 'message' => 'خطا در ارتباط با درگاه بانکی انتخاب شده یا مرچنت نامعتبر است.'], 500);
}


    /**

     * 🎫 ۴. واکشی تیکت‌های پشتیبانی کلاینت (حل باگ ۴۰۴)

     */

    public function getMyTickets()

    {

        $lead = $this->getAuthenticatedClientLead();

        if (!$lead) return response()->json(['status' => 'error', 'message' => 'پرونده یافت نشد'], 404);



        $tickets = DB::table('client_tickets')

            ->where('lead_id', $lead->id)

            ->orderBy('id', 'desc')

            ->get();



        return response()->json(['status' => 'success', 'data' => $tickets]);

    }



 /**

     * 🎫 ۳. ارسال تیکت جدید همراه با ضمیمه اسناد پشتیبان

     */

    public function storeTicket(Request $request)

    {

        $request->validate([

            'subject' => 'required|string|max:255',

            'description' => 'required|string',

            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240'

        ]);



        $lead = $this->getAuthenticatedClientLead();

        if (!$lead) return response()->json(['status' => 'error', 'message' => 'پرونده یافت نشد'], 404);



        $attachmentPath = null;

        if ($request->hasFile('file')) {

            $attachmentPath = $request->file('file')->store("ticket_attachments/{$lead->id}", 'public');

        }



        DB::table('client_tickets')->insert([

            'lead_id' => $lead->id,

            'department_id' => $request->department_id ?: 1,

            'subject' => $request->subject,

            'description' => $request->description,

            'status' => 'open',

            'priority' => 'medium',

            'attachment_path' => $attachmentPath, // 👈 ذخیره آدرس فایل تیکت

            'created_at' => now(),

            'updated_at' => now()

        ]);



        return response()->json(['status' => 'success', 'message' => 'تیکت دپارتمانی شما به همراه فایل پیوست صادر شد.']);

    }



    /**

     * 💬 واکشی کامل تاریخچه چت کلاینت لایو

     */

    public function getPortalChatHistory()

    {

        $lead = $this->getAuthenticatedClientLead();

        if (!$lead) return response()->json(['status' => 'error', 'message' => 'پرونده یافت نشد'], 404);



        $chats = DB::table('chat_logs')

            ->where('lead_id', $lead->id)

            ->orderBy('created_at', 'asc')

            ->get()

            ->map(function ($chat) {

                return [

                    'id' => $chat->id,

                    'sender' => $chat->sender_type, // user, agent, bot

                    'message' => $chat->message,

                    'time' => \Carbon\Carbon::parse($chat->created_at)->format('H:i'),

                    'date' => \Carbon\Carbon::parse($chat->created_at)->format('Y/m/d')

                ];

            });



        return response()->json(['status' => 'success', 'data' => $chats]);

    }



    /**

     * 💬 ارسال پیام چت جدید از سمت کلاینت

     */

    public function sendPortalChatMessage(Request $request)

    {

        $request->validate(['message' => 'required|string']);

        $lead = $this->getAuthenticatedClientLead();

        if (!$lead) return response()->json(['status' => 'error', 'message' => 'پرونده یافت نشد'], 404);



        DB::table('chat_logs')->insert([

            'lead_id' => $lead->id,

            'channel' => 'client_portal',

            'sender_type' => 'user',

            'message' => $request->message,

            'is_analyzed' => 0,

            'created_at' => now(),

            'updated_at' => now()

        ]);



        return response()->json(['status' => 'success', 'message' => 'پیام شما فورا به کارتابل مشاور ارسال شد.']);

    }



    /**
     * 🔏 تایید و اتمام تسک توسط خود کلاینت جهت باز شدن قفل سند عمومی (رفع خطای ۴۰۴ کلاینت)
     */
    public function completeTaskByClient($taskId) 
{
    try {
        $lead = $this->getAuthenticatedClientLead();
        if (!$lead) {
            return response()->json(['status' => 'error', 'message' => 'پرونده یافت نشد'], 404);
        }

        DB::table('next_tasks')
            ->where('id', $taskId)
            ->where('lead_id', $lead->id)
            ->update([
                'status' => 'done',
                'completed_at' => now(), // 🎯 پلمب خودکار زمان اتمام تسک کلاینت
                'updated_at' => now()
            ]);

        return response()->json(['status' => 'success', 'message' => '✓ اقدام با موفقیت تایید و تکمیل شد.']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}


    /**

     * 📋 واکشی کامنت‌های یک تسک خاص

     */

    public function getTaskComments($taskId)

    {

        $comments = DB::table('task_comments')

            ->where('task_id', $taskId)

            ->orderBy('created_at', 'asc')

            ->get();



        return response()->json(['status' => 'success', 'data' => $comments]);

    }



    /**

     * 📋 ثبت کامنت جدید روی تسک از سمت کلاینت

     */

    public function storeTaskComment(Request $request, $taskId)

    {

        $request->validate(['comment' => 'required|string']);

        $user = auth()->user();



        DB::table('task_comments')->insert([

            'task_id' => $taskId,

            'user_id' => $user->id,

            'comment' => $request->comment,

            'sender_name' => $user->name,

            'created_at' => now(),

            'updated_at' => now()

        ]);



        return response()->json(['status' => 'success', 'message' => 'فیدبک شما روی این وظیفه ثبت شد.']);

    }



    /**

     * 📂 ۱. واکشی لیست تمام مدارک آپلود شده کلاینت به همراه وضعیت تایید

     */

    public function getMyDocuments()

    {

        $lead = $this->getAuthenticatedClientLead();

        if (!$lead) return response()->json(['status' => 'error', 'message' => 'پرونده یافت نشد'], 404);



        $documents = DB::table('client_documents')

            ->where('lead_id', $lead->id)

            ->orderBy('id', 'desc')

            ->get()

            ->map(function($doc) {

                return [

                    'id' => $doc->id,

                    'document_type' => $doc->document_type,

                    'title' => $doc->title,

                    'file_url' => asset('storage/' . $doc->file_path),

                    'status' => $doc->status, // pending_review, approved, rejected

                    'uploaded_by' => $doc->uploaded_by,

                    'rejection_reason' => $doc->rejection_reason,

                    'created_at' => \Carbon\Carbon::parse($doc->created_at)->format('Y/m/d H:i')

                ];

            });



        return response()->json(['status' => 'success', 'data' => $documents]);

    }



    /**

     * 📂 ۲. آپلود مدرک جدید توسط کلاینت در Storage امن لاراول

     */

    public function uploadDocument(Request $request)

    {

        $request->validate([

            'document_type' => 'required|string', // passport, degree, resume, contract, etc.

            'title' => 'required|string|max:255',

            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // حد مجاز ۱۰ مگابایت

        ]);



        $lead = $this->getAuthenticatedClientLead();

        if (!$lead) return response()->json(['status' => 'error', 'message' => 'پرونده یافت نشد'], 404);



        if ($request->hasFile('file')) {

            // ذخیره فایل در مسیر امن storage/app/public/client_docs/{lead_id}

            $filePath = $request->file('file')->store("client_docs/{$lead->id}", 'public');



            DB::table('client_documents')->insert([

                'lead_id' => $lead->id,

                'document_type' => $request->document_type,

                'title' => $request->title,

                'file_path' => $filePath,

                'status' => 'pending_review',

                'uploaded_by' => 'client',

                'created_at' => now(),

                'updated_at' => now()

            ]);



            return response()->json(['status' => 'success', 'message' => 'مدرک شما با موفقیت در خزانه اسناد آپلود شد و در صف بررسی وکیل قرار گرفت.']);

        }



        return response()->json(['status' => 'error', 'message' => 'فایلی دریافت نشد.'], 400);

    }



    /**

     * 📚 واکشی پکیج مقالات و چک‌لیست‌های دانشنامه بر اساس پروفایل کلاینت

     */

    public function getPortalKnowledgeBase()

    {

        $lead = $this->getAuthenticatedClientLead();

        if (!$lead) return response()->json(['status' => 'error', 'message' => 'پرونده یافت نشد'], 404);



        // واکشی لایو تمام مقالات فعال سیستم

        $articles = DB::table('knowledge_bases')

            ->where('is_active', 1)

            ->orderBy('id', 'desc')

            ->get()

            ->map(function($post) {

                return [

                    'id' => $post->id,

                    'title' => $post->title,

                    'category' => $post->category, // faq, general

                    'content' => $post->content,

                    'file_url' => $post->file_path ? asset('storage/' . $post->file_path) : null,

                    'updated_at' => \Carbon\Carbon::parse($post->updated_at)->format('Y/m/d')

                ];

            });



        return response()->json([

            'status' => 'success',

            'target_country' => $lead->target_country ?? 'آلمان',

            'data' => $articles

        ]);

    }

   

}


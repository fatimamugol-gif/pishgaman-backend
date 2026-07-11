<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema; // 🎯 حیاتی: برای جلوگیری از کرش ۵۰۰ در Schema::hasTable
use Carbon\Carbon; // 🎯 حیاتی: برای فرمت‌سازی تاریخ‌ها

class StaffManagerController extends Controller
{
    /**
     * 🎫 ۱. واکشی جامع تیکت‌ها - نسخه VIP ضدگلوله (Bypass ادمین و مدیر ارشد)
     */
    public function getAllClientTickets()
    {
        try {
            $user = auth()->user(); 

            // ۱. کوئری اصلی و پایه
            $query = DB::table('client_tickets')
                ->leftJoin('leads', 'client_tickets.lead_id', '=', 'leads.id')
                ->leftJoin('next_departments', 'client_tickets.department_id', '=', 'next_departments.id')
                ->select(
                    'client_tickets.*', 
                    DB::raw('COALESCE(leads.name, "متقاضی ناشناس") as client_name'), 
                    'leads.target_country',
                    DB::raw('COALESCE(next_departments.name, "پشتیبانی عمومی") as department_name')
                );

            // گارد تفکیک کارتابل فقط برای کارشناسان عادی
            if ($user && $user->role === 'agent') {
                if (!empty($user->department_id)) {
                    $query->where('client_tickets.department_id', $user->department_id);
                }
            }

            $tickets = $query->orderBy('client_tickets.id', 'desc')
                ->get()
                ->map(function($ticket) {
                    
                    $messages = Schema::hasTable('ticket_messages')
                        ? DB::table('ticket_messages')->where('ticket_id', $ticket->id)->orderBy('id', 'asc')->get()
                            ->map(fn($msg) => [
                                'id' => $msg->id,
                                'body' => $msg->body,
                                'admin_id' => $msg->sender_type === 'staff' ? $msg->user_id : null,
                                'client_id' => $msg->sender_type === 'client' ? $msg->user_id : null,
                                'sender_name' => $msg->sender_name ?? 'کاربر',
                                'created_at' => isset($msg->created_at) ? Carbon::parse($msg->created_at)->toIso8601String() : now()->toIso8601String()
                            ])->toArray()
                        : [];

                    if (empty($messages)) {
                        $messages[] = [
                            'id' => 0,
                            'body' => $ticket->description ?? 'بدون توضیحات اولیه',
                            'admin_id' => null,
                            'client_id' => $ticket->lead_id,
                            'sender_name' => $ticket->client_name,
                            'created_at' => $ticket->created_at ? Carbon::parse($ticket->created_at)->toIso8601String() : now()->toIso8601String()
                        ];

                        if (!empty($ticket->reply)) {
                            $messages[] = [
                                'id' => 9999,
                                'body' => $ticket->reply,
                                'admin_id' => 1,
                                'client_id' => null,
                                'sender_name' => 'پاسخ کارشناس',
                                'created_at' => $ticket->updated_at ? Carbon::parse($ticket->updated_at)->toIso8601String() : now()->toIso8601String()
                            ];
                        }
                    }

                    return [
                        'id' => $ticket->id,
                        'subject' => $ticket->subject,
                        'status' => $ticket->status,
                        'priority' => $ticket->priority,
                        'department_id' => $ticket->department_id,
                        'department_name' => $ticket->department_name ?? 'پشتیبانی عمومی', 
                        'attachment_url' => $ticket->attachment_path ? asset('storage/' . $ticket->attachment_path) : null,
                        'client' => [
                            'id' => $ticket->lead_id,
                            'name' => $ticket->client_name,
                        ],
                        'messages' => $messages
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $tickets->values()->toArray()
            ]);

        } catch (\Exception $e) {
            \Log::error("🚨 [getAllClientTickets Critical Error]: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 📋 ۲. حذف فیزیکی تسک کلاینت توسط کارشناس
     */
    public function destroyClientTask($id)
    {
        DB::table('next_tasks')->where('id', $id)->delete();
        return response()->json(['status' => 'success', 'message' => 'وظیفه با موفقیت از کارتابل حذف شد.']);
    }

    /**
     * 🎫 ۲. ثبت پاسخ جدید کارشناس
     */
    public function replyToTicket(Request $request, $ticketId)
    {
        $request->validate(['reply' => 'required|string']);
        $user = auth()->user();

        try {
            $ticket = DB::table('client_tickets')->where('id', $ticketId)->first();
            if (!$ticket) return response()->json(['status' => 'error', 'message' => 'تیکت یافت نشد'], 404);

            if (Schema::hasTable('ticket_messages')) {
                DB::table('ticket_messages')->insert([
                    'ticket_id' => $ticketId,
                    'user_id' => $user->id,
                    'sender_type' => 'staff',
                    'sender_name' => $user->name,
                    'body' => $request->reply,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } else {
                DB::table('client_tickets')->where('id', $ticketId)->update([
                    'reply' => $request->reply,
                    'updated_at' => now()
                ]);
            }

            DB::table('client_tickets')->where('id', $ticketId)->update(['status' => 'answered']);

            return response()->json(['status' => 'success', 'message' => 'پاسخ کارشناس ثبت شد.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 📋 ۳. ایجاد وظیفه جدید برای کلاینت
     */
    public function createNewClientTask(Request $request)
    {
        $request->validate([
            'lead_id' => 'required|integer',
            'task_title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date_shamsi' => 'nullable|string',
            'due_date_shamsi' => 'required|string',
            'priority' => 'required|string|in:low,medium,high',
            'has_reminder' => 'nullable|integer',
            'reminder_time_shamsi' => 'nullable|string'
        ]);

        try {
            DB::table('next_tasks')->insert([
                'lead_id' => $request->lead_id,
                'task_title' => $request->task_title,
                'description' => $request->description,
                'start_date_shamsi' => $request->start_date_shamsi ?? date('Y/m/d'),
                'due_date_shamsi' => $request->due_date_shamsi,
                'priority' => $request->priority,
                'status' => 'pending',
                'target_audience' => 'client',
                'has_reminder' => $request->has_reminder ?? 0,
                'reminder_time_shamsi' => $request->reminder_time_shamsi,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json(['status' => 'success', 'message' => 'کارت وظیفه با موفقیت صادر و ابلاغ شد.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 📚 ۴. افزودن یا به‌روزرسانی قوانین پایگاه دانش
     */
    public function upsertKnowledgeBase(Request $request)
    {
        $request->validate([
            'id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'content' => 'required|string',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:15360'
        ]);

        $data = [
            'title' => $request->title,
            'category' => $request->category,
            'content' => $request->content,
            'is_active' => 1,
            'updated_at' => now()
        ];

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('knowledge_attachments', 'public');
        }

        if ($request->id) {
            DB::table('knowledge_bases')->where('id', $request->id)->update($data);
            $msg = 'مقاله دانشنامه با موفقیت ویرایش شد.';
        } else {
            $data['created_at'] = now();
            DB::table('knowledge_bases')->insert($data);
            $msg = 'مقاله جدید با موفقیت اضافه شد.';
        }

        return response()->json(['status' => 'success', 'message' => $msg]);
    }

   /**
     * 📋 واکشی آرشیو تمام تسک‌های صادر شده (تزریق فیلد کلیدی global_doc_id)
     */
    public function getAllClientTasks()
    {
        try {
            if (!Schema::hasTable('next_tasks')) {
                return response()->json(['status' => 'success', 'data' => []]);
            }

            $tasks = DB::table('next_tasks')
                ->leftJoin('leads', 'next_tasks.lead_id', '=', 'leads.id')
                // 🎯 اصلاح شد: افزودن فیلد next_tasks.global_doc_id به بخش select دیتابیس
                ->select('next_tasks.*', 'next_tasks.global_doc_id', DB::raw('COALESCE(leads.name, "متقاضی رسمی") as lead_name'), 'leads.phone as lead_phone')
                ->where('next_tasks.target_audience', 'client')
                ->orderBy('next_tasks.id', 'desc')
                ->get()
                ->map(function($task) {
                    
                    $attachments = Schema::hasTable('task_attachments') 
                        ? DB::table('task_attachments')->where('task_id', $task->id)->get()->map(fn($att) => [
                            'id' => $att->id,
                            'name' => $att->file_name,
                            'url' => asset('storage/' . $att->file_path),
                            'by' => $att->uploaded_by
                          ])->toArray()
                        : [];

                    $comments = Schema::hasTable('task_comments')
                        ? DB::table('task_comments')->where('task_id', $task->id)->orderBy('id', 'asc')->get()->map(fn($comm) => [
                            'id' => $comm->id ?? rand(1,999),
                            'comment' => $comm->comment,
                            'sender_name' => $comm->sender_name ?? 'کاربر سیستم',
                            'created_at' => isset($comm->created_at) ? Carbon::parse($comm->created_at)->toIso8601String() : now()->toIso8601String()
                          ])->toArray()
                        : [];

                    return [
                        'id' => $task->id,
                        'lead_id' => $task->lead_id,
                        'global_doc_id' => $task->global_doc_id, // 🎯 الحاق قطعی به خروجی مپ داده‌ای جهت فرانت
                        'task_title' => $task->task_title,
                        'description' => $task->description ?? '',
                        'start_date_shamsi' => $task->start_date_shamsi ?? '',
                        'due_date_shamsi' => $task->due_date_shamsi ?? '',
                        'priority' => $task->priority ?? 'medium',
                        'status' => $task->status ?? 'pending',
                        'lead_name' => $task->lead_name,
                        'attachments' => $attachments,
                        'comments' => $comments
                    ];
                });

            return response()->json(['status' => 'success', 'data' => $tasks]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // app/Http/Controllers/Api/StaffManagerController.php

/**
 * 👑 صدور فاکتور جدید + ماژول هوشمند تقسیط خودکار قراردادها (حل گارد فیلد amount_toman)
 */
public function generateInvoiceOrInstallments(Request $request)
{
    $request->validate([
        'lead_id' => 'required|integer',
        'title' => 'required|string|max:255',
        'total_amount' => 'required|numeric',
        'payment_method' => 'required|in:full,installment',
        'installments_count' => 'nullable|integer|min:2',
        'gap_days' => 'nullable|integer',
        'base_due_timestamp' => 'required|integer'
    ]);

    try {
        DB::beginTransaction();

        $leadId = $request->lead_id;
        $totalAmount = $request->total_amount;
        $baseTimestamp = intval($request->base_due_timestamp);

        if ($request->payment_method === 'full') {
            // صدور یک فاکتور نقدی یکباره
            DB::table('client_invoices')->insert([
                'lead_id' => $leadId,
                'invoice_number' => 'INV-' . time() . '-' . rand(10,99),
                'title' => $request->title,
                'amount' => $totalAmount,
                'amount_toman' => $totalAmount, // 🎯 تزریق هم‌زمان جهت فرار از ارور 1364 دیتابیس قدیمی
                'due_timestamp' => $baseTimestamp,
                'payment_type' => 'full',
                'status' => 'unpaid',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            // 📊 شکستن قرارداد به N قسط مساوی
            $count = $request->installments_count;
            $installmentAmount = $totalAmount / $count;
            $gapDays = $request->gap_days ?? 30;
            $secondsInDay = 24 * 60 * 60;

            for ($i = 1; $i <= $count; $i++) {
                $currentDueTimestamp = $baseTimestamp + (($i - 1) * $gapDays * $secondsInDay);

                DB::table('client_invoices')->insert([
                    'lead_id' => $leadId,
                    'invoice_number' => 'INST-' . $i . '-' . time() . '-' . rand(10,99),
                    'title' => $request->title . " (قسط {$i} از {$count})",
                    'amount' => $installmentAmount,
                    'amount_toman' => $installmentAmount, // 🎯 تزریق هم‌زمان جهت فرار از ارور 1364 دیتابیس قدیمی
                    'due_timestamp' => $currentDueTimestamp,
                    'payment_type' => 'installment',
                    'status' => 'unpaid',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        DB::commit();
        return response()->json(['status' => 'success', 'message' => '✓ فرآیند ساخت و تقسیط مالی کلاینت با موفقیت صادر شد.']);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}
/**
 * 🏦 پایش، تایید نهایی یا رد فیش واریزی کلاینت توسط حسابدار
 */
public function reviewClientReceipt(Request $request, $invoiceId)
{
    $request->validate([
        'action' => 'required|in:approve,reject',
        'reject_reason' => 'nullable|string'
    ]);

    $status = $request->action === 'approve' ? 'paid' : 'rejected';

    DB::table('client_invoices')->where('id', $invoiceId)->update([
        'status' => $status,
        'reject_reason' => $request->reject_reason,
        'updated_at' => now()
    ]);

    $msg = $request->action === 'approve' ? '✓ فیش بانکی تایید و فاکتور تسویه شد.' : '❌ فیش بانکی رد شد و جهت اصلاح به کارتابل کلاینت عودت گردید.';
    return response()->json(['status' => 'success', 'message' => $msg]);
}

    /**
     * 📚 واکشی تمام مقالات دانشنامه
     */
    public function getAllKnowledgeArticles()
    {
        try {
            $articles = DB::table('knowledge_bases')->orderBy('id', 'desc')->get();
            return response()->json(['status' => 'success', 'data' => $articles]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 💬 ثبت کامنت جدید توسط کارشناس
     */
    public function storeTaskCommentByStaff(Request $request, $taskId)
    {
        $request->validate(['comment' => 'required|string']);
        $user = auth()->user();

        try {
            DB::table('task_comments')->insert([
                'task_id' => $taskId,
                'user_id' => $user->id,
                'comment' => $request->comment,
                'sender_name' => $user->name . ' (مشاور)',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json(['status' => 'success', 'message' => 'کامنت مشاور با موفقیت ثبت شد.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
 * 📋 واکشی کلاینت‌های رسمی (مچ شده با ساختار دیتای کامپوننت حسابداری)
 */
public function getOfficialClientsOnly()
{
    try {
        $clients = DB::table('leads')
            ->select('id', 'name', 'phone')
            ->where('status', 'official_client') 
            ->orderBy('name', 'asc')
            ->get();

        // 🎯 کپسوله‌سازی خروجی در آرایه data جهت مچ شدن با فرانت‌آند Next.js
        return response()->json(['status' => 'success', 'data' => $clients]);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}

    /**
     * 📚 حذف مستند پایگاه دانش
     */
    public function destroyKnowledgeArticle($id)
    {
        try {
            DB::table('knowledge_bases')->where('id', $id)->delete();
            return response()->json(['status' => 'success', 'message' => 'مستند حذف گردید.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 🔏 اشتراک‌گذاری سند از مخزن عمومی
     */
    public function uploadSharedDocByStaff(Request $request) {
        $request->validate([
            'lead_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,zip,rar|max:30000'
        ]);
        
        $path = $request->file('file')->store('shared_vault', 'public');
        
        DB::table('shared_documents')->insert([
            'lead_id' => $request->lead_id,
            'title' => $request->title,
            'file_path' => $path,
            'is_approved_by_staff' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        return response()->json(['status' => 'success', 'message' => 'فایل به اشتراک گذاشته شد.']);
    }

    public function uploadGlobalDoc(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,zip,rar|max:30000'
        ]);

        try {
            $path = $request->file('file')->store('global_vault', 'public');
            
            $id = DB::table('global_vault')->insertGetId([
                'title' => $request->title,
                'file_path' => $path,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json(['status' => 'success', 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getGlobalDocs()
    {
        $docs = DB::table('global_vault')->orderBy('id', 'desc')->get();
        return response()->json(['status' => 'success', 'data' => $docs]);
    }

    public function assignGlobalDocToClient(Request $request)
    {
        $request->validate([
            'global_doc_id' => 'required|integer',
            'lead_id' => 'required|integer'
        ]);

        try {
            DB::beginTransaction();

            $doc = DB::table('global_vault')->where('id', $request->global_doc_id)->first();
            if (!$doc) return response()->json(['status' => 'error', 'message' => 'سند یافت نشد'], 404);

            DB::table('next_tasks')->insert([
                'lead_id' => $request->lead_id,
                'global_doc_id' => $request->global_doc_id,
                'task_title' => "🔏 تایید دریافت و مطالعه: " . $doc->title,
                'description' => "متقاضی گرامی، لطفاً پس از مطالعه کامل این سند رسمی، این کارت وظیفه را جهت باز شدن قفل فایل تایید و تکمیل فرمایید.",
                'start_date_shamsi' => date('Y/m/d'),
                'due_date_shamsi' => date('Y/m/d', strtotime('+5 days')),
                'priority' => 'high',
                'status' => 'pending',
                'target_audience' => 'client',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

/**
 * 📊 واکشی تمام فاکتورها و اقساط سیستم به همراه نام لید جهت میز مانیتورینگ حسابداری
 */
public function getAllInvoicesForStaff()
{
    try {
        // 🎯 فیکس شد: اصلاح "client_invoices::lead_id" به "client_invoices.lead_id"
        $invoices = DB::table('client_invoices')
            ->leftJoin('leads', 'client_invoices.lead_id', '=', 'leads.id')
            ->select(
                'client_invoices.*',
                DB::raw('COALESCE(leads.name, "متقاضی رسمی") as lead_name')
            )
            ->orderBy('client_invoices.id', 'desc')
            ->get()
            ->map(function($inv) {
                // پیدا کردن فیش بانکی آپلود شده برای این فاکتور در صورت وجود
                $payment = DB::table('invoice_payments')
                    ->where('invoice_id', $inv->id)
                    ->where('gateway', 'bank_receipt')
                    ->orderBy('id', 'desc')
                    ->first();

                return [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'title' => $inv->title,
                    'amount' => floatval($inv->amount),
                    'due_timestamp' => $inv->due_timestamp ?? time(),
                    'payment_type' => $inv->payment_type,
                    'status' => $inv->status,
                    'reject_reason' => $inv->reject_reason,
                    'lead_name' => $inv->lead_name,
                    'file_path' => $payment ? $payment->file_path : null
                ];
            });

        // 🎯 کپسوله‌سازی استاندارد خروجی به صورت آرایه data برای خوانش Next.js
        return response()->json(['status' => 'success', 'data' => $invoices]);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}
}
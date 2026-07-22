<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ایمپورت یکدست و استاندارد کنترلرها
use App\Http\Controllers\WebhookTestController;
use App\Http\Controllers\Api\PerfexWebhookController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\InstagramWebhookController;
use App\Http\Controllers\Api\VoIpWebhookController;
use App\Services\OkSolarKnowledgeService;
use App\Http\Controllers\Api\NextDashboardController;
use App\Http\Controllers\Api\NextCoreController;
use App\Http\Controllers\Api\StaffManagerController;
use App\Http\Controllers\Api\ClientPortalController;
use App\Http\Controllers\CallLogController;
use App\Http\Controllers\Api\HrManagementController;
use App\Http\Controllers\Api\PayrollController;
use Illuminate\Support\Facades\Artisan;
use App\Services\NotificationService;
/*
|--------------------------------------------------------------------------
| ۱. روت‌های عمومی (Public Routes)
|--------------------------------------------------------------------------
*/

Route::get('/next/test-notification', function () {
    return redirect()->to('/api/next/test-universal-hub');
});
Route::get('/next/test-universal-hub', function () {
    $notifier = new NotificationService();

    $targets = [
        'phone'            => '09100816547',
        'email'            => 'm.r.shahbazi1991@gmail.com',
        'telegram_chat_id' => '987654321',
        'fcm_token'        => 'FCM_DEVICE_TOKEN_FROM_BROWSER', 
    ];

    $payload = [
        'title'        => '🚀 پلمب نهایی کورتکس Universal Notification',
        'body'         => 'مهندس جان، تمامی مجاری ارتباطی سیستم (SMS, Email, Telegram, WhatsApp, FCM) با موفقیت به هسته متمرکز متصل شدند.',
        'click_action' => '/dashboard/leaves'
    ];

    // شلیک اتمیک و همزمان به ۵ کانال ارتباطی لایو سازمان
    $status = $notifier->send($targets, $payload, ['sms', 'email', 'telegram', 'whatsapp', 'firebase']);

    return response()->json([
        'status'  => 'success',
        'message' => '🛰️ پکت جامع به تمام وب‌سرویس‌های جهانی مخابراتی و گوگل شلیک شد.',
        'results' => $status
    ]);
});
Route::post('/ok-solar/knowledge/ask', function (Request $request, OkSolarKnowledgeService $solarService) {
    $request->validate([
        'question' => 'required|string',
        'category' => 'nullable|string'
    ]);
    return response()->json($solarService->askSolarAgent($request->question, $request->category));
});

    Route::post('/next/auth/otp/request', [NextCoreController::class, 'requestOtp']);
    Route::post('/next/auth/otp/verify', [NextCoreController::class, 'verifyOtp']);

    Route::post('/next/webhook/site-leads', [NextCoreController::class, 'handleWebsiteWebhook']);

// 🎯 اسکریپت رادار کمکی: انتقال و سینک خودکار کارشناسان جامانده به جدول agents
    // Route::get('/next/sys/sync-agents', function() {
    //     try {
    //         $users = DB::table('users')->where('role', '!=', 'client')->get();
    //         $syncedCount = 0;

    //         foreach ($users as $user) {
    //             $exists = DB::table('agents')->where('email', $user->email)->exists();
    //             if (!$exists) {
    //                 $safePerfexId = (int)('178' . $user->id);
    //                 DB::table('agents')->insert([
    //                     'perfex_staff_id' => $safePerfexId,
    //                     'name' => $user->name,
    //                     'email' => $user->email,
    //                     'voip_extension' => $user->voip_extension,
    //                     'role' => ($user->role === 'supervisor') ? 'supervisor' : 'call_center',
    //                     'department_id' => $user->department_id ?: 1,
    //                     'is_active' => 1,
    //                     'created_at' => now(),
    //                     'updated_at' => now()
    //                 ]);
    //                 $syncedCount++;
    //             }
    //         }
    //         return response()->json(['status' => 'success', 'message' => "تعداد {$syncedCount} کارشناس جامانده با موفقیت به کارتابل مخابراتی دشبورد منتقل شدند."]);
    //     } catch (\Exception $e) {
    //         return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
    //     }
    // });

    // 🎯 روت پلمب‌شده برای پاکسازی اتمیک کل کش‌های سیستم در زمان اجرا
Route::get('/system/clear-everything', function () {
    // گارد امنیتی جزیی: می‌توانی چک کنی که فقط ادمین یا لوکال‌هاست بازش کند
    if (app()->environment('production') && request()->get('key') !== 'pishgamanVIP77') {
        return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
    }

    try {
        // لایروبی تجمیعی تمام بافرهای کامپایل شده لاراول
        Artisan::call('optimize:clear');
        
        // پاکسازی کش دیتابیس و سایر درایورهای متصل (Redis/Memcached)
        Artisan::call('cache:clear');

        return response()->json([
            'status' => 'success',
            'message' => '🚀 کورتکس دیتابیس، کش، روت‌ها و ویوهای پروژه با موفقیت در زمان اجرا لایروبی و پلمب شدند!'
        ]);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// ورود متمرکز کاربران و کارشناسان
Route::post('/next/auth/login', [NextCoreController::class, 'login'])->name('login');

Route::post('/next/session-report/trigger', [NextDashboardController::class, 'triggerSessionDeadline']);
Route::post('/next/session-report/submit/{id}', [NextDashboardController::class, 'submitSessionReport']);

Route::get('/next/session-reports/archive', [NextDashboardController::class, 'getAllSessionReports']);

// وب‌هوک‌های ثالث مخابرات و شبکه‌های اجتماعی
Route::post('/v1/webhook/telegram', [TelegramWebhookController::class, 'handle']);
Route::post('/v1/webhook/instagram', [InstagramWebhookController::class, 'handle']);
Route::post('/webhook/voip/call-log', [VoIpWebhookController::class, 'handleCallLog']);

// وب‌هوک‌های سیستم Perfex CRM
Route::post('/perfex/webhook', [PerfexWebhookController::class, 'handle']);
Route::post('/perfex-webhook-test', [WebhookTestController::class, 'handleTestWebhook']);

// روت‌های عمومی مورد نیاز فرانت (بازگشت دقیق نام متد getDepartments شما جهت رفع ۵۰۰)
Route::get('/next/departments', [NextCoreController::class, 'getDepartments']);
Route::get('/next/agents/voip-status', [NextDashboardController::class, 'getAgentsVoipStatus']);
Route::get('/next/supervisor/reports', [NextDashboardController::class, 'getSupervisorReports']);
Route::get('/next/senior-consultants', [NextDashboardController::class, 'getSeniorConsultants']);
Route::get('/next/initial-consultants', [NextDashboardController::class, 'getInitialConsultants']);

/*
|--------------------------------------------------------------------------
| ۲. دژ مستحکم سانکتوم (مخصوص کارشناسان و کارتابل‌ها)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // دریافت اطلاعات کارشناس لاگین شده
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // مانیتورینگ تماس‌ها و مرکز تلفن پیشگامان (VoIP Dashboard)
    Route::get('/next/dashboard/agents-voip', [NextDashboardController::class, 'getAgentsVoipStatus']);
    Route::get('/next/dashboard/live-popup', [NextDashboardController::class, 'checkLivePopup']);
    Route::get('/next/dashboard/active-calls', [NextDashboardController::class, 'getActiveCalls']);
    Route::get('/next/agent/dashboard-hub', [NextDashboardController::class, 'getAgentDashboardHub']);
    Route::post('/next/voip/click-to-dial', [NextDashboardController::class, 'clickToDial']);
    Route::get('/next/voip/logs', [CallLogController::class, 'index']);

    Route::post('/next/hr/leaves/store', [HrManagementController::class, 'submitLeaveRequest']);
    Route::get('/next/hr/leaves/history', [HrManagementController::class, 'getLeavesHistory']);
    Route::post('/next/hr/leaves/review/{id}', [HrManagementController::class, 'reviewLeaveRequest']);
    // 🎯 اندپویننت‌های سیستم حضور و غیاب تایمری و شیفت‌های کاری (Attendance Core)
    Route::post('/next/hr/attendance/toggle', [HrManagementController::class, 'toggleClock']);
    Route::get('/next/hr/attendance/status', [HrManagementController::class, 'getClockStatus']);
    Route::post('/next/hr/admin/store-holiday', [HrManagementController::class, 'storeHoliday']);
    Route::post('/next/hr/admin/store-shift', [HrManagementController::class, 'storeShift']);
    Route::get('/next/hr/admin/config-list', [HrManagementController::class, 'getShiftsAndHolidays']);
    Route::post('/next/hr/admin/update-limit', [HrManagementController::class, 'updateCustomLeaveLimit']);
    Route::get('/next/hr/admin/all-attendance', [HrManagementController::class, 'getAllStaffAttendanceLogs']);
    Route::post('/next/hr/admin/update-attendance', [NextCoreController::class, 'updateManualAttendance']);
    
    // 🔄 سینک خودکار با دستگاه فاراتکنو
    Route::post('/api/attendance/sync', [HrManagementController::class, 'syncWithFaratechnoDevice']);
    Route::get('/api/attendance/last-sync', [HrManagementController::class, 'getLastSyncDate']);

    // 🎯 سیستم جبران تاخیر (Delay Compensation System)
    Route::get('/next/delay-compensation/rules', [NextCoreController::class, 'getDelayCompensationRules']);
    Route::post('/next/delay-compensation/rules', [NextCoreController::class, 'storeDelayCompensationRule']);
    // به این:
    Route::put('/next/delay-compensation/rules/{id}', [NextCoreController::class, 'updateDelayCompensationRule']);
    // و همچنین برای پشتیبانی از PATCH (اختیاری)
    Route::patch('/next/delay-compensation/rules/{id}', [NextCoreController::class, 'updateDelayCompensationRule']);    
    Route::delete('/next/delay-compensation/rules/{id}', [NextCoreController::class, 'destroyDelayCompensationRule']);
    Route::post('/next/delay-compensation/process', [NextCoreController::class, 'processAttendanceDelay']);
    Route::post('/next/delay-compensation/record', [NextCoreController::class, 'recordCompensationCompleted']);
    Route::get('/next/delay-compensation/user', [NextCoreController::class, 'getUserDelayCompensations']);
    Route::get('/next/delay-compensation/all', [NextCoreController::class, 'getAllDelayCompensations']);

    Route::get('/next/payrolls', [PayrollController::class, 'getPayrolls']);
    Route::post('/next/payrolls', [PayrollController::class, 'storeOrUpdate']);
    

    // دپارتمان‌ها و قوانین فلوچارت ارجاع (NextCoreController)
    Route::post('/next/workflows/assign-agent', [NextCoreController::class, 'assignAgentToDepartment']);
    Route::post('/next/workflows/store', [NextCoreController::class, 'storeWorkflow']);
    Route::get('/next/workflows/fetch/{departmentId}', [NextCoreController::class, 'getWorkflow']);

    // مدیریت و داده‌کاوی متقاضیان (Leads CRUD)
    Route::get('/next/dashboard/leads', [NextDashboardController::class, 'getLeadsForDashboard']);
    Route::post('/next/leads/store', [NextCoreController::class, 'storeLead']);
    // Route::put('/next/leads/update/{id}', [NextCoreController::class, 'updateNextLead']); // بازگشت متد بومی شما
    Route::post('/next/leads/update/{id}', [NextCoreController::class, 'updateNextLead']);
    Route::delete('/next/leads/delete/{id}', [NextCoreController::class, 'destroyNextLead']); // بازگشت متد بومی شما
    Route::get('/next/leads/sources', [NextCoreController::class, 'getLeadSources']);
    Route::post('/next/leads/call-outcome', [NextDashboardController::class, 'submitCallOutcome']);
    Route::get('/next/leads/{leadId}/call-logs', [NextDashboardController::class, 'getLeadCallLogs']);
    Route::post('/next/leads/link-secondary-phone', [NextCoreController::class, 'linkSecondaryPhone']);
    Route::post('/next/leads/convert-to-client', [NextCoreController::class, 'convertLeadToClient']);
    Route::post('/next/leads/merge', [NextCoreController::class, 'mergeLeads']);
    Route::post('/next/leads/{id}/recalculate-score', [NextDashboardController::class, 'recalculateScore']);
    Route::get('/next/leads/detail/{id}', [NextDashboardController::class, 'getLeadDetailsForFront']); // بازگشت متد بومی شما
    Route::post('/next/leads/store-chat', [NextCoreController::class, 'storeManualChatMessage']);
    Route::post('/next/leads/update-inline/{id}', [NextDashboardController::class, 'updateInlineField']);
    Route::post('/next/leads/store-event/{id}', [NextDashboardController::class, 'storeLeadEvent']);
    Route::get('/next/dashboard/senior-consultants', [NextDashboardController::class, 'getSeniorConsultants']);
    Route::post('/next/leads/submit-session-report/{taskId}', [NextDashboardController::class, 'submitSessionReport']);
    Route::post('next/leads/schedule-senior-session/{id}', [NextDashboardController::class, 'scheduleSeniorConsultation']);

    Route::post('/next/leads/update-persona/{id}', [NextDashboardController::class, 'updateLeadPersona']);
    Route::post('/next/leads/store-summary/{id}', [NextDashboardController::class, 'storeCallSummary']);

    Route::post('/next/leads/{id}/recalculate-score', [NextDashboardController::class, 'recalculateScore']);
    Route::post('/next/leads/store-chat', [NextDashboardController::class, 'storeManualChat']);

    

    // مدیریت کاربران و پرمیشن‌ها (بازگشت دقیق نام متدهای بومی شما جهت رفع ارور ۵۰۰)
    Route::get('/next/users', [NextCoreController::class, 'getUsersList']);
    Route::post('/next/users/store', [NextCoreController::class, 'storeUser']);
    // Route::put('/next/users/update/{id}', [NextCoreController::class, 'updateUserComplete']);
    // 🎯 تغییر یافت و پلمب شد: تبدیل متد از PUT به POST جهت همگام‌سازی با ساختار فرانت‌آند Next.js
    Route::post('/next/users/update/{id}', [NextCoreController::class, 'updateUserComplete']);
    Route::delete('/next/users/delete/{id}', [NextCoreController::class, 'destroyUser']);
    Route::put('/next/users/role/{id}', [NextCoreController::class, 'updateUserRole']);
    Route::post('/next/departments/store', [NextCoreController::class, 'storeOrUpdateDepartment']);

    // 🔐 مدیریت MAC Address کارشناسان (ادمین/سوپروایزر)
    Route::get('/next/agents/mac-addresses', [NextCoreController::class, 'getAgentsWithMacAddresses']);
    Route::post('/next/agents/mac-addresses/{agentId}', [NextCoreController::class, 'updateAgentMacAddresses']);

    // 📊 گزارش ساعات کاری ماهانه کارشناس
    Route::get('/next/hr/monthly-working-hours', [NextCoreController::class, 'getMonthlyWorkingHours']);

    // مدیریت جلسات مشاوره اولیه (Consultation Sessions)
    Route::get('/next/consultation-sessions', [NextCoreController::class, 'getConsultationSessions']);
    Route::post('/next/consultation-sessions', [NextCoreController::class, 'storeConsultationSession']);
    Route::post('/next/consultation-sessions/{id}', [NextCoreController::class, 'updateConsultationSession']);
    Route::delete('/next/consultation-sessions/{id}', [NextCoreController::class, 'destroyConsultationSession']);

    // 👑 هاب مدیریت دپارتمانی کارشناسان (StaffManagerController)
    Route::get('/staff/tickets', [StaffManagerController::class, 'getAllClientTickets']);
    Route::post('/staff/tickets/{ticketId}/reply', [StaffManagerController::class, 'replyToTicket']);
    Route::post('/staff/tasks/create-client-task', [StaffManagerController::class, 'createNewClientTask']);
    Route::post('/staff/knowledge/upsert', [StaffManagerController::class, 'upsertKnowledgeBase']);
    Route::get('/staff/tasks/archive', [StaffManagerController::class, 'getAllClientTasks']);
    Route::delete('/staff/tasks/{id}', [StaffManagerController::class, 'destroyClientTask']);
    Route::get('/staff/knowledge/archive', [StaffManagerController::class, 'getAllKnowledgeArticles']);
    Route::get('/staff/leads', [StaffManagerController::class, 'getOfficialClientsOnly']); // 🎯 مچ شده با AccountingManager فرانت
    Route::delete('/staff/knowledge/{id}', [StaffManagerController::class, 'destroyKnowledgeArticle']);
    Route::post('/staff/tasks/{id}/comment', [StaffManagerController::class, 'storeTaskCommentByStaff']);
    Route::post('/staff/shared-docs/upload', [StaffManagerController::class, 'uploadSharedDocByStaff']);
    Route::post('/staff/global-docs/upload', [StaffManagerController::class, 'uploadGlobalDoc']);
    Route::get('/staff/global-docs', [StaffManagerController::class, 'getGlobalDocs']);
    Route::post('/staff/global-docs/assign', [StaffManagerController::class, 'assignGlobalDocToClient']);
    Route::post('/staff/accounting/generate', [StaffManagerController::class, 'generateInvoiceOrInstallments']);
    Route::get('/staff/accounting/invoices', [StaffManagerController::class, 'getAllInvoicesForStaff']); // 🎯 مچ شده با AccountingManager فرانت
    Route::post('/staff/accounting/review/{id}', [StaffManagerController::class, 'reviewClientReceipt']);

    // ویرایش همه‌جانبه کارت تسک بدون خطای ۵۰۰
    Route::post('/staff/tasks/update-fields/{id}', function(Request $request, $id) {
        try {
            $schemaColumns = Schema::getColumnListing('next_tasks');
            $safeUpdateData = collect($request->all())
                ->filter(function ($value, $key) use ($schemaColumns) {
                    return in_array($key, $schemaColumns) && !in_array($key, ['id', 'lead_id']);
                })->toArray();

            if (!empty($safeUpdateData)) {
                $safeUpdateData['updated_at'] = now();
                DB::table('next_tasks')->where('id', $id)->update($safeUpdateData);
            }
            return response()->json(['status' => 'success', 'message' => 'کارت با موفقیت ویرایش شد.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    });
    
    // مدیریت تسک‌ها و یادآورها (NextCoreController)
    $table_prefix = '/next/leads/{id}/';
    Route::get($table_prefix . 'reminders', [NextCoreController::class, 'getReminders']);
    Route::get($table_prefix . 'tasks', [NextCoreController::class, 'getTasks']);
    Route::post('/next/tasks', [NextCoreController::class, 'storeTask']);
    Route::post('/next/tasks/status/{id}', [NextCoreController::class, 'updateTaskStatus']);

    // یادآورها (NextCoreController)
    Route::post('/next/reminders', [NextCoreController::class, 'storeReminder']);
    Route::get('/next/reminders/check-now', [NextCoreController::class, 'checkNow']);
    Route::post('/next/reminders/{id}/dismiss', [NextCoreController::class, 'dismissReminder']);
    Route::post('/next/reminders/status/{id}', [NextCoreController::class, 'updateReminderStatus']);

    // 💬 سیستم چت آنلاین بین کارشناسان (Staff Chat)
    Route::prefix('staff/chat')->controller(\App\Http\Controllers\Api\StaffChatController::class)->group(function () {
        Route::get('/conversations', 'getConversations');        // لیست مکالمات
        Route::get('/messages/{otherUserId}', 'getMessages');    // پیام‌های یک مکالمه
        Route::post('/send', 'sendMessage');                     // ارسال پیام جدید
        Route::post('/read/{messageId}', 'markAsRead');          // علامت‌گذاری به عنوان خوانده شده
    });
});

/*
|--------------------------------------------------------------------------
| ۳. هاب اختصاصی پورتال متقاضیان رسمی (Client Portal API Group)
|--------------------------------------------------------------------------
*/
Route::prefix('client-portal')->middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', [ClientPortalController::class, 'getDashboardMetrics']);
    Route::get('/tasks', [ClientPortalController::class, 'getMyTasks']);
    Route::get('/invoices', [ClientPortalController::class, 'getMyInvoices']);
    Route::get('/tickets', [ClientPortalController::class, 'getMyTickets']);
    Route::post('/tickets/store', [ClientPortalController::class, 'storeTicket']);

    Route::post('/invoices/{id}/upload-receipt', [ClientPortalController::class, 'uploadPaymentReceipt']);
    Route::post('/invoices/{id}/online-pay', [ClientPortalController::class, 'requestOnlinePayment']);
    
    // روت‌های هاب ارتباطات کلاینت
    Route::get('/chat/history', [ClientPortalController::class, 'getPortalChatHistory']);
    Route::post('/chat/send', [ClientPortalController::class, 'sendPortalChatMessage']);
    Route::get('/tasks/{taskId}/comments', [ClientPortalController::class, 'getTaskComments']);
    Route::post('/tasks/{taskId}/comments/store', [ClientPortalController::class, 'storeTaskComment']);
    Route::post('/tasks/{taskId}/answer', [ClientPortalController::class, 'answerTaskWithFile']);
    Route::post('/tasks/{taskId}/complete', [ClientPortalController::class, 'completeTaskByClient']);

    // اسناد اشتراکی کلاینت
    Route::get('/shared-documents', [ClientPortalController::class, 'getSharedDocumentsForClient']); 
    Route::post('/shared-documents/{id}/sign', [ClientPortalController::class, 'signDocumentByClient']);

    Route::get('/documents', [ClientPortalController::class, 'getMyDocuments']);
    Route::post('/documents/upload', [ClientPortalController::class, 'uploadDocument']);
    Route::get('/knowledge-base', [ClientPortalController::class, 'getPortalKnowledgeBase']);
});
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon; // 👈 گارد اول: ایمپورت قطعی کربن

class CallLogController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = DB::table('voip_call_stats as vcs')
                ->leftJoin('leads as l', 'vcs.lead_id', '=', 'l.id')
                ->select([
                    'vcs.id',
                    'vcs.unique_id',
                    'vcs.agent_extension',
                    'vcs.customer_phone',
                    'vcs.duration_seconds',
                    'vcs.disposition',
                    'vcs.call_type',
                    'vcs.created_at',
                    'l.id as lead_id',
                    // 🧠 گارد دوم: اگر ستون‌های first_name وجود ندارند، فقط از name استفاده شود
                    DB::raw("COALESCE(l.name, 'متقاضی ناشناس / لید جدید') as customer_name")
                ]);

            // ⏱️ ۱. فیلتر بازه زمانی
            if ($request->has('date_range') && !empty($request->date_range)) {
                switch ($request->date_range) {
                    case 'today':
                        $query->whereDate('vcs.created_at', Carbon::today()->toDateString());
                        break;
                    case 'yesterday':
                        $query->whereDate('vcs.created_at', Carbon::yesterday()->toDateString());
                        break;
                    case 'last_7_days':
                        $query->where('vcs.created_at', '>=', Carbon::now()->subDays(7));
                        break;
                    case 'last_30_days':
                        $query->where('vcs.created_at', '>=', Carbon::now()->subDays(30));
                        break;
                }
            }

            // 🔄 ۲. فیلتر جهت تماس
            if ($request->filled('call_type')) {
                $query->where('vcs.call_type', $request->call_type);
            }

            // 🟢 ۳. فیلتر وضعیت تماس
            if ($request->filled('disposition')) {
                $query->where('vcs.disposition', $request->disposition);
            }

            // 📞 ۴. فیلتر داخلی کارشناس
            if ($request->filled('agent_extension')) {
                $query->where('vcs.agent_extension', $request->agent_extension);
            }

            // 🔍 ۵. جستجوی متنی
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('vcs.customer_phone', 'LIKE', "%{$search}%")
                      ->orWhere('l.name', 'LIKE', "%{$search}%");
                });
            }

            // مرتب‌سازی و پجینیشن
            $perPage = $request->get('per_page', 15);
            $logs = $query->orderBy('vcs.created_at', 'desc')
                          ->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $logs->items(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'total' => $logs->total(),
                    'per_page' => $logs->perPage()
                ]
            ]);

        } catch (\Exception $e) {
            // 🚨 در صورت بروز هرگونه خطای دیتابیس، متن دقیق خطا را برمی‌گرداند تا کورکورانه عیب‌یابی نکنیم
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}
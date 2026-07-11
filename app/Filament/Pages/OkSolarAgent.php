<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use App\Services\OkSolarKnowledgeService;

// 💡 فیکس اورژانسی: جایگزینی کلاس فرم قدیمی با کلاس اسکیمای والد فیلامنت جدید بر اساس استک تریس
use Filament\Schemas\Schema as Form; 
use Filament\Notifications\Notification;

class OkSolarAgent extends Page implements HasForms
{
    use InteractsWithForms;

    // 🔒 تنظیم مقتدرانه انواع متغیرها جهت هماهنگی با هسته فیلامنت
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = 'ایجنت مشاوره خورشیدی B2B';
    protected static ?string $title = '🧠 میز کار تحلیل و مذاکره هوشمند okSolar';
    
    protected string $view = 'filament.pages.ok-solar-agent';

    // متغیرهای فرم
    public ?string $client_scenario = null;
    public ?string $category = null;
    public ?string $ai_output = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('category')
                    ->label('دسته بندی تجهیزات مرجع')
                    ->options([
                        'panel' => 'پنل‌های فتوولتاییک',
                        'inverter' => 'اینورترها',
                        'battery' => 'سیستم‌های ذخیره‌ساز/باتری',
                    ])
                    ->placeholder('انتخاب کنید (اختیاری)'),

                Textarea::make('client_scenario')
                    ->label('مشخصات، صنعت و چالش‌های کارفرما (برای تزریق به متغیرهای پرامپت)')
                    ->placeholder("مثال:\n- نوع صنعت: کارخانه سیمان\n- مقیاس: مگاواتی / محدودیت سقف سوله\n- چالش اصلی: قطعی قطع برق روزانه ۳ ساعت و جریمه دیماند\n- تصمیم‌گیرنده: مدیر مالی (CFO)\n- اعتراض اصلی: سنگین بودن سرمایه‌گذاری اولیه")
                    ->rows(6)
                    ->required(),
            ]);
    }

    /**
     * اکشن شلیک فرم به سرویس هوش مصنوعی okSolar
     */
    public function processAnalysis()
    {
        $data = $this->form->getState();
        
        $this->ai_output = '⏳ در حال آنالیز لایه به لایه سناریو، فرمول‌بندی ROI و بازنویسی اسکریپت مذاکره... لطفاً منتظر بمانید رفیق.';

        $solarService = app(OkSolarKnowledgeService::class);
        $result = $solarService->askSolarAgent($data['client_scenario'], $data['category']);

        if ($result['status'] === 'success') {
            // 💡 فیکس نهایی: تبدیل فرمت مارک‌داون به HTML شیک برای رندر بی‌پایان در بلید
            $this->ai_output = \Illuminate\Support\Str::markdown($result['answer']);
            
            Notification::make()
                ->title('تحلیل ایجنت با موفقیت انجام شد ✨')
                ->success()
                ->send();
        } else {
            $this->ai_output = '❌ خطا: ' . $result['message'];
            
            Notification::make()
                ->title('خطا در پردازش')
                ->danger()
                ->send();
        }
    }
}
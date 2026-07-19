<?php

namespace App\Filament\Resources\Leads;

use App\Filament\Resources\Leads\Pages;
use App\Models\Lead;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    public static function getModelLabel(): string
    {
        return 'لید';
    }

    public static function getPluralModelLabel(): string
    {
        return 'لیدها';
    }

    public static function getNavigationLabel(): string
    {
        return 'لیدهای دریافتی';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-funnel';
    }

    /**
     * 📊 طراحی فرم مشاهده و ویرایش اطلاعات پرونده (پشتیبانی از نام و تلفن فیزیکی)
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 📋 بخش اول: اطلاعات عمومی و فیزیکی پرونده (قابل پر کردن توسط کارشناس)
                TextInput::make('perfex_lead_id')->label('شناسه لید پرفکس')->disabled(),
                TextInput::make('name')->label('👤 نام و نام خانوادگی متقاضی')->placeholder('امکان ثبت دستی توسط مشاور...')->required(),
                TextInput::make('phone')->label('📱 شماره تماس مستقیم')->placeholder('مثال: 989123456789')->required(),
                
                Select::make('agent_id')
                    ->label('مشاور تخصیص یافته')
                    ->relationship('agent', 'name')
                    ->placeholder('سیستم در حال تصمیم‌گیری است...')
                    ->disabled(), 
                TextInput::make('status')->label('وضعیت سیستم')->disabled(),

                // 🧠 بخش دوم: نمایش هوشمند آنالیز استخراج شده از JSON
                TextInput::make('behavioral_data.intent')
                    ->label('نیت شناسایی شده (Intent)')
                    ->placeholder('در حال استخراج...')
                    ->disabled(),

                TextInput::make('behavioral_data.destination')
                    ->label('کشور مقصد')
                    ->placeholder('نامشخص')
                    ->disabled(),

                TextInput::make('behavioral_data.urgency')
                    ->label('درجه اضطرار (Urgency)')
                    ->disabled(),

                TextInput::make('behavioral_data.interest_level')
                    ->label('سطح علاقه متقاضی')
                    ->disabled(),

                Textarea::make('behavioral_data.conversation_summary')
                    ->label('📝 خلاصه آخرین مکالمات کاربر')
                    ->columnSpanFull()
                    ->rows(3)
                    ->disabled(),

                Textarea::make('behavioral_data.recommended_action')
                    ->label('💡 اقدام پیشنهادی هوش مصنوعی')
                    ->columnSpanFull()
                    ->rows(3)
                    ->disabled(),
            ]);
    }
    
    /**
     * 🖥️ مانیتورینگ زنده جدول لیدها به همراه اکشن هوشمند ادغام
     */
    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('perfex_lead_id')->label('کد لید')->sortable()->searchable(),
                
                // نمایش فیزیکی نام (اگر خالی بود عنوان کانال را نشان می‌دهد)
                TextColumn::make('name')
                    ->label('👤 نام متقاضی')
                    ->state(fn ($record) => $record->name ?: data_get($record, 'behavioral_data.title', 'بدون نام'))
                    ->searchable(),

                // نمایش فیزیکی شماره تلفن
                TextColumn::make('phone')
                    ->label('📱 شماره تلفن')
                    ->default('ثبت نشده')
                    ->searchable(),

                TextColumn::make('source')->label('منبع ورودی')->badge()->color('gray'),
                
                TextColumn::make('behavioral_data.destination')
                    ->label('مقصد احتمالی')
                    ->state(fn ($record) => data_get($record, 'behavioral_data.destination') ?: data_get($record, 'customerInsight.likely_destination', 'نامشخص'))
                    ->badge()
                    ->color('info'),

                TextColumn::make('behavioral_data.intent')
                    ->label('نیت متقاضی')
                    ->state(fn ($record) => data_get($record, 'behavioral_data.intent') ?: data_get($record, 'customerInsight.last_intent', 'نامشخص'))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('behavioral_data.interest_level')
                    ->label('سطح علاقه')
                    ->state(fn ($record) => data_get($record, 'behavioral_data.interest_level') ?: data_get($record, 'customerInsight.interest_level', 'medium'))
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'high' => 'danger',
                        'medium' => 'warning',
                        'low' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('agent.name')->label('مشاور مسئول')->placeholder('در انتظار تخصیص...'),
                TextColumn::make('status')->label('وضعیت')->badge()->color(fn (string $state): string => match ($state) {
                    'assigned' => 'success',
                    'unassigned' => 'warning',
                    default => 'gray',
                }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('intent')
                    ->label('نوع درخواست (AI)')
                    ->options([
                        'study_visa' => 'ویزای تحصیلی',
                        'work_visa' => 'ویزای کاری',
                        'investment_visa' => 'سرمایه‌گذاری',
                        'general_inquiry' => 'مشاوره عمومی',
                    ])
                    ->query(fn ($query, array $data) => 
                        $query->when($data['value'], fn ($q, $value) => 
                            $q->where('behavioral_data->intent', $value)
                        )
                    ),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make()->label('مشاهده و آنالیز AI'),
                \Filament\Actions\EditAction::make(),

                // 📞 اکشن ایجاد جلسه مشاوره اولیه
                \Filament\Actions\Action::make('create_consultation_session')
                    ->label('جلسه مشاوره اولیه')
                    ->icon('heroicon-o-phone')
                    ->color('success')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('session_date')
                            ->label('تاریخ جلسه')
                            ->required()
                            ->default(now()),
                        
                        \Filament\Forms\Components\Select::make('agent_id')
                            ->label('مشاور')
                            ->relationship('agent', 'name')
                            ->default(fn ($record) => $record->agent_id)
                            ->disabled(fn ($record) => !auth()->user()->hasRole('supervisor')),
                        
                        // 👤 اطلاعات هویتی و عمومی متقاضی
                        \Filament\Forms\Components\Section::make('اطلاعات هویتی')
                            ->schema([
                                \Filament\Forms\Components\TextInput::make('first_name')->label('نام'),
                                \Filament\Forms\Components\TextInput::make('last_name')->label('نام خانوادگی'),
                                \Filament\Forms\Components\TextInput::make('age')->label('سن')->numeric(),
                                \Filament\Forms\Components\Select::make('marital_status')
                                    ->label('وضعیت تاهل')
                                    ->options([
                                        'single' => 'مجرد',
                                        'married' => 'متاهل',
                                    ]),
                                \Filament\Forms\Components\Select::make('military_status')
                                    ->label('وضعیت نظام وظیفه')
                                    ->options([
                                        'exempt' => 'معاف',
                                        'served' => 'انجام شده',
                                        'serving' => 'در حال خدمت',
                                        'not_required' => 'مشمول نمی‌باشد',
                                    ]),
                            ])
                            ->columns(3),
                        
                        // 🎓 سوابق تحصیلی متقاضی
                        \Filament\Forms\Components\Section::make('سوابق تحصیلی')
                            ->schema([
                                \Filament\Forms\Components\Select::make('last_degree')
                                    ->label('آخرین مدرک تحصیلی')
                                    ->options([
                                        'diploma' => 'دیپلم',
                                        'associate' => 'کاردانی',
                                        'bachelor' => 'کارشناسی',
                                        'master' => 'کارشناسی ارشد',
                                        'phd' => 'دکتری',
                                    ]),
                                \Filament\Forms\Components\TextInput::make('gpa')->label('معدل')->numeric(),
                                \Filament\Forms\Components\TextInput::make('graduation_year')->label('سال فارغ‌التحصیلی')->numeric(),
                                \Filament\Forms\Components\TextInput::make('field_of_study')->label('رشته تحصیلی'),
                            ])
                            ->columns(2),
                        
                        // 🌐 مدرک زبان و وضعیت تمکن مالی
                        \Filament\Forms\Components\Section::make('مدرک زبان و تمکن مالی')
                            ->schema([
                                \Filament\Forms\Components\Select::make('language_degree')
                                    ->label('نوع مدرک زبان')
                                    ->options([
                                        'ielts' => 'IELTS',
                                        'toefl' => 'TOEFL',
                                        'duolingo' => 'Duolingo',
                                        'pte' => 'PTE',
                                    ]),
                                \Filament\Forms\Components\TextInput::make('language_score')->label('نمره زبان'),
                                \Filament\Forms\Components\TextInput::make('financial_capability')->label('تمکن مالی (تومان)')->numeric(),
                                \Filament\Forms\Components\Select::make('has_job_offer')
                                    ->label('جاب آفر')
                                    ->options([
                                        true => 'دارد',
                                        false => 'ندارد',
                                    ])
                                    ->default(false),
                            ])
                            ->columns(2),
                        
                        // 📊 اطلاعات استراتژیک بیزینسی
                        \Filament\Forms\Components\Section::make('اطلاعات ویزا')
                            ->schema([
                                \Filament\Forms\Components\TextInput::make('target_country')->label('کشور مقصد'),
                                \Filament\Forms\Components\Select::make('visa_type')
                                    ->label('نوع ویزا')
                                    ->options([
                                        'study' => 'ویزای تحصیلی',
                                        'work' => 'ویزای کاری',
                                        'investment' => 'ویزای سرمایه‌گذاری',
                                        'tourist' => 'ویزای توریستی',
                                    ]),
                            ])
                            ->columns(2),
                        
                        // اطلاعات همسر
                        \Filament\Forms\Components\Section::make('اطلاعات همسر')
                            ->schema([
                                \Filament\Forms\Components\TextInput::make('spouse_name')->label('نام همسر'),
                                \Filament\Forms\Components\TextInput::make('spouse_phone')->label('شماره تماس همسر'),
                            ])
                            ->columns(2),
                        
                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('یادداشت‌های جلسه')
                            ->rows(3),
                    ])
                    ->action(function (array $data, $record): void {
                        $session = new \App\Models\ConsultationSession();
                        $session->lead_id = $record->id;
                        $session->agent_id = $data['agent_id'] ?? $record->agent_id;
                        $session->session_date = $data['session_date'];
                        $session->session_type = 'initial';
                        $session->status = 'completed';
                        $session->notes = $data['notes'] ?? null;
                        
                        // اطلاعات هویتی
                        $session->first_name = $data['first_name'] ?? null;
                        $session->last_name = $data['last_name'] ?? null;
                        $session->age = $data['age'] ?? null;
                        $session->marital_status = $data['marital_status'] ?? null;
                        $session->military_status = $data['military_status'] ?? null;
                        
                        // سوابق تحصیلی
                        $session->last_degree = $data['last_degree'] ?? null;
                        $session->gpa = $data['gpa'] ?? null;
                        $session->graduation_year = $data['graduation_year'] ?? null;
                        $session->field_of_study = $data['field_of_study'] ?? null;
                        
                        // مدرک زبان و تمکن مالی
                        $session->language_degree = $data['language_degree'] ?? null;
                        $session->language_score = $data['language_score'] ?? null;
                        $session->financial_capability = $data['financial_capability'] ?? 0;
                        $session->has_job_offer = $data['has_job_offer'] ?? false;
                        
                        // اطلاعات ویزا
                        $session->target_country = $data['target_country'] ?? null;
                        $session->visa_type = $data['visa_type'] ?? null;
                        
                        // اطلاعات همسر
                        $session->spouse_name = $data['spouse_name'] ?? null;
                        $session->spouse_phone = $data['spouse_phone'] ?? null;
                        
                        $session->save();
                        
                        // اگر ناظر کارشناس را تغییر داد، آپدیت کن
                        if (isset($data['agent_id']) && $data['agent_id'] != $record->agent_id) {
                            $record->agent_id = $data['agent_id'];
                            $record->save();
                        }
                        
                        \Filament\Notifications\Notification::make()
                            ->title('جلسه مشاوره اولیه ثبت شد')
                            ->success()
                            ->send();
                    })
                    ->visible(fn ($record) => !$record->consultationSessions()->where('session_type', 'initial')->exists()),

                // 🎯 اکشن گرافیکی ادغام لید موازی (مجهز به لایه ولیدیشن امنیت فیلامنت)
                \Filament\Actions\Action::make('merge_lead')
                    ->label('ادغام لید موازی')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('warning')
                    ->form([
                        Select::make('target_lead_id')
                            ->label('انتخاب لید اصلی (مقصد ادغام)')
                            ->helperText('لطفاً پرونده اصلی و باسابقه متقاضی را سرچ و انتخاب کنید. تمام چت‌های لید جاری به این لید منتقل خواهند شد.')
                            ->required()
                            ->searchable()
                            // ۱. متد جستجوی لایو زنده
                            ->getSearchResultsUsing(function (string $search, $record) {
                                return Lead::query()
                                    ->where('id', '!=', $record->id)
                                    ->where(function ($query) use ($search) {
                                        $query->where('perfex_lead_id', 'like', "%{$search}%")
                                              ->orWhere('name', 'like', "%{$search}%")
                                              ->orWhere('behavioral_data->title', 'like', "%{$search}%")
                                              ->orWhere('phone', 'like', "%{$search}%");
                                    })
                                    ->limit(15)
                                    ->get()
                                    ->mapWithKeys(fn ($item) => [
                                        $item->id => "کد لید: " . ($item->perfex_lead_id) . " - " . ($item->name ?: data_get($item->behavioral_data, 'title', 'بدون نام'))
                                    ]);
                            })
                            // 💡 ۲. متد جادویی حل مشکل ولیدیشن امنیت فیلامنت برای تایید نهایی گزینه انتخاب شده
                            ->getOptionLabelUsing(function ($value) {
                                $lead = Lead::find($value);
                                if (!$lead) return '';
                                return "کد لید: " . ($lead->perfex_lead_id) . " - " . ($lead->name ?: data_get($lead->behavioral_data, 'title', 'بدون نام'));
                            })
                            ->placeholder('نام، تلفن یا کد لید را سرچ کنید...'),
                    ])
                    ->action(function (array $data, $record): void {
                        $sourceLeadId = $record->id; 
                        $targetLeadId = $data['target_lead_id']; 

                        $mergeService = app(\App\Services\LeadMergeService::class);
                        $success = $mergeService->mergeLeads($sourceLeadId, $targetLeadId);

                        if ($success) {
                            \Filament\Notifications\Notification::make()
                                ->title('ادغام موفقیت‌آمیز چنل‌ها')
                                ->body('تمام تاریخچه چت‌ها، کانال‌های ورودی و ساختارهای هوشمند با موفقیت یکپارچه و لید موازی حذف شد.')
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('خطا در ادغام')
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn ($record) => !in_array($record->status, ['closed', 'converted'])),
            ])
            ->poll('5s'); 
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Leads\RelationManagers\ChatLogsRelationManager::class,
            \App\Filament\Resources\Leads\RelationManagers\ConsultationSessionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }
}
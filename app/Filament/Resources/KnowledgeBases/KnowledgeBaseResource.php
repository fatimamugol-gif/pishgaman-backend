<?php

namespace App\Filament\Resources\KnowledgeBases;

use App\Filament\Resources\KnowledgeBases\Pages\CreateKnowledgeBase;
use App\Filament\Resources\KnowledgeBases\Pages\EditKnowledgeBase;
use App\Filament\Resources\KnowledgeBases\Pages\ListKnowledgeBases;
use App\Filament\Resources\KnowledgeBases\Schemas\KnowledgeBaseForm;
use App\Filament\Resources\KnowledgeBases\Tables\KnowledgeBasesTable;
use App\Models\KnowledgeBase;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables;

class KnowledgeBaseResource extends Resource
{
    protected static ?string $model = KnowledgeBase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\TextInput::make('title')
                    ->label('عنوان قانون، بخشنامه یا فایل')
                    ->required(),

                \Filament\Forms\Components\Select::make('category')
                    ->label('دسته‌بندی')
                    ->options([
                        'general' => 'عمومی',
                        'faq' => 'سوالات متداول',
                        'rules' => 'قوانین و مصوبات سفارت',
                        'contract' => 'مستندات قرارداد',
                    ])
                    ->default('general')
                    ->required(),

                // 💡 باکس آپلود فایل PDF تخصصی قوانین پیشگامان
                \Filament\Forms\Components\FileUpload::make('file_path')
                    ->label('آپلود فایل قوانین و مستندات')
                    ->directory('knowledge-base-files')
                    // 💡 اضافه کردن انواع فرمت‌های مجاز (PDF, Word, Excel, CSV, TXT)
                    ->acceptedFileTypes([
                        'application/pdf',
                        'text/plain',
                        'text/csv',
                        'application/msword', // doc
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
                        'application/vnd.ms-excel', // xls
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' // xlsx
                    ])
                    ->maxSize(20480), // سقف حجم را به ۲۰ مگابایت افزایش دادیم برای فایل‌های سنگین اکسل یا پی‌دی‌اف
                // 💡 این فیلد را nullable کردیم چون ممکن است دیتا از فایل خوانده شود
                \Filament\Forms\Components\Textarea::make('content')
                    ->label('متن دستی قانون (اگر فایل آپلود نکردید، اینجا بنویسید)')
                    ->rows(8),

                \Filament\Forms\Components\Toggle::make('is_active')
                    ->label('وضعیت انتشار در پایگاه دانش')
                    ->default(true),
            ]);
    }

    public static function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return $table
            ->columns([
                // 💡 اضافه شدن بک‌اسلش (\) قبل از Filament برای خوانش مطلق آدرس کلاس
                \Filament\Tables\Columns\TextColumn::make('title')
                    ->label('عنوان قانون / بخشنامه')
                    ->searchable(),

                \Filament\Tables\Columns\TextColumn::make('category')
                    ->label('دسته‌بندی')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'general' => 'عمومی',
                        'faq' => 'سوالات متداول',
                        'rules' => 'قوانین سفارت',
                        'contract' => 'مستندات قرارداد',
                        default => $state,
                    }),

                \Filament\Tables\Columns\IconColumn::make('is_active')
                    ->label('وضعیت انتشار')
                    ->boolean(),

                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ ثبت')
                    ->dateTime('Y/m/d')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKnowledgeBases::route('/'),
            'create' => CreateKnowledgeBase::route('/create'),
            'edit' => EditKnowledgeBase::route('/{record}/edit'),
        ];
    }
}

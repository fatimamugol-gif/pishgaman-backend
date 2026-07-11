<?php

namespace App\Filament\Resources\Agents;

use App\Filament\Resources\AgentResource\Pages;
use App\Models\Agent;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Schemas\Schema;

class AgentResource extends Resource
{
    protected static ?string $model = Agent::class;

    // متغیرهای دردسرساز گرافیکی و متنی را کاملا پاک کردیم و به متدهای زیر تبدیل کردیم:

    public static function getModelLabel(): string
    {
        return 'مشاور';
    }

    public static function getPluralModelLabel(): string
    {
        return 'مشاوران فروش';
    }

    public static function getNavigationLabel(): string
    {
        return 'مشاوران فروش';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-users';
    }

  public static function form(Schema $schema): Schema
    {
        // در فایلمنت ۵، فیلدها مستقیماً و بدون نیاز به کامپوننت واسط درون کانتینر شِما قرار می‌گیرند
        return $schema
            ->components([
                \Filament\Forms\Components\TextInput::make('perfex_staff_id')
                    ->label('شناسه کارمند در پرفکس')
                    ->required()
                    ->numeric(),

                \Filament\Forms\Components\TextInput::make('name')
                    ->label('نام و نام خانوادگی')
                    ->required(),

                \Filament\Forms\Components\TextInput::make('email')
                    ->label('ایمیل')
                    ->email()
                    ->required(),

                \Filament\Forms\Components\Toggle::make('is_active')
                    ->label('وضعیت فعالیت')
                    ->default(true),

                \Filament\Forms\Components\TextInput::make('max_capacity')
                    ->label('سقف ظرفیت لید')
                    ->numeric()
                    ->default(10),

                \Filament\Forms\Components\TextInput::make('current_active_leads')
                    ->label('لیدهای فعال فعلی')
                    ->numeric()
                    ->default(0),

                \Filament\Forms\Components\TextInput::make('conversion_rate')
                    ->label('نرخ تبدیل')
                    ->numeric()
                    ->default(0.00)
                    ->suffix('%'),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('perfex_staff_id')->label('کد پرفکس')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('نام مشاور')->searchable(),
                Tables\Columns\TextColumn::make('email')->label('ایمیل'),
                Tables\Columns\ToggleColumn::make('is_active')->label('وضعیت فعالیت'),
                Tables\Columns\TextColumn::make('current_active_leads')
                    ->label('لیدهای فعال / سقف')
                    ->state(fn ($record): string => "{$record->current_active_leads} / {$record->max_capacity}"),
                Tables\Columns\TextColumn::make('conversion_rate')->label('نرخ تبدیل')->sortable()->badge()->color('success')->suffix('%'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('وضعیت مشاور')
                    ->options([
                        true => 'فعال',
                        false => 'غیرفعال',
                    ]),
            ])
            // اصلاح آدرس اکشن‌ها برای فایلمنت ۵
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
{
    return [
        'index' => \App\Filament\Resources\Agents\Pages\ListAgents::route('/'),
        'create' => \App\Filament\Resources\Agents\Pages\CreateAgent::route('/create'),
        'edit' => \App\Filament\Resources\Agents\Pages\EditAgent::route('/{record}/edit'),
    ];
}
}
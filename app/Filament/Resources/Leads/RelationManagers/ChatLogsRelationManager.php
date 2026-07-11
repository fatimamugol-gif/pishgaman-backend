<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema; // 💡 استفاده از موتور اختصاصی سیستم شما

class ChatLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'chatLogs';

    protected static ?string $title = 'تاریخچه مکالمات و چت لاگ';

    /**
     * 💡 هماهنگ‌سازی متد فرم با ساختار Schema اختصاصی پروژه شما
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 🔐 چت‌لاگ‌ها فقط خواندنی هستند، آرایه کامپوننت‌ها را کاملاً خالی می‌گذاریم.
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('sender_type')
                    ->label('فرستنده')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'user' => 'primary',
                        'bot' => 'success',
                        'agent' => 'warning',
                        default => 'gray'
                    }),

                \Filament\Tables\Columns\TextColumn::make('message')
                    ->label('متن پیام')
                    ->wrap(),

                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->label('زمان')
                    ->dateTime('H:i - Y/m/d'),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
<?php
use App\Filament\Pages\OkSolarAgent;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin/ok-solar-agent', OkSolarAgent::class)
    ->middleware([\Illuminate\View\Middleware\ShareErrorsFromSession::class])
    ->name('filament.admin.pages.ok-solar-agent');
<?php

return [
    'paths' => ['api/*','next/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'], // یا آدرس دقیق فرانتت مثل http://localhost:3001
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'], // 🎯 پذیرفتن تمام هدرهای سفارشی فرانت
    'exposed_headers' => [],
    'cors_allowed_origins' => ['*'],
    'max_age' => 0,
    'supports_credentials' => true,
];
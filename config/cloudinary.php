<?php

return [

    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
    'api_key'    => env('CLOUDINARY_API_KEY'),
    'api_secret' => env('CLOUDINARY_API_SECRET'),
    'secure'     => env('CLOUDINARY_SECURE', true),

    // URL complète cloudinary://...
    'cloud_url'  => env('CLOUDINARY_URL'),

    // Optionnel
    'notification_url' => env('CLOUDINARY_NOTIFICATION_URL'),
];

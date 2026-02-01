<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SingPayAuthService
{
    public function getAccessToken(): string
    {
        return Cache::remember('singpay_access_token', 3500, function () {
            $response = Http::asForm()->post(
                config('services.singpay.token_url'),
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => config('services.singpay.client_id'),
                    'client_secret' => config('services.singpay.client_secret'),
                ]
            );

            return $response->json()['access_token'];
        });
    }
}

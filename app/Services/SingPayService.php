<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SingPayService
{
    protected string $apiUrl;
    protected string $tokenUrl;
    protected string $clientId;
    protected string $clientSecret;

    public function __construct()
    {
        $this->apiUrl = config('services.singpay.api_url');   // ex: https://api.singpay.com
        $this->tokenUrl = config('services.singpay.token_url'); // ex: https://api.singpay.com/oauth/token
        $this->clientId = config('services.singpay.client_id');
        $this->clientSecret = config('services.singpay.client_secret');
    }

    /**
     * Récupère le token OAuth 2.0 (cache pour éviter les appels répétés)
     */
    public function getAccessToken(): string
    {
        return Cache::remember('singpay_access_token', 3500, function () {
            $response = Http::asForm()
                ->post($this->tokenUrl, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]);

            if (!$response->successful()) {
                throw new \Exception('Unable to get SingPay access token: ' . $response->body());
            }

            return $response->json('access_token');
        });
    }

    /**
     * Retourne un client HTTP avec token pour production
     */
    protected function client()
    {
        return Http::withToken($this->getAccessToken())
            ->acceptJson()
            ->timeout(30);
        // ✅ SSL vérifié par défaut
    }

    /**
     * Airtel Money USSD Push
     * POST /74/paiement
     */
    public function payAirtel(array $data)
    {
        return $this->client()
            ->post("{$this->apiUrl}/74/paiement", $data)
            ->throw() // lance une exception si erreur HTTP
            ->json();
    }

    /**
     * Moov Money USSD Push
     * POST /62/paiement
     */
    public function payMoov(array $data)
    {
        return $this->client()
            ->post("{$this->apiUrl}/62/paiement", $data)
            ->throw()
            ->json();
    }

    /**
     * Récupérer le statut d’une transaction
     * GET /transaction/api/status/{id}
     */
    public function getTransactionStatus(string $transactionId)
    {
        return $this->client()
            ->get("{$this->apiUrl}/transaction/api/status/{$transactionId}")
            ->throw()
            ->json();
    }

    /**
     * Récupérer une transaction par référence
     */
    public function getTransactionByReference(string $reference)
    {
        return $this->client()
            ->get("{$this->apiUrl}/transaction/api/search/by-reference/{$reference}")
            ->throw()
            ->json();
    }
}

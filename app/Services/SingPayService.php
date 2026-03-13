<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SingPayService
{
    protected string $apiUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected string $walletId;

    public function __construct()
    {
        $this->apiUrl        = config('services.singpay.api_url');
        $this->clientId      = config('services.singpay.client_id');
        $this->clientSecret  = config('services.singpay.client_secret');
        $this->walletId      = config('services.singpay.wallet_id');
    }

    /**
     * Client HTTP avec les headers d'authentification SingPay
     */
    protected function client()
    {
        return Http::withHeaders([
            'x-client-id'     => $this->clientId,
            'x-client-secret' => $this->clientSecret,
            'x-wallet'        => $this->walletId,
        ])
        ->acceptJson()
        ->timeout(30);
    }

    /**
     * Airtel Money USSD Push
     * POST /74/paiement
     */
    public function payAirtel(array $data)
    {
        return $this->client()
            ->post("{$this->apiUrl}/74/paiement", [
                'amount'       => $data['amount'],
                'reference'    => $data['reference'],
                'client_msisdn'=> $data['phone'],
                'portefeuille' => $this->walletId,
                'isTransfer'   => false,
            ])
            ->throw()
            ->json();
    }

    /**
     * Moov Money USSD Push
     * POST /62/paiement
     */
    public function payMoov(array $data)
    {
        return $this->client()
            ->post("{$this->apiUrl}/62/paiement", [
                'amount'       => $data['amount'],
                'reference'    => $data['reference'],
                'client_msisdn'=> $data['phone'],
                'portefeuille' => $this->walletId,
                'isTransfer'   => false,
            ])
            ->throw()
            ->json();
    }

    /**
     * Statut d'une transaction
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
     * GET /transaction/api/search/by-reference/{reference}
     */
    public function getTransactionByReference(string $reference)
    {
        return $this->client()
            ->get("{$this->apiUrl}/transaction/api/search/by-reference/{$reference}")
            ->throw()
            ->json();
    }
}
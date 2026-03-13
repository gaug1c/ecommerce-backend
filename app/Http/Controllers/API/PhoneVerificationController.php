<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as TwilioClient;

class PhoneVerificationController extends Controller
{
    /**
     * Envoie un code OTP à 6 chiffres via WhatsApp (Twilio)
     *
     * POST /api/v1/phone/send-otp
     * Body: { "phone": "+24106XXXXXX" }
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:8|max:20',
        ]);

        $user  = $request->user();
        $phone = $this->normalizePhone($request->phone);

        // Déjà vérifié ?
        if ($user->phone_verified_at && $user->phone === $phone) {
            return response()->json([
                'success' => false,
                'message' => 'Ce numéro est déjà vérifié.',
            ], 409);
        }

        // Anti-spam : 1 envoi par minute par user
        $spamKey = "otp_spam:{$user->id}";
        if (Cache::has($spamKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Veuillez attendre 1 minute avant de renvoyer le code.',
            ], 429);
        }

        // Générer et stocker l'OTP (TTL 10 min)
        $otp    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $cacheKey = "phone_otp:{$user->id}";
        Cache::put($cacheKey, [
            'code'  => $otp,
            'phone' => $phone,
        ], now()->addMinutes(10));

        // Anti-spam : bloquer pendant 60 s
        Cache::put($spamKey, true, now()->addMinute());

        // Envoyer via Twilio WhatsApp
        try {
            $twilio = new TwilioClient(
                config('services.twilio.sid'),
                config('services.twilio.token')
            );

            $message = $twilio->messages->create(
                "whatsapp:{$phone}",
                [
                    'from'             => config('services.twilio.whatsapp_from'),
                    'contentSid'       => config('services.twilio.otp_content_sid'),
                    'contentVariables' => json_encode(['1' => $otp]),
                ]
            );

            Log::info('Twilio OTP sent', [
                'sid'    => $message->sid,
                'status' => $message->status,
                'to'     => $phone,
            ]);

        } catch (\Exception $e) {
            Log::error('Twilio OTP error', ['error' => $e->getMessage(), 'phone' => $phone]);
            Cache::forget($cacheKey);
            Cache::forget($spamKey);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Code envoyé sur WhatsApp !',
        ]);
    }

    /**
     * Vérifie le code OTP et marque le téléphone comme vérifié
     *
     * POST /api/v1/phone/verify-otp
     * Body: { "phone": "+24106XXXXXX", "code": "123456" }
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'code'  => 'required|string|size:6',
        ]);

        $user  = $request->user();
        $phone = $this->normalizePhone($request->phone);
        $cacheKey = "phone_otp:{$user->id}";

        $cached = Cache::get($cacheKey);

        if (!$cached) {
            return response()->json([
                'success' => false,
                'message' => 'Code expiré ou inexistant. Veuillez renvoyer un code.',
            ], 422);
        }

        if ($cached['phone'] !== $phone) {
            return response()->json([
                'success' => false,
                'message' => 'Ce code ne correspond pas au numéro saisi.',
            ], 422);
        }

        if ($cached['code'] !== $request->code) {
            return response()->json([
                'success' => false,
                'message' => 'Code incorrect. Veuillez réessayer.',
            ], 422);
        }

        // ✅ Marquer le téléphone comme vérifié
        $user->update([
            'phone'             => $phone,
            'phone_verified_at' => now(),
        ]);

        // Nettoyer le cache
        Cache::forget($cacheKey);

        return response()->json([
            'success'            => true,
            'message'            => 'Téléphone vérifié avec succès !',
            'phone_verified_at'  => $user->phone_verified_at,
        ]);
    }

    /**
     * Normalise le numéro : retire espaces, tirets, ajoute + si absent
     * et supprime le 0 local après l'indicatif pays
     * ex: +33 07 45 52 15 14 → +33745521514
     */
    private function normalizePhone(string $phone): string
    {
        // Supprimer espaces, tirets, parenthèses
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);

        // Ajouter + si absent
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        // Supprimer le 0 local après l'indicatif pays
        // ex: +33 0745521514 → +33745521514
        $phone = preg_replace('/^(\+\d{1,3})0(\d+)$/', '$1$2', $phone);

        return $phone;
    }
}
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\SingPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SingPayWebhookController extends Controller
{
    public function handle(Request $request, SingPayService $singPay)
    {
        /**
         * Exemple payload callback SingPay
         * {
         *   "reference": "SP-65AF3...",
         *   "transaction_id": 123456,
         *   "status": "SUCCESS"
         * }
         */

        $reference = $request->input('reference');
        $transactionId = $request->input('transaction_id');

        if (!$reference && !$transactionId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid callback payload'
            ], 400);
        }

        // 1️⃣ Récupérer le paiement local
        $payment = Payment::where('transaction_id', $reference)
            ->orWhere('provider_reference', $transactionId)
            ->first();

        if (!$payment) {
            Log::warning('SingPay webhook: payment not found', $request->all());

            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }

        DB::beginTransaction();

        try {
            /**
             * 2️⃣ Vérification OFFICIELLE du statut via l’API SingPay
             * Endpoint doc :
             * GET /transaction/api/status/{id}
             * ou
             * GET /transaction/api/search/by-reference/{reference}
             */
            $transaction = $transactionId
                ? $singPay->getTransactionStatus($transactionId)
                : $singPay->getTransactionByReference($reference);

            $status = $transaction['status'] ?? null;

            if (!$status) {
                throw new \Exception('Unable to retrieve transaction status');
            }

            // 3️⃣ Idempotence
            if ($payment->status === 'completed') {
                DB::commit();
                return response()->json(['success' => true]);
            }

            // 4️⃣ Mapping statut SingPay → statut interne
            switch (strtoupper($status)) {
                case 'SUCCESS':
                case 'COMPLETED':
                    $payment->update([
                        'status' => 'completed',
                        'payment_details' => json_encode($transaction)
                    ]);

                    $payment->order->update([
                        'payment_status' => 'paid'
                    ]);
                    break;

                case 'FAILED':
                case 'CANCELLED':
                    $payment->update([
                        'status' => 'failed',
                        'payment_details' => json_encode($transaction)
                    ]);
                    break;

                case 'PENDING':
                    $payment->update([
                        'status' => 'pending',
                        'payment_details' => json_encode($transaction)
                    ]);
                    break;

                default:
                    Log::warning('SingPay unknown status', [
                        'status' => $status,
                        'transaction' => $transaction
                    ]);
                    break;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'SingPay webhook processed successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('SingPay webhook error', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed'
            ], 500);
        }
    }
}

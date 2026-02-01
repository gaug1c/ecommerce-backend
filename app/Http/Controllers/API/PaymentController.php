<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\SingPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function processPayment(Request $request, $orderId, SingPayService $singPay)
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:AIRTEL,MOOV',
            'phone'    => 'required|string|min:8|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $order = Order::where('user_id', $request->user()->id)
            ->whereIn('payment_status', ['unpaid', 'pending'])
            ->find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or already paid',
            ], 404);
        }

        if ($order->payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment already initiated for this order',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $payment = Payment::create([
                'order_id' => $order->id,
                'payment_method' => 'mobile_money',
                'provider' => $request->provider,
                'phone' => $request->phone,
                'amount' => $order->total_amount,
                'status' => 'pending',
                'transaction_id' => 'SP-' . uniqid(),
            ]);

            // ⚡ Appel API SingPay selon provider
            if ($request->provider === 'AIRTEL') {
                $response = $singPay->payAirtel([
                    'amount'      => $payment->amount,
                    'reference'   => $payment->transaction_id,
                    'phone'       => $payment->phone,
                    'callbackUrl' => route('singpay.webhook'),
                ]);
            } elseif ($request->provider === 'MOOV') {
                $response = $singPay->payMoov([
                    'amount'      => $payment->amount,
                    'reference'   => $payment->transaction_id,
                    'phone'       => $payment->phone,
                    'callbackUrl' => route('singpay.webhook'),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Provider not supported'
                ], 422);
            }

            $payment->update([
                'provider_reference' => $response['transaction_id'] ?? null,
                'payment_details'    => json_encode($response),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'USSD push envoyé au client',
                'data' => [
                    'payment' => $payment,
                    'singpay' => $response,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Payment initiation failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getPaymentStatus($orderId)
    {
        $order = Order::with('payment')->find($orderId);

        if (!$order || !$order->payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order->payment,
        ]);
    }
}

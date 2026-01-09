<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function processPayment(Request $request, $orderId)
    {
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:card,mobile_money,paypal,bank_transfer,cash_on_delivery',
            'card_number' => 'required_if:payment_method,card|string',
            'card_holder_name' => 'required_if:payment_method,card|string',
            'card_expiry' => 'required_if:payment_method,card|string',
            'card_cvv' => 'required_if:payment_method,card|string',
            'paypal_email' => 'required_if:payment_method,paypal|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::where('user_id', $request->user()->id)->find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be paid'
            ], 422);
        }

        // Check if payment already exists
        if ($order->payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment already processed for this order'
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Simulate payment processing
            $paymentStatus = $this->simulatePaymentGateway($request->payment_method);

            $payment = Payment::create([
                'order_id' => $order->id,
                'payment_method' => $request->payment_method,
                'amount' => $order->total_amount,
                'status' => $paymentStatus,
                'transaction_id' => 'TXN-' . strtoupper(uniqid()),
                'payment_details' => json_encode([
                    'method' => $request->payment_method,
                    'processed_at' => now()
                ])
            ]);

            if ($paymentStatus === 'completed') {
                $order->update(['status' => 'paid']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'data' => [
                    'payment' => $payment,
                    'order' => $order->load('payment')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getPaymentStatus($orderId)
    {
        $order = Order::with('payment')->find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if (!$order->payment) {
            return response()->json([
                'success' => false,
                'message' => 'No payment found for this order'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order->payment
        ]);
    }

    public function refund(Request $request, $paymentId)
    {
        $payment = Payment::whereHas('order', function($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->find($paymentId);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }

        if ($payment->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Only completed payments can be refunded'
            ], 422);
        }

        DB::beginTransaction();

        try {
            $payment->update([
                'status' => 'refunded',
                'refunded_at' => now()
            ]);

            $payment->order->update(['status' => 'refunded']);

            // Restore product stock
            foreach ($payment->order->items as $item) {
                $item->product->increment('stock', $item->quantity);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment refunded successfully',
                'data' => $payment
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Refund processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simulate payment gateway processing
     * In production, integrate with real payment gateway (Stripe, PayPal, etc.)
     */
    private function simulatePaymentGateway($paymentMethod)
    {
        // Simulate 90% success rate
        $random = rand(1, 10);
        
        if ($paymentMethod === 'cash_on_delivery') {
            return 'pending';
        }

        return $random <= 9 ? 'completed' : 'failed';
    }

    /**
     * Webhook handler for payment gateway callbacks
     */
    public function webhook(Request $request)
    {
        // Validate webhook signature (implementation depends on payment gateway)
        
        $transactionId = $request->input('transaction_id');
        $status = $request->input('status');

        $payment = Payment::where('transaction_id', $transactionId)->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }

        DB::beginTransaction();

        try {
            $payment->update(['status' => $status]);

            if ($status === 'completed') {
                $payment->order->update(['status' => 'paid']);
            } elseif ($status === 'failed') {
                $payment->order->update(['status' => 'payment_failed']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed'
            ], 500);
        }
    }
}
<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\SellerController;
use App\Http\Controllers\API\SingPayWebhookController;
use App\Http\Controllers\API\GoogleAuthController;
use App\Http\Controllers\API\FacebookAuthController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\ForgotPasswordController;
use App\Http\Controllers\API\ResetPasswordController;

use App\Http\Controllers\API\Admin\UserController;
use App\Http\Controllers\API\Admin\DashboardController;
use App\Http\Controllers\API\Admin\AdminOrderController;
#use App\Http\Controllers\API\Admin\AdminPaymentController;

use App\Http\Middleware\SellerMiddleware;

/*
|--------------------------------------------------------------------------
| API Routes v1 – E-commerce Gabon
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Routes PUBLIQUES (sans authentification)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {

    // Auth
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/auth/google', [GoogleAuthController::class, 'login']);
    Route::post('/auth/facebook', [FacebookAuthController::class, 'login']);

    // Produits (lecture seule)
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/featured', [ProductController::class, 'featured']);
    Route::get('/products/on-sale', [ProductController::class, 'onSale']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::get('/products/{id}/availability', [ProductController::class, 'checkAvailability']);

    // Catégories (lecture seule)
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/popular', [CategoryController::class, 'popular']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);

    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink']);//okay
    Route::post('/reset-password', [ResetPasswordController::class, 'reset']);
});

/*
|--------------------------------------------------------------------------
| Routes PROTÉGÉES (auth:sanctum)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Devenir vendeur
    Route::post('/seller/become', [SellerController::class, 'becomeSeller']);

    // Panier
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::put('/cart/items/{itemId}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{itemId}', [CartController::class, 'removeItem']);
    Route::delete('/cart/clear', [CartController::class, 'clear']);
    Route::post('/cart/validate', [CartController::class, 'validateCart']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']);

    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'markOneAsRead']);

    // Commandes
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::get('/orders/track/{orderNumber}', [OrderController::class, 'track']);
    Route::post('/orders/{id}/confirm-delivery', [OrderController::class, 'confirmDelivery']);

    // Confirmation commande par vendeur
    Route::post('/orders/{id}/confirm', [OrderController::class, 'confirmOrder']);

    /*
    |--------------------------------------------------------------------------
    | Paiements – SingPay
    |--------------------------------------------------------------------------
    */
    Route::post(
        '/payments/orders/{orderId}',
        [PaymentController::class, 'processPayment']
    );

    Route::get(
        '/payments/orders/{orderId}/status',
        [PaymentController::class, 'getPaymentStatus']
    );

    Route::post(
        '/payments/{paymentId}/refund',
        [PaymentController::class, 'refund']
    );
});

/*
|--------------------------------------------------------------------------
| Routes VENDEUR
|--------------------------------------------------------------------------
*/
Route::prefix('v1/seller')
    ->middleware(['auth:sanctum', SellerMiddleware::class])
    ->group(function () {

        Route::post('/products', [ProductController::class, 'store']);//okay -- online only
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    });

/*
|--------------------------------------------------------------------------
| Routes ADMIN
|--------------------------------------------------------------------------
*/
Route::prefix('v1/admin')
    ->middleware(['auth:sanctum', 'admin'])
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);//okay

        // Utilisateurs
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);

        Route::post('/users/{id}/activate', [UserController::class, 'activate']);
        Route::post('/users/{id}/deactivate', [UserController::class, 'deactivate']);

        // Vendeurs
        Route::get('/sellers', [UserController::class, 'sellers']);
        Route::post('/sellers/{id}/approve', [UserController::class, 'approveSeller']);//okay
        Route::post('/sellers/{id}/reject', [SellerController::class, 'rejectSeller']);//okay
        Route::post('/sellers/{id}/suspend', [UserController::class, 'suspendSeller']);

        // Commandes
        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
        Route::post('/orders/{id}/cancel', [AdminOrderController::class, 'cancel']);
        Route::post('/orders/{id}/force-complete', [AdminOrderController::class, 'forceComplete']);

        // Paiements
        // Route::get('/payments', [AdminPaymentController::class, 'index']);
        // Route::get('/payments/{id}', [AdminPaymentController::class, 'show']);
        // Route::post('/payments/{id}/refund', [AdminPaymentController::class, 'refund']);

        // Produits (admin)
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);

        // Catégories
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
    });

/*
|--------------------------------------------------------------------------
| Webhook SingPay (PUBLIC – NE PAS PROTÉGER)
|--------------------------------------------------------------------------
*/
Route::post(
    '/webhooks/singpay',
    [SingPayWebhookController::class, 'handle']
)->name('singpay.webhook');

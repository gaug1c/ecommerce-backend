<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\PaymentController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| API Routes pour E-commerce Gabon
|--------------------------------------------------------------------------
*/

// Routes API publiques (sans authentification)
Route::prefix('v1')->group(function () {
    
    // Authentification
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

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
});

// Routes API protégées (nécessitent authentification)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    
    // Authentification
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Panier
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::put('/cart/items/{itemId}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{itemId}', [CartController::class, 'removeItem']);
    Route::delete('/cart/clear', [CartController::class, 'clear']);
    Route::post('/cart/validate', [CartController::class, 'validateCart']);

    // Commandes
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::get('/orders/track/{orderNumber}', [OrderController::class, 'track']);
    Route::post('/orders/{id}/confirm-delivery', [OrderController::class, 'confirmDelivery']);

    // Paiements
    Route::post('/payments/orders/{orderId}', [PaymentController::class, 'processPayment']);
    Route::get('/payments/orders/{orderId}/status', [PaymentController::class, 'getPaymentStatus']);
    Route::post('/payments/{paymentId}/refund', [PaymentController::class, 'refund']);
});

// Routes Admin (nécessitent authentification + rôle admin)
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    
    // Gestion des produits
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    // Gestion des catégories
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
});

// Webhook pour les paiements (public mais sécurisé par signature)
Route::post('/webhooks/payment', [PaymentController::class, 'webhook']);
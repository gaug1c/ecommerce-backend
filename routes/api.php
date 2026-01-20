<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\SellerController;
use App\Http\Controllers\API\Admin\UserController;
use App\Http\Controllers\API\Admin\DashboardController;
use App\Http\Middleware\SellerMiddleware;

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
    Route::post('/register', [AuthController::class, 'register']);//  okay 
    Route::post('/login', [AuthController::class, 'login']);// okay

    // Produits (lecture seule)
    Route::get('/products', [ProductController::class, 'index']);//okay
    Route::get('/products/featured', [ProductController::class, 'featured']);//okay
    Route::get('/products/on-sale', [ProductController::class, 'onSale']);//okay
    Route::get('/products/{id}', [ProductController::class, 'show']);// okay
    Route::get('/products/{id}/availability', [ProductController::class, 'checkAvailability']);//okay

    // Catégories (lecture seule)
    Route::get('/categories', [CategoryController::class, 'index']); // okay
    Route::get('/categories/popular', [CategoryController::class, 'popular']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);//okay
});

// Routes API protégées (nécessitent authentification)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    
    // Authentification
    Route::post('/logout', [AuthController::class, 'logout']);// okay
    Route::get('/user', [AuthController::class, 'user']);// okay

    // Devenir un vendeur
    Route::post('/seller/become', [SellerController::class, 'becomeSeller']);//okay

    // Panier
    Route::get('/cart', [CartController::class, 'index']);//okay
    Route::post('/cart/items', [CartController::class, 'addItem']);// okay
    Route::put('/cart/items/{itemId}', [CartController::class, 'updateItem']);//okay
    Route::delete('/cart/items/{itemId}', [CartController::class, 'removeItem']);//okay
    Route::delete('/cart/clear', [CartController::class, 'clear']);//okay
    Route::post('/cart/validate', [CartController::class, 'validateCart']);//okay

    // Commandes
    Route::get('/orders', [OrderController::class, 'index']);//okay
    Route::post('/orders', [OrderController::class, 'store']);//okay
    Route::get('/orders/{id}', [OrderController::class, 'show']);//okay
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);//okay
    Route::get('/orders/track/{orderNumber}', [OrderController::class, 'track']);//okay -- 
    Route::post('/orders/{id}/confirm-delivery', [OrderController::class, 'confirmDelivery']);//okay

    //Confrimation de la commande par le vendeur
    
    Route::post('/orders/{id}/confirm', [OrderController::class, 'confirmOrder']);//okay

    // Paiements
    Route::post('/payments/orders/{orderId}', [PaymentController::class, 'processPayment']);//okay
    Route::get('/payments/orders/{orderId}/status', [PaymentController::class, 'getPaymentStatus']);//okay
    Route::post('/payments/{paymentId}/refund', [PaymentController::class, 'refund']);
});

// Routes API protégées pour les vendeurs

Route::prefix('v1/seller')->middleware(['auth:sanctum', SellerMiddleware::class])->group(function () {
    Route::post('/products', [ProductController::class, 'store']);//okay
    Route::put('/products/{id}', [ProductController::class, 'update']);//okay
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);//okay
});


// Routes Admin (nécessitent authentification + rôle admin)
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'admin'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    Route::get('/dashboard', [DashboardController::class, 'index']);//okay

    /*
    |--------------------------------------------------------------------------
    | Utilisateurs (CRUD + activation)
    |--------------------------------------------------------------------------
    */
    Route::get('/users', [UserController::class, 'index']);//okay
    Route::get('/users/{id}', [UserController::class, 'show']);//okay
    Route::post('/users', [UserController::class, 'store']);//okay
    Route::put('/users/{id}', [UserController::class, 'update']);//okay
    Route::delete('/users/{id}', [UserController::class, 'destroy']);//okay

    // Activation / Désactivation compte
    Route::post('/users/{id}/activate', [UserController::class, 'activate']);
    Route::post('/users/{id}/deactivate', [UserController::class, 'deactivate']);

    /*
    |--------------------------------------------------------------------------
    | Vendeurs
    |--------------------------------------------------------------------------
    */
    Route::get('/sellers', [UserController::class, 'sellers']);
    Route::post('/sellers/{id}/approve', [UserController::class, 'approveSeller']);
    Route::post('/sellers/{id}/suspend', [UserController::class, 'suspendSeller']);

    /*
    |--------------------------------------------------------------------------
    | Commandes (supervision admin)
    |--------------------------------------------------------------------------
    */
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
    Route::post('/orders/{id}/cancel', [AdminOrderController::class, 'cancel']);
    Route::post('/orders/{id}/force-complete', [AdminOrderController::class, 'forceComplete']);

    /*
    |--------------------------------------------------------------------------
    | Paiements
    |--------------------------------------------------------------------------
    */
    Route::get('/payments', [AdminPaymentController::class, 'index']);
    Route::get('/payments/{id}', [AdminPaymentController::class, 'show']);
    Route::post('/payments/{id}/refund', [AdminPaymentController::class, 'refund']);

    
    // Gestion des produits
    Route::post('/products', [ProductController::class, 'store']);// okay  création de produit par l'admin
    Route::put('/products/{id}', [ProductController::class, 'update']);// okay l'admin peut aussi modifier l'article d'un vendeur
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);// okay : l'admin peut aussi supprimer un produit créer par ale vendeur

    // Gestion des catégories
    Route::post('/categories', [CategoryController::class, 'store']);// okay -- voir l'erreur 
    Route::put('/categories/{id}', [CategoryController::class, 'update']);// okay
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);// okay mais voir pourquoi ça supprime par directement aussi l'élément depuis la base de donnée en ligne
});

// Webhook pour les paiements (public mais sécurisé par signature)
Route::post('/webhooks/payment', [PaymentController::class, 'webhook']);
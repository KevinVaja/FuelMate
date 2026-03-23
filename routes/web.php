<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AgentWithdrawalController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminAgentVerificationController;
use App\Http\Controllers\AdminWithdrawalController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\FuelRequestController;
use App\Http\Controllers\OrderCancellationController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => view('home'));

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

Route::get('/agent-login', [AuthController::class, 'showLoginAgent'])->name('login');
Route::post('/agent-login', [AuthController::class, 'login']);

Route::get('/admin-login', [AuthController::class, 'showLoginAdmin'])->name('login');
Route::post('/admin-login', [AuthController::class, 'login']);

Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::post('/send-otp', [FuelRequestController::class,'sendOtp'])
    ->middleware(['auth', 'role:user'])
    ->name('user.send.otp');
Route::post('/verify-otp', [FuelRequestController::class,'verifyOtp'])
    ->middleware(['auth', 'role:user'])
    ->name('user.verify.otp');

// User
Route::middleware(['auth', 'role:user'])->prefix('user')->group(function () {
    Route::get('/dashboard', [UserController::class, 'dashboard'])->name('user.dashboard');
    Route::get('/order', [UserController::class, 'orderForm'])->name('user.order');
    Route::post('/order/estimate', [BillingController::class, 'estimate'])->name('user.order.estimate');
    Route::post('/order', [UserController::class, 'placeOrder'])->name('user.order.place');
    Route::get('/track/{id}', [UserController::class, 'track'])->name('user.track');
    Route::post('/track/{id}/location', [UserController::class, 'updateLocation'])->name('user.track.location');
    Route::post('/track/{id}/cancel', [OrderCancellationController::class, 'customerCancel'])->name('user.orders.cancel');
    Route::get('/history', [UserController::class, 'history'])->name('user.history');
    Route::get('/support', [UserController::class, 'supportIndex'])->name('user.support');
    Route::post('/support', [UserController::class, 'supportStore'])->name('user.support.store');
});

Route::middleware(['auth', 'role:user'])->get('/orders/{id}/invoice', [BillingController::class, 'invoice'])
    ->name('orders.invoice');

// Agent
Route::middleware(['auth', 'role:agent'])->prefix('agent')->group(function () {
    Route::get('/dashboard', [AgentController::class, 'dashboard'])->name('agent.dashboard');
    Route::get('/requests', [AgentController::class, 'requests'])->name('agent.requests');
    Route::post('/requests/{id}/accept', [AgentController::class, 'accept'])->middleware('agent.approved')->name('agent.accept');
    Route::get('/active', [AgentController::class, 'active'])->name('agent.active');
    Route::post('/active/{id}/status', [AgentController::class, 'updateStatus'])->middleware('agent.approved')->name('agent.status');
    Route::post('/active/{id}/cancel', [OrderCancellationController::class, 'agentCancel'])->middleware('agent.approved')->name('agent.cancel');
    Route::post('/location', [AgentController::class, 'updateLocation'])->name('agent.location');
    Route::post('/toggle-availability', [AgentController::class, 'toggleAvailability'])->middleware('agent.approved')->name('agent.toggle');
    Route::get('/history', [AgentController::class, 'history'])->name('agent.history');
    Route::get('/earnings', [AgentController::class, 'earnings'])->middleware('agent.approved')->name('agent.earnings');
    Route::get('/withdrawals', [AgentWithdrawalController::class, 'index'])->middleware('agent.approved')->name('agent.withdrawals.index');
    Route::get('/withdrawals/create', [AgentWithdrawalController::class, 'create'])->middleware('agent.approved')->name('agent.withdrawals.create');
    Route::post('/withdrawals', [AgentWithdrawalController::class, 'store'])->middleware('agent.approved')->name('agent.withdrawals.store');
});

// Admin
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');

    Route::get('/orders', [AdminController::class, 'orders'])->name('admin.orders');
    Route::patch('/orders/{id}/cancel', [OrderCancellationController::class, 'adminCancel'])->name('admin.orders.cancel');
    Route::post('/orders/{id}/refunds/approve', [OrderCancellationController::class, 'approveRefund'])->name('admin.orders.refunds.approve');

    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
    Route::patch('/users/{id}/toggle', [AdminController::class, 'toggleUser'])->name('admin.users.toggle');

    Route::get('/agents/pending', [AdminAgentVerificationController::class, 'index'])->name('admin.agents.pending');
    Route::get('/agents/{id}/verify', [AdminAgentVerificationController::class, 'show'])->name('admin.agents.verify');
    Route::get('/agents/{id}/documents/{document}', [AdminAgentVerificationController::class, 'document'])->name('admin.agents.documents.show');
    Route::post('/agents/{id}/approve', [AdminAgentVerificationController::class, 'approve'])->name('admin.agents.verification.approve');
    Route::post('/agents/{id}/reject', [AdminAgentVerificationController::class, 'reject'])->name('admin.agents.verification.reject');

    Route::get('/agents', [AdminController::class, 'agents'])->name('admin.agents');
    Route::patch('/agents/{id}/approve', [AdminController::class, 'approveAgent'])->name('admin.agents.approve');
    Route::patch('/agents/{id}/reject', [AdminController::class, 'rejectAgent'])->name('admin.agents.reject');

    Route::get('/products', [AdminController::class, 'products'])->name('admin.products');
    Route::post('/products', [AdminController::class, 'storeProduct'])->name('admin.products.store');
    Route::patch('/products/{id}', [AdminController::class, 'updateProduct'])->name('admin.products.update');
    Route::patch('/products/{id}/toggle', [AdminController::class, 'toggleProduct'])->name('admin.products.toggle');

    Route::get('/delivery-charges', [AdminController::class, 'deliveryCharges'])->name('admin.delivery_charges');
    Route::patch('/delivery-charges/night-delivery', [AdminController::class, 'updateNightDeliveryPricing'])->name('admin.delivery_charges.night_delivery');
    Route::post('/delivery-charges', [AdminController::class, 'storeDeliveryCharge'])->name('admin.delivery_charges.store');
    Route::patch('/delivery-charges/{id}', [AdminController::class, 'updateDeliveryCharge'])->name('admin.delivery_charges.update');
    Route::delete('/delivery-charges/{id}', [AdminController::class, 'deleteDeliveryCharge'])->name('admin.delivery_charges.delete');

    Route::get('/billing', [BillingController::class, 'adminIndex'])->name('admin.billing.index');

    Route::get('/service-areas', [AdminController::class, 'serviceAreas'])->name('admin.service_areas');
    Route::post('/service-areas', [AdminController::class, 'storeServiceArea'])->name('admin.service_areas.store');
    Route::patch('/service-areas/{id}/toggle', [AdminController::class, 'toggleServiceArea'])->name('admin.service_areas.toggle');

    Route::get('/support', [AdminController::class, 'support'])->name('admin.support');
    Route::patch('/support/{id}/respond', [AdminController::class, 'respondTicket'])->name('admin.support.respond');

    Route::get('/withdrawals', [AdminWithdrawalController::class, 'index'])->name('admin.withdrawals.index');
    Route::post('/withdrawals/{id}/approve', [AdminWithdrawalController::class, 'approve'])->name('admin.withdrawals.approve');
    Route::post('/withdrawals/{id}/reject', [AdminWithdrawalController::class, 'reject'])->name('admin.withdrawals.reject');
    Route::post('/withdrawals/{id}/complete', [AdminWithdrawalController::class, 'markCompleted'])->name('admin.withdrawals.complete');
});

Route::get('/contact-us', [PageController::class, 'contact'])->name('contact.page');
Route::get('/petrol-delivery', [PageController::class, 'petrol'])->name('petrol.page');
Route::get('/diesel-delivery', [PageController::class, 'diesel'])->name('diesel.page');
Route::get('/emergency-support', [PageController::class, 'emergency'])->name('emergency.page');
Route::get('/24x7-availability', [PageController::class, 'availability'])->name('availability.page');

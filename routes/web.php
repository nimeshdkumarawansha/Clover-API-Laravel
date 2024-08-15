<?php

use App\Http\Controllers\CloverController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Route::get('/clover/redirect', [CloverController::class, 'redirectToClover'])->name('clover.redirect');
// Route::get('/clover/callback', [CloverController::class, 'handleCloverCallback'])->name('clover.callback');

Route::get('/clover/redirect', [CloverController::class, 'redirectToClover'])->name('clover.redirect');
Route::get('/clover/callback', [CloverController::class, 'handleCloverCallback'])->name('clover.callback');
Route::get('/clover/create-order', [CloverController::class, 'createCloverOrder'])->name('clover.create_order');
Route::post('/clover/add-item', [CloverController::class, 'addItemToOrder'])->name('clover.add_item');
Route::post('/clover/make-payment', [CloverController::class, 'makePayment'])->name('clover.make_payment');

// Route::post('/clover/payment', [CloverController::class, 'makePayment'])->name('clover.make_payment');
Route::match(['GET', 'POST'], '/clover/payment', [CloverController::class, 'makePayment'])->name('clover.make_payment');
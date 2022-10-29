<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\AdvanceController;
use App\Http\Controllers\AutoController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('service/order', [ServiceController::class, 'botget']);

Route::post('service/order', [ServiceController::class, 'bot']);

Route::post('service/order', [ServiceController::class, 'bot']);
Route::post('advance', [AdvanceController::class, 'bot']);

Route::post('auto', [AutoController::class, 'index']);

Route::resources([


    'pay' => PayController::class,
]);


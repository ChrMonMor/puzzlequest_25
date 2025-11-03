<?php
/*  Route::apiResource base, but needs middleware in controller __construct
Verb          Path                        Action  Route Name
GET           /users                      index   users.index
GET           /users/create               create  users.create
POST          /users                      store   users.store
GET           /users/{user}               show    users.show
GET           /users/{user}/edit          edit    users.edit
PUT|PATCH     /users/{user}               update  users.update
DELETE        /users/{user}               destroy users.destroy
*/
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FlagController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\HistoryFlagController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\QuestionOptionController;
use App\Http\Controllers\QuestionTypeController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\RunTypeController;


Route::get('/ping', function () {
    return response()->json(['message' => 'pong'], 200);
});

/*** User Routes ***/
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::get('/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth.api'])->group(function () {
    Route::post('/user', [AuthController::class, 'me']);
    Route::patch('/update-profile', [AuthController::class, 'updateProfile']);
    Route::delete('/delete', [AuthController::class, 'deleteAccount']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});

Route::middleware(['auth.api', 'refresh.token'])->post('/user', [AuthController::class, 'me']);

Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

/*** CRUD Routes for Runs ***/
Route::apiResource('runs', RunController::class);

Route::group(['middleware' => 'auth:api'], function () {
    Route::post('runs/{run_id}/flags/bulk', [RunController::class, 'addFlagsBulk']);
    Route::put('runs/{run_id}/flags/bulk', [RunController::class, 'updateFlagsBulk']);
    Route::delete('runs/{run_id}/flags/bulk', [RunController::class, 'deleteFlagsBulk']);
});

/*** RunTypes Routes ***/
Route::get('run-types', [RunTypeController::class, 'index']);
Route::get('run-types/{id}', [RunTypeController::class, 'show']);

/*** CRUD Routes for Questions ***/
Route::apiResource('questions', QuestionController::class);


/*** CRUD Routes for Flags ***/
Route::apiResource('flags', FlagController::class);
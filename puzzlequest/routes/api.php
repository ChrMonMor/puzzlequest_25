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

// -------------------- GUEST ROUTES --------------------
Route::prefix('guests')->group(function () {
    Route::post('/init', [AuthController::class, 'initGuest']); // public
    Route::post('/end', [AuthController::class, 'endGuest']); // requires guest token
    Route::get('/info', [AuthController::class, 'getGuestInfo']); // requires guest token
    Route::post('/upgrade', [AuthController::class, 'upgradeGuest']); // requires guest token
});

// -------------------- USER ROUTES --------------------
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


// -------------------- RUN ROUTES --------------------
Route::prefix('runs')->group(function () {
    Route::get('/', [RunController::class, 'index']);          // Public: list all runs
    Route::get('{id}', [RunController::class, 'show']);       // Public: show single run

    Route::middleware('auth:api')->group(function () {
        Route::post('/', [RunController::class, 'store']);    // Create run
        Route::put('{id}', [RunController::class, 'update']); // Update run
        Route::delete('{id}', [RunController::class, 'destroy']); // Delete run

        // Bulk operations for flags and questions linked to runs
        Route::post('{runId}/flags/bulk', [FlagController::class, 'bulkCreate']);
        Route::put('{runId}/flags/bulk', [FlagController::class, 'bulkUpdate']);
        Route::delete('{runId}/flags/bulk', [FlagController::class, 'bulkDelete']);

        Route::post('{runId}/questions/bulk', [QuestionController::class, 'bulkCreate']);
        Route::put('{runId}/questions/bulk', [QuestionController::class, 'bulkUpdate']);
        Route::delete('{runId}/questions/bulk', [QuestionController::class, 'bulkDelete']);
    });
});

// -------------------- FLAG ROUTES --------------------
Route::prefix('flags')->group(function () {
    Route::get('/', [FlagController::class, 'index']);        // Public
    Route::get('{id}', [FlagController::class, 'show']);     // Public

    Route::middleware('auth:api')->group(function () {
        Route::post('/', [FlagController::class, 'store']);
        Route::put('{id}', [FlagController::class, 'update']);
        Route::delete('{id}', [FlagController::class, 'destroy']);
    });
});

// -------------------- RUN TYPE ROUTES --------------------
Route::prefix('run-types')->group(function () {
    Route::get('/', [RunTypeController::class, 'index']);    // Public
    Route::get('{id}', [RunTypeController::class, 'show']); // Public

    Route::middleware('auth:api')->group(function () {
        Route::post('/', [RunTypeController::class, 'store']);
        Route::put('{id}', [RunTypeController::class, 'update']);
        Route::delete('{id}', [RunTypeController::class, 'destroy']);
    });
});

// -------------------- QUESTION ROUTES --------------------
Route::prefix('questions')->group(function () {
    Route::get('/', [QuestionController::class, 'index']);        // Public
    Route::get('{id}', [QuestionController::class, 'show']);     // Public

    Route::middleware('auth:api')->group(function () {
        Route::post('/', [QuestionController::class, 'store']);
        Route::put('{id}', [QuestionController::class, 'update']);
        Route::delete('{id}', [QuestionController::class, 'destroy']);
    });
});

// -------------------- QUESTION OPTION ROUTES --------------------
Route::prefix('question-options')->group(function () {
    Route::get('/', [QuestionOptionController::class, 'index']);       // Public
    Route::get('{id}', [QuestionOptionController::class, 'show']);     // Public

    Route::middleware('auth:api')->group(function () {
        Route::post('/', [QuestionOptionController::class, 'store']);
        Route::put('{id}', [QuestionOptionController::class, 'update']);
        Route::delete('{id}', [QuestionOptionController::class, 'destroy']);

        // Bulk options for a question
        Route::post('{questionId}/bulk', [QuestionOptionController::class, 'bulkCreate']);
        Route::put('{questionId}/bulk', [QuestionOptionController::class, 'bulkUpdate']);
        Route::delete('{questionId}/bulk', [QuestionOptionController::class, 'bulkDelete']);
    });
});

// -------------------- QUESTION TYPE ROUTES --------------------
Route::prefix('question-types')->group(function () {
    Route::get('/', [QuestionTypeController::class, 'index']);      // Public
    Route::get('{id}', [QuestionTypeController::class, 'show']);   // Public

    Route::middleware('auth:api')->group(function () {
        Route::post('/', [QuestionTypeController::class, 'store']);
        Route::put('{id}', [QuestionTypeController::class, 'update']);
        Route::delete('{id}', [QuestionTypeController::class, 'destroy']);
    });
});
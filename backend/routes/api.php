<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    AuthController,
    AdminDashboardController,
    ClientDashboardController,
    ClientController,
    ClientAccountController,
    TransactionController,
    ReportController,
    ProfitController,
    ExportController,
    StatementController,
    UserController,
    SettingsController,
    ActivityLogController,
    ClientPortalController
};
use Illuminate\Support\Facades\DB;

/* ============================================================
 |  PUBLIC ROUTES (No auth required)
 ============================================================ */

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

// ⚠️ TEMPORARY DEBUG ROUTE — Remove after fixing CSRF issue
Route::get('/debug-session', function () {
    return response()->json([
        'session_driver'      => config('session.driver'),
        'session_domain'      => config('session.domain'),
        'session_secure'      => config('session.secure'),
        'session_same_site'   => config('session.same_site'),
        'session_http_only'   => config('session.http_only'),
        'sanctum_stateful'    => config('sanctum.stateful'),
        'sanctum_guard'       => config('sanctum.guard'),
        'auth_default_guard'  => config('auth.defaults.guard'),
        'cors_credentials'    => config('cors.supports_credentials'),
        'frontend_url'        => env('FRONTEND_URL'),
        'app_url'             => env('APP_URL'),
        'env'                 => app()->environment(),
    ]);
});

// ⚠️ TEMPORARY DEBUG ROUTE — Enhanced session/cache diagnostics
Route::get('/debug-session', function () {
    return response()->json([
        'config_session_same_site' => config('session.same_site'),
        'env_SESSION_SAME_SITE'    => env('SESSION_SAME_SITE'),
        'getenv_SESSION_SAME_SITE' => getenv('SESSION_SAME_SITE'),
        'config_session_domain'    => config('session.domain'),
        'config_session_secure'    => config('session.secure'),

        // 🔑 CRITICAL: Check if cache file exists and what's in it
        'cache_config_exists' => file_exists(base_path('bootstrap/cache/config.php')),
        'cache_config_size'   => file_exists(base_path('bootstrap/cache/config.php'))
                                    ? filesize(base_path('bootstrap/cache/config.php'))
                                    : 0,

        // 🔑 Check if packages/services cache files exist
        'cache_packages_exists' => file_exists(base_path('bootstrap/cache/packages.php')),
        'cache_services_exists' => file_exists(base_path('bootstrap/cache/services.php')),

        // 🔑 App environment
        'app_env' => app()->environment(),
        'app_debug' => config('app.debug'),
    ]);
});

/* ============================================================
 |  AUTHENTICATED ROUTES
 ============================================================ */

Route::middleware('auth:sanctum')->group(function () {

    /* ---------------- Auth ---------------- */
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    /* ---------------- Client dashboard ---------------- */
    Route::get('/client/dashboard', [ClientDashboardController::class, 'index']);

    /* ---------------- Client portal ---------------- */
    Route::prefix('client')->group(function () {
        Route::get('/profile',      [ClientPortalController::class, 'profile']);
        Route::get('/accounts',     [ClientPortalController::class, 'accounts']);
        Route::get('/transactions', [ClientPortalController::class, 'transactions']);
        Route::get('/reports',      [ClientPortalController::class, 'reports']);
        Route::get('/statements',   [ClientPortalController::class, 'statements']);
    });

    /* ---------------- Transactions (read) ---------------- */
    Route::get('/transactions',               [TransactionController::class, 'index']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);

    /* ---------------- Reports ---------------- */
    Route::get('/reports/daily',   [ReportController::class, 'daily']);
    Route::get('/reports/weekly',  [ReportController::class, 'weekly']);
    Route::get('/reports/monthly', [ReportController::class, 'monthly']);

    /* ---------------- Profit ---------------- */
    Route::get('/profit/daily',          [ProfitController::class, 'daily']);
    Route::get('/profit/weekly',         [ProfitController::class, 'weekly']);
    Route::get('/profit/monthly',        [ProfitController::class, 'monthly']);
    Route::get('/profit/chart/daily',    [ProfitController::class, 'chartDaily']);
    Route::get('/profit/chart/monthly',  [ProfitController::class, 'chartMonthly']);

    /* ---------------- Statement ---------------- */
    Route::get('/statements',          [StatementController::class, 'index']);
    Route::get('/statements/download', [StatementController::class, 'download']);

    /* ---------------- Export (PDF / Excel / Print) ---------------- */
    Route::get('/export/pdf',   [ExportController::class, 'pdf']);
    Route::get('/export/excel', [ExportController::class, 'excel']);
    Route::get('/export/print', [ExportController::class, 'print']);

    /* ================================================================
     |  ADMIN ONLY
     ================================================================ */
    Route::middleware('role:ADMIN')->group(function () {

        Route::get('/admin/dashboard', [AdminDashboardController::class, 'index']);

        Route::apiResource('clients', ClientController::class);
        Route::apiResource('accounts', ClientAccountController::class);

        /* ---------------- Transactions (write) ---------------- */
        Route::post('/transactions',                 [TransactionController::class, 'store']);
        Route::put('/transactions/{transaction}',    [TransactionController::class, 'update']);
        Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy']);

        // Partial sales against a purchase
        Route::post('/transactions/{transaction}/partial-sales',
            [TransactionController::class, 'addPartialSale']);

        // Payments
        Route::post('/transactions/{transaction}/payments',
            [TransactionController::class, 'addPayment']);

        Route::get('/reports/client/{client}', [ReportController::class, 'client']);

        Route::apiResource('users', UserController::class);

        Route::get('/settings', [SettingsController::class, 'show']);
        Route::put('/settings', [SettingsController::class, 'update']);

        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    });
});


Route::get('/db-test', function () {
    try {
        DB::connection()->getPdo();

        return response()->json([
            'success' => true,
            'message' => 'Aiven MySQL connected successfully',
            'database' => DB::connection()->getDatabaseName(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
});


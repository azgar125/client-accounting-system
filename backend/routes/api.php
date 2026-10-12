<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

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

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
|
| Routes that do not require authentication.
|
*/


/*
|--------------------------------------------------------------------------
| AUTH - LOGIN
|--------------------------------------------------------------------------
|
| IMPORTANT:
| `web` middleware is required because AuthController uses:
|
| $request->session()->regenerate();
|
*/

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware([
        'web',
        'throttle:5,1',
    ]);


/*
|--------------------------------------------------------------------------
| TEMPORARY DATABASE TEST
|--------------------------------------------------------------------------
|
| Remove this route after confirming the database connection.
|
*/

Route::get('/db-test', function () {

    try {

        DB::connection()->getPdo();

        return response()->json([
            'success'  => true,
            'message'  => 'Aiven MySQL connected successfully',
            'database' => DB::connection()->getDatabaseName(),
        ]);

    } catch (\Throwable $e) {

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
});


/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES
|--------------------------------------------------------------------------
|
| `web`
|   -> Enables Laravel session.
|
| `auth:sanctum`
|   -> Checks Sanctum authenticated user.
|
*/

Route::middleware([
    'web',
    'auth:sanctum',
])->group(function () {


    /*
    |--------------------------------------------------------------------------
    | AUTH
    |--------------------------------------------------------------------------
    */

    Route::post('/auth/logout', [
        AuthController::class,
        'logout'
    ]);

    Route::get('/auth/me', [
        AuthController::class,
        'me'
    ]);


    /*
    |--------------------------------------------------------------------------
    | CLIENT DASHBOARD
    |--------------------------------------------------------------------------
    */

    Route::get('/client/dashboard', [
        ClientDashboardController::class,
        'index'
    ]);


    /*
    |--------------------------------------------------------------------------
    | CLIENT PORTAL
    |--------------------------------------------------------------------------
    */

    Route::prefix('client')->group(function () {

        Route::get('/profile', [
            ClientPortalController::class,
            'profile'
        ]);

        Route::get('/accounts', [
            ClientPortalController::class,
            'accounts'
        ]);

        Route::get('/transactions', [
            ClientPortalController::class,
            'transactions'
        ]);

        Route::get('/reports', [
            ClientPortalController::class,
            'reports'
        ]);

        Route::get('/statements', [
            ClientPortalController::class,
            'statements'
        ]);
    });


    /*
    |--------------------------------------------------------------------------
    | TRANSACTIONS - READ
    |--------------------------------------------------------------------------
    */

    Route::get('/transactions', [
        TransactionController::class,
        'index'
    ]);

    Route::get('/transactions/{transaction}', [
        TransactionController::class,
        'show'
    ]);


    /*
    |--------------------------------------------------------------------------
    | REPORTS
    |--------------------------------------------------------------------------
    */

    Route::get('/reports/daily', [
        ReportController::class,
        'daily'
    ]);

    Route::get('/reports/weekly', [
        ReportController::class,
        'weekly'
    ]);

    Route::get('/reports/monthly', [
        ReportController::class,
        'monthly'
    ]);


    /*
    |--------------------------------------------------------------------------
    | PROFIT
    |--------------------------------------------------------------------------
    */

    Route::get('/profit/daily', [
        ProfitController::class,
        'daily'
    ]);

    Route::get('/profit/weekly', [
        ProfitController::class,
        'weekly'
    ]);

    Route::get('/profit/monthly', [
        ProfitController::class,
        'monthly'
    ]);

    Route::get('/profit/chart/daily', [
        ProfitController::class,
        'chartDaily'
    ]);

    Route::get('/profit/chart/monthly', [
        ProfitController::class,
        'chartMonthly'
    ]);


    /*
    |--------------------------------------------------------------------------
    | STATEMENTS
    |--------------------------------------------------------------------------
    */

    Route::get('/statements', [
        StatementController::class,
        'index'
    ]);

    Route::get('/statements/download', [
        StatementController::class,
        'download'
    ]);


    /*
    |--------------------------------------------------------------------------
    | EXPORT
    |--------------------------------------------------------------------------
    */

    Route::get('/export/pdf', [
        ExportController::class,
        'pdf'
    ]);

    Route::get('/export/excel', [
        ExportController::class,
        'excel'
    ]);

    Route::get('/export/print', [
        ExportController::class,
        'print'
    ]);


    /*
    |--------------------------------------------------------------------------
    | ADMIN ONLY
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:ADMIN')->group(function () {


        /*
        |--------------------------------------------------------------------------
        | ADMIN DASHBOARD
        |--------------------------------------------------------------------------
        */

        Route::get('/admin/dashboard', [
            AdminDashboardController::class,
            'index'
        ]);


        /*
        |--------------------------------------------------------------------------
        | CLIENTS
        |--------------------------------------------------------------------------
        */

        Route::apiResource(
            'clients',
            ClientController::class
        );


        /*
        |--------------------------------------------------------------------------
        | ACCOUNTS
        |--------------------------------------------------------------------------
        */

        Route::apiResource(
            'accounts',
            ClientAccountController::class
        );


        /*
        |--------------------------------------------------------------------------
        | TRANSACTIONS - WRITE
        |--------------------------------------------------------------------------
        */

        Route::post('/transactions', [
            TransactionController::class,
            'store'
        ]);

        Route::put('/transactions/{transaction}', [
            TransactionController::class,
            'update'
        ]);

        Route::delete('/transactions/{transaction}', [
            TransactionController::class,
            'destroy'
        ]);


        /*
        |--------------------------------------------------------------------------
        | PARTIAL SALES
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/transactions/{transaction}/partial-sales',
            [
                TransactionController::class,
                'addPartialSale'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | PAYMENTS
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/transactions/{transaction}/payments',
            [
                TransactionController::class,
                'addPayment'
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | CLIENT REPORT
        |--------------------------------------------------------------------------
        */

        Route::get('/reports/client/{client}', [
            ReportController::class,
            'client'
        ]);


        /*
        |--------------------------------------------------------------------------
        | USERS
        |--------------------------------------------------------------------------
        */

        Route::apiResource(
            'users',
            UserController::class
        );


        /*
        |--------------------------------------------------------------------------
        | SETTINGS
        |--------------------------------------------------------------------------
        */

        Route::get('/settings', [
            SettingsController::class,
            'show'
        ]);

        Route::put('/settings', [
            SettingsController::class,
            'update'
        ]);


        /*
        |--------------------------------------------------------------------------
        | ACTIVITY LOGS
        |--------------------------------------------------------------------------
        */

        Route::get('/activity-logs', [
            ActivityLogController::class,
            'index'
        ]);
    });
});

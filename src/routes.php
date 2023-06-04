<?php

use Illuminate\Support\Facades\Route;
use Rgxfox\Jira\Http\Controllers\{StatController, CheckController};
use Rgxfox\Jira\Http\Middleware\GoogleSpreadsheetMiddleware;


Route::prefix(config('foxjira.prefix', 'kd'))->middleware(GoogleSpreadsheetMiddleware::class)->group(function () {
    Route::get('/sprint/stat', [StatController::class, 'stat']);
    Route::get('/sprint/issues', [StatController::class, 'issues']);
    Route::get('/sprint/check', CheckController::class);
});

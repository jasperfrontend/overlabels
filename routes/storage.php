<?php
//
//use App\Http\Controllers\StorageConnectionController;
//use App\Http\Controllers\StorageBrowserController;
//use Illuminate\Support\Facades\Route;
//
//Route::middleware(['auth', 'verified', 'storage.refresh'])->group(function () {
//    Route::prefix('storage')->name('storage.')->group(function () {
//        Route::get('/app', [StorageConnectionController::class, 'index'])->name('index');
//        Route::get('/connect/{provider}', [StorageConnectionController::class, 'connect'])->name('connect');
//        Route::get('/callback/{provider}', [StorageConnectionController::class, 'callback'])->name('callback');
//        Route::patch('/accounts/{account}/disconnect', [StorageConnectionController::class, 'disconnect'])->name('disconnect');
//        Route::delete('/accounts/{account}', [StorageConnectionController::class, 'destroy'])->name('destroy');
//
//        Route::prefix('accounts/{account}')->name('accounts.')->group(function () {
//            Route::get('/files', [StorageBrowserController::class, 'listFiles'])->name('files.list');
//            Route::get('/files/{fileId}', [StorageBrowserController::class, 'getFile'])->name('files.get');
//            Route::get('/files/{fileId}/download-url', [StorageBrowserController::class, 'getDownloadUrl'])->name('files.download-url');
//            Route::get('/files/{fileId}/shareable-url', [StorageBrowserController::class, 'getShareableUrl'])->name('files.shareable-url');
//            Route::get('/files/{fileId}/thumbnail', [StorageBrowserController::class, 'getThumbnail'])->name('files.thumbnail');
//            Route::get('/quota', [StorageBrowserController::class, 'getQuota'])->name('quota');
//            Route::get('/validate', [StorageBrowserController::class, 'validateConnection'])->name('validate');
//        });
//    });
//});

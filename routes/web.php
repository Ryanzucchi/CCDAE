<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/distritos/geojson', \App\Http\Controllers\Api\DistritosGeojsonController::class)->name('api.distritos.geojson');

Route::get('/export-distritos-temp', function () {
    return response()->json(\App\Models\Distrito::all());
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::get('/api/infraestrutura/geojson', \App\Http\Controllers\Api\InfraestruturaGeojsonController::class)->name('api.infraestrutura.geojson');

Route::get('/test-login', function() {
    $u = \App\Models\User::where('email', 'ryan@admin.com')->first();
    $attempt = Auth::attempt(['email' => 'ryan@admin.com', 'password' => '12345678']);
    return response()->json([
        'user' => $u,
        'attempt' => $attempt,
        'db_host' => env('DB_HOST'),
        'hash_check' => $u ? Hash::check('12345678', $u->password) : false,
    ]);
});

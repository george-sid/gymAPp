<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Auth\Login;
use App\Livewire\Clients;
use App\Livewire\Dashboard;
use App\Livewire\Services;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Login::class)->name('login');
});

Route::middleware(['auth', 'staff'])->group(function () {
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::livewire('/', Dashboard::class)->name('dashboard');

    Route::livewire('/clients', Clients\Index::class)->name('clients.index');
    Route::livewire('/clients/create', Clients\Edit::class)->name('clients.create');
    Route::livewire('/clients/{client}', Clients\Show::class)->name('clients.show');
    Route::livewire('/clients/{client}/edit', Clients\Edit::class)->name('clients.edit');

    Route::livewire('/services', Services\Index::class)->name('services.index');
});

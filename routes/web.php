<?php

use App\Models\Group;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::view('friends', 'friends.index')->name('friends.index');

    Route::view('groups', 'groups.index')->name('groups.index');

    Route::get('groups/{group}', function (Group $group) {
        return view('groups.show', ['group' => $group]);
    })->name('groups.show');
});

require __DIR__.'/settings.php';

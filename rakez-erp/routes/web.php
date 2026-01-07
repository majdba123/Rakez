<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Admin notifications test page
Route::get('/admin/notifications', function () {
    return view('admin.notifications');
});

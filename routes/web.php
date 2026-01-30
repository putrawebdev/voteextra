<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/admin/dashboard', 'pages::admin.dashboard')->name('admin.dashboard');
Route::livewire('/admin/ekstramanage', 'pages::admin.ekstramanage')->name('admin.ekstramanage');

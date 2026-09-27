<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\ChildrenController;
use App\Http\Controllers\ClanController;
use App\Http\Controllers\MarriageController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\TreeController;
use Illuminate\Support\Facades\Route;

// Local only, no login: served on 127.0.0.1 (docs/architecture-local.md §2).

Route::get('/', [ClanController::class, 'index'])->name('clans.index');
Route::get('/clans/create', [ClanController::class, 'create'])->name('clans.create');
Route::post('/clans', [ClanController::class, 'store'])->name('clans.store');
Route::get('/clans/{clan}/settings', [ClanController::class, 'edit'])->name('clans.edit');
Route::put('/clans/{clan}', [ClanController::class, 'update'])->name('clans.update');
Route::post('/clans/{clan}/founders', [ClanController::class, 'setFounders'])->name('clans.founders');
Route::delete('/clans/{clan}', [ClanController::class, 'destroy'])->name('clans.destroy');
Route::post('/clans/{clan}/restore', [ClanController::class, 'restore'])->withTrashed()->name('clans.restore');
Route::post('/current-clan', [ClanController::class, 'switch'])->name('clans.switch');

Route::get('/people', [PersonController::class, 'index'])->name('people.index');
Route::get('/people/create', [PersonController::class, 'create'])->name('people.create');
Route::post('/people', [PersonController::class, 'store'])->name('people.store');
Route::get('/people/{person}', [PersonController::class, 'show'])->name('people.show');
Route::get('/people/{person}/edit', [PersonController::class, 'edit'])->name('people.edit');
Route::put('/people/{person}', [PersonController::class, 'update'])->name('people.update');
Route::delete('/people/{person}', [PersonController::class, 'destroy'])->name('people.destroy');
Route::post('/people/{person}/move', [PersonController::class, 'move'])->name('people.move');

Route::get('/children', [ChildrenController::class, 'create'])->name('children.create');
Route::post('/people/{person}/children', [ChildrenController::class, 'store'])->name('children.store');

Route::get('/spouse-search', [MarriageController::class, 'search'])->name('marriages.search');
Route::post('/people/{person}/marriages', [MarriageController::class, 'store'])->name('marriages.store');
Route::put('/marriages/{marriage}', [MarriageController::class, 'update'])->name('marriages.update');
Route::delete('/marriages/{marriage}', [MarriageController::class, 'destroy'])->name('marriages.destroy');

Route::get('/tree', [TreeController::class, 'index'])->name('tree');
Route::get('/tree/data', [TreeController::class, 'data'])->name('tree.data');

Route::get('/print', [PrintController::class, 'index'])->name('print');
Route::get('/print/sheet', [PrintController::class, 'sheet'])->name('print.sheet');

Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
Route::post('/backups', [BackupController::class, 'store'])->name('backups.store');
Route::post('/backups/restore', [BackupController::class, 'restore'])->name('backups.restore');

Route::post('/photos', [PhotoController::class, 'store'])->name('photos.store');
Route::put('/photos/{photo}', [PhotoController::class, 'update'])->name('photos.update');
Route::post('/photos/{photo}/primary', [PhotoController::class, 'primary'])->name('photos.primary');
Route::delete('/photos/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');

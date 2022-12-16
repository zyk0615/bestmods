<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::group(['middleware' => ['auth0.authenticate.optional']], function () {
    Route::get('/', 'HomeController@index');
    Route::get('retrieve', 'HomeController@retrieve');
    Route::get('/view/{mod}/{view?}', 'HomeController@viewMod');
});

Route::match(['get', 'post'], '/create/{type?}', 'HomeController@create')->middleware(['auth0.authenticate']);

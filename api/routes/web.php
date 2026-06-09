<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->post('/auth/login', 'AuthController@login');

$router->get('/public/get-wilayah', 'PublicWilayahController@getWilayah');
$router->post('/public/test-klasifikasi-alamat', 'PublicAddressClassifierController@test');

$router->group(['middleware' => 'auth.token'], function () use ($router) {
    $router->get('/activity-logs', 'ActivityLogController@index');
    $router->get('/activity-logs/summary', 'ActivityLogController@summary');
    $router->get('/activity-logs/{logId}', 'ActivityLogController@show');
    $router->get('/log-aktivitas', 'ActivityLogController@index');
    $router->get('/log-aktivitas/summary', 'ActivityLogController@summary');
    $router->get('/log-aktivitas/{logId}', 'ActivityLogController@show');

    $router->get('/users', 'UserController@index');
    $router->post('/users', 'UserController@store');
    $router->post('/users/{userId}/reset-password', 'UserController@resetPassword');
    $router->get('/users/{userId}', 'UserController@show');
    $router->put('/users/{userId}', 'UserController@update');
    $router->patch('/users/{userId}', 'UserController@update');
    $router->delete('/users/{userId}', 'UserController@destroy');

    $router->get('/wilayah', 'WilayahController@index');
    $router->post('/wilayah', 'WilayahController@store');
    $router->get('/wilayah/{wilayahId}', 'WilayahController@show');
    $router->put('/wilayah/{wilayahId}', 'WilayahController@update');
    $router->patch('/wilayah/{wilayahId}', 'WilayahController@update');
    $router->delete('/wilayah/{wilayahId}', 'WilayahController@destroy');

    // Dashboard Data Master
    $router->get('/dashboard/summary', 'DashboardController@getSummary');
    $router->get('/dashboard/chart', 'DashboardController@getChart');
    $router->get('/dashboard/wilayah-tree', 'DashboardController@getWilayahTree');
    $router->get('/dashboard/map/wilayah-points', 'DashboardMapController@wilayahPoints');
    $router->get('/dashboard/map/wilayah/{wilayahId}/mahasiswa', 'DashboardMapController@mahasiswaByWilayah');
    $router->get('/dashboard/map/mahasiswa-search', 'DashboardMapController@searchMahasiswa');

    $router->get('/mahasiswa', 'MahasiswaController@index');
    $router->post('/mahasiswa/import/scan', 'MahasiswaImportController@scan');
    $router->post('/mahasiswa/import/confirm', 'MahasiswaImportController@confirm');
    $router->get('/mahasiswa/import/template', 'MahasiswaImportController@downloadTemplate');
    $router->get('/mahasiswa/{mahasiswaId}', 'MahasiswaController@show');
    $router->post('/mahasiswa', 'MahasiswaController@store');
    $router->put('/mahasiswa/{mahasiswaId}', 'MahasiswaController@update');
    $router->patch('/mahasiswa/{mahasiswaId}', 'MahasiswaController@update');
    $router->delete('/mahasiswa/{mahasiswaId}', 'MahasiswaController@destroy');

    // Visitasi Rencana (dosen only — enforced in repository)
    $router->get('/visitasi', 'VisitasiController@index');
    $router->post('/visitasi', 'VisitasiController@store');
    $router->get('/visitasi/export-rekap-excel', 'SimulasiRuteController@exportRekapExcel');
    $router->get('/visitasi/{rencanaId}', 'VisitasiController@show');
    $router->put('/visitasi/{rencanaId}', 'VisitasiController@update');
    $router->patch('/visitasi/{rencanaId}', 'VisitasiController@update');
    $router->delete('/visitasi/{rencanaId}', 'VisitasiController@destroy');
    $router->post('/visitasi/{rencanaId}/selesai', 'VisitasiController@markSelesai');

    // Peserta Visitasi
    $router->get('/visitasi/{rencanaId}/peserta', 'VisitasiController@pesertaIndex');
    $router->post('/visitasi/{rencanaId}/peserta', 'VisitasiController@pesertaStore');
    $router->put('/visitasi/{rencanaId}/peserta/{pesertaId}', 'VisitasiController@pesertaUpdate');
    $router->patch('/visitasi/{rencanaId}/peserta/{pesertaId}', 'VisitasiController@pesertaUpdate');
    $router->delete('/visitasi/{rencanaId}/peserta/{pesertaId}', 'VisitasiController@pesertaDestroy');

    // Simulasi Rute
    $router->post('/visitasi/{rencanaId}/simulasi', 'SimulasiRuteController@simulate');
    $router->post('/visitasi/simultan', 'SimulasiRuteController@createAndSimulate');
    $router->get('/visitasi/{rencanaId}/rute', 'SimulasiRuteController@getRute');
    $router->get('/visitasi/{rencanaId}/rute/history', 'SimulasiRuteController@getRuteHistory');
    $router->get('/visitasi/{rencanaId}/rute/{ruteId}/print-data', 'SimulasiRuteController@getPrintData');

    // Log Simulasi (admin only — enforced in controller)
    $router->get('/log-simulasi', 'SimulasiRuteController@logSimulasi');
});

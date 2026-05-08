<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->withFacades();
$app->withEloquent();
// Bootstrap DB manually if connection() fails
$app->make('db'); 

$parentId = '001033004001';
$expectedLength = strlen($parentId) + 3;
$maxId = App\Models\Wilayah::query()
    ->where('wilayah_id', 'like', $parentId . '%')
    ->whereRaw('char_length(wilayah_id) = ?', [$expectedLength])
    ->max('wilayah_id');
var_dump($maxId);

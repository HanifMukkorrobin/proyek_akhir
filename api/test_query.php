<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->withFacades();
$app->withEloquent();
$ids = ['001033004001', '001033004002'];
$childCounts = App\Models\Wilayah::query()
    ->selectRaw('LEFT(wilayah_id, LENGTH(wilayah_id) - 3) as parent_id, COUNT(*) as count')
    ->whereRaw('LENGTH(wilayah_id) > 3')
    ->whereNull('dihapus_pada')
    ->where(function ($q) use ($ids) {
        foreach ($ids as $id) {
            $q->orWhere('wilayah_id', 'LIKE', $id . '___');
        }
    })
    ->groupByRaw('LEFT(wilayah_id, LENGTH(wilayah_id) - 3)')
    ->get()
    ->pluck('count', 'parent_id')
    ->toArray();
print_r($childCounts);

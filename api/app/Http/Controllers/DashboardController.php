<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Repositories\DashboardRepository;

class DashboardController extends Controller
{
    private $repository;

    public function __construct(DashboardRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getSummary(Request $request)
    {
        $angkatan = $this->resolveAngkatanFilter($request);

        return response()->json([
            'code' => 200,
            'data' => $this->repository->getSummary($angkatan),
            'message' => 'Success',
            'errors' => new \stdClass()
        ]);
    }

    public function getChart(Request $request)
    {
        $groupBy = $request->query('group_by', 'provinsi');
        $limit = $request->query('limit');

        if (!in_array($groupBy, ['provinsi', 'kabupaten'])) {
            return response()->json([
                'code' => 400,
                'data' => new \stdClass(),
                'message' => 'Parameter group_by harus berupa provinsi atau kabupaten.',
                'errors' => new \stdClass()
            ], 400);
        }

        if ($limit !== null && $limit !== '') {
            $limit = (int) $limit;

            if ($limit < 1 || $limit > 100) {
                return response()->json([
                    'code' => 400,
                    'data' => new \stdClass(),
                    'message' => 'Parameter limit harus berada di rentang 1 sampai 100.',
                    'errors' => new \stdClass()
                ], 400);
            }
        } else {
            $limit = null;
        }

        $angkatan = $this->resolveAngkatanFilter($request);

        return response()->json([
            'code' => 200,
            'data' => $this->repository->getChartData($groupBy, $limit, $angkatan),
            'message' => 'Success',
            'errors' => new \stdClass()
        ]);
    }

    public function getWilayahTree(Request $request)
    {
        $parentId = $request->query('parent_id', '');
        $rootLevel = $request->query('root_level', 'root');
        $angkatan = $this->resolveAngkatanFilter($request);
        
        return response()->json([
            'code' => 200,
            'data' => $this->repository->getWilayahTree($parentId, $rootLevel, $angkatan),
            'message' => 'Success',
            'errors' => new \stdClass()
        ]);
    }

    private function resolveAngkatanFilter(Request $request): ?int
    {
        $value = $request->query('angkatan', $request->query('tahun'));

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (!preg_match('/^\d{4}$/', trim((string) $value))) {
            return null;
        }

        $year = (int) $value;
        $maxYear = (int) date('Y') + 1;

        if ($year < 2000 || $year > $maxYear) {
            return null;
        }

        return $year;
    }
}

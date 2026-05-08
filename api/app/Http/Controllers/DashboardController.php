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

    public function getSummary()
    {
        return response()->json([
            'code' => 200,
            'data' => $this->repository->getSummary(),
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

        return response()->json([
            'code' => 200,
            'data' => $this->repository->getChartData($groupBy, $limit),
            'message' => 'Success',
            'errors' => new \stdClass()
        ]);
    }

    public function getWilayahTree(Request $request)
    {
        $parentId = $request->query('parent_id', '');
        $rootLevel = $request->query('root_level', 'root');
        
        return response()->json([
            'code' => 200,
            'data' => $this->repository->getWilayahTree($parentId, $rootLevel),
            'message' => 'Success',
            'errors' => new \stdClass()
        ]);
    }
}

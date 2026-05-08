<?php

namespace App\Http\Controllers;

use App\Repositories\PublicWilayahRepository;
use Illuminate\Http\Request;

class PublicWilayahController extends Controller
{
    private PublicWilayahRepository $publicWilayahRepository;

    public function __construct(PublicWilayahRepository $publicWilayahRepository)
    {
        $this->publicWilayahRepository = $publicWilayahRepository;
    }

    public function getWilayah(Request $request)
    {
        $wilayahId = trim((string) $request->query('wilayah_id', ''));
        $cari = trim((string) $request->query('cari', ''));

        $data = $this->publicWilayahRepository->getWilayah($wilayahId, $cari);

        return $this->successResponse($data, 'Data wilayah berhasil diambil.');
    }
}
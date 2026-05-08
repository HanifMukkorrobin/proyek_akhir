<?php

namespace App\Http\Controllers;

use App\Repositories\MahasiswaRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class MahasiswaImportController extends Controller
{
    private MahasiswaRepository $mahasiswaRepository;

    public function __construct(MahasiswaRepository $mahasiswaRepository)
    {
        $this->mahasiswaRepository = $mahasiswaRepository;
    }

    public function scan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xlsm,xltx,csv,txt',
            'use_external_geocoding' => 'nullable',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal.', 422, $validator->errors());
        }

        $file = $request->file('file');

        if ($file === null) {
            return $this->errorResponse('File import wajib diunggah.', 422);
        }

        $useExternalGeocoding = $this->resolveUseExternalFromRequest($request);

        if ($request->exists('use_external_geocoding') && $useExternalGeocoding === null) {
            return $this->errorResponse('Parameter use_external_geocoding harus bernilai boolean.', 422);
        }

        try {
            $result = $this->mahasiswaRepository->scan(
                $file,
                $useExternalGeocoding
            );

            return $this->successResponse($result, 'Proses scan import selesai.');
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 500);
        } catch (Throwable $exception) {
            return $this->errorResponse('Terjadi kesalahan saat scan import.', 500);
        }
    }

    public function confirm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'import_id' => 'required|uuid',
            'baris' => 'nullable|array',
            'baris.*' => 'integer|min:2',
            'dibuat_oleh_user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal.', 422, $validator->errors());
        }

        $payload = $validator->validated();

        try {
            $result = $this->mahasiswaRepository->confirm(
                (string) $payload['import_id'],
                $payload['baris'] ?? null,
                array_key_exists('dibuat_oleh_user_id', $payload)
                    ? (int) $payload['dibuat_oleh_user_id']
                    : null
            );

            return $this->successResponse($result, 'Konfirmasi import selesai.');
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();

            if (str_contains(mb_strtolower($message), 'tidak ditemukan')) {
                return $this->errorResponse($message, 404);
            }

            if (str_contains(mb_strtolower($message), 'sudah pernah dikonfirmasi')) {
                return $this->errorResponse($message, 409);
            }

            return $this->errorResponse($message, 500);
        } catch (Throwable $exception) {
            return $this->errorResponse('Terjadi kesalahan saat konfirmasi import.', 500);
        }
    }

    public function downloadTemplate()
    {
        $content = $this->mahasiswaRepository->generateTemplateXlsx();
        $fileName = 'template-import-mahasiswa.xlsx';

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    private function resolveUseExternalFromRequest(Request $request): ?bool
    {
        if (!$request->exists('use_external_geocoding')) {
            return null;
        }

        return filter_var(
            $request->input('use_external_geocoding'),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );
    }
}

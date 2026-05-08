<?php

namespace App\Repositories;

use App\Models\Mahasiswa;
use App\Models\Wilayah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use ZipArchive;

class MahasiswaRepository
{
    private AddressWilayahClassifierRepository $addressWilayahClassifierRepository;

    private string $draftDirectory;

    public function __construct(AddressWilayahClassifierRepository $addressWilayahClassifierRepository)
    {
        $this->addressWilayahClassifierRepository = $addressWilayahClassifierRepository;
        $this->draftDirectory = storage_path('app/imports/mahasiswa');
    }

    public function paginate(array $filters = []): array
    {
        $query = Mahasiswa::query()
            ->with('wilayah')
            ->orderByDesc('dibuat_pada')
            ->orderBy('mahasiswa_id');

        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('nama', 'ILIKE', '%' . $search . '%')
                    ->orWhere('alamat', 'ILIKE', '%' . $search . '%')
                    ->orWhere('mahasiswa_id', 'ILIKE', '%' . $search . '%');
            });
        }

        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 10);

        $pagination = paginate_builder($query, $page, $perPage);

        return [
            'data' => array_map(function (Mahasiswa $mahasiswa) {
                return $this->transform($mahasiswa);
            }, $pagination['data']->all()),
            'halaman_sekarang' => $pagination['halaman_sekarang'],
            'per_halaman' => $pagination['per_halaman'],
            'total_data' => $pagination['total_data'],
            'total_halaman' => $pagination['total_halaman'],
        ];
    }

    public function find(string $mahasiswaId): ?array
    {
        $mahasiswa = Mahasiswa::query()
            ->with('wilayah')
            ->where('mahasiswa_id', $mahasiswaId)
            ->first();

        if ($mahasiswa === null) {
            return null;
        }

        return $this->transform($mahasiswa);
    }

    public function create(array $payload, ?bool $useExternalGeocoding = null): array
    {
        $mahasiswaId = (string) Str::uuid();

        $alamat = trim((string) ($payload['alamat'] ?? ''));
        $geocodingPayload = $this->resolveFromAlamat($alamat, $useExternalGeocoding);

        $resolvedAddress = $this->applyDefaultAddressIfInvalid($alamat, $geocodingPayload);
        $alamat = $resolvedAddress['alamat'];
        $geocodingPayload = $resolvedAddress['geocoding_payload'];

        $mahasiswa = Mahasiswa::query()->create([
            'mahasiswa_id' => $mahasiswaId,
            'nama' => trim((string) ($payload['nama'] ?? '')),
            'alamat' => $alamat,
            'wilayah_id' => $geocodingPayload['wilayah_id'],
            'latitude' => $geocodingPayload['latitude'],
            'longitude' => $geocodingPayload['longitude'],
            'dibuat_oleh_user_id' => $payload['dibuat_oleh_user_id'] ?? null,
            'diubah_oleh_user_id' => $payload['diubah_oleh_user_id'] ?? null,
        ]);

        $mahasiswa->load('wilayah');

        return [
            'data' => $this->transform($mahasiswa),
            'geocoding_reference' => $geocodingPayload['reference'],
        ];
    }

    public function update(string $mahasiswaId, array $payload, ?bool $useExternalGeocoding = null): ?array
    {
        $mahasiswa = Mahasiswa::query()
            ->where('mahasiswa_id', $mahasiswaId)
            ->first();

        if ($mahasiswa === null) {
            return null;
        }

        $geocodingReference = null;

        if (array_key_exists('nama', $payload)) {
            $mahasiswa->nama = trim((string) $payload['nama']);
        }

        if (array_key_exists('alamat', $payload)) {
            $alamat = trim((string) $payload['alamat']);
            $geocodingPayload = $this->resolveFromAlamat($alamat, $useExternalGeocoding);

            $resolvedAddress = $this->applyDefaultAddressIfInvalid($alamat, $geocodingPayload);
            $alamat = $resolvedAddress['alamat'];
            $geocodingPayload = $resolvedAddress['geocoding_payload'];

            $mahasiswa->alamat = $alamat;
            $mahasiswa->wilayah_id = $geocodingPayload['wilayah_id'];
            $mahasiswa->latitude = $geocodingPayload['latitude'];
            $mahasiswa->longitude = $geocodingPayload['longitude'];
            $geocodingReference = $geocodingPayload['reference'];
        }

        if (array_key_exists('diubah_oleh_user_id', $payload)) {
            $mahasiswa->diubah_oleh_user_id = $payload['diubah_oleh_user_id'];
        }

        $mahasiswa->save();
        $mahasiswa->load('wilayah');

        $result = [
            'data' => $this->transform($mahasiswa),
        ];

        if ($geocodingReference !== null) {
            $result['geocoding_reference'] = $geocodingReference;
        }

        return $result;
    }

    public function delete(string $mahasiswaId, ?int $deletedByUserId = null): bool
    {
        $mahasiswa = Mahasiswa::query()
            ->where('mahasiswa_id', $mahasiswaId)
            ->first();

        if ($mahasiswa === null) {
            return false;
        }

        if ($deletedByUserId !== null) {
            $mahasiswa->dihapus_oleh_user_id = $deletedByUserId;
            $mahasiswa->save();
        }

        $mahasiswa->delete();

        return true;
    }

    public function scan(UploadedFile $file, ?bool $useExternalGeocoding = null): array
    {
        $parsedRows = $this->parseImportFile($file);

        if (empty($parsedRows)) {
            throw new InvalidArgumentException('File import tidak memiliki data.');
        }

        $resolvedUseExternal = $useExternalGeocoding;

        if ($resolvedUseExternal === null) {
            $resolvedUseExternal = $this->resolveUseExternalFromEnv();
        }

        $scannedRows = [];
        $importableCount = 0;
        $notImportableCount = 0;

        foreach ($parsedRows as $row) {
            $scanResult = $this->scanImportRow($row, $resolvedUseExternal);

            if ($scanResult['status_import'] === 'dapat_import') {
                $importableCount++;
            } else {
                $notImportableCount++;
            }

            $scannedRows[] = $scanResult;
        }

        $importId = (string) Str::uuid();

        $draft = [
            'import_id' => $importId,
            'created_at' => date(DATE_ATOM),
            'is_confirmed' => false,
            'use_external_geocoding' => $resolvedUseExternal,
            'ringkasan' => [
                'total_data' => count($scannedRows),
                'dapat_import' => $importableCount,
                'tidak_dapat_import' => $notImportableCount,
            ],
            'data' => $scannedRows,
        ];

        $this->storeImportDraft($importId, $draft);

        return [
            'import_id' => $importId,
            'ringkasan' => $draft['ringkasan'],
            'data' => $scannedRows,
        ];
    }

    public function confirm(string $importId, ?array $selectedRows = null, ?int $defaultCreatedByUserId = null): array
    {
        $draft = $this->loadImportDraft($importId);

        if (($draft['is_confirmed'] ?? false) === true) {
            throw new RuntimeException('Draft import sudah pernah dikonfirmasi.');
        }

        $selectedSet = $this->buildSelectedSet($selectedRows);

        $imported = [];
        $failed = [];
        $skipped = [];

        foreach ($draft['data'] as $row) {
            $rowNumber = (int) ($row['baris'] ?? 0);

            if (($row['status_import'] ?? 'tidak_dapat_import') !== 'dapat_import') {
                $skipped[] = [
                    'baris' => $rowNumber,
                    'alasan' => 'Baris ditandai tidak dapat di-import pada tahap scan.',
                ];
                continue;
            }

            if ($selectedSet !== null && !isset($selectedSet[$rowNumber])) {
                $skipped[] = [
                    'baris' => $rowNumber,
                    'alasan' => 'Baris tidak termasuk dalam daftar konfirmasi.',
                ];
                continue;
            }

            try {
                $nama = (string) ($row['input']['nama'] ?? '');
                $alamat = (string) ($row['input']['alamat'] ?? '');
                $klasifikasi = $row['hasil_klasifikasi'] ?? [];

                $createdBy = $this->normalizeNullableInt($row['input']['dibuat_oleh_user_id'] ?? null);

                if ($createdBy === null) {
                    $createdBy = $defaultCreatedByUserId;
                }

                $mahasiswa = Mahasiswa::query()->create([
                    'mahasiswa_id' => (string) Str::uuid(),
                    'nama' => $nama,
                    'alamat' => $alamat,
                    'wilayah_id' => $klasifikasi['wilayah_id'] ?? null,
                    'latitude' => $klasifikasi['latitude'] ?? null,
                    'longitude' => $klasifikasi['longitude'] ?? null,
                    'dibuat_oleh_user_id' => $createdBy,
                    'diubah_oleh_user_id' => $createdBy,
                ]);

                $imported[] = [
                    'baris' => $rowNumber,
                    'mahasiswa_id' => $mahasiswa->mahasiswa_id,
                    'nama' => $mahasiswa->nama,
                    'wilayah_id' => $mahasiswa->wilayah_id,
                ];
            } catch (Throwable $exception) {
                $failed[] = [
                    'baris' => $rowNumber,
                    'alasan' => $exception->getMessage(),
                ];
            }
        }

        $confirmationResult = [
            'import_id' => $importId,
            'ringkasan' => [
                'total_terscan' => (int) ($draft['ringkasan']['total_data'] ?? count($draft['data'] ?? [])),
                'berhasil_import' => count($imported),
                'gagal_import' => count($failed),
                'dilewati' => count($skipped),
            ],
            'hasil' => [
                'berhasil' => $imported,
                'gagal' => $failed,
                'dilewati' => $skipped,
            ],
        ];

        $draft['is_confirmed'] = true;
        $draft['confirmed_at'] = date(DATE_ATOM);
        $draft['confirmation_result'] = $confirmationResult;
        $this->storeImportDraft($importId, $draft);

        return $confirmationResult;
    }

    public function generateTemplateXlsx(): string
    {
        $rows = [
            ['nama', 'alamat'],
            ['Budi Santoso', 'Jl. Raya Jatibarang, Desa Janegara, Kec. Jatibarang, Kab. Brebes'],
            ['Siti Aminah', 'Perumahan Tunggulsari Indah, Ds Tunggulsari, Kec. Kedungwaru, Kab. Tulungagung'],
        ];

        return $this->buildSimpleXlsx($rows);
    }

    public function generateTemplateCsv(): string
    {
        $rows = [
            ['nama', 'alamat'],
            ['Budi Santoso', 'Jl. Raya Jatibarang, Desa Janegara, Kec. Jatibarang, Kab. Brebes'],
            ['Siti Aminah', 'Perumahan Tunggulsari Indah, Ds Tunggulsari, Kec. Kedungwaru, Kab. Tulungagung'],
        ];

        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException('Gagal membuat template CSV.');
        }

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $csvContent = stream_get_contents($stream) ?: '';
        fclose($stream);

        return "\xEF\xBB\xBF" . $csvContent;
    }

    private function resolveFromAlamat(string $alamat, ?bool $useExternalGeocoding): array
    {
        $useExternal = $useExternalGeocoding;

        if ($useExternal === null) {
            $useExternal = $this->resolveUseExternalFromEnv();
        }

        $classification = $this->addressWilayahClassifierRepository->classifyOne($alamat, $useExternal);
        $wilayah = $classification['mapping']['wilayah'] ?? [];

        $selectedNode = $wilayah['desa']
            ?? $wilayah['kecamatan']
            ?? $wilayah['kabupaten_kota']
            ?? $wilayah['provinsi']
            ?? null;

        return [
            'wilayah_id' => is_array($selectedNode) ? ($selectedNode['wilayah_id'] ?? null) : null,
            'latitude' => $classification['geocoding']['latitude'] ?? null,
            'longitude' => $classification['geocoding']['longitude'] ?? null,
            'reference' => [
                'mapping_status' => $classification['mapping']['status'] ?? null,
                'needs_confirmation' => $classification['needs_confirmation'] ?? null,
                'confidence_score' => $classification['mapping']['confidence']['score'] ?? null,
                'geocoding_source' => $classification['geocoding']['source'] ?? null,
                'external_geocoding_used' => $classification['external_geocoding']['used'] ?? false,
            ],
        ];
    }

    private function applyDefaultAddressIfInvalid(string $alamat, array $geocodingPayload): array
    {
        $isInvalid = $alamat === ''
            || ($geocodingPayload['reference']['mapping_status'] ?? 'unmatched') === 'unmatched'
            || ($geocodingPayload['reference']['needs_confirmation'] ?? true) === true
            || $geocodingPayload['wilayah_id'] === null;

        if ($isInvalid) {
            return [
                'alamat' => 'Politeknik Elektronika Surabaya, Jl. Raya ITS, Keputih, Sukolilo, Surabaya, Jawa Timur',
                'geocoding_payload' => [
                    'wilayah_id' => '001035078009001',
                    'latitude' => -7.275766980349144,
                    'longitude' => 112.79378525896956,
                    'reference' => [
                        'mapping_status' => 'matched',
                        'needs_confirmation' => false,
                        'confidence_score' => 1.0,
                        'geocoding_source' => 'default',
                        'external_geocoding_used' => false,
                    ],
                ]
            ];
        }

        return [
            'alamat' => $alamat,
            'geocoding_payload' => $geocodingPayload,
        ];
    }

    private function scanImportRow(array $row, bool $useExternalGeocoding): array
    {
        $baris = (int) ($row['baris'] ?? 0);
        $nama = trim((string) ($row['nama'] ?? ''));
        $alamat = trim((string) ($row['alamat'] ?? ''));
        $dibuatOlehUserId = $this->normalizeNullableInt($row['dibuat_oleh_user_id'] ?? null);

        $alasan = [];
        $hasilKlasifikasi = [
            'status_mapping' => null,
            'needs_confirmation' => null,
            'confidence_score' => null,
            'wilayah_id' => null,
            'latitude' => null,
            'longitude' => null,
            'geocoding_source' => null,
            'external_geocoding_used' => null,
        ];

        if ($nama === '') {
            $alasan[] = 'Kolom nama wajib diisi.';
        }

        if (empty($alasan)) {
            $classification = $this->addressWilayahClassifierRepository->classifyOne($alamat, $useExternalGeocoding);
            $mappedWilayah = $classification['mapping']['wilayah'] ?? [];
            $selectedNode = $mappedWilayah['desa']
                ?? $mappedWilayah['kecamatan']
                ?? $mappedWilayah['kabupaten_kota']
                ?? $mappedWilayah['provinsi']
                ?? null;

            $wilayahId = is_array($selectedNode) ? ($selectedNode['wilayah_id'] ?? null) : null;

            $isInvalid = $alamat === '' 
                || ($classification['mapping']['status'] ?? 'unmatched') === 'unmatched'
                || ($classification['needs_confirmation'] ?? true) === true
                || $wilayahId === null;

            if ($isInvalid) {
                $alamat = 'Politeknik Elektronika Surabaya, Jl. Raya ITS, Keputih, Sukolilo, Surabaya, Jawa Timur';
                $hasilKlasifikasi = [
                    'status_mapping' => 'matched',
                    'needs_confirmation' => false,
                    'confidence_score' => 1.0,
                    'wilayah_id' => '001035078009001',
                    'latitude' => -7.275766980349144,
                    'longitude' => 112.79378525896956,
                    'geocoding_source' => 'default',
                    'external_geocoding_used' => false,
                ];
            } else {
                $hasilKlasifikasi = [
                    'status_mapping' => $classification['mapping']['status'] ?? null,
                    'needs_confirmation' => $classification['needs_confirmation'] ?? null,
                    'confidence_score' => $classification['mapping']['confidence']['score'] ?? null,
                    'wilayah_id' => $wilayahId,
                    'latitude' => $classification['geocoding']['latitude'] ?? null,
                    'longitude' => $classification['geocoding']['longitude'] ?? null,
                    'geocoding_source' => $classification['geocoding']['source'] ?? null,
                    'external_geocoding_used' => $classification['external_geocoding']['used'] ?? false,
                ];
            }
        }

        return [
            'baris' => $baris,
            'input' => [
                'nama' => $nama,
                'alamat' => $alamat,
                'dibuat_oleh_user_id' => $dibuatOlehUserId,
            ],
            'status_import' => empty($alasan) ? 'dapat_import' : 'tidak_dapat_import',
            'alasan' => $alasan,
            'hasil_klasifikasi' => $hasilKlasifikasi,
        ];
    }

    private function parseImportFile(UploadedFile $file): array
    {
        $extension = mb_strtolower((string) $file->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xlsm', 'xltx'], true)) {
            return $this->parseImportXlsx($file);
        }

        return $this->parseImportCsv($file);
    }

    private function parseImportCsv(UploadedFile $file): array
    {
        $filePath = $file->getRealPath();

        if ($filePath === false || !is_file($filePath)) {
            throw new InvalidArgumentException('File import tidak valid.');
        }

        $firstLine = (string) file_get_contents($filePath, false, null, 0, 4096);
        $firstLine = strtok($firstLine, "\n") ?: '';
        $delimiter = $this->detectDelimiter($firstLine);

        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new RuntimeException('Gagal membaca file import.');
        }

        $rawHeaders = fgetcsv($handle, 0, $delimiter);

        if ($rawHeaders === false) {
            fclose($handle);
            throw new InvalidArgumentException('Header file import tidak ditemukan.');
        }

        $normalizedHeaders = array_map(function ($header) {
            return $this->normalizeHeader((string) $header);
        }, $rawHeaders);

        $requiredHeaders = ['nama', 'alamat'];

        foreach ($requiredHeaders as $requiredHeader) {
            if (!in_array($requiredHeader, $normalizedHeaders, true)) {
                fclose($handle);
                throw new InvalidArgumentException('Header wajib tidak ditemukan: ' . $requiredHeader . '.');
            }
        }

        $headerIndexMap = [];

        foreach ($normalizedHeaders as $index => $header) {
            if (!array_key_exists($header, $headerIndexMap)) {
                $headerIndexMap[$header] = $index;
            }
        }

        $rows = [];
        $baris = 1;

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            $baris++;

            if ($this->isEmptyRow($values)) {
                continue;
            }

            $rows[] = [
                'baris' => $baris,
                'nama' => $this->valueFromCsv($values, $headerIndexMap, 'nama'),
                'alamat' => $this->valueFromCsv($values, $headerIndexMap, 'alamat'),
                'dibuat_oleh_user_id' => $this->valueFromCsv($values, $headerIndexMap, 'dibuat_oleh_user_id'),
            ];
        }

        fclose($handle);

        return $rows;
    }

    private function parseImportXlsx(UploadedFile $file): array
    {
        $filePath = $file->getRealPath();

        if ($filePath === false || !is_file($filePath)) {
            throw new InvalidArgumentException('File import tidak valid.');
        }

        $zip = new ZipArchive();

        if ($zip->open($filePath) !== true) {
            throw new InvalidArgumentException('File Excel tidak dapat dibuka.');
        }

        try {
            $sheetPath = $this->resolveFirstWorksheetPath($zip);
            $sheetXml = $zip->getFromName($sheetPath);

            if ($sheetXml === false) {
                throw new InvalidArgumentException('Worksheet Excel tidak ditemukan.');
            }

            $sharedStrings = $this->readSharedStrings($zip);
        } finally {
            $zip->close();
        }

        $sheet = simplexml_load_string($sheetXml);

        if ($sheet === false || !isset($sheet->sheetData)) {
            throw new InvalidArgumentException('Format worksheet Excel tidak valid.');
        }

        $rawRows = [];

        foreach ($sheet->sheetData->row as $rowNode) {
            $rowNumber = (int) $rowNode['r'];
            $cells = [];
            $fallbackIndex = 0;

            foreach ($rowNode->c as $cellNode) {
                $cellRef = (string) $cellNode['r'];
                $columnIndex = $cellRef !== ''
                    ? $this->columnNameToIndex(preg_replace('/\d+/', '', $cellRef) ?? '')
                    : $fallbackIndex;

                $cells[$columnIndex] = $this->extractXlsxCellValue($cellNode, $sharedStrings);
                $fallbackIndex++;
            }

            if (!$this->isEmptyRow($cells)) {
                ksort($cells);
                $rawRows[] = [
                    'baris' => $rowNumber > 0 ? $rowNumber : count($rawRows) + 1,
                    'cells' => $cells,
                ];
            }
        }

        if (empty($rawRows)) {
            throw new InvalidArgumentException('Header file import tidak ditemukan.');
        }

        $headerRow = array_shift($rawRows);
        $normalizedHeaders = [];

        foreach ($headerRow['cells'] as $index => $header) {
            $normalizedHeaders[$index] = $this->normalizeHeader((string) $header);
        }

        $requiredHeaders = ['nama', 'alamat'];

        foreach ($requiredHeaders as $requiredHeader) {
            if (!in_array($requiredHeader, $normalizedHeaders, true)) {
                throw new InvalidArgumentException('Header wajib tidak ditemukan: ' . $requiredHeader . '.');
            }
        }

        $headerIndexMap = [];

        foreach ($normalizedHeaders as $index => $header) {
            if (!array_key_exists($header, $headerIndexMap)) {
                $headerIndexMap[$header] = $index;
            }
        }

        $rows = [];

        foreach ($rawRows as $row) {
            $values = $row['cells'];

            if ($this->isEmptyRow($values)) {
                continue;
            }

            $rows[] = [
                'baris' => (int) $row['baris'],
                'nama' => $this->valueFromCellMap($values, $headerIndexMap, 'nama'),
                'alamat' => $this->valueFromCellMap($values, $headerIndexMap, 'alamat'),
                'dibuat_oleh_user_id' => $this->valueFromCellMap($values, $headerIndexMap, 'dibuat_oleh_user_id'),
            ];
        }

        return $rows;
    }

    private function detectDelimiter(string $firstLine): string
    {
        $commaCount = substr_count($firstLine, ',');
        $semicolonCount = substr_count($firstLine, ';');

        return $semicolonCount > $commaCount ? ';' : ',';
    }

    private function normalizeHeader(string $header): string
    {
        $cleanHeader = preg_replace('/^\xEF\xBB\xBF/u', '', $header) ?? $header;
        $cleanHeader = mb_strtolower(trim($cleanHeader));
        $cleanHeader = preg_replace('/\s+/u', '_', $cleanHeader) ?? $cleanHeader;

        return trim($cleanHeader);
    }

    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function valueFromCsv(array $values, array $headerIndexMap, string $key): string
    {
        if (!isset($headerIndexMap[$key])) {
            return '';
        }

        $index = $headerIndexMap[$key];

        return trim((string) ($values[$index] ?? ''));
    }

    private function valueFromCellMap(array $values, array $headerIndexMap, string $key): string
    {
        if (!isset($headerIndexMap[$key])) {
            return '';
        }

        $index = $headerIndexMap[$key];

        return trim((string) ($values[$index] ?? ''));
    }

    private function buildSimpleXlsx(array $rows): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'mahasiswa-template-');

        if ($tempPath === false) {
            throw new RuntimeException('Gagal membuat file template sementara.');
        }

        $zip = new ZipArchive();

        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tempPath);
            throw new RuntimeException('Gagal membuat template Excel.');
        }

        try {
            $zip->addFromString('[Content_Types].xml', $this->xlsxContentTypesXml());
            $zip->addFromString('_rels/.rels', $this->xlsxRootRelsXml());
            $zip->addFromString('docProps/app.xml', $this->xlsxAppXml());
            $zip->addFromString('docProps/core.xml', $this->xlsxCoreXml());
            $zip->addFromString('xl/workbook.xml', $this->xlsxWorkbookXml());
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xlsxWorkbookRelsXml());
            $zip->addFromString('xl/styles.xml', $this->xlsxStylesXml());
            $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsxWorksheetXml($rows));
        } finally {
            $zip->close();
        }

        $content = file_get_contents($tempPath);
        @unlink($tempPath);

        if ($content === false) {
            throw new RuntimeException('Gagal membaca template Excel.');
        }

        return $content;
    }

    private function xlsxWorksheetXml(array $rows): string
    {
        $rowXml = '';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $cellsXml = '';

            foreach ($row as $columnIndex => $value) {
                $cellRef = $this->columnIndexToName($columnIndex) . $excelRow;
                $styleIndex = $excelRow === 1 ? ' s="1"' : '';
                $escapedValue = $this->escapeXml((string) $value);

                $cellsXml .= '<c r="' . $cellRef . '"' . $styleIndex . ' t="inlineStr"><is><t>' . $escapedValue . '</t></is></c>';
            }

            $rowXml .= '<row r="' . $excelRow . '">' . $cellsXml . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:B' . max(1, count($rows)) . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . '<cols><col min="1" max="1" width="28" customWidth="1"/><col min="2" max="2" width="92" customWidth="1"/></cols>'
            . '<sheetData>' . $rowXml . '</sheetData>'
            . '<autoFilter ref="A1:B' . max(1, count($rows)) . '"/>'
            . '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
            . '</worksheet>';
    }

    private function xlsxContentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function xlsxRootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function xlsxWorkbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Template Mahasiswa" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function xlsxWorkbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function xlsxStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF20893A"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function xlsxCoreXml(): string
    {
        $createdAt = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>Template Import Mahasiswa</dc:title>'
            . '<dc:creator>GeoVisit PJJ IT</dc:creator>'
            . '<cp:lastModifiedBy>GeoVisit PJJ IT</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $createdAt . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $createdAt . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private function xlsxAppXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>GeoVisit PJJ IT</Application>'
            . '</Properties>';
    }

    private function resolveFirstWorksheetPath(ZipArchive $zip): string
    {
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($relsXml === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $rels = simplexml_load_string($relsXml);

        if ($rels === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        foreach ($rels->Relationship as $relationship) {
            $attributes = $relationship->attributes();
            $type = (string) ($attributes['Type'] ?? '');
            $target = (string) ($attributes['Target'] ?? '');

            if (str_ends_with($type, '/worksheet') && $target !== '') {
                return str_starts_with($target, '/')
                    ? ltrim($target, '/')
                    : 'xl/' . ltrim($target, '/');
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');

        if ($sharedStringsXml === false) {
            return [];
        }

        $sharedStrings = simplexml_load_string($sharedStringsXml);

        if ($sharedStrings === false) {
            return [];
        }

        $values = [];

        foreach ($sharedStrings->si as $stringItem) {
            if (isset($stringItem->t)) {
                $values[] = (string) $stringItem->t;
                continue;
            }

            $text = '';

            foreach ($stringItem->r as $run) {
                $text .= (string) ($run->t ?? '');
            }

            $values[] = $text;
        }

        return $values;
    }

    private function extractXlsxCellValue($cellNode, array $sharedStrings): string
    {
        $type = (string) ($cellNode['t'] ?? '');

        if ($type === 's') {
            $index = (int) ($cellNode->v ?? -1);

            return isset($sharedStrings[$index]) ? (string) $sharedStrings[$index] : '';
        }

        if ($type === 'inlineStr') {
            if (isset($cellNode->is->t)) {
                return (string) $cellNode->is->t;
            }

            $text = '';

            foreach ($cellNode->is->r as $run) {
                $text .= (string) ($run->t ?? '');
            }

            return $text;
        }

        return trim((string) ($cellNode->v ?? ''));
    }

    private function columnNameToIndex(string $columnName): int
    {
        $columnName = mb_strtoupper($columnName);
        $index = 0;

        for ($i = 0; $i < strlen($columnName); $i++) {
            $index = ($index * 26) + (ord($columnName[$i]) - 64);
        }

        return max(0, $index - 1);
    }

    private function columnIndexToName(int $columnIndex): string
    {
        $columnName = '';
        $index = $columnIndex + 1;

        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $columnName = chr(65 + $remainder) . $columnName;
            $index = intdiv($index - 1, 26);
        }

        return $columnName;
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function normalizeNullableInt($value): ?int
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if (!is_numeric($text)) {
            return null;
        }

        return (int) $text;
    }

    private function buildSelectedSet(?array $selectedRows): ?array
    {
        if ($selectedRows === null || $selectedRows === []) {
            return null;
        }

        $selectedSet = [];

        foreach ($selectedRows as $row) {
            if (!is_numeric($row)) {
                throw new InvalidArgumentException('Daftar baris konfirmasi tidak valid.');
            }

            $selectedSet[(int) $row] = true;
        }

        return $selectedSet;
    }

    private function loadImportDraft(string $importId): array
    {
        $draftPath = $this->getImportDraftPath($importId);

        if (!is_file($draftPath)) {
            throw new RuntimeException('Draft import tidak ditemukan.');
        }

        $rawPayload = file_get_contents($draftPath);

        if ($rawPayload === false) {
            throw new RuntimeException('Gagal membaca draft import.');
        }

        $payload = json_decode($rawPayload, true);

        if (!is_array($payload)) {
            throw new RuntimeException('Format draft import tidak valid.');
        }

        return $payload;
    }

    private function storeImportDraft(string $importId, array $payload): void
    {
        $this->ensureImportDraftDirectoryExists();

        $draftPath = $this->getImportDraftPath($importId);
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            throw new RuntimeException('Gagal menyimpan draft import.');
        }

        $written = file_put_contents($draftPath, $encoded);

        if ($written === false) {
            throw new RuntimeException('Gagal menulis draft import.');
        }
    }

    private function getImportDraftPath(string $importId): string
    {
        return $this->draftDirectory . DIRECTORY_SEPARATOR . $importId . '.json';
    }

    private function ensureImportDraftDirectoryExists(): void
    {
        if (is_dir($this->draftDirectory)) {
            return;
        }

        if (!mkdir($this->draftDirectory, 0775, true) && !is_dir($this->draftDirectory)) {
            throw new RuntimeException('Gagal membuat direktori draft import.');
        }
    }

    private function resolveUseExternalFromEnv(): bool
    {
        $resolved = filter_var((string) env('NOMINATIM_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($resolved === null) {
            return true;
        }

        return $resolved;
    }

    private function transform(Mahasiswa $mahasiswa): array
    {
        $mahasiswa->loadMissing('wilayah');

        return [
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
            'nama' => $mahasiswa->nama,
            'alamat' => $mahasiswa->alamat,
            'wilayah_id' => $mahasiswa->wilayah_id,
            'wilayah' => $this->transformWilayah($mahasiswa->wilayah),
            'latitude' => $mahasiswa->latitude,
            'longitude' => $mahasiswa->longitude,
            'dibuat_pada' => $mahasiswa->dibuat_pada,
            'dibuat_oleh_user_id' => $mahasiswa->dibuat_oleh_user_id,
            'diubah_pada' => $mahasiswa->diubah_pada,
            'diubah_oleh_user_id' => $mahasiswa->diubah_oleh_user_id,
            'dihapus_pada' => $mahasiswa->dihapus_pada,
            'dihapus_oleh_user_id' => $mahasiswa->dihapus_oleh_user_id,
        ];
    }

    private function transformWilayah(?Wilayah $wilayah)
    {
        if ($wilayah === null) {
            return (object) [];
        }

        $wilayahId = (string) $wilayah->wilayah_id;

        return [
            'wilayah_id' => $wilayah->wilayah_id,
            'nama' => $wilayah->nama,
            'latitude' => $wilayah->latitude,
            'longitude' => $wilayah->longitude,
            'level' => (int) ceil(strlen($wilayahId) / 3),
            'parent_wilayah_id' => strlen($wilayahId) > 3
                ? substr($wilayahId, 0, strlen($wilayahId) - 3)
                : null,
        ];
    }
}

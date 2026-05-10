<?php

namespace App\Http\Controllers;

use App\Repositories\AddressWilayahClassifierRepository;
use Illuminate\Http\Request;

class PublicAddressClassifierController extends Controller
{
    private AddressWilayahClassifierRepository $addressWilayahClassifierRepository;

    public function __construct(AddressWilayahClassifierRepository $addressWilayahClassifierRepository)
    {
        $this->addressWilayahClassifierRepository = $addressWilayahClassifierRepository;
    }

    public function test(Request $request)
    {
        $addresses = $request->input('alamat');
        $useExternalGeocoding = $request->input('use_external_geocoding', null);

        if (!is_array($addresses) || empty($addresses)) {
            $addresses = $this->getDefaultTestAddresses();
        }

        $result = $this->addressWilayahClassifierRepository->classifyMany($addresses, [
            'use_external_geocoding' => $useExternalGeocoding,
        ]);

        return $this->successResponse($result, 'Klasifikasi alamat berhasil diproses.');
    }

    private function getDefaultTestAddresses(): array
    {
        return [
            'Dsn Cimider, RT 12 RT 05, Lemah Mulya, Majalaya, Karawang'
            // 'Jl. Lb. Bambu No.61, RT 02/09, Bojong Kulur, Kec. Gunung Putri, Kabupaten Bogor, Jawa Barat 16969',
            // 'Jl. Raya Jatibarang Brebes  Desa Janegara RT 012 RW 04 No.53 Janegara, Kec. Jatibarang, Kab. Brebes 52261',
            // 'Ds Tunggulsari Kec Tulungagung Kab Tulungagung',
            // 'Jalan Pahlawan gang IV, Rejoagung, Kec. Kedungwaru, Kab.Tulungagung, Jawatimur 66225',
            // 'PERUM KARABA INDAH BLOK CB NO 11RT/RW:001/010KEL : WADASKEC : TELUK JAMBE TIMUR KOTA :KARAWANG PROV :JAWA BARAT',
            // 'Jl. Semeru No. 124B, Kepanjenlor, Kepanjenkidul, Kota Blitar, Jawa Timur',
            // 'Alamat rumah: Dsn. Krajan,RT 004/RW 002, DS. Gondosuli, Kec. GondangKab Tulungagung/ 66263',
            // 'Dsn. Cari RT/RW 01/02 Ds.Banjarsari, Kec. Ngantru, Kab. Tulungagung, Jawa Timur 66252',
            // 'Lingkungan Darang Rt.04 Rw.02 Tamanan, Trenggalek, Jawa Timur 66312',
            // 'Desa Balerejo, Kec. Kauman, Kabupaten Tulungagung, Dusun Bebekan, RT 2 RW 3',
            // 'RT 3 RW 2 Dsn. Rejoagung Ds. Rejoagung Kec. Kedungwaru Kab. Tulungagung Jawa Timur ',
            // 'Jl.Pandean Lingkungan 08 Ngunut RT 01 RW 02 Kec.Ngunut Kab.Tulungagung Jawa Timur',
            // 'Dusun pakel,Rt 35,Rw 06,prambon tugu, Trenggalek (66352)',
            // 'Jl. Jend. Soedirman, no. 426, RT.50/RW.05, Kec. Randudongkal (52353)',
            // 'Jl. Jend. Soedirman, no. 426, RT.50/RW.05, Kec. Randudongkal (52353)',
        ];
    }
}
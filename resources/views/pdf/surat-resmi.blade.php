<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $namaSurat ?? $jenisSurat->nama_surat }}</title>
    <style>
        /* Desain ramah DOMPDF: berbasis tabel HTML, hindari CSS modern flex/grid */
        @page {
            size: A4 portrait;
            margin: 1.0cm 2.0cm 1.0cm 2.0cm;
        }
        body {
            font-family: "Times New Roman", "Times", "DejaVu Serif", serif;
            font-size: 10.5pt;
            line-height: 1.32;
            color: #000;
        }
        .kop-table {
            width: 100%;
            margin-bottom: 2px;
        }
        .kop-logo {
            text-align: left;
            vertical-align: middle;
        }
        .kop-text {
            text-align: center;
            vertical-align: middle;
        }
        .kop-instansi {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.25;
        }
        .kop-nagari {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            line-height: 1.3;
            margin: 1px 0;
        }
        .kop-kontak-1 {
            font-size: 8.5pt;
            margin-top: 3px;
            line-height: 1.2;
        }
        .kop-kontak-2 {
            font-size: 8.5pt;
            margin-top: 1px;
            line-height: 1.2;
        }
        .kop-line {
            border-top: 2.5px solid #000;
            border-bottom: 1px solid #000;
            height: 2px;
            margin-top: 4px;
            margin-bottom: 10px;
        }
        .judul-surat-table {
            width: 100%;
            text-align: center;
            margin-top: 4px;
            margin-bottom: 10px;
        }
        .judul-surat {
            font-size: 12pt;
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
        }
        .nomor-surat {
            font-size: 10.5pt;
            margin-top: 2px;
        }
        .konten {
            margin-top: 6px;
            margin-bottom: 12px;
            text-align: justify;
        }
        .konten p {
            margin-top: 10px;
            margin-bottom: 5px;
            text-align: justify;
            text-indent: 0 !important;
        }
        .konten table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0;
        }
        .konten table[data-rincian-warga="1"] {
            width: 97% !important;
            margin-left: 15px !important;
        }
        .konten table[data-rincian-warga="1"] td:first-child {
            width: 28%;
        }
        .konten table[data-rincian-warga="1"] td:nth-child(2) {
            width: 3%;
            text-align: center;
        }
        .konten th, .konten td {
            padding: 1.5px 3px;
            vertical-align: top;
            font-size: 10.5pt;
            white-space: normal;
            word-break: normal;
            overflow-wrap: normal;
            word-wrap: normal;
        }
        .ttd-table {
            width: 100%;
            margin-top: 10px;
            page-break-inside: avoid;
        }
        .watermark {
            position: fixed;
            top: 40%;
            left: 10%;
            width: 80%;
            text-align: center;
            font-size: 55pt;
            color: rgba(220, 38, 38, 0.18);
            transform: rotate(-35deg);
            font-weight: bold;
            z-index: -1000;
        }
    </style>
</head>
<body>
    @if (!empty($isDraftWatermark))
        <div class="watermark">DRAFT / SIMULASI</div>
    @endif

    <!-- KOP SURAT NAGARI TARAM -->
    @php
        $logoPath = null;
        if (!empty($nagari->logo_path)) {
            if (file_exists(public_path('storage/' . $nagari->logo_path))) {
                $logoPath = public_path('storage/' . $nagari->logo_path);
            } elseif (file_exists(storage_path('app/public/' . $nagari->logo_path))) {
                $logoPath = storage_path('app/public/' . $nagari->logo_path);
            }
        }
        if (!$logoPath && file_exists(public_path('images/logo-lima-puluh-kota.png'))) {
            $logoPath = public_path('images/logo-lima-puluh-kota.png');
        }

        $stempelPath = empty($isDraftWatermark)
            ? app(\App\Services\StempelNagari::class)->path($nagari ?? null)
            : null;

        $kabupaten = strtoupper(trim(preg_replace('/^kabupaten\s+/i', '', $nagari->nama_kabupaten ?? 'LIMA PULUH KOTA')));
        $kecamatan = strtoupper(trim(preg_replace('/^kecamatan\s+/i', '', $nagari->nama_kecamatan ?? 'HARAU')));
        $namaNagari = strtoupper(trim(preg_replace('/^nagari\s+/i', '', $nagari->nama_nagari ?? 'TARAM')));
    @endphp

    <table class="kop-table">
        <tr>
            <td class="kop-logo" style="width: 80px;">
                @if ($logoPath)
                    <img src="{{ $logoPath }}" style="height: 82px; width: auto; max-width: 80px;">
                @else
                    <div style="font-size: 28pt; text-align: center;">🏛</div>
                @endif
            </td>
            <td class="kop-text">
                <div class="kop-instansi">PEMERINTAH KABUPATEN {{ $kabupaten }}</div>
                <div class="kop-instansi">KECAMATAN {{ $kecamatan }}</div>
                <div class="kop-nagari">NAGARI {{ $namaNagari }}</div>
                <div class="kop-kontak-1">
                    {{ strtoupper($nagari->alamat_kantor ?? 'JLN. TARAM - BUKIT LIMBUKU') }}
                    @if(!empty($nagari->telepon)) TELP. {{ $nagari->telepon }} @endif
                </div>
                <div class="kop-kontak-2">
                    @if(!empty($nagari->email)) Email : {{ $nagari->email }} @endif
                    @if(!empty($nagari->website)) &nbsp;&nbsp;&nbsp;&nbsp; Website : {{ $nagari->website }} @endif
                </div>
            </td>
            <td style="width: 80px;"></td>
        </tr>
    </table>
    <div class="kop-line"></div>

    <!-- JUDUL SURAT & NOMOR -->
    <table class="judul-surat-table">
        <tr>
            <td>
                <div class="judul-surat">{{ $namaSurat ?? $jenisSurat->nama_surat }}</div>
                <div class="nomor-surat">Nomor: {{ $nomorSurat }}</div>
            </td>
        </tr>
    </table>

    <!-- REDAKSI / ISI SURAT DINAMIS -->
    <div class="konten">
        {!! $kontenSurat !!}
    </div>

    <!-- TANDA TANGAN WALI NAGARI -->
    <table class="ttd-table">
        <tr>
            <td style="width: 55%;"></td>
            <td style="width: 45%; text-align: center;">
                <div>Taram, {{ $tanggalSurat }}</div>
                <div style="font-weight: bold; margin-top: 3px;">Wali Nagari Taram</div>
                
                {{-- Seperti surat fisik: ditandatangani dahulu, lalu dicap. Cap 4 cm dibubuhkan di atas tanda tangan
                     dan mengenai sepertiga bagian kiri tanda tangan; kolom ini selebar 7,65 cm. --}}
                <div style="position: relative; height: 2.9cm; margin: 2px 0;">
                    @if (empty($isDraftWatermark) && ($ttdPath ?? null))
                        <img src="{{ $ttdPath }}" alt="Tanda tangan Wali Nagari" style="position: absolute; left: 1.33cm; top: 0.55cm; max-width: 5cm; max-height: 1.9cm;">
                    @endif
                    @if ($stempelPath)
                        <img src="{{ $stempelPath }}" alt="Stempel resmi Nagari Taram" style="position: absolute; left: -1cm; top: -0.64cm; width: 4cm; height: 4cm;">
                    @endif
                </div>

                <div style="font-weight: bold; text-decoration: underline;">
                    {{ $pejabat?->nama_pejabat ?? 'Pejabat belum ditetapkan' }}
                </div>
                @if(!empty($pejabat?->nip))
                    <div style="font-size: 10pt; margin-top: 2px;">
                        NIP. {{ $pejabat->nip }}
                    </div>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>

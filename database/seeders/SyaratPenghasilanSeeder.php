<?php

namespace Database\Seeders;

use App\Models\JenisSurat;
use App\Services\AuditLogService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SyaratPenghasilanSeeder extends Seeder
{
    public function run(AuditLogService $auditLog): void
    {
        DB::transaction(function () use ($auditLog): void {
            $jenisSurat = JenisSurat::query()->where('nama_surat', 'Surat Keterangan Penghasilan')->lockForUpdate()->first();
            if (! $jenisSurat) {
                return;
            }

            $syarat = $jenisSurat->syaratDokumens()->firstOrCreate(
                ['nama_dokumen' => 'Slip Gaji / Bukti Penghasilan'],
                [
                    'wajib' => true,
                    'keterangan' => 'Slip gaji atau bukti penghasilan pengganti, termasuk surat pernyataan penghasilan bagi pemohon yang tidak memiliki slip gaji.',
                    'urutan' => ($jenisSurat->syaratDokumens()->max('urutan') ?? 0) + 1,
                ],
            );

            if ($syarat->wasRecentlyCreated) {
                $auditLog->record(
                    actor: null,
                    action: 'ubah_syarat_dokumen',
                    targetType: 'JenisSurat',
                    targetId: $jenisSurat->id,
                    description: 'Menambahkan syarat Slip Gaji / Bukti Penghasilan sesuai permintaan perangkat Nagari.',
                    after: $syarat->only(['id', 'nama_dokumen', 'wajib', 'keterangan', 'urutan']),
                );
            }
        });
    }
}

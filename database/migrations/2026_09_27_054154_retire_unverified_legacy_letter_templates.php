<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacyTemplates = [
            'Surat Keterangan Berkelakuan Baik' => 'fdad39a91922eaa81ae0158d1e26923d1cd51c313802b0806a50d970f843dc0a',
            'Surat Keterangan Ahli Waris' => '745a7deadbd4d2c7086215f5ef2153edc6f4c14f69a990783249c6803cecbd8d',
        ];

        DB::transaction(function () use ($legacyTemplates): void {
            foreach ($legacyTemplates as $name => $hash) {
                $jenis = DB::table('jenis_surat')->where('nama_surat', $name)->where('status', 'aktif')->lockForUpdate()->first();
                if (! $jenis) {
                    continue;
                }

                $template = DB::table('template_surat')
                    ->where('jenis_surat_id', $jenis->id)
                    ->where('status_aktif', true)
                    ->first();
                if (! $template || hash('sha256', (string) $template->konten_html) !== $hash) {
                    continue;
                }

                DB::table('jenis_surat')->where('id', $jenis->id)->update(['status' => 'draft', 'updated_at' => now()]);
                DB::table('log_aktivitas')->insert([
                    'user_id' => null,
                    'aksi' => 'nonaktifkan_format_lama',
                    'target_type' => 'JenisSurat',
                    'target_id' => (string) $jenis->id,
                    'keterangan' => "Format lama {$name} dipindahkan ke draft karena contoh resminya tidak ada dalam dokumen sumber yang tersedia. Perubahan manual admin pada template dikecualikan.",
                    'ip_address' => null,
                    'created_at' => now(),
                ]);
            }
        });
    }

    public function down(): void {}
};

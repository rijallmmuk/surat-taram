<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<array{table: string, column: string, references: string, on: string, previousDelete: string, cascadeUpdate: bool}> */
    private const REFERENCES = [
        ['table' => 'penduduk', 'column' => 'jorong_id', 'references' => 'id', 'on' => 'jorongs', 'previousDelete' => 'set null', 'cascadeUpdate' => false],
        ['table' => 'penduduk', 'column' => 'ref_agama_id', 'references' => 'id', 'on' => 'ref_agama', 'previousDelete' => 'set null', 'cascadeUpdate' => false],
        ['table' => 'penduduk', 'column' => 'ref_status_kawin_id', 'references' => 'id', 'on' => 'ref_status_kawin', 'previousDelete' => 'set null', 'cascadeUpdate' => false],
        ['table' => 'penduduk', 'column' => 'ref_pekerjaan_id', 'references' => 'id', 'on' => 'ref_pekerjaan', 'previousDelete' => 'set null', 'cascadeUpdate' => false],
        ['table' => 'penduduk', 'column' => 'ref_pendidikan_id', 'references' => 'id', 'on' => 'ref_pendidikan', 'previousDelete' => 'set null', 'cascadeUpdate' => false],
        ['table' => 'penduduk', 'column' => 'ref_kewarganegaraan_id', 'references' => 'id', 'on' => 'ref_kewarganegaraan', 'previousDelete' => 'set null', 'cascadeUpdate' => false],
        ['table' => 'syarat_dokumen', 'column' => 'master_syarat_dokumen_id', 'references' => 'id', 'on' => 'master_syarat_dokumen', 'previousDelete' => 'set null', 'cascadeUpdate' => false],
        ['table' => 'dokumen_warga', 'column' => 'master_syarat_dokumen_id', 'references' => 'id', 'on' => 'master_syarat_dokumen', 'previousDelete' => 'set null', 'cascadeUpdate' => false],
        ['table' => 'dokumen_warga', 'column' => 'penduduk_nik', 'references' => 'nik', 'on' => 'penduduk', 'previousDelete' => 'cascade', 'cascadeUpdate' => true],
        ['table' => 'pejabat_nagari', 'column' => 'user_id', 'references' => 'id', 'on' => 'users', 'previousDelete' => 'set null', 'cascadeUpdate' => false],
        ['table' => 'pengajuan_surat', 'column' => 'diverifikasi_oleh_user_id', 'references' => 'id', 'on' => 'users', 'previousDelete' => 'set null', 'cascadeUpdate' => false],
        ['table' => 'pengajuan_surat', 'column' => 'diterbitkan_oleh_user_id', 'references' => 'id', 'on' => 'users', 'previousDelete' => 'set null', 'cascadeUpdate' => false],
        ['table' => 'pengajuan_surat', 'column' => 'pejabat_penandatangan_id', 'references' => 'id', 'on' => 'pejabat_nagari', 'previousDelete' => 'set null', 'cascadeUpdate' => false],
        ['table' => 'log_aktivitas', 'column' => 'user_id', 'references' => 'id', 'on' => 'users', 'previousDelete' => 'set null', 'cascadeUpdate' => false],
    ];

    public function up(): void
    {
        foreach (self::REFERENCES as $reference) {
            $this->replaceForeignKey($reference, 'restrict');
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::REFERENCES) as $reference) {
            $this->replaceForeignKey($reference, $reference['previousDelete']);
        }
    }

    /**
     * @param  array{table: string, column: string, references: string, on: string, previousDelete: string, cascadeUpdate: bool}  $reference
     */
    private function replaceForeignKey(array $reference, string $deleteRule): void
    {
        Schema::table($reference['table'], function (Blueprint $table) use ($reference, $deleteRule): void {
            $table->dropForeign([$reference['column']]);

            $foreignKey = $table->foreign($reference['column'])
                ->references($reference['references'])
                ->on($reference['on'])
                ->onDelete($deleteRule);

            if ($reference['cascadeUpdate']) {
                $foreignKey->cascadeOnUpdate();
            }
        });
    }
};

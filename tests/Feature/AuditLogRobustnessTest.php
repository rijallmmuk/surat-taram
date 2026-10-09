<?php

use App\Filament\Resources\VerifikasiPengajuanResource\Pages\ViewVerifikasiPengajuan;
use App\Models\JenisSurat;
use App\Models\Penduduk;
use App\Models\PengajuanSurat;
use App\Models\User;
use App\Services\AuditLogService;
use Database\Seeders\MasterReferensiSeeder;
use Database\Seeders\NagariSeeder;
use Database\Seeders\PendudukSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Database\Seeders\StarterJenisSuratSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        MasterReferensiSeeder::class,
        NagariSeeder::class,
        PendudukSeeder::class,
        RoleAndUserSeeder::class,
        StarterJenisSuratSeeder::class,
    ]);
});

function pengajuanUntukAudit(User $actor): PengajuanSurat
{
    return PengajuanSurat::create([
        'id' => (string) Str::uuid(),
        'jenis_surat_id' => JenisSurat::query()->firstOrFail()->id,
        'penduduk_nik' => Penduduk::query()->firstOrFail()->nik,
        'diajukan_oleh_user_id' => $actor->id,
        'data_isian' => [],
        'status' => 'diajukan',
    ]);
}

test('audit service stores immutable actor and change snapshots', function () {
    $actor = User::query()->where('role', 'admin')->firstOrFail();

    $log = app(AuditLogService::class)->record(
        actor: $actor,
        action: 'uji_audit',
        targetType: 'PengajuanSurat',
        targetId: 'target-1',
        description: 'Menguji metadata audit.',
        before: ['status' => 'diajukan'],
        after: ['status' => 'diverifikasi'],
        metadata: ['source' => 'test'],
    );

    expect($log->metadata['actor'])->toMatchArray([
        'id' => $actor->id,
        'name' => $actor->name,
        'username' => $actor->username,
        'role' => 'admin',
    ])->and($log->metadata['before'])->toBe(['status' => 'diajukan'])
        ->and($log->metadata['after'])->toBe(['status' => 'diverifikasi'])
        ->and($log->metadata['source'])->toBe('test');
});

test('audit records cannot be updated or deleted through eloquent', function () {
    $log = app(AuditLogService::class)->record(
        actor: User::query()->where('role', 'admin')->firstOrFail(),
        action: 'uji_append_only',
        targetType: 'User',
        targetId: '1',
        description: 'Log permanen.',
    );

    expect(fn () => $log->update(['keterangan' => 'Diubah']))
        ->toThrow(LogicException::class, 'tidak dapat diubah')
        ->and(fn () => $log->delete())
        ->toThrow(LogicException::class, 'tidak dapat dihapus');
});

test('database rejects direct updates and deletes of audit records', function () {
    $log = app(AuditLogService::class)->record(
        actor: User::query()->where('role', 'admin')->firstOrFail(),
        action: 'uji_trigger',
        targetType: 'User',
        targetId: '1',
        description: 'Log dilindungi database.',
    );

    expect(fn () => DB::table('log_aktivitas')->where('id', $log->id)->update(['keterangan' => 'Diubah']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('log_aktivitas')->where('id', $log->id)->delete())
        ->toThrow(QueryException::class);
});

test('verification rolls back when its audit record cannot be written', function () {
    Storage::fake('local');
    pasangStempelUji();
    $actor = User::query()->where('role', 'sekretaris')->firstOrFail();
    $pengajuan = pengajuanUntukAudit(User::query()->where('role', 'warga')->firstOrFail());
    $audit = Mockery::mock(AuditLogService::class);
    $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('audit gagal'));
    app()->instance(AuditLogService::class, $audit);
    $this->actingAs($actor);

    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $pengajuan->id])
        ->callAction('verifikasi')
        ->assertNotified('Pengajuan belum diverifikasi');

    expect($pengajuan->fresh()->status)->toBe('diajukan')
        ->and($pengajuan->fresh()->diverifikasi_oleh_user_id)->toBeNull();
});

test('rejection rolls back when its audit record cannot be written', function () {
    $actor = User::query()->where('role', 'sekretaris')->firstOrFail();
    $pengajuan = pengajuanUntukAudit($actor);
    $audit = Mockery::mock(AuditLogService::class);
    $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('audit gagal'));
    app()->instance(AuditLogService::class, $audit);
    $this->actingAs($actor);

    Livewire::test(ViewVerifikasiPengajuan::class, ['record' => $pengajuan->id])
        ->callAction('tolak', ['catatan_penolakan' => 'Dokumen tidak sesuai.'])
        ->assertNotified('Pengajuan belum ditolak');

    expect($pengajuan->fresh()->status)->toBe('diajukan')
        ->and($pengajuan->fresh()->catatan_penolakan)->toBeNull();
});

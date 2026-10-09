<?php

use App\Filament\Auth\UnifiedLogin;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'superadmin']);
    Role::firstOrCreate(['name' => 'admin']);
    $this->superadmin = User::factory()->create(['role' => 'superadmin', 'username' => 'pemilik_sistem']);
    $this->admin = User::factory()->create(['role' => 'admin', 'username' => 'admin_nagari']);
    filament()->setCurrentPanel(filament()->getPanel('panel'));
});

test('superadmin alone can manage admin accounts and cannot be deleted or deactivated by admin', function () {
    expect(Gate::forUser($this->superadmin)->allows('viewAny', User::class))->toBeTrue()
        ->and(Gate::forUser($this->superadmin)->allows('update', $this->admin))->toBeTrue()
        ->and(Gate::forUser($this->superadmin)->allows('update', $this->superadmin))->toBeTrue()
        ->and(Gate::forUser($this->superadmin)->denies('delete', $this->superadmin))->toBeTrue()
        ->and(Gate::forUser($this->admin)->denies('viewAny', User::class))->toBeTrue()
        ->and(Gate::forUser($this->admin)->denies('update', $this->superadmin))->toBeTrue()
        ->and(Gate::forUser($this->admin)->denies('delete', $this->superadmin))->toBeTrue();

    $this->actingAs($this->admin);
    expect(UserResource::canAccess())->toBeFalse();

    $this->actingAs($this->superadmin);
    expect(UserResource::canAccess())->toBeTrue();
});

test('superadmin edits own password but cannot deactivate own account or change roles through form', function () {
    $this->actingAs($this->superadmin);

    Livewire::test(ListUsers::class)
        ->callTableAction('edit', $this->superadmin, ['password' => 'lemah'])
        ->assertHasActionErrors(['password']);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$this->superadmin, $this->admin])
        ->callTableAction('edit', $this->superadmin, [
            'password' => 'BaruKuat#2026',
            'is_active' => false,
        ])
        ->assertHasNoActionErrors();

    expect($this->superadmin->fresh()->is_active)->toBeTrue()
        ->and(Hash::check('BaruKuat#2026', $this->superadmin->fresh()->password))->toBeTrue()
        ->and($this->superadmin->fresh()->role)->toBe('superadmin');

    Livewire::test(ListUsers::class)
        ->callAction('create', [
            'name' => 'Superadmin Palsu',
            'username' => 'superadmin_palsu',
            'role' => 'superadmin',
            'password' => 'BaruKuat#2026',
        ])
        ->assertHasActionErrors(['role']);

    expect(User::query()->where('username', 'superadmin_palsu')->exists())->toBeFalse();
});

test('superadmin can sign in through the shared login', function () {
    Livewire::test(UnifiedLogin::class)
        ->set('data.email', 'pemilik_sistem')
        ->set('data.password', 'password')
        ->call('authenticate')
        ->assertHasNoErrors();

    expect(auth()->id())->toBe($this->superadmin->id);
});

test('bootstrap command creates the first superadmin without a default password', function () {
    $this->superadmin->delete();

    $this->artisan('app:buat-superadmin', ['username' => 'pemilik_baru'])
        ->expectsQuestion('Nama lengkap superadmin', 'Pemilik Sistem')
        ->expectsQuestion('Kata sandi baru (minimal 8 karakter)', 'SandiKuat#2026')
        ->expectsQuestion('Ulangi kata sandi', 'SandiKuat#2026')
        ->assertExitCode(0);

    $created = User::query()->where('username', 'pemilik_baru')->firstOrFail();
    expect($created->role)->toBe('superadmin')
        ->and($created->hasRole('superadmin'))->toBeTrue()
        ->and(Hash::check('SandiKuat#2026', $created->password))->toBeTrue()
        ->and(LogAktivitas::query()->where('aksi', 'buat_superadmin_awal')->where('user_id', $created->id)->exists())->toBeTrue();

    $this->artisan('app:buat-superadmin', ['username' => 'kedua'])->assertExitCode(1);
});

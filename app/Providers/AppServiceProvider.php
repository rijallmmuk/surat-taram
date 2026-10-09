<?php

namespace App\Providers;

use App\Models\JenisSurat;
use App\Models\Jorong;
use App\Models\MasterSyaratDokumen;
use App\Models\Nagari;
use App\Models\PejabatNagari;
use App\Models\Penduduk;
use App\Models\PengajuanSurat;
use App\Models\RefAgama;
use App\Models\RefKewarganegaraan;
use App\Models\RefPekerjaan;
use App\Models\RefPendidikan;
use App\Models\RefShdk;
use App\Models\RefStatusKawin;
use App\Models\RefSuku;
use App\Models\User;
use App\Policies\JenisSuratPolicy;
use App\Policies\JorongPolicy;
use App\Policies\MasterReferensiPolicy;
use App\Policies\NagariPolicy;
use App\Policies\PejabatNagariPolicy;
use App\Policies\PendudukPolicy;
use App\Policies\PengajuanSuratPolicy;
use App\Policies\UserPolicy;
use App\Services\MasterReferensiHelper;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // dompdf hanya boleh membaca aset surat, bukan seluruh root proyek (.env, config, vendor).
        config(['dompdf.options.chroot' => [resource_path(), public_path(), storage_path()]]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale('id');
        setlocale(LC_TIME, 'id_ID.UTF-8', 'id_ID', 'id', 'ind');

        Table::configureUsing(function (Table $table): void {
            $table
                ->paginationPageOptions([10, 25, 50, 100])
                ->defaultPaginationPageOption(10);
        });

        DatePicker::configureUsing(function (DatePicker $picker): void {
            $picker
                ->native()
                ->extraInputAttributes(['lang' => 'id-ID']);
        });

        DateTimePicker::configureUsing(function (DateTimePicker $picker): void {
            $picker
                ->native()
                ->extraInputAttributes(['lang' => 'id-ID']);
        });

        Action::configureUsing(function (Action $action): void {
            $action->modalFooterActionsAlignment(Alignment::Center);
        });

        CreateAction::configureUsing(function (CreateAction $action): void {
            $action->createAnother(false);
        });

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Nagari::class, NagariPolicy::class);
        Gate::policy(Jorong::class, JorongPolicy::class);
        Gate::policy(PejabatNagari::class, PejabatNagariPolicy::class);
        Gate::policy(Penduduk::class, PendudukPolicy::class);
        Gate::policy(JenisSurat::class, JenisSuratPolicy::class);
        Gate::policy(PengajuanSurat::class, PengajuanSuratPolicy::class);

        Gate::policy(RefAgama::class, MasterReferensiPolicy::class);
        Gate::policy(RefStatusKawin::class, MasterReferensiPolicy::class);
        Gate::policy(RefShdk::class, MasterReferensiPolicy::class);
        Gate::policy(RefPendidikan::class, MasterReferensiPolicy::class);
        Gate::policy(RefPekerjaan::class, MasterReferensiPolicy::class);
        Gate::policy(RefKewarganegaraan::class, MasterReferensiPolicy::class);
        Gate::policy(RefSuku::class, MasterReferensiPolicy::class);
        Gate::policy(MasterSyaratDokumen::class, MasterReferensiPolicy::class);

        foreach ([
            RefAgama::class => 'ref_agama',
            RefStatusKawin::class => 'ref_status_kawin',
            RefShdk::class => 'ref_shdk',
            RefPendidikan::class => 'ref_pendidikan',
            RefPekerjaan::class => 'ref_pekerjaan',
            RefKewarganegaraan::class => 'ref_kewarganegaraan',
            RefSuku::class => 'ref_suku',
            Jorong::class => 'jorongs',
        ] as $modelClass => $table) {
            $modelClass::saved(fn () => MasterReferensiHelper::forgetOptionsForTable($table));
            $modelClass::deleted(fn () => MasterReferensiHelper::forgetOptionsForTable($table));
        }
    }
}

<x-filament-widgets::widget>
    <div class="taram-welcome-card">
        <x-filament::icon
            icon="heroicon-o-building-library"
            class="taram-welcome-ornament"
        />

        <div class="taram-welcome-content">
            <div class="taram-welcome-identity">
                <span class="taram-welcome-avatar" aria-hidden="true">
                    {{ $initials }}
                </span>

                <div class="min-w-0">
                    <p class="taram-welcome-heading">
                        {{ $greeting }}, {{ $name }}
                    </p>
                    <p class="taram-welcome-subtitle">
                        {{ $roleLabel }} &bull; {{ $contextDescription }}
                    </p>
                </div>
            </div>

            <div class="taram-welcome-aside">
                @if ($newSubmissionUrl)
                    <a
                        href="{{ $newSubmissionUrl }}"
                        class="taram-welcome-action"
                        wire:navigate
                    >
                        <x-filament::icon icon="heroicon-m-document-plus" class="h-5 w-5" />
                        <span>Ajukan Surat Baru</span>
                    </a>
                @endif

                <div class="taram-welcome-date">
                    <p>
                        <x-filament::icon icon="heroicon-m-calendar-days" class="h-4 w-4" />
                        <span>{{ $dateLabel }}</span>
                    </p>
                    <span class="taram-welcome-time">{{ $timeLabel }}</span>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>

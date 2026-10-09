<?php

namespace App\Services;

use InvalidArgumentException;

class NomorSuratFormatter
{
    public const DEFAULT_PATTERN = '{KODE_KLASIFIKASI}/{NOMOR_URUT}/{KODE_UNIT}/{TAHUN}';

    /**
     * @return array<string, string>
     */
    private function replacements(string $kodeKlasifikasi, string $kodeUnit, string $nomorUrut, int $tahun): array
    {
        return [
            '[Kode Klasifikasi]' => $kodeKlasifikasi,
            '[kode_klasifikasi]' => $kodeKlasifikasi,
            '{KODE_KLASIFIKASI}' => $kodeKlasifikasi,
            '[Nomor Urut]' => $nomorUrut,
            '[nomor_urut]' => $nomorUrut,
            '{NOMOR_URUT}' => $nomorUrut,
            '[Kode Unit]' => $kodeUnit,
            '[kode_unit]' => $kodeUnit,
            '{KODE_UNIT}' => $kodeUnit,
            '[Tahun]' => (string) $tahun,
            '[tahun]' => (string) $tahun,
            '{TAHUN}' => (string) $tahun,
        ];
    }

    /**
     * @return array<string>
     */
    public function problems(?string $pattern, ?string $resetCounter = null): array
    {
        $rawPattern = filled($pattern) ? $pattern : self::DEFAULT_PATTERN;
        $pattern = trim($rawPattern);
        $knownTokens = array_keys($this->replacements('', '', '', 0));
        $issues = [];

        if (! str_contains($pattern, '{NOMOR_URUT}')
            && ! str_contains($pattern, '[Nomor Urut]')
            && ! str_contains($pattern, '[nomor_urut]')) {
            $issues[] = 'Pola nomor harus memuat variabel {NOMOR_URUT} atau [Nomor Urut].';
        }

        preg_match_all('/\{[^{}]+\}|\[[^\[\]]+\]/u', $pattern, $matches);
        foreach (array_unique($matches[0]) as $token) {
            if (! in_array($token, $knownTokens, true)) {
                $issues[] = "Variabel nomor {$token} tidak dikenal.";
            }
        }

        $literal = str_replace($knownTokens, '', $pattern);
        if (strpbrk($literal, '{}[]') !== false) {
            $issues[] = 'Pola nomor memiliki tanda kurung variabel yang tidak lengkap.';
        }

        if (preg_match('/[[:cntrl:]<>]/u', $rawPattern)) {
            $issues[] = 'Pola nomor tidak boleh memuat baris baru atau tanda < dan >.';
        }

        if ($resetCounter === 'tahunan'
            && ! str_contains($pattern, '{TAHUN}')
            && ! str_contains($pattern, '[Tahun]')
            && ! str_contains($pattern, '[tahun]')) {
            $issues[] = 'Reset tahunan memerlukan variabel {TAHUN} agar nomor tidak berulang pada tahun berikutnya.';
        }

        return $issues;
    }

    public function format(?string $pattern, string $kodeKlasifikasi, string $kodeUnit, int $nomorUrut, int $padding, int $tahun): string
    {
        $issues = $this->problems($pattern);
        if ($issues !== []) {
            throw new InvalidArgumentException(implode(' ', $issues));
        }

        $pattern = filled($pattern) ? trim($pattern) : self::DEFAULT_PATTERN;
        $formattedNumber = $padding > 0
            ? str_pad((string) $nomorUrut, $padding, '0', STR_PAD_LEFT)
            : (string) $nomorUrut;
        $replacements = $this->replacements($kodeKlasifikasi, $kodeUnit, $formattedNumber, $tahun);

        return str_replace(array_keys($replacements), array_values($replacements), $pattern);
    }

    public function extractNomorUrut(?string $pattern, string $nomorSurat): ?int
    {
        $pattern = filled($pattern) ? trim($pattern) : self::DEFAULT_PATTERN;
        $tokens = preg_split(
            '/(\{NOMOR_URUT\}|\[Nomor Urut\]|\[nomor_urut\]|\{KODE_KLASIFIKASI\}|\[Kode Klasifikasi\]|\[kode_klasifikasi\]|\{KODE_UNIT\}|\[Kode Unit\]|\[kode_unit\]|\{TAHUN\}|\[Tahun\]|\[tahun\])/u',
            $pattern,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        );

        if ($tokens === false) {
            return null;
        }

        $nomorTokens = ['{NOMOR_URUT}', '[Nomor Urut]', '[nomor_urut]'];
        $tahunTokens = ['{TAHUN}', '[Tahun]', '[tahun]'];
        $knownTokens = array_keys($this->replacements('', '', '', 0));
        $regex = '';

        foreach ($tokens as $token) {
            if (in_array($token, $nomorTokens, true)) {
                $regex .= '(?<nomor_urut>[0-9]+)';
            } elseif (in_array($token, $tahunTokens, true)) {
                $regex .= '[0-9]{4}';
            } elseif (in_array($token, $knownTokens, true)) {
                $regex .= '.*?';
            } else {
                $regex .= preg_quote($token, '/');
            }
        }

        if (preg_match('/^'.$regex.'$/uD', trim($nomorSurat), $matches) !== 1) {
            return null;
        }

        $rawNomorUrut = $matches['nomor_urut'] ?? null;
        if (! is_string($rawNomorUrut) || ! ctype_digit($rawNomorUrut)) {
            return null;
        }

        $normalizedNomorUrut = ltrim($rawNomorUrut, '0');
        $nomorUrut = filter_var($normalizedNomorUrut === '' ? '0' : $normalizedNomorUrut, FILTER_VALIDATE_INT);

        return $nomorUrut === false ? null : $nomorUrut;
    }
}

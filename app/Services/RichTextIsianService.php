<?php

namespace App\Services;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Throwable;

class RichTextIsianService
{
    /** @param string|array<string, mixed> $value */
    public function sanitize(string|array $value): string
    {
        $html = is_array($value) ? RichContentRenderer::make($value)->toHtml() : $value;
        $config = new HtmlSanitizerConfig;

        foreach (['p', 'div', 'br', 'span', 'a', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'blockquote'] as $tag) {
            $config = $config->allowElement($tag);
        }

        return (new HtmlSanitizer($config))->sanitize($html);
    }

    public function text(string $sanitizedHtml): string
    {
        $spacedHtml = preg_replace('/<\/?(?:p|div|br|li|blockquote)\b[^>]*>/i', ' ', $sanitizedHtml);
        $text = html_entity_decode(strip_tags((string) $spacedHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
    }

    /**
     * HTML aman yang dapat disisipkan di tengah kalimat surat: paragraf menjadi baris baru,
     * butir daftar menjadi "• butir", format tebal/miring/garis bawah/tautan dipertahankan.
     */
    public function sebaris(mixed $value): string
    {
        $html = $this->display($value);
        $html = (string) preg_replace('/<li\b[^>]*>/i', '• ', $html);
        $html = (string) preg_replace('/<\/(?:p|div|li|blockquote)>/i', '<br>', $html);
        $html = (string) preg_replace('/<\/?(?:p|div|ul|ol|blockquote)\b[^>]*>/i', '', $html);
        $html = (string) preg_replace('/(?:\s*<br\s*\/?>\s*)+$/i', '', $html);

        return trim((string) preg_replace('/(?:<br\s*\/?>\s*){2,}/i', '<br>', $html));
    }

    public function display(mixed $value): string
    {
        if (! is_string($value) && ! is_array($value)) {
            return '';
        }

        try {
            return $this->sanitize($value);
        } catch (Throwable) {
            return '';
        }
    }
}

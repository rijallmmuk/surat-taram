@php
    $initialHtml = app(\App\Services\RichTextIsianService::class)->display($dataIsian[$field->nama_field] ?? null);
    $statePath = 'dataIsian.'.$field->nama_field;
@endphp

<div
    wire:ignore
    x-data="{
        sync() {
            $wire.set(@js($statePath), $refs.editor.innerHTML);
        },
        format(command) {
            $refs.editor.focus();
            document.execCommand(command, false, null);
            this.sync();
        }
    }"
    class="overflow-hidden rounded-xl border border-slate-300 bg-white focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500"
>
    <div role="toolbar" aria-label="Format {{ $field->label }}" class="flex flex-wrap gap-1 border-b border-slate-200 bg-slate-50 px-2 py-1.5">
        <button type="button" @click.prevent="format('bold')" aria-label="Tebal" class="rounded px-2 py-1 text-sm font-bold text-slate-700 hover:bg-slate-200 focus-visible:outline-2 focus-visible:outline-emerald-600">B</button>
        <button type="button" @click.prevent="format('italic')" aria-label="Miring" class="rounded px-2 py-1 text-sm italic text-slate-700 hover:bg-slate-200 focus-visible:outline-2 focus-visible:outline-emerald-600">I</button>
        <button type="button" @click.prevent="format('underline')" aria-label="Garis bawah" class="rounded px-2 py-1 text-sm underline text-slate-700 hover:bg-slate-200 focus-visible:outline-2 focus-visible:outline-emerald-600">U</button>
        <button type="button" @click.prevent="format('insertUnorderedList')" aria-label="Daftar berpoin" class="rounded px-2 py-1 text-sm text-slate-700 hover:bg-slate-200 focus-visible:outline-2 focus-visible:outline-emerald-600">• Daftar</button>
        <button type="button" @click.prevent="format('insertOrderedList')" aria-label="Daftar bernomor" class="rounded px-2 py-1 text-sm text-slate-700 hover:bg-slate-200 focus-visible:outline-2 focus-visible:outline-emerald-600">1. Daftar</button>
    </div>
    <div
        x-ref="editor"
        x-init="$refs.editor.innerHTML = @js($initialHtml)"
        contenteditable="true"
        role="textbox"
        aria-label="{{ $field->label }}"
        aria-multiline="true"
        @input.debounce.250ms="sync()"
        @blur="sync()"
        class="min-h-32 px-3.5 py-2.5 text-sm text-slate-800 outline-none [&_ul]:list-disc [&_ol]:list-decimal [&_ul]:pl-5 [&_ol]:pl-5 [&_p]:mb-2"
    ></div>
</div>

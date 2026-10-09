<script>
    (() => {
        if (window.taramValidasiBrowser) return;
        window.taramValidasiBrowser = true;

        const pesan = (el) => {
            const v = el.validity;
            if (v.valueMissing) return el.type === 'file' ? 'Pilih berkas terlebih dahulu.' : 'Kolom ini wajib diisi.';
            if (v.typeMismatch) return el.type === 'email' ? 'Masukkan alamat email yang benar.' : 'Format isian tidak sesuai.';
            if (v.patternMismatch) return 'Format isian tidak sesuai.';
            if (v.tooShort) return `Minimal ${el.minLength} karakter.`;
            if (v.tooLong) return `Maksimal ${el.maxLength} karakter.`;
            if (v.rangeUnderflow) return `Nilai minimal ${el.min}.`;
            if (v.rangeOverflow) return `Nilai maksimal ${el.max}.`;
            if (v.stepMismatch || v.badInput) return 'Masukkan nilai yang benar.';
            return '';
        };

        document.addEventListener('invalid', (event) => {
            const el = event.target;
            if (! el || typeof el.setCustomValidity !== 'function') return;
            el.setCustomValidity('');
            if (! el.validity.valid) el.setCustomValidity(pesan(el));
        }, true);

        // Bersihkan pesan lama sebelum browser memvalidasi ulang, termasuk kolom yang nilainya diisi lewat skrip.
        document.addEventListener('click', (event) => {
            const form = event.target.closest?.('button[type="submit"], input[type="submit"]')?.form;
            if (form) [...form.elements].forEach((el) => el.setCustomValidity?.(''));
        }, true);

        ['input', 'change'].forEach((jenis) => document.addEventListener(jenis, (event) => {
            const el = event.target;
            if (el && typeof el.setCustomValidity === 'function') el.setCustomValidity('');
        }, true));
    })();
</script>

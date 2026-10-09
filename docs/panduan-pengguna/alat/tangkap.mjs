#!/usr/bin/env node
// Pengambil screenshot buku panduan tanpa dependensi: mengendalikan Google Chrome lewat DevTools Protocol.
//
// Pakai:
//   node tangkap.mjs mulai                       -> jalankan Chrome headless (port 9333)
//   node tangkap.mjs langkah <tab> <file.json>   -> jalankan daftar langkah pada tab (satu tab per peran)
//   node tangkap.mjs selesai                     -> tutup Chrome
//
// Langkah (JSON array), contoh:
//   {"layar": "laptop"} | {"layar": "hp"}
//   {"buka": "/panel"}
//   {"isi": "input[id$='nik']", "nilai": "1307..."}      -> isi kolom dan picu event input/change/blur
//   {"pilih": "select[id$='status']", "nilai": "aktif"}
//   {"klik": "button[type=submit]"} | {"klikTeks": "Selanjutnya"}
//   {"tunggu": 1500} | {"tungguTeks": "Dasbor"} | {"tungguElemen": ".fi-modal"}
//   {"js": "document.title"}                             -> cetak hasil evaluasi
//   {"gulir": "selector"} | {"gulirAtas": true}
//   {"tandai": ["selector", ...]} | {"tandaiTeks": ["Simpan", ...]} | {"hapusTanda": true}
//   {"foto": "nama.png"} | {"foto": "nama.png", "elemen": "selector", "jarak": 12} | {"foto": "nama.png", "penuh": true}

import { spawn } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ALAT = dirname(fileURLToPath(import.meta.url));
const GAMBAR = resolve(ALAT, '..', 'gambar');
const PORT = 9333;
const DASAR = process.env.PANDUAN_URL ?? 'http://127.0.0.1:8000';
const STATE = join(ALAT, '.tab.json');
const LAYAR = {
    laptop: { width: 1366, height: 820, deviceScaleFactor: 1, mobile: false },
    hp: { width: 390, height: 844, deviceScaleFactor: 2, mobile: true },
};

const tidur = (ms) => new Promise((r) => setTimeout(r, ms));

async function json(path, method = 'GET') {
    const res = await fetch(`http://127.0.0.1:${PORT}${path}`, { method });
    return res.json();
}

class Sesi {
    constructor(url) {
        this.ws = new WebSocket(url);
        this.id = 0;
        this.tunggu = new Map();
        this.ws.onmessage = (e) => {
            const m = JSON.parse(e.data);
            if (m.id && this.tunggu.has(m.id)) {
                const { ok, gagal } = this.tunggu.get(m.id);
                this.tunggu.delete(m.id);
                m.error ? gagal(new Error(m.error.message)) : ok(m.result);
            }
        };
    }
    siap() {
        return new Promise((r) => (this.ws.onopen = r));
    }
    kirim(method, params = {}) {
        const id = ++this.id;
        this.ws.send(JSON.stringify({ id, method, params }));
        return new Promise((ok, gagal) => this.tunggu.set(id, { ok, gagal }));
    }
    async nilai(expression) {
        const r = await this.kirim('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
        if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description ?? r.exceptionDetails.text);
        return r.result.value;
    }
}

async function mulai() {
    const profil = join(ALAT, '.profil-chrome');
    mkdirSync(profil, { recursive: true });
    const chrome = spawn('google-chrome', [
        '--headless=new', `--remote-debugging-port=${PORT}`, `--user-data-dir=${profil}`,
        '--no-first-run', '--hide-scrollbars', '--force-color-profile=srgb', '--lang=id-ID', 'about:blank',
    ], { detached: true, stdio: 'ignore' });
    chrome.unref();
    for (let i = 0; i < 50; i++) {
        try { await json('/json/version'); writeFileSync(STATE, '{}'); console.log('Chrome siap'); return; } catch { await tidur(200); }
    }
    throw new Error('Chrome tidak dapat dijalankan');
}

async function tab(nama) {
    const daftar = existsSync(STATE) ? JSON.parse(readFileSync(STATE, 'utf8')) : {};
    const target = (await json('/json/list')).find((t) => t.id === daftar[nama]);
    if (target) return target.webSocketDebuggerUrl;

    const browser = new Sesi((await json('/json/version')).webSocketDebuggerUrl);
    await browser.siap();
    // Setiap peran memakai konteks terpisah sehingga cookie login tidak tercampur.
    const { browserContextId } = await browser.kirim('Target.createBrowserContext', { disposeOnDetach: false });
    const { targetId } = await browser.kirim('Target.createTarget', { url: 'about:blank', browserContextId });
    browser.ws.close();
    daftar[nama] = targetId;
    writeFileSync(STATE, JSON.stringify(daftar, null, 2));

    return (await json('/json/list')).find((t) => t.id === targetId).webSocketDebuggerUrl;
}

const css = (s) => JSON.stringify(s);

async function tungguTenang(s, maks = 15000) {
    const awal = Date.now();
    await tidur(250);
    while (Date.now() - awal < maks) {
        const sibuk = await s.nilai(`(() => {
            if (document.readyState !== 'complete') return true;
            const lw = window.Livewire?.all?.() ?? [];
            if ([...document.querySelectorAll('[wire\\\\:loading]:not([style*="display: none"])')].some(e => e.offsetParent)) return true;
            return lw.some(c => c.__instance?.effects?.loading);
        })()`).catch(() => true);
        if (!sibuk) break;
        await tidur(200);
    }
    await tidur(400);
}

async function jalankan(s, langkah) {
    for (const l of langkah) {
        if (l.layar) {
            const v = LAYAR[l.layar];
            s.layar = l.layar;
            await s.kirim('Emulation.setDeviceMetricsOverride', v);
            await s.kirim('Emulation.setUserAgentOverride', {
                userAgent: v.mobile
                    ? 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Mobile Safari/537.36'
                    : 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36',
            });
            await s.kirim('Emulation.setTouchEmulationEnabled', { enabled: v.mobile });
        } else if (l.buka) {
            await s.kirim('Page.navigate', { url: l.buka.startsWith('http') ? l.buka : DASAR + l.buka });
            await tidur(600);
            await tungguTenang(s);
        } else if (l.isi) {
            await s.nilai(`(() => { const e = document.querySelector(${css(l.isi)}); if (!e) throw new Error('tidak ada ' + ${css(l.isi)});
                e.focus(); e.value = ${css(l.nilai)}; for (const t of ['input','change']) e.dispatchEvent(new Event(t, {bubbles: true}));
                e.blur(); e.dispatchEvent(new Event('blur', {bubbles: true})); })()`);
            await tungguTenang(s);
        } else if (l.ketik) {
            await s.nilai(`document.querySelector(${css(l.ketik)}).focus()`);
            await s.kirim('Input.insertText', { text: l.nilai });
            await tungguTenang(s);
        } else if (l.unggah) {
            const { root } = await s.kirim('DOM.getDocument', { depth: -1, pierce: true });
            const { nodeIds } = await s.kirim('DOM.querySelectorAll', { nodeId: root.nodeId, selector: l.unggah });
            const nodeId = nodeIds[l.urutan ?? 0];
            if (!nodeId) throw new Error('tidak ada input berkas ' + l.unggah);
            await s.kirim('DOM.setFileInputFiles', { nodeId, files: [resolve(ALAT, l.berkas)] });
            await tidur(1500);
            await tungguTenang(s, 20000);
        } else if (l.teks !== undefined) {
            // Ketik pada posisi kursor saat ini (mis. di editor isi surat) tanpa memindahkan fokus.
            await s.kirim('Input.insertText', { text: l.teks });
            await tungguTenang(s);
        } else if (l.tombol) {
            const kode = { Enter: 13, Escape: 27, Backspace: 8, ArrowDown: 40, ArrowUp: 38, Tab: 9 }[l.tombol] ?? 0;
            for (let i = 0; i < (l.ulang ?? 1); i++) {
                await s.kirim('Input.dispatchKeyEvent', { type: 'rawKeyDown', key: l.tombol, code: l.tombol, windowsVirtualKeyCode: kode });
                if (l.tombol === 'Enter') await s.kirim('Input.dispatchKeyEvent', { type: 'char', text: '\r', key: 'Enter', windowsVirtualKeyCode: 13 });
                await s.kirim('Input.dispatchKeyEvent', { type: 'keyUp', key: l.tombol, code: l.tombol, windowsVirtualKeyCode: kode });
            }
            await tungguTenang(s);
        } else if (l.pilih) {
            await s.nilai(`(() => { const e = document.querySelector(${css(l.pilih)}); if (!e) throw new Error('tidak ada ' + ${css(l.pilih)});
                e.value = ${css(l.nilai)}; e.dispatchEvent(new Event('change', {bubbles: true})); })()`);
            await tungguTenang(s);
        } else if (l.klik) {
            await s.nilai(`(() => { const e = document.querySelector(${css(l.klik)}); if (!e) throw new Error('tidak ada ' + ${css(l.klik)}); e.scrollIntoView({block: 'center'}); e.click(); })()`);
            await tungguTenang(s, l.maks ?? 15000);
        } else if (l.tekan || l.tekanTeks) {
            // Klik dengan peristiwa mouse sungguhan (untuk dropdown/menu Alpine yang mengabaikan element.click()).
            const sasaran = l.tekan
                ? `document.querySelector(${css(l.tekan)})`
                : `[...document.querySelectorAll('button, a, [role=button], [role=option], label, li, span, div, h2, h3, h4')].filter(x => x.offsetParent && x.textContent.trim() === ${css(l.tekanTeks)}).pop()`;
            const p = await s.nilai(`(() => { const e = ${sasaran}; if (!e) return null; e.scrollIntoView({block: 'center'});
                const r = e.getBoundingClientRect(); return {x: r.left + r.width / 2, y: r.top + r.height / 2}; })()`);
            if (!p) throw new Error('tidak ada ' + (l.tekan ?? l.tekanTeks));
            for (const type of ['mouseMoved', 'mousePressed', 'mouseReleased']) {
                await s.kirim('Input.dispatchMouseEvent', { type, x: p.x, y: p.y, button: 'left', clickCount: 1 });
            }
            await tungguTenang(s, l.maks ?? 15000);
        } else if (l.klikTeks) {
            await s.nilai(`(() => { const t = ${css(l.klikTeks)}; const e = [...document.querySelectorAll('button, a, [role=button], [role=tab], label, li, span, div')]
                .filter(x => x.offsetParent && x.textContent.trim() === t).pop(); if (!e) throw new Error('tidak ada teks ' + t); e.scrollIntoView({block: 'center'}); e.click(); })()`);
            await tungguTenang(s, l.maks ?? 15000);
        } else if (l.tunggu) {
            await tidur(l.tunggu);
        } else if (l.tungguTeks) {
            for (let i = 0; i < 75 && !(await s.nilai(`!!document.body?.innerText?.includes(${css(l.tungguTeks)})`).catch(() => false)); i++) await tidur(200);
            await tungguTenang(s);
        } else if (l.tungguElemen) {
            for (let i = 0; i < 75 && !(await s.nilai(`!!document.querySelector(${css(l.tungguElemen)})`).catch(() => false)); i++) await tidur(200);
            await tungguTenang(s);
        } else if (l.js) {
            console.log(JSON.stringify(await s.nilai(l.js)));
            await tungguTenang(s);
        } else if (l.gulir) {
            await s.nilai(`document.querySelector(${css(l.gulir)})?.scrollIntoView({block: ${css(l.posisi ?? 'start')}}); window.scrollBy(0, ${l.geser ?? -80})`);
            await tidur(300);
        } else if (l.gulirAtas) {
            await s.nilai('window.scrollTo(0, 0)');
            await tidur(300);
        } else if (l.tandai || l.tandaiTeks) {
            await s.nilai(`(() => {
                let n = document.querySelectorAll('.panduan-tanda').length;
                const pasang = (e) => { if (!e) return; n++; e.classList.add('panduan-tanda'); e.dataset.panduanNomor = n;
                    e.style.setProperty('outline', '3px solid #e11d48', 'important'); e.style.setProperty('outline-offset', '3px', 'important');
                    e.style.setProperty('border-radius', getComputedStyle(e).borderRadius || '6px');
                    const b = document.createElement('span'); b.className = 'panduan-nomor'; b.textContent = n;
                    const r = e.getBoundingClientRect();
                    Object.assign(b.style, {position: 'absolute', left: (r.left + window.scrollX - 14) + 'px', top: (r.top + window.scrollY - 14) + 'px',
                        width: '26px', height: '26px', borderRadius: '50%', background: '#e11d48', color: '#fff', font: 'bold 14px/26px sans-serif',
                        textAlign: 'center', zIndex: 99999, boxShadow: '0 1px 3px rgba(0,0,0,.4)'});
                    document.body.appendChild(b); };
                for (const s of ${css(l.tandai ?? [])}) pasang(document.querySelector(s));
                for (const t of ${css(l.tandaiTeks ?? [])}) pasang([...document.querySelectorAll('button, a, label, [role=tab], span, h2, h3, li')]
                    .filter(x => x.offsetParent && x.textContent.trim() === t).pop());
            })()`);
        } else if (l.hapusTanda) {
            await s.nilai(`document.querySelectorAll('.panduan-nomor').forEach(e => e.remove());
                document.querySelectorAll('.panduan-tanda').forEach(e => { e.classList.remove('panduan-tanda'); e.style.removeProperty('outline'); e.style.removeProperty('outline-offset'); })`);
        } else if (l.foto) {
            mkdirSync(GAMBAR, { recursive: true });
            const opsi = { format: 'png', captureBeyondViewport: !!l.penuh };
            if (l.elemen) {
                const r = await s.nilai(`(() => { const e = document.querySelector(${css(l.elemen)}); if (!e) return null; const r = e.getBoundingClientRect();
                    return {x: r.left + window.scrollX, y: r.top + window.scrollY, w: r.width, h: r.height}; })()`);
                if (!r) throw new Error('elemen foto tidak ada: ' + l.elemen);
                const j = l.jarak ?? 12;
                opsi.clip = { x: Math.max(0, r.x - j), y: Math.max(0, r.y - j), width: r.w + 2 * j, height: r.h + 2 * j, scale: 1 };
                opsi.captureBeyondViewport = true;
            }
            let layarDiregang = false;
            if (l.penuh && !l.elemen) {
                // Layar diregangkan setinggi halaman agar elemen tetap (navigasi bawah HP) berada di dasar gambar.
                await s.nilai('window.scrollTo(0, 0)');
                const h = Math.min(await s.nilai('Math.max(document.documentElement.scrollHeight, document.body.scrollHeight)'), l.maksTinggi ?? 4000);
                const v = LAYAR[s.layar ?? 'laptop'];
                await s.kirim('Emulation.setDeviceMetricsOverride', { ...v, height: Math.max(v.height, Math.ceil(h)) });
                await tidur(500);
                opsi.captureBeyondViewport = false;
                layarDiregang = true;
            }
            const { data } = await s.kirim('Page.captureScreenshot', opsi);
            if (layarDiregang) {
                await s.kirim('Emulation.setDeviceMetricsOverride', LAYAR[s.layar ?? 'laptop']);
                await tidur(300);
            }
            writeFileSync(join(GAMBAR, l.foto), Buffer.from(data, 'base64'));
            console.log('foto', l.foto);
        }
    }
}

const [perintah, nama, berkas] = process.argv.slice(2);
if (perintah === 'mulai') {
    await mulai();
} else if (perintah === 'selesai') {
    const b = new Sesi((await json('/json/version')).webSocketDebuggerUrl);
    await b.siap();
    await b.kirim('Browser.close').catch(() => {});
    console.log('Chrome ditutup');
} else if (perintah === 'pdf') {
    // node tangkap.mjs pdf <berkas.html> <keluaran.pdf>
    const s = new Sesi(await tab('cetak'));
    await s.siap();
    await s.kirim('Page.enable');
    await s.kirim('Emulation.clearDeviceMetricsOverride').catch(() => {});
    await s.kirim('Emulation.setEmulatedMedia', { media: 'print' });
    await s.kirim('Page.navigate', { url: 'file://' + resolve(nama) });
    await tidur(1500);
    for (let i = 0; i < 100 && !(await s.nilai('document.readyState === "complete" && [...document.images].every(i => i.complete)')); i++) await tidur(200);
    const { data } = await s.kirim('Page.printToPDF', { printBackground: true, preferCSSPageSize: true, displayHeaderFooter: false, generateDocumentOutline: true, generateTaggedPDF: true });
    writeFileSync(resolve(berkas), Buffer.from(data, 'base64'));
    console.log('PDF', resolve(berkas));
    s.ws.close();
} else if (perintah === 'langkah') {
    const s = new Sesi(await tab(nama));
    await s.siap();
    await s.kirim('Page.enable');
    await s.kirim('Runtime.enable');
    const isi = berkas === '-' ? readFileSync(0, 'utf8') : readFileSync(berkas, 'utf8');
    // Pengaturan layar hilang saat koneksi ditutup, jadi diterapkan ulang dari catatan tab.
    const catatan = JSON.parse(readFileSync(STATE, 'utf8'));
    const layarLama = catatan[`${nama}:layar`] ?? 'laptop';
    try {
        await jalankan(s, [{ layar: layarLama }]);
        await jalankan(s, JSON.parse(isi));
    } finally {
        const terbaru = JSON.parse(readFileSync(STATE, 'utf8'));
        terbaru[`${nama}:layar`] = s.layar ?? layarLama;
        writeFileSync(STATE, JSON.stringify(terbaru, null, 2));
        s.ws.close();
    }
} else {
    console.log('Perintah: mulai | langkah <tab> <file.json|-> | selesai');
}
process.exit(0);

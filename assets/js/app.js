// Konfirmasi aksi penting
document.querySelectorAll('form[data-yakin]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.dataset.yakin)) e.preventDefault(); });
});
// Filter berubah = langsung tampilkan
document.querySelectorAll('form[data-auto] select').forEach(function (el) {
    el.addEventListener('change', function () { el.form.submit(); });
});
// Centang semua
document.querySelectorAll('[data-semua]').forEach(function (c) {
    c.addEventListener('change', function () {
        c.closest('table').querySelectorAll('tbody input[type=checkbox]').forEach(function (x) { x.checked = c.checked; });
    });
});
// Cegah klik dobel pada tombol simpan (kecuali bila pengiriman dibatalkan konfirmasi)
document.querySelectorAll('form[method=post]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
        setTimeout(function () {
            if (ev.defaultPrevented) return;
            f.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
        }, 0);
    });
});
// Menu samping di HP
var bm = document.getElementById('buka-menu'), sp = document.getElementById('samping');
if (bm && sp) {
    bm.addEventListener('click', function () { sp.classList.toggle('buka'); });
}
// Form catat kegiatan: tampilkan pilihan sesuai jenis (makan -> porsi, mood -> wajah, susu -> ml, ...)
document.querySelectorAll('.catat-cepat').forEach(function (f) {
    var teks = f.querySelector('label.f[data-untuk]');
    function atur() {
        var j = (f.querySelector('input[name=jenis]:checked') || {}).value;
        f.querySelectorAll('[data-untuk]').forEach(function (el) {
            var cocok = el.dataset.untuk.split(' ').indexOf(j) >= 0;
            el.hidden = !cocok;
            el.querySelectorAll('input').forEach(function (i) { i.disabled = !cocok; i.required = cocok && i.type === 'radio'; });
        });
        if (teks && !teks.hidden) {
            var lbl = teks.querySelector('span');
            lbl.textContent = lbl.dataset['label' + j.charAt(0).toUpperCase() + j.slice(1)] || 'Nilai';
            teks.querySelector('input').inputMode = j === 'susu' ? 'numeric' : 'text';
            teks.querySelector('input').placeholder = j === 'susu' ? '150' : 'Paracetamol sirup 2,5 ml';
        }
    }
    f.querySelectorAll('input[name=jenis]').forEach(function (r) { r.addEventListener('change', atur); });
    atur();
});

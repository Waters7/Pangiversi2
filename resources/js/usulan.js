
let pesertaIndex = 0;
const pesertaModal = document.getElementById('pesertaModal');
const tambahPesertaBtn = document.getElementById('tambahPesertaBtn');
const closePesertaModal = document.getElementById('closePesertaModal');
const cancelPesertaBtn = document.getElementById('cancelPesertaBtn');
const savePesertaBtn = document.getElementById('savePesertaBtn');
const pesertaFormContainer = document.getElementById('pesertaFormContainer');
const pesertaContainer = document.getElementById('pesertaContainer');

function openModal() {
    const currentIndex = pesertaIndex++;

    pesertaFormContainer.innerHTML = `
        <div id="formPeserta_${currentIndex}">
            <div class="flex items-center justify-between mb-4">
                <p class="text-sm font-semibold text-slate-700">Peserta #${pesertaContainer.children.length + 2}</p>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Nama Peserta <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="pesertaNama_${currentIndex}"
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent transition"
                        placeholder="Nama lengkap peserta"
                        required>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                            NIP <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="pesertaNip_${currentIndex}"
                            class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent transition"
                            placeholder="Nomor Induk Pegawai"
                            required>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                            Unit Kerja <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="pesertaUnit_${currentIndex}"
                            class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent transition"
                            placeholder="Nama unit kerja / bagian"
                            required>
                    </div>
                </div>

                
                <div class="flex items-center gap-2.5 p-3.5 bg-amber-50 rounded-xl border border-amber-100">
                    <input type="checkbox" id="pesertaBukanPegawai_${currentIndex}"
                        class="w-4 h-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500 cursor-pointer">
                    <label for="pesertaBukanPegawai_${currentIndex}" class="flex-1 text-sm font-medium text-slate-700 cursor-pointer">
                        Bukan pegawai (keluarga, tamu, dll)
                    </label>
                </div>

                <div>
                    <label for="pesertaCatatan_${currentIndex}" class="text-sm font-semibold text-slate-700 mb-1.5 hidden">
                        Catatan / Keterangan Peserta
                        <span class="text-xs font-normal text-slate-400 ml-1">(jelaskan siapa dan urgensinya)</span>
                    </label>
                    <textarea id="pesertaCatatan_${currentIndex}" rows="3"
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent transition resize-none hidden"
                        placeholder="cth: Keluarga kepala dinas yang ikut dalam kunjungan bilateral, dll"></textarea>
                </div>
            </div>
        </div>
    `;

    pesertaModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    // Toggle NIP & Unit Kerja based on checkbox
    const checkboxBukanPegawai = document.getElementById(`pesertaBukanPegawai_${currentIndex}`);
    const inputNip = document.getElementById(`pesertaNip_${currentIndex}`);
    const inputUnit = document.getElementById(`pesertaUnit_${currentIndex}`);
    const inputCatatan = document.getElementById(`pesertaCatatan_${currentIndex}`);
    const labelCatatan = inputCatatan.previousElementSibling;

    checkboxBukanPegawai.addEventListener('change', function() {
        if (this.checked) {
            inputNip.disabled = true;
            inputUnit.disabled = true;
            inputNip.value = '';
            inputUnit.value = '';
            inputNip.removeAttribute('required');
            inputUnit.removeAttribute('required');
            
            // Show catatan field
            inputCatatan.classList.remove('hidden');
            labelCatatan.classList.remove('hidden');
        } else {
            inputNip.disabled = false;
            inputUnit.disabled = false;
            inputNip.setAttribute('required', 'required');
            inputUnit.setAttribute('required', 'required');
            
            // Hide catatan field
            inputCatatan.classList.add('hidden');
            labelCatatan.classList.add('hidden');
            inputCatatan.value = '';
        }
    });

    // Save handler
    savePesertaBtn.onclick = () => savePeserta(currentIndex);
}

function savePeserta(index) {
    const nama = document.getElementById(`pesertaNama_${index}`).value.trim();
    const checkboxBukanPegawai = document.getElementById(`pesertaBukanPegawai_${index}`);
    const nip = document.getElementById(`pesertaNip_${index}`).value.trim();
    const unitKerja = document.getElementById(`pesertaUnit_${index}`).value.trim();

    if (!nama) {
        alert('Nama peserta harus diisi!');
        return;
    }

    if (!checkboxBukanPegawai.checked && (!nip || !unitKerja)) {
        alert('NIP dan Unit Kerja harus diisi untuk pegawai!');
        return;
    }

    addPesertaForm(index, nama, nip, unitKerja, checkboxBukanPegawai.checked);
    closeModal();
}

function addPesertaForm(index, nama, nip, unitKerja, isBukanPegawai) {
    const nipDisplay = isBukanPegawai ? 'Non-Pegawai' : `NIP: ${nip}`;
    const unitDisplay = isBukanPegawai ? '' : `${unitKerja} · `;
    
    const pesertaHTML = `
        <div class="peserta-item flex items-center gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200 group" data-peserta-id="${index}">
            <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-slate-800 truncate">${nama}</p>
            <p class="text-xs text-slate-500 truncate">${unitDisplay}${nipDisplay}</p>
            </div>
            <button type="button" class="infoPesertaBtn text-slate-400 hover:text-blue-500 transition"
                data-peserta-id="${index}"
                title="Lihat informasi peserta">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            </button>
            <button type="button" class="removePesertaBtn text-slate-300 group-hover:text-red-500 transition"
                data-peserta-id="${index}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M6 18L18 6M6 6l12 12"/>
            </svg>
            </button>

            
            <input type="hidden" name="peserta[${index}][nama]" value="${nama}">
            <input type="hidden" name="peserta[${index}][nip]" value="${nip || ''}">
            <input type="hidden" name="peserta[${index}][unit_kerja]" value="${unitKerja || ''}">
            <input type="hidden" name="peserta[${index}][bukan_pegawai]" value="${isBukanPegawai ? 1 : 0}">
            <input type="hidden" name="peserta[${index}][catatan]" value="${document.getElementById(`pesertaCatatan_${index}`)?.value || ''}">
        </div>
        `;

    pesertaContainer.insertAdjacentHTML('beforeend', pesertaHTML);
}

function closeModal() {
    pesertaModal.classList.add('hidden');
    document.body.style.overflow = '';
}

// Event listeners
tambahPesertaBtn.addEventListener('click', openModal);
closePesertaModal.addEventListener('click', closeModal);
cancelPesertaBtn.addEventListener('click', closeModal);

// Close modal on outside click
pesertaModal.addEventListener('click', function(e) {
    if (e.target === pesertaModal) {
        closeModal();
    }
});

// Event delegation for remove peserta button
pesertaContainer.addEventListener('click', function(e) {
    if (e.target.closest('.removePesertaBtn')) {
        e.preventDefault();
        const pesertaItem = e.target.closest('.peserta-item');
        pesertaItem.remove();
    }
});

// Event delegation for info peserta button
pesertaContainer.addEventListener('click', function(e) {
    if (e.target.closest('.infoPesertaBtn')) {
        e.preventDefault();
        const pesertaId = e.target.closest('.infoPesertaBtn').dataset.pesertaId;
        const pesertaItem = e.target.closest('.peserta-item');
        
        // Ambil data dari hidden inputs
        const nama = pesertaItem.querySelector(`input[name="peserta[${pesertaId}][nama]"]`)?.value || '';
        const nip = pesertaItem.querySelector(`input[name="peserta[${pesertaId}][nip]"]`)?.value || '';
        const unitKerja = pesertaItem.querySelector(`input[name="peserta[${pesertaId}][unit_kerja]"]`)?.value || '';
        const bukanPegawai = pesertaItem.querySelector(`input[name="peserta[${pesertaId}][bukan_pegawai]"]`)?.value === '1';
        const catatan = pesertaItem.querySelector(`input[name="peserta[${pesertaId}][catatan]"]`)?.value || '';
        
        // Buat modal detail
        showPesertaDetailModal(nama, nip, unitKerja, bukanPegawai, catatan);
    }
});

// Fungsi untuk menampilkan modal detail peserta
function showPesertaDetailModal(nama, nip, unitKerja, bukanPegawai, catatan) {
    // Hapus modal sebelumnya jika ada
    const existingModal = document.getElementById('detailPesertaModal');
    if (existingModal) existingModal.remove();
    
    // Buat modal baru
    const modalHTML = `
        <div id="detailPesertaModal" class="fixed inset-0 backdrop-blur-sm bg-black/40 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full max-h-[90vh] overflow-y-auto">
                <!-- Header -->
                <div class="sticky top-0 bg-white px-6 py-4 border-b border-slate-100 flex items-center justify-between rounded-t-2xl">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-linear-to-br from-blue-400 to-blue-600 flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800 text-lg">Detail Peserta</h3>
                            <p class="text-xs text-slate-400">Informasi lengkap peserta</p>
                        </div>
                    </div>
                    <button type="button" id="closeDetailModal" class="text-slate-400 hover:text-slate-600 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <!-- Body -->
                <div class="p-6 space-y-4">
                    <!-- Nama -->
                    <div class="bg-slate-50 rounded-xl p-4">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Nama Lengkap</span>
                        </div>
                        <p class="text-slate-800 font-semibold text-base ml-11">${nama}</p>
                    </div>
                    
                    <!-- Status -->
                    <div class="bg-slate-50 rounded-xl p-4">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Status</span>
                        </div>
                        <div class="ml-11">
                            ${bukanPegawai ? 
                                '<span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-amber-100 text-amber-700">Non-Pegawai</span>' : 
                                '<span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-700">Pegawai</span>'
                            }
                        </div>
                    </div>
                    
                    ${!bukanPegawai ? `
                    <!-- NIP -->
                    <div class="bg-slate-50 rounded-xl p-4">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center">
                                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">NIP</span>
                        </div>
                        <p class="text-slate-800 font-semibold text-base ml-11">${nip}</p>
                    </div>
                    
                    <!-- Unit Kerja -->
                    <div class="bg-slate-50 rounded-xl p-4">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Unit Kerja</span>
                        </div>
                        <p class="text-slate-800 font-semibold text-base ml-11">${unitKerja}</p>
                    </div>
                    ` : ''}
                    
                    ${catatan ? `
                    <!-- Catatan -->
                    <div class="bg-slate-50 rounded-xl p-4">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center">
                                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Catatan</span>
                        </div>
                        <p class="text-slate-700 text-sm leading-relaxed ml-11">${catatan}</p>
                    </div>
                    ` : ''}
                </div>
                
                <!-- Footer -->
                <div class="sticky bottom-0 bg-slate-50 px-6 py-4 border-t border-slate-100 flex justify-end rounded-b-2xl">
                    <button type="button" id="closeDetailModalBtn" 
                            class="px-6 py-2.5 bg-slate-600 text-white text-sm font-semibold rounded-xl hover:bg-slate-700 transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Tambahkan modal ke body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Event listeners untuk close modal
    const modal = document.getElementById('detailPesertaModal');
    const closeBtn = document.getElementById('closeDetailModal');
    const closeBtnFooter = document.getElementById('closeDetailModalBtn');
    
    function closeModal() {
        modal.remove();
    }
    
    closeBtn.addEventListener('click', closeModal);
    closeBtnFooter.addEventListener('click', closeModal);
    
    // Close on outside click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
    
    // Restore scroll when modal closed
    modal.addEventListener('remove', function() {
        document.body.style.overflow = '';
    });
}


document.getElementById('estimasi_biaya').addEventListener('input', function() {
    const biaya = parseInt(this.value) || 0;
    const formatted = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(biaya);
    document.getElementById('displayEstimasiBiaya').textContent = formatted;
});

// Initialize display on page load
document.getElementById('estimasi_biaya').dispatchEvent(new Event('input'));
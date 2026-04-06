
function openPesertaModal(nama, jabatan, nip, unit, gol)
{
    document.getElementById('p_nama').innerText = nama;
    document.getElementById('p_jabatan').innerText = jabatan;
    document.getElementById('p_nip').innerText = nip;
    document.getElementById('p_unit').innerText = unit;
    document.getElementById('p_gol').innerText = gol;

    document.getElementById('modalPeserta').classList.remove('hidden');
    document.getElementById('modalPeserta').classList.add('flex');
}

function closePesertaModal()
{
    document.getElementById('modalPeserta').classList.add('hidden');
}

window.openPesertaModal = openPesertaModal;
window.closePesertaModal = closePesertaModal;
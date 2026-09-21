<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Daftar koordinat kota tujuan perjalanan dinas yang lazim, supaya peta
 * langsung terisi tanpa administrator mengetik lintang-bujur satu per satu.
 *
 * Titiknya pusat kota (ketelitian kilometer), cukup untuk menempatkan
 * penanda pada peta se-Indonesia. Kota yang tidak ada di sini diisi
 * koordinatnya lewat Master Data → Lokasi Tujuan.
 */
class KoordinatKota
{
    /**
     * @var array<string, array{0: float, 1: float}> nama kota (huruf kecil) => [lintang, bujur]
     */
    private const KOTA = [
        // Sulawesi Utara — dalam kota dan sekitarnya
        'manado' => [1.4748, 124.8421],
        'tomohon' => [1.3256, 124.8390],
        'bitung' => [1.4404, 125.1217],
        'kotamobagu' => [0.7335, 124.3167],
        'tondano' => [1.3037, 124.9082],
        'minahasa' => [1.3037, 124.9082],
        'airmadidi' => [1.4194, 124.9847],
        'minahasa utara' => [1.4194, 124.9847],
        'amurang' => [1.1839, 124.5773],
        'minahasa selatan' => [1.1839, 124.5773],
        'ratahan' => [1.0165, 124.7987],
        'minahasa tenggara' => [1.0165, 124.7987],
        'lolak' => [0.8944, 124.0906],
        'bolaang mongondow' => [0.8944, 124.0906],
        'boroko' => [0.9958, 123.2634],
        'bolaang mongondow utara' => [0.9958, 123.2634],
        'tutuyan' => [0.7897, 124.6394],
        'bolaang mongondow timur' => [0.7897, 124.6394],
        'molibagu' => [0.4028, 123.9358],
        'bolaang mongondow selatan' => [0.4028, 123.9358],
        'tahuna' => [3.6083, 125.4979],
        'kepulauan sangihe' => [3.6083, 125.4979],
        'sangihe' => [3.6083, 125.4979],
        'melonguane' => [4.0116, 126.7078],
        'kepulauan talaud' => [4.0116, 126.7078],
        'talaud' => [4.0116, 126.7078],
        'ondong siau' => [2.7383, 125.3894],
        'siau' => [2.7383, 125.3894],
        'sitaro' => [2.7383, 125.3894],
        'likupang' => [1.6714, 125.0521],

        // Sulawesi dan sekitarnya (di luar Sulawesi Utara)
        'gorontalo' => [0.5435, 123.0568],
        'palu' => [-0.8917, 119.8707],
        'luwuk' => [-0.9516, 122.7875],
        'poso' => [-1.3959, 120.7524],
        'makassar' => [-5.1477, 119.4327],
        'parepare' => [-4.0135, 119.6255],
        'palopo' => [-2.9925, 120.1969],
        'watampone' => [-4.5386, 120.3279],
        'bone' => [-4.5386, 120.3279],
        'makale' => [-3.0994, 119.8506],
        'toraja' => [-3.0994, 119.8506],
        'kendari' => [-3.9985, 122.5129],
        'bau-bau' => [-5.4708, 122.6167],
        'baubau' => [-5.4708, 122.6167],
        'mamuju' => [-2.6748, 118.8885],
        'ternate' => [0.7906, 127.3841],
        'sofifi' => [0.7333, 127.5667],
        'tidore' => [0.6857, 127.4014],
        'ambon' => [-3.6954, 128.1814],
        'tual' => [-5.6300, 132.7500],

        // Jawa dan Bali
        'jakarta' => [-6.2088, 106.8456],
        'jakarta pusat' => [-6.1862, 106.8341],
        'jakarta selatan' => [-6.2615, 106.8106],
        'jakarta timur' => [-6.2250, 106.9004],
        'jakarta barat' => [-6.1683, 106.7588],
        'jakarta utara' => [-6.1385, 106.8634],
        'tangerang' => [-6.1783, 106.6319],
        'tangerang selatan' => [-6.2886, 106.7179],
        'bekasi' => [-6.2383, 106.9756],
        'depok' => [-6.4025, 106.7942],
        'bogor' => [-6.5971, 106.8060],
        'serang' => [-6.1200, 106.1503],
        'bandung' => [-6.9175, 107.6191],
        'cirebon' => [-6.7320, 108.5523],
        'semarang' => [-6.9932, 110.4203],
        'yogyakarta' => [-7.7956, 110.3695],
        'surakarta' => [-7.5755, 110.8243],
        'solo' => [-7.5755, 110.8243],
        'magelang' => [-7.4797, 110.2177],
        'surabaya' => [-7.2575, 112.7521],
        'malang' => [-7.9666, 112.6326],
        'batu' => [-7.8671, 112.5239],
        'jember' => [-8.1845, 113.6681],
        'denpasar' => [-8.6705, 115.2126],
        'kuta' => [-8.7237, 115.1750],
        'badung' => [-8.5819, 115.1771],
        'nusa dua' => [-8.8000, 115.2300],

        // Sumatera
        'banda aceh' => [5.5483, 95.3238],
        'medan' => [3.5952, 98.6722],
        'padang' => [-0.9471, 100.4172],
        'bukittinggi' => [-0.3055, 100.3692],
        'pekanbaru' => [0.5071, 101.4478],
        'batam' => [1.0456, 104.0305],
        'tanjung pinang' => [0.9186, 104.4554],
        'tanjungpinang' => [0.9186, 104.4554],
        'jambi' => [-1.6101, 103.6131],
        'palembang' => [-2.9761, 104.7754],
        'bengkulu' => [-3.7928, 102.2608],
        'bandar lampung' => [-5.4294, 105.2621],
        'lampung' => [-5.4294, 105.2621],
        'pangkal pinang' => [-2.1316, 106.1169],
        'pangkalpinang' => [-2.1316, 106.1169],

        // Kalimantan
        'pontianak' => [-0.0263, 109.3425],
        'palangka raya' => [-2.2096, 113.9108],
        'palangkaraya' => [-2.2096, 113.9108],
        'banjarmasin' => [-3.3186, 114.5944],
        'banjarbaru' => [-3.4428, 114.8309],
        'samarinda' => [-0.5022, 117.1536],
        'balikpapan' => [-1.2379, 116.8529],
        'tanjung selor' => [2.8375, 117.3653],
        'tarakan' => [3.3000, 117.6333],

        // Nusa Tenggara
        'mataram' => [-8.5833, 116.1167],
        'lombok' => [-8.5833, 116.1167],
        'kupang' => [-10.1772, 123.6070],
        'labuan bajo' => [-8.4964, 119.8877],

        // Papua
        'jayapura' => [-2.5337, 140.7181],
        'sorong' => [-0.8762, 131.2558],
        'manokwari' => [-0.8615, 134.0620],
        'nabire' => [-3.3676, 135.4960],
        'timika' => [-4.5460, 136.8830],
        'wamena' => [-4.0975, 138.9503],
        'merauke' => [-8.4932, 140.4018],
    ];

    /**
     * Kota dan kabupaten Sulawesi Utara — "dalam kota dan sekitarnya" bagi
     * Poltekkes Kemenkes Manado.
     *
     * @var list<string>
     */
    private const SEKITAR_MANADO = [
        'manado', 'tomohon', 'bitung', 'kotamobagu', 'tondano', 'minahasa', 'airmadidi', 'minahasa utara',
        'amurang', 'minahasa selatan', 'ratahan', 'minahasa tenggara', 'lolak', 'bolaang mongondow',
        'boroko', 'bolaang mongondow utara', 'tutuyan', 'bolaang mongondow timur', 'molibagu',
        'bolaang mongondow selatan', 'tahuna', 'kepulauan sangihe', 'sangihe', 'melonguane',
        'kepulauan talaud', 'talaud', 'ondong siau', 'siau', 'sitaro', 'likupang',
    ];

    /**
     * Koordinat sebuah nama kota, atau null bila tidak dikenal. Awalan
     * "Kota"/"Kabupaten" dan sisa provinsi setelah koma diabaikan.
     *
     * @return array{0: float, 1: float}|null
     */
    public function cari(?string $nama): ?array
    {
        return self::KOTA[$this->kunci($nama)] ?? null;
    }

    /**
     * Apakah kota ini berada di Sulawesi Utara — Manado dan sekitarnya.
     */
    public function sekitarManado(?string $nama): bool
    {
        return in_array($this->kunci($nama), self::SEKITAR_MANADO, true);
    }

    private function kunci(?string $nama): string
    {
        if (blank($nama)) {
            return '';
        }

        return Str::of($nama)->before(',')->lower()->squish()
            ->replaceMatches('/^(kota|kabupaten|kab\.?)\s+/', '')
            ->toString();
    }
}

@props(['usulan'])

{{-- Badge status usulan; label dan warna berasal dari App\Enums\StatusUsulan --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold '.$usulan->status_badge]) }}>
    {{ $usulan->status_text }}
</span>

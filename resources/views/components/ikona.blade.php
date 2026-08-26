{{--
    Ikona ze sady Lucide.

    Použití:
        <x-ikona name="check" />
        <x-ikona name="mail" :size="16" />
        <x-ikona name="triangle-alert" class="varovani" />

    Barvu dědí z textu (`currentColor`), takže se řídí okolním `color`.
    Neznámý název nevykreslí nic — radši prázdno než rozbité rozložení.
    Seznam dostupných: App\Support\Lucide::seznam(), přehled na lucide.dev/icons
--}}
@props(['name', 'size' => 20, 'stroke' => 2])

@php($obsah = \App\Support\Lucide::obsah($name))

@if ($obsah)
    <svg {{ $attributes->merge(['class' => 'ikona']) }}
        width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24"
        fill="none" stroke="currentColor" stroke-width="{{ $stroke }}"
        stroke-linecap="round" stroke-linejoin="round"
        aria-hidden="true" focusable="false"
        style="display:inline-block; vertical-align:-0.15em; flex-shrink:0">{!! $obsah !!}</svg>
@endif

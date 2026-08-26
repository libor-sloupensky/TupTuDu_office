{{--
    Ikona ze sady Lucide.

    Použití:
        <x-ikona name="check" />
        <x-ikona name="mail" :size="16" />
        <x-ikona name="triangle-alert" class="varovani" />

    Barvu dědí z textu (`currentColor`), takže se řídí okolním `color`.
    Neznámý název nevykreslí nic — radši prázdno než rozbité rozložení.

    Když ikonu vkládá JavaScript a komponenta se použít nedá, sáhni po
    `App\Support\Lucide::svg('check')` — je to týž výstup.

    Seznam dostupných: App\Support\Lucide::seznam(), přehled na lucide.dev/icons
--}}
@props(['name', 'size' => 20, 'stroke' => 2])

{!! \App\Support\Lucide::svg($name, (int) $size, (float) $stroke) !!}

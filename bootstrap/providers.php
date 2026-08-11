<?php

return [
    App\Providers\AppServiceProvider::class,
    // Registrujeme ručně — na produkci je bootstrap/cache/packages.php
    // z auto-discovery zacachovaný a nové balíčky by se nenačetly.
    Laravel\Socialite\SocialiteServiceProvider::class,
];

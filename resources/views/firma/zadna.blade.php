@extends('layouts.app')
@section('title', 'Nemáte firmu')

@section('content')
<div class="card" style="max-width: 550px; margin: 0 auto; text-align: center; padding: 2rem;">
    <h2 style="margin-bottom: 1rem;">Nemáte přiřazenou žádnou firmu</h2>
    <p style="color: #666; margin-bottom: 1.5rem;">
        Pro přístup do systému potřebujete být členem firmy.
        Požádejte správce firmy, aby vás pozval přes nastavení firmy.
    </p>
    <p style="color: #999; font-size: 0.85rem;">
        V případě komplikací nás kontaktujte na
        <a href="mailto:office@tuptudu.cz" style="color: #3498db;">office@tuptudu.cz</a>
    </p>
</div>
@endsection

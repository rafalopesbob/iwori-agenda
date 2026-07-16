<x-mail::message>
# Resumo do período 🌿

{!! nl2br(e($renderedBody)) !!}

<x-mail::table>
| Data | Situação | Valor |
|:-----|:---------|------:|
@foreach ($sessions as $session)
| {{ $session->scheduled_at->format('d/m/Y H:i') }} | {{ $session->status->label() }} | R$ {{ number_format($session->value, 2, ',', '.') }} |
@endforeach
</x-mail::table>

<x-mail::panel>
**Total a pagar: R$ {{ number_format($total, 2, ',', '.') }}**
</x-mail::panel>

Qualquer dúvida, fale com {{ $professionalName }}.

Até breve!<br>
{{ config('app.name') }}
</x-mail::message>

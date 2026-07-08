<x-mail::message>
# Resumo do período 🌿

Olá, {{ $client->name }}!

Aqui está o resumo das suas sessões com **{{ $professionalName }}**
no período de **{{ $start->format('d/m/Y') }}** a **{{ $end->format('d/m/Y') }}**:

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

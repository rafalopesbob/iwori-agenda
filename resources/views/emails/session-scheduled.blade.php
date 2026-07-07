<x-mail::message>
# Sessão confirmada 🌿

Olá, {{ $clientName }}!

Sua sessão com **{{ $professionalName }}** está agendada:

<x-mail::panel>
**Data:** {{ $session->scheduled_at->locale('pt_BR')->translatedFormat('l, d \d\e F \d\e Y') }}<br>
**Horário:** {{ $session->scheduled_at->format('H:i') }}<br>
**Duração:** {{ $session->duration_minutes }} minutos
</x-mail::panel>

Caso precise remarcar, entre em contato com {{ $professionalName }}.

Até lá!<br>
{{ config('app.name') }}
</x-mail::message>

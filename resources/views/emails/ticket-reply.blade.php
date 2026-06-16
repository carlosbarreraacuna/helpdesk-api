<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Re: [{{ $ticket->ticket_number }}]</title>
  <style>
    body { font-family: Arial, sans-serif; font-size: 14px; color: #222; background: #fff; margin: 0; padding: 20px; line-height: 1.6; }
    .reply-body { white-space: pre-wrap; word-break: break-word; margin-bottom: 16px; }
    .signature { color: #555; font-size: 13px; margin-top: 24px; border-top: 1px solid #e0e0e0; padding-top: 12px; }
    .signature strong { color: #222; }
    .ticket-ref { font-size: 12px; color: #888; margin-top: 4px; }
    .cta { margin: 20px 0; }
    .cta a {
      display: inline-block;
      background: #2563eb;
      color: #fff;
      text-decoration: none;
      padding: 10px 22px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 600;
    }
    .divider { border: none; border-top: 1px solid #e0e0e0; margin: 24px 0; }
    /* Quoted thread — Gmail collapses this automatically */
    .quoted { color: #555; font-size: 13px; }
    .quoted-header { color: #888; font-size: 12px; margin-bottom: 8px; }
    .quoted-item { border-left: 3px solid #dadce0; padding-left: 12px; margin-bottom: 16px; }
    .quoted-item .who { font-size: 12px; color: #888; margin-bottom: 4px; }
    .quoted-item .body { white-space: pre-wrap; word-break: break-word; color: #555; }
    .quoted-original { border-left: 3px solid #dadce0; padding-left: 12px; color: #555; font-size: 13px; white-space: pre-wrap; word-break: break-word; }
  </style>
</head>
<body>

  {{-- ── Nueva respuesta ── --}}
  <div class="reply-body">{{ $replyBody }}</div>

  <div class="cta">
    <a href="{{ config('app.frontend_url', 'http://localhost:3000') }}/portal/mis-tickets/{{ $ticket->id }}">
      Ver ticket en el portal
    </a>
  </div>

  <div class="signature">
    <strong>{{ $agentName }}</strong><br>
    Mesa de Ayuda — CARDIQUE<br>
    <span class="ticket-ref">Ticket {{ $ticket->ticket_number }} · Estado: {{ $ticket->status->name ?? 'En progreso' }}</span>
  </div>

  {{-- ── Hilo citado (Gmail lo colapsa con "...") ── --}}
  <hr class="divider">

  <div class="quoted">
    <div class="quoted-header">
      ———— Conversación anterior — {{ $ticket->ticket_number }} ————
    </div>

    {{-- Comentarios previos en orden inverso (más reciente primero, como Gmail) --}}
    @foreach($history->reverse() as $comment)
      <div class="quoted-item">
        <div class="who">
          <strong>{{ $comment->user->name ?? 'Usuario' }}</strong>
          &nbsp;·&nbsp; {{ $comment->created_at->format('d/m/Y H:i') }}
        </div>
        <div class="body">{{ $comment->comment }}</div>
      </div>
    @endforeach

    {{-- Solicitud original siempre al final del hilo --}}
    <div class="quoted-item">
      <div class="who">
        <strong>{{ $ticket->requester_name }}</strong>
        &nbsp;·&nbsp; {{ $ticket->created_at->format('d/m/Y H:i') }}
        &nbsp;·&nbsp; <em>Solicitud original</em>
      </div>
      <div class="body">{{ $ticket->description }}</div>
    </div>
  </div>

</body>
</html>

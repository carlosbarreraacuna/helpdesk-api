<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Re: [{{ $ticket->ticket_number }}]</title>
</head>
<body style="font-family:Arial,sans-serif;font-size:14px;color:#222;background:#fff;margin:0;padding:20px;line-height:1.6;">

  {{-- ── Respuesta nueva ── --}}
  <div style="white-space:pre-wrap;word-break:break-word;margin-bottom:20px;">{{ $replyBody }}</div>

  <div style="margin:20px 0;">
    <a href="{{ config('app.frontend_url', 'http://localhost:3000') }}/portal/mis-tickets/{{ $ticket->id }}"
       style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:10px 22px;border-radius:6px;font-size:13px;font-weight:600;">
      Ver ticket en el portal
    </a>
  </div>

  {{-- ── Firma ── --}}
  <div style="color:#555;font-size:13px;margin-top:24px;border-top:1px solid #e0e0e0;padding-top:12px;">
    <strong style="color:#222;">{{ $agentName }}</strong><br>
    Mesa de Ayuda — CARDIQUE<br>
    <span style="font-size:12px;color:#888;">Ticket {{ $ticket->ticket_number }} · Estado: {{ $ticket->status->name ?? 'En progreso' }}</span>
  </div>

  {{-- ── Hilo citado — Gmail lo colapsa bajo "..." ── --}}
  <br>
  <div style="color:#888;font-size:12px;">
    -------- Mensaje(s) anterior(es) --------
  </div>

  {{-- Comentarios previos en orden inverso --}}
  @foreach($history->reverse() as $comment)
  <br>
  <div style="font-size:12px;color:#666;margin-bottom:2px;">
    <b>{{ $comment->user->name ?? 'Usuario' }}</b> · {{ $comment->created_at->format('d/m/Y H:i') }}
  </div>
  <blockquote style="margin:0 0 12px 0;padding:8px 12px;border-left:4px solid #d0d0d0;color:#444;font-size:13px;white-space:pre-wrap;word-break:break-word;">{{ $comment->comment }}</blockquote>
  @endforeach

  {{-- Solicitud original siempre al final --}}
  <br>
  <div style="font-size:12px;color:#666;margin-bottom:2px;">
    <b>{{ $ticket->requester_name }}</b> · {{ $ticket->created_at->format('d/m/Y H:i') }} · <em>Solicitud original</em>
  </div>
  <blockquote style="margin:0 0 12px 0;padding:8px 12px;border-left:4px solid #d0d0d0;color:#444;font-size:13px;white-space:pre-wrap;word-break:break-word;">{{ $ticket->description }}</blockquote>

</body>
</html>

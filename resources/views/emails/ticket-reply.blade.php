<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Respuesta a ticket {{ $ticket->ticket_number }}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; background: #f3f4f6; color: #374151; font-size: 14px; line-height: 1.6; }
    .wrapper { max-width: 620px; margin: 24px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
    .header { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); padding: 28px 32px; color: #fff; }
    .header h1 { font-size: 20px; font-weight: 700; }
    .header p { font-size: 13px; opacity: .85; margin-top: 4px; }
    .badge { display: inline-block; background: rgba(255,255,255,.2); border: 1px solid rgba(255,255,255,.3); border-radius: 999px; padding: 4px 14px; font-size: 13px; font-weight: 600; margin-top: 12px; }
    .content { padding: 28px 32px; }
    .section { margin-bottom: 24px; }
    .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #6b7280; margin-bottom: 12px; }
    .reply-box { background: #eff6ff; border-left: 4px solid #2563eb; border-radius: 0 8px 8px 0; padding: 16px; font-size: 14px; white-space: pre-wrap; word-break: break-word; color: #1e3a5f; }
    .agent-meta { font-size: 12px; color: #6b7280; margin-top: 8px; }
    .info-grid { display: grid; grid-template-columns: 140px 1fr; gap: 8px 12px; }
    .info-label { color: #9ca3af; font-size: 13px; }
    .info-value { font-size: 13px; font-weight: 600; color: #1f2937; }
    .status-pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #dbeafe; color: #1d4ed8; }
    .priority-alta { color: #dc2626; }
    .priority-media { color: #d97706; }
    .priority-baja { color: #16a34a; }
    /* Timeline */
    .timeline { border-left: 2px solid #e5e7eb; margin-left: 8px; padding-left: 16px; }
    .timeline-item { margin-bottom: 16px; position: relative; }
    .timeline-item::before { content: ''; position: absolute; left: -21px; top: 6px; width: 8px; height: 8px; border-radius: 50%; background: #d1d5db; border: 2px solid #fff; }
    .timeline-item.agent::before { background: #2563eb; }
    .timeline-item.requester::before { background: #10b981; }
    .timeline-bubble { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 14px; font-size: 13px; white-space: pre-wrap; word-break: break-word; color: #374151; }
    .timeline-item.agent .timeline-bubble { background: #eff6ff; border-color: #bfdbfe; }
    .timeline-meta { font-size: 11px; color: #9ca3af; margin-top: 4px; }
    /* Original */
    .original-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px 16px; font-size: 13px; white-space: pre-wrap; word-break: break-word; color: #6b7280; }
    .cta { text-align: center; margin-top: 24px; }
    .cta a { display: inline-block; background: #2563eb; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 700; font-size: 14px; }
    .divider { border: none; border-top: 1px solid #f3f4f6; margin: 20px 0; }
    .footer { padding: 16px 32px 24px; text-align: center; font-size: 11px; color: #9ca3af; }
  </style>
</head>
<body>
  <div class="wrapper">

    <div class="header">
      <h1>💬 Respuesta a tu ticket</h1>
      <p>Un agente ha respondido a tu solicitud.</p>
      <div class="badge">{{ $ticket->ticket_number }}</div>
    </div>

    <div class="content">

      <!-- Respuesta del agente -->
      <div class="section">
        <div class="section-title">Respuesta del agente</div>
        <div class="reply-box">{{ $replyBody }}</div>
        <div class="agent-meta">— {{ $agentName }} &nbsp;·&nbsp; {{ now()->format('d/m/Y H:i') }}</div>
      </div>

      <hr class="divider">

      <!-- Info del ticket -->
      <div class="section">
        <div class="section-title">Información del ticket</div>
        <div class="info-grid">
          <span class="info-label">Número:</span>
          <span class="info-value">{{ $ticket->ticket_number }}</span>

          <span class="info-label">Estado:</span>
          <span class="info-value">
            <span class="status-pill">{{ $ticket->status->name ?? 'En progreso' }}</span>
          </span>

          <span class="info-label">Prioridad:</span>
          <span class="info-value priority-{{ $ticket->priority }}">
            {{ ucfirst($ticket->priority) }}
          </span>

          @if($ticket->category)
          <span class="info-label">Categoría:</span>
          <span class="info-value">{{ $ticket->category->name }}</span>
          @endif

          <span class="info-label">Fecha apertura:</span>
          <span class="info-value">{{ $ticket->created_at->format('d/m/Y H:i') }}</span>
        </div>
      </div>

      <hr class="divider">

      <!-- Descripción original -->
      <div class="section">
        <div class="section-title">Solicitud original</div>
        <div class="original-box">{{ $ticket->description }}</div>
      </div>

      @if($history->isNotEmpty())
      <hr class="divider">

      <!-- Historial de comentarios públicos -->
      <div class="section">
        <div class="section-title">Historial de conversación ({{ $history->count() }} {{ $history->count() === 1 ? 'mensaje' : 'mensajes' }})</div>
        <div class="timeline">
          @foreach($history as $comment)
            @php
              $isAgent = $comment->user && in_array($comment->user->role?->name ?? '', ['agente', 'supervisor', 'admin']);
            @endphp
            <div class="timeline-item {{ $isAgent ? 'agent' : 'requester' }}">
              <div class="timeline-bubble">{{ $comment->comment }}</div>
              <div class="timeline-meta">
                {{ $comment->user->name ?? 'Usuario' }}
                &nbsp;·&nbsp;
                {{ $comment->created_at->format('d/m/Y H:i') }}
              </div>
            </div>
          @endforeach
        </div>
      </div>
      @endif

      <!-- CTA -->
      <div class="cta">
        <a href="{{ config('app.frontend_url', 'http://localhost:3000') }}/portal/mis-tickets/{{ $ticket->id }}">
          Ver seguimiento de mi ticket
        </a>
      </div>

    </div>

    <div class="footer">
      Este mensaje fue generado automáticamente por el Sistema de Mesa de Ayuda de CARDIQUE.<br>
      Para responder, ingresa al portal o contacta directamente a tu agente.
    </div>
  </div>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ticket {{ $ticket->ticket_number }}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; background: #f3f4f6; color: #374151; font-size: 14px; line-height: 1.6; }
    .wrapper { max-width: 600px; margin: 24px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
    .header { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); padding: 28px 32px; color: #fff; }
    .header h1 { font-size: 20px; font-weight: 700; }
    .header p { font-size: 13px; opacity: .85; margin-top: 4px; }
    .badge { display: inline-block; background: rgba(255,255,255,.2); border: 1px solid rgba(255,255,255,.3); border-radius: 999px; padding: 4px 14px; font-size: 13px; font-weight: 600; margin-top: 12px; }
    .content { padding: 28px 32px; }
    .section { margin-bottom: 24px; }
    .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #6b7280; margin-bottom: 12px; }
    .info-grid { display: grid; grid-template-columns: 140px 1fr; gap: 8px 12px; }
    .info-label { color: #9ca3af; font-size: 13px; }
    .info-value { font-size: 13px; font-weight: 600; color: #1f2937; }
    .transcript-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; font-size: 12px; white-space: pre-wrap; word-break: break-word; color: #4b5563; max-height: 300px; overflow: hidden; }
    .article-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 14px 16px; }
    .article-box .article-title { font-weight: 700; color: #1d4ed8; font-size: 14px; }
    .article-box .article-meta { font-size: 12px; color: #6b7280; margin-top: 4px; }
    .cta { text-align: center; margin-top: 24px; }
    .cta a { display: inline-block; background: #2563eb; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 700; font-size: 14px; }
    .cta a:hover { background: #1d4ed8; }
    .divider { border: none; border-top: 1px solid #f3f4f6; margin: 20px 0; }
    .footer { padding: 16px 32px 24px; text-align: center; font-size: 11px; color: #9ca3af; }
    .priority-alta { color: #dc2626; }
    .priority-media { color: #d97706; }
    .priority-baja { color: #16a34a; }
    .status-pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #dbeafe; color: #1d4ed8; }
  </style>
</head>
<body>
  <div class="wrapper">
    <!-- Header -->
    <div class="header">
      @if($isAgentNotification)
        <h1>🎫 Nuevo ticket asignado</h1>
        <p>Se te ha asignado un ticket que requiere tu atención.</p>
      @else
        <h1>✅ Ticket creado exitosamente</h1>
        <p>Hemos recibido tu solicitud y será atendida a la brevedad.</p>
      @endif
      <div class="badge">{{ $ticket->ticket_number }}</div>
    </div>

    <div class="content">

      <!-- Ticket info -->
      <div class="section">
        <div class="section-title">Información del ticket</div>
        <div class="info-grid">
          <span class="info-label">Número:</span>
          <span class="info-value">{{ $ticket->ticket_number }}</span>

          <span class="info-label">Estado:</span>
          <span class="info-value">
            <span class="status-pill">{{ $ticket->status->name ?? 'Nuevo' }}</span>
          </span>

          <span class="info-label">Prioridad:</span>
          <span class="info-value priority-{{ $ticket->priority }}">
            {{ ucfirst($ticket->priority) }}
          </span>

          @if($ticket->category)
          <span class="info-label">Categoría:</span>
          <span class="info-value">{{ $ticket->category->name }}</span>
          @endif

          <span class="info-label">Fecha:</span>
          <span class="info-value">{{ $ticket->created_at->format('d/m/Y H:i') }}</span>
        </div>
      </div>

      <hr class="divider">

      <!-- Requester -->
      <div class="section">
        <div class="section-title">Solicitante</div>
        <div class="info-grid">
          <span class="info-label">Nombre:</span>
          <span class="info-value">{{ $ticket->requester_name }}</span>

          <span class="info-label">Email:</span>
          <span class="info-value">{{ $ticket->requester_email }}</span>

          <span class="info-label">Área:</span>
          <span class="info-value">{{ $ticket->requester_area }}</span>
        </div>
      </div>

      <hr class="divider">

      <!-- Description -->
      <div class="section">
        <div class="section-title">Descripción de la solicitud</div>
        <div class="transcript-box" style="max-height: none;">{{ $ticket->description }}</div>
      </div>

      @if($botKbArticleTitle)
      <hr class="divider">
      <!-- KB article shown -->
      <div class="section">
        <div class="section-title">Artículo sugerido por el asistente virtual</div>
        <div class="article-box">
          <div class="article-title">📖 {{ $botKbArticleTitle }}</div>
          <div class="article-meta">
            ¿Fue útil para el usuario?
            @if($botKbHelpful === true) <strong style="color:#16a34a">Sí</strong>
            @elseif($botKbHelpful === false) <strong style="color:#dc2626">No</strong>
            @else <span>No indicado</span>
            @endif
          </div>
        </div>
      </div>
      @endif

      @if($botContext)
      <hr class="divider">
      <!-- Chatbot transcript -->
      <div class="section">
        <div class="section-title">Transcripción del chat con el asistente virtual</div>
        <div class="transcript-box">{{ $botContext }}</div>
      </div>
      @endif

      <!-- CTA -->
      <div class="cta">
        @if($isAgentNotification)
          <a href="{{ config('app.frontend_url', 'http://localhost:3000') }}/tickets/{{ $ticket->id }}">
            Ver ticket en el sistema
          </a>
        @else
          <a href="{{ config('app.frontend_url', 'http://localhost:3000') }}/portal/mis-tickets/{{ $ticket->id }}">
            Ver seguimiento de mi ticket
          </a>
        @endif
      </div>

    </div>

    <div class="footer">
      Este mensaje fue generado automáticamente por el Sistema de Mesa de Ayuda.<br>
      Por favor no responder a este correo.
    </div>
  </div>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Resultado de validación {{ $ticket->ticket_number }}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; background: #f3f4f6; color: #374151; font-size: 14px; line-height: 1.6; }
    .wrapper { max-width: 600px; margin: 24px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
    .header-approved { background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); padding: 28px 32px; color: #fff; }
    .header-rejected  { background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); padding: 28px 32px; color: #fff; }
    .header-approved h1, .header-rejected h1 { font-size: 20px; font-weight: 700; }
    .header-approved p, .header-rejected p  { font-size: 13px; opacity: .9; margin-top: 4px; }
    .badge { display: inline-block; background: rgba(255,255,255,.25); border: 1px solid rgba(255,255,255,.4); border-radius: 999px; padding: 4px 14px; font-size: 13px; font-weight: 600; margin-top: 12px; }
    .content { padding: 28px 32px; }
    .section { margin-bottom: 20px; }
    .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #6b7280; margin-bottom: 10px; }
    .info-grid { display: grid; grid-template-columns: 140px 1fr; gap: 8px 12px; }
    .info-label { color: #9ca3af; font-size: 13px; }
    .info-value { font-size: 13px; font-weight: 600; color: #1f2937; }
    .comment-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px 16px; font-size: 13px; color: #4b5563; white-space: pre-wrap; }
    .cta { text-align: center; margin-top: 24px; }
    .cta a { display: inline-block; background: #2563eb; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 700; font-size: 14px; }
    .divider { border: none; border-top: 1px solid #f3f4f6; margin: 20px 0; }
    .footer { padding: 16px 32px 24px; text-align: center; font-size: 11px; color: #9ca3af; }
  </style>
</head>
<body>
  <div class="wrapper">
    @if($action === 'approved')
    <div class="header-approved">
      <h1>✅ Ticket aprobado por el usuario</h1>
      <p>{{ $requesterName }} confirmó que la solución fue satisfactoria. Ya puedes cerrar el ticket.</p>
      <div class="badge">{{ $ticket->ticket_number }}</div>
    </div>
    @else
    <div class="header-rejected">
      <h1>❌ El usuario reporta que aún hay problemas</h1>
      <p>{{ $requesterName }} rechazó la validación. El ticket volvió a "En progreso".</p>
      <div class="badge">{{ $ticket->ticket_number }}</div>
    </div>
    @endif

    <div class="content">

      <div class="section">
        <div class="section-title">Información</div>
        <div class="info-grid">
          <span class="info-label">Ticket:</span>
          <span class="info-value">{{ $ticket->ticket_number }}</span>

          <span class="info-label">Solicitante:</span>
          <span class="info-value">{{ $requesterName }}</span>

          <span class="info-label">Resultado:</span>
          <span class="info-value">{{ $action === 'approved' ? 'Aprobado ✅' : 'Rechazado ❌' }}</span>
        </div>
      </div>

      @if($comment)
      <hr class="divider">
      <div class="section">
        <div class="section-title">Comentario del usuario</div>
        <div class="comment-box">{{ $comment }}</div>
      </div>
      @endif

      <div class="cta">
        <a href="{{ config('app.frontend_url', 'http://localhost:3000') }}/tickets/{{ $ticket->id }}">
          Ver ticket en el sistema
        </a>
      </div>

    </div>

    <div class="footer">
      Este mensaje fue generado automáticamente por el Sistema de Mesa de Ayuda.<br>
      Por favor no responder a este correo.
    </div>
  </div>
</body>
</html>

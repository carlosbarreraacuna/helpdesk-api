<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Valida tu ticket {{ $ticket->ticket_number }}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; background: #f3f4f6; color: #374151; font-size: 14px; line-height: 1.6; }
    .wrapper { max-width: 600px; margin: 24px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
    .header { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 28px 32px; color: #fff; }
    .header h1 { font-size: 20px; font-weight: 700; }
    .header p { font-size: 13px; opacity: .9; margin-top: 4px; }
    .badge { display: inline-block; background: rgba(255,255,255,.25); border: 1px solid rgba(255,255,255,.4); border-radius: 999px; padding: 4px 14px; font-size: 13px; font-weight: 600; margin-top: 12px; }
    .content { padding: 28px 32px; }
    .section { margin-bottom: 20px; }
    .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #6b7280; margin-bottom: 10px; }
    .info-grid { display: grid; grid-template-columns: 140px 1fr; gap: 8px 12px; }
    .info-label { color: #9ca3af; font-size: 13px; }
    .info-value { font-size: 13px; font-weight: 600; color: #1f2937; }
    .description-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px 16px; font-size: 13px; color: #4b5563; white-space: pre-wrap; }
    .alert-box { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; padding: 14px 16px; font-size: 13px; color: #92400e; margin-bottom: 20px; }
    .alert-box strong { display: block; margin-bottom: 4px; }
    .cta-group { display: flex; gap: 12px; margin-top: 24px; }
    .btn { display: inline-block; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; font-size: 14px; text-align: center; }
    .btn-approve { background: #16a34a; color: #fff; }
    .btn-reject  { background: #dc2626; color: #fff; }
    .divider { border: none; border-top: 1px solid #f3f4f6; margin: 20px 0; }
    .footer { padding: 16px 32px 24px; text-align: center; font-size: 11px; color: #9ca3af; }
    .deadline-note { font-size: 12px; color: #6b7280; margin-top: 16px; text-align: center; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <h1>¿Tu solicitud fue resuelta?</h1>
      <p>El equipo de soporte marcó tu ticket como resuelto. Por favor confirma si todo está correcto.</p>
      <div class="badge">{{ $ticket->ticket_number }}</div>
    </div>

    <div class="content">

      <div class="alert-box">
        <strong>Se requiere tu confirmación</strong>
        Antes de cerrar el ticket necesitamos que nos confirmes si la solución fue satisfactoria.
        Si no respondes antes de la fecha límite, el ticket se cerrará automáticamente.
      </div>

      <div class="section">
        <div class="section-title">Detalle del ticket</div>
        <div class="info-grid">
          <span class="info-label">Número:</span>
          <span class="info-value">{{ $ticket->ticket_number }}</span>

          <span class="info-label">Solicitante:</span>
          <span class="info-value">{{ $ticket->requester_name }}</span>

          @if($ticket->validation_deadline)
          <span class="info-label">Fecha límite:</span>
          <span class="info-value">{{ $ticket->validation_deadline->format('d/m/Y H:i') }}</span>
          @endif
        </div>
      </div>

      <hr class="divider">

      <div class="section">
        <div class="section-title">Tu solicitud original</div>
        <div class="description-box">{{ $ticket->description }}</div>
      </div>

      <hr class="divider">

      <p style="font-size:14px; color:#374151;">
        Haz clic en uno de los botones para indicarnos si la solución fue satisfactoria:
      </p>

      <div class="cta-group">
        <a href="{{ $validationUrl }}?action=approved" class="btn btn-approve">
          ✅ Sí, todo está correcto
        </a>
        <a href="{{ $validationUrl }}?action=rejected" class="btn btn-reject">
          ❌ Aún hay problemas
        </a>
      </div>

      <p class="deadline-note">
        También puedes validar iniciando sesión en el portal y abriendo este ticket.
      </p>

    </div>

    <div class="footer">
      Este mensaje fue generado automáticamente por el Sistema de Mesa de Ayuda.<br>
      Por favor no responder a este correo.
    </div>
  </div>
</body>
</html>

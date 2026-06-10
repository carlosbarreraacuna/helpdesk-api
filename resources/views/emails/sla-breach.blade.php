<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SLA vencido: {{ $ticket->ticket_number }}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; background: #f3f4f6; color: #374151; font-size: 14px; line-height: 1.6; }
    .wrapper { max-width: 600px; margin: 24px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
    .header { background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); padding: 28px 32px; color: #fff; }
    .header h1 { font-size: 20px; font-weight: 700; }
    .header p { font-size: 13px; opacity: .9; margin-top: 4px; }
    .badge { display: inline-block; background: rgba(255,255,255,.25); border: 1px solid rgba(255,255,255,.4); border-radius: 999px; padding: 4px 14px; font-size: 13px; font-weight: 600; margin-top: 12px; }
    .content { padding: 28px 32px; }
    .alert-box { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; padding: 14px 16px; font-size: 13px; color: #991b1b; margin-bottom: 20px; }
    .alert-box strong { display: block; margin-bottom: 4px; font-size: 14px; }
    .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #6b7280; margin-bottom: 10px; }
    .info-grid { display: grid; grid-template-columns: 160px 1fr; gap: 8px 12px; margin-bottom: 20px; }
    .info-label { color: #9ca3af; font-size: 13px; }
    .info-value { font-size: 13px; font-weight: 600; color: #1f2937; }
    .priority-alta { color: #dc2626; }
    .priority-media { color: #d97706; }
    .priority-baja { color: #2563eb; }
    .divider { border: none; border-top: 1px solid #f3f4f6; margin: 20px 0; }
    .btn { display: inline-block; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 700; font-size: 14px; background: #dc2626; color: #fff; margin-top: 8px; }
    .footer { padding: 16px 32px 24px; text-align: center; font-size: 11px; color: #9ca3af; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <h1>⚠️ SLA vencido — Acción requerida</h1>
      <p>Hola {{ $recipientName }}, el siguiente ticket ha superado su tiempo de resolución.</p>
      <div class="badge">{{ $ticket->ticket_number }}</div>
    </div>

    <div class="content">

      <div class="alert-box">
        <strong>El tiempo de resolución ha vencido</strong>
        Este ticket requiere atención inmediata. Cada minuto de retraso adicional afecta el indicador de cumplimiento de SLA.
      </div>

      <div class="section-title">Detalle del ticket</div>
      <div class="info-grid">
        <span class="info-label">Número:</span>
        <span class="info-value">{{ $ticket->ticket_number }}</span>

        <span class="info-label">Solicitante:</span>
        <span class="info-value">{{ $ticket->requester_name }}</span>

        <span class="info-label">Prioridad:</span>
        <span class="info-value priority-{{ $ticket->priority }}">{{ strtoupper($ticket->priority) }}</span>

        @if($ticket->assignedAgent)
        <span class="info-label">Agente asignado:</span>
        <span class="info-value">{{ $ticket->assignedAgent->name }}</span>
        @endif

        @if($ticket->sla_resolution_due_at)
        <span class="info-label">Venció el:</span>
        <span class="info-value" style="color:#dc2626;">
          {{ $ticket->sla_resolution_due_at->timezone('America/Bogota')->format('d/m/Y H:i') }} (hora Colombia)
        </span>
        @endif

        @if($ticket->created_at)
        <span class="info-label">Creado el:</span>
        <span class="info-value">{{ $ticket->created_at->timezone('America/Bogota')->format('d/m/Y H:i') }}</span>
        @endif
      </div>

      <hr class="divider">

      <div class="section-title">Descripción del problema</div>
      <p style="font-size:13px; color:#4b5563; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:14px 16px; margin-bottom:20px; white-space:pre-wrap;">{{ Str::limit($ticket->description, 300) }}</p>

      <a href="{{ config('app.frontend_url') }}/tickets/{{ $ticket->id }}" class="btn">
        Ver ticket en el sistema →
      </a>

    </div>

    <div class="footer">
      Este mensaje fue generado automáticamente por el Sistema de Mesa de Ayuda.<br>
      Por favor no responder a este correo.
    </div>
  </div>
</body>
</html>

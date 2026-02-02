<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Usuario Solicita Contacto con Asesor</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #2563eb;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: #f9fafb;
            padding: 30px;
            border-radius: 0 0 8px 8px;
            border: 1px solid #e5e7eb;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 10px;
            margin: 20px 0;
        }
        .label {
            font-weight: bold;
            color: #6b7280;
        }
        .alert {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #6b7280;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔔 Usuario Solicita Contacto con Asesor</h1>
    </div>
    
    <div class="content">
        <p>Un usuario ha solicitado contacto con un asesor a través del chatbot de WhatsApp y necesita atención prioritaria.</p>
        
        <div class="alert">
            <strong>⚠️ Acción Requerida:</strong> Por favor contactar al usuario a la brevedad posible.
        </div>
        
        <h3>📋 Información del Usuario</h3>
        <div class="info-grid">
            <div class="label">Nombre:</div>
            <div>{{ $user->name }}</div>
            
            <div class="label">Email:</div>
            <div>{{ $user->email }}</div>
            
            <div class="label">Cédula:</div>
            <div>{{ $user->cedula }}</div>
            
            <div class="label">WhatsApp:</div>
            <div>{{ $conversation->phone_number }}</div>
            
            <div class="label">Teléfono:</div>
            <div>{{ $user->phone ?? 'No registrado' }}</div>
            
            <div class="label">Rol:</div>
            <div>{{ $user->role->name ?? 'Sin rol' }}</div>
            
            <div class="label">Área:</div>
            <div>{{ $user->area->name ?? 'Sin área' }}</div>
        </div>
        
        <h3>⏰ Detalles de la Solicitud</h3>
        <div class="info-grid">
            <div class="label">Fecha/Hora:</div>
            <div>{{ $conversation->last_interaction_at->format('d/m/Y H:i:s') }}</div>
            
            <div class="label">Estado:</div>
            <div>Esperando contacto con asesor</div>
            
            <div class="label">WhatsApp ID:</div>
            <div>{{ $conversation->wa_id }}</div>
        </div>
        
        <div class="alert">
            <strong>📱 Instrucciones:</strong><br>
            1. Contactar al usuario por WhatsApp al número {{ $conversation->phone_number }}<br>
            2. Identificarse como asesor del sistema de helpdesk<br>
            3. Brindar asistencia según la necesidad del usuario<br>
            4. Registrar el seguimiento en el sistema
        </div>
        
        <div class="footer">
            <p>Este mensaje fue generado automáticamente por el Sistema de Mesa de Ayuda</p>
            <p>Si no eres el responsable de esta solicitud, por favor reenviar al área correspondiente.</p>
        </div>
    </div>
</body>
</html>

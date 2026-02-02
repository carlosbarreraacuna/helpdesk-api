<?php

namespace App\Services;

use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketStatus;
use Illuminate\Support\Facades\Log;

class WhatsAppBotService
{
    protected $whatsapp;

    public function __construct(WhatsAppService $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    /**
     * Procesar mensaje recibido
     */
    public function processMessage($phone, $message, $waId)
    {
        // Obtener o crear conversación
        $conversation = WhatsAppConversation::firstOrCreate(
            ['phone_number' => $phone],
            [
                'wa_id' => $waId,
                'state' => 'INICIO',
                'last_interaction_at' => now(),
            ]
        );

        // Guardar mensaje entrante
        WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'message_id' => uniqid(),
            'direction' => 'incoming',
            'message_body' => $message,
            'sent_at' => now(),
        ]);

        // Verificar opciones globales primero
        if ($this->handleOpcionesGlobales($conversation, $message)) {
            return; // Si se manejó una opción global, no continuar con el flujo normal
        }

        // Procesar según el estado
        Log::info('Procesando mensaje', [
            'conversation_state' => $conversation->state,
            'message_body' => $message,
            'phone' => $phone
        ]);

        switch ($conversation->state) {
            case 'INICIO':
                Log::info('Ejecutando handleCedula');
                $this->handleCedula($conversation, $message);
                break;

            case 'ESPERANDO_TELEFONO':
                Log::info('Ejecutando handleTelefonoAuthentication');
                $this->handleTelefonoAuthentication($conversation, $message);
                break;

            case 'AUTENTICANDO':
                // Estado de transición
                break;

            case 'MENU_PRINCIPAL':
                Log::info('Ejecutando handleMenuPrincipal');
                $this->handleMenuPrincipal($conversation, $message);
                break;

            case 'CREANDO_TICKET':
                Log::info('Ejecutando handleCreandoTicket');
                $this->handleCreandoTicket($conversation, $message);
                break;

            case 'ESPERANDO_DESCRIPCION':
                Log::info('Ejecutando handleDescripcionTicket');
                $this->handleDescripcionTicket($conversation, $message);
                break;

            case 'SELECCIONANDO_PRIORIDAD':
                Log::info('Ejecutando handleSeleccionPrioridad');
                $this->handleSeleccionPrioridad($conversation, $message);
                break;

            case 'CONSULTANDO_TICKET':
                Log::info('Ejecutando handleConsultandoTicket');
                $this->handleConsultandoTicket($conversation, $message);
                break;

            case 'DETALLES_TICKET':
                Log::info('Ejecutando handleDetallesTicket');
                $this->handleDetallesTicket($conversation, $message);
                break;

            case 'CONTACTANDO_ASESOR':
                Log::info('Ejecutando handleContactandoAsesor');
                $this->handleContactandoAsesor($conversation, $message);
                break;

            default:
                Log::info('Estado no reconocido, reiniciando conversación');
                $this->resetConversation($conversation);
        }
    }

    /**
     * INICIO: Solicitar cédula
     */
    protected function handleInicio($conversation, $message)
    {
        $response = "👋 ¡Bienvenido al Sistema de Mesa de Ayuda!\n\n";
        $response .= "Para comenzar, por favor ingresa tu número de *cédula*:";

        $this->sendMessage($conversation, $response);
        // No cambiar de estado aquí, esperar a recibir la cédula
    }

    /**
     * Procesar cédula en estado INICIO
     */
    protected function handleCedula($conversation, $message)
    {
        // Si es el primer mensaje (saludo), mostrar bienvenida y pedir cédula
        if (in_array(strtolower(trim($message)), ['hola', 'hi', 'buenos días', 'buenas', 'buenas tardes', 'buenas noches'])) {
            $response = "👋 ¡Bienvenido al Sistema de Mesa de Ayuda!\n\n";
            $response .= "Para comenzar, por favor ingresa tu número de *cédula*:\n\n";
            $response .= "💡 *Opciones disponibles:*\n";
            $response .= "• Escribe \"salir\" para terminar la conversación";
            
            $this->sendMessage($conversation, $response);
            return;
        }

        // Validar cédula
        $cedula = trim($message);
        
        if (!$this->validarCedula($cedula)) {
            $response = "❌ Cédula inválida. Por favor ingresa un número de cédula válido:\n\n";
            $response .= "💡 *Opciones disponibles:*\n";
            $response .= "• Escribe tu cédula para continuar\n";
            $response .= "• Escribe \"salir\" para terminar la conversación";
            
            $this->sendMessage($conversation, $response);
            return;
        }

        // Guardar cédula en contexto y cambiar a estado ESPERANDO_TELEFONO
        $conversation->updateState('ESPERANDO_TELEFONO', ['cedula' => $cedula]);

        $response = "✅ Cédula registrada.\n\n";
        $response .= "Ahora, por favor ingresa tu número de *teléfono* (10 dígitos):\n\n";
        $response .= "💡 *Opciones disponibles:*\n";
        $response .= "• Escribe tu número de teléfono\n";
        $response .= "• Escribe \"salir\" para terminar la conversación";

        $this->sendMessage($conversation, $response);
    }

    /**
     * Autenticar usuario con teléfono
     */
    protected function handleTelefonoAuthentication($conversation, $message)
    {
        $telefono = trim($message);
        
        // Validar teléfono
        if (!$this->validarTelefono($telefono)) {
            $response = "❌ Teléfono inválido. Por favor ingresa un número de 10 dígitos:\n\n";
            $response .= "💡 *Opciones disponibles:*\n";
            $response .= "• Escribe tu número de teléfono (10 dígitos)\n";
            $response .= "• Escribe \"salir\" para terminar la conversación";
            
            $this->sendMessage($conversation, $response);
            return;
        }

        $cedula = $conversation->getContextValue('cedula');

        // Limpiar teléfono
        $telefono = preg_replace('/[^0-9]/', '', $telefono);

        Log::info('Buscando usuario', [
            'cedula' => $cedula,
            'telefono' => $telefono,
            'conversation_id' => $conversation->id
        ]);

        // Buscar usuario
        $user = User::where('cedula', $cedula)
                    ->where(function($q) use ($telefono) {
                        $q->where('phone', $telefono)
                          ->orWhere('whatsapp_phone', $telefono);
                    })
                    ->first();

        Log::info('Resultado búsqueda usuario', [
            'user_found' => $user ? true : false,
            'user_id' => $user ? $user->id : null,
            'user_name' => $user ? $user->name : null
        ]);

        if (!$user) {
            $response = "❌ No encontramos un usuario con esos datos.\n\n";
            $response .= "¿Deseas intentar nuevamente?\n";
            $response .= "1️⃣ Sí, intentar de nuevo\n";
            $response .= "2️⃣ Contactar a un asesor\n\n";
            $response .= "💡 *Otras opciones:*\n";
            $response .= "• Escribe \"salir\" para terminar la conversación";

            $this->sendMessage($conversation, $response);
            $conversation->updateState('INICIO');
            return false;
        }

        // Usuario encontrado
        $conversation->update([
            'user_id' => $user->id,
            'is_authenticated' => true,
        ]);

        // Actualizar WhatsApp del usuario si no lo tiene
        if (!$user->whatsapp_phone) {
            $user->update(['whatsapp_phone' => $conversation->phone_number]);
        }

        $this->mostrarMenuPrincipal($conversation);
        return true;
    }

    /**
     * Mostrar menú principal
     */
    protected function mostrarMenuPrincipal($conversation)
    {
        $user = $conversation->user;

        $response = "🏠 *Menú Principal*\n\n";
        $response .= "Hola *{$user->name}*, ¿en qué puedo ayudarte?\n\n";
        $response .= "Selecciona una opción:\n";
        $response .= "1️⃣ Crear nuevo ticket\n";
        $response .= "2️⃣ Consultar estado de ticket\n";
        $response .= "3️⃣ Contactar con un asesor\n";
        $response .= "0️⃣ Salir\n\n";
        $response .= "💡 *En cualquier momento puedes:*\n";
        $response .= "• Escribe \"menu\" para volver a este menú\n";
        $response .= "• Escribe \"salir\" para terminar la conversación";

        $this->sendMessage($conversation, $response);
        $conversation->updateState('MENU_PRINCIPAL');
    }

    /**
     * Manejar menú principal
     */
    protected function handleMenuPrincipal($conversation, $message)
    {
        $option = trim($message);

        switch ($option) {
            case '1':
            case 'crear':
            case 'nuevo ticket':
                $this->iniciarCreacionTicket($conversation);
                break;

            case '2':
            case 'consultar':
            case 'estado':
                $this->iniciarConsultaTicket($conversation);
                break;

            case '3':
            case 'asesor':
            case 'contactar':
                $this->iniciarContactoAsesor($conversation);
                break;

            case '0':
            case 'salir':
                $this->cerrarSesion($conversation);
                break;

            default:
                $this->sendMessage($conversation, "❌ Opción no válida. Por favor selecciona una opción del menú (1, 2, 3 o 0).");
                $this->mostrarMenuPrincipal($conversation);
        }
    }

    /**
     * Iniciar creación de ticket
     */
    protected function iniciarCreacionTicket($conversation)
    {
        $response = "📝 *Crear Nuevo Ticket*\n\n";
        $response .= "Por favor describe tu problema o solicitud:\n";
        $response .= "(Puedes ser lo más detallado posible)\n\n";
        $response .= "💡 *Opciones disponibles:*\n";
        $response .= "• Escribe la descripción de tu problema\n";
        $response .= "• Escribe \"menu\" para volver al menú principal\n";
        $response .= "• Escribe \"salir\" para terminar la conversación";

        $this->sendMessage($conversation, $response);
        $conversation->updateState('ESPERANDO_DESCRIPCION');
    }

    /**
     * Procesar descripción y pedir prioridad
     */
    protected function handleDescripcionTicket($conversation, $message)
    {
        $descripcion = trim($message);

        if (strlen($descripcion) < 10) {
            $response = "❌ La descripción es muy corta. Por favor describe tu problema con más detalle:\n\n";
            $response .= "💡 *Opciones disponibles:*\n";
            $response .= "• Escribe una descripción más detallada (mínimo 10 caracteres)\n";
            $response .= "• Escribe \"menu\" para volver al menú principal\n";
            $response .= "• Escribe \"salir\" para terminar la conversación";
            
            $this->sendMessage($conversation, $response);
            return;
        }

        // Guardar descripción en contexto y pedir prioridad
        $conversation->updateState('SELECCIONANDO_PRIORIDAD', ['descripcion' => $descripcion]);

        $response = "✅ Descripción recibida.\n\n";
        $response .= "Ahora selecciona la prioridad de tu ticket:\n\n";
        $response .= "1️⃣ Baja - No urgente\n";
        $response .= "2️⃣ Media - Requiere atención pronto\n";
        $response .= "3️⃣ Alta - Urgente\n\n";
        $response .= "💡 *Opciones disponibles:*\n";
        $response .= "• Selecciona 1, 2 o 3 para la prioridad\n";
        $response .= "• Escribe \"menu\" para volver al menú principal\n";
        $response .= "• Escribe \"salir\" para terminar la conversación";

        $this->sendMessage($conversation, $response);
    }

    /**
     * Procesar selección de prioridad y crear ticket
     */
    protected function handleSeleccionPrioridad($conversation, $message)
    {
        $user = $conversation->user;
        $descripcion = $conversation->getContextValue('descripcion');
        $opcion = trim($message);

        // Mapear opción a prioridad
        $prioridades = [
            '1' => 'baja',
            '2' => 'media', 
            '3' => 'alta',
            'baja' => 'baja',
            'media' => 'media',
            'alta' => 'alta'
        ];

        if (!isset($prioridades[$opcion])) {
            $response = "❌ Opción no válida. Por favor selecciona:\n\n";
            $response .= "1️⃣ Baja - No urgente\n";
            $response .= "2️⃣ Media - Requiere atención pronto\n";
            $response .= "3️⃣ Alta - Urgente\n\n";
            $response .= "💡 *Otras opciones:*\n";
            $response .= "• Escribe \"menu\" para volver al menú principal\n";
            $response .= "• Escribe \"salir\" para terminar la conversación";
            
            $this->sendMessage($conversation, $response);
            return;
        }

        $priority = $prioridades[$opcion];

        // Crear ticket
        $ticketNumber = 'TKT-' . date('Y') . '-' . str_pad(Ticket::count() + 1, 4, '0', STR_PAD_LEFT);
        $newStatus = TicketStatus::where('name', 'nuevo')->first();
        $verificationCode = rand(100000, 999999);

        // Obtener área del usuario
        $area = $user->area->name ?? $user->department ?? $user->company ?? 'WhatsApp';

        $ticket = Ticket::create([
            'ticket_number' => $ticketNumber,
            'requester_name' => $user->name,
            'requester_email' => $user->email,
            'requester_area' => $area,
            'description' => $descripcion,
            'verification_code' => $verificationCode,
            'priority' => $priority,
            'status_id' => $newStatus->id,
            'created_by' => $user->id,
            'sla_due_date' => now()->addHours(24),
        ]);

        // Historial
        \App\Models\TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => 'creado',
            'new_value' => 'Ticket creado vía WhatsApp',
        ]);

        $response = "✅ *Ticket creado exitosamente*\n\n";
        $response .= "📌 *Número:* {$ticketNumber}\n";
        $response .= "📋 *Estado:* Nuevo\n";
        $response .= "⏱️ *Prioridad:* " . ucfirst($priority) . "\n";
        $response .= "🏢 *Área:* {$area}\n\n";
        $response .= "Recibirás actualizaciones por este medio.\n\n";
        $response .= "¿Deseas hacer algo más?\n";
        $response .= "1️⃣ Volver al menú\n";
        $response .= "0️⃣ Salir";

        $this->sendMessage($conversation, $response);
        $conversation->updateState('MENU_PRINCIPAL');
    }

    /**
     * Iniciar consulta de ticket
     */
    protected function iniciarConsultaTicket($conversation)
    {
        $response = "🔍 *Consultar Ticket*\n\n";
        $response .= "Por favor ingresa el *número completo* de tu ticket.\n\n";
        $response .= "Ejemplo: TKT-2026-0001\n\n";
        $response .= "💡 *Opciones disponibles:*\n";
        $response .= "• Escribe el número de tu ticket (TKT-YYYY-NNNN)\n";
        $response .= "• Escribe \"menu\" para volver al menú principal\n";
        $response .= "• Escribe \"salir\" para terminar la conversación";

        $this->sendMessage($conversation, $response);
        $conversation->updateState('CONSULTANDO_TICKET');
    }

    /**
     * Consultar ticket específico
     */
    protected function handleConsultandoTicket($conversation, $message)
    {
        $ticketNumber = trim($message);

        // Validar formato del número de ticket
        if (!preg_match('/^TKT-\d{4}-\d{4}$/', $ticketNumber)) {
            $response = "❌ Formato inválido. El número de ticket debe tener el formato:\n\n";
            $response .= "TKT-YYYY-NNNN\n";
            $response .= "Ejemplo: TKT-2026-0001\n\n";
            $response .= "💡 *Opciones disponibles:*\n";
            $response .= "• Escribe el número correcto de tu ticket\n";
            $response .= "• Escribe \"menu\" para volver al menú principal\n";
            $response .= "• Escribe \"salir\" para terminar la conversación";
            
            $this->sendMessage($conversation, $response);
            return;
        }

        // Buscar ticket del usuario
        $ticket = Ticket::where('ticket_number', $ticketNumber)
                       ->where('created_by', $conversation->user_id)
                       ->with(['status', 'assignedAgent'])
                       ->first();

        if (!$ticket) {
            $response = "❌ No se encontró el ticket *{$ticketNumber}* o no te pertenece.\n\n";
            $response .= "Por favor:\n";
            $response .= "• Verifica el número del ticket\n";
            $response .= "• Asegúrate de que el ticket fue creado por ti\n\n";
            $response .= "💡 *Opciones disponibles:*\n";
            $response .= "• Escribe otro número de ticket\n";
            $response .= "• Escribe \"menu\" para volver al menú principal\n";
            $response .= "• Escribe \"salir\" para terminar la conversación";
            
            $this->sendMessage($conversation, $response);
            return;
        }

        // Mostrar detalles del ticket
        $response = "📋 *Detalle del Ticket*\n\n";
        $response .= "🎫 *Número:* {$ticket->ticket_number}\n";
        $response .= "📊 *Estado:* {$ticket->status->name}\n";
        $response .= "⚡ *Prioridad:* " . ucfirst($ticket->priority) . "\n";
        $response .= "🏢 *Área:* {$ticket->requester_area}\n";
        
        if ($ticket->assignedAgent) {
            $response .= "👤 *Asignado a:* {$ticket->assignedAgent->name}\n";
        } else {
            $response .= "👤 *Asignado a:* Sin asignar\n";
        }
        
        $response .= "📅 *Creado:* " . $ticket->created_at->format('d/m/Y H:i') . "\n";
        
        if ($ticket->sla_due_date) {
            $response .= "⏰ *SLA:* " . $ticket->sla_due_date->format('d/m/Y H:i') . "\n";
        }
        
        $response .= "\n📝 *Descripción:*\n{$ticket->description}\n";
        
        if ($ticket->verification_code) {
            $response .= "\n🔐 *Código verificación:* {$ticket->verification_code}\n";
        }
        
        if ($ticket->resolved_at) {
            $response .= "\n✅ *Resuelto:* " . $ticket->resolved_at->format('d/m/Y H:i') . "\n";
        }

        $response .= "\n¿Deseas hacer algo más?\n";
        $response .= "1️⃣ Volver al menú\n";
        $response .= "2️⃣ Consultar otro ticket\n";
        $response .= "3️⃣ Contactar asesor\n";
        $response .= "0️⃣ Salir";

        $this->sendMessage($conversation, $response);
        $conversation->updateState('DETALLES_TICKET');
    }

    /**
     * Manejar opciones después de ver detalles de ticket
     */
    protected function handleDetallesTicket($conversation, $message)
    {
        $option = trim($message);

        switch ($option) {
            case '1':
                $this->mostrarMenuPrincipal($conversation);
                break;

            case '2':
                $this->iniciarConsultaTicket($conversation);
                break;

            case '3':
                $this->iniciarContactoAsesor($conversation);
                break;

            case '0':
            case 'salir':
                $this->cerrarSesion($conversation);
                break;

            default:
                $response = "❌ Opción no válida. Por favor selecciona:\n\n";
                $response .= "1️⃣ Volver al menú\n";
                $response .= "2️⃣ Consultar otro ticket\n";
                $response .= "3️⃣ Contactar asesor\n";
                $response .= "0️⃣ Salir\n\n";
                $response .= "💡 *Otras opciones:*\n";
                $response .= "• Escribe \"menu\" para volver al menú principal\n";
                $response .= "• Escribe \"salir\" para terminar la conversación";

                $this->sendMessage($conversation, $response);
        }
    }

    /**
     * Iniciar contacto con asesor
     */
    protected function iniciarContactoAsesor($conversation)
    {
        $response = "👨‍💼 *Contacto con Asesor*\n\n";
        $response .= "Un asesor será notificado y te contactará pronto.\n\n";
        $response .= "Mientras tanto, puedes:\n";
        $response .= "✅ Continuar usando el chatbot normalmente\n";
        $response .= "✅ El asesor te escribirá por este medio cuando esté disponible\n\n";
        $response .= "¿Deseas volver al menú?\n";
        $response .= "1️⃣ Sí, volver al menú\n";
        $response .= "0️⃣ Salir";

        // Notificar a supervisores
        $this->notificarSupervisores($conversation);

        $this->sendMessage($conversation, $response);
        $conversation->updateState('MENU_PRINCIPAL');
    }

    /**
     * Cerrar sesión
     */
    protected function cerrarSesion($conversation)
    {
        $response = "👋 ¡Hasta pronto!\n\n";
        $response .= "Gracias por usar nuestro servicio.\n";
        $response .= "Envía cualquier mensaje para volver a iniciar.";

        $this->sendMessage($conversation, $response);
        
        $conversation->update([
            'state' => 'INICIO',
            'is_authenticated' => false,
            'context' => null,
        ]);
    }

    /**
     * Reiniciar conversación
     */
    protected function resetConversation($conversation)
    {
        $response = "🔄 *Sesión reiniciada*\n\n";
        $response .= "Para comenzar, por favor ingresa tu número de *cédula*:";

        $this->sendMessage($conversation, $response);
        
        $conversation->update([
            'state' => 'INICIO',
            'is_authenticated' => false,
            'context' => null,
        ]);
    }

    /**
     * Validar cédula
     */
    protected function validarCedula($cedula)
    {
        // Remover caracteres no numéricos
        $cedula = preg_replace('/[^0-9]/', '', $cedula);
        
        // Validar longitud (entre 6 y 10 dígitos)
        return strlen($cedula) >= 6 && strlen($cedula) <= 10;
    }

    /**
     * Validar teléfono
     */
    protected function validarTelefono($telefono)
    {
        // Remover caracteres no numéricos
        $telefono = preg_replace('/[^0-9]/', '', $telefono);
        
        // Validar longitud (10 dígitos)
        return strlen($telefono) === 10;
    }

    /**
     * Notificar a supervisores
     */
    protected function notificarSupervisores($conversation)
    {
        $user = $conversation->user;
        
        // Obtener supervisores activos
        $supervisores = User::whereHas('role', function($q) {
            $q->whereIn('name', ['supervisor', 'admin']);
        })->where('is_active', true)->get();

        foreach ($supervisores as $supervisor) {
            if ($supervisor->email) {
                \Mail::to($supervisor->email)->send(
                    new \App\Mail\AsesorSolicitadoWhatsApp($user, $conversation)
                );
            }
        }
    }

    /**
     * Manejar opciones globales de navegación
     */
    protected function handleOpcionesGlobales($conversation, $message)
    {
        $message = strtolower(trim($message));
        
        // Opción para volver al menú principal
        if (in_array($message, ['menu', 'menú', 'volver', 'volver al menú', 'volver al menu', '0'])) {
            // Solo permitir volver al menú si está autenticado
            if ($conversation->is_authenticated && $conversation->user) {
                $this->mostrarMenuPrincipal($conversation);
                $conversation->updateState('MENU_PRINCIPAL');
                return true;
            } else {
                $this->sendMessage($conversation, "❌ Debes estar autenticado para acceder al menú principal. Por favor inicia sesión con tu cédula y teléfono.");
                return true;
            }
        }
        
        // Opción para salir/cerrar sesión
        if (in_array($message, ['salir', 'exit', 'cerrar', 'cerrar sesión', 'cerrar sesion', 'adios', 'adiós'])) {
            $this->cerrarSesion($conversation);
            return true;
        }
        
        // Opción para consultar otro ticket (solo si está autenticado)
        if (in_array($message, ['consultar', 'consulta', 'otro ticket', 'consultar otro', 'nueva consulta'])) {
            if ($conversation->is_authenticated && $conversation->user) {
                $this->iniciarConsultaTicket($conversation);
                return true;
            } else {
                $this->sendMessage($conversation, "❌ Debes estar autenticado para consultar tickets. Por favor inicia sesión con tu cédula y teléfono.");
                return true;
            }
        }
        
        return false; // No se manejó ninguna opción global
    }

    /**
     * Enviar mensaje y guardar en log
     */
    protected function sendMessage($conversation, $message)
    {
        $this->whatsapp->sendMessage($conversation->phone_number, $message);

        // Guardar mensaje saliente
        WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'message_id' => uniqid(),
            'direction' => 'outgoing',
            'message_body' => $message,
            'sent_at' => now(),
        ]);
    }
}

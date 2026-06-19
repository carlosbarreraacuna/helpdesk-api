<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReportTemplateController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\Api\Kb\KbCategoryController;
use App\Http\Controllers\Api\Kb\KbTagController;
use App\Http\Controllers\Api\Kb\KbArticleController;
use App\Http\Controllers\Api\Assets\AssetTypeController;
use App\Http\Controllers\Api\Assets\LocationController;
use App\Http\Controllers\Api\Assets\ServiceProviderController;
use App\Http\Controllers\Api\Assets\AssetController;
use App\Http\Controllers\Api\Assets\AssetAssignmentController;
use App\Http\Controllers\Api\Assets\AssetMaintenanceController;
use App\Http\Controllers\Api\Assets\UserTechProfileController;
use App\Http\Controllers\Api\Widget\WidgetController;
use App\Http\Controllers\Api\Widget\ChatController;
use App\Http\Controllers\Api\EmailChannelController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\GoogleOAuthController;
use App\Http\Controllers\Api\TicketCategoryController;
use App\Http\Controllers\Api\WorkGroupController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\SlaController;
use App\Http\Controllers\Api\BackupController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::options('{any}', function () {
    return response()->json([], 204);
})->where('any', '.*');

// Public routes (Portal) — throttled per IP to prevent abuse/spam
Route::middleware('throttle:20,1')->group(function () {
    Route::post('/portal/tickets', [TicketController::class, 'store']);
    Route::post('/portal/tickets/search', [TicketController::class, 'searchPublic']);

    // Public ticket validation — accessible via email link (no auth required)
    Route::post('/portal/tickets/validate/{token}', [TicketController::class, 'validateTicket']);
    Route::get('/portal/tickets/validate/{token}', function (string $token) {
        $ticket = \App\Models\Ticket::where('validation_token', $token)->first();
        if (!$ticket) {
            return response()->json(['message' => 'Enlace inválido o expirado'], 404);
        }
        return response()->json([
            'ticket_number'      => $ticket->ticket_number,
            'requester_name'     => $ticket->requester_name,
            'description'        => $ticket->description,
            'validation_deadline'=> $ticket->validation_deadline,
            'status'             => $ticket->status?->name,
        ]);
    });

    // Public KB routes (no auth required)
    Route::get('/portal/kb/categories', [KbCategoryController::class, 'index']);
    Route::get('/portal/kb/articles', [KbArticleController::class, 'index']);
    Route::get('/portal/kb/articles/{id}', [KbArticleController::class, 'show']);
    Route::get('/portal/kb/suggest', [KbArticleController::class, 'suggest']);

    // Widget KB search — público (usuarios no autenticados pueden buscar)
    Route::get('/widget/kb/search', [WidgetController::class, 'kbSearch']);
});

// Authentication routes
// Rate limit: max 5 login attempts per minute per IP → locked 15 min
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

// Refresh token endpoint — uses httpOnly cookie, no Bearer token required
Route::post('/auth/refresh', [AuthController::class, 'refresh'])
    ->middleware('throttle:10,1');

// Protected routes — 60 requests per minute per authenticated user
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Broadcasting auth para canales privados (Reverb/Pusher protocol)
    Route::post('/broadcasting/auth', \App\Http\Controllers\Api\BroadcastingAuthController::class);

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // ─── IA ────────────────────────────────────────────────────────────────────
    Route::prefix('ai')->group(function () {
        Route::post('/classify-ticket',       [AiController::class, 'classifyTicket']);
        Route::post('/tickets/{id}/suggest-reply', [AiController::class, 'suggestReply']);
        Route::post('/tickets/{id}/summarize',     [AiController::class, 'summarizeTicket']);
        Route::post('/kb-search',             [AiController::class, 'kbSearch']);
        Route::post('/widget-chat',           [AiController::class, 'widgetChat']);
        Route::get('/sla-risk',               [AiController::class, 'slaRisk']);
        Route::post('/sentiment',             [AiController::class, 'sentiment']);
    });
    
    // Menu routes
    Route::get('/menu/user', [MenuItemController::class, 'getUserMenu']);
    
    // Returns agents + supervisors for ticket assignment — accessible to all authenticated roles
    Route::get('/users/assignable', [UserController::class, 'assignable']);
    // Returns portal users (role: usuario) for participant search — accessible to all authenticated roles
    Route::get('/users/portal-users', [UserController::class, 'portalUsers']);

    // Ticket routes
    Route::get('/tickets', [TicketController::class, 'index']); // List according to role
    Route::get('/tickets/{id}', [TicketController::class, 'show']);
    Route::get('/tickets/{id}/peek', [TicketController::class, 'peek']);
    Route::get('/ticket-statuses', [TicketController::class, 'getStatuses']); // Get all statuses
    Route::post('/tickets/{id}/assign', [TicketController::class, 'assign']);
    Route::post('/tickets/{id}/escalate', [TicketController::class, 'escalate']);
    Route::post('/tickets/{id}/return-to-group', [TicketController::class, 'returnToGroup']);

    // Ticket categories (public read, admin write)
    Route::get('/ticket-categories', [TicketCategoryController::class, 'index']);
    Route::middleware('permission:users.update')->group(function () {
        Route::get('/ticket-categories/all', [TicketCategoryController::class, 'indexAll']);
        Route::post('/ticket-categories', [TicketCategoryController::class, 'store']);
        Route::patch('/ticket-categories/{id}', [TicketCategoryController::class, 'update']);
        Route::delete('/ticket-categories/{id}', [TicketCategoryController::class, 'destroy']);
    });

    // Work groups (admin only)
    Route::middleware('permission:users.update')->group(function () {
        Route::get('/work-groups', [WorkGroupController::class, 'index']);
        Route::post('/work-groups', [WorkGroupController::class, 'store']);
        Route::patch('/work-groups/{id}', [WorkGroupController::class, 'update']);
        Route::delete('/work-groups/{id}', [WorkGroupController::class, 'destroy']);
        Route::put('/work-groups/{id}/agents', [WorkGroupController::class, 'syncAgents']);
        Route::post('/work-groups/{id}/agents', [WorkGroupController::class, 'addAgent']);
        Route::delete('/work-groups/{id}/agents/{agentId}', [WorkGroupController::class, 'removeAgent']);
        Route::put('/work-groups/{id}/categories', [WorkGroupController::class, 'syncCategories']);
        Route::post('/work-groups/{id}/categories', [WorkGroupController::class, 'addCategory']);
        Route::delete('/work-groups/{id}/categories/{categoryId}', [WorkGroupController::class, 'removeCategory']);
        Route::get('/work-groups/available-agents', [WorkGroupController::class, 'availableAgents']);
    });
    Route::patch('/tickets/{id}/status', [TicketController::class, 'updateStatus']);
    Route::post('/tickets/{id}/comments', [TicketController::class, 'addComment']);
    Route::get('/tickets/{id}/comments', [TicketController::class, 'getComments']);
    Route::get('/tickets/{id}/history', [TicketController::class, 'getHistory']);
    Route::post('/tickets/{id}/close', [TicketController::class, 'close']);
    Route::post('/tickets/{id}/request-validation', [TicketController::class, 'requestValidation']);
    Route::get('/tickets/{id}/validation-status', [TicketController::class, 'getValidationStatus']);
    Route::post('/tickets/{id}/validate-portal', [TicketController::class, 'validateByPortal']);
    Route::post('/tickets/validate/{token}', [TicketController::class, 'validateTicket']);
    
    // User Management Routes
    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/stats', [UserController::class, 'stats']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::get('/users/{id}/permissions', [UserController::class, 'getPermissions']);
    });
    
    // Create user
    Route::post('/users', [UserController::class, 'store'])
        ->middleware('permission:users.create');
    
    // Update user
    Route::patch('/users/{id}', [UserController::class, 'update'])
        ->middleware('permission:users.update');
    
    // Delete user
    Route::delete('/users/{id}', [UserController::class, 'destroy'])
        ->middleware('permission:users.delete');
    
    // Toggle user status
    Route::patch('/users/{id}/toggle-status', [UserController::class, 'toggleStatus'])
        ->middleware('permission:users.toggle_status');
    
    // Assign role
    Route::post('/users/{id}/assign-role', [UserController::class, 'assignRole'])
        ->middleware('permission:users.change_role');
    
    // Reset password
    Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword'])
        ->middleware('permission:users.reset_password');
    
    // Special permissions
    Route::post('/users/{id}/grant-permission', [UserController::class, 'grantPermission'])
        ->middleware('permission:users.manage_permissions');
    
    Route::delete('/users/{userId}/revoke-permission/{permissionId}', [UserController::class, 'revokePermission'])
        ->middleware('permission:users.manage_permissions');
    
    // Permission Management Routes
    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::post('/permissions', [PermissionController::class, 'store']);
    Route::patch('/permissions/{id}', [PermissionController::class, 'update']);
    Route::delete('/permissions/{id}', [PermissionController::class, 'destroy']);
    Route::get('/permissions/roles/{roleId}', [PermissionController::class, 'getRolePermissions']);
    Route::post('/permissions/roles/{roleId}', [PermissionController::class, 'updateRolePermissions']);
    Route::get('/permissions/users/{userId}', [PermissionController::class, 'getUserPermissions']);
    Route::post('/permissions/users/{userId}/toggle', [PermissionController::class, 'toggleUserPermission']);
    
    // Role Management Routes
    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::get('/roles/{id}', [RoleController::class, 'show']);
    Route::patch('/roles/{id}', [RoleController::class, 'update']);
    Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
    
    // Area Management Routes
    Route::get('/areas', [AreaController::class, 'index']);
    Route::post('/areas', [AreaController::class, 'store']);
    Route::get('/areas/{id}', [AreaController::class, 'show']);
    Route::patch('/areas/{id}', [AreaController::class, 'update']);
    Route::delete('/areas/{id}', [AreaController::class, 'destroy']);
    
    // Menu Management Routes
    Route::middleware('permission:settings.update')->group(function () {
        Route::get('/menu-items', [MenuItemController::class, 'index']);
        Route::post('/menu-items', [MenuItemController::class, 'store']);
        Route::patch('/menu-items/{id}', [MenuItemController::class, 'update']);
        Route::delete('/menu-items/{id}', [MenuItemController::class, 'destroy']);
        Route::post('/menu-items/reorder', [MenuItemController::class, 'reorder']);
        
        // Configuración por rol
        Route::get('/menu-items/role/{roleId}', [MenuItemController::class, 'getMenuForRole']);
        Route::post('/menu-items/role/{roleId}', [MenuItemController::class, 'updateRoleMenu']);
    });
    
    // Reports Routes
    Route::get('/reports', [ReportController::class, 'index']);
    
    // MÉTRICAS
    Route::get('/reports/metrics/total-tickets', [ReportController::class, 'totalTickets']);
    Route::get('/reports/metrics/pending-tickets', [ReportController::class, 'pendingTickets']);
    Route::get('/reports/metrics/resolved-tickets', [ReportController::class, 'resolvedTickets']);
    Route::get('/reports/metrics/avg-resolution-time', [ReportController::class, 'avgResolutionTime']);
    
    // GRÁFICOS
    Route::get('/reports/charts/tickets-by-status', [ReportController::class, 'ticketsByStatus']);
    Route::get('/reports/charts/tickets-by-priority', [ReportController::class, 'ticketsByPriority']);
    Route::get('/reports/charts/tickets-trend', [ReportController::class, 'ticketsTrend']);
    Route::get('/reports/charts/tickets-by-area', [ReportController::class, 'ticketsByArea']);
    
    // TABLAS
    Route::get('/reports/tables/tickets-by-agent', [ReportController::class, 'ticketsByAgent']);
    Route::get('/reports/tables/sla-compliance', [ReportController::class, 'slaCompliance']);
    
    // EXPORTACIÓN
    Route::post('/reports/export/all-tickets', [ReportController::class, 'exportTickets'])
        ->middleware('permission:reports.export');
    
    // ADMIN - Gestión de reportes
    Route::middleware('permission:reports.manage')->group(function () {
        Route::get('/report-templates', [ReportTemplateController::class, 'index']);
        Route::post('/report-templates', [ReportTemplateController::class, 'store']);
        Route::patch('/report-templates/{id}', [ReportTemplateController::class, 'update']);
        Route::delete('/report-templates/{id}', [ReportTemplateController::class, 'destroy']);
        
        // Configuración de reportes por rol
        Route::get('/report-templates/role/{roleId}', [ReportTemplateController::class, 'getReportsForRole']);
        Route::post('/report-templates/role/{roleId}', [ReportTemplateController::class, 'updateRoleReports']);
    });
    
    // Module Management Routes
    Route::get('/modules', [ModuleController::class, 'index']);
    Route::post('/modules', [ModuleController::class, 'store']);
    Route::get('/modules/{id}', [ModuleController::class, 'show']);
    Route::patch('/modules/{id}', [ModuleController::class, 'update']);
    Route::delete('/modules/{id}', [ModuleController::class, 'destroy']);
    
    // Audit Routes
    Route::middleware('permission:audit.view')->group(function () {
        Route::get('/audit/permissions', [AuditController::class, 'permissionLogs']);
        Route::get('/audit/permissions/users/{userId}', [AuditController::class, 'userPermissionLogs']);
        Route::get('/audit/permissions/roles/{roleId}', [AuditController::class, 'rolePermissionLogs']);
        Route::get('/audit/auth', [AuditController::class, 'authLogs']);
        Route::get('/audit/auth/summary', [AuditController::class, 'authLogsSummary']);
    });

    // Backup Routes
    Route::middleware('permission:backups.view')->group(function () {
        Route::get('/backups', [BackupController::class, 'index']);
        Route::get('/backups/{id}', [BackupController::class, 'show']);
        Route::get('/backups/{id}/download', [BackupController::class, 'download']);
        Route::get('/backups/restores/history', [BackupController::class, 'restoreHistory']);
        Route::get('/backups/restores/{id}/status', [BackupController::class, 'restoreStatus']);
    });
    Route::middleware('permission:backups.restore')->group(function () {
        Route::post('/backups/{id}/restore', [BackupController::class, 'restore']);
    });

    // ─── SLA ────────────────────────────────────────────────────────────────
    Route::get('/sla/configs', [SlaController::class, 'index']);
    Route::get('/sla/dashboard', [SlaController::class, 'dashboard']);
    Route::patch('/sla/configs/{id}', [SlaController::class, 'update'])
        ->middleware('permission:settings.update');
    Route::post('/sla/recalculate', [SlaController::class, 'recalculate'])
        ->middleware('permission:settings.update');
    Route::get('/sla/history', [SlaController::class, 'history']);
    Route::get('/sla/history/export', [SlaController::class, 'exportHistory']);

    // ─── BASE DE CONOCIMIENTO ───────────────────────────────────────────────

    // Suggest (used in ticket form, public-like but needs auth)
    Route::get('/kb/suggest', [KbArticleController::class, 'suggest']);

    // Categories & Subcategories
    Route::get('/kb/categories', [KbCategoryController::class, 'index']);
    Route::get('/kb/categories/all', [KbCategoryController::class, 'indexAll']);
    Route::get('/kb/categories/{id}/subcategories', [KbCategoryController::class, 'subcategories']);

    Route::middleware('permission:kb.manage')->group(function () {
        Route::post('/kb/categories', [KbCategoryController::class, 'store']);
        Route::patch('/kb/categories/{id}', [KbCategoryController::class, 'update']);
        Route::delete('/kb/categories/{id}', [KbCategoryController::class, 'destroy']);
        Route::post('/kb/categories/{id}/subcategories', [KbCategoryController::class, 'storeSubcategory']);
        Route::patch('/kb/subcategories/{id}', [KbCategoryController::class, 'updateSubcategory']);
        Route::delete('/kb/subcategories/{id}', [KbCategoryController::class, 'destroySubcategory']);
    });

    // Tags
    Route::get('/kb/tags', [KbTagController::class, 'index']);
    Route::post('/kb/tags', [KbTagController::class, 'store'])->middleware('permission:kb.create');
    Route::delete('/kb/tags/{id}', [KbTagController::class, 'destroy'])->middleware('permission:kb.manage');

    // Articles – public read
    Route::get('/kb/articles', [KbArticleController::class, 'index']);
    Route::get('/kb/articles/{id}', [KbArticleController::class, 'show']);
    Route::post('/kb/articles/{id}/vote', [KbArticleController::class, 'vote']);
    Route::get('/kb/articles/{id}/vote', [KbArticleController::class, 'userVote']);

    // Articles – create (agentes+)
    Route::post('/kb/articles', [KbArticleController::class, 'store'])
        ->middleware('permission:kb.create');

    // Articles – versions & edit (supervisores+)
    Route::middleware('permission:kb.edit')->group(function () {
        Route::post('/kb/articles/{id}/versions', [KbArticleController::class, 'storeVersion']);
        Route::get('/kb/articles/{id}/versions', [KbArticleController::class, 'versions']);
        Route::get('/kb/articles/{articleId}/versions/{versionId}', [KbArticleController::class, 'showVersion']);
    });

    // Articles – publish/archive (admin)
    Route::middleware('permission:kb.publish')->group(function () {
        Route::post('/kb/articles/{articleId}/versions/{versionId}/publish', [KbArticleController::class, 'publishVersion']);
        Route::patch('/kb/articles/{id}/status', [KbArticleController::class, 'updateStatus']);
        Route::delete('/kb/articles/{id}', [KbArticleController::class, 'destroy']);
    });

    // Ticket <-> KB Article integration
    Route::get('/tickets/{ticketId}/kb-articles', [KbArticleController::class, 'ticketArticles']);
    Route::post('/tickets/{ticketId}/kb-articles', [KbArticleController::class, 'linkToTicket'])
        ->middleware('permission:kb.link');
    Route::delete('/tickets/{ticketId}/kb-articles/{articleId}', [KbArticleController::class, 'unlinkFromTicket'])
        ->middleware('permission:kb.link');

    // =====================
    // MÓDULO INVENTARIO
    // =====================

    // Tipos de activos
    Route::get('/assets/types', [AssetTypeController::class, 'index']);
    Route::middleware('permission:assets.manage')->group(function () {
        Route::post('/assets/types', [AssetTypeController::class, 'store']);
        Route::patch('/assets/types/{assetType}', [AssetTypeController::class, 'update']);
        Route::delete('/assets/types/{assetType}', [AssetTypeController::class, 'destroy']);
        Route::get('/assets/types/{assetType}/fields', [AssetTypeController::class, 'indexFields']);
        Route::post('/assets/types/{assetType}/fields', [AssetTypeController::class, 'storeField']);
        Route::patch('/assets/types/{assetType}/fields/{field}', [AssetTypeController::class, 'updateField']);
        Route::delete('/assets/types/{assetType}/fields/{field}', [AssetTypeController::class, 'destroyField']);
    });

    // Ubicaciones
    Route::get('/locations', [LocationController::class, 'index']);
    Route::get('/areas/{area}/locations', [LocationController::class, 'indexByArea']);
    Route::middleware('permission:assets.manage')->group(function () {
        Route::post('/areas/{area}/locations', [LocationController::class, 'store']);
        Route::patch('/locations/{location}', [LocationController::class, 'update']);
        Route::delete('/locations/{location}', [LocationController::class, 'destroy']);
    });

    // Proveedores de servicio
    Route::get('/service-providers', [ServiceProviderController::class, 'index']);
    Route::middleware('permission:assets.manage')->group(function () {
        Route::post('/service-providers', [ServiceProviderController::class, 'store']);
        Route::patch('/service-providers/{serviceProvider}', [ServiceProviderController::class, 'update']);
        Route::delete('/service-providers/{serviceProvider}', [ServiceProviderController::class, 'destroy']);
    });

    // Activos
    Route::middleware('permission:assets.view')->group(function () {
        Route::get('/assets', [AssetController::class, 'index']);
        Route::get('/assets/{asset}', [AssetController::class, 'show']);
        Route::get('/assets/{asset}/events', [AssetController::class, 'events']);
        Route::get('/assets/{asset}/assignments', [AssetController::class, 'assignments']);
        Route::get('/assets/{asset}/maintenances', [AssetController::class, 'maintenances']);
    });
    Route::middleware('permission:assets.create')->group(function () {
        Route::post('/assets', [AssetController::class, 'store']);
    });
    Route::middleware('permission:assets.update')->group(function () {
        Route::patch('/assets/{asset}', [AssetController::class, 'update']);
        Route::patch('/assets/{asset}/status', [AssetController::class, 'updateStatus']);
        Route::patch('/assets/{asset}/location', [AssetController::class, 'updateLocation']);
    });
    Route::middleware('permission:assets.manage')->group(function () {
        Route::delete('/assets/{asset}', [AssetController::class, 'destroy']);
    });

    // Asignaciones
    Route::middleware('permission:assets.assign')->group(function () {
        Route::post('/assets/{asset}/assign', [AssetAssignmentController::class, 'assign']);
        Route::post('/assets/{asset}/return', [AssetAssignmentController::class, 'return']);
    });
    Route::get('/users/{userId}/assets', [AssetAssignmentController::class, 'userAssets']);

    // Mantenimientos
    Route::middleware('permission:assets.view')->group(function () {
        Route::get('/maintenances', [AssetMaintenanceController::class, 'index']);
        Route::get('/maintenances/{maintenance}', [AssetMaintenanceController::class, 'show']);
    });
    Route::middleware('permission:assets.maintain')->group(function () {
        Route::post('/maintenances', [AssetMaintenanceController::class, 'store']);
        Route::patch('/maintenances/{maintenance}', [AssetMaintenanceController::class, 'update']);
        Route::patch('/maintenances/{maintenance}/status', [AssetMaintenanceController::class, 'updateStatus']);
    });

    // ── Widget de Soporte ──────────────────────────────────────────────────────
    Route::prefix('widget')->group(function () {
        // KB search público para el widget (también funciona sin auth, manejado aparte)
        Route::get('/kb/search', [WidgetController::class, 'kbSearch']);
        // Con autenticación:
        Route::get('/session', [WidgetController::class, 'session']);
        Route::post('/session', [WidgetController::class, 'session']);
        Route::get('/recent-tickets', [WidgetController::class, 'recentTickets']);
        Route::get('/tickets/{ticketId}/messages', [WidgetController::class, 'ticketMessages']);
        Route::post('/articles/{articleId}/feedback', [WidgetController::class, 'articleFeedback']);
        // Chat
        Route::get('/sessions/{session}/messages', [ChatController::class, 'messages']);
        Route::post('/sessions/{session}/messages', [ChatController::class, 'sendMessage']);
        Route::post('/sessions/{session}/read', [ChatController::class, 'markRead']);
        Route::post('/sessions/{session}/close', [ChatController::class, 'close']);
        Route::post('/sessions/{session}/assign', [ChatController::class, 'assign']);
        // Panel agente
        Route::get('/active-sessions', [ChatController::class, 'activeSessions']);
        Route::get('/unread-count', [ChatController::class, 'unreadCount']);
    });

    // ── Canal de Email ────────────────────────────────────────────────────────
    Route::prefix('email-channels')->middleware('permission:settings.update')->group(function () {
        Route::get('/',                                    [EmailChannelController::class, 'index']);
        Route::post('/',                                   [EmailChannelController::class, 'store']);
        Route::patch('/{emailChannel}',                    [EmailChannelController::class, 'update']);
        Route::delete('/{emailChannel}',                   [EmailChannelController::class, 'destroy']);
        Route::post('/{emailChannel}/test',                [EmailChannelController::class, 'testConnection']);
        Route::post('/{emailChannel}/poll',                [EmailChannelController::class, 'pollNow']);
    });
    Route::get('/tickets/{ticketId}/email-messages',       [EmailChannelController::class, 'messages']);

    // Reply to ticket by specific channel
    Route::post('/tickets/{id}/reply-channel', [TicketController::class, 'replyByChannel']);

    // Ticket participants
    Route::get('/tickets/{id}/participants',             [TicketController::class, 'getParticipants']);
    Route::post('/tickets/{id}/participants',            [TicketController::class, 'addParticipant']);
    Route::delete('/tickets/{id}/participants/{userId}', [TicketController::class, 'removeParticipant']);

    // Remote sessions (AnyDesk / TeamViewer)
    Route::get('/tickets/{ticketId}/remote-sessions',                      [\App\Http\Controllers\Api\RemoteSessionController::class, 'index']);
    Route::post('/tickets/{ticketId}/remote-sessions',                     [\App\Http\Controllers\Api\RemoteSessionController::class, 'store']);
    Route::patch('/tickets/{ticketId}/remote-sessions/{sessionId}/finish', [\App\Http\Controllers\Api\RemoteSessionController::class, 'finish']);
    Route::delete('/tickets/{ticketId}/remote-sessions/{sessionId}',       [\App\Http\Controllers\Api\RemoteSessionController::class, 'destroy']);

    // ── Google Calendar / Meet ─────────────────────────────────────────────────
    Route::prefix('google')->group(function () {
        Route::get('/oauth/redirect-url', [GoogleOAuthController::class, 'redirectUrl']);
        Route::get('/oauth/status',       [GoogleOAuthController::class, 'status']);
        Route::delete('/oauth/disconnect',[GoogleOAuthController::class, 'disconnect']);
    });

    // Meetings
    Route::get('/meetings',                              [MeetingController::class, 'index']);
    Route::post('/meetings',                             [MeetingController::class, 'store']);
    Route::get('/meetings/metrics',                      [MeetingController::class, 'metrics']);
    Route::get('/meetings/{id}',                         [MeetingController::class, 'show']);
    Route::patch('/meetings/{id}',                       [MeetingController::class, 'update']);
    Route::delete('/meetings/{id}',                      [MeetingController::class, 'destroy']);
    Route::post('/tickets/{ticketId}/quick-meet',        [MeetingController::class, 'quickMeet']);

    // Hoja de vida del usuario
    Route::get('/users/{userId}/profile/tech', [UserTechProfileController::class, 'profile']);
    Route::get('/users/{userId}/profile/assets', [UserTechProfileController::class, 'currentAssets']);
    Route::get('/users/{userId}/profile/assets/history', [UserTechProfileController::class, 'assetHistory']);
    Route::get('/users/{userId}/profile/software', [UserTechProfileController::class, 'software']);
    Route::middleware('permission:assets.assign')->group(function () {
        Route::post('/users/{userId}/profile/software', [UserTechProfileController::class, 'storeSoftware']);
        Route::delete('/users/{userId}/profile/software/{software}', [UserTechProfileController::class, 'destroySoftware']);
    });
});

// Google OAuth2 callback — público (no lleva token de Sanctum)
Route::get('/google/oauth/callback', [GoogleOAuthController::class, 'callback'])->name('google.oauth.callback');

// WhatsApp/Gmail webhooks (públicas) — throttle generoso para no perder callbacks legítimos del proveedor
Route::middleware('throttle:120,1')->group(function () {
    Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
    Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'webhook']);

    // Gmail Pub/Sub push webhook (público — Google no envía auth tokens)
    Route::post('/gmail/webhook', [\App\Http\Controllers\Api\GmailWebhookController::class, 'push']);
});

// Gmail OAuth2 flow — callback must be defined BEFORE the dynamic {channelId} route
Route::get('/gmail/oauth/callback',    [\App\Http\Controllers\Api\GmailWebhookController::class, 'oauthCallback'])->name('gmail.oauth.callback');
Route::get('/gmail/oauth/{channelId}', [\App\Http\Controllers\Api\GmailWebhookController::class, 'oauthRedirect'])->name('gmail.oauth.redirect');

// Enviar mensaje manual desde el sistema (requiere autenticación)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/whatsapp/send', [WhatsAppWebhookController::class, 'sendManualMessage']);
});

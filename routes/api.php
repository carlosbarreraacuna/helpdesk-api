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

// Public routes (Portal)
Route::post('/portal/tickets', [TicketController::class, 'store']);
Route::post('/portal/tickets/search', [TicketController::class, 'searchPublic']);

// Authentication routes
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    
    // Menu routes
    Route::get('/menu/user', [MenuItemController::class, 'getUserMenu']);
    
    // Ticket routes
    Route::get('/tickets', [TicketController::class, 'index']); // List according to role
    Route::get('/tickets/{id}', [TicketController::class, 'show']);
    Route::get('/ticket-statuses', [TicketController::class, 'getStatuses']); // Get all statuses
    Route::post('/tickets/{id}/assign', [TicketController::class, 'assign']);
    Route::post('/tickets/{id}/escalate', [TicketController::class, 'escalate']);
    Route::patch('/tickets/{id}/status', [TicketController::class, 'updateStatus']);
    Route::post('/tickets/{id}/comments', [TicketController::class, 'addComment']);
    Route::get('/tickets/{id}/comments', [TicketController::class, 'getComments']);
    Route::post('/tickets/{id}/close', [TicketController::class, 'close']);
    
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
    Route::get('/audit/permissions', [AuditController::class, 'permissionLogs']);
    Route::get('/audit/permissions/users/{userId}', [AuditController::class, 'userPermissionLogs']);
    Route::get('/audit/permissions/roles/{roleId}', [AuditController::class, 'rolePermissionLogs']);
});

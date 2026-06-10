<?php

$tags = [
    'AuthController'             => 'Autenticación',
    'TicketController'           => 'Tickets',
    'UserController'             => 'Usuarios',
    'RoleController'             => 'Roles y Permisos',
    'PermissionController'       => 'Roles y Permisos',
    'SlaController'              => 'SLA',
    'ReportController'           => 'Reportes',
    'ReportTemplateController'   => 'Reportes',
    'AuditController'            => 'Auditoría',
    'AreaController'             => 'Configuración',
    'ModuleController'           => 'Configuración',
    'MenuItemController'         => 'Configuración',
    'WorkGroupController'        => 'Configuración',
    'TicketCategoryController'   => 'Configuración',
    'KbArticleController'        => 'Base de Conocimiento',
    'KbCategoryController'       => 'Base de Conocimiento',
    'KbTagController'            => 'Base de Conocimiento',
    'AssetController'            => 'Inventario de Activos',
    'AssetTypeController'        => 'Inventario de Activos',
    'AssetAssignmentController'  => 'Inventario de Activos',
    'AssetMaintenanceController' => 'Inventario de Activos',
    'LocationController'         => 'Inventario de Activos',
    'ServiceProviderController'  => 'Inventario de Activos',
    'UserTechProfileController'  => 'Inventario de Activos',
    'EmailChannelController'     => 'Canales',
    'WhatsAppWebhookController'  => 'Canales',
    'GmailWebhookController'     => 'Canales',
    'GoogleOAuthController'      => 'Canales',
    'ChatController'             => 'Widget / Chat',
    'WidgetController'           => 'Widget / Chat',
    'AiController'               => 'Inteligencia Artificial',
    'MeetingController'          => 'Reuniones Remotas',
    'RemoteSessionController'    => 'Reuniones Remotas',
    'BroadcastingAuthController' => 'WebSockets',
];

$base = 'app/Http/Controllers/Api';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
$updated = 0;

foreach ($files as $file) {
    if ($file->getExtension() !== 'php') continue;
    $name = $file->getBasename('.php');
    if (!isset($tags[$name])) continue;
    $tag = $tags[$name];
    $content = file_get_contents($file->getPathname());
    if (str_contains($content, '@tags')) continue;
    $content = preg_replace(
        '/(class ' . preg_quote($name, '/') . '\b)/',
        "/**\n * @tags {$tag}\n */\n$1",
        $content,
        1
    );
    file_put_contents($file->getPathname(), $content);
    echo "Tagged: {$name} -> {$tag}\n";
    $updated++;
}

echo "\nTotal updated: {$updated}\n";

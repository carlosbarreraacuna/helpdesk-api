<?php

namespace App\Providers;

use App\Models\WidgetChatSession;
use App\Policies\WidgetChatSessionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(WidgetChatSession::class, WidgetChatSessionPolicy::class);

        // Allow docs access only to users with settings.update permission
        Gate::define('viewApiDocs', function ($user) {
            return $user && $user->hasPermissionAccess('settings.update');
        });

        $this->bootScramble();
    }

    private function bootScramble(): void
    {
        if (!class_exists(\Dedoc\Scramble\Scramble::class)) {
            return;
        }

        \Dedoc\Scramble\Scramble::ignoreDefaultRoutes();

        \Dedoc\Scramble\Scramble::registerApi('v1', [
            'api_path' => 'api',
            'info'     => [
                'title'   => config('app.name') . ' — API v1',
                'version' => '1.0.0',
            ],
        ]);

        \Dedoc\Scramble\Scramble::afterOpenApiGenerated(function (\Dedoc\Scramble\Support\Generator\OpenApi $openApi) {
            $openApi->secure(
                \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer', 'Bearer')
            );
        });
    }
}

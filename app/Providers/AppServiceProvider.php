<?php

namespace App\Providers;

use App\Models\WidgetChatSession;
use App\Policies\WidgetChatSessionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(WidgetChatSession::class, WidgetChatSessionPolicy::class);
    }
}

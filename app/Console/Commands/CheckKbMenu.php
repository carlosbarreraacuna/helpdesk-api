<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;

class CheckKbMenu extends Command
{
    protected $signature = 'kb:check-menu';
    protected $description = 'Check KB menu items and their role visibility';

    public function handle()
    {
        $parent = MenuItem::where('key', 'knowledge_base')->first();
        $this->info("Parent: id={$parent->id} route={$parent->route}");

        $children = MenuItem::where('parent_id', $parent->id)->orderBy('order')->get();
        foreach ($children as $child) {
            $roles = DB::table('menu_item_role')
                ->where('menu_item_id', $child->id)
                ->where('is_visible', true)
                ->count();
            $this->info("  Child: {$child->key} | {$child->label} | {$child->route} | visible_roles={$roles}");
        }

        $parentRoles = DB::table('menu_item_role')
            ->where('menu_item_id', $parent->id)
            ->where('is_visible', true)
            ->count();
        $this->info("Parent visible_roles={$parentRoles}");
    }
}

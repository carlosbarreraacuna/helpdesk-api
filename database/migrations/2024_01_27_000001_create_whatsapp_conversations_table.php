<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->index(); // WhatsApp del usuario
            $table->string('wa_id')->index(); // WhatsApp ID de Meta
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->enum('state', [
                'INICIO',
                'ESPERANDO_TELEFONO',
                'AUTENTICANDO',
                'MENU_PRINCIPAL',
                'CREANDO_TICKET',
                'ESPERANDO_DESCRIPCION',
                'CONSULTANDO_TICKET',
                'CONTACTANDO_ASESOR'
            ])->default('INICIO');
            $table->json('context')->nullable(); // Datos temporales de la conversación
            $table->boolean('is_authenticated')->default(false);
            $table->timestamp('last_interaction_at');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};

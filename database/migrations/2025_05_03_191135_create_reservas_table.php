<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios', 'id');
            $table->foreignId('espacio_id')->nullable()->constrained('espacios', 'id');
            $table->foreignId('clase_id')->nullable()->constrained('clases', 'id');
            $table->string('tipo_reserva');
            $table->timestampTz('horario_reserva')->useCurrent();
            $table->timestamps();
        });        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reservas');
    }
};

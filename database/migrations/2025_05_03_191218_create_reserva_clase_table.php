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
        Schema::create('reserva_clase', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clase_id')->constrained('clases', 'id');
            $table->timestampTz('horario_reserva')->useCurrent();
            $table->integer('capacidad_actual')->default(0);
            $table->integer('capacidad_maxima');
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
        Schema::dropIfExists('reserva_clase');
    }
};

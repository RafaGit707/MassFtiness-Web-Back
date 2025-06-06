<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReservaClaseIdToReservasTable extends Migration
{
    public function up()
    {
        Schema::table('reservas', function (Blueprint $table) {
            // Asegúrate de que el tipo y si es unsigned coincidan con la clave primaria de 'reserva_clase'
            $table->foreignId('reserva_clase_id')->nullable()->after('clase_id') // O después del campo que prefieras
                  ->comment('FK a la tabla reserva_clase (slots de horario definidos)')
                  ->constrained('reserva_clase') // Asume que tu tabla es 'reserva_clase'
                  ->onDelete('set null'); // O 'cascade' si quieres que las reservas se borren al borrar el slot
                                          // 'set null' es más seguro si quieres mantener el historial de reservas
        });
    }

    public function down()
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropForeign(['reserva_clase_id']);
            $table->dropColumn('reserva_clase_id');
        });
    }
}
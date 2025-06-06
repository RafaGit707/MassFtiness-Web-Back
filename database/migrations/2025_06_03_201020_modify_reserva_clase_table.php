<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyReservaClaseTable extends Migration
{
    public function up()
    {
        Schema::table('reserva_clase', function (Blueprint $table) {
            // Renombrar 'horario_reserva' si existe y es DATETIME, o eliminarla
            if (Schema::hasColumn('reserva_clase', 'horario_reserva')) {
                // Si es DATETIME y quieres mantener los datos, necesitarías un plan de migración de datos
                // Por ahora, la eliminaremos si no la necesitas más con este formato
                $table->dropColumn('horario_reserva');
            }
            // Si 'capacidad_actual' se calcula al vuelo, puedes eliminarla también
            // if (Schema::hasColumn('reserva_clase', 'capacidad_actual')) {
            //     $table->dropColumn('capacidad_actual');
            // }

            $table->tinyInteger('dia_semana')->after('clase_id'); // 1=Lunes, ..., 7=Domingo
            $table->time('hora_inicio')->after('dia_semana');
            $table->integer('duracion_minutos')->default(60)->after('hora_inicio'); // Duración en minutos
            // capacidad_maxima ya la tienes
        });
    }

    public function down()
    {
        Schema::table('reserva_clase', function (Blueprint $table) {
            $table->dropColumn('dia_semana');
            $table->dropColumn('hora_inicio');
            $table->dropColumn('duracion_minutos');
            // Re-añadir horario_reserva si es necesario para el rollback
            // $table->timestamp('horario_reserva')->nullable();
        });
    }
}

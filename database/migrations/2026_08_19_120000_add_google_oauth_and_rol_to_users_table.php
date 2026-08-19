<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ingreso con Google y rol.
     *
     * Lo importante acá es que `password` pasa a ser nullable: quien entra con
     * Google nunca eligió una contraseña, y guardarle una al azar sería peor que
     * no tener ninguna —figuraría como que puede entrar con email y clave cuando
     * no puede—.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // El `sub` de Google: su identificador estable. No es el email, que
            // el usuario puede cambiar en su cuenta de Google.
            $table->string('google_id', 64)->nullable()->unique()->after('email');

            // Centinela es de un solo usuario, pero el rol existe desde el
            // principio para no tener que rehacer la autorización el día que
            // haya que compartirle la documentación de un proyecto a alguien.
            //
            // Es un ENUM real: al sumar un caso a RolUsuario hay que ensancharlo
            // en otra migración, o sqlite pasa los tests y MySQL tira 500.
            $table->enum('rol', ['admin', 'lector'])->default('admin')->after('google_id');

            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'rol']);

            // Volver atrás con usuarios sin contraseña dejaría la tabla en un
            // estado que MySQL no acepta, así que se les pone una imposible de
            // usar: no coincide con ningún hash.
            DB::table('users')
                ->whereNull('password')
                ->update(['password' => '']);

            $table->string('password')->nullable(false)->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('full_name');

            // Добавляем first_name и last_name
            $table->string('first_name')->after('id'); // или after нужного поля
            $table->string('last_name')->after('first_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Откатываем изменения: удаляем новые поля
            $table->dropColumn(['first_name', 'last_name']);

            // Возвращаем поле fullname
            $table->string('full_name')->after('id');
        });
    }
};

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
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('route');

            // Добавляем поля для начальной и конечной точки
            $table->string('pickup_address')->after('id'); // или after другого подходящего поля
            $table->string('destination_address')->after('pickup_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
           // Удаляем новые поля
            $table->dropColumn(['pickup_address', 'destination_address']);

            // Восстанавливаем поле route
            $table->string('route')->after('id');
        });
    }
};

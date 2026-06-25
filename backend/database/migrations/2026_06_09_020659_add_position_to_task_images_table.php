<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_images', function (Blueprint $table) {
            if (!Schema::hasColumn('task_images', 'position')) {
                $table->integer('position')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('task_images', function (Blueprint $table) {
            if (Schema::hasColumn('task_images', 'position')) {
                $table->dropColumn('position');
            }
        });
    }
};
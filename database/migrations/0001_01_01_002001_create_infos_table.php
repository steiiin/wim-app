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
        Schema::create('infos', function (Blueprint $table) {
            $table->id();
            $table->mediumText('payload');
            $table->boolean('is_permanent');
            $table->dateTimeTz('from')->nullable();
            $table->dateTimeTz('until')->nullable();
            $table->boolean('is_allday')->default(false);
            $table->string('autotag')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('infos');
    }
};

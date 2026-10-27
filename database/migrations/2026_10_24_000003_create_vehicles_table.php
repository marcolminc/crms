<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate_number', 20)->unique();
            $table->string('make', 60);
            $table->string('model', 60);
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('type', 30)->index();
            $table->unsignedTinyInteger('seats')->default(5);
            $table->string('transmission', 20)->default('manual');
            $table->string('fuel_type', 20)->default('petrol');
            $table->string('colour', 40)->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->decimal('rate_per_day', 12, 2);
            $table->string('status', 30)->default('available')->index();
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};

<?php

use App\Models\Parameter;
use App\Models\Run;
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
        Schema::create('run_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Run::class);
            $table->foreignIdFor(Parameter::class);
            $table->json('value');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('run_parameters');
    }
};

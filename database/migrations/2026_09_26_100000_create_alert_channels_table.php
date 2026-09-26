<?php

use App\Models\Tenant;
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
        Schema::create('alert_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            // Encrypted by the model. Anyone with a webhook URL can post to its channel.
            $table->text('webhook_url');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_channels');
    }
};

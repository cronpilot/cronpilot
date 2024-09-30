<?php

use App\Models\Server;
use App\Models\ServerCredential;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('server_server_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ServerCredential::class);
            $table->foreignIdFor(Server::class);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->drop('server_server_credentials');
        });
    }
};

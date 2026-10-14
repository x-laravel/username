<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection($this->getConnection())->create($this->table(), function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->string('username');
            $table->timestamp('created_at')->nullable();

            $table->unique(['model_type', 'username']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists($this->table());
    }

    public function getConnection(): ?string
    {
        $connection = Config::get('username.history.connection');

        return is_string($connection) ? $connection : null;
    }

    private function table(): string
    {
        return Config::string('username.history.table', 'username_history');
    }
};

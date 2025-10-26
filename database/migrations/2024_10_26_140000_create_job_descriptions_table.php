<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_descriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('filename');
            $table->string('stored_path');
            $table->longText('text');
            $table->timestamps();
        });
    }
};


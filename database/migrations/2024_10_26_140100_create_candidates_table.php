<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('job_description_id');
            $table->string('filename');
            $table->string('stored_path');
            $table->longText('resume_text')->nullable();
            $table->unsignedTinyInteger('fit_score')->nullable();
            $table->json('strengths')->nullable();
            $table->json('weaknesses')->nullable();
            $table->text('summary')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamps();

            $table->foreign('job_description_id')
                ->references('id')
                ->on('job_descriptions')
                ->cascadeOnDelete();
        });
    }
};


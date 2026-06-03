<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_application_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('credit_application_id');
            $table->unsignedBigInteger('credit_facility_id')->nullable();
            $table->unsignedBigInteger('author_user_id');
            $table->text('note');
            $table->timestamps();

            $table->index(['credit_application_id', 'created_at'], 'credit_application_notes_application_created_index');
            $table->index(['credit_facility_id', 'created_at'], 'credit_application_notes_facility_created_index');
            $table->index('author_user_id', 'credit_application_notes_author_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_application_notes');
    }
};

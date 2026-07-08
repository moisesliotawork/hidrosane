<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_auto_merge_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('merged_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('trigger', 32)->default('scheduled');
            $table->json('failures')->nullable();
            $table->timestamp('ran_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_auto_merge_runs');
    }
};

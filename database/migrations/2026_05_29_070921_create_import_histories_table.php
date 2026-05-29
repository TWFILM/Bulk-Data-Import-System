<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create("import_histories", function (Blueprint $table) {
            // Primary key is the Laravel batch UUID (string)
            $table->string("id")->primary();
            $table->string("file_name");
            // Processing | Finished | Failed
            $table->string("status")->default("Processing");
            $table->unsignedInteger("items_added")->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("import_histories");
    }
};

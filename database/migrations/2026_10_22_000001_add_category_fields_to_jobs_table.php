<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->string('category')->nullable()->index()->after('required_skills');
            $table->string('subcategory')->nullable()->index()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['subcategory']);
            $table->dropColumn(['category', 'subcategory']);
        });
    }
};

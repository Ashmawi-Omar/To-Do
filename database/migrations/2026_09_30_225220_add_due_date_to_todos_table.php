<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->date('due_date')->nullable()->index()->after('completed');
        });

        // Existing to-dos keep the day they were created on.
        DB::table('todos')->orderBy('id')->each(function ($todo) {
            DB::table('todos')
                ->where('id', $todo->id)
                ->update(['due_date' => substr($todo->created_at, 0, 10)]);
        });

        Schema::table('todos', function (Blueprint $table) {
            $table->date('due_date')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->dropIndex(['due_date']);
            $table->dropColumn('due_date');
        });
    }
};

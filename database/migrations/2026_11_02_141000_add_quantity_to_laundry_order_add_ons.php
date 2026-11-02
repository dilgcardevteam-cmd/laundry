<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laundry_order_add_ons', function (Blueprint $table): void {
            $table->unsignedInteger('quantity')->default(1)->after('name');
            $table->decimal('line_total', 12, 2)->default(0)->after('price');
        });

        DB::table('laundry_order_add_ons')->update([
            'line_total' => DB::raw('price'),
        ]);
    }

    public function down(): void
    {
        Schema::table('laundry_order_add_ons', function (Blueprint $table): void {
            $table->dropColumn(['quantity', 'line_total']);
        });
    }
};

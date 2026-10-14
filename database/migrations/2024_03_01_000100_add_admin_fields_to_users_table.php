<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('cover')->index();
            $table->timestamp('suspended_at')->nullable()->after('is_admin');
            $table->string('suspension_reason')->nullable()->after('suspended_at');
        });

        // The first member to have registered runs the network until they
        // hand the role on, so a fresh install is never locked out of /admin.
        $first = DB::table('users')->orderBy('id')->value('id');

        if ($first) {
            DB::table('users')->where('id', $first)->update(['is_admin' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'suspended_at', 'suspension_reason']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('petrol_license_photo')->nullable()->after('approval_status');
            $table->string('gst_certificate_photo')->nullable()->after('petrol_license_photo');
            $table->string('owner_id_proof_photo')->nullable()->after('gst_certificate_photo');
            $table->enum('verification_status', ['pending', 'approved', 'rejected'])->default('pending')->after('owner_id_proof_photo');
            $table->text('rejection_reason')->nullable()->after('verification_status');
            $table->timestamp('approved_at')->nullable()->after('rejection_reason');
        });

        DB::table('agents')->update([
            'verification_status' => DB::raw('approval_status'),
        ]);

        DB::table('agents')
            ->where('approval_status', 'approved')
            ->update(['approved_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn([
                'petrol_license_photo',
                'gst_certificate_photo',
                'owner_id_proof_photo',
                'verification_status',
                'rejection_reason',
                'approved_at',
            ]);
        });
    }
};

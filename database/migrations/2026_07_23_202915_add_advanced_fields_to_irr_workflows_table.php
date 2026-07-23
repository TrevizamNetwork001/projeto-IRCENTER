<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('irr_workflows', function (Blueprint $table): void {
            $table->string('profile_key', 30)
                ->default('manual')
                ->after('name');

            $table->string('admin_contact_handle', 100)
                ->nullable()
                ->after('contact_handle');

            $table->string('tech_contact_handle', 100)
                ->nullable()
                ->after('admin_contact_handle');

            $table->string('noc_email')
                ->nullable()
                ->after('contact_email');

            $table->string('abuse_email')
                ->nullable()
                ->after('noc_email');

            $table->string('peering_email')
                ->nullable()
                ->after('abuse_email');

            $table->string('peeringdb_url')
                ->nullable()
                ->after('peering_email');

            $table->string('looking_glass_url')
                ->nullable()
                ->after('peeringdb_url');

            $table->string('website_url')
                ->nullable()
                ->after('looking_glass_url');

            $table->text('custom_remarks')
                ->nullable()
                ->after('website_url');

            $table->index('profile_key');
        });
    }

    public function down(): void
    {
        Schema::table('irr_workflows', function (Blueprint $table): void {
            $table->dropIndex(['profile_key']);

            $table->dropColumn([
                'profile_key',
                'admin_contact_handle',
                'tech_contact_handle',
                'noc_email',
                'abuse_email',
                'peering_email',
                'peeringdb_url',
                'looking_glass_url',
                'website_url',
                'custom_remarks',
            ]);
        });
    }
};

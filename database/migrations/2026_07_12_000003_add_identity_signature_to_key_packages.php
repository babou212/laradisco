<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bind each KeyPackage to its owner's long-term identity key. The client signs
 * the KeyPackage bytes with its user identity private key; peers verify this
 * signature (against the identity key from identity/{user}) before adding the
 * device to a group — closing the "malicious server injects a rogue device"
 * gap, since the server cannot forge the identity signature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mls_key_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('mls_key_packages', 'identity_signature')) {
                $table->text('identity_signature')->nullable()->after('key_package_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mls_key_packages', function (Blueprint $table) {
            $table->dropColumn('identity_signature');
        });
    }
};

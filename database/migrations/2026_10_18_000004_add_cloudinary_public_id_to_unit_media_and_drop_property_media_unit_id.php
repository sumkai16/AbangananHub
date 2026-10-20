<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * unit_media never stored the Cloudinary public id, so deleting a unit photo guessed it from the
 * URL (and never deleted videos, which live in a different folder and resource type). Store it.
 *
 * property_media.unit_id was never written (0 rows) and nothing read it; unit photos live in
 * unit_media. Dropped.
 *
 * The backfill parses Cloudinary delivery URLs ("…/upload/v123/<folder>/<name>.<ext>" becomes
 * "<folder>/<name>"). Any other URL (seeded Unsplash photos, for instance) stays null and falls
 * back to UnitMedia::destroyFromCloudinary()'s legacy guess.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('unit_media', 'cloudinary_public_id')) {
            Schema::table('unit_media', fn (Blueprint $t) => $t->string('cloudinary_public_id', 255)->nullable()->after('media_url'));
        }

        DB::table('unit_media')
            ->whereNull('cloudinary_public_id')
            ->where('media_url', 'like', '%res.cloudinary.com/%/upload/%')
            ->orderBy('media_id')
            ->each(function ($row) {
                $publicId = $this->publicIdFromUrl($row->media_url);
                if ($publicId !== null) {
                    DB::table('unit_media')->where('media_id', $row->media_id)->update(['cloudinary_public_id' => $publicId]);
                }
            }, 200, 'media_id');

        if (Schema::hasColumn('property_media', 'unit_id')) {
            Schema::table('property_media', function (Blueprint $t) {
                $t->dropForeign(['unit_id']);
                $t->dropColumn('unit_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('property_media', 'unit_id')) {
            Schema::table('property_media', function (Blueprint $t) {
                $t->unsignedBigInteger('unit_id')->nullable()->after('property_id');
                $t->foreign('unit_id')->references('unit_id')->on('property_units')->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('unit_media', 'cloudinary_public_id')) {
            Schema::table('unit_media', fn (Blueprint $t) => $t->dropColumn('cloudinary_public_id'));
        }
    }

    /** "https://res.cloudinary.com/x/image/upload/v123/abanganan/units/a.jpg" => "abanganan/units/a" */
    private function publicIdFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! $path || ! str_contains($path, '/upload/')) {
            return null;
        }

        $afterUpload = substr($path, strpos($path, '/upload/') + strlen('/upload/'));
        // Only the version segment is dropped; stored URLs carry no transformation segment.
        $afterUpload = preg_replace('#^v\d+/#', '', $afterUpload);
        $withoutExt = preg_replace('#\.[A-Za-z0-9]+$#', '', $afterUpload);

        return $withoutExt !== '' ? urldecode($withoutExt) : null;
    }
};

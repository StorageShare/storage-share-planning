<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('end_checklist_item_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('end_checklist_item_id');
            $table->string('file_path');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->foreign('end_checklist_item_id')
                ->references('id')
                ->on('end_checklist_items')
                ->onDelete('cascade');
        });

        // Backfill existing single photo_path values into the new photos table
        // Keep this lightweight; if the table is large, this may take some time.
        DB::table('end_checklist_items')
            ->whereNotNull('photo_path')
            ->orderBy('id')
            ->chunkById(500, function ($items) {
                $now = now();
                $toInsert = [];
                foreach ($items as $item) {
                    $toInsert[] = [
                        'end_checklist_item_id' => $item->id,
                        'file_path' => $item->photo_path,
                        'uploaded_by' => $item->uploaded_by,
                        'uploaded_at' => $item->uploaded_at ?? $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if (!empty($toInsert)) {
                    DB::table('end_checklist_item_photos')->insert($toInsert);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('end_checklist_item_photos');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $root = env('EQUIPMENT_DOCUMENTS_ROOT', storage_path('app/private/equipment-documents'));

        if (! app()->environment('testing') && is_dir($root)) {
            File::deleteDirectory($root);
        }

        Schema::dropIfExists('inspection_reference_documents');
        Schema::dropIfExists('equipment_documents');
    }

    public function down(): void
    {
        // A exclusão dos documentos e seus arquivos é intencionalmente irreversível.
    }
};

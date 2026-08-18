<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table): void {
            $table->longText('general_notes')->nullable()->change();
        });

        DB::table('inspections')
            ->select(['id', 'general_notes'])
            ->whereNotNull('general_notes')
            ->orderBy('id')
            ->chunkById(100, function ($inspections): void {
                foreach ($inspections as $inspection) {
                    $text = (string) $inspection->general_notes;
                    if (trim($text) === '' || $this->isStructuredDocument($text)) {
                        continue;
                    }

                    $lines = preg_split('/\R/u', $text) ?: [$text];
                    $content = array_map(static function (string $line): array {
                        if ($line === '') {
                            return ['type' => 'paragraph'];
                        }

                        return [
                            'type' => 'paragraph',
                            'content' => [['type' => 'text', 'text' => $line]],
                        ];
                    }, $lines);

                    DB::table('inspections')
                        ->where('id', $inspection->id)
                        ->update([
                            'general_notes' => json_encode([
                                'schema_version' => 1,
                                'document' => ['type' => 'doc', 'content' => $content],
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                        ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('inspections')
            ->select(['id', 'general_notes'])
            ->whereNotNull('general_notes')
            ->orderBy('id')
            ->chunkById(100, function ($inspections): void {
                foreach ($inspections as $inspection) {
                    $decoded = json_decode((string) $inspection->general_notes, true);
                    if (! is_array($decoded) || ! is_array($decoded['document'] ?? null)) {
                        continue;
                    }

                    DB::table('inspections')
                        ->where('id', $inspection->id)
                        ->update(['general_notes' => $this->plainText($decoded['document'])]);
                }
            });

        Schema::table('inspections', function (Blueprint $table): void {
            $table->text('general_notes')->nullable()->change();
        });
    }

    private function isStructuredDocument(string $value): bool
    {
        $decoded = json_decode($value, true);

        return is_array($decoded)
            && ($decoded['schema_version'] ?? null) === 1
            && is_array($decoded['document'] ?? null);
    }

    /** @param array<string, mixed> $document */
    private function plainText(array $document): string
    {
        $blocks = [];
        foreach (($document['content'] ?? []) as $node) {
            if (is_array($node)) {
                $blocks[] = $this->nodeText($node);
            }
        }

        return trim(implode("\n", $blocks));
    }

    /** @param array<string, mixed> $node */
    private function nodeText(array $node): string
    {
        if (($node['type'] ?? null) === 'text') {
            return (string) ($node['text'] ?? '');
        }
        if (($node['type'] ?? null) === 'hardBreak') {
            return "\n";
        }

        $parts = [];
        foreach (($node['content'] ?? []) as $child) {
            if (is_array($child)) {
                $parts[] = $this->nodeText($child);
            }
        }

        return implode('', $parts);
    }
};

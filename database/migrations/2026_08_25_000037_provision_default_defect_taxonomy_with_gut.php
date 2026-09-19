<?php

declare(strict_types=1);

use App\Enums\GutCriterion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var array<string, array{name:string,description:string,position:int,classifications:list<array{code:string,name:string,color:string,position:int,lower_limit:int,upper_limit:int,description:string}>> */
    private const CATEGORIES = [
        'CV' => [
            'name' => 'CIVIL',
            'description' => 'Avarias relacionadas aos elementos civis.',
            'position' => 1,
            'classifications' => [
                ['code' => 'CV-1', 'name' => 'Grave', 'color' => '#FF0000', 'position' => 1, 'lower_limit' => 75, 'upper_limit' => 125, 'description' => 'Tratar em até 1 ano'],
                ['code' => 'CV-2', 'name' => 'Alta', 'color' => '#FFC000', 'position' => 2, 'lower_limit' => 36, 'upper_limit' => 74, 'description' => 'Tratar em até 2 anos'],
                ['code' => 'CV-3', 'name' => 'Média', 'color' => '#FFFF00', 'position' => 3, 'lower_limit' => 16, 'upper_limit' => 35, 'description' => 'Tratar em até 3 anos'],
                ['code' => 'CV-4', 'name' => 'Baixa', 'color' => '#92D050', 'position' => 4, 'lower_limit' => 8, 'upper_limit' => 15, 'description' => 'Intervenção por oportunidade'],
                ['code' => 'CV-5', 'name' => 'Muito baixa', 'color' => '#0070C0', 'position' => 5, 'lower_limit' => 1, 'upper_limit' => 7, 'description' => 'Registro de condição'],
            ],
        ],
        'TAC' => [
            'name' => 'TAC',
            'description' => 'Avarias relacionadas ao tratamento anticorrosivo.',
            'position' => 2,
            'classifications' => [
                ['code' => 'TA-1', 'name' => 'Grave', 'color' => '#FF0000', 'position' => 1, 'lower_limit' => 45, 'upper_limit' => 75, 'description' => 'Tratar em até 1 ano'],
                ['code' => 'TA-2', 'name' => 'Alta', 'color' => '#FFC000', 'position' => 2, 'lower_limit' => 25, 'upper_limit' => 44, 'description' => 'Tratar em até 3 anos'],
                ['code' => 'TA-3', 'name' => 'Média', 'color' => '#FFFF00', 'position' => 3, 'lower_limit' => 15, 'upper_limit' => 24, 'description' => 'Tratar em até 5 anos'],
            ],
        ],
        'REC' => [
            'name' => 'REC',
            'description' => 'Avarias relacionadas à recuperação estrutural.',
            'position' => 3,
            'classifications' => [
                ['code' => 'IE-1', 'name' => 'Grave', 'color' => '#FF0000', 'position' => 1, 'lower_limit' => 75, 'upper_limit' => 125, 'description' => 'Tratar em até 1 ano'],
                ['code' => 'IE-2', 'name' => 'Alta', 'color' => '#FFC000', 'position' => 2, 'lower_limit' => 36, 'upper_limit' => 74, 'description' => 'Tratar em até 2 anos'],
                ['code' => 'IE-3', 'name' => 'Média', 'color' => '#FFFF00', 'position' => 3, 'lower_limit' => 16, 'upper_limit' => 35, 'description' => 'Tratar em até 3 anos'],
                ['code' => 'IE-4', 'name' => 'Baixa', 'color' => '#92D050', 'position' => 4, 'lower_limit' => 8, 'upper_limit' => 15, 'description' => 'Intervenção por oportunidade'],
                ['code' => 'IE-5', 'name' => 'Muito baixa', 'color' => '#0070C0', 'position' => 5, 'lower_limit' => 1, 'upper_limit' => 7, 'description' => 'Registro de condição'],
            ],
        ],
    ];

    /** @var array<int, string> */
    private const GUT_COLORS = [
        1 => '#00AEEF',
        2 => '#92D050',
        3 => '#FFFF00',
        4 => '#FFC000',
        5 => '#FF0000',
    ];

    public function up(): void
    {
        DB::table('organizations')->orderBy('id')->eachById(function (object $organization): void {
            foreach (self::CATEGORIES as $code => $definition) {
                $key = ['organization_id' => $organization->id, 'code' => $code];
                if (! DB::table('defect_categories')->where($key)->exists()) {
                    DB::table('defect_categories')->insert($key + [
                        'public_id' => (string) Str::ulid(),
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                        'position' => $definition['position'],
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $categoryId = DB::table('defect_categories')->where($key)->value('id');

                foreach ($definition['classifications'] as $classification) {
                    $key = ['organization_id' => $organization->id, 'defect_category_id' => $categoryId, 'code' => $classification['code']];
                    $existing = DB::table('defect_classifications')->where($key)->first();
                    if ($existing === null) {
                        DB::table('defect_classifications')->insert($key + $classification + [
                            'public_id' => (string) Str::ulid(),
                            'severity_rank' => $classification['position'],
                            'status' => 'active',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        $missing = [];
                        foreach (['name', 'description', 'color', 'lower_limit', 'upper_limit'] as $field) {
                            if ($existing->$field === null || ($field === 'name' && $existing->name === $existing->code)) {
                                $missing[$field] = $classification[$field];
                            }
                        }
                        if ($existing->severity_rank === null) {
                            $missing['severity_rank'] = $classification['position'];
                        }
                        if ($missing !== []) {
                            DB::table('defect_classifications')->where($key)->update($missing + ['updated_at' => now()]);
                        }
                    }
                }

                foreach (GutCriterion::cases() as $criterion) {
                    foreach (self::GUT_COLORS as $score => $color) {
                        $key = ['organization_id' => $organization->id, 'defect_category_id' => $categoryId, 'criterion' => $criterion->value, 'score' => $score];
                        if (! DB::table('defect_category_gut_options')->where($key)->exists()) {
                            DB::table('defect_category_gut_options')->insert($key + [
                                'color' => $color, 'created_at' => now(), 'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // Historical defaults are preserved on rollback.
    }
};

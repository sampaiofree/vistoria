<?php

declare(strict_types=1);

namespace App\Services\Equipments;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EquipmentCsv
{
    /** @var array<string, string> */
    public const FIELDS = [
        'maintenance_plan_code' => 'Plano de manutenção',
        'maintenance_item_code' => 'Item manutenção',
        'tag' => 'Campo de ordenação (TAG)',
        'description' => 'Descrição item de manutenção',
        'installation_location' => 'Local de instalação',
        'area_code' => 'Area(usina)',
        'area_name' => 'Area.nome',
        'subarea_code' => 'Sub-area',
        'subarea_name' => 'sub-area.nome',
        'name' => 'Denominação do loc.instalação',
        'task_list_group' => 'GrpLisTar.',
        'task_list_group_counter' => 'Numerador de grupos',
        'abc_code' => 'Código ABC',
        'defect_code_prefix' => 'Prefixo de avaria',
    ];

    /** @var array<int, string> */
    public const REQUIRED_FIELDS = [
        'maintenance_item_code',
        'tag',
        'name',
        'defect_code_prefix',
    ];

    /** @return array{columns: array<int, array{key:string, label:string}>, rows: array<int, array{line:int, values:array<string, string>}>, mapping:array<string, ?string>} */
    public function read(UploadedFile $file): array
    {
        $handle = fopen((string) $file->getRealPath(), 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Não foi possível ler o arquivo CSV.']);
        }

        try {
            $firstLine = (string) fgets($handle);
            $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
            rewind($handle);

            $headers = fgetcsv($handle, 0, $delimiter);

            if (! is_array($headers) || count($headers) === 0) {
                throw ValidationException::withMessages(['file' => 'O CSV não possui um cabeçalho válido.']);
            }

            $columns = [];
            foreach ($headers as $index => $header) {
                $label = trim((string) $header);
                if ($index === 0) {
                    $label = (string) preg_replace('/^\xEF\xBB\xBF/', '', $label);
                }

                if ($label === '') {
                    $label = 'Coluna '.($index + 1);
                }

                $columns[] = ['key' => 'column_'.$index, 'label' => $label];
            }

            $rows = [];
            $line = 1;
            while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
                $line++;

                if ($values === [null] || $values === []) {
                    continue;
                }

                if (count($values) > count($columns)) {
                    throw ValidationException::withMessages(['file' => "A linha {$line} possui mais colunas que o cabeçalho."]);
                }

                $row = [];
                foreach ($columns as $index => $column) {
                    $row[$column['key']] = isset($values[$index]) ? (string) $values[$index] : '';
                }

                if (collect($row)->every(fn (string $value): bool => trim($value) === '')) {
                    continue;
                }

                $rows[] = ['line' => $line, 'values' => $row];
                if (count($rows) > 5000) {
                    throw ValidationException::withMessages(['file' => 'O CSV pode conter no máximo 5.000 registros.']);
                }
            }
        } finally {
            fclose($handle);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'O CSV não possui registros para importar.']);
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'mapping' => $this->suggestMapping($columns),
        ];
    }

    /** @param array<int, array{key:string, label:string}> $columns
     * @return array<string, ?string>
     */
    public function suggestMapping(array $columns): array
    {
        $byHeader = [];
        foreach ($columns as $column) {
            $byHeader[$this->normalizeHeader($column['label'])] = $column['key'];
        }

        $mapping = [];
        foreach (self::FIELDS as $field => $label) {
            $mapping[$field] = $byHeader[$this->normalizeHeader($label)] ?? null;
        }

        return $mapping;
    }

    /** @param array<int, array{key:string, label:string}> $columns
     * @param  array<string, mixed>  $mapping
     * @return array<string, ?string>
     */
    public function validateMapping(array $columns, array $mapping): array
    {
        $keys = collect($columns)->pluck('key')->all();
        $validated = [];

        foreach (self::FIELDS as $field => $_label) {
            $selected = $mapping[$field] ?? null;
            $validated[$field] = is_string($selected) && in_array($selected, $keys, true) ? $selected : null;
        }

        $missing = collect(self::REQUIRED_FIELDS)
            ->filter(fn (string $field): bool => $validated[$field] === null)
            ->mapWithKeys(fn (string $field): array => ["mapping.{$field}" => 'Selecione uma coluna para '.self::FIELDS[$field].'.'])
            ->all();

        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }

        $duplicates = collect($validated)
            ->filter()
            ->countBy()
            ->filter(fn (int $count): bool => $count > 1);

        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages(['mapping' => 'Uma mesma coluna do CSV não pode preencher mais de um campo.']);
        }

        return $validated;
    }

    /** @param array<string, ?string> $mapping
     * @param  array<string, string>  $values
     * @return array<string, ?string>
     */
    public function mapRow(array $mapping, array $values): array
    {
        $data = [];
        foreach (self::FIELDS as $field => $_label) {
            $source = $mapping[$field] ?? null;
            $data[$field] = $source === null ? null : ($values[$source] ?? null);
        }

        return $data;
    }

    private function normalizeHeader(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '');
    }
}

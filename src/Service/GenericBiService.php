<?php

namespace App\Service;

use App\Entity\UploadedFile;

class GenericBiService
{
    public function analyze(UploadedFile $uploadedFile): array
    {
        $rows = array_values(array_filter(array_map(fn ($row) => $row->getRawData(), $uploadedFile->getRows()->toArray())));
        $columns = $this->profileColumns($rows);
        $dateColumns = $this->columnsByType($columns, 'date');
        $numberColumns = $this->columnsByType($columns, 'number');
        $categoryColumns = $this->columnsByType($columns, 'category');
        $booleanColumns = $this->columnsByType($columns, 'boolean');
        $textColumns = $this->columnsByType($columns, 'text');

        $charts = [];
        $recommendations = [];

        foreach (array_slice($categoryColumns, 0, 6) as $category) {
            $key = 'distribution_' . $this->slug($category);
            $charts[$key] = [
                'title' => 'Distribucion por ' . $category,
                'type' => 'bar',
                'dimension' => $category,
                'metric' => 'conteo',
                'data' => $this->top($this->groupCount($rows, $category), 12),
            ];
            $recommendations[] = 'Distribucion por ' . $category;
        }

        foreach (array_slice($numberColumns, 0, 5) as $number) {
            $charts['summary_' . $this->slug($number)] = [
                'title' => 'Resumen de ' . $number,
                'type' => 'metric',
                'dimension' => null,
                'metric' => $number,
                'data' => $this->numberSummary($rows, $number),
            ];
        }

        foreach (array_slice($categoryColumns, 0, 4) as $category) {
            foreach (array_slice($numberColumns, 0, 3) as $number) {
                $key = 'sum_' . $this->slug($number) . '_by_' . $this->slug($category);
                $charts[$key] = [
                    'title' => $number . ' por ' . $category,
                    'type' => 'bar',
                    'dimension' => $category,
                    'metric' => $number,
                    'data' => $this->top($this->groupSum($rows, $category, $number), 12),
                ];
                $recommendations[] = $number . ' por ' . $category;
            }
        }

        foreach (array_slice($dateColumns, 0, 3) as $date) {
            $charts['rows_by_month_' . $this->slug($date)] = [
                'title' => 'Registros por mes: ' . $date,
                'type' => 'line',
                'dimension' => $date,
                'metric' => 'conteo',
                'data' => $this->groupByMonthCount($rows, $date),
            ];

            foreach (array_slice($numberColumns, 0, 3) as $number) {
                $charts['sum_' . $this->slug($number) . '_by_month_' . $this->slug($date)] = [
                    'title' => $number . ' por mes',
                    'type' => 'line',
                    'dimension' => $date,
                    'metric' => $number,
                    'data' => $this->groupByMonthSum($rows, $date, $number),
                ];
            }
        }

        return [
            'row_count' => count($rows),
            'column_count' => count($columns),
            'columns' => $columns,
            'types' => [
                'date' => $dateColumns,
                'number' => $numberColumns,
                'category' => $categoryColumns,
                'boolean' => $booleanColumns,
                'text' => $textColumns,
            ],
            'charts' => $charts,
            'recommendations' => array_values(array_unique(array_slice($recommendations, 0, 12))),
        ];
    }

    private function profileColumns(array $rows): array
    {
        $headers = [];
        foreach ($rows as $row) {
            $headers = array_values(array_unique([...$headers, ...array_keys($row)]));
        }

        $profiles = [];
        foreach ($headers as $header) {
            $values = array_values(array_filter(array_map(fn ($row) => $row[$header] ?? null, $rows), fn ($value) => $value !== null && $value !== ''));
            $unique = count(array_unique(array_map(fn ($value) => mb_strtolower((string) $value), $values)));
            $profiles[$header] = [
                'name' => $header,
                'type' => $this->detectType($values, count($rows), $unique),
                'filled' => count($values),
                'fill_rate' => count($rows) > 0 ? round((count($values) / count($rows)) * 100, 1) : 0,
                'unique' => $unique,
                'examples' => array_slice(array_values(array_unique(array_map(fn ($value) => (string) $value, $values))), 0, 5),
            ];
        }

        return $profiles;
    }

    private function detectType(array $values, int $rowCount, int $unique): string
    {
        if ($values === []) {
            return 'empty';
        }

        $sample = array_slice($values, 0, 50);
        $numbers = count(array_filter($sample, fn ($value) => $this->toNumber($value) !== null));
        $dates = count(array_filter($sample, fn ($value) => $this->toDate($value) !== null));
        $booleans = count(array_filter($sample, fn ($value) => in_array(mb_strtolower(trim((string) $value)), ['si', 'sí', 'no', 'true', 'false', '1', '0'], true)));
        $longText = count(array_filter($sample, fn ($value) => mb_strlen((string) $value) > 80));
        $sampleCount = max(1, count($sample));

        return match (true) {
            $dates / $sampleCount >= .75 => 'date',
            $numbers / $sampleCount >= .85 => 'number',
            $booleans / $sampleCount >= .85 => 'boolean',
            $longText / $sampleCount >= .35 => 'text',
            $unique > max(25, $rowCount * .65) => 'identifier',
            default => 'category',
        };
    }

    private function columnsByType(array $columns, string $type): array
    {
        return array_keys(array_filter($columns, fn ($column) => $column['type'] === $type));
    }

    private function groupCount(array $rows, string $column): array
    {
        $result = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row[$column] ?? ''));
            $key = $key === '' ? 'Sin valor' : $key;
            $result[$key] = ($result[$key] ?? 0) + 1;
        }
        arsort($result);

        return $result;
    }

    private function groupSum(array $rows, string $dimension, string $metric): array
    {
        $result = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row[$dimension] ?? ''));
            $key = $key === '' ? 'Sin valor' : $key;
            $result[$key] = ($result[$key] ?? 0) + ($this->toNumber($row[$metric] ?? null) ?? 0);
        }
        arsort($result);

        return array_map(fn ($value) => round($value, 2), $result);
    }

    private function groupByMonthCount(array $rows, string $dateColumn): array
    {
        $result = [];
        foreach ($rows as $row) {
            $date = $this->toDate($row[$dateColumn] ?? null);
            if (!$date) {
                continue;
            }
            $key = $date->format('Y-m');
            $result[$key] = ($result[$key] ?? 0) + 1;
        }
        ksort($result);

        return $result;
    }

    private function groupByMonthSum(array $rows, string $dateColumn, string $metric): array
    {
        $result = [];
        foreach ($rows as $row) {
            $date = $this->toDate($row[$dateColumn] ?? null);
            if (!$date) {
                continue;
            }
            $key = $date->format('Y-m');
            $result[$key] = ($result[$key] ?? 0) + ($this->toNumber($row[$metric] ?? null) ?? 0);
        }
        ksort($result);

        return array_map(fn ($value) => round($value, 2), $result);
    }

    private function numberSummary(array $rows, string $column): array
    {
        $values = array_values(array_filter(array_map(fn ($row) => $this->toNumber($row[$column] ?? null), $rows), fn ($value) => $value !== null));
        sort($values);
        $count = count($values);

        return [
            'count' => $count,
            'sum' => round(array_sum($values), 2),
            'avg' => $count > 0 ? round(array_sum($values) / $count, 2) : 0,
            'min' => $count > 0 ? round($values[0], 2) : 0,
            'max' => $count > 0 ? round($values[$count - 1], 2) : 0,
        ];
    }

    private function top(array $data, int $limit): array
    {
        return array_slice($data, 0, $limit, true);
    }

    private function toNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $clean = str_replace(',', '.', preg_replace('/[^\d,.-]/', '', (string) $value));

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function toDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }
        if (is_numeric($value) && (float) $value > 25000 && (float) $value < 90000) {
            return \DateTimeImmutable::createFromMutable(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value));
        }
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'm/d/Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, trim((string) $value));
            if ($date instanceof \DateTimeImmutable) {
                return $date;
            }
        }
        $timestamp = strtotime((string) $value);

        return $timestamp ? (new \DateTimeImmutable())->setTimestamp($timestamp) : null;
    }

    private function slug(string $value): string
    {
        $value = mb_strtolower($value);
        $value = strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        return trim(preg_replace('/[^a-z0-9]+/', '_', $value) ?? 'chart', '_') ?: 'chart';
    }
}


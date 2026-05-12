<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile as HttpUploadedFile;

class FileParserService
{
    public function parse(HttpUploadedFile|string $file): array
    {
        $path = $file instanceof HttpUploadedFile ? $file->getRealPath() : $file;
        $extension = strtolower($file instanceof HttpUploadedFile ? $file->getClientOriginalExtension() : pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => $this->parseCsv($path),
            'json' => $this->parseJson($path),
            'xlsx', 'xls' => $this->parseSpreadsheet($path),
            default => throw new \InvalidArgumentException('Formato no soportado. Usa XLSX, XLS, CSV o JSON.'),
        };
    }

    private function parseSpreadsheet(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $matrix = $sheet->toArray(null, true, true, true);
        return $this->matrixToDataset($matrix);
    }

    private function parseCsv(string $path): array
    {
        $delimiter = $this->detectDelimiter($path);
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new \RuntimeException('No se pudo leer el CSV.');
        }

        $matrix = [];
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $row = $delimiter === ';' ? $row : str_getcsv(implode(';', $row), $delimiter);
            $matrix[] = $row;
        }
        fclose($handle);

        return $this->matrixToDataset($matrix);
    }

    private function parseJson(string $path): array
    {
        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $rows = array_is_list($payload) ? $payload : ($payload['rows'] ?? $payload['data'] ?? $payload['items'] ?? []);
        $rows = array_map(fn (array $row) => $this->flattenRow($row), $rows);
        $headers = $this->uniqueHeaders(array_merge(...array_map('array_keys', $rows ?: [[]])));

        return $this->buildResult($headers, $rows);
    }

    private function matrixToDataset(array $matrix): array
    {
        $matrix = array_values(array_filter($matrix, fn (array $row) => count(array_filter($row, fn ($v) => $v !== null && $v !== '')) > 0));
        $headers = $this->uniqueHeaders(array_map(fn ($v) => trim($this->removeBom((string) $v)), array_values($matrix[0] ?? [])));
        $rows = [];

        foreach (array_slice($matrix, 1) as $row) {
            $values = array_values($row);
            $assoc = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = $values[$index] ?? null;
            }
            if ($assoc !== []) {
                $rows[] = $assoc;
            }
        }

        return $this->buildResult($headers, $rows);
    }

    private function detectDelimiter(string $path): string
    {
        $sample = (string) file_get_contents($path, false, null, 0, 4096);
        $candidates = [';' => substr_count($sample, ';'), ',' => substr_count($sample, ','), "\t" => substr_count($sample, "\t")];
        arsort($candidates);

        return array_key_first($candidates) ?: ';';
    }

    private function uniqueHeaders(array $headers): array
    {
        $seen = [];
        $result = [];

        foreach ($headers as $header) {
            $header = trim((string) $header);
            if ($header === '') {
                continue;
            }

            $base = $header;
            $suffix = 2;
            while (isset($seen[mb_strtolower($header)])) {
                $header = $base . ' ' . $suffix++;
            }

            $seen[mb_strtolower($header)] = true;
            $result[] = $header;
        }

        return $result;
    }

    private function flattenRow(array $row, string $prefix = ''): array
    {
        $flat = [];
        foreach ($row as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value) && !array_is_list($value)) {
                $flat += $this->flattenRow($value, $name);
                continue;
            }
            $flat[$name] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
        }

        return $flat;
    }

    private function removeBom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }

    private function buildResult(array $headers, array $rows): array
    {
        $examples = [];
        foreach ($headers as $header) {
            if ($header === '') {
                continue;
            }
            $examples[$header] = array_values(array_filter(array_slice(array_column($rows, $header), 0, 5), fn ($v) => $v !== null && $v !== ''));
        }

        return [
            'headers' => array_values(array_filter($headers)),
            'rows' => $rows,
            'examples' => $examples,
        ];
    }
}

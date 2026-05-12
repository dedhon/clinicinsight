<?php

namespace App\Service;

use App\Entity\DatasetColumn;
use App\Entity\UploadedFile;
use Doctrine\ORM\EntityManagerInterface;

class ColumnDetectionService
{
    public const ALLOWED_FIELDS = [
        'visit_date',
        'professional_name',
        'specialty',
        'center',
        'status',
        'amount',
        'insurance',
        'duration_minutes',
        'patient_age',
        'patient_gender',
        'patient_id_anonymous',
    ];

    private const SENSITIVE_PATTERNS = [
        'nombre', 'name', 'dni', 'nif', 'nie', 'telefono', 'phone', 'email',
        'direccion', 'address', 'cip', 'historia', 'medical_record',
        'diagnostico', 'diagnosis', 'tratamiento', 'treatment', 'notas', 'notes',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OpenAiMappingService $openAiMappingService,
    ) {}

    public function detectAndPersist(UploadedFile $uploadedFile, array $headers, array $examples): void
    {
        $localColumns = [];
        $safeColumnsForAi = [];

        foreach ($headers as $header) {
            $isSensitive = $this->isSensitive($header);
            $localColumns[$header] = [
                'ignored' => $isSensitive,
                'reason' => $isSensitive ? 'Detectada como columna sensible por reglas locales.' : null,
            ];

            if (!$isSensitive) {
                // Privacy guardrail: only headers and short examples are sent to OpenAI, never full rows.
                $safeColumnsForAi[$header] = array_map(fn ($v) => mb_substr((string) $v, 0, 60), array_slice($examples[$header] ?? [], 0, 3));
            }
        }

        $aiMapping = $this->openAiMappingService->mapColumns(array_keys($safeColumnsForAi), $safeColumnsForAi);
        $byOriginal = [];
        foreach ($aiMapping['columns'] ?? [] as $column) {
            $byOriginal[$column['original_name'] ?? ''] = $column;
        }

        foreach ($headers as $header) {
            $ai = $byOriginal[$header] ?? [];
            $ignored = $localColumns[$header]['ignored'] || (bool) ($ai['ignored'] ?? false);
            $mappedField = $ignored ? null : ($ai['mapped_field'] ?? null);
            if ($mappedField !== null && !in_array($mappedField, self::ALLOWED_FIELDS, true)) {
                $mappedField = null;
            }

            $datasetColumn = (new DatasetColumn())
                ->setUploadedFile($uploadedFile)
                ->setOriginalName($header)
                ->setDetectedType($this->detectType($examples[$header] ?? []))
                ->setMappedField($mappedField)
                ->setConfidence(isset($ai['confidence']) ? (float) $ai['confidence'] : null)
                ->setIgnored($ignored)
                ->setReason($localColumns[$header]['reason'] ?? ($ai['reason'] ?? null))
                ->setExampleValues(array_slice($examples[$header] ?? [], 0, 5));

            $this->em->persist($datasetColumn);
        }

        $this->em->flush();
    }

    public function isSensitive(string $header): bool
    {
        $normalized = mb_strtolower(strtr($header, ['_' => ' ', '-' => ' ']));
        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function detectType(array $examples): ?string
    {
        $values = array_values(array_filter($examples, fn ($v) => $v !== null && $v !== ''));
        if ($values === []) {
            return null;
        }

        $first = (string) $values[0];
        if (is_numeric(str_replace(',', '.', $first))) {
            return 'number';
        }
        if (strtotime($first) !== false) {
            return 'date';
        }

        return 'string';
    }
}


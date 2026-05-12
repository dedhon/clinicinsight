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
        'paciente', 'patient name', 'nombre paciente', 'apellido', 'surname',
        'dni', 'nif', 'nie', 'telefono', 'phone', 'email', 'e-mail',
        'direccion', 'address', 'cip', 'historia', 'medical_record',
        'diagnostico', 'diagnosis', 'tratamiento', 'treatment', 'notas', 'notes',
    ];

    private const LOCAL_FIELD_PATTERNS = [
        'visit_date' => ['fecha visita', 'fecha cita', 'fecha', 'date', 'visit date', 'appointment date', 'dia'],
        'professional_name' => ['profesional', 'doctor', 'doctora', 'medico', 'medica', 'facultativo', 'terapeuta', 'dentista'],
        'specialty' => ['especialidad', 'specialty', 'servicio', 'unidad'],
        'center' => ['centro', 'clinica', 'sede', 'center', 'location'],
        'status' => ['estado', 'status', 'situacion', 'asistencia'],
        'amount' => ['importe', 'facturacion', 'precio', 'total', 'amount', 'revenue', 'fee', 'coste'],
        'insurance' => ['aseguradora', 'mutua', 'seguro', 'insurance', 'payer'],
        'duration_minutes' => ['duracion', 'minutos', 'duration', 'minutes'],
        'patient_age' => ['edad', 'age'],
        'patient_gender' => ['genero', 'sexo', 'gender', 'sex'],
        'patient_id_anonymous' => ['id paciente', 'patient id', 'codigo paciente', 'identificador paciente'],
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
            $localField = $isSensitive ? null : $this->suggestLocalField($header);
            $localColumns[$header] = [
                'ignored' => $isSensitive,
                'mapped_field' => $localField,
                'confidence' => $localField ? 0.82 : null,
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
            $mappedField = $ignored ? null : ($ai['mapped_field'] ?? $localColumns[$header]['mapped_field'] ?? null);
            if ($mappedField !== null && !in_array($mappedField, self::ALLOWED_FIELDS, true)) {
                $mappedField = null;
            }

            $datasetColumn = (new DatasetColumn())
                ->setUploadedFile($uploadedFile)
                ->setOriginalName($header)
                ->setDetectedType($this->detectType($examples[$header] ?? []))
                ->setMappedField($mappedField)
                ->setConfidence(isset($ai['confidence']) ? (float) $ai['confidence'] : $localColumns[$header]['confidence'])
                ->setIgnored($ignored)
                ->setReason($localColumns[$header]['reason'] ?? ($ai['reason'] ?? ($mappedField ? 'Mapeada por reglas locales.' : null)))
                ->setExampleValues($ignored ? [] : array_slice($examples[$header] ?? [], 0, 5));

            $this->em->persist($datasetColumn);
        }

        $this->em->flush();
    }

    public function isSensitive(string $header): bool
    {
        $normalized = $this->normalizeHeader($header);
        if (str_contains($normalized, 'profesional') || str_contains($normalized, 'doctor') || str_contains($normalized, 'medico')) {
            return false;
        }

        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function suggestLocalField(string $header): ?string
    {
        $normalized = $this->normalizeHeader($header);
        foreach (self::LOCAL_FIELD_PATTERNS as $field => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($normalized, $pattern)) {
                    return $field;
                }
            }
        }

        return null;
    }

    private function normalizeHeader(string $header): string
    {
        $value = mb_strtolower(strtr($header, ['_' => ' ', '-' => ' ', '.' => ' ']));
        $value = strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        return preg_replace('/\s+/', ' ', trim($value)) ?? $value;
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

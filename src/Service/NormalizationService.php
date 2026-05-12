<?php

namespace App\Service;

use App\Entity\UploadedFile;
use Doctrine\ORM\EntityManagerInterface;

class NormalizationService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function normalize(UploadedFile $uploadedFile, array $mapping): void
    {
        foreach ($uploadedFile->getRows() as $row) {
            $normalized = [];
            foreach ($mapping as $original => $config) {
                if (($config['ignored'] ?? false) || empty($config['mapped_field'])) {
                    continue;
                }

                $field = $config['mapped_field'];
                $value = $row->getRawData()[$original] ?? null;
                $normalized[$field] = $this->normalizeValue($field, $value);
            }

            $row->setNormalizedData(array_filter($normalized, fn ($v) => $v !== null && $v !== ''));
        }

        $uploadedFile->setStatus(UploadedFile::STATUS_MAPPED);
        $this->em->flush();
    }

    private function normalizeValue(string $field, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field) {
            'visit_date' => $this->normalizeDate($value),
            'amount' => (float) str_replace(',', '.', preg_replace('/[^\d,.-]/', '', (string) $value)),
            'duration_minutes', 'patient_age' => (int) $value,
            'status' => $this->normalizeStatus((string) $value),
            'patient_id_anonymous' => hash('sha256', 'clinicinsight:' . (string) $value),
            default => trim((string) $value),
        };
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value) && (float) $value > 25000 && (float) $value < 90000) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $text = trim((string) $value);
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'm/d/Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $text);
            if ($date instanceof \DateTimeImmutable) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($text);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private function normalizeStatus(string $value): string
    {
        $value = mb_strtolower(trim($value));
        return match (true) {
            str_contains($value, 'cancel') => 'cancelada',
            str_contains($value, 'no') && (str_contains($value, 'show') || str_contains($value, 'present')) => 'no_presentado',
            str_contains($value, 'pend') => 'pendiente',
            default => 'realizada',
        };
    }
}

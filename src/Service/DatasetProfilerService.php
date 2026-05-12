<?php

namespace App\Service;

use App\Entity\UploadedFile;

class DatasetProfilerService
{
    public function profile(UploadedFile $uploadedFile): array
    {
        $columns = $uploadedFile->getColumns()->toArray();
        $mappedColumns = array_values(array_filter($columns, fn ($column) => !$column->isIgnored() && $column->getMappedField()));
        $sensitiveColumns = array_values(array_filter($columns, fn ($column) => $column->isIgnored()));
        $fields = array_values(array_unique(array_filter(array_map(fn ($column) => $column->getMappedField(), $mappedColumns))));

        $score = 0;
        $signals = [];
        $warnings = [];

        $this->addSignal($fields, 'visit_date', 25, 'Tiene una columna de fecha de visita/cita.', $score, $signals);
        $this->addSignal($fields, 'amount', 20, 'Tiene una metrica economica para facturacion.', $score, $signals);
        $this->addSignal($fields, 'status', 15, 'Tiene estados de cita para cancelaciones/no show.', $score, $signals);
        $this->addSignal($fields, 'professional_name', 15, 'Permite comparar profesionales.', $score, $signals);
        $this->addSignal($fields, 'specialty', 10, 'Permite analizar especialidades.', $score, $signals);
        $this->addSignal($fields, 'insurance', 10, 'Permite analizar aseguradoras.', $score, $signals);
        $this->addSignal($fields, 'center', 5, 'Permite separar por centro o sede.', $score, $signals);

        if ($uploadedFile->getRowCount() < 2) {
            $warnings[] = 'El archivo tiene muy pocas filas para generar estadisticas fiables.';
        }
        if (!in_array('visit_date', $fields, true)) {
            $warnings[] = 'No se detecto fecha: los graficos temporales pueden quedar vacios.';
        }
        if (!in_array('amount', $fields, true)) {
            $warnings[] = 'No se detecto importe: los KPIs de facturacion seran 0.';
        }
        if (count($mappedColumns) < 2) {
            $warnings[] = 'Pocas columnas compatibles. Puede que el Excel no sea de actividad clinica/gestion.';
        }
        if (count($sensitiveColumns) > 0) {
            $warnings[] = count($sensitiveColumns) . ' columnas sensibles seran ignoradas antes del analisis.';
        }

        $type = $this->guessDatasetType($fields);

        return [
            'score' => min(100, $score),
            'level' => $this->level($score),
            'type' => $type,
            'mapped_count' => count($mappedColumns),
            'ignored_count' => count($sensitiveColumns),
            'total_columns' => count($columns),
            'signals' => $signals,
            'warnings' => $warnings,
            'preview_rows' => array_slice(array_map(fn ($row) => $row->getRawData(), $uploadedFile->getRows()->toArray()), 0, 8),
        ];
    }

    private function addSignal(array $fields, string $field, int $points, string $message, int &$score, array &$signals): void
    {
        if (!in_array($field, $fields, true)) {
            return;
        }

        $score += $points;
        $signals[] = $message;
    }

    private function guessDatasetType(array $fields): string
    {
        if (in_array('amount', $fields, true) && in_array('insurance', $fields, true)) {
            return 'Facturacion y aseguradoras';
        }
        if (in_array('status', $fields, true) && in_array('visit_date', $fields, true)) {
            return 'Agenda y asistencia';
        }
        if (in_array('professional_name', $fields, true)) {
            return 'Rendimiento por profesional';
        }

        return 'Dataset desconocido';
    }

    private function level(int $score): string
    {
        return match (true) {
            $score >= 75 => 'Alta compatibilidad',
            $score >= 45 => 'Compatibilidad parcial',
            default => 'Baja compatibilidad',
        };
    }
}


<?php

namespace App\Service;

use App\Entity\UploadedFile;

class AnalyticsService
{
    public function analyze(UploadedFile $uploadedFile): array
    {
        $rows = array_values(array_filter(array_map(fn ($r) => $r->getNormalizedData(), $uploadedFile->getRows()->toArray())));
        $totalVisits = count($rows);
        $completed = $this->countStatus($rows, 'realizada');
        $cancelled = $this->countStatus($rows, 'cancelada');
        $noShow = $this->countStatus($rows, 'no_presentado');
        $revenue = array_sum(array_map(fn ($r) => (float) ($r['amount'] ?? 0), $rows));
        $completedRate = $totalVisits > 0 ? round(($completed / $totalVisits) * 100, 1) : 0;
        $cancellationRate = $totalVisits > 0 ? round((($cancelled + $noShow) / $totalVisits) * 100, 1) : 0;

        $charts = [
            'visits_by_month' => $this->groupCount($rows, fn ($r) => substr((string) ($r['visit_date'] ?? 'Sin fecha'), 0, 7)),
            'revenue_by_month' => $this->groupSum($rows, fn ($r) => substr((string) ($r['visit_date'] ?? 'Sin fecha'), 0, 7), 'amount'),
            'visits_by_professional' => $this->groupCount($rows, fn ($r) => $r['professional_name'] ?? 'Sin profesional'),
            'revenue_by_professional' => $this->groupSum($rows, fn ($r) => $r['professional_name'] ?? 'Sin profesional', 'amount'),
            'cancellations_by_weekday' => $this->cancellationsByWeekday($rows),
            'visits_by_status' => $this->groupCount($rows, fn ($r) => $r['status'] ?? 'sin_estado'),
            'visits_by_specialty' => $this->groupCount($rows, fn ($r) => $r['specialty'] ?? 'Sin especialidad'),
            'visits_by_insurance' => $this->groupCount($rows, fn ($r) => $r['insurance'] ?? 'Sin aseguradora'),
        ];

        return [
            'kpis' => [
                'total_visits' => $totalVisits,
                'completed_visits' => $completed,
                'cancelled_visits' => $cancelled,
                'no_show_visits' => $noShow,
                'total_revenue' => round($revenue, 2),
                'average_ticket' => $completed > 0 ? round($revenue / $completed, 2) : 0,
                'completed_rate' => $completedRate,
                'cancellation_rate' => $cancellationRate,
            ],
            'charts' => $charts,
        ];
    }

    private function countStatus(array $rows, string $status): int
    {
        return count(array_filter($rows, fn ($r) => ($r['status'] ?? null) === $status));
    }

    private function groupCount(array $rows, callable $keyBy): array
    {
        $result = [];
        foreach ($rows as $row) {
            $key = (string) $keyBy($row);
            $result[$key] = ($result[$key] ?? 0) + 1;
        }
        ksort($result);
        return $result;
    }

    private function groupSum(array $rows, callable $keyBy, string $field): array
    {
        $result = [];
        foreach ($rows as $row) {
            $key = (string) $keyBy($row);
            $result[$key] = ($result[$key] ?? 0) + (float) ($row[$field] ?? 0);
        }
        ksort($result);
        return array_map(fn ($v) => round($v, 2), $result);
    }

    private function cancellationsByWeekday(array $rows): array
    {
        $labels = ['Mon' => 0, 'Tue' => 0, 'Wed' => 0, 'Thu' => 0, 'Fri' => 0, 'Sat' => 0, 'Sun' => 0];
        foreach ($rows as $row) {
            if (($row['status'] ?? null) !== 'cancelada' || empty($row['visit_date'])) {
                continue;
            }
            $labels[date('D', strtotime($row['visit_date']))] += 1;
        }
        return $labels;
    }
}

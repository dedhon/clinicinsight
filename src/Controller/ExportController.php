<?php

namespace App\Controller;

use App\Entity\InsightReport;
use App\Entity\UploadedFile as UploadedDatasetFile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ExportController extends AbstractController
{
    #[Route('/upload/{id}/export/powerbi.csv', name: 'report_export_powerbi', methods: ['GET'])]
    public function powerBiCsv(UploadedDatasetFile $uploadedFile, EntityManagerInterface $em): Response
    {
        $report = $this->getAuthorizedReport($uploadedFile, $em);
        $lines = [];
        $lines[] = ['dataset', 'metric_group', 'metric_name', 'dimension', 'value'];

        foreach ($report->getKpis() as $name => $value) {
            $lines[] = [$uploadedFile->getOriginalName(), 'kpi', $name, 'total', $value];
        }

        foreach ($report->getCharts() as $chartName => $series) {
            foreach ($series as $dimension => $value) {
                $lines[] = [$uploadedFile->getOriginalName(), 'chart', $chartName, $dimension, $value];
            }
        }

        $csv = implode("\r\n", array_map(fn (array $row) => $this->csvLine($row), $lines));

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="clinicinsight_powerbi_' . $uploadedFile->getId() . '.csv"',
        ]);
    }

    #[Route('/upload/{id}/export/report.json', name: 'report_export_json', methods: ['GET'])]
    public function reportJson(UploadedDatasetFile $uploadedFile, EntityManagerInterface $em): JsonResponse
    {
        $report = $this->getAuthorizedReport($uploadedFile, $em);

        return $this->json([
            'project' => $uploadedFile->getProject()->getName(),
            'file' => $uploadedFile->getOriginalName(),
            'generated_at' => $report->getCreatedAt()->format(DATE_ATOM),
            'kpis' => $report->getKpis(),
            'charts' => $report->getCharts(),
            'summary' => $report->getSummary(),
        ]);
    }

    private function getAuthorizedReport(UploadedDatasetFile $uploadedFile, EntityManagerInterface $em): InsightReport
    {
        if ($uploadedFile->getProject()->getUser()->getUserIdentifier() !== $this->getUser()?->getUserIdentifier()) {
            throw $this->createAccessDeniedException();
        }

        $report = $em->getRepository(InsightReport::class)->findOneBy(
            ['uploadedFile' => $uploadedFile],
            ['createdAt' => 'DESC']
        );

        if (!$report instanceof InsightReport) {
            throw $this->createNotFoundException('Todavia no hay informe para exportar.');
        }

        return $report;
    }

    private function csvLine(array $row): string
    {
        return implode(',', array_map(fn ($value) => '"' . str_replace('"', '""', (string) $value) . '"', $row));
    }
}


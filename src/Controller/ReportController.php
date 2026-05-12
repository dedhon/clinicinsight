<?php

namespace App\Controller;

use App\Entity\InsightReport;
use App\Entity\UploadedFile as UploadedDatasetFile;
use App\Service\AnalyticsService;
use App\Service\InsightService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ReportController extends AbstractController
{
    #[Route('/upload/{id}/report', name: 'report_show', methods: ['GET'])]
    public function show(
        UploadedDatasetFile $uploadedFile,
        AnalyticsService $analyticsService,
        InsightService $insightService,
        EntityManagerInterface $em,
    ): Response {
        if ($uploadedFile->getProject()->getUser()->getUserIdentifier() !== $this->getUser()?->getUserIdentifier()) {
            throw $this->createAccessDeniedException();
        }

        $existingReport = $em->getRepository(InsightReport::class)->findOneBy(
            ['uploadedFile' => $uploadedFile],
            ['createdAt' => 'DESC']
        );

        if ($uploadedFile->getStatus() === UploadedDatasetFile::STATUS_ANALYZED && $existingReport instanceof InsightReport) {
            return $this->render('report/show.html.twig', [
                'uploadedFile' => $uploadedFile,
                'report' => $existingReport,
            ]);
        }

        $analysis = $analyticsService->analyze($uploadedFile);
        $summary = $insightService->summarize($analysis['kpis'], $analysis['charts']);

        $report = (new InsightReport())
            ->setProject($uploadedFile->getProject())
            ->setUploadedFile($uploadedFile)
            ->setKpis($analysis['kpis'])
            ->setCharts($analysis['charts'])
            ->setSummary($summary);

        $uploadedFile->setStatus(UploadedDatasetFile::STATUS_ANALYZED);
        $em->persist($report);
        $em->flush();

        return $this->render('report/show.html.twig', [
            'uploadedFile' => $uploadedFile,
            'report' => $report,
        ]);
    }
}

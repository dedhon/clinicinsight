<?php

namespace App\Controller;

use App\Entity\DatasetRow;
use App\Entity\Project;
use App\Entity\UploadedFile as UploadedDatasetFile;
use App\Service\ColumnDetectionService;
use App\Service\FileParserService;
use App\Service\PlanCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class UploadController extends AbstractController
{
    public function __construct(private readonly PlanCatalog $planCatalog) {}

    #[Route('/project/{id}/upload', name: 'project_upload', methods: ['POST'])]
    public function upload(
        Project $project,
        Request $request,
        EntityManagerInterface $em,
        FileParserService $parser,
        ColumnDetectionService $columnDetection,
        string $uploadsDir,
    ): RedirectResponse {
        if (!$this->isGranted('ROLE_SUPER_ADMIN') && $project->getUser()->getUserIdentifier() !== $this->getUser()?->getUserIdentifier()) {
            throw $this->createAccessDeniedException();
        }

        $file = $request->files->get('dataset');
        if (!$file instanceof UploadedFile) {
            $this->addFlash('danger', 'Selecciona un archivo.');
            return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
        }

        if (!$file->isValid()) {
            $this->addFlash('danger', 'No se pudo leer el archivo subido: ' . $file->getErrorMessage());
            return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
        }

        $plan = $this->planCatalog->get($project->getCustomer()->getPlan());
        $sizeMb = $file->getSize() ? $file->getSize() / 1024 / 1024 : 0;
        if ($sizeMb > $plan['max_upload_mb']) {
            $this->addFlash('danger', sprintf('Este archivo pesa %.1f MB. El plan %s permite hasta %d MB por archivo.', $sizeMb, $plan['label'], $plan['max_upload_mb']));
            return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
        }

        $datasetCount = (int) $em->getRepository(UploadedDatasetFile::class)->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->join('f.project', 'p')
            ->andWhere('p.customer = :customer')
            ->setParameter('customer', $project->getCustomer())
            ->getQuery()
            ->getSingleScalarResult();

        if ($datasetCount >= $plan['max_datasets']) {
            $this->addFlash('danger', sprintf('El plan %s permite hasta %d datasets. Cambia el plan para subir mas.', $plan['label'], $plan['max_datasets']));
            return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
        }

        $storageBytes = (int) $em->getRepository(UploadedDatasetFile::class)->createQueryBuilder('f')
            ->select('COALESCE(SUM(f.fileSizeBytes), 0)')
            ->join('f.project', 'p')
            ->andWhere('p.customer = :customer')
            ->setParameter('customer', $project->getCustomer())
            ->getQuery()
            ->getSingleScalarResult();
        $maxStorageBytes = $plan['max_storage_gb'] * 1024 * 1024 * 1024;
        if ($storageBytes + (int) $file->getSize() > $maxStorageBytes) {
            $this->addFlash('danger', sprintf('El plan %s incluye %d GB de almacenamiento total. Este archivo superaria el limite.', $plan['label'], $plan['max_storage_gb']));
            return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
        }

        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0775, true);
        }

        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getClientMimeType() ?: 'application/octet-stream';
        $extension = strtolower($file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION) ?: 'dat');
        if (!in_array($extension, ['xlsx', 'xls', 'csv', 'json'], true)) {
            $this->addFlash('danger', 'Formato no soportado. Usa XLSX, XLS, CSV o JSON.');
            return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $file->move($uploadsDir, $storedName);
        $path = rtrim($uploadsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $storedName;

        $uploaded = (new UploadedDatasetFile())
            ->setProject($project)
            ->setFilename($storedName)
            ->setOriginalName($originalName)
            ->setMimeType($mimeType)
            ->setFileSizeBytes((int) filesize($path));
        $em->persist($uploaded);

        try {
            $parsed = $parser->parse($path);
            foreach ($parsed['rows'] as $index => $rawRow) {
                $em->persist((new DatasetRow())
                    ->setUploadedFile($uploaded)
                    ->setRowNumber($index + 1)
                    ->setRawData($rawRow)
                );
            }

            $uploaded->setRowCount(count($parsed['rows']))->setStatus(UploadedDatasetFile::STATUS_PARSED);
            $em->flush();
            $columnDetection->detectAndPersist($uploaded, $parsed['headers'], $parsed['examples']);
        } catch (\Throwable $e) {
            $uploaded->setStatus(UploadedDatasetFile::STATUS_ERROR)->setErrorMessage($e->getMessage());
            $em->flush();
            $this->addFlash('danger', 'No se pudo procesar el archivo: ' . $e->getMessage());
            return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
        }

        return $this->redirectToRoute('upload_mapping', ['id' => $uploaded->getId()]);
    }
}

<?php

namespace App\Controller;

use App\Entity\DatasetRow;
use App\Entity\Project;
use App\Entity\UploadedFile as UploadedDatasetFile;
use App\Service\ColumnDetectionService;
use App\Service\FileParserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class UploadController extends AbstractController
{
    #[Route('/project/{id}/upload', name: 'project_upload', methods: ['POST'])]
    public function upload(
        Project $project,
        Request $request,
        EntityManagerInterface $em,
        FileParserService $parser,
        ColumnDetectionService $columnDetection,
        string $uploadsDir,
    ): RedirectResponse {
        if ($project->getUser()->getUserIdentifier() !== $this->getUser()?->getUserIdentifier()) {
            throw $this->createAccessDeniedException();
        }

        $file = $request->files->get('dataset');
        if (!$file) {
            $this->addFlash('danger', 'Selecciona un archivo.');
            return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
        }

        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0775, true);
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $file->guessExtension();
        $file->move($uploadsDir, $storedName);
        $path = rtrim($uploadsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $storedName;

        $uploaded = (new UploadedDatasetFile())
            ->setProject($project)
            ->setFilename($storedName)
            ->setOriginalName($file->getClientOriginalName())
            ->setMimeType($file->getMimeType() ?? 'application/octet-stream');
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

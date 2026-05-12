<?php

namespace App\Controller;

use App\Entity\UploadedFile as UploadedDatasetFile;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DataExplorerController extends AbstractController
{
    #[Route('/upload/{id}/data', name: 'data_explorer', methods: ['GET'])]
    public function index(UploadedDatasetFile $uploadedFile, Request $request): Response
    {
        if ($uploadedFile->getProject()->getUser()->getUserIdentifier() !== $this->getUser()?->getUserIdentifier()) {
            throw $this->createAccessDeniedException();
        }

        $query = mb_strtolower(trim((string) $request->query->get('q', '')));
        $rows = [];

        foreach ($uploadedFile->getRows() as $row) {
            $raw = $row->getRawData();
            $normalized = $row->getNormalizedData() ?? [];
            $haystack = mb_strtolower(json_encode([$raw, $normalized], JSON_UNESCAPED_UNICODE));

            if ($query !== '' && !str_contains($haystack, $query)) {
                continue;
            }

            $rows[] = [
                'row_number' => $row->getRowNumber(),
                'raw' => $raw,
                'normalized' => $normalized,
            ];

            if (count($rows) >= 250) {
                break;
            }
        }

        return $this->render('data/index.html.twig', [
            'uploadedFile' => $uploadedFile,
            'rows' => $rows,
            'query' => $query,
        ]);
    }
}


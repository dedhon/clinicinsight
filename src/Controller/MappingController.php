<?php

namespace App\Controller;

use App\Entity\UploadedFile as UploadedDatasetFile;
use App\Service\ColumnDetectionService;
use App\Service\NormalizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class MappingController extends AbstractController
{
    #[Route('/upload/{id}/mapping', name: 'upload_mapping', methods: ['GET', 'POST'])]
    public function mapping(
        UploadedDatasetFile $uploadedFile,
        Request $request,
        EntityManagerInterface $em,
        NormalizationService $normalizationService,
    ): Response|RedirectResponse {
        if ($uploadedFile->getProject()->getUser()->getUserIdentifier() !== $this->getUser()?->getUserIdentifier()) {
            throw $this->createAccessDeniedException();
        }

        //Prdsfsd
        if ($request->isMethod('POST')) {
            $mapping = [];
            foreach ($uploadedFile->getColumns() as $column) {
                $key = (string) $column->getId();
                $ignored = $request->request->all('ignored')[$key] ?? false;
                $field = $request->request->all('mapped_field')[$key] ?? null;

                $column
                    ->setIgnored((bool) $ignored)
                    ->setMappedField($ignored ? null : $field);

                $mapping[$column->getOriginalName()] = [
                    'ignored' => (bool) $ignored,
                    'mapped_field' => $ignored ? null : $field,
                ];
            }

            $em->flush();
            $normalizationService->normalize($uploadedFile, $mapping);

            return $this->redirectToRoute('report_show', ['id' => $uploadedFile->getId()]);
        }

        return $this->render('upload/mapping.html.twig', [
            'uploadedFile' => $uploadedFile,
            'allowedFields' => ColumnDetectionService::ALLOWED_FIELDS,
        ]);
    }
}

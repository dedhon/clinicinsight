<?php

namespace App\Controller;

use App\Entity\DatasetColumn;
use App\Entity\DatasetRow;
use App\Entity\Project;
use App\Entity\UploadedFile as UploadedDatasetFile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProjectController extends AbstractController
{
    #[Route('/project/new', name: 'project_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $project = (new Project())
                ->setUser($this->getUser())
                ->setName((string) $request->request->get('name'))
                ->setDescription($request->request->get('description'));

            $em->persist($project);
            $em->flush();

            return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
        }

        return $this->render('project/new.html.twig');
    }

    #[Route('/project/{id}', name: 'project_show', methods: ['GET'])]
    public function show(Project $project): Response
    {
        $this->denyUnlessOwner($project);

        return $this->render('project/show.html.twig', [
            'project' => $project,
        ]);
    }

    #[Route('/project/{id}/demo', name: 'project_demo', methods: ['POST'])]
    public function demo(Project $project, EntityManagerInterface $em): RedirectResponse
    {
        $this->denyUnlessOwner($project);

        $uploaded = (new UploadedDatasetFile())
            ->setProject($project)
            ->setFilename('demo.csv')
            ->setOriginalName('demo.csv')
            ->setMimeType('text/csv')
            ->setStatus(UploadedDatasetFile::STATUS_MAPPED)
            ->setRowCount(4);
        $em->persist($uploaded);

        $mapping = [
            'fecha' => 'visit_date',
            'profesional' => 'professional_name',
            'especialidad' => 'specialty',
            'estado' => 'status',
            'importe' => 'amount',
            'aseguradora' => 'insurance',
            'edad' => 'patient_age',
            'genero' => 'patient_gender',
        ];

        foreach ($mapping as $original => $field) {
            $em->persist((new DatasetColumn())
                ->setUploadedFile($uploaded)
                ->setOriginalName($original)
                ->setMappedField($field)
                ->setConfidence(1)
            );
        }

        $rows = [
            ['visit_date' => '2026-01-10', 'professional_name' => 'Dra Lopez', 'specialty' => 'Fisioterapia', 'status' => 'realizada', 'amount' => 65, 'insurance' => 'Privado', 'patient_age' => 42, 'patient_gender' => 'F'],
            ['visit_date' => '2026-01-11', 'professional_name' => 'Dr Ruiz', 'specialty' => 'Odontologia', 'status' => 'cancelada', 'amount' => 0, 'insurance' => 'Mutua', 'patient_age' => 38, 'patient_gender' => 'M'],
            ['visit_date' => '2026-02-03', 'professional_name' => 'Dra Lopez', 'specialty' => 'Fisioterapia', 'status' => 'realizada', 'amount' => 70, 'insurance' => 'Privado', 'patient_age' => 51, 'patient_gender' => 'F'],
            ['visit_date' => '2026-02-05', 'professional_name' => 'Dra Marin', 'specialty' => 'Dermatologia', 'status' => 'no_presentado', 'amount' => 0, 'insurance' => 'Mutua', 'patient_age' => 29, 'patient_gender' => 'F'],
        ];

        foreach ($rows as $index => $normalized) {
            $em->persist((new DatasetRow())
                ->setUploadedFile($uploaded)
                ->setRowNumber($index + 1)
                ->setRawData([])
                ->setNormalizedData($normalized)
            );
        }

        $em->flush();

        return $this->redirectToRoute('report_show', ['id' => $uploaded->getId()]);
    }

    private function denyUnlessOwner(Project $project): void
    {
        if ($project->getUser()->getUserIdentifier() !== $this->getUser()?->getUserIdentifier()) {
            throw $this->createAccessDeniedException();
        }
    }
}

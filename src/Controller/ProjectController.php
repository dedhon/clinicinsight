<?php

namespace App\Controller;

use App\Entity\DatasetColumn;
use App\Entity\DatasetRow;
use App\Entity\Project;
use App\Entity\ProjectShare;
use App\Entity\UploadedFile as UploadedDatasetFile;
use App\Entity\User;
use App\Service\PlanCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
class ProjectController extends AbstractController
{
    public function __construct(
        private readonly PlanCatalog $planCatalog,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('/project/new', name: 'project_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $customer = $this->getUser()->getCustomer();
            $plan = $this->planCatalog->get($customer->getPlan());
            if ($customer->getProjects()->count() >= $plan['max_projects']) {
                $this->addFlash('danger', $this->translator->trans('project.flash.plan_limit', [
                    '{plan}' => $plan['label'],
                    '{max}' => $plan['max_projects'],
                ]));
                return $this->redirectToRoute('dashboard');
            }

            $project = (new Project())
                ->setUser($this->getUser())
                ->setCustomer($customer)
                ->setName((string) $request->request->get('name'))
                ->setDescription($request->request->get('description'));

            $em->persist($project);
            $em->flush();

            return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
        }

        return $this->render('project/new.html.twig');
    }

    #[Route('/project/{id}', name: 'project_show', methods: ['GET'])]
    public function show(Project $project, EntityManagerInterface $em): Response
    {
        $this->denyUnlessProjectAccess($project);

        return $this->render('project/show.html.twig', [
            'project' => $project,
            'canManageSharing' => $this->canManageProject($project),
            'shareableUsers' => $this->shareableUsers($project, $em),
        ]);
    }

    #[Route('/project/{id}/share', name: 'project_share_add', methods: ['POST'])]
    public function addShare(Project $project, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $this->denyUnlessManageProject($project);
        if (!$this->isCsrfTokenValid('project_share_' . $project->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $em->getRepository(User::class)->find($request->request->getInt('user_id'));
        if (!$user instanceof User || $user->getCustomer()->getId() !== $project->getCustomer()->getId() || $user->getId() === $project->getUser()->getId()) {
            $this->addFlash('danger', $this->translator->trans('project.flash.share_invalid'));
            return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
        }

        if (!$project->isSharedWith($user)) {
            $em->persist((new ProjectShare())->setProject($project)->setUser($user));
            $em->flush();
            $this->addFlash('success', $this->translator->trans('project.flash.shared'));
        }

        return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
    }

    #[Route('/project/{id}/share/{shareId}/remove', name: 'project_share_remove', methods: ['POST'])]
    public function removeShare(Project $project, int $shareId, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $this->denyUnlessManageProject($project);
        if (!$this->isCsrfTokenValid('project_share_remove_' . $shareId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $share = $em->getRepository(ProjectShare::class)->find($shareId);
        if ($share instanceof ProjectShare && $share->getProject()->getId() === $project->getId()) {
            $em->remove($share);
            $em->flush();
            $this->addFlash('success', $this->translator->trans('project.flash.access_removed'));
        }

        return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
    }

    #[Route('/project/{id}/demo', name: 'project_demo', methods: ['POST'])]
    public function demo(Project $project, EntityManagerInterface $em): RedirectResponse
    {
        $this->denyUnlessManageProject($project);

        $uploaded = (new UploadedDatasetFile())
            ->setProject($project)
            ->setFilename('demo.csv')
            ->setOriginalName('demo.csv')
            ->setMimeType('text/csv')
            ->setFileSizeBytes(1024)
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

    private function denyUnlessProjectAccess(Project $project): void
    {
        if ($this->canAccessProject($project)) {
            return;
        }

        throw $this->createAccessDeniedException();
    }

    private function denyUnlessManageProject(Project $project): void
    {
        if (!$this->canManageProject($project)) {
            throw $this->createAccessDeniedException();
        }
    }

    private function canAccessProject(Project $project): bool
    {
        if ($this->isGranted('ROLE_SUPER_ADMIN') || $this->canManageProject($project)) {
            return true;
        }

        return $project->isSharedWith($this->getUser());
    }

    private function canManageProject(Project $project): bool
    {
        return $this->isGranted('ROLE_SUPER_ADMIN') || $project->getUser()->getUserIdentifier() === $this->getUser()?->getUserIdentifier();
    }

    private function shareableUsers(Project $project, EntityManagerInterface $em): array
    {
        $users = $em->getRepository(User::class)->findBy(['customer' => $project->getCustomer()], ['name' => 'ASC']);

        return array_values(array_filter($users, fn (User $user) => $user->getId() !== $project->getUser()->getId() && !$project->isSharedWith($user)));
    }
}

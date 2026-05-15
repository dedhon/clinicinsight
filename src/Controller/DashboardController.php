<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\ProjectShare;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function index(EntityManagerInterface $em): Response
    {
        $ownedProjects = $em->getRepository(Project::class)->findBy(['user' => $this->getUser()], ['createdAt' => 'DESC']);
        $sharedProjects = array_map(
            fn (ProjectShare $share) => $share->getProject(),
            $em->getRepository(ProjectShare::class)->findBy(['user' => $this->getUser()], ['createdAt' => 'DESC'])
        );
        $projectsById = [];
        foreach ([...$ownedProjects, ...$sharedProjects] as $project) {
            $projectsById[$project->getId()] = $project;
        }

        return $this->render('dashboard/index.html.twig', [
            'projects' => array_values($projectsById),
        ]);
    }
}

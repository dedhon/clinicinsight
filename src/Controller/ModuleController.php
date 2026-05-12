<?php

namespace App\Controller;

use App\Service\ModuleRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ModuleController extends AbstractController
{
    #[Route('/modules', name: 'modules_index', methods: ['GET'])]
    public function index(ModuleRegistry $moduleRegistry): Response
    {
        return $this->render('modules/index.html.twig', [
            'groups' => $moduleRegistry->grouped(),
            'currentPlan' => $moduleRegistry->currentPlan(),
        ]);
    }
}


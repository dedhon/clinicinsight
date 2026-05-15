<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\LocaleCatalog;
use App\Service\PlanCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
class SettingsController extends AbstractController
{
    public function __construct(
        private readonly PlanCatalog $planCatalog,
        private readonly LocaleCatalog $localeCatalog,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('/settings', name: 'customer_settings', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('settings/index.html.twig', [
            'customer' => $user->getCustomer(),
            'currentUser' => $user,
            'plan' => $this->planCatalog->get($user->getCustomer()->getPlan()),
            'locales' => $this->localeCatalog->locales(),
            'statusLabels' => $this->localeCatalog->statusLabels($user->getLocale()),
        ]);
    }

    #[Route('/settings/profile', name: 'customer_settings_profile', methods: ['POST'])]
    public function updateProfile(Request $request, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('settings_profile', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $name = trim((string) $request->request->get('name'));
        if ($name === '') {
            $this->addFlash('danger', $this->translator->trans('settings.flash.name_required'));

            return $this->redirectToRoute('customer_settings');
        }

        $user->setName($name);
        $user->setLocale($this->localeCatalog->normalize((string) $request->request->get('locale', $user->getLocale())));
        $em->flush();
        $this->addFlash('success', $this->translator->trans('settings.flash.profile_updated'));

        return $this->redirectToRoute('customer_settings');
    }

    #[Route('/settings/password', name: 'customer_settings_password', methods: ['POST'])]
    public function updatePassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('settings_password', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $currentPassword = (string) $request->request->get('current_password');
        $newPassword = (string) $request->request->get('new_password');
        $confirmPassword = (string) $request->request->get('confirm_password');

        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('danger', $this->translator->trans('settings.flash.current_password_invalid'));

            return $this->redirectToRoute('customer_settings');
        }

        if (mb_strlen($newPassword) < 8) {
            $this->addFlash('danger', $this->translator->trans('settings.flash.password_too_short'));

            return $this->redirectToRoute('customer_settings');
        }

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('danger', $this->translator->trans('settings.flash.password_mismatch'));

            return $this->redirectToRoute('customer_settings');
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $em->flush();
        $this->addFlash('success', $this->translator->trans('settings.flash.password_updated'));

        return $this->redirectToRoute('customer_settings');
    }
}

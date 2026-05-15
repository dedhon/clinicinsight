<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\LocaleCatalog;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserLocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly LocaleCatalog $localeCatalog,
        private readonly TranslatorInterface $translator,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -64],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            $event->getRequest()->setLocale('es');
            if (method_exists($this->translator, 'setLocale')) {
                $this->translator->setLocale('es');
            }
            return;
        }

        $locale = $this->localeCatalog->normalize($user->getLocale());
        $event->getRequest()->setLocale($locale);
        if (method_exists($this->translator, 'setLocale')) {
            $this->translator->setLocale($locale);
        }
    }
}

<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\InsightReport;
use App\Entity\Project;
use App\Entity\UploadedFile as UploadedDatasetFile;
use App\Entity\User;
use App\Service\LocaleCatalog;
use App\Service\PlanCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PlanCatalog $planCatalog,
        private readonly LocaleCatalog $localeCatalog,
    ) {}

    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'customer_id' => $request->query->getInt('customer_id'),
            'plan' => (string) $request->query->get('plan', ''),
            'status' => (string) $request->query->get('status', ''),
        ];
        $customerPage = max(1, $request->query->getInt('customer_page', 1));
        $userPage = max(1, $request->query->getInt('user_page', 1));
        $limit = 10;
        $allCustomers = $em->getRepository(Customer::class)->findBy([], ['name' => 'ASC']);

        $customerQb = $em->getRepository(Customer::class)->createQueryBuilder('c')
            ->leftJoin('c.users', 'u')
            ->addSelect('u')
            ->orderBy('c.createdAt', 'DESC')
            ->distinct();

        if ($filters['q'] !== '') {
            $customerQb
                ->andWhere('LOWER(c.name) LIKE :q OR LOWER(c.slug) LIKE :q OR LOWER(c.billingEmail) LIKE :q OR LOWER(u.email) LIKE :q OR LOWER(u.name) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($filters['q']) . '%');
        }
        if ($filters['customer_id'] > 0) {
            $customerQb->andWhere('c.id = :customerId')->setParameter('customerId', $filters['customer_id']);
        }
        if (in_array($filters['plan'], $this->planCatalog->keys(), true)) {
            $customerQb->andWhere('c.plan = :plan')->setParameter('plan', $filters['plan']);
        }
        if (in_array($filters['status'], $this->planCatalog->statuses(), true)) {
            $customerQb->andWhere('c.subscriptionStatus = :status')->setParameter('status', $filters['status']);
        }

        $customerCountQb = clone $customerQb;
        $customerTotal = (int) $customerCountQb
            ->select('COUNT(DISTINCT c.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
        $customers = $customerQb
            ->setFirstResult(($customerPage - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $userQb = $em->getRepository(User::class)->createQueryBuilder('u')
            ->join('u.customer', 'c')
            ->addSelect('c')
            ->orderBy('u.createdAt', 'DESC');
        if ($filters['q'] !== '') {
            $userQb
                ->andWhere('LOWER(u.email) LIKE :q OR LOWER(u.name) LIKE :q OR LOWER(c.name) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($filters['q']) . '%');
        }
        if ($filters['customer_id'] > 0) {
            $userQb->andWhere('c.id = :customerId')->setParameter('customerId', $filters['customer_id']);
        }
        if (in_array($filters['plan'], $this->planCatalog->keys(), true)) {
            $userQb->andWhere('c.plan = :plan')->setParameter('plan', $filters['plan']);
        }
        if (in_array($filters['status'], $this->planCatalog->statuses(), true)) {
            $userQb->andWhere('c.subscriptionStatus = :status')->setParameter('status', $filters['status']);
        }
        $userCountQb = clone $userQb;
        $userTotal = (int) $userCountQb
            ->select('COUNT(u.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
        $users = $userQb
            ->setFirstResult(($userPage - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->render('admin/index.html.twig', [
            'customers' => $customers,
            'allCustomers' => $allCustomers,
            'users' => $users,
            'filters' => $filters,
            'customerPagination' => [
                'page' => $customerPage,
                'pages' => max(1, (int) ceil($customerTotal / $limit)),
                'total' => $customerTotal,
                'limit' => $limit,
            ],
            'userPagination' => [
                'page' => $userPage,
                'pages' => max(1, (int) ceil($userTotal / $limit)),
                'total' => $userTotal,
                'limit' => $limit,
            ],
            'plans' => $this->planCatalog->all(),
            'statuses' => $this->planCatalog->statuses(),
            'statusLabels' => $this->localeCatalog->statusLabels($this->getUser()?->getLocale() ?? 'es'),
        ]);
    }

    #[Route('/admin/customer/create', name: 'admin_customer_create', methods: ['POST'])]
    public function createCustomer(Request $request, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('admin_customer_create', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $name = trim((string) $request->request->get('name'));
        if ($name === '') {
            $this->addFlash('danger', 'El nombre del cliente es obligatorio.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $customer = (new Customer())
            ->setName($name)
            ->setSlug($this->uniqueSlug($em, $name))
            ->setPlan($this->validPlan((string) $request->request->get('plan', 'starter')))
            ->setSubscriptionStatus($this->validStatus((string) $request->request->get('subscription_status', 'trial')))
            ->setBillingEmail($request->request->get('billing_email') ?: null);

        $em->persist($customer);
        $em->flush();
        $this->addFlash('success', 'Cliente creado.');

        return $this->redirectToRoute('admin_customer_show', ['id' => $customer->getId()]);
    }

    #[Route('/admin/customer/{id}', name: 'admin_customer_show', methods: ['GET'])]
    public function showCustomer(Customer $customer, Request $request, EntityManagerInterface $em): Response
    {
        $projects = $em->getRepository(Project::class)->findBy(['customer' => $customer], ['updatedAt' => 'DESC']);
        $files = $this->filesForProjects($em, $projects);
        $reports = $this->reportsForProjects($em, $projects);
        $totalRows = array_sum(array_map(fn (UploadedDatasetFile $file) => $file->getRowCount(), $files));
        $plan = $this->planCatalog->get($customer->getPlan());

        return $this->render('admin/customer.html.twig', [
            'customer' => $customer,
            'plan' => $plan,
            'plans' => $this->planCatalog->all(),
            'statuses' => $this->planCatalog->statuses(),
            'statusLabels' => $this->localeCatalog->statusLabels($this->getUser()?->getLocale() ?? 'es'),
            'projects' => $projects,
            'files' => $files,
            'reports' => $reports,
            'totalRows' => $totalRows,
            'activeTab' => (string) $request->query->get('tab', 'summary'),
        ]);
    }

    #[Route('/admin/customer/{id}/user/create', name: 'admin_customer_user_create', methods: ['POST'])]
    public function createUser(Customer $customer, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('admin_customer_user_create_' . $customer->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $email = mb_strtolower(trim((string) $request->request->get('email')));
        $name = trim((string) $request->request->get('name'));
        $plainPassword = (string) $request->request->get('password');

        if ($email === '' || $name === '' || $plainPassword === '') {
            $this->addFlash('danger', 'Nombre, email y contraseña son obligatorios.');
            return $this->redirectToRoute('admin_customer_show', ['id' => $customer->getId(), 'tab' => 'users']);
        }

        if (!$this->planCatalog->canAddUser($customer->getPlan(), $customer->getUsers()->count())) {
            $this->addFlash('danger', sprintf(
                'El plan %s permite hasta %d usuarios. Cambia el plan antes de crear mas usuarios.',
                $this->planCatalog->label($customer->getPlan()),
                $this->planCatalog->get($customer->getPlan())['max_users']
            ));
            return $this->redirectToRoute('admin_customer_show', ['id' => $customer->getId(), 'tab' => 'users']);
        }

        if ($em->getRepository(User::class)->findOneBy(['email' => $email]) instanceof User) {
            $this->addFlash('danger', 'Ya existe un usuario con ese email.');
            return $this->redirectToRoute('admin_customer_show', ['id' => $customer->getId(), 'tab' => 'users']);
        }

        $roles = [];
        if ($request->request->getBoolean('customer_admin')) {
            $roles[] = 'ROLE_CUSTOMER_ADMIN';
        }

        $user = (new User())
            ->setCustomer($customer)
            ->setName($name)
            ->setEmail($email)
            ->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $em->persist($user);
        $em->flush();
        $this->addFlash('success', 'Usuario creado.');

        return $this->redirectToRoute('admin_customer_show', ['id' => $customer->getId(), 'tab' => 'users']);
    }

    #[Route('/admin/customer/{id}/subscription', name: 'admin_customer_subscription', methods: ['POST'])]
    public function updateCustomer(Customer $customer, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('admin_customer_' . $customer->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $customer
            ->setPlan($this->validPlan((string) $request->request->get('plan', $customer->getPlan())))
            ->setSubscriptionStatus($this->validStatus((string) $request->request->get('subscription_status', $customer->getSubscriptionStatus())))
            ->setBillingEmail($request->request->get('billing_email') ?: null)
            ->setStripeCustomerId($request->request->get('stripe_customer_id') ?: null);

        $em->flush();
        $this->addFlash('success', 'Cliente actualizado.');

        $redirectToCustomer = $request->request->getBoolean('redirect_to_customer');

        return $redirectToCustomer
            ? $this->redirectToRoute('admin_customer_show', ['id' => $customer->getId(), 'tab' => 'subscription'])
            : $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/admin/user/{id}/roles', name: 'admin_user_roles', methods: ['POST'])]
    public function updateUserRoles(User $user, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('admin_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $roles = [];
        if ($request->request->getBoolean('super_admin')) {
            $roles[] = 'ROLE_SUPER_ADMIN';
        }
        if ($request->request->getBoolean('customer_admin')) {
            $roles[] = 'ROLE_CUSTOMER_ADMIN';
        }

        $user->setRoles($roles);
        $em->flush();
        $this->addFlash('success', 'Permisos actualizados.');

        $customerId = $request->request->getInt('customer_id');

        return $customerId > 0
            ? $this->redirectToRoute('admin_customer_show', ['id' => $customerId, 'tab' => 'users'])
            : $this->redirectToRoute('admin_dashboard');
    }

    /**
     * @param list<Project> $projects
     * @return list<UploadedDatasetFile>
     */
    private function filesForProjects(EntityManagerInterface $em, array $projects): array
    {
        if ($projects === []) {
            return [];
        }

        return $em->getRepository(UploadedDatasetFile::class)->createQueryBuilder('f')
            ->andWhere('f.project IN (:projects)')
            ->setParameter('projects', $projects)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<Project> $projects
     * @return list<InsightReport>
     */
    private function reportsForProjects(EntityManagerInterface $em, array $projects): array
    {
        if ($projects === []) {
            return [];
        }

        return $em->getRepository(InsightReport::class)->createQueryBuilder('r')
            ->andWhere('r.project IN (:projects)')
            ->setParameter('projects', $projects)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    private function uniqueSlug(EntityManagerInterface $em, string $name): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        $suffix = 2;

        while ($em->getRepository(Customer::class)->findOneBy(['slug' => $slug]) instanceof Customer) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;

        return trim($value, '-') ?: 'cliente';
    }

    private function validPlan(string $plan): string
    {
        return in_array($plan, $this->planCatalog->keys(), true) ? $plan : 'starter';
    }

    private function validStatus(string $status): string
    {
        return in_array($status, $this->planCatalog->statuses(), true) ? $status : 'trial';
    }
}

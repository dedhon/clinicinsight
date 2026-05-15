<?php

namespace App\Command;

use App\Entity\Customer;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-user', description: 'Create or update a local ClinicInsight user')]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('password', InputArgument::REQUIRED)
            ->addArgument('name', InputArgument::OPTIONAL, '', 'Admin')
            ->addArgument('customer', InputArgument::OPTIONAL, '', 'Demo Clinic');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getArgument('email');
        $plainPassword = (string) $input->getArgument('password');
        $name = (string) $input->getArgument('name');
        $customerName = (string) $input->getArgument('customer') ?: 'Demo Clinic';
        $customerSlug = $this->slugify($customerName);

        $customer = $this->em->getRepository(Customer::class)->findOneBy(['slug' => $customerSlug]);
        if (!$customer instanceof Customer) {
            $customer = (new Customer())
                ->setName($customerName)
                ->setSlug($customerSlug);
            $this->em->persist($customer);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]) ?? new User();
        $user
            ->setCustomer($customer)
            ->setEmail($email)
            ->setName($name)
            ->setRoles($this->rolesForEmail($email))
            ->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->em->persist($user);
        $this->em->flush();

        $output->writeln(sprintf('Usuario listo: %s (%s)', $email, $customerName));

        return Command::SUCCESS;
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;

        return trim($value, '-') ?: 'demo-clinic';
    }

    private function rolesForEmail(string $email): array
    {
        return str_ends_with(mb_strtolower($email), '@clinicinsight.local') ? ['ROLE_SUPER_ADMIN'] : [];
    }
}

<?php

namespace App\Service;

class PlanCatalog
{
    public const PLANS = [
        'starter' => [
            'label' => 'Inicial',
            'monthly_price' => 49,
            'target' => 'Clinicas pequenas o profesionales independientes',
            'max_users' => 1,
            'max_projects' => 3,
            'max_datasets' => 20,
            'max_upload_mb' => 25,
            'max_storage_gb' => 1,
            'ai_reports' => false,
            'support' => 'Email',
        ],
        'professional' => [
            'label' => 'Profesional',
            'monthly_price' => 149,
            'target' => 'Clinicas con equipo y varios servicios',
            'max_users' => 5,
            'max_projects' => 20,
            'max_datasets' => 150,
            'max_upload_mb' => 75,
            'max_storage_gb' => 10,
            'ai_reports' => true,
            'support' => 'Prioritario',
        ],
        'enterprise' => [
            'label' => 'Empresa',
            'monthly_price' => 399,
            'target' => 'Grupos, franquicias o clinicas con muchas sedes',
            'max_users' => 20,
            'max_projects' => 100,
            'max_datasets' => 1000,
            'max_upload_mb' => 250,
            'max_storage_gb' => 100,
            'ai_reports' => true,
            'support' => 'Dedicado',
        ],
    ];

    public const STATUSES = ['trial', 'active', 'past_due', 'cancelled'];

    public function all(): array
    {
        return self::PLANS;
    }

    public function keys(): array
    {
        return array_keys(self::PLANS);
    }

    public function statuses(): array
    {
        return self::STATUSES;
    }

    public function get(string $plan): array
    {
        return self::PLANS[$plan] ?? self::PLANS['starter'];
    }

    public function label(string $plan): string
    {
        return $this->get($plan)['label'];
    }

    public function canAddUser(string $plan, int $currentUsers): bool
    {
        return $currentUsers < $this->get($plan)['max_users'];
    }
}

<?php

namespace App\Service;

class ModuleRegistry
{
    private const PLAN_ORDER = ['starter' => 1, 'professional' => 2, 'business' => 3, 'enterprise' => 4];

    public function __construct(private readonly string $currentPlan = 'professional') {}

    public function all(): array
    {
        $modules = [
            [
                'id' => 'core_projects',
                'name' => 'Proyectos y usuarios',
                'version' => '1.0.0',
                'category' => 'Core',
                'description' => 'Login, proyectos, subida de archivos y permisos por usuario.',
                'minimum_plan' => 'starter',
                'price_label' => 'Incluido',
                'status' => 'active',
                'routes' => ['dashboard', 'project_show', 'project_upload'],
            ],
            [
                'id' => 'generic_bi',
                'name' => 'BI automatico generico',
                'version' => '1.1.0',
                'category' => 'Analytics',
                'description' => 'Detecta columnas de cualquier Excel/CSV/JSON y genera graficos recomendados.',
                'minimum_plan' => 'starter',
                'price_label' => 'Incluido',
                'status' => 'active',
                'routes' => ['report_show'],
            ],
            [
                'id' => 'clinic_analytics',
                'name' => 'Analitica clinica',
                'version' => '1.1.0',
                'category' => 'Verticales',
                'description' => 'KPIs de visitas, cancelaciones, no show, facturacion, profesionales, especialidades y aseguradoras.',
                'minimum_plan' => 'professional',
                'price_label' => 'Plan Pro',
                'status' => 'active',
                'routes' => ['report_show'],
            ],
            [
                'id' => 'encrypted_explorer',
                'name' => 'Explorador cifrado',
                'version' => '1.0.0',
                'category' => 'Datos',
                'description' => 'Consulta filas originales y normalizadas descifradas en sesion con buscador y copiado JSON.',
                'minimum_plan' => 'professional',
                'price_label' => 'Plan Pro',
                'status' => 'active',
                'routes' => ['data_explorer'],
            ],
            [
                'id' => 'exports',
                'name' => 'Exportaciones BI',
                'version' => '1.0.0',
                'category' => 'Exports',
                'description' => 'Export a Power BI CSV, JSON agregado, PNG de graficos e impresion/PDF desde navegador.',
                'minimum_plan' => 'professional',
                'price_label' => 'Plan Pro',
                'status' => 'active',
                'routes' => ['report_export_powerbi', 'report_export_json'],
            ],
            [
                'id' => 'ai_mapping',
                'name' => 'Mapeo e insights IA',
                'version' => '0.9.0',
                'category' => 'IA',
                'description' => 'Mapeo de columnas y resumen ejecutivo usando OpenAI sin enviar filas completas.',
                'minimum_plan' => 'business',
                'price_label' => 'Plan Business',
                'status' => 'beta',
                'routes' => [],
            ],
            [
                'id' => 'slides_export',
                'name' => 'Diapositivas ejecutivas',
                'version' => '0.1.0',
                'category' => 'Exports',
                'description' => 'Generacion de presentaciones con KPIs, graficos y conclusiones listas para direccion.',
                'minimum_plan' => 'business',
                'price_label' => 'Plan Business',
                'status' => 'planned',
                'routes' => [],
            ],
            [
                'id' => 'audit_log',
                'name' => 'Auditoria y cumplimiento',
                'version' => '0.1.0',
                'category' => 'Seguridad',
                'description' => 'Registro de accesos, descargas, exploracion de datos y cambios de mapeo.',
                'minimum_plan' => 'enterprise',
                'price_label' => 'Enterprise',
                'status' => 'planned',
                'routes' => [],
            ],
            [
                'id' => 'team_billing',
                'name' => 'Equipos y facturacion',
                'version' => '0.1.0',
                'category' => 'SaaS',
                'description' => 'Organizaciones, roles, limites por plan, suscripciones y facturas.',
                'minimum_plan' => 'enterprise',
                'price_label' => 'Enterprise',
                'status' => 'planned',
                'routes' => [],
            ],
            [
                'id' => 'external_api',
                'name' => 'API externa',
                'version' => '0.1.0',
                'category' => 'Integraciones',
                'description' => 'Endpoints para subir datasets, consultar informes y conectar integraciones externas.',
                'minimum_plan' => 'enterprise',
                'price_label' => 'Enterprise',
                'status' => 'planned',
                'routes' => [],
            ],
        ];

        return array_map(fn (array $module) => $module + [
            'enabled' => $this->isPlanAllowed($module['minimum_plan']) && $module['status'] !== 'planned',
            'current_plan' => $this->currentPlan(),
        ], $modules);
    }

    public function grouped(): array
    {
        $groups = [];
        foreach ($this->all() as $module) {
            $groups[$module['category']][] = $module;
        }

        return $groups;
    }

    public function currentPlan(): string
    {
        return strtolower($this->currentPlan ?: 'professional');
    }

    public function isEnabled(string $moduleId): bool
    {
        foreach ($this->all() as $module) {
            if ($module['id'] === $moduleId) {
                return $module['enabled'];
            }
        }

        return false;
    }

    private function isPlanAllowed(string $minimumPlan): bool
    {
        return (self::PLAN_ORDER[$this->currentPlan()] ?? 1) >= (self::PLAN_ORDER[$minimumPlan] ?? 999);
    }
}


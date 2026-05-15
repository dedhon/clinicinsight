<?php

namespace App\Service;

class LocaleCatalog
{
    public const LOCALES = [
        'es' => 'Espanol',
        'ca' => 'Catala',
        'en' => 'English',
        'fr' => 'Francais',
        'de' => 'Deutsch',
        'pt' => 'Portugues',
        'it' => 'Italiano',
    ];

    public const STATUS_LABELS = [
        'es' => [
            'trial' => 'Prueba',
            'active' => 'Activa',
            'past_due' => 'Pago pendiente',
            'cancelled' => 'Cancelada',
        ],
        'ca' => [
            'trial' => 'Prova',
            'active' => 'Activa',
            'past_due' => 'Pagament pendent',
            'cancelled' => 'Cancel·lada',
        ],
        'en' => [
            'trial' => 'Trial',
            'active' => 'Active',
            'past_due' => 'Payment due',
            'cancelled' => 'Cancelled',
        ],
        'fr' => [
            'trial' => 'Essai',
            'active' => 'Active',
            'past_due' => 'Paiement en retard',
            'cancelled' => 'Annulee',
        ],
        'de' => [
            'trial' => 'Testphase',
            'active' => 'Aktiv',
            'past_due' => 'Zahlung offen',
            'cancelled' => 'Gekuendigt',
        ],
        'pt' => [
            'trial' => 'Teste',
            'active' => 'Ativa',
            'past_due' => 'Pagamento pendente',
            'cancelled' => 'Cancelada',
        ],
        'it' => [
            'trial' => 'Prova',
            'active' => 'Attiva',
            'past_due' => 'Pagamento in sospeso',
            'cancelled' => 'Annullata',
        ],
    ];

    public function locales(): array
    {
        return self::LOCALES;
    }

    public function normalize(string $locale): string
    {
        return array_key_exists($locale, self::LOCALES) ? $locale : 'es';
    }

    public function statusLabels(string $locale): array
    {
        return self::STATUS_LABELS[$this->normalize($locale)];
    }

    public function statusLabel(string $status, string $locale): string
    {
        return $this->statusLabels($locale)[$status] ?? $status;
    }
}

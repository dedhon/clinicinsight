<?php

namespace App\Service;

use GuzzleHttp\Client;

class OpenAiMappingService
{
    public function __construct(
        private readonly string $openAiModel,
        private readonly ?string $openAiApiKey = null,
    ) {}

    public function mapColumns(array $headers, array $examples): array
    {
        if (!$this->openAiApiKey) {
            return ['columns' => array_map(fn ($h) => [
                'original_name' => $h,
                'mapped_field' => null,
                'confidence' => 0.0,
                'ignored' => false,
                'reason' => 'OPENAI_API_KEY no configurada.',
            ], $headers)];
        }

        $payload = [
            'headers' => $headers,
            'examples' => $examples,
            'allowed_fields' => ColumnDetectionService::ALLOWED_FIELDS,
        ];

        $text = $this->createResponse(
            'Eres un sistema de business intelligence para clinicas. Devuelve solo JSON valido. No uses ni guardes datos personales o clinicos sensibles.',
            "Mapea estas columnas a campos normalizados. Si una columna contiene nombre, DNI, telefono, email, direccion, diagnostico, tratamiento, CIP o historia clinica, marca ignored=true.\n\n" . json_encode($payload, JSON_UNESCAPED_UNICODE)
        );

        return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
    }

    public function summarize(array $kpis, array $charts): string
    {
        if (!$this->openAiApiKey) {
            return 'Resumen IA no disponible: OPENAI_API_KEY no configurada.';
        }

        // Privacy guardrail: only aggregate KPIs/charts are sent to OpenAI.
        return $this->createResponse(
            'Eres un analista BI para gerentes de clinicas. No inventes datos. No menciones pacientes.',
            'Genera un resumen ejecutivo breve en espanol para una clinica usando solo estos agregados: ' . json_encode(['kpis' => $kpis, 'charts' => $charts], JSON_UNESCAPED_UNICODE),
            false
        );
    }

    private function createResponse(string $instructions, string $input, bool $jsonSchema = true): string
    {
        $client = new Client(['base_uri' => 'https://api.openai.com/v1/']);
        $body = [
            'model' => $this->openAiModel,
            'instructions' => $instructions,
            'input' => $input,
            'store' => false,
        ];

        if ($jsonSchema) {
            $body['text'] = [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'clinic_column_mapping',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'columns' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'properties' => [
                                        'original_name' => ['type' => 'string'],
                                        'mapped_field' => ['type' => ['string', 'null']],
                                        'confidence' => ['type' => 'number'],
                                        'ignored' => ['type' => 'boolean'],
                                        'reason' => ['type' => ['string', 'null']],
                                    ],
                                    'required' => ['original_name', 'mapped_field', 'confidence', 'ignored', 'reason'],
                                ],
                            ],
                        ],
                        'required' => ['columns'],
                    ],
                ],
            ];
        }

        $response = $client->post('responses', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openAiApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => $body,
            'timeout' => 45,
        ]);

        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        return $decoded['output_text'] ?? $this->extractText($decoded);
    }

    private function extractText(array $response): string
    {
        foreach ($response['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text') {
                    return (string) ($content['text'] ?? '');
                }
            }
        }

        return '';
    }
}


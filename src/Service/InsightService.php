<?php

namespace App\Service;

class InsightService
{
    public function __construct(private readonly OpenAiMappingService $openAiMappingService) {}

    public function summarize(array $kpis, array $charts): string
    {
        return $this->openAiMappingService->summarize($kpis, $charts);
    }
}


<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

#[Signature('ai:health')]
#[Description('Check that the Python AI service (and the Ollama behind it) answers')]
class CheckAiService extends Command
{
    public function handle(): int
    {
        $url = config('services.ai.url');

        try {
            $response = Http::timeout(5)->get($url.'/health');
        } catch (ConnectionException) {
            $this->error("AI service unreachable at {$url}.");

            return self::FAILURE;
        }

        if ($response->failed()) {
            $this->error("AI service at {$url} returned HTTP {$response->status()}.");

            return self::FAILURE;
        }

        if (! $response->json('ollama')) {
            $this->warn("AI service is up at {$url}, but Ollama is not answering.");

            return self::FAILURE;
        }

        if ($missing = $response->json('missing_models')) {
            $this->warn('Models not pulled yet: '.implode(', ', $missing).'. Run: docker compose exec ollama ollama pull <model>');

            return self::FAILURE;
        }

        $this->info("AI service and Ollama are up at {$url}.");

        return self::SUCCESS;
    }
}

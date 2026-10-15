<?php

namespace LaraGram\Ai\Console\Commands;

use LaraGram\Console\Attribute\Description;
use LaraGram\Console\Attribute\Signature;
use LaraGram\Console\Command;
use LaraGram\Ai\Responses\AgentResponse;
use LaraGram\Ai\Responses\StructuredAgentResponse;

use function LaraGram\Ai\agent;
use function LaraGram\Console\Prompts\note;
use function LaraGram\Console\Prompts\spin;
use function LaraGram\Console\Prompts\textarea;

#[Description('Chat with one of your agents')]
#[Signature('agent:chat')]
class ChatCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $agent = agent(
            instructions: 'You are a helpful assistant.',
        );

        while (true) {
            $prompt = textarea('Prompt...');

            $response = spin(
                fn (): AgentResponse => $agent->prompt($prompt),
                message: 'Thinking...',
            );

            if ($response instanceof StructuredAgentResponse) {
                $response = json_encode($response->structured, JSON_PRETTY_PRINT);
            }

            note($response.PHP_EOL);
        }

        return Command::SUCCESS;
    }
}

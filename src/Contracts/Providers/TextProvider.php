<?php

namespace LaraGram\Ai\Contracts\Providers;

use LaraGram\Ai\Contracts\Gateway\StepTextGateway;
use LaraGram\Ai\Gateway\TextGenerationLoop;
use LaraGram\Ai\Prompts\AgentPrompt;
use LaraGram\Ai\Responses\AgentResponse;
use LaraGram\Ai\Responses\StreamableAgentResponse;

interface TextProvider extends Provider
{
    /**
     * Invoke the given agent.
     */
    public function prompt(AgentPrompt $prompt): AgentResponse;

    /**
     * Stream the response from the given agent.
     */
    public function stream(AgentPrompt $prompt): StreamableAgentResponse;

    /**
     * Set the provider's text gateway.
     */
    public function useTextGateway(StepTextGateway $gateway): self;

    /**
     * Get the multi-step text generation loop wrapping the provider's text gateway.
     */
    public function textGenerationLoop(): TextGenerationLoop;

    /**
     * Get the name of the default text model.
     */
    public function defaultTextModel(): string;

    /**
     * Get the name of the cheapest text model.
     */
    public function cheapestTextModel(): string;

    /**
     * Get the name of the smartest text model.
     */
    public function smartestTextModel(): string;
}

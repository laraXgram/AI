<?php

namespace LaraGram\Ai\Contracts;

use Closure;
use LaraGram\Ai\Skills\Skill;

interface HasSkills
{
    /**
     * Get the skills available to the agent.
     *
     * @return iterable<Closure|Skill|string>
     */
    public function skills(): iterable;
}

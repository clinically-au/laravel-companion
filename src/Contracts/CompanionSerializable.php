<?php

declare(strict_types=1);

namespace Clinically\Companion\Contracts;

interface CompanionSerializable
{
    /**
     * Return the attributes to expose via the Companion model browser.
     *
     * @return array<string, mixed>
     */
    public function toCompanionArray(): array;

    /**
     * Return the relationship names that are browsable via Companion.
     *
     * @return list<string>
     */
    public function companionRelationships(): array;

    /**
     * Return the scope names that are exposed via Companion.
     *
     * @return list<string>
     */
    public function companionScopes(): array;
}

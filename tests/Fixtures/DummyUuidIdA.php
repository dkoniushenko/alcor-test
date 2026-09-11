<?php

declare(strict_types=1);

namespace Alcor\Tests\Fixtures;

use Alcor\Shared\Domain\ValueObject\AbstractUuidId;

// Not a real domain identifier — exists only so AbstractUuidId's own tests have
// something concrete to instantiate. Paired with DummyUuidIdB for the
// different-concrete-type equals() check.
final readonly class DummyUuidIdA extends AbstractUuidId
{
}

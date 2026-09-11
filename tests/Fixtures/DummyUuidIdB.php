<?php

declare(strict_types=1);

namespace Alcor\Tests\Fixtures;

use Alcor\Shared\Domain\ValueObject\AbstractUuidId;

// See DummyUuidIdA — the second, distinct dummy type needed to prove equals()
// treats different AbstractUuidId subtypes as unequal.
final readonly class DummyUuidIdB extends AbstractUuidId
{
}

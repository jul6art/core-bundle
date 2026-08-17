<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Service\Traits;

use Faker\Factory;
use Faker\Generator;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Trait FakerAwareTrait.
 */
trait FakerAwareTrait
{
    protected Generator $faker;

    #[Required]
    public function setFaker(): void
    {
        $this->faker = Factory::create();
    }
}

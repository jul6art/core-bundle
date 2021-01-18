<?php

namespace Jul6Art\CoreBundle\Service\Traits;

use Faker\Factory;
use Faker\Generator;

/**
 * Trait FakerAwareTrait.
 */
trait FakerAwareTrait
{
    /**
     * @var Generator
     */
    protected $faker;

    /**
     * @required
     */
    public function setFaker(): void
    {
        $this->faker = Factory::create();
    }
}

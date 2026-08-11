<?php

namespace Visualbuilder\ExportScheduler\Tests\Database\Factories;

use Orchestra\Testbench\Factories\UserFactory as TestbenchUserFactory;
use Visualbuilder\ExportScheduler\Tests\Models\User;

/**
 * Orchestra's UserFactory builds an `Illuminate\Foundation\Auth\User`, so morph
 * types recorded against it never matched the test User class. Pinning the model
 * here keeps `owner_type` and `$user::class` comparable.
 */
class UserFactory extends TestbenchUserFactory
{
    protected $model = User::class;
}

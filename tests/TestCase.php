<?php

declare(strict_types=1);

namespace TwistedBinary\FeedbackWidget\Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Override;
use TwistedBinary\FeedbackWidget\FeedbackWidgetServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return array<int, class-string>
     */
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [
            FeedbackWidgetServiceProvider::class,
        ];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    #[Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    /**
     * Create a simple authenticated user for testing.
     */
    public function createAuthenticatedUser(int $id = 1): Authenticatable
    {
        $user = \Mockery::mock(Authenticatable::class);
        $user->shouldReceive('getAuthIdentifier')->andReturn($id);
        $user->shouldReceive('getAuthIdentifierName')->andReturn('id');
        $user->shouldReceive('getAuthPassword')->andReturn('password');
        $user->shouldReceive('getRememberToken')->andReturn(null);
        $user->shouldReceive('setRememberToken');
        $user->shouldReceive('getRememberTokenName')->andReturn('remember_token');

        return $user;
    }
}

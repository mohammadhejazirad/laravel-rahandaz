<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Support\Facades\Hash;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class LoginPasswordTimingTest extends TestCase
{
    public function test_missing_user_timing_hash_uses_bcrypt_compatible_input(): void
    {
        $property = new ReflectionProperty(LoginRequest::class, 'dummyHash');
        $property->setValue(null, null);
        $hasher = new BcryptHasher(['rounds' => 4]);

        Hash::shouldReceive('make')->once()->andReturnUsing(function (string $value) use ($hasher): string {
            $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $value);

            return $hasher->make($value);
        });
        Hash::shouldReceive('check')->twice()->andReturnUsing(function (string $value, string $hash) use ($hasher): bool {
            $this->assertFalse($hasher->check($value, $hash));

            return false;
        });

        try {
            $request = new LoginRequest;
            $method = new ReflectionMethod(LoginRequest::class, 'burnPasswordTime');
            $method->invoke($request, 'incorrect-password', true);
            $method->invoke($request, 'incorrect-password', true);
        } finally {
            $property->setValue(null, null);
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class RestaurantTableApiTest extends TestCase
{
    public function test_health_endpoint_is_available(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJson(['ok' => true, 'service' => 'riscp-platform']);
    }
}

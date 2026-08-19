<?php

declare(strict_types=1);

namespace App\Core\Domain;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PAID = 'paid';
    case PREPARING = 'preparing';
    case COMPLETED = 'completed';
    case REFUNDED = 'refunded';
    case CANCELLED = 'cancelled';
}

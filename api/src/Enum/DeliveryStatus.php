<?php

namespace App\Enum;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Retrying = 'retrying';
    case Delivered = 'delivered';
    case Failed = 'failed';
}

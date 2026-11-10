<?php

namespace App\Enum;

enum DeliveryOutcome
{
    case Delivered;
    case RetryableFailure;
    case PermanentFailure;
}

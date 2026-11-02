<?php

namespace App\Enum;

/**
 * How a Target is reached. Each case has one DeliveryTransport implementation.
 * v1 only ships HTTP; AMQP/MQTT would be new cases.
 */
enum TargetProtocol: string
{
    case Http = 'http';
}

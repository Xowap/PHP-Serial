<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial;

/**
 * Lifecycle state of a serial device.
 */
enum DeviceState
{
    case NotSet;
    case Set;
    case Opened;
}

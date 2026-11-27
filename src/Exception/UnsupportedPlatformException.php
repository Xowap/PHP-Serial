<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial\Exception;

/**
 * Thrown when the current operating system has no serial port implementation.
 */
class UnsupportedPlatformException extends PhpSerialException
{
}

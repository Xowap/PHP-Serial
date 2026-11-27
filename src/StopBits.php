<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial;

/**
 * Stop bit lengths supported by the library.
 */
enum StopBits: string
{
    case One = '1';
    case OneAndHalf = '1.5';
    case Two = '2';
}

<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial;

/**
 * Parity modes supported by the library.
 */
enum Parity: string
{
    case None = 'none';
    case Odd = 'odd';
    case Even = 'even';
    case Mark = 'mark';
    case Space = 'space';
}

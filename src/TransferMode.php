<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial;

/**
 * Line discipline used when reading from the port.
 *
 * "Binary" puts the terminal in raw mode so every byte (including Ctrl-C and
 * Ctrl-Z) is delivered untouched. "Text" keeps canonical mode and echo, which
 * is convenient for interactive terminals but unsuitable for binary protocols.
 */
enum TransferMode: string
{
    case Text = 'text';
    case Binary = 'binary';
}

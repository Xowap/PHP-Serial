<?php

declare(strict_types=1);

namespace Hyperthese\PhpSerial;

/**
 * Flow control modes supported by the library.
 */
enum FlowControl: string
{
    case None = 'none';
    case RtsCts = 'rts/cts';
    case XonXoff = 'xon/xoff';
}

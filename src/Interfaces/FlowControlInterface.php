<?php

namespace PhpSerial\Interfaces;

class FlowControlInterface
{
    public const NONE     = 'none';
    public const XON_XOFF = 'xon/xoff';
    public const RST_CTS  = 'rts/cts';
}

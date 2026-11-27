<?php

declare(strict_types=1);

/*
 * Backwards compatibility for 1.x code that did `new PhpSerial`.
 *
 * New code should use Hyperthese\PhpSerial\PhpSerial directly.
 */

if (!class_exists('PhpSerial', false)) {
    class_alias(\Hyperthese\PhpSerial\PhpSerial::class, 'PhpSerial');
}

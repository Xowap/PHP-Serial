# Installation

## Requirements

| Requirement | Details |
| --- | --- |
| PHP | 8.1 or newer. The test matrix covers PHP 8.1, 8.2, 8.3 and 8.4. |
| `psr/log` | Installed automatically by Composer (`^1.1 \|\| ^2.0 \|\| ^3.0`). It is the only runtime dependency. |
| `proc_open()` | Must be enabled. The library uses it to run `stty` on POSIX and `mode` on Windows. |
| `stty` | Required on Linux and macOS. It is part of coreutils, busybox or the base system utilities. |
| `mode` | Required on Windows. It is a built-in command of `cmd.exe`. |
| Device access | The user running PHP must be allowed to open the serial device. On Linux this usually means membership in the `dialout` or `uucp` group. |

!!! note "PHP extensions"

    No serial-specific PHP extension is needed. The library opens the device
    with `fopen()` and configures it through the operating system utilities.
    The `psr/log` package is a Composer dependency, not a PHP extension.

## Install with Composer

```bash
composer require hyperthese/php-serial
```

To stay on the 2.x line explicitly:

```bash
composer require "hyperthese/php-serial:^2.0"
```

Composer registers the PSR-4 autoloader for `Hyperthese\PhpSerial\` and loads
`src/deprecated.php`, which exposes the legacy global `PhpSerial` alias for
code that has not been migrated yet.

## Manual installation

Without Composer, copy the `src/` directory into your project and register a
PSR-4 autoloader for the namespace:

```php
<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Hyperthese\\PhpSerial\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});
```

The `psr/log` interfaces must also be available. Install them with Composer or
provide the `Psr\Log\LoggerInterface` definition yourself. The legacy global
alias is not registered by the manual autoloader; add it explicitly if needed:

```php
class_alias(\Hyperthese\PhpSerial\PhpSerial::class, 'PhpSerial');
```

## Verify the installation

Create a short check script:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Hyperthese\PhpSerial\PhpSerial;
use Hyperthese\PhpSerial\Platform\PlatformFactory;

printf("PHP Serial %s\n", PhpSerial::VERSION);

$platform = PlatformFactory::create();
printf("Platform: %s\n", $platform->name());
printf("Configuration utility available: %s\n", $platform->isAvailable() ? 'yes' : 'no');
```

Run it with the same PHP binary and user that will run the application:

```bash
php check.php
```

A working POSIX installation prints something similar to:

```text
PHP Serial 2.0.0
Platform: linux
Configuration utility available: yes
```

On Windows the platform name is `windows` and `isAvailable()` always returns
`true`, because `mode` is part of the operating system.

You can also confirm that the classes autoload:

```bash
php -r "require 'vendor/autoload.php'; var_dump(class_exists('Hyperthese\\PhpSerial\\PhpSerial'));"
```

!!! tip "Check the web server user too"

    Running the check from your shell does not prove that Apache, php-fpm or a
    cron job can see `stty` or the device. Run the same script as the service
    user, or check the deployment notes in [Platforms](platforms.md).

## Upgrading from 1.x

```bash
composer require "hyperthese/php-serial:^2.0"
```

PHP 8.1 or newer is mandatory, and the old `include 'PhpSerial.php'` workflow
is gone. Existing code can keep using the global `PhpSerial` class through the
Composer alias, but new code should import
`Hyperthese\PhpSerial\PhpSerial`. The public properties, `setSetserialFlag()`
and the old warning-based error handling were removed; see
[Migration from 1.x](migration.md) before deploying.

!!! warning "Configuration timing changed"

    In 2.x, `conf*()` methods only update pending settings. They are applied by
    `deviceOpen()`, and calling them while the port is open throws a
    `ConfigurationException`. Close the device before reconfiguring it.

PHP Serial
==========

PHP Serial was written at a time where I did not know any other language than
PHP and I started to get seriously bored with its abilities.

I somehow got hold of a « Citizen C2202-PD » point-of-sale display, and I wanted
to play around with it. I also managed to get the documentation of it, and
created a convenience class to access the serial port though the Linux file.

Afterwards, I posted it to [PHP Classes](http://www.phpclasses.org/package/3679-PHP-Communicate-with-a-serial-port.html),
and this probably is what brought it any visibility.

Example
-------

```php
<?php
include 'PhpSerial.php';

// Let's start the class
$serial = new PhpSerial;

// First we must specify the device. This works on both linux and windows (if
// your linux serial device is /dev/ttyS0 for COM1, etc)
$serial->deviceSet("COM1");

// We can change the baud rate, parity, length, stop bits, flow control
$serial->confBaudRate(2400);
$serial->confParity("none");
$serial->confCharacterLength(8);
$serial->confStopBits(1);
$serial->confFlowControl("none");

// Then we need to open it
$serial->deviceOpen();

// To write into
$serial->sendMessage("Hello !");
```

State of the project
--------------------

Interestingly enough, this piece of code that is widely untested has created a
lot if interest ever since it was created, and especially nowadays with
everybody toying around with Arduinos and Raspberry Pis. I receive about 1 email
every month asking for help with the code or sending patches/suggestions.

I think that it is time for me to remove the dust off this project and to give
it a full visibility on modern tools, aka GitHub.

### Bugs

There is **lots** of bugs. I know there is. I just don't know which are they.

### Platform support

* **Linux**: the initially supported platform, the one I used. Probably the less
  buggy one.
* **MacOS**: although I never tried it on MacOS, it is similar to Linux and some
  patches were submitted to me, so I guess it is OK
* **Windows**: it seems to be working for some people, not working for some
  others. Theoretically there should be a way to get it done.

### Concerns

I have a few concerns regarding the behaviour of this code.

* Inter-platform consistency. I seriously doubt that all operations go the same
  way across all platforms.
* Read operations. Reading was never needed in my project, so all the tests I
  did on that matter were theoretic. I was also quite naive, so the API is
  probably not optimal. What we need is to re-think reading from scratch.
* Configuration done by calling functions. This is so Java. It would be much
  better to be able to pass a configuration array once and for all. Furthermore,
  I suspect that the order of call matters, which is bad.
* Auto-closing the device. There is an auto-close function that is registered
  at PHP shutdown. This sounds quite ridiculous, something has to be done about
  that.
* Use exceptions. Currently there is an heavy use of the errors system to report
  errors (2007 baby), but this is seriously lame. They have to be replaced by
  actual exceptions.

Call for contribution
---------------------

I have about 0 time to code or test this project. However, there is clearly a
need for it.

As in all open-source projects, I need people to fit this to their needs and to
contribute back their code.

What is needed, IMHO:

* Address the concerns listed above, and find new ones.
* Create a reproducible test environment for each OS, and prove that each
  feature works (basically, unit-testing).
* Report of use cases, bugs, missing features, etc.

If you feel like doing any of those, do not hesitate to create an issue or a
pull-request, I'll gladly consider consider it :)

Licence
-------

PHP Serial
Copyright (C) 2007-2014 PHP Serial's contributors (see CONTRIBUTORS file)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

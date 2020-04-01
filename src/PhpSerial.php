<?php

namespace PhpSerial;

use PhpSerial\Interfaces\BaudInterface;
use PhpSerial\Interfaces\ParityInterface;

define("SERIAL_DEVICE_NOTSET", 0);
define("SERIAL_DEVICE_SET", 1);
define("SERIAL_DEVICE_OPENED", 2);

/**
 * Serial port control class
 *
 * THIS PROGRAM COMES WITH ABSOLUTELY NO WARRANTIES !
 * USE IT AT YOUR OWN RISKS !
 *
 * @author Rémy Sanchez <remy.sanchez@hyperthese.net>
 * @author Rizwan Kassim <rizwank@geekymedia.com>
 * @thanks Aurélien Derouineau for finding how to open serial ports with windows
 * @thanks Alec Avedisyan for help and testing with reading
 * @thanks Jim Wright for OSX cleanup/fixes.
 * @copyright under GPL 2 licence
 */
class PhpSerial
{
    public ?string $device    = null;
    public ?string $winDevice = null;
    public         $dHandle   = null;
    public int     $dState    = SERIAL_DEVICE_NOTSET;
    public string  $buffer    = '';
    public string  $os        = '';

    /**
     * This var says if buffer should be flushed by sendMessage (true) or
     * manually (false)
     */
    public bool $autoFlush = true;

    public function __construct()
    {
        setlocale(LC_ALL, "en_US");
        $sysName = php_uname();
        if (substr($sysName, 0, 5) === "Linux") {
            $this->os = "linux";
            if ($this->_exec("stty") === 0) {
                register_shutdown_function([$this, "deviceClose"]);
            } else {
                trigger_error(
                    "No stty availible, unable to run.",
                    E_USER_ERROR
                );
            }
        } elseif (substr($sysName, 0, 6) === "Darwin") {
            $this->os = "osx";
            register_shutdown_function([$this, "deviceClose"]);
        } elseif (substr($sysName, 0, 7) === "Windows") {
            $this->os = "windows";
            register_shutdown_function([$this, "deviceClose"]);
        } else {
            trigger_error(
                "Host OS is neither osx, linux nor windows, unable " . "to run.",
                E_USER_ERROR
            );
            exit();
        }
    }

    //
    // OPEN/CLOSE DEVICE SECTION -- {START}
    //
    /**
     * Device set function : used to set the device name/address.
     * -> linux : use the device address, like /dev/ttyS0
     * -> osx : use the device address, like /dev/tty.serial
     * -> windows : use the COMxx device name, like COM1 (can also be used
     *     with linux)
     */
    public function deviceSet(string $device): bool
    {
        if ($this->dState !== SERIAL_DEVICE_OPENED) {
            if ($this->os === "linux") {
                if (preg_match("@^COM(\\d+):?$@i", $device, $matches)) {
                    $device = "/dev/ttyS" . ($matches[1] - 1);
                }
                if ($this->_exec("stty -F " . $device) === 0) {
                    $this->device = $device;
                    $this->dState = SERIAL_DEVICE_SET;

                    return true;
                }
            } elseif ($this->os === "osx") {
                if ($this->_exec("stty -f " . $device) === 0) {
                    $this->device = $device;
                    $this->dState = SERIAL_DEVICE_SET;

                    return true;
                }
            } elseif ($this->os === "windows") {
                if (preg_match("@^COM(\\d+):?$@i", $device, $matches) and $this->_exec(
                        exec("mode " . $device . " xon=on BAUD=9600")
                    ) === 0) {
                    $this->winDevice = "COM" . $matches[1];
                    $this->device    = "\\.com" . $matches[1];
                    $this->dState    = SERIAL_DEVICE_SET;

                    return true;
                }
            }
            trigger_error("Specified serial port is not valid", E_USER_WARNING);

            return false;
        } else {
            trigger_error(
                "You must close your device before to set an other " . "one",
                E_USER_WARNING
            );

            return false;
        }
    }

    /**
     * Opens the device for reading and/or writing.
     */
    public function deviceOpen(string $mode = "r+b"): bool
    {
        if ($this->dState === SERIAL_DEVICE_OPENED) {
            trigger_error("The device is already opened", E_USER_NOTICE);

            return true;
        }
        if ($this->dState === SERIAL_DEVICE_NOTSET) {
            trigger_error(
                "The device must be set before to be open",
                E_USER_WARNING
            );

            return false;
        }
        if (!preg_match("@^[raw]\\+?b?$@", $mode)) {
            trigger_error(
                "Invalid opening mode : " . $mode . ". Use fopen() modes.",
                E_USER_WARNING
            );

            return false;
        }
        $this->dHandle = @fopen($this->device, $mode);
        if ($this->dHandle !== false) {
            stream_set_blocking($this->dHandle, 0);
            $this->dState = SERIAL_DEVICE_OPENED;

            return true;
        }
        $this->dHandle = null;
        trigger_error("Unable to open the device", E_USER_WARNING);

        return false;
    }

    /**
     * Closes the device
     */
    public function deviceClose(): bool
    {
        if ($this->dState !== SERIAL_DEVICE_OPENED) {
            return true;
        }
        if (fclose($this->dHandle)) {
            $this->dHandle = null;
            $this->dState  = SERIAL_DEVICE_SET;

            return true;
        }
        trigger_error("Unable to close the device", E_USER_ERROR);

        return false;
    }

    //
    // OPEN/CLOSE DEVICE SECTION -- {STOP}
    //
    //
    // CONFIGURE SECTION -- {START}
    //
    /**
     * Configure the Baud Rate
     * Possible rates : 110, 150, 300, 600, 1200, 2400, 4800, 9600, 38400,
     * 57600 and 115200.
     */
    public function confBaudRate(int $rate): bool
    {
        if ($this->dState !== SERIAL_DEVICE_SET) {
            trigger_error(
                "Unable to set the baud rate : the device is " . "either not set or opened",
                E_USER_WARNING
            );

            return false;
        }
        $validBauds = [
            BaudInterface::RATE_110    => 11,
            BaudInterface::RATE_150    => 15,
            BaudInterface::RATE_300    => 30,
            BaudInterface::RATE_600    => 60,
            BaudInterface::RATE_1200   => 12,
            BaudInterface::RATE_2400   => 24,
            BaudInterface::RATE_4800   => 48,
            BaudInterface::RATE_9600   => 96,
            BaudInterface::RATE_19200  => 19,
            BaudInterface::RATE_38400  => 38400,
            BaudInterface::RATE_57600  => 57600,
            BaudInterface::RATE_115200 => 115200
        ];
        if (isset($validBauds[$rate])) {
            if ($this->os === "linux") {
                $ret = $this->_exec(
                    "stty -F " . $this->device . " " . (int)$rate,
                    $out
                );
            } elseif ($this->os === "osx") {
                $ret = $this->_exec(
                    "stty -f " . $this->device . " " . (int)$rate,
                    $out
                );
            } elseif ($this->os === "windows") {
                $ret = $this->_exec(
                    "mode " . $this->winDevice . " BAUD=" . $validBauds[$rate],
                    $out
                );
            } else {
                return false;
            }
            if ($ret !== 0) {
                trigger_error(
                    "Unable to set baud rate: " . $out[1],
                    E_USER_WARNING
                );

                return false;
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * Configure parity.
     * Modes : odd, even, none
     */
    public function confParity(string $parity): bool
    {
        if ($this->dState !== SERIAL_DEVICE_SET) {
            trigger_error(
                "Unable to set parity : the device is either not set or opened",
                E_USER_WARNING
            );

            return false;
        }
        $args = [
            ParityInterface::NONE => "-parenb",
            ParityInterface::ODD  => "parenb parodd",
            ParityInterface::EVEN => "parenb -parodd",
        ];
        if (!isset($args[$parity])) {
            trigger_error("Parity mode not supported", E_USER_WARNING);

            return false;
        }
        if ($this->os === "linux") {
            $ret = $this->_exec(
                "stty -F " . $this->device . " " . $args[$parity],
                $out
            );
        } elseif ($this->os === "osx") {
            $ret = $this->_exec(
                "stty -f " . $this->device . " " . $args[$parity],
                $out
            );
        } else {
            $ret = $this->_exec(
                "mode " . $this->winDevice . " PARITY=" . $parity[0],
                $out
            );
        }
        if ($ret === 0) {
            return true;
        }
        trigger_error("Unable to set parity : " . $out[1], E_USER_WARNING);

        return false;
    }

    /**
     * Sets the length of a character.
     *
     * $length length of a character (5 <= length <= 8)
     */
    public function confCharacterLength(int $length): bool
    {
        if ($this->dState !== SERIAL_DEVICE_SET) {
            trigger_error(
                "Unable to set length of a character : the device " . "is either not set or opened",
                E_USER_WARNING
            );

            return false;
        }
        $length = (int)$length;
        if ($length < 5) {
            $length = 5;
        } elseif ($length > 8) {
            $length = 8;
        }
        if ($this->os === "linux") {
            $ret = $this->_exec(
                "stty -F " . $this->device . " cs" . $length,
                $out
            );
        } elseif ($this->os === "osx") {
            $ret = $this->_exec(
                "stty -f " . $this->device . " cs" . $length,
                $out
            );
        } else {
            $ret = $this->_exec(
                "mode " . $this->winDevice . " DATA=" . $length,
                $out
            );
        }
        if ($ret === 0) {
            return true;
        }
        trigger_error(
            "Unable to set character length : " . $out[1],
            E_USER_WARNING
        );

        return false;
    }

    /**
     * Sets the length of stop bits.
     *
     *  $length the length of a stop bit. It must be either 1,
     *                       1.5 or 2. 1.5 is not supported under linux and on
     *                       some computers.
     */
    public function confStopBits(float $length): bool
    {
        if ($this->dState !== SERIAL_DEVICE_SET) {
            trigger_error(
                "Unable to set the length of a stop bit : the " . "device is either not set or opened",
                E_USER_WARNING
            );

            return false;
        }
        if ($length != 1 and $length != 2 and $length != 1.5 and !($length == 1.5 and $this->os === "linux")) {
            trigger_error(
                "Specified stop bit length is invalid",
                E_USER_WARNING
            );

            return false;
        }
        if ($this->os === "linux") {
            $ret = $this->_exec(
                "stty -F " . $this->device . " " . (($length == 1) ? "-" : "") . "cstopb",
                $out
            );
        } elseif ($this->os === "osx") {
            $ret = $this->_exec(
                "stty -f " . $this->device . " " . (($length == 1) ? "-" : "") . "cstopb",
                $out
            );
        } else {
            $ret = $this->_exec(
                "mode " . $this->winDevice . " STOP=" . $length,
                $out
            );
        }
        if ($ret === 0) {
            return true;
        }
        trigger_error(
            "Unable to set stop bit length : " . $out[1],
            E_USER_WARNING
        );

        return false;
    }

    /**
     * Configures the flow control
     *
     * @param string $mode Set the flow control mode. Availible modes :
     *                      -> "none" : no flow control
     *                      -> "rts/cts" : use RTS/CTS handshaking
     *                      -> "xon/xoff" : use XON/XOFF protocol
     *
     * @return bool
     */
    public function confFlowControl($mode)
    {
        if ($this->dState !== SERIAL_DEVICE_SET) {
            trigger_error(
                "Unable to set flow control mode : the device is " . "either not set or opened",
                E_USER_WARNING
            );

            return false;
        }
        $linuxModes   = [
            "none"     => "clocal -crtscts -ixon -ixoff",
            "rts/cts"  => "-clocal crtscts -ixon -ixoff",
            "xon/xoff" => "-clocal -crtscts ixon ixoff"
        ];
        $windowsModes = [
            "none"     => "xon=off octs=off rts=on",
            "rts/cts"  => "xon=off octs=on rts=hs",
            "xon/xoff" => "xon=on octs=off rts=on",
        ];
        if ($mode !== "none" and $mode !== "rts/cts" and $mode !== "xon/xoff") {
            trigger_error("Invalid flow control mode specified", E_USER_ERROR);

            return false;
        }
        if ($this->os === "linux") {
            $ret = $this->_exec(
                "stty -F " . $this->device . " " . $linuxModes[$mode],
                $out
            );
        } elseif ($this->os === "osx") {
            $ret = $this->_exec(
                "stty -f " . $this->device . " " . $linuxModes[$mode],
                $out
            );
        } else {
            $ret = $this->_exec(
                "mode " . $this->winDevice . " " . $windowsModes[$mode],
                $out
            );
        }
        if ($ret === 0) {
            return true;
        } else {
            trigger_error(
                "Unable to set flow control : " . $out[1],
                E_USER_ERROR
            );

            return false;
        }
    }

    /**
     * Sets a setserial parameter (cf man setserial)
     * NO MORE USEFUL !
     *    -> No longer supported
     *    -> Only use it if you need it
     *
     * @param string $param parameter name
     * @param string $arg parameter value
     *
     * @return bool
     */
    public function setSetserialFlag($param, $arg = "")
    {
        if (!$this->_ckOpened()) {
            return false;
        }
        $return = exec(
            "setserial " . $this->device . " " . $param . " " . $arg . " 2>&1"
        );
        if (strpos($return, 'I') === 0) {
            trigger_error("setserial: Invalid flag", E_USER_WARNING);

            return false;
        } elseif (strpos($return, '/') === 0) {
            trigger_error("setserial: Error with device file", E_USER_WARNING);

            return false;
        } else {
            return true;
        }
    }

    //
    // CONFIGURE SECTION -- {STOP}
    //
    //
    // I/O SECTION -- {START}
    //
    /**
     * Sends a string to the device
     *
     * @param string $str string to be sent to the device
     * @param float  $waitForReply time to wait for the reply (in seconds)
     */
    public function sendMessage($str, $waitForReply = 0.1)
    {
        $this->buffer .= $str;
        if ($this->autoFlush === true) {
            $this->serialflush();
        }
        usleep((int)($waitForReply * 1000000));
    }

    /**
     * Reads the port until no new datas are availible, then return the content.
     *
     * @param int $count Number of characters to be read (will stop before
     *                   if less characters are in the buffer)
     *
     * @return string
     */
    public function readPort(int $count = 0): string
    {
        if ($this->dState !== SERIAL_DEVICE_OPENED) {
            trigger_error("Device must be opened to read it", E_USER_WARNING);

            return false;
        }
        if ($this->os === "linux" || $this->os === "osx") {
            // Behavior in OSX isn't to wait for new data to recover, but just
            // grabs what's there!
            // Doesn't always work perfectly for me in OSX
            $content = "";
            $i       = 0;
            if ($count !== 0) {
                do {
                    if ($i > $count) {
                        $content .= fread($this->dHandle, ($count - $i));
                    } else {
                        $content .= fread($this->dHandle, 128);
                    }
                } while (($i += 128) === strlen($content));
            } else {
                do {
                    $content .= fread($this->dHandle, 128);
                } while (($i += 128) === strlen($content));
            }

            return $content;
        } elseif ($this->os === "windows") {
            // Windows port reading procedures still buggy
            $content = "";
            $i       = 0;
            if ($count !== 0) {
                do {
                    if ($i > $count) {
                        $content .= fread($this->dHandle, ($count - $i));
                    } else {
                        $content .= fread($this->dHandle, 128);
                    }
                } while (($i += 128) === strlen($content));
            } else {
                do {
                    $content .= fread($this->dHandle, 128);
                } while (($i += 128) === strlen($content));
            }

            return $content;
        }

        return false;
    }

    /**
     * Flushes the output buffer
     * Renamed from flush for osx compat. issues
     *
     * @return bool
     */
    public function serialflush()
    {
        if (!$this->_ckOpened()) {
            return false;
        }
        if (fwrite($this->dHandle, $this->buffer) !== false) {
            $this->buffer = "";

            return true;
        } else {
            $this->buffer = "";
            trigger_error("Error while sending message", E_USER_WARNING);

            return false;
        }
    }

    //
    // I/O SECTION -- {STOP}
    //
    //
    // INTERNAL TOOLKIT -- {START}
    //
    public function _ckOpened()
    {
        if ($this->dState !== SERIAL_DEVICE_OPENED) {
            trigger_error("Device must be opened", E_USER_WARNING);

            return false;
        }

        return true;
    }

    public function _ckClosed()
    {
        if ($this->dState === SERIAL_DEVICE_OPENED) {
            trigger_error("Device must be closed", E_USER_WARNING);

            return false;
        }

        return true;
    }

    public function _exec($cmd, &$out = null)
    {
        $desc = [
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ];
        $proc = proc_open($cmd, $desc, $pipes);
        $ret  = stream_get_contents($pipes[1]);
        $err  = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $retVal = proc_close($proc);
        if (func_num_args() == 2) {
            $out = [$ret, $err];
        }

        return $retVal;
    }

    //
    // INTERNAL TOOLKIT -- {STOP}
    //
}


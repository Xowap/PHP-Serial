<?php

/*

This example illustrates some basic communication with a device via an HTML form.  In this example,
the device being communicated with is a StarTech.com video switcher, model VS421CPNTA.

http://www.startech.com/item/VS421CPNTA-4-Port-Component-Video-Switch-with-RS232.aspx

This device uses RS232 commands to switch between 1 of 4 inputs by sending an 'I' followed by a
single digit 1-4, and will echo back this same string in response.

Port settings are 9600-8-N-1, set specifically in the code below.  Failure to specifically set these
may result in incorrect defaults being used.

A timer is used in the function that reads the serial input, .5 seconds is the allotted time for the
unit to echo back a response, other devices may need more time for their replies depending on port
speed and the amount of data being sent back, this is just an example and is sufficient for this
device.  No end of line character is sent, but we could instead have used a loop to read until a
number was received, again, this code is just an example.

We then echo back the result received, and present the form again for additional input changes.

*/

function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());

    return ((float) $usec + (float) $sec);
}

$the_input  = $_POST['the_input'];

if ($the_input == '') {
    echo "<div id='newrequestbox'>";
    echo "<form id='FormName' name='FormName' action='example_VS421CPNTA.php' method='post'>
            <table width=500>
                <tr>
                    <td>Switch to input? :</td>
                    <td><input type=text name=the_input  maxlength=30 size=30></td>
                    <td><input type=submit value='Switch'></td>
                </tr>
            </table>
        </form>";
    echo "</div>";
} else {

include 'PhpSerial.php';

// Let's start the class
$serial = new PhpSerial;

// First we must specify the device. This works on both linux and windows (if
// your linux serial device is /dev/ttyS0 for COM1, etc)
// $serial->deviceSet("COM1");
$serial->deviceSet("/dev/cu.usbserial-FTDY7ID6");

// We can change the baud rate, parity, length, stop bits, flow control
$serial->confBaudRate(2400);
$serial->confParity("none");
$serial->confCharacterLength(8);
$serial->confStopBits(1);
$serial->confFlowControl("none");

// Then we need to open it
$serial->deviceOpen();

// To write into
$serial->sendMessage("I".$the_input);

// Or to read from
$read = '';
$theResult = '';
$start = microtime_float();

while ( ($read == '') && (microtime_float() <= $start + 0.5) ) {
    $read = $serial->readPort();
    if ($read != '') {
        $theResult .= $read;
        $read = '';
    }
}

// If you want to change the configuration, the device must be closed
$serial->deviceClose();

// etc...

echo "Read data: ".$theResult."<br>";

echo "<form id='FormName' name='FormName' action='example_VS421CPNTA.php' method='post'>
        <table width=500>

            <tr>
                <td>Switch to input? :</td>
                <td><input type=text name=the_input  maxlength=30 size=30></td>
                <td><input type=submit value='Switch'></td>
            </tr>
        </table>
    </form>";
}

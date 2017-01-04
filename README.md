## Homeautod

### Backup database
Use mysqldump to backup your database often!

mysqldump homeautod > /var/lib/homeautod/homeauto-db-backup.sql

(You might need to add the -u and -p parameter to mysqldump depending
on your setup)

### Adding zones
Before you add devices you need to add zones in which the devices
and endpoints will reside.

had_cmd -c addzone 'My first zone'

To list all zones use:
had_cmd -c listzones

### Adding devices
New devices can be added with had_cmd

had_cmd -c adddevice -d <driver> -p <path>

To list all devices use:
had_cmd -c listdevices

### Basics of homeautod
Homeautod is a daemon or service that keeps control of one or more
devices acting like an alarmsystem or homeautomation system.

#### Zones
Zones is a virtual object which represents an area in which devices
and endpoints reside. A relay which turns on the lights in your garage
would have the zone 'Garage' and a hadtemp device in your basement would
be placed in the zone 'Basement'.

#### Devices
Devices define objects which represents each pysical device connected to
homeautod. It can be a Brainbox ED-588 device, a gsmmodem, a hadtemp
device or an rtsp/h264 compatible IP-camera. Support for more devices
will be added in the future.

Path should be set appropriately:
  * A dns name or IP-number
  * A tty (for example: /dev/ttyUSB1)

#### Endpoints
Endpoints are added automatically and represent each output or input
on the devices.

#### Events, Triggers and Actions
An event is an object that collects triggers and actions. Each
event can contain multiple triggers and multiple actions. For example,
multiple motion sensors in your basement can trigger the same event.

One event can for example be to turn on lights via relays or send a
message to one or more users.

Actions can set states on endpoints and hold them for a predefined length
of time. add_time defines how much time is added to the queue. min_time
defines a minimum times to which the queue will be prolonged to.

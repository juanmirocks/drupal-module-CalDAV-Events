# CalDAV reader & writer (drupal module)

React upon events and manage calendar information from a CalDAV server.

Features:
* Read events from a CalDAV calendar to:
  * Create nodes with the event's information.
  * Announce the events via email.
  * Works well with other modules. For example, you can use the [Twitter Module](https://drupal.org/project/twitter) to tweet events.
* Create weekly events into the calendar. For example, you can send weekly invitations to a list of users who should hold a presentation


## Use
Go to the Module's configuration page. The configuration should be self-explanatory.

### Dependencies
None

### Drupal Version
This module is for Drupal 7.

### Status & Testability
This module has been used in production in a medium-size site since 2013.

In theory, every CalDAV server should work. Popular CalDAV servers are for instance [DAViCal](http://www.davical.org/) or [Google Calendar](https://support.google.com/calendar/?hl=en#topic=3417927). In practice, the module was tested with the following servers: DAViCal.

### Maintenance
I wrote this module for my own use. If you need maintenance or want to extend the module, contact me at: https://juanmi.rocks.


## Development
The main/entry code is the PHP module file [`caldav_events.module`][caldav_events.module].
## CalDAV reader & writer (drupal module)

React upon events and manage calendar information from a CalDAV server.

Features:
* Read events from a CalDAV calendar to:
  * Create nodes with the event's information.
  * Announce the events via email.
  * Works well with other modules. For example, you can use the [Twitter Module](https://drupal.org/project/twitter) to tweet events.
* Create weekly events into the calendar. For example, you can send weekly invitations to a list of users who should hold a presentation


### Dependencies
None


### Use
Go to the Module's configuration page. The configuration should be self-explanatory.


### Status & Testability
This is a drupal sandbox project. However, the module has been used in production in a medium-size site since 2013.

In theory, every CalDAV server should work. Popular CalDAV servers are for instance [DAViCal](http://www.davical.org/) or [Google Calendar](https://support.google.com/calendar/?hl=en#topic=3417927). In practice, the module was tested with the following servers: DAViCal.


#### Drupal Version
This module is for Drupal 7.


### Maintenance
I wrote this module for my own needs. I can only maintain and extend the module if people show interest.

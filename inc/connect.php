<?php

require_once('utils.php');
require_once('caldav-client-v2.php');
require_once('iCalendar.php');

/**
 * Event object from an iCalendar.
 *
 * Named after the VEVENT component in the iCalendar format.
 */
class VEvent {
  private $icalendar;
  private $vevent;

  function __construct($icalendar_text) {
    $this->icalendar = new iCalendar(array('icalendar' => $icalendar_text));

    $aux = $this->icalendar->GetComponents('VEVENT');
    //Assert with opt string added in >= PHP 5.4.8 "I expect only one VEVENT for this iCalendar");
    assert(sizeof($aux) == 1);
    $this->vevent = $aux[0];
  }

  function get_property($name) {
    $props = $this->get_properties($name);
    if (empty($props))
      return "";
    else
      return reset($props);
  }

  function get_properties($name) {
    $props = $this->vevent->GetProperties($name);
    $ret = array();
    foreach ($props as $key => $prop) {
      array_push($ret, $prop->content);
    }
    return $ret;
  }

  /**
   * Assumed string date as written in iCalendar, with the ISO 8601 format, and
   * perhaps some small variations.
   */
  private static $date_format = 'Ymd\THis';
  private function to_date($string) {
    $date = $string;
    if ($date[strlen($date)-1] == 'Z') {
      $date = substr($date, 0, strlen($date)-1);
    }

    //TODO, the timezone could be in the DT arrays
    $timezone = null;

    $aux = $this->icalendar->GetComponents('VTIMEZONE');
    if (!empty($aux)) {
      $aux = $aux[0]->GetProperties('TZID');
      if (!empty($aux)) {
        $timezone = $aux[0]->content;
      }
    }
    if (!$timezone) {
      $timezone = 'UTC';
    }

    $timezone = new DateTimeZone($timezone);

    return DateTime::createFromFormat(self::$date_format, $date, $timezone);
  }

  function summary() { return $this->get_property('SUMMARY'); }
  function description() { return $this->get_property('DESCRIPTION'); }
  function location() { return $this->get_property('LOCATION'); }
  function start() { return $this->to_date($this->get_property('DTSTART')); }
  function end() { return $this->to_date($this->get_property('DTEND')); }
  function attendees() { return $this->get_properties('ATTENDEE'); }

  function attendeesOnlyValidEmails() {
    $attendees = $this->get_properties('ATTENDEE');
    $ret = array();
    foreach ($attendees as $a) {
      if (preg_match('/^mailto:(.+)/', $a, $match)) {
        array_push($ret, $match[1]);;
      }
    }
    return $ret;
  }
}

/**
 * Test if an authorized connection can be made to the calendar.
 *
 * @param params
 *   array with properties {url, username, password}
 *
 * @return array with properties:
 *   * `connected`: connection was successfully establish true/false
 *   * `response`: full HEAD response from the server.
 *
 */
function _test_connection($params) {
  $client = new CalDAVClient($params['url'], $params['username'], $params['password']);
  $response = $client->DoHEADRequest(null);
  return array('connected' => _startsWith($response, 'HTTP/1.1 200 OK'), 'response' => $response);
}

/**
 * Return array of events that match the search parameters.
 *
 * The returned events are sorted by start date desc, that is, the latest start date comes first.
 *
 * @return
 *  array keyed by the event's unique and unchangeable `href` with fields:
 *    * `etag`: aka event's version hash
 *    * `icalendar`: icalendar text representation of the event
 */
function _read_events_from_server($params) {
  $client = new CalDAVClient($params['url'], $params['username'], $params['password']);

  $event_name=$params['event_name'];
  $start=$params['event_start'];
  $end=$params['event_end'];

  $range_filter = "<C:time-range start=\"$start\" end=\"$end\"/>";
  $summary_filter = "<C:text-match>$event_name</C:text-match>";
  $filter = "<C:filter><C:comp-filter name=\"VCALENDAR\"><C:comp-filter name=\"VEVENT\">$range_filter<C:prop-filter name=\"SUMMARY\">$summary_filter</C:prop-filter></C:comp-filter></C:comp-filter></C:filter>";

  $results = $client->DoCalendarQuery($filter);

  $updated_events = array();
  foreach ($results as $item) {
    $icalendar_text = $item['data'];
    //Note, the href identifies the event uniquely and this does not change. The etag identifies the
    //event's version and so can change. Therefore: 2 events with same href but different etags
    //represent different versions of the same event.
    $updated_events[$item['href']] = array('etag' => $item['etag'], 'icalendar' => $icalendar_text, 'vevent' => new VEvent($icalendar_text));
  }

  //Suppress possible errors because PHP bug: http://stackoverflow.com/questions/3235387/usort-array-was-modified-by-the-user-comparison-function
  @uasort($updated_events, '_sort_events_LIFO');

  return $updated_events;
}

function _sort_events_LIFO($a, $b) {
  $a = $a['vevent'];
  $b = $b['vevent'];
  if ($a->start() < $b->start()) {
    return +1;
  } else {
    return -1;
  }
}

function _sort_events_FIFO($a, $b) {
  return -(_sort_events_LIFO($a, $b));
}

function _set_status(&$elem, $status) {
  $elem['status'] = $status;
  return $elem;
}

function _fill_events_status($olds, &$news) {
  $ret = array();

  foreach ($news as $new_key => $new) {
    $exists_previous = @ $olds[$new_key];

    if ($exists_previous) {
      if (@ $exists_previous['keep']) {
        $new['keep'] = $exists_previous['keep'];
        $news[$new_key] = $new;
      }

      if ($exists_previous['etag'] == $new['etag']) {
        $ret[$new_key] = _set_status($new, 'same');
      } else {
        $ret[$new_key] = _set_status($new, 'modified');
      }
    }
    else {
      $ret[$new_key] = 'new';
      $ret[$new_key] = _set_status($new, 'new');
    }
  }

  foreach ($olds as $old_key => $old) {
    $got = @ $news[$old_key];
    if (!$got) {
      $ret[$old_key] = _set_status($old, 'deleted');
    }
  }

  @uasort($ret, '_sort_events_FIFO');

  return $ret;
}
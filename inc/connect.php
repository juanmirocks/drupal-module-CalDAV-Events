<?php

require_once('caldav-client-v2.php');
require_once('iCalendar.php');

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
    $updated_events[$item['href']] = array('etag' => $item['etag'], 'icalendar' => $icalendar_text);
  }

  //Suppress possible errors because PHP bug: http://stackoverflow.com/questions/3235387/usort-array-was-modified-by-the-user-comparison-function
  @uasort($updated_events, '_sort_events');

  return $updated_events;
}

function _sort_events($a, $b) {
  $a = new VEvent($a['icalendar']);
  $b = new VEvent($b['icalendar']);
  return ($a->start() < $b->start());
}

function _check_changes($olds, &$news) {
  $same_events = array();
  $modified_events = array();
  $new_events = array();
  $deleted_events = array();

  foreach ($news as $new_key => $new) {
    $exists_previous = @ $olds[$new_key];

    if ($exists_previous) {
      if (@ $exists_previous['keep']) {
        $new['keep'] = $exists_previous['keep'];
        $news[$new_key] = $new;
      }

      if ($exists_previous['etag'] == $new['etag'])
        $same_events[$new_key] = $new;
      else
        $modified_events[$new_key] = $new;
    }
    else
      $new_events[$new_key] = $new;
  }

  foreach ($olds as $old_key => $old) {
    $got = @ $news[$old_key];
    if (!$got)
      $deleted_events[$old_key] = $old;
  }

  return array(
               'same' => $same_events,
               'modified' => $modified_events,
               'new' => $new_events,
               'deleted' => $deleted_events);
}
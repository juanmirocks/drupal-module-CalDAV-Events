<?php

require_once('caldav-client-v2.php');
require_once('iCalendar.php');

class VEvent {
  private $icalendar;
  private $vevent;

  function __construct($icalendar_text) {
    $this->icalendar = new iCalendar(array('icalendar' => $icalendar_text));

    $aux = $this->icalendar->GetComponents('VEVENT');
    assert(sizeof($aux) == 1, "I expect only one VEVENT for this iCalendar");
    $this->vevent = $aux[0];
    //print_r($this->vevent);
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

  function summary() { return $this->get_property('SUMMARY'); }
  function description() { return $this->get_property('DESCRIPTION'); }
  function location() { return $this->get_property('LOCATION'); }
  function start() { return $this->get_property('DTSTART'); }
  function end() { return $this->get_property('DTEND'); }
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

function connect($params) {
  $client = new CalDAVClient($params['server_url'], $params['username'], $params['password']);

  $start=$params['start'];
  $end=$params['end'];
  $event_prefix=$params['event_prefix'];

  $range_filter = "<C:time-range start=\"$start\" end=\"$end\"/>";
  $summary_filter = "<C:text-match>$event_prefix</C:text-match>";
  $filter = "<C:filter><C:comp-filter name=\"VCALENDAR\"><C:comp-filter name=\"VEVENT\">$range_filter<C:prop-filter name=\"SUMMARY\">$summary_filter</C:prop-filter></C:comp-filter></C:comp-filter></C:filter>";

  $results = $client->DoCalendarQuery($filter);

  $updated_events = array();
  foreach ($results as $item) {
    $icalendar_text = $item['data'];
    $updated_events[$item['href']] = array('etag' => $item['etag'], 'data' => $icalendar_text);
  }

  //check_changes($previous_events, $updated_events);

  return $updated_events;
}

function check_changes($olds, $news) {
  $same_events=array();
  $modified_events=array();
  $new_events=array();
  $deleted_events=array();

  foreach ($news as $new_key => $new) {
    $got = @ $olds[$new_key];
    if ($got) {
      if ($got['etag'] == $new['etag'])
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

  /* echo "Same: "; print_r($same_events); */
  /* echo "Modified: "; print_r($modified_events); */
  /* echo "New: "; print_r($new_events); */
  /* echo "Deleted: "; print_r($deleted_events); */
}

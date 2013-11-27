<?php
$date=$start_date->format('M d');

print "<span class=\"event-date\">$date</span>&nbsp; <strong>$unprefixed_summary</strong><br />
        <span class=\"event-description\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; $description</span>";

$customary_day='Thursday';
$actual_day=$start_date->format('l');
$customary_hour='2:30 pm';
$actual_hour=$start_date->format('g:i a');

if ($customary_day !== $actual_day || $customary_hour !== $actual_hour) {
  print "<p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <em>! $actual_day $actual_hour !</em></p>";
}

?>

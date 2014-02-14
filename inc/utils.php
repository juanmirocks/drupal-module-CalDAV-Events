<?php

function _startsWith($string, $substring) {
  return !strncmp($string, $substring, strlen($substring));
}
<?php
// church/modules/events/calendar.php
// Alias — redirects to the main events index
header('Location: /church/modules/events/index.php' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit;
<?PHP
/* Copyright 2012-2023, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * Plugin development contribution by gfjardim
 */
?>
<?
$plugin  = 'dynamix.system.autofan';
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

$new = isset($default) ? array_replace_recursive($_POST, $default) : $_POST;
foreach ($new as $key => $value) {
  if (!strlen($value)) continue;
  switch ($key) {
  case '#prefix':
    parse_str($value, $prefix);
    $options = '';
    break;
  case 'service':
  case 'temp_source':
  case 'hwmon_name':
  case 'filter_mode':
  case 'exclude':
    // handled separately below or stored in cfg only
    break;
  default:
    if ($key[0]!='#') $options .= (isset($prefix[$key]) ? "-{$prefix[$key]} " : "")."$value ";
    break;
  }
}
// Build drive filter flag based on mode
$filter_mode   = $new['filter_mode'] ?? 'exclude';
$filter_drives = trim($new['exclude'] ?? '');
if ($filter_drives) {
  $flag = ($filter_mode === 'include') ? '-I' : '-e';
  $options .= "$flag $filter_drives ";
}
$autofan = "$docroot/plugins/$plugin/scripts/rc.autofan";
exec("$autofan stop >/dev/null");
$keys['options'] = trim($options);
$_POST['#command'] = $autofan;
$_POST['#arg'][1] = 'start';
?>

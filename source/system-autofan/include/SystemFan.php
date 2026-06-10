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
 *
 * Version log:
 * Version 1.6   Modified by InfinityMod - added multifan support
 * Version 1.7   Modified by mrjacobarussell - added temp sensor enumeration (list_temp)
 */
?>
<?
$plugin = 'dynamix.system.autofan';
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

function scan_dir($dir) {
  $out = [];
  foreach (array_diff(scandir($dir), ['.','..']) as $f) $out[] = realpath($dir).'/'.$f;
  return $out;
}
function list_fan() {
  $out = [];
  exec("find /sys/devices -type f -iname 'fan[0-9]_input' -exec dirname \"{}\" +|uniq", $chips);
  foreach ($chips as $chip) {
    $name = is_file("$chip/name") ? file_get_contents("$chip/name") : false;
    if ($name) foreach (preg_grep("/fan\d+_input/", scan_dir($chip)) as $fan) $out[] = ['chip'=>trim($name), 'name'=>end(explode('/',$fan)), 'sensor'=>$fan , 'rpm'=>intval(file_get_contents($fan))];
  }
  return $out;
}
function list_temp() {
  $out = [];
  exec("find /sys/devices -type f -name 'temp[0-9]_input' -exec dirname \"{}\" +|uniq", $chips);
  foreach ($chips as $chip) {
    $name = is_file("$chip/name") ? trim(file_get_contents("$chip/name")) : '';
    if (!$name) continue;
    foreach (preg_grep("/temp\d+_input$/", scan_dir($chip)) as $temp) {
      $label_file = preg_replace('/_input$/', '_label', $temp);
      $label = is_file($label_file) ? trim(file_get_contents($label_file)) : end(explode('/',$temp));
      $raw = intval(file_get_contents($temp));
      $out[] = [
        'chip'   => $name,
        'name'   => end(explode('/',$temp)),
        'label'  => $label,
        'sensor' => $temp,
        'temp'   => round($raw / 1000, 1),
      ];
    }
  }
  return $out;
}

switch ($_GET['op']??'') {
case 'detect':
  $pwm = $_GET['pwm']??'';
  if (is_file($pwm)) {
    $pwm_dir = dirname(realpath($pwm));
    $default_method = file_get_contents($pwm."_enable");
    $default_rpm    = file_get_contents($pwm);
    file_put_contents($pwm."_enable", "1");
    file_put_contents($pwm, "150");
    sleep(3);
    $all_init  = list_fan();
    file_put_contents($pwm, "255");
    sleep(3);
    $all_final = list_fan();
    file_put_contents($pwm, $default_rpm);
    file_put_contents($pwm."_enable", $default_method);
    // Only consider fans on the same hwmon chip as the selected PWM controller.
    // Without this filter, a fan on a different chip can win the race when its
    // RPM happens to fluctuate while the target chip's PWM is being cycled.
    $init_fans  = array_values(array_filter($all_init,  fn($f) => dirname(realpath($f['sensor'])) === $pwm_dir));
    $final_fans = array_values(array_filter($all_final, fn($f) => dirname(realpath($f['sensor'])) === $pwm_dir));
    for ($i=0; $i < count($final_fans); $i++) {
      if (($final_fans[$i]['rpm'] - $init_fans[$i]['rpm'])>0) {
        echo $init_fans[$i]['sensor'];
        break;
      }
    }
  }
  break;
case 'pwm':
  $pwm = $_GET['pwm']??'';
  $fan = $_GET['fan']??'';
  if (is_file($pwm) && is_file($fan)) {
    $autofan = "$docroot/plugins/$plugin/scripts/rc.autofan";
    exec("$autofan stop >/dev/null");
    $fan_min = explode("_", $fan)[0]."_min";
    $default_method = file_get_contents($pwm."_enable");
    $default_pwm = file_get_contents($pwm);
    $default_fan_min = file_get_contents($fan_min);
    file_put_contents($pwm."_enable", "1");
    file_put_contents($fan_min, "0");
    file_put_contents($pwm, "0");
    sleep(5);
    $min_rpm = file_get_contents($fan);
    foreach (range(0, 20) as $i) {
      $val=$i*5;
      file_put_contents($pwm, $val);
      sleep(2);
      if ((file_get_contents($fan) - $min_rpm) > 15) {
        # Debounce
        for ($i=0; $i <= 10; $i++) if (file_get_contents($fan) == 0) {$is_lowest = false; break;} else {$is_lowest = true; sleep(1);};
        if ($is_lowest) {echo $val; break;}
      }
    }
    file_put_contents($pwm, $default_pwm);
    file_put_contents($fan_min, $default_fan_min);
    file_put_contents($pwm."_enable", $default_method);
    exec("$autofan start >/dev/null");
  }
  break;
case 'temps':
  header('Content-Type: application/json');
  echo json_encode(list_temp());
  break;
case 'autopair':
  // Cycle every PWM on the chip of the selected controller (or all chips if none given)
  // and find which fan on the same chip responds. Returns array of matched pairs.
  $filter_pwm = $_GET['pwm'] ?? '';
  $filter_dir  = $filter_pwm && is_file($filter_pwm) ? realpath(dirname($filter_pwm)) : '';
  $autofan_svc = "$docroot/plugins/$plugin/scripts/rc.autofan";
  exec("$autofan_svc stop >/dev/null");
  $pairs = [];
  exec("find /sys/devices -type f -iname 'pwm[0-9]' -exec dirname \"{}\" +|uniq", $chips);
  foreach ($chips as $chip) {
    $chip_real = realpath($chip);
    if ($filter_dir && $chip_real !== $filter_dir) continue;
    $chip_name = is_file("$chip/name") ? trim(file_get_contents("$chip/name")) : '';
    $pwm_files = preg_grep("/pwm\d+$/", scan_dir($chip));
    $fan_files  = preg_grep("/fan\d+_input$/", scan_dir($chip));
    if (empty($pwm_files) || empty($fan_files)) continue;
    foreach ($pwm_files as $pwm_path) {
      $default_method = file_get_contents($pwm_path."_enable");
      $default_val    = file_get_contents($pwm_path);
      file_put_contents($pwm_path."_enable", "1");
      file_put_contents($pwm_path, "150");
      sleep(3);
      $init = [];
      foreach ($fan_files as $f) $init[$f] = intval(file_get_contents($f));
      file_put_contents($pwm_path, "255");
      sleep(3);
      $best_fan = ''; $best_delta = 0;
      foreach ($fan_files as $f) {
        $delta = intval(file_get_contents($f)) - $init[$f];
        if ($delta > $best_delta) { $best_delta = $delta; $best_fan = $f; }
      }
      file_put_contents($pwm_path, $default_val);
      file_put_contents($pwm_path."_enable", $default_method);
      if ($best_fan) {
        $pairs[] = [
          'chip'      => $chip_name,
          'pwm'       => $pwm_path,
          'pwm_name'  => end(explode('/', $pwm_path)),
          'fan'       => $best_fan,
          'fan_name'  => end(explode('/', $best_fan)),
          'rpm_delta' => $best_delta,
        ];
      }
    }
  }
  exec("$autofan_svc start >/dev/null");
  header('Content-Type: application/json');
  echo json_encode($pairs);
  break;
}
?>

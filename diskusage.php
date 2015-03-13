<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('KEY_GLOBAL', 'Global');
define('DEFAULT_COMMAND_USAGE', 'du -s $path');

$file = (isset($argv[1])) ? $argv[1] : null;

if (null === $file) die('First parameter must be a file' . PHP_EOL);
else if (!file_exists($file)) die('File does not exist: ' . $file . PHP_EOL);

$config = parse_ini_file($file, true);
$global_settings = (isset($config[KEY_GLOBAL])) ? $config[KEY_GLOBAL] : array();

function get_raw_setting(array $settings, $key, $default=null) {
	return (isset($settings[$key])) ? $settings[$key] : $default;
}
function get_setting(array $settings, $key, $default=null) {
	$value = get_raw_setting($settings, $key, $default);

	if (preg_match_all('/\$([a-zA-Z]+[a-zA-Z0-9_\-]*)/', $value, $matches)) {

		for($i=0, $j=count($matches[1]); $i < $j; $i++) {
			$sub_value = get_setting($settings, $matches[1][$i]);

			if (null === $sub_value) throw new RuntimeException('Unable to lookup value for ' . $matches[0][$i]);
			$value = str_replace($matches[0][$i], $sub_value, $value);

		}

	}
	return $value;
}
foreach($config as $section => $section_settings) {

	if ($section == KEY_GLOBAL) continue;

	$settings = array_merge($global_settings, $section_settings);

	#[datetime] [PHP_OS] [CONFIG_NAME] [PATH] [USAGE]
	$logpath = get_setting($settings, 'logpath');
	$path = get_setting($settings, 'path');

	// Get command to call to retrieve disk usage for path in format [command [options]] %s - where %s will be the path
	$command_usage = get_setting($settings, 'commandusage', DEFAULT_COMMAND_USAGE);
	$result = `$command_usage`;
	$usage = 0;

	if (preg_match('/([0-9]+)\s+(.+)/', $result, $matches)) {

		$usage = intval($matches[1]);

	} else {

		error_log(sprintf('%s was expecting  the returned results from command to be in the format [size] [directory]', __FILE__));

	}

	$fp = fopen($logpath, 'a+');

	if ($fp) {
		$values = array(
			date('Y-m-d H:i:s'),
			PHP_OS,
			$section,
			$path,
			$usage
		);

		echo implode('; ', $values) . PHP_EOL;

		fputcsv($fp, $values);

		fclose($fp);
	} else {
		error_log(sprintf('%s was unable to the log %s for section %s from the %s config file', __FILE__, $logpath, $section, $file));
	}

}
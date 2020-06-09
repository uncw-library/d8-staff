
if (getenv('DRUPAL_SALT') && empty($settings['hash_salt'])) {
    $settings['hash_salt'] = getenv('DRUPAL_SALT');
};

$settings['config_sync_directory'] = '/drupal_sync';

$databases['default']['default'] = array (
  'database' => getenv('MYSQL_DATABASE'),
  'username' => getenv('MYSQL_USER'),
  'password' => getenv('MYSQL_PASSWORD'),
  'prefix' => '',
  'host' => getenv('MYSQL_HOST'),
  'port' => '3306',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
);

<?php return array(
    'root' => array(
        'pretty_version' => '2.0.0',
        'version' => '2.0.0.0',
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'reference' => NULL,
        'name' => 'loggedin/loggedin',
        'dev' => true,
    ),
    'versions' => array(
        'duckdev/freemius-plugin-licensing' => array(
            'pretty_version' => '1.0.0',
            'version' => '1.0.0.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../duckdev/freemius-plugin-licensing',
            'aliases' => array(),
            'reference' => 'f175fa2adbba8b30936142ba1cf4da97ce7b6f1e',
            'dev_requirement' => false,
        ),
        'loggedin/loggedin' => array(
            'pretty_version' => '2.0.0',
            'version' => '2.0.0.0',
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'reference' => NULL,
            'dev_requirement' => false,
        ),
    ),
);

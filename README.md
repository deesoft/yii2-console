yii2-console
============

Yii2 console command

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require deesoft/yii2-console "~1.0"
```

or add

```
"deesoft/yii2-console": "~1.0"
```

to the require section of your `composer.json` file.

Usage
-----

Once the extension is installed, simply modify your console config as follows:

```php
'controlerMap' => [
    'migrate' => [
        'class' => 'dee\console\MigrateController',
        'migrationLookup' => [
            '@yii/rbac/migrations',
            '@mdm/autonumber/migrations',
            '@mdm/upload/migrations',
        ]
    ]
]
```

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

Once the extension is installed, simply modify your config as follows:

```php
// params.php or params-loca.php

return [
    ...
    'dee.migration.path' => [
        '@yii/rbac/migrations',
        '@mdm/autonumber/migrations',
        '@mdm/upload/migrations',
    ]
];
```

You also can dinamically add new path from your extension via `bootstrap`.
```php
    ...
    public function bootstrap($app)
    {
        $app->params['dee.migration.path'][] = '@your/ext/migrations';
    }
```

Feature
-------
Unlike original migration that only can `migrate/down` or `migrate/redo` with migration squence.
You can `down` and `redo` individual migration without depend it squence. E.g, your migration history are
```
	(2016-02-09 02:29:14) m160201_050050_create_table_accounting
	(2016-02-09 02:29:14) m160201_050040_create_table_inventory
	(2016-02-09 02:29:13) m160201_050030_create_table_sales
	(2016-02-09 02:29:13) m160201_050020_create_table_purchase
	(2016-02-09 02:29:13) m160201_050010_create_table_master
	(2016-02-09 02:29:11) m140527_084418_auto_number
	(2016-02-09 02:29:11) m140506_102106_rbac_init
	(2016-02-01 04:02:51) m130524_201442_init
```
You can `down` or `redo` only `m160201_050020_create_table_purchase`. Use `migrate/partial-down` or `migrate/partial-redo` to do that.
```
./yii migrate/partial-down m160201_050020
```

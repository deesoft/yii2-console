yii2-console
============

Yii2 console command. Contain migration controller that more feature and usability.

[![Latest Stable Version](https://poser.pugx.org/deesoft/yii2-console/v/stable)](https://packagist.org/packages/deesoft/yii2-console) 
[![Latest Unstable Version](https://poser.pugx.org/deesoft/yii2-console/v/unstable)](https://packagist.org/packages/deesoft/yii2-console) 
[![License](https://poser.pugx.org/deesoft/yii2-console/license)](https://packagist.org/packages/deesoft/yii2-console)

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
// params.php or params-local.php

return [
    ...
    'dee.migration.path' => [
        '@yii/rbac/migrations',
        // list your migration path here
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
### Compatible with official migrate command

### Partial Up, Down and Redo
Unlike original migration that only can `up`, `down` or `redo` with migration squence.
We can `up`, `down` and `redo` individual migration without depend it squence. E.g, your migration history are
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
We can `down` or `redo` only `m160201_050020_create_table_purchase`. Use `migrate/partial` or `migrate/partial` to do that.
```
./yii migrate/down m160201_050020
./yii migrate/redo 140527_084418
```

### Exclude Specific version from action

```
./yii migrate -e=160201_050030,140527_084418
./yii migrate/down all -e=m140506_102106_rbac_init
```
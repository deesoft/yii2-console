<?php

namespace dee\console;

use yii\mongodb\console\controllers\MigrateController as BaseMigrateController;

/**
 * MongodbMigrateController. This controller required yiisoft/yii2-mongodb extension.
 * Use at application config
 * 
 * ```
 * 'controlerMap' => [
 *     'mongodb-migrate' => [
 *         'class' => 'dee\console\MongodbMigrateController',
 *         'migrationLookup' => [
 *             '@yii/rbac/migrations',
 *             '@mdm/autonumber/migrations',
 *             '@mdm/upload/migrations',
 *         ]
 *     ]
 * ]
 * ```
 * Or simply add `migrationLookup` to application params
 * ```
 * // file config/params.php
 *
 * return [
 *     'dee.migration.mongopath' => [
 *         '@yii/rbac/migrations',
 *         '@mdm/autonumber/migrations',
 *         '@mdm/upload/migrations',
 *     ]
 * ];
 * ```
 * 
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.2
 */
class MongodbMigrateController extends BaseMigrateController
{
    use MigrateTrait;
    /**
     * @var string
     */
    public $extraFile = '@runtime/dee-migration/mongo-path.php';
    /**
     * @var string
     */
    protected $paramVar = 'dee.migration.mongopath';

}

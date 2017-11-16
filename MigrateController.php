<?php

namespace dee\console;

use yii\console\controllers\MigrateController as BaseMigrateController;

/**
 * MigrateController
 * Use at application config
 * 
 * ```
 * 'controlerMap' => [
 *     'migrate' => [
 *         'class' => 'dee\console\MigrateController',
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
 *     'dee.migration.path' => [
 *         '@yii/rbac/migrations',
 *         '@mdm/autonumber/migrations',
 *         '@mdm/upload/migrations',
 *     ]
 * ];
 * ```
 * 
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class MigrateController extends BaseMigrateController
{

    use MigrateTrait;
    /**
     * @var string
     */
    public $baseMigrationClass = 'yii\console\Migration';
    /**
     * @var string 
     */
    public $extraFile = '@runtime/dee-migration/path.php';
    /**
     * @var string 
     */
    protected $lookupParamName = 'dee.migration.path';
    /**
     * @var string
     */
    protected $nsParamName = 'dee.migration.ns';

}

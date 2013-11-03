<?php
/**
 * Migrator class file.
 *
 * @category YupeComponent
 * @package  yupe.modules.yupe.components
 * @author   Alexander Tischenko <tsm@glavset.ru>
 * @license  BSD https://raw.github.com/yupe/yupe/master/LICENSE
 * @version  0.6
 * @link     http://www.yupe.ru
 */

namespace yupe\components\migrator;

use yupe\components;

interface MigratorInterface
{
    /**
     * Форматированный вывод:
     * 
     * @param stirng $message - сообщение
     * @param string $endline - конец строки
     * 
     * @return void
     */
    public function formating($message, $endline = "\n");

    /**
     * Инициализируем класс:
     *
     * @return parent:init()
     **/
    public function init();

    /**
     * Обновление до актуальной миграции:
     *
     * @param string $module - required module
     *
     * @return bool if migration updated
     **/
    public function updateToLatest($module);

    /**
     * Проверяем на незавершённые миграции:
     *
     * @param string $module - required module
     * @param string $class  - migration class
     *
     * @return bool is updated to migration
     **/
    public function checkForBadMigration($module, $class = false);

    /**
     * Даунгрейд миграции:
     *
     * @param string $module - required module
     * @param string $class  - name of migration class
     *
     * @return bool is downgraded from migration
     **/
    public function migrateDown($module, $class);

    /**
     * Check each modules for new migrations
     *
     * @param string  $module - required module
     * @param integer $limit  - limit of array
     *
     * @return mixed version and apply time
     */
    public function getMigrationHistory($module, $limit = 20);

    /**
     * Check each modules for new migrations
     *
     * @param array $modules - list of modules
     *
     * @return mixed new migrations
     */
    public function checkForUpdates($modules);

    /**
     * Return db-installed modules list
     *
     * @return mixed db-installed
     **/
    public function getModulesWithDBInstalled();
}
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

use Yii;
use CDbCacheDependency;
use ErrorException;
use CException;
use CDbConnection;
use TagsCache;
use CHtml;

abstract class Migrator extends \CApplicationComponent implements MigratorInterface
{
    public $connectionID = 'db';
    public $migrationTable = 'migrations';

    /**
     * @var CDbConnection
     */
    private $_db;

    /**
     * Форматированный вывод:
     * 
     * @param stirng $message - сообщение
     * @param string $endline - конец строки
     * 
     * @return void
     */
    public function formating($message, $endline = "\n")
    {
        echo $message . $endline;
    }

    /**
     * Инициализируем класс:
     *
     * @return parent:init()
     **/
    public function init()
    {
        // check for table
        $db = $this->getDbConnection();
        if ($db->schema->getTable($db->tablePrefix . $this->migrationTable) === null) {
            $this->createMigrationHistoryTable();
        }
        return parent::init();
    }

    /**
     * Обновление до актуальной миграции:
     *
     * @param string $module - required module
     *
     * @return bool if migration updated
     **/
    public function updateToLatest($module)
    {
        if (($newMigrations = $this->getNewMigrations($module)) !== array()) {
            Yii::log(
                Yii::t(
                    'YupeModule.yupe',
                    'Updating DB of {module}  to latest version',
                    array('{module}' => $module)
                )
            );
            foreach ($newMigrations as $migration) {
                if ($this->migrateUp($module, $migration) === false) {
                    return false;
                }
            }
        } else {
            Yii::log(
                Yii::t(
                    'YupeModule.yupe',
                    'There is no new migrations for {module}',
                    array('{module}' => $module)
                )
            );
        }
        return true;
    }

    /**
     * Проверяем на незавершённые миграции:
     *
     * @param string $module - required module
     * @param string $class  - migration class
     *
     * @return bool is updated to migration
     **/
    public function checkForBadMigration($module, $class = false)
    {
        echo Yii::t('YupeModule.yupe', "Checking for pending migrations") . '<br />';

        $db = $this->getDbConnection();

        $data = $db->cache(
                3600, new CDbCacheDependency('select count(id) from ' . $db->tablePrefix . $this->migrationTable)
            )->createCommand()
            ->selectDistinct('version, apply_time')
            ->from($db->tablePrefix . $this->migrationTable)
            ->order('version DESC')
            ->where(
                'module = :module',
                array(
                    ':module' => $module,
                )
            )
            ->queryAll();

        if (($data !== array()) || ((strpos($class, '_base') !== false) && ($data[] = array(
            'version' => $class,
            'apply_time' => 0
        )))
        ) {
            foreach ($data as $migration) {
                if ($migration['apply_time'] == 0) {
                    try {
                        $this->formating(
                            Yii::t(
                                'YupeModule.yupe',
                                'Downgrade {migration} for {module}.',
                                array(
                                    '{module}' => $module,
                                    '{migration}' => $migration['version'],
                                )
                            )
                        );

                        Yii::log(
                            Yii::t(
                                'YupeModule.yupe',
                                'Downgrade {migration} for {module}.',
                                array(
                                    '{module}' => $module,
                                    '{migration}' => $migration['version'],
                                )
                            )
                        );
                        if ($this->migrateDown($module, $migration['version']) !== false) {
                            $db->createCommand()->delete(
                                $db->tablePrefix . $this->migrationTable,
                                array(
                                    $db->quoteColumnName('version') . "=" . $db->quoteValue($migration['version']),
                                    $db->quoteColumnName('module') . "=" . $db->quoteValue($module),
                                )
                            );
                        } else {
                            Yii::log(
                                Yii::t(
                                    'YupeModule.yupe',
                                    'Can\'t downgrade migrations {migration} for {module}.',
                                    array(
                                        '{module}' => $module,
                                        '{migration}' => $migration['version'],
                                    )
                                )
                            );

                            $this->formating(
                                Yii::t(
                                    'YupeModule.yupe',
                                    'Can\'t downgrade migrations {migration} for {module}.',
                                    array(
                                        '{module}' => $module,
                                        '{migration}' => $migration['version'],
                                    )
                                )
                            );

                            return false;
                        }
                    } catch (ErrorException $e) {
                        Yii::log(
                            Yii::t(
                                'YupeModule.yupe',
                                'There is an error: {error}',
                                array(
                                    '{error}' => $e
                                )
                            )
                        );
                        $this->formating(
                            Yii::t(
                                'YupeModule.yupe',
                                'There is an error: {error}',
                                array(
                                    '{error}' => $e
                                )
                            ), null
                        );
                    }
                }
            }
        } else {
            Yii::log(
                Yii::t(
                    'YupeModule.yupe',
                    'No need to downgrade migrations for {module}',
                    array('{module}' => $module)
                )
            );

            $this->formating(
                Yii::t(
                    'YupeModule.yupe',
                    'No need to downgrade migrations for {module}',
                    array('{module}' => $module)
                )
            );
        }
        return true;
    }

    /**
     * Обновляем миграцию:
     *
     * @param string $module - required module
     * @param string $class  - name of migration class
     *
     * @return bool is updated to migration
     **/
    protected function migrateUp($module, $class)
    {
        $db = $this->getDbConnection();

        ob_start();
        ob_implicit_flush(false);

        $this->formating(
            Yii::t('YupeModule.yupe', "Checking migration {class}", array('{class}' => $class)),
            null
        );

        Yii::app()->cache->clear('getMigrationHistory');

        $start = microtime(true);
        $migration = $this->instantiateMigration($module, $class);

        // Вставляем запись о начале миграции
        $db->createCommand()->insert(
            $db->tablePrefix . $this->migrationTable,
            array(
                'version' => $class,
                'module' => $module,
                'apply_time' => 0,
            )
        );

        $result = $migration->up();
        Yii::log($msg = ob_get_clean());

        if ($result !== false) {
            // Проставляем "установлено"
            $db->createCommand()->update(
                $db->tablePrefix . $this->migrationTable,
                array('apply_time' => time()),
                "version = :ver AND module = :mod",
                array(':ver' => $class, 'mod' => $module)
            );
            $time = microtime(true) - $start;
            Yii::log(
                Yii::t(
                    'YupeModule.yupe',
                    "Migration {class} applied for {s} seconds.",
                    array('{class}' => $class, '{s}' => sprintf("%.3f", $time))
                )
            );
        } else {
            $time = microtime(true) - $start;
            Yii::log(
                Yii::t(
                    'YupeModule.yupe',
                    "Error when running {class} ({s} seconds.)",
                    array('{class}' => $class, '{s}' => sprintf("%.3f", $time))
                )
            );
            throw new CException(
                Yii::t(
                    'YupeModule.yupe',
                    'Error was found when installing: {error}',
                    array(
                        '{error}' => $msg
                    )
                )
            );
            return false;
        }
    }

    /**
     * Даунгрейд миграции:
     *
     * @param string $module - required module
     * @param string $class  - name of migration class
     *
     * @return bool is downgraded from migration
     **/
    public function migrateDown($module, $class)
    {
        Yii::log(Yii::t('YupeModule.yupe', "Downgrade migration {class}", array('{class}' => $class)));
        $db = $this->getDbConnection();
        $start = microtime(true);
        $migration = $this->instantiateMigration($module, $class);

        ob_start();
        ob_implicit_flush(false);
        $result = $migration->down();
        Yii::log($msg = ob_get_clean());
        Yii::app()->cache->clear('getMigrationHistory');

        if ($result !== false) {
            $db->createCommand()->delete(
                $db->tablePrefix . $this->migrationTable,
                array(
                    'AND',
                    $db->quoteColumnName('version') . "=" . $db->quoteValue($class),
                    array(
                        'AND',
                        $db->quoteColumnName('module') . "=" . $db->quoteValue($module),
                    )
                )
            );
            $time = microtime(true) - $start;
            Yii::log(
                Yii::t(
                    'YupeModule.yupe',
                    "Migration {class} downgrated for {s} seconds.",
                    array('{class}' => $class, '{s}' => sprintf("%.3f", $time))
                )
            );
            return true;
        } else {
            $time = microtime(true) - $start;
            Yii::log(
                Yii::t(
                    'YupeModule.yupe',
                    "Error when downgrading {class} ({s} сек.)",
                    array('{class}' => $class, '{s}' => sprintf("%.3f", $time))
                )
            );
            throw new CException(
                Yii::t(
                    'YupeModule.yupe',
                    'Error was found when installing: {error}',
                    array(
                        '{error}' => $msg
                    )
                )
            );
        }
    }

    /**
     * Check each modules for new migrations
     *
     * @param string $module - required module
     * @param string $class  - class of migration
     *
     * @return mixed version and apply time
     */
    protected function instantiateMigration($module, $class)
    {
        $file = Yii::getPathOfAlias("application.modules." . $module . ".install.migrations") . '/' . $class . '.php';
        include_once $file;
        $migration = new $class;
        $migration->setDbConnection($this->getDbConnection());
        return $migration;
    }

    /**
     * Connect to DB
     *
     * @return db connection or make exception
     */
    protected function getDbConnection()
    {
        if ($this->_db !== null) {
            return $this->_db;
        } else {
            if (($this->_db = Yii::app()->getComponent($this->connectionID)) instanceof CDbConnection) {
                return $this->_db;
            }
        }
        throw new CException(
            Yii::t('YupeModule.yupe', 'Parameter connectionID is wrong')
        );
    }

    /**
     * Check each modules for new migrations
     *
     * @param string  $module - required module
     * @param integer $limit  - limit of array
     *
     * @return mixed version and apply time
     */
    public function getMigrationHistory($module, $limit = 20, $all = false)
    {
        $db = $this->getDbConnection();

        $data = Yii::app()->cache->get('getMigrationHistory' . '-limit-' . $limit);

        if ($data === false) {
            $data = array();

            Yii::app()->cache->clear('getMigrationHistory');

            $items = $db->cache(
                    3600, new CDbCacheDependency('select count(id) from ' . $db->tablePrefix . $this->migrationTable)
                )->createCommand()
                ->select('version, apply_time, module')
                ->from($db->tablePrefix . $this->migrationTable)
                ->order('version DESC')
                ->limit($limit)
                ->queryAll();

            foreach ($items as $item) {
                $mod = $item['module'] ?: 'all';
                array_pop($item);
                
                $data[$mod][] = $item;
            }

            Yii::app()->cache->set('getMigrationHistory', $data, 3600, new TagsCache('yupe', 'installedModules', 'getModulesDisabled', 'getMigrationHistory'));
        }

        if ($module !== null && isset($data[$module])) {
            $return = $data[$module];
        } else {
            if ($all === false) {
                foreach ($data as $module => $items) {
                    foreach ($items as $item) {
                        $return[] = array_merge(
                            array('module' => $module),
                            $item
                        );
                    }
                }
            } else {
                return $data;
            }
        }

        return CHtml::listData($return, 'version', 'apply_time');
    }

    /**
     * Create migration history table
     *
     * @return nothing
     */
    protected function createMigrationHistoryTable()
    {
        $db = $this->getDbConnection();
        Yii::log(
            Yii::t(
                'YupeModule.yupe',
                'Creating table for store migration versions {table}',
                array('{table}' => $this->migrationTable)
            )
        );
        $options = Yii::app()->db->schema instanceof CMysqlSchema ? 'ENGINE=InnoDB DEFAULT CHARSET=utf8' : '';
        $db->createCommand()->createTable(
            $db->tablePrefix . $this->migrationTable,
            array(
                'id' => 'pk',
                'module' => 'string NOT NULL',
                'version' => 'string NOT NULL',
                'apply_time' => 'integer',
            ),
            $options
        );

        $db->createCommand()->createIndex(
            "idx_migrations_module",
            $db->tablePrefix . $this->migrationTable,
            "module",
            false
        );
    }

    /**
     * Check for new migrations for module
     *
     * @param string $module - required module
     *
     * @return mixed new migrations
     */
    protected function getNewMigrations($module)
    {
        $applied = array();
        foreach ($this->getMigrationHistory($module, -1) as $version => $time) {
            if ($time) {
                $applied[substr($version, 1, 13)] = true;
            }
        }

        $migrations = array();

        if (($migrationsPath = Yii::getPathOfAlias("application.modules." . $module . ".install.migrations")) && is_dir(
            $migrationsPath
        )
        ) {
            $handle = opendir($migrationsPath);
            while (($file = readdir($handle)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $path = $migrationsPath . '/' . $file;
                if (preg_match('/^(m(\d{6}_\d{6})_.*?)\.php$/', $file, $matches) && is_file(
                    $path
                ) && !isset($applied[$matches[2]])
                ) {
                    $migrations[] = $matches[1];
                }
            }
            closedir($handle);
            sort($migrations);
        }
        return $migrations;
    }

    /**
     * Check each modules for new migrations
     *
     * @param array $modules - list of modules
     *
     * @return mixed new migrations
     */
    public function checkForUpdates($modules)
    {
        $updates = array();

        foreach ($modules as $mid => $module) {
            if ($a = $this->getNewMigrations($mid)) {
                $updates[$mid] = $a;
            }
        }

        return $updates;
    }

    /**
     * Return db-installed modules list
     *
     * @return mixed db-installed
     **/
    public function getModulesWithDBInstalled()
    {
        $db = $this->getDbConnection();
        $modules = array();
        $m = $db->cache(
                3600, new CDbCacheDependency('select count(id) from ' . $db->tablePrefix . $this->migrationTable)
            )->createCommand()
            ->select('module')
            ->from($db->tablePrefix . $this->migrationTable)
            ->order('module DESC')
            ->group('module')
            ->queryAll();

        foreach ($m as $i) {
            $modules[] = $i['module'];
        }

        return $modules;
    }
}
<?php
/**
 * MigrateCommand class file.
 *
 * @category YupeCommands
 * @package  yupe.modules.yupe.components.migrator.commands
 * @author   Evgeniy Kulikov <im@kulikov.im>
 * @license  BSD https://raw.github.com/yupe/yupe/master/LICENSE
 * @version  0.6
 * @link     http://www.yupe.ru
 */

class MigratorCommand extends CConsoleCommand
{
    const BASE_MIGRATION='m000000_000000_base';

    /**
     * @var string the directory that stores the migrations. This must be specified
     * in terms of a path alias, and the corresponding directory must exist.
     * Defaults to 'application.migrations' (meaning 'protected/migrations').
     */
    public $migrationPath='application.migrations';
    /**
     * @var string the name of the table for keeping applied migration information.
     * This table will be automatically created if not exists. Defaults to 'tbl_migration'.
     * The table structure is: (version varchar(255) primary key, apply_time integer)
     */
    public $migrationTable='tbl_migration';
    /**
     * @var string the application component ID that specifies the database connection for
     * storing migration information. Defaults to 'db'.
     */
    public $connectionID='db';
    /**
     * @var string the path of the template file for generating new migrations. This
     * must be specified in terms of a path alias (e.g. application.migrations.template).
     * If not set, an internal template will be used.
     */
    public $templateFile;
    /**
     * @var string the default command action. It defaults to 'up'.
     */
    public $defaultAction='up';
    /**
     * @var boolean whether to execute the migration in an interactive mode. Defaults to true.
     * Set this to false when performing migration in a cron job or background process.
     */
    public $interactive=true;

    /**
     * Форматированный вывод:
     * 
     * @param stirng $message - сообщение
     * @param string $after   - конец строки
     * @param string $bedore  - начало строки
     * 
     * @return void
     */
    public function formating($message = null, $after = "\n", $before = "")
    {
        echo $before . $message . $after;
    }

    public function notice()
    {
        $yupeVersion = Yii::app()->getModule('yupe')->getVersion();

        $this->formating(
            Yii::t(
                'YupeModule', "Yupe Migration Tool v1.0 (based on Yupe v:version)", array(
                    ':version' => $yupeVersion
                )
            ), "\n\n", "\n"
        );
    }

    public function beforeAction($action, $params)
    {
        $path = Yii::getPathOfAlias($this->migrationPath);
        
        if ($path === false || !is_dir($path)) {
            
            $this->formating(
                Yii(
                    'YupeModule.yupe', 'Error: The migration directory does not exist: :path', array(
                        ':path' => $this->migrationPath
                    )
                )
            );
            
            exit(1);
        }

        $this->migrationPath = $path;

        $this->notice();        

        return parent::beforeAction($action,$params);
    }

    public function actionUp($args)
    {
        if(($migrations=$this->getNewMigrations())===array())
        {
            echo "No new migration found. Your system is up-to-date.\n";
            return 0;
        }

        $total=count($migrations);
        $step=isset($args[0]) ? (int)$args[0] : 0;
        if($step>0)
            $migrations=array_slice($migrations,0,$step);

        $n=count($migrations);
        if($n===$total)
            echo "Total $n new ".($n===1 ? 'migration':'migrations')." to be applied:\n";
        else
            echo "Total $n out of $total new ".($total===1 ? 'migration':'migrations')." to be applied:\n";

        foreach($migrations as $migration)
            echo "    $migration\n";
        echo "\n";

        if($this->confirm('Apply the above '.($n===1 ? 'migration':'migrations')."?"))
        {
            foreach($migrations as $migration)
            {
                if($this->migrateUp($migration)===false)
                {
                    echo "\nMigration failed. All later migrations are canceled.\n";
                    return 2;
                }
            }
            echo "\nMigrated up successfully.\n";
        }
    }

    public function actionDown($args)
    {
        $step=isset($args[0]) ? (int)$args[0] : 1;
        if($step<1)
        {
            echo "Error: The step parameter must be greater than 0.\n";
            return 1;
        }

        if(($migrations=$this->getMigrationHistory($step))===array())
        {
            echo "No migration has been done before.\n";
            return 0;
        }
        $migrations=array_keys($migrations);

        $n=count($migrations);
        echo "Total $n ".($n===1 ? 'migration':'migrations')." to be reverted:\n";
        foreach($migrations as $migration)
            echo "    $migration\n";
        echo "\n";

        if($this->confirm('Revert the above '.($n===1 ? 'migration':'migrations')."?"))
        {
            foreach($migrations as $migration)
            {
                if($this->migrateDown($migration)===false)
                {
                    echo "\nMigration failed. All later migrations are canceled.\n";
                    return 2;
                }
            }
            echo "\nMigrated down successfully.\n";
        }
    }

    public function actionRedo($args)
    {
        $step=isset($args[0]) ? (int)$args[0] : 1;
        if($step<1)
        {
            echo "Error: The step parameter must be greater than 0.\n";
            return 1;
        }

        if(($migrations=$this->getMigrationHistory($step))===array())
        {
            echo "No migration has been done before.\n";
            return 0;
        }
        $migrations=array_keys($migrations);

        $n=count($migrations);
        echo "Total $n ".($n===1 ? 'migration':'migrations')." to be redone:\n";
        foreach($migrations as $migration)
            echo "    $migration\n";
        echo "\n";

        if($this->confirm('Redo the above '.($n===1 ? 'migration':'migrations')."?"))
        {
            foreach($migrations as $migration)
            {
                if($this->migrateDown($migration)===false)
                {
                    echo "\nMigration failed. All later migrations are canceled.\n";
                    return 2;
                }
            }
            foreach(array_reverse($migrations) as $migration)
            {
                if($this->migrateUp($migration)===false)
                {
                    echo "\nMigration failed. All later migrations are canceled.\n";
                    return 2;
                }
            }
            echo "\nMigration redone successfully.\n";
        }
    }

    public function actionTo($args)
    {
        if(isset($args[0]))
            $version=$args[0];
        else
            $this->usageError('Please specify which version to migrate to.');

        $originalVersion=$version;
        if(preg_match('/^m?(\d{6}_\d{6})(_.*?)?$/',$version,$matches))
            $version='m'.$matches[1];
        else
        {
            echo "Error: The version option must be either a timestamp (e.g. 101129_185401)\nor the full name of a migration (e.g. m101129_185401_create_user_table).\n";
            return 1;
        }

        // try migrate up
        $migrations=$this->getNewMigrations();
        foreach($migrations as $i=>$migration)
        {
            if(strpos($migration,$version.'_')===0)
                return $this->actionUp(array($i+1));
        }

        // try migrate down
        $migrations=array_keys($this->getMigrationHistory(-1));
        foreach($migrations as $i=>$migration)
        {
            if(strpos($migration,$version.'_')===0)
            {
                if($i===0)
                {
                    echo "Already at '$originalVersion'. Nothing needs to be done.\n";
                    return 0;
                }
                else
                    return $this->actionDown(array($i));
            }
        }

        echo "Error: Unable to find the version '$originalVersion'.\n";
        return 1;
    }

    public function actionMark($args)
    {
        if(isset($args[0]))
            $version=$args[0];
        else
            $this->usageError('Please specify which version to mark to.');
        $originalVersion=$version;
        if(preg_match('/^m?(\d{6}_\d{6})(_.*?)?$/',$version,$matches))
            $version='m'.$matches[1];
        else {
            echo "Error: The version option must be either a timestamp (e.g. 101129_185401)\nor the full name of a migration (e.g. m101129_185401_create_user_table).\n";
            return 1;
        }

        $db=$this->getDbConnection();

        // try mark up
        $migrations=$this->getNewMigrations();
        foreach($migrations as $i=>$migration)
        {
            if(strpos($migration,$version.'_')===0)
            {
                if($this->confirm("Set migration history at $originalVersion?"))
                {
                    $command=$db->createCommand();
                    for($j=0;$j<=$i;++$j)
                    {
                        $command->insert($this->migrationTable, array(
                            'version'=>$migrations[$j],
                            'apply_time'=>time(),
                        ));
                    }
                    echo "The migration history is set at $originalVersion.\nNo actual migration was performed.\n";
                }
                return 0;
            }
        }

        // try mark down
        $migrations=array_keys($this->getMigrationHistory(-1));
        foreach($migrations as $i=>$migration)
        {
            if(strpos($migration,$version.'_')===0)
            {
                if($i===0)
                    echo "Already at '$originalVersion'. Nothing needs to be done.\n";
                else
                {
                    if($this->confirm("Set migration history at $originalVersion?"))
                    {
                        $command=$db->createCommand();
                        for($j=0;$j<$i;++$j)
                            $command->delete($this->migrationTable, $db->quoteColumnName('version').'=:version', array(':version'=>$migrations[$j]));
                        echo "The migration history is set at $originalVersion.\nNo actual migration was performed.\n";
                    }
                }
                return 0;
            }
        }

        echo "Error: Unable to find the version '$originalVersion'.\n";
        return 1;
    }

    /**
     * Существует ли модуль:
     * 
     * @param mixed  $module - модуль
     * @return mixed, string - имя модуля, null - не найден
     */
    public function getModule($module)
    {
        $path = Yii::getPathOfAlias('application.modules.' . $module);

        return is_dir($path)
            ? $module
            : null;
    }

    public function actionHistory($args)
    {
        $module = $this->getModule(
            isset($args[0]) ? $args[0] : -1
        );

        $limit = $module !== null
            ? (isset($args[1]) ? (int) $args[1] : -1)
            : (isset($args[0]) ? (int) $args[0] : -1);

        $migrations = Yii::app()->migrator->getMigrationHistory($module, $limit, true);

        if ($migrations === array()) {
            $this->formating(
                Yii::t('YupeModule.yupe', "No migration has been done before.")
            );
        } else {
            $n = 0;
            
            foreach ($migrations as $module_name) {
                $n += count($module_name);
            }
            
            if ($limit > 0) {
                $this->formating(
                    Yii::t(
                        'YupeModule.yupe',
                        "Showing the last :count applied migration|Showing the last :count applied migrations", array(
                            $n, ':count' => $n
                        )
                    )
                    . (
                        $module !== null
                            ? " " . Yii::t('YupeModule.yupe', 'for module ":module"', array(':module' => $module))
                            : ""
                    ), ":\n\n"
                );
            } else {
                $this->formating(
                    Yii::t(
                        'YupeModule.yupe',
                        'Total :count migration has been applied before|Total :count migrations have been applied before', array(
                            $n, ':count' => $n
                        )
                    ). (
                        $module !== null
                            ? ", " . Yii::t('YupeModule.yupe', 'for module ":module"', array(':module' => $module))
                            : ""
                    ), ":\n\n"
                );
            }
            if ($module !== null) {
                foreach($migrations as $version => $time) {
                    $this->formating(
                        date('Y-m-d H:i:s', $time) . ' - ' . $version, "\n", "\t"
                    );
                }
            } else {

                $count = 0;

                foreach ($migrations as $module_name => $items) {
                    if ($limit > 0 && $limit <= $count) {
                        break;
                    }

                    $this->formating(
                        $module_name, ":\n", "\n\t"
                    );

                    foreach ($items as $item) {
                        if ($limit > 0 && $limit <= $count) {
                            break;
                        }

                        $this->formating(
                            date('Y-m-d H:i:s', $item['apply_time']) . ' - ' . $item['version'], "\n", "\t\t"
                        );

                        $count++;
                    }
                }
            }
        }
        $this->formating();
    }

    public function actionNew($args)
    {
        $limit=isset($args[0]) ? (int)$args[0] : -1;
        $migrations=$this->getNewMigrations();
        if($migrations===array())
            echo "No new migrations found. Your system is up-to-date.\n";
        else
        {
            $n=count($migrations);
            if($limit>0 && $n>$limit)
            {
                $migrations=array_slice($migrations,0,$limit);
                echo "Showing $limit out of $n new ".($n===1 ? 'migration' : 'migrations').":\n";
            }
            else
                echo "Found $n new ".($n===1 ? 'migration' : 'migrations').":\n";

            foreach($migrations as $migration)
                echo "    ".$migration."\n";
        }
    }

    public function actionCreate($args)
    {
        if(isset($args[0]))
            $name=$args[0];
        else
            $this->usageError('Please provide the name of the new migration.');

        if(!preg_match('/^\w+$/',$name)) {
            echo "Error: The name of the migration must contain letters, digits and/or underscore characters only.\n";
            return 1;
        }

        $name='m'.gmdate('ymd_His').'_'.$name;
        $content=strtr($this->getTemplate(), array('{ClassName}'=>$name));
        $file=$this->migrationPath.DIRECTORY_SEPARATOR.$name.'.php';

        if($this->confirm("Create new migration '$file'?"))
        {
            file_put_contents($file, $content);
            echo "New migration created successfully.\n";
        }
    }

    public function confirm($message,$default=false)
    {
        if(!$this->interactive)
            return true;
        return parent::confirm($message,$default);
    }

    protected function migrateUp($class)
    {
        if($class===self::BASE_MIGRATION)
            return;

        echo "*** applying $class\n";
        $start=microtime(true);
        $migration=$this->instantiateMigration($class);
        if($migration->up()!==false)
        {
            $this->getDbConnection()->createCommand()->insert($this->migrationTable, array(
                'version'=>$class,
                'apply_time'=>time(),
            ));
            $time=microtime(true)-$start;
            echo "*** applied $class (time: ".sprintf("%.3f",$time)."s)\n\n";
        }
        else
        {
            $time=microtime(true)-$start;
            echo "*** failed to apply $class (time: ".sprintf("%.3f",$time)."s)\n\n";
            return false;
        }
    }

    protected function migrateDown($class)
    {
        if($class===self::BASE_MIGRATION)
            return;

        echo "*** reverting $class\n";
        $start=microtime(true);
        $migration=$this->instantiateMigration($class);
        if($migration->down()!==false)
        {
            $db=$this->getDbConnection();
            $db->createCommand()->delete($this->migrationTable, $db->quoteColumnName('version').'=:version', array(':version'=>$class));
            $time=microtime(true)-$start;
            echo "*** reverted $class (time: ".sprintf("%.3f",$time)."s)\n\n";
        }
        else
        {
            $time=microtime(true)-$start;
            echo "*** failed to revert $class (time: ".sprintf("%.3f",$time)."s)\n\n";
            return false;
        }
    }

    protected function instantiateMigration($class)
    {
        $file=$this->migrationPath.DIRECTORY_SEPARATOR.$class.'.php';
        require_once($file);
        $migration=new $class;
        $migration->setDbConnection($this->getDbConnection());
        return $migration;
    }

    /**
     * @var CDbConnection
     */
    private $_db;
    protected function getDbConnection()
    {
        if($this->_db!==null)
            return $this->_db;
        elseif(($this->_db=Yii::app()->getComponent($this->connectionID)) instanceof CDbConnection)
            return $this->_db;

        echo "Error: CMigrationCommand.connectionID '{$this->connectionID}' is invalid. Please make sure it refers to the ID of a CDbConnection application component.\n";
        exit(1);
    }

    protected function getMigrationHistory($module = null, $limit)
    {
        return Yii::app()->migrator->getMigrationHistory($module, $limit);
    }

    protected function createMigrationHistoryTable()
    {
        $db=$this->getDbConnection();
        echo 'Creating migration history table "'.$this->migrationTable.'"...';
        $db->createCommand()->createTable($this->migrationTable,array(
            'version'=>'string NOT NULL PRIMARY KEY',
            'apply_time'=>'integer',
        ));
        $db->createCommand()->insert($this->migrationTable,array(
            'version'=>self::BASE_MIGRATION,
            'apply_time'=>time(),
        ));
        echo "done.\n";
    }

    protected function getNewMigrations()
    {
        $applied=array();
        foreach($this->getMigrationHistory(-1) as $version=>$time)
            $applied[substr($version,1,13)]=true;

        $migrations=array();
        $handle=opendir($this->migrationPath);
        while(($file=readdir($handle))!==false)
        {
            if($file==='.' || $file==='..')
                continue;
            $path=$this->migrationPath.DIRECTORY_SEPARATOR.$file;
            if(preg_match('/^(m(\d{6}_\d{6})_.*?)\.php$/',$file,$matches) && is_file($path) && !isset($applied[$matches[2]]))
                $migrations[]=$matches[1];
        }
        closedir($handle);
        sort($migrations);
        return $migrations;
    }

    public function getHelp()
    {
        return $this->notice() . <<<EOD
USAGE
  yiic migrate ([module]) [action] [parameter]

DESCRIPTION
  This command provides support for database migrations. The optional
  'action' parameter specifies which specific migration task to perform.
  It can take these values: up, down, to, create, history, new, mark.
  If the 'action' parameter is not given, it defaults to 'up'.
  Each action takes different parameters. Their usage can be found in
  the following examples.

EXAMPLES
 * yiic migrate
   Applies ALL new migrations. This is equivalent to 'yiic migrate up'.

 * yiic migrate create create_user_table
   Creates a new migration named 'create_user_table'.

 * yiic migrate create user create_user_table
   Creates a new migration named 'create_user_table' for module User.

 * yiic migrate up 3
   Applies the next 3 new migrations.

 * yiic migrate yupe up 3
   Applies the next 3 new migrations of module Yupe.

 * yiic migrate down
   Reverts the last applied migration.

 * yiic migrate yupe down
   Reverts the last applied migration of module Yupe.

 * yiic migrate down 3
   Reverts the last 3 applied migrations.

 * yiic migrate yupe down 3
   Reverts the last 3 applied migrations of module Yupe.

 * yiic migrate to 101129_185401
   Migrates up or down to version 101129_185401.

 * yiic migrate yupe to 101129_185401
   Migrates up or down to version 101129_185401 for module Yupe.

 * yiic migrate mark 101129_185401
   Modifies the migration history up or down to version 101129_185401.
   No actual migration will be performed.

 * yiic migrate history
   Shows all previously applied migration information.

 * yiic migrate history 10
   Shows the last 10 applied migrations.

 * yiic migrate yupe history 10
   Shows the last 10 applied migrations for module Yupe.

 * yiic migrate new
   Shows all new migrations.

 * yiic migrate yupe new
   Shows all new migrations for module Yupe.

 * yiic migrate new 10
   Shows the next 10 migrations that have not been applied.

 * yiic migrate new 10
   Shows the next 10 migrations that have not been applied for module Yupe.

EOD;
    }

    protected function getTemplate()
    {
        if($this->templateFile!==null)
            return file_get_contents(Yii::getPathOfAlias($this->templateFile).'.php');
        else
            return <<<EOD
<?php
/**
 * <Module/Application> install migration
 *
 * @category <your category>
 * @package  <your package>
 * @author   <author>
 * @license  <license>
 * @link     <your site>
 **/

class {ClassName} extends yupe\components\DbMigration
{
    public function up()
    {
    }

    public function down()
    {
        echo "{ClassName} does not support migration down.\\n";
        return false;
    }

    /*
    // Use safeUp/safeDown to do migration with transaction
    public function safeUp()
    {
    }

    public function safeDown()
    {
    }
    */
}
EOD;
    }
}
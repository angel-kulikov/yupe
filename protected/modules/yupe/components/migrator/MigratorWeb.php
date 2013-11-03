<?php
/**
 * MigratorWeb class file.
 *
 * @category YupeComponent
 * @package  yupe.modules.yupe.components.migrator
 * @author   Evgeniy Kulikov <im@kulikov.im>
 * @license  BSD https://raw.github.com/yupe/yupe/master/LICENSE
 * @version  0.6
 * @link     http://www.yupe.ru
 */

namespace yupe\components\migrator;

class MigratorWeb extends Migrator
{
	/**
     * Форматированный вывод:
     * 
     * @param stirng $message - сообщение
     * @param string $endline - конец строки
     * 
     * @return void
     */
    public function formating($message, $endline = "<br />")
    {
        echo $message . $endline;
    }
}
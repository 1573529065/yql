<?php
/**
 * Created by PhpStorm.
 * User: ifehrim@gmail.com
 * Date: 10/13/2017
 * Time: 2:32 PM
 */

namespace App\Unit\Traits;

/**
 * Trait Switchable
 *
 * for slice table search and relationship
 *
 *  example :   table . switch_endfix
 *
 *
 * @property string switch_endfix
 * @package App\Traits
 */
trait  Switchable
{

    protected $switch_endfix = "";
    protected $switch_prefix = "_";


    /**
     * @param string $switch_endfix
     * @return $this
     */
    public static function switcher($switch_endfix=null)
    {
        if ($switch_endfix == null) $switch_endfix = date("Y");
        $o = new static();
        $o->switch_endfix = $switch_endfix;
        return $o;
    }

    public function getTable()
    {
        if (!empty($this->switch_endfix)) {
            return $this->table . $this->switch_prefix . $this->switch_endfix;
        }
        return parent::getTable();
    }



}
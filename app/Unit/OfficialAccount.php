<?php
/**
 * Created by PhpStorm.
 * User: HWT51
 * Date: 2019/4/1
 * Time: 15:35
 */

namespace App\Unit;


class OfficialAccount
{
    private $ids;

    public function __construct()
    {
        $this->ids = collect(config('admin.official-account.ids') ?? []);
    }

    /**
     * @return array
     */
    public function getIds()
    {
        return $this->ids->toArray();
    }


    /**
     * @param array $ids
     * @return array
     */
    public function filterIds(array $ids)
    {
        return array_values(collect($ids)->diff($this->ids)->toArray());
    }

    /**
     * @param $idString
     * @return string
     */
    public function filterIdString(string $idString) {
        $ids = explode(',', $idString);
        return implode(',', $this->filterIds($ids));
    }

    /**
     * @param array $ids
     * @return OfficialAccount
     */
    public function set(array $ids)
    {
        $this->ids = collect($ids);
        return $this;
    }

    /**
     * @param $id
     * @return bool
     */
    public function isOfficial($id){
        return (is_numeric($this->ids->search($id)));
    }
}
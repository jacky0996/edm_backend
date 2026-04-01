<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

trait RepositoryTrait
{
    /**
     * 取得 model
     * @return Model|SoftDeletes
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * 取得 model
     * @return Builder
     */
    public function newModelQuery()
    {
        return $this->getModel()->newQuery();
    }

    /**
     * 將欄位名稱陣列排除重複值，避免 select 重複欄位時觸發 Error
     * @param array $columns 欄位名稱陣列
     * @return array
     */
    public static function uniqueColumns(array $columns): array
    {
        if (in_array('*', $columns)) {
            $columns = array_filter(fn ($col_name) => !is_string($col_name) || ($col_name === '*'));
        }

        return array_values(array_unique($columns));
    }

    /**
     * 取得model全部資料
     * @return Model[]|EloquentCollectionloquentCollection
     */
    public function all()
    {
        return $this->getModel()->all();
    }

    /**
     * 虛建一筆資料
     * @param array $data 新增的資料
     * @return Model|$this
     */
    public function make(array $data): object
    {
        return $this->newModelQuery()->make($data);
    }

    /**
     * 新增一筆資料
     * @param array $data 新增的資料
     * @return Model|$this
     */
    public function create(array $data): object
    {
        return $this->newModelQuery()->create($data);
    }

    /**
     * 判斷資料是否存在
     * @param int $id 顯示資料的id
     * @return bool
     */
    public function exists($id)
    {
        return $this->newModelQuery()->getQuery()->exists($id);
    }

    /**
     * 判斷資料是否不存在
     * @param int $id 顯示資料的id
     * @return bool
     */
    public function notExists($id)
    {
        return !$this->exists($id);
    }

    /**
     * 顯示單筆資料
     * @param int $id 顯示資料的id
     * @param array $columns 篩選欄位
     * @return Model|Model[]|EloquentCollection
     * @throws ModelNotFoundException
     */
    public function show($id, $columns = ['*'])
    {
        return $this->newModelQuery()->findOrFail($id, $columns);
    }

    /**
     * 顯示單筆資料（不進fail）
     * @param int $id 顯示資料的id
     * @param array $columns 篩選欄位
     * @return Model|Model[]|EloquentCollection|null
     */
    public function show_me($id, $columns = ['*'])
    {
        return $this->newModelQuery()->find($id, $columns);
    }

    /**
     * 顯示單筆資料（包括封存資料）
     * @param int $id 顯示資料的id
     * @param array $columns 篩選欄位
     * @return Model|Model[]|EloquentCollection|null
     */
    public function show_trash($id, $columns = ['*'])
    {
        return $this->getModel()->withTrashed()->find($id, $columns);
    }

    /**
     * 更新單筆資料
     * @param array $data 更新的資料內容
     * @param int $id 更新資料的id
     * @return bool
     */
    public function update(array $data, $id)
    {
        // return $this->newModelQuery()->whereKey($id)->update($data);
        return $this->newModelQuery()->findOrFail($id)->update($data);
    }

    /**
     * 刪除單筆資料
     * @param int $id 刪除資料的id
     * @return bool|null
     * @throws \LogicException
     */
    public function delete($id): int
    {
        $data = $this->newModelQuery()->findOrFail($id);

        return $data->delete($id);
    }

    /**
     * 強制刪除單筆資料
     * @param int $id 刪除資料的id
     * @return int
     */
    public function destroy($id): int
    {
        $data = $this->newModelQuery()->findOrFail($id);

        return $data->destroy($id);
    }

    /**
     * 批次刪除資料
     * @param array $ids 刪除資料的id
     * @return int
     */
    public function batch_delete($ids): int
    {
        return $this->newModelQuery()->getQuery()->whereIn('id', $ids)->delete();
    }

    /**
     * 更新或建立
     * @param  object         $obj1   要判斷的obj
     * @param  object         $obj2   其他要更新的obj
     * @return Model|static
     */
    public function updateOrCreate($obj1, $obj2 = [])
    {
        return $this->newModelQuery()->updateOrCreate($obj1, $obj2);
    }

    /**
     * 找尋條件相符的資料
     * @param  array $obj 要判斷的obj
     * @return Builder
     */
    public function findDataQuery($obj)
    {
        $query = $this->newModelQuery();

        foreach ($obj as $key => $value) {
            $query = $query->where($key, $value);
        }

        return $query;
    }
}

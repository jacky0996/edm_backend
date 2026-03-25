<?php

namespace App\Models\EDM;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use HasFactory;
    protected $table      = 'image';
    protected $connection = 'crm';
    protected $guarded    = [];

    /**
     * 讓 DataLib::CheckFilePath 能夠讀取到完整的路徑屬性
     * 使用 /../ 跳出 DataLib 硬編碼的 public 資料夾限制
     */
    public function getPathAttribute()
    {
        return '/../' . ltrim($this->name, '/');
    }
}

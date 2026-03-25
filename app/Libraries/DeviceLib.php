<?php

namespace App\Libraries;

use App\Services\CRM\DeviceService;

class DeviceLib
{
    public static function ValidCreate($serial_number = null, $project_id = null) // 驗證此設備可否被新建
    {
        $check = false; // 可新建

        if ($serial_number) { // 有序號則需判斷有無存在
            $device_crm = DeviceService::get_latest_device("{$serial_number}");

            if ($device_crm) {
                if ($device_crm->project_id != $project_id) {
                    $check = 2; // 專案不同
                }
            }
        }

        return $check;
    }

    public static function ValidUpdate($serial_number = null, $device_record = null, $now_device_erp_id = null, $now_device_id = null) // 驗證此設備可否被更新
    {
        // 主要判斷此序號能否更換系統
        // 1.若只給序號，則只判斷序號有無在系統中
        // 2.若有給序號＆設備紀錄，則多判斷是否為強制更換
        // 3.若無序號設備則不判斷

        // Return：1直接換、2不能換、3強制更換

        $res = 1;

        if ($serial_number) {
            $device = DeviceService::get_latest_device($serial_number);

            if ($device) { // 序號在CRM中
                if ($device_record) {
                    if ($device->id != $device_record->device_id) {
                        $res = 2;
                    }
                } else {
                    if ($now_device_id) {
                        if ($device->id != $now_device_id) {
                            $res = 2;
                        }
                    } else {
                        $res = 2;
                    }
                }
            }

            if ($device_record) {
                $check_device_record = \App\Models\CRM\Device\DeviceRecord::where('serial_number', $serial_number)
                    ->where('id', '!=', $device_record->id)
                    ->whereNotNull('device_id')
                    ->whereNotNull('detect_id')
                    ->whereNull('back_serial_number') // 等待更換1
                    ->whereNull('back_part_number') // 等待更換2
                    ->whereNull('back_name') // 等待更換3
                    ->whereHas('detect', function ($qry) { // 尚未完修
                        $qry->where('repair_status', '<', 3);
                    })
                    ->first();

                if ($check_device_record) { // 有另一條設備紀錄，並且尚未歸還
                    $res = 3;
                }

                if (!$device_record->device_id) { // 無綁定設備序號(device_id)，為備品維修
                    $res = 1;
                }
            }
        }
        $res = 1;

        return $res;
    }
}

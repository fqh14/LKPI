<?php

namespace App\Http\Controllers;

use App\Models\MCardgroup;

class Cardgroup extends BaseMasterController
{
    protected $model;
    protected bool $hasStatus = false; 

    protected function getTitle(): string { return 'Card Group'; }
    protected function getBaseRoute(): string { return 'cardgroup'; }
    protected function getFieldCode(): string { return 'cardgroupcode'; }
    protected function getFieldName(): string { return 'cardgroupname'; }
    protected function getTableName(): string { return 'cardgroup'; }

    protected function getTableColumns(): array 
    { 
        return ['No', 'Kode', 'Nama', 'Tipe', 'Aksi']; 
    }

    public function __construct()
    {
        $this->model = new MCardgroup();
    }

    protected function getExtraColumns($item): array 
    { 
        $itemObj = (object)$item;
        $isVendor = ((int)$itemObj->grouptype === 0);
        
        $badge = $isVendor 
            ? '<span class="badge badge-info text-white font-weight-bold px-2 py-1"> GRUP SUPPLIER </span>' 
            : '<span class="badge badge-primary text-white font-weight-bold px-2 py-1"> GRUP CUSTOMER </span>';

        return [$badge]; 
    }

    protected function getExtraFields(): array 
    { 
        return [
            [
                'name'    => 'grouptype',
                'label'   => 'Grup Tipe',
                'type'    => 'select',
                'options' => [
                    '1' => 'Customer', 
                    '0' => 'Supplier'
                ]
            ]
        ]; 
    }
}
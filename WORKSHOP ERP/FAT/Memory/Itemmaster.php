<?php

namespace App\Http\Controllers;

use App\Models\MItemmaster;
use App\Models\MItemgroup;
use App\Models\MUom;

class Itemmaster extends BaseMasterController
{
    protected $model;
    protected MItemgroup $mItemgroup;
    protected MUom $mUom;

    protected function getTitle(): string { return 'Item Produk'; }
    protected function getBaseRoute(): string { return 'itemmaster'; }
    protected function getFieldCode(): string { return 'itemcode'; }
    protected function getFieldName(): string { return 'itemname'; }
    protected function getTableName(): string { return 'itemmaster'; }

    public function __construct()
    {
        $this->model        = new MItemmaster();
        $this->mItemgroup   = new MItemgroup();
        $this->mUom         = new MUom();
    }

    protected function getTableColumns(): array 
    { 
        return ['No', 'Kode', 'Nama', 'Grup', 'Satuan (Uom)', 'Kode Batch', 'Bulan Exp', 'Status', 'Aksi']; 
    }

    protected function getExtraColumns($item): array 
    { 
        $itemObj = (object)$item;
        return [
            e($itemObj->itemgroupname ?? '-'),
            e($itemObj->uomname ?? '-'),
            e($itemObj->itembatch ?? '-'),
            e($itemObj->monthED ?? 0),
        ]; 
    }

    protected function getExtraFields(): array 
    { 
        return [
            [
                'name'    => 'itemgroupcode', 
                'label'   => 'Grup Item', 
                'type'    => 'datalist', 
                'options' => $this->formatOptions($this->mItemgroup->filters([
                    'status' => 1
                ]), 'itemgroupcode', 'itemgroupname'),
            ],
            [
                'name'    => 'uomcode', 
                'label'   => 'Satuan (Uom)', 
                'type'    => 'datalist', 
                'options' => $this->formatOptions($this->mUom->filters([
                    'status' => 1
                ]), 'uomcode', 'uomname'),
            ],
            [
                'name'  => 'itembatch',
                'label' => 'Kode Batch',
                'type'  => 'text'
            ],
            [
                'name'    => 'isstock', 
                'label'   => 'Stock', 
                'type'    => 'radio',
                'options' => ['1' => 'Ya', '0' => 'Tidak'] 
            ],
            [
                'name'  => 'minstock',
                'label' => 'Stock Min',
                'type'  => 'number'
            ],
            [
                'name'  => 'monthED',
                'label' => 'Bulan Exp Date',
                'type'  => 'number'
            ],
            [
                'name'  => 'remark',
                'label' => 'Deskripsi',
                'type'  => 'textarea'
            ]
        ];
    }
}
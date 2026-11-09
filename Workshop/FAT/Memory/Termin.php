<?php

namespace App\Http\Controllers;

use App\Models\MTermin;

class Termin extends BaseMasterController
{
    protected $model;
    protected function getTitle(): string { return 'Termin Pembayaran'; }
    protected function getBaseRoute(): string { return 'termin'; }
    protected function getFieldCode(): string { return 'termcode'; }
    protected function getFieldName(): string { return 'termname'; }
    protected function getTableName(): string { return 'termin'; }

    public function __construct()
    {
        $this->model = new MTermin();
    }

    protected function getTableColumns(): array 
    { 
        return ['No', 'Kode', 'Nama', 'Hari', 'Status', 'Aksi']; 
    }

    protected function getExtraColumns($item): array 
    { 
        $itemArray = is_object($item) ? (array)$item : $item;
        return [e($itemArray['day'] ?? '')]; 
    }

    protected function getExtraFields(): array 
    { 
        return [
            [
                'name'  => 'day',
                'label' => 'Hari',
                'type'  => 'number' 
            ]
        ]; 
    }
}
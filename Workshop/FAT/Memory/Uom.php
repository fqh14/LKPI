<?php

namespace App\Http\Controllers;

use App\Models\MUom; 

class Uom extends BaseMasterController
{
    protected $model;

    protected function getTitle(): string { return 'Satuan (UoM)'; }
    protected function getBaseRoute(): string { return 'uom'; }
    protected function getFieldCode(): string { return 'uomcode'; }
    protected function getFieldName(): string { return 'uomname'; }
    protected function getTableName(): string { return 'uom'; }
    
    protected function getTableColumns(): array 
    { 
        return ['No', 'Kode', 'Nama', 'Status', 'Aksi']; 
    }

    public function __construct()
    {
        $this->model = new MUom(); 
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\MCurrency; 

class Currency extends BaseMasterController
{
    protected $model;
    
    protected function getTitle(): string { return 'Mata Uang (Currency)'; }
    protected function getBaseRoute(): string { return 'currency'; }
    protected function getFieldCode(): string { return 'currcode'; }
    protected function getFieldName(): string { return 'currname'; }
    protected function getTableName(): string { return 'currency'; }
    
    protected function getTableColumns(): array 
    { 
        return ['No', 'Kode', 'Nama', 'Status', 'Aksi']; 
    }

    public function __construct()
    {
        $this->model = new MCurrency(); 
    }
}
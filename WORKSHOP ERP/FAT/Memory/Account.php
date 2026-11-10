<?php

namespace App\Http\Controllers;

use App\Models\MAccount; 

class Account extends BaseMasterController
{
    protected $model;

    protected function getTitle(): string { return 'Chart of Account (COA)'; }
    protected function getBaseRoute(): string { return 'account'; }
    protected function getFieldCode(): string { return 'acctcode'; }
    protected function getFieldName(): string { return 'acctname'; }
    protected function getTableName(): string { return 'account'; }
    
    protected function getTableColumns(): array { 
        return ['No', 'Kode', 'Nama', 'Status', 'Aksi']; 
    }

    public function __construct()
    {
        $this->model = new MAccount(); 
    }
}
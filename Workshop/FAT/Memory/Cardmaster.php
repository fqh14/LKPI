<?php

namespace App\Http\Controllers;

use App\Models\MCardmaster;
use App\Models\MCardgroup;
use App\Models\MTermin;
use App\Models\MAccount;

class Cardmaster extends BaseMasterController
{
    protected $model;
    protected MCardgroup $mCardgroup;
    protected MTermin $mTermin;
    protected MAccount $mAccount;

    protected function getTitle(): string { return 'Business Partner'; }
    protected function getBaseRoute(): string { return 'cardmaster'; }
    protected function getFieldCode(): string { return 'cardcode'; }
    protected function getFieldName(): string { return 'cardname'; }
    protected function getTableName(): string { return 'cardmaster'; }

    public function __construct()
    {
        $this->model        = new MCardmaster();
        $this->mCardgroup   = new MCardgroup();
        $this->mTermin      = new MTermin();
        $this->mAccount     = new MAccount();
    }

    protected function getTableColumns(): array 
    { 
        return ['No', 'Kode', 'Nama', 'Alamat', 'Telepon', 'Status', 'Aksi']; 
    }

    protected function getExtraColumns($item): array 
    { 
        $itemObj = (object)$item;
        return [
            e($itemObj->address ?? ''),
            e($itemObj->phone ?? ''),
        ]; 
    }

    protected function getExtraFields(): array 
    { 
        return [
            [
                'name'    => 'cardgroupcode',
                'label'   => 'Grup Partner',
                'type'    => 'select',
                'options' => $this->formatOptions($this->mCardgroup->filters([]), 'cardgroupcode', 'cardgroupname'),
            ],
            ['name'  => 'address', 'label' => 'ALAMAT', 'type' => 'textarea'],
            ['name'  => 'email', 'label' => 'EMAIL', 'type' => 'email'],
            ['name'  => 'phone', 'label' => 'TELPON', 'type' => 'text'],
            ['name'  => 'subdistrict', 'label' => 'KECAMATAN', 'type' => 'text'],
            ['name'  => 'city', 'label' => 'KOTA', 'type' => 'text'],
            [
                'name'    => 'termcode',
                'label'   => 'Termin',
                'type'    => 'select',
                'options' => $this->formatOptions($this->mTermin->filters(['status' => 1]), 'termcode', 'termname'),
            ],
            ['name'  => 'discount', 'label' => 'DISKON (%)', 'type' => 'number'],
            ['name'  => 'creditlimit', 'label' => 'LIMIT', 'type' => 'number'],
            [
                'name'    => 'debpay-acct',
                'label'   => 'Account Payable',
                'type'    => 'select',
                'options' => $this->formatOptions($this->mAccount->filters(['status' => 1]), 'acctcode', 'acctname'),
            ],
            [
                'name'    => 'dpmclear-acct',
                'label'   => 'Account Down Payment',
                'type'    => 'select',
                'options' => $this->formatOptions($this->mAccount->filters(['status' => 1]), 'acctcode', 'acctname'),
            ],
        ];
    }
}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Session;
use App\Models\MPurchaserequest;
use App\Models\MWarehousemaster;
use App\Models\MItemmaster;

class Purchaserequest extends BaseTransactionController
{
    protected $model;
    protected MWarehousemaster $mWarehousemaster;
    protected MItemmaster $mItemmaster;
    
    protected bool $showImport = false;
    protected bool $showRemark = true;
    protected bool $showSummary = false;
    protected bool $showFreight = false;
    protected bool $showBtndraft = true;

    public function __construct()
    {
        $this->model                = new MPurchaserequest();
        $this->mWarehousemaster     = new MWarehousemaster();
        $this->mItemmaster          = new MItemmaster();
    }

    protected function getTitle(): string { return 'Purchase Request'; }
    protected function getBaseRoute(): string { return 'purchaserequest'; }
    protected function getTxnNumberField(): string { return 'docnum'; }
    protected function getDateField(): string { return 'docdate'; }

    protected function generateTxnNumber(): string 
    { 
        return method_exists($this->model, 'generateNumber') ? $this->model->generateNumber() : 'PR-' . date('ymdHis'); 
    }

    protected function getTableColumns(): array { return ['No', 'Nomor PR', 'Tanggal', 'Tanggal Req','Gudang', 'Status', 'Aksi']; }

    protected function getExtraColumns($item): array 
    { 
        $itemArray = is_object($item) ? (array)$item : $item;
        return [
            e($itemArray['requireddate'] ?? '-'),
            e($itemArray['whsname'] ?? '-'),
        ]; 
    }

    protected function getExtraFields($row = null): array
    {
        return [
            [
                'name'    => 'whscode',
                'label'   => 'Gudang Tujuan',
                'type'    => 'select',
                'value'   => $row['whscode'] ?? 'VPCH',
                'options' => $this->formatOptions($this->mWarehousemaster->filters([
                    'status' => 1
                ]), 'whscode', 'whsname'),
                'readonly' => true
            ],
            [
                'name'    => 'requireddate',
                'label'   => 'Tanggal Dibutuhkan',
                'type'    => 'date',
                'value'   => $row['requireddate'] ?? date('Y-m-d'),
                'options' => []
            ]
        ];
    }

    protected function getDetailFields(): array
    {
        $items = $this->mItemmaster->filters(['status' => 1]);
        $itemOptions = [];
        foreach ($items as $item) {
            $itemArray = is_object($item) ? (array)$item : $item;
            $itemOptions[$itemArray['itemcode']] = [
                'label' => $itemArray['itemname'],
                'uom'   => $itemArray['uomcode'] ?? $itemArray['uom'] ?? ''
            ];
        }

        return [
            [
                'name'        => 'itemcode',
                'label'       => 'Item Produk',
                'type'        => 'datalist',
                'options'     => $itemOptions,
                'required'    => true,
                'placeholder' => 'Pilih Item'
            ],
            [
                'name'        => 'uomcode',
                'label'       => 'Uom',
                'type'        => 'text',
                'width'       => '10%',
                'readonly'    => true,
            ],
            [
                'name'        => 'qty',
                'label'       => 'Jumlah',
                'type'        => 'number',
                'class'       => 'text-end',
                'required'    => true,
                'min'         => 0
            ],
            [
                'name'        => 'remark',
                'label'       => 'Catatan',
                'type'        => 'textarea',
                'placeholder' => 'Catatan'
            ]
        ];
    }

    protected function prepareHeaderOnCreate(array $postData, int $status, Request $request = null): array
    {
        $header                 = parent::prepareHeaderOnCreate($postData, $status, $request ?? request());
        $header['docdate']      = !empty($header['docdate']) ? $header['docdate'] : date('Y-m-d H:i:s');
        $header['whscode']      = $postData['whscode'] ?? '';
        $header['requireddate'] = $postData['requireddate'] ?? date('Y-m-d');
        $header['remark']       = $postData['remark'] ?? '';
        $header['createddate']  = date('Y-m-d H:i:s');
        $header['createdby']    = (int) (Session::get('usercode') ?? 1); 
        return $header;
    }

    protected function prepareDetailsOnCreate(array $items, string $txnNumber): array
    {
        $detailsData = [];

        foreach ($items as $index => $item) {
            $detailsData[] = [
                'docnum'   => $txnNumber,
                'linenum'  => $index + 1,
                'itemcode' => $item['itemcode'] ?? '',
                'uomcode'  => $item['uomcode'] ?? '',
                'qty'      => (float) ($item['qty'] ?? 0),
                'remark'   => $item['remark'] ?? '',
            ];
        }

        return $detailsData;
    }

    protected function prepareHeaderData(string $txnNumber, array $postData): array
    {
        return [
            'docnum'       => $txnNumber,
            'docdate'      => $postData['docdate'] ?? date('Y-m-d H:i:s'),
            'whscode'      => $postData['whscode'] ?? '',
            'requireddate' => $postData['requireddate'] ?? date('Y-m-d'),
            'remark'       => $postData['remark'] ?? '',
            'updateddate'  => date('Y-m-d H:i:s'),
            'updatedby'    => (int) (Session::get('usercode') ?? 1),
        ];
    }

    protected function prepareDetailsData(string $txnNumber, array $postData): array
    {
        return $this->prepareDetailsOnCreate($postData['items'] ?? [], $txnNumber);
    }

    protected function validateTransaction(array $postData): ?string
    {
        if (empty($postData['whscode'])) {
            return 'Gudang tujuan wajib dipilih.';
        }
        if (empty($postData['requireddate'])) {
            return 'Tanggal dibutuhkan wajib diisi.';
        }

        $items = $postData['items'] ?? [];
        if (empty($items)) {
            return 'Detail barang yang diajukan tidak boleh kosong!';
        }

        foreach ($items as $index => $item) {
            $rowNum = $index + 1;
            
            if (empty($item['itemcode'])) {
                return "Barang pada baris ke-{$rowNum} belum dipilih.";
            }
            if ((float)($item['qty'] ?? 0) <= 0) {
                return "Jumlah (Qty) pada baris ke-{$rowNum} harus lebih dari 0.";
            }
        }

        return null;
    }
}
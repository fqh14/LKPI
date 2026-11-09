<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Session;
use App\Models\MDiscountlist;
use App\Models\MItemmaster;
use App\Models\MSelltype;
use App\Models\MCardmaster;

class Discountlist extends BaseTransactionController
{
    protected $model;
    protected MCardmaster $mCardmaster;
    protected MItemmaster $mItemmaster;
    protected MSelltype $mSelltype;
    
    protected bool $showRemark = false;
    protected bool $showSummary = false;
    protected bool $showBtndraft = true;

    protected bool $showBtnCancel = false;

    public function __construct()
    {
        $this->model       = new MDiscountlist();
        $this->mCardmaster = new MCardmaster();
        $this->mItemmaster = new MItemmaster();
        $this->mSelltype   = new MSelltype();
    }

    protected function getTitle(): string {return 'Discountlist';}
    protected function getBaseRoute(): string {return 'discountlist';}
    protected function getTxnNumberField(): string {return 'docnum';}
    protected function getDateField(): string {return 'docdate';}
    protected function getTableColumns(): array {return ['No', 'Dokumen', 'Tanggal', 'Status', 'Aksi'];}

    protected function generateTxnNumber(): string 
    { 
        return method_exists($this->model, 'generateNumber') ? $this->model->generateNumber() : 'DL-' . date('ymdHis'); 
    }

    protected function getDetailFields(): array
    { 
        $items = $this->mItemmaster->filters(['itemgroupcode' => ['FG'],'status' => 1]);
        $itemOptions = [];
        foreach ($items as $item) {
            $itemArray = (array)$item;
            $itemOptions[$itemArray['itemcode']] = [
                'label' => $itemArray['itemname'],
                'uom'   => $itemArray['uomcode'] ?? $itemArray['uom'] ?? ''
            ];
        }

        return [
            [
                'name'        => 'cardcode',
                'label'       => 'Customer',
                'type'        => 'select',
                'options'     => $this->formatOptions($this->mCardmaster->filters([
                    'cardgroupcode !=' => 'VS',
                    'status'        => 1,
                ]), 'cardcode', 'cardname'),
                'required'    => true,
                'width'       => '10%',
                'placeholder' => 'Customer'
            ],
            [
                'name'        => 'sellcode',
                'label'       => 'Tipe Penjualan',
                'type'        => 'select',
                'options'     => $this->formatOptions($this->mSelltype->filters([
                    'status'        => 1
                ]), 'sellcode', 'sellname'),
                'required'    => true,
                'width'       => '10%',
                'placeholder' => 'Tipe Penjualan'
            ],
            [
                'name'        => 'itemcode',
                'label'       => 'Item',
                'type'        => 'datalist',
                'options'     => $itemOptions,
                'required'    => true,
                'width'       => '42%',
                'placeholder' => 'Produk'
            ],
            [
                'name'        => 'uomcode',
                'label'       => 'Uom',
                'type'        => 'text',
                'width'       => '10%',
                'readonly'    => true,
            ],
            [
                'name'        => 'maxdiscount',
                'label'       => 'Discount',
                'type'        => 'number',
                'class'       => 'text-end',
                'required'    => true,
                'width'       => '8%',
                'min'         => 0,
                'step'        => 0.01
            ],
            [
                'name'        => 'startdate',
                'label'       => 'Tanggal Mulai',
                'type'        => 'date',
                'class'       => 'text-end',
                'width'       => '10%',
                'required'    => true
            ],
            [
                'name'        => 'enddate',
                'label'       => 'Tanggal Berakhir',
                'type'        => 'date',
                'class'       => 'text-end',
                'width'       => '10%',
                'required'    => true,
            ]
        ];
    }

    protected function prepareHeaderOnCreate(array $postData, int $status, Request $request = null): array
    {
        $header = parent::prepareHeaderOnCreate($postData, $status, $request ?? request());
        $header['docdate'] = !empty($header['docdate']) ? $header['docdate'] : date('Y-m-d H:i:s');
        
        return $header;
    }

    protected function prepareDetailsOnCreate(array $items, string $txnNumber, Request $request = null): array
    {
        $detailsData = [];
        $today       = date('Y-m-d');

        foreach ($items as $index => $item) {
            $detailsData[] = [
                'docnum'       => $txnNumber,
                'linenum'      => $index + 1,
                'cardcode'     => $item['cardcode'] ?? '',
                'sellcode'     => $item['sellcode'] ?? '',
                'itemcode'     => $item['itemcode'] ?? '',
                'uomcode'      => $item['uomcode'] ?? '',
                'maxdiscount'  => $item['maxdiscount'] ?? 0,
                'startdate'    => !empty($item['startdate']) ? $item['startdate'] : $today,
                'enddate'      => !empty($item['enddate']) ? $item['enddate'] : $today,
            ];
        }

        return $detailsData;
    }

    protected function prepareHeaderData(string $txnNumber, array $postData): array
    {
        return [
            'docdate' => $postData['docdate'] ?? date('Y-m-d H:i:s'),
        ];
    }

    protected function prepareDetailsData(string $txnNumber, array $postData): array
    {
        return $this->prepareDetailsOnCreate($postData['items'] ?? [], $txnNumber, request());
    }

    protected function validateTransaction(array $postData, ?string $currentDocNum = null): ?string
    {
        $items = $postData['items'] ?? [];
        if (empty($items)) {
            return 'Detail item discountlist tidak boleh kosong! Mohon masukkan minimal 1 barang.';
        }

        foreach ($items as $index => $item) {
            $rowNum = $index + 1;
            
            if (empty($item['cardcode'])) {
                return "Customer pada baris ke-{$rowNum} belum dipilih.";
            }
            if (empty($item['sellcode'])) {
                return "Tipe Penjualan pada baris ke-{$rowNum} belum dipilih.";
            }
            if (empty($item['itemcode'])) {
                return "Produk/Barang pada baris ke-{$rowNum} belum dipilih.";
            }
            if (!isset($item['maxdiscount']) || $item['maxdiscount'] === '') {
                return "Harga pada baris ke-{$rowNum} harus diisi.";
            }
            if ((float)$item['maxdiscount'] < 0) {
                return "Harga pada baris ke-{$rowNum} tidak boleh minus.";
            }
            if (empty($item['startdate']) || empty($item['enddate'])) {
                return "Tanggal Mulai dan Akhir pada baris ke-{$rowNum} harus diisi.";
            }
            if (strtotime($item['startdate']) > strtotime($item['enddate'])) {
                return "Tanggal Mulai pada baris ke-{$rowNum} tidak boleh melebihi Tanggal Berakhir.";
            }
        }

        return null;
    }

    protected function getExcelMapping(): array
    {
        return [
            'Kode Customer'    => 'cardcodecode',
            'Tipe Penjualan'   => 'sellcode',
            'Kode Produk'      => 'itemcode',
            'Harga'            => 'maxdiscount',
            'Tanggal Mulai'    => 'startdate',
            'Tanggal Berakhir' => 'enddate',
        ];
    }

    public function gettemplates(): array
    {
        return [
            'default' => 'discountlist.xlsx',
        ];
    }

}
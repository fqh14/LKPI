<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Models\MSalesquotation;
use App\Models\MCardmaster;
use App\Models\MWarehousemaster;
use App\Models\MSelltype;
use App\Models\MCurrency;
use App\Models\MTax;
use App\Models\MTermin;
use App\Models\MPricelist;
use App\Models\MDiscountlist;
use App\Models\MItemmaster;

class Salesquotation extends BaseTransactionController
{
    protected $model;
    protected MCardmaster $mCardmaster;
    protected MWarehousemaster $mWarehousemaster;
    protected MSelltype $mSelltype;
    protected MCurrency $mCurrency;
    protected MTax $mTax;
    protected MTermin $mTermin;
    protected MPricelist $mPricelist;
    protected MDiscountlist $mDiscountlist;
    protected MItemmaster $mItemmaster;

    protected bool $showImport   = false;
    protected bool $showRemark   = true;
    protected bool $showSummary  = true;
    protected bool $showFreight  = false;
    protected bool $showDpp      = true;
    protected bool $showBtndraft = true;

    public function __construct()
    {
        $this->model             = new MSalesquotation();
        $this->mWarehousemaster  = new MWarehousemaster();
        $this->mItemmaster       = new MItemmaster();
        $this->mCardmaster       = new MCardmaster();
        $this->mCurrency         = new MCurrency();
        $this->mTax              = new MTax();
        $this->mTermin           = new MTermin();
        $this->mSelltype         = new MSelltype();
        $this->mPricelist        = new MPricelist();
        $this->mDiscountlist     = new MDiscountlist();
    }

    protected function getTitle(): string { return 'Sales Quotation'; }
    protected function getBaseRoute(): string { return 'salesquotation'; }
    protected function getTxnNumberField(): string { return 'docnum'; }
    protected function getDateField(): string { return 'docdate'; }
    
    protected function generateTxnNumber(): string 
    { 
        return method_exists($this->model, 'generateNumber') ? $this->model->generateNumber() : 'SQ-' . date('ymdHis'); 
    }

    public function add()
    {
        $data = [
            'title'             => $this->getTitle(),
            'base_route'        => $this->getBaseRoute(),
            'field_code'        => $this->getTxnNumberField(),
            'field_date'        => $this->getDateField(),
            'current_number'    => $this->generateTxnNumber(),
            'extra'             => $this->getExtraFields(),
            'detail_columns'    => $this->getDetailFields(),
            'action_url'        => $this->getBaseRoute() . '/saveadd',
            'show_remark'       => $this->showRemark,
            'show_summary'      => $this->showSummary,
            'show_dpp'          => $this->showDpp,
            'show_btndraft'     => $this->showBtndraft,
            'message'           => '',
        ];

        return view('basetransaction.add', $data);
    }

    protected function getTableColumns(): array 
    { 
        return ['No', 'Nomor', 'Tanggal', 'Jatuh Tempo', 'Tipe', 'Customer', 'Diskon', 'Grand Total', 'Status', 'Aksi']; 
    }

    protected function getExtraColumns($item): array 
    { 
        $itemArray = is_object($item) ? (array)$item : $item;
        return [
            e($itemArray['docduedate'] ?? ''),
            e($itemArray['sellname'] ?? '-'),
            e($itemArray['cardname'] ?? '-'),
            number_format((float)($itemArray['discountamount'] ?? 0), 2, ',', '.'),
            number_format((float)($itemArray['grandtotal'] ?? 0), 2, ',', '.'),
        ]; 
    }

    protected function getExtraFields($row = null): array
    {
        return [
            [
                'name'      => 'docduedate',
                'label'     => 'Jatuh Tempo',
                'type'      => 'date',
                'value'     => $row['docduedate'] ?? date('Y-m-d', strtotime('+21 days')),
                'required'  => true,
            ],
            [
                'name'     => 'whscode',
                'label'    => 'Gudang',
                'type'     => 'select',
                'value'    => $row['whscode'] ?? 'VSLS',
                'options'  => $this->formatOptions($this->mWarehousemaster->filters([
                    'status'    => 1
                ]), 'whscode', 'whsname'),
                'required' => true,
                'readonly' => true
            ],  
            [
                'name'    => 'cardcode',
                'label'   => 'Customer',
                'type'    => 'select',
                'value'   => $row['cardcode'] ?? '',
                'options' => $this->formatOptions($this->mCardmaster->filters([
                    'cardgroupcode' => 'VC',
                    'status'        => 1
                ]), 'cardcode', 'cardname'),
                'required' => true,
            ],
            [
                'name'        => 'sellcode',
                'label'       => 'Tipe Penjualan',
                'type'        => 'select',
                'options'     => $this->formatOptions($this->mSelltype->filters([
                    'status'        => 1
                ]), 'sellcode', 'sellname'),
                'required'    => true,
                'width'       => '15%',
                'placeholder' => 'Masukkan Nama Tipe Penjualan'
            ],
            [
                'name'        => 'taxclude',
                'label'       => 'Sifat Pajak',
                'type'        => 'select',
                'value'       => $row['taxclude'] ?? 0,
                'options'     => [
                    '0' => 'Exclude (Belum Termasuk Harga)',
                    '1' => 'Include (Sudah Termasuk Harga)'
                ],
                'required'    => true
            ],
            [
                'name'        => 'taxcode',
                'label'       => 'Pajak (Tax)',
                'type'        => 'select',
                'value'       => $row['taxcode'] ?? '',
                'options'     => $this->formatOptions($this->mTax->filters([
                    'status'  => 1
                ]), 'taxcode', 'taxname'),
                'placeholder' => 'Pilih Tax'
            ],
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
                'uom'   => $itemArray['uomcode'] ?? $itemArray['uom'] ?? '',
                'price' => $itemArray['price'] ?? $itemArray['unitprice'] ?? 0
            ];
        }

        return [
            [
                'name'          => 'itemcode',
                'label'         => 'Item Produk',
                'type'          => 'datalist',
                'options'       => $itemOptions,
                'required'      => true,
                'placeholder'   => 'Pilih Item'
            ],
            [
                'name'          => 'uomcode',
                'label'         => 'Uom',
                'type'          => 'text',
                'width'         => '9%',
                'readonly'      => true,
            ],
            [
                'name'          => 'qty',
                'label'         => 'Jumlah',
                'type'          => 'number',
                'class'         => 'text-end input-qty',
                'width'         => '9%',
                'min'           => 1,
                'required'      => true,
            ],
            [
                'name'          => 'unitprice',
                'label'         => 'Harga',
                'type'          => 'number',
                'class'         => 'text-end input-price bg-light',
                'width'         => '11%',
                'min'           => 0,
                'required'      => true,
                'readonly'      => true,
            ],
            [
                'name'          => 'discount',
                'label'         => 'Diskon',
                'type'          => 'number',
                'class'         => 'text-end input-discount',
                'max'           => 100,
                'width'         => '9%',
                'min'           => 0,
                'step'          => 0.01
            ],
            [
                'name'          => 'subtotal',
                'label'         => 'Sub Total',
                'type'          => 'number',
                'class'         => 'text-end input-subtotal',
                'width'         => 'auto',
                'readonly'      => true
            ],
            [
                'name'          => 'remark',
                'label'         => 'Catatan',
                'type'          => 'textarea',
                'placeholder'   => 'Catatan Item'
            ]
        ];
    }

    public function saveadd(Request $request): JsonResponse
    {
        if (!$request->isMethod('post')) {
            return $this->ajaxResponse('error', 'Metode request tidak valid.');
        }

        if (!$this->validate($this->getRules())) {
            return $this->ajaxResponse('error', implode(', ', $this->validator->errors()->all()));
        }

        $postData = $request->post();
        if ($valError = $this->validateTransaction($postData)) {
            return $this->ajaxResponse('error', $valError);
        }

        $txnNumberField = $this->getTxnNumberField(); 
        $txnNumber      = $postData[$txnNumberField] ?? '';

        if (empty($txnNumber)) {
            return $this->ajaxResponse('error', 'Nomor Transaksi tidak boleh kosong.');
        }

        $status      = ($postData['btn_submit'] ?? 'save') === 'draft' ? 9 : 1;
        $items       = $postData['items'] ?? [];
        
        unset($postData['items'], $postData['btn_submit']);

        $headerData  = $this->prepareHeaderOnCreate($postData, $status, $items);
        $detailsData = $this->prepareDetailsOnCreate($items, $txnNumber);

        DB::beginTransaction();

        try {
            $saveResult = $this->model->saveadd($headerData, $detailsData, $txnNumberField);
            
            if (($saveResult['status'] ?? '') !== 'success') {
                throw new \Exception($saveResult['message'] ?? 'Gagal menyimpan transaksi Sales Quotation.');
            }

            DB::commit();

            $this->flashSession([
                'status'  => ($status === 9 ? 'draft' : 'add'), 
                'fa_id'   => $txnNumber, 
                'fa_name' => $this->getTitle()
            ]);

            return $this->ajaxResponse('success', 'Berhasil disimpan', ['redirect' => url($this->getBaseRoute())]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'DB Error: ' . $e->getMessage());
        }
    }

    public function saveedit(Request $request, $id): JsonResponse
    {
        if (!$request->isMethod('post')) {
            return $this->ajaxResponse('error', 'Metode request tidak valid.');
        }

        $header = $this->model->find($id);
        if (!$header) {
            return $this->ajaxResponse('error', 'Data tidak ditemukan di database.');
        }

        if (!$this->validate($this->getRules())) {
            return $this->ajaxResponse('error', implode(', ', $this->validator->errors()->all()));
        }

        $postData = $request->post();
        if ($valError = $this->validateTransaction($postData)) {
            return $this->ajaxResponse('error', $valError);
        }

        $headerArray = is_object($header) ? (array)$header : $header;
        $txnNumber   = $headerArray[$this->getTxnNumberField()];
        $btnSubmit   = $postData['btn_submit'] ?? $postData['btn_submit_action'] ?? 'save';
        $items       = $postData['items'] ?? [];
        
        $oldStatus = (int)($headerArray['status'] ?? 9);
        $newStatus = ($oldStatus === 9) ? (($btnSubmit === 'draft') ? 9 : 1) : 1;

        $headerData  = $this->prepareHeaderData($txnNumber, $postData, $newStatus, $items);
        $detailsData = $this->prepareDetailsData($txnNumber, $postData);

        unset($headerData['docdate'], $headerData['createddate'], $headerData['createdby']);

        $headerData['updateddate'] = date('Y-m-d H:i:s');
        $headerData['updatedby']   = (int) (Session::get('usercode') ?? 1);

        if ($newStatus === 1) {
            $headerData['status'] = 1;
            if ($oldStatus === 9 && empty($headerArray['approveddate'])) {
                $headerData['approveddate'] = date('Y-m-d H:i:s');
                $headerData['approvedby']   = (int) (Session::get('usercode') ?? 1);
            }
        } else {
            $headerData['status'] = 9;
        }
        
        DB::beginTransaction();

        try {
            $editResult = $this->model->saveedit($id, $headerData, $detailsData);
            if (($editResult['status'] ?? '') === 'error') {
                throw new \Exception($editResult['message'] ?? 'Gagal memperbarui data.');
            }

            DB::commit();

            $this->flashSession([
                'status'  => ((int)$newStatus === 9 ? 'draft' : 'edit'), 
                'fa_id'   => $txnNumber, 
                'fa_name' => $this->getTitle()
            ]);

            return $this->ajaxResponse('success', 'Berhasil diperbarui', ['redirect' => url($this->getBaseRoute())]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'DB Error: ' . $e->getMessage());
        }
    }

    protected function prepareHeaderOnCreate(array $postData, int $status, Request $request = null): array
    {
        $header = parent::prepareHeaderOnCreate($postData, $status, request());
        $computed = $this->calculateHeaderTotals(array_merge($postData, ['items' => $items]));

        $headerData = array_merge($header, [
            'docnum'         => $postData['docnum'] ?? '',
            'docdate'        => !empty($header['docdate']) ? $header['docdate'] : date('Y-m-d H:i:s'),
            'docduedate'     => $postData['docduedate'] ?? date('Y-m-d'),
            'cardcode'       => $postData['cardcode'] ?? '',
            'whscode'        => $postData['whscode'] ?? '',
            'remark'         => $postData['remark'] ?? '',
            'status'         => $status,
            'subtotal'       => $computed['subtotal'],
            'discountamount' => $computed['discountamount'],
            'dppamount'      => $computed['dppamount'], 
            'taxclude'       => $computed['taxclude'],
            'taxcode'        => $computed['taxcode'],
            'taxamount'      => $computed['taxamount'],
            'grandtotal'     => $computed['grandtotal'],
            'createddate'    => date('Y-m-d H:i:s'),
            'createdby'      => (int) (Session::get('usercode') ?? 1),
        ]);

        if ($status === 1) {
            $headerData['approveddate'] = date('Y-m-d H:i:s');
            $headerData['approvedby']   = (int) (Session::get('usercode') ?? 1);
        }

        return $headerData;
    }

    protected function prepareHeaderData(string $txnNumber, array $postData, int $status = 1, array $items = []): array
    {
        $computed = $this->calculateHeaderTotals(array_merge($postData, ['items' => $items]));

        $headerData = [
            'docnum'         => $txnNumber,
            'docduedate'     => $postData['docduedate'] ?? date('Y-m-d'),
            'docdate'        => $postData['docdate'] ?? date('Y-m-d H:i:s'),
            'cardcode'       => $postData['cardcode'] ?? '',
            'whscode'        => $postData['whscode'] ?? '',
            'subtotal'       => $computed['subtotal'],
            'discountamount' => $computed['discountamount'],
            'dppamount'      => $computed['dppamount'],
            'taxclude'       => $computed['taxclude'],
            'taxcode'        => $computed['taxcode'],
            'taxamount'      => $computed['taxamount'],
            'grandtotal'     => $computed['grandtotal'],
            'remark'         => $postData['remark'] ?? '',
            'status'         => $status,
            'updateddate'    => date('Y-m-d H:i:s'),
            'updatedby'      => (int) (Session::get('usercode') ?? 1),
        ];

        if ($status === 1) {
            $headerData['approveddate'] = date('Y-m-d H:i:s');
            $headerData['approvedby']   = (int) (Session::get('usercode') ?? 1);
        }

        return $headerData;
    }

    protected function prepareDetailsOnCreate(array $items, string $txnNumber, Request $request = null): array
    {
        $detailsData = [];

        foreach ($items as $index => $item) {
            $itemCode  = $item['itemcode'] ?? '';
            $qty       = $this->parseNumber($item['qty'] ?? 0);
            $unitprice = $this->parseNumber($item['unitprice'] ?? 0);
            $discount  = $this->parseNumber($item['discount'] ?? 0);

            $itemGross    = $qty * $unitprice;
            $discNominal  = $itemGross * ($discount / 100);
            $itemSubtotal = $itemGross - $discNominal;

            $detailsData[] = [
                'docnum'    => $txnNumber,
                'linenum'   => $index + 1,
                'itemcode'  => $itemCode,
                'uomcode'   => $item['uomcode'] ?? '',
                'qty'       => $qty,
                'unitprice' => $unitprice,
                'discount'  => $discount,
                'subtotal'  => $itemSubtotal,
                'remark'    => $item['remark'] ?? '',
            ];
        }

        return $detailsData;
    }

    protected function prepareDetailsData(string $txnNumber, array $postData): array
    {
        return $this->prepareDetailsOnCreate($postData['items'] ?? [], $txnNumber);
    }

    private function parseNumber($val): float
    {
        if ($val === null || $val === '') return 0.0;
        if (is_numeric($val)) return (float) $val;

        $val = trim((string) $val);
        if (strpos($val, '.') !== false && strpos($val, ',') !== false) {
            $val = str_replace('.', '', $val);
            $val = str_replace(',', '.', $val);
        } 
        else if (strpos($val, '.') !== false) {
            $val = str_replace('.', '', $val);
        } 
        else if (strpos($val, ',') !== false) {
            $val = str_replace(',', '.', $val);
        }

        return (float) $val;
    }

    protected function validateTransaction(array $postData): ?string
    {
        $cardCode = $postData['cardcode'] ?? '';
        $sellCode = $postData['sellcode'] ?? '';
        
        if (empty($cardCode)) {
            return 'Customer wajib dipilih.';
        }
        if (empty($postData['whscode'])) {
            return 'Gudang wajib dipilih.';
        }
        if (empty($sellCode)) {
            return 'Tipe Penjualan wajib dipilih.';
        }

        $items = $postData['items'] ?? [];
        if (empty($items)) {
            return 'Detail barang pada Sales Quotation tidak boleh kosong!';
        }

        $docDate = $postData['docdate'] ?? date('Y-m-d');

        foreach ($items as $index => $item) {
            $rowNum    = $index + 1;
            $itemCode  = $item['itemcode'] ?? '';
            $qty       = $this->parseNumber($item['qty'] ?? 0);
            $unitprice = $this->parseNumber($item['unitprice'] ?? 0);
            $discount  = $this->parseNumber($item['discount'] ?? 0);
            
            if (empty($itemCode)) {
                return "Barang pada baris ke-{$rowNum} belum dipilih.";
            }
            if ($qty <= 0) {
                return "Jumlah (Qty) pada baris ke-{$rowNum} harus lebih dari 0.";
            }

            $priceList = $this->mPricelist->getPricelistActive($itemCode, $docDate, $sellCode);
            $priceListArray = is_object($priceList) ? (array)$priceList : $priceList;
            if (!$priceListArray) {
                return "Item baris ke-{$rowNum} ({$itemCode}) tidak memiliki Price List aktif untuk Tipe Penjualan ini.";
            }
            if ($unitprice != (float)$priceListArray['pricevalue']) {
                return "Harga satuan item baris ke-{$rowNum} tidak valid/berubah dari master Price List.";
            }

            if ($discount > 0) {
                $maxDiscount = $this->mDiscountlist->getMaxDiscountActive($cardCode, $sellCode, $itemCode, $docDate);
                if ($discount > $maxDiscount) {
                    return "Diskon item baris ke-{$rowNum} ({$discount}%) melebihi batas maksimal yang diizinkan ({$maxDiscount}%).";
                }
            }
        }

        return null;
    }

    public function getItemPrice(Request $request): JsonResponse
    {
        if (!$request->isMethod('post')) return response()->json(['status' => false, 'message' => 'Invalid request']);

        $itemCode = $request->post('itemcode');
        $sellCode = $request->post('sellcode');
        $cardCode = $request->post('cardcode');
        $docDate  = $request->post('docdate') ?? date('Y-m-d');

        if (empty($sellCode) || empty($cardCode)) {
            return response()->json(['status' => false, 'message' => 'Pilih Customer dan Tipe Penjualan terlebih dahulu.']);
        }

        $priceList = $this->mPricelist->getPricelistActive($itemCode, $docDate, $sellCode);
        $priceListArray = is_object($priceList) ? (array)$priceList : $priceList;

        if ($priceListArray) {
            $maxDiscount = $this->mDiscountlist->getMaxDiscountActive($cardCode, $sellCode, $itemCode, $docDate);
            
            return response()->json([
                'status'       => true, 
                'price'        => (float) $priceListArray['pricevalue'],
                'max_discount' => $maxDiscount
            ]);
        }

        return response()->json([
            'status'  => false, 
            'message' => 'Item tidak memiliki Price List aktif untuk tipe penjualan ini.'
        ]);
    }
}
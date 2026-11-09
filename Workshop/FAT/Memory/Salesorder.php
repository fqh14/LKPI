<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Models\MSalesorder;
use App\Models\MSalesquotation;
use App\Models\MWarehousemaster;
use App\Models\MCardmaster;
use App\Models\MSelltype;
use App\Models\MCurrency;
use App\Models\MTermin;
use App\Models\MTax;
use App\Models\MItemmaster;
use App\Models\MItemwarehouse;

class Salesorder extends BaseTransactionController
{
    protected $model;
    protected MSalesquotation $mSalesquotation;
    protected MWarehousemaster $mWarehousemaster;
    protected MCardmaster $mCardmaster;
    protected MSelltype $mSelltype;
    protected MCurrency $mCurrency;
    protected MTermin $mTermin;
    protected MTax $mTax;
    protected MItemmaster $mItemmaster;
    protected MItemwarehouse $mItemwarehouse;

    protected bool $showImport  = false;
    protected bool $showRemark  = true;
    protected bool $showSummary = true;
    protected bool $showFreight = false;
    protected bool $showDpp     = true;
    protected bool $showBtndraft = true;

    public function __construct()
    {
        $this->model             = new MSalesorder();
        $this->mSalesquotation   = new MSalesquotation();
        $this->mWarehousemaster  = new MWarehousemaster();
        $this->mCardmaster       = new MCardmaster();
        $this->mSelltype         = new MSelltype();
        $this->mCurrency         = new MCurrency();
        $this->mTermin           = new MTermin();
        $this->mTax              = new MTax();
        $this->mItemmaster       = new MItemmaster();
        $this->mItemwarehouse    = new MItemwarehouse();    
    }

    protected function getTitle(): string { return 'Sales Order'; }
    protected function getBaseRoute(): string { return 'salesorder'; }
    protected function getTxnNumberField(): string { return 'docnum'; }
    protected function getDateField(): string { return 'docdate'; }
    
    protected function generateTxnNumber(): string 
    { 
        return method_exists($this->model, 'generateNumber') ? $this->model->generateNumber() : 'SO-' . date('ymdHis'); 
    }

    protected function getCopyFromColumns(): array
    {
        return [
            [
                'name'  => 'docnum',
                'label' => 'Dokumen',
                'class' => 'font-weight-bold'
            ],
            [
                'name'  => 'docdate',
                'label' => 'Tanggal'
            ],
            [
                'name'  => 'sellname',
                'label' => 'Penjualan'
            ],
            [
                'name'  => 'cardname',
                'label' => 'Customer'
            ],
            [
                'name'  => 'grandtotal',
                'label' => 'Total',
                'class' => 'text-end',
                'format' => 'number' 
            ],
            [
                'name'  => 'remark',
                'label' => 'Catatan'
            ]
        ];
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
            
            'copy_from_url'        => url('salesorder/getOpenList'),
            'copy_from_detail_url' => url('salesorder/getOpenDetail'),
            'copy_from_label'      => 'Copy From SQ',
            'copy_from_columns'    => $this->getCopyFromColumns(),
        ];

        return view('basetransaction.add', $data);
    }

    protected function getTableColumns(): array 
    { 
        return ['No', 'Nomor', 'Tanggal', 'Tipe', 'Customer', 'Total', 'Diskon', 'Grand Total', 'Status', 'Aksi']; 
    }

    protected function getExtraColumns($item): array 
    { 
        $itemArray = is_object($item) ? (array)$item : $item;
        return [
            e($itemArray['sellname'] ?? '-'),
            e($itemArray['cardname'] ?? '-'),
            number_format((float)($itemArray['subtotal'] ?? 0), 2, ',', '.'),
            number_format((float)($itemArray['discountamount'] ?? 0), 2, ',', '.'),
            number_format((float)($itemArray['grandtotal'] ?? 0), 2, ',', '.'),
        ]; 
    }

    protected function getExtraFields($row = null): array
    {        
        return [
            [
                'name'      => 'baseentry',
                'label'     => 'No Referensi',
                'type'      => 'text',
                'value'     => $row['baseentry'] ?? '',
                'disable'   => true,                
                'readonly'  => true,
            ],
            [
                'name'     => 'whscode',
                'label'    => 'Gudang',
                'type'     => 'select',
                'options' => $this->formatOptions($this->mWarehousemaster->filters([
                    'status'    => 1
                ]), 'whscode', 'whsname'),
                'readonly' => true,
            ],
            [
                'name'    => 'cardcode',
                'label'   => 'Customer',
                'type'    => 'select',
                'value'   => $row['cardcode'] ?? '',
                'options' => $this->formatOptions($this->mCardmaster->filters([
                    'cardgroupcode =' => 'VC',
                    'status'           => 1
                ]), 'cardcode', 'cardname'),
            ],
            [
                'name'        => 'sellcode',
                'label'       => 'Tipe Penjualan',
                'type'        => 'select',
                'options'     => $this->formatOptions($this->mSelltype->filters([
                    'status'    => 1
                ]), 'sellcode', 'sellname'),
                'required'    => true,              
                'readonly'    => true
            ],
            [
                'name'    => 'delivereddate',
                'label'   => 'Estimasi Pengiriman',
                'type'    => 'date',
                'value'   => $row['delivereddate'] ?? date('Y-m-d'),
                'required' => true
            ],
            [
                'name'    => 'currcode',
                'label'   => 'Mata Uang',
                'type'    => 'select',
                'value'   => $row['currcode'] ?? 'IDR',
                'options' => $this->formatOptions($this->mCurrency->filters([
                    'status' => 1
                ]), 'currcode', 'currname'),
                'required' => true
            ],
            [
                'name'    => 'taxclude',
                'label'   => 'Tipe Pajak',
                'type'    => 'select',
                'value'   => $row['taxclude'] ?? 0,
                'options' => [0 => 'Exclude', 1 => 'Include']
            ],
            [
                'name'    => 'taxcode',
                'label'   => 'Kode Pajak Global',
                'type'    => 'select',
                'value'   => $row['taxcode'] ?? '',
                'options' => $this->formatOptions($this->mTax->filters([
                    'status' => 1
                ]), 'taxcode', 'taxname'),
                'placeholder' => 'Pilih Pajak'
            ],
            [
                'name'    => 'termcode',
                'label'   => 'Syarat Pembayaran',
                'type'    => 'select',
                'value'   => $row['termcode'] ?? '',
                'options' => $this->formatOptions($this->mTermin->filters([
                    'status' => 1
                ]), 'termcode', 'termname'),
                'required' => true
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
                'type'          => 'select',
                'options'       => $this->formatOptions($this->mItemmaster->filters([
                    'status' => 1
                ]), 'itemcode', 'itemname'),
                'placeholder'   => 'Pilih Item',
                'required'      => true,
                'readonly'      => true,
            ],
            [
                'name'          => 'uomcode',
                'label'         => 'Uom',
                'type'          => 'text',
                'width'         => 'auto',
                'readonly'      => true,
            ],
            [
                'name'          => 'qty',
                'label'         => 'Jumlah',
                'type'          => 'number',
                'class'         => 'text-end input-qty',
                'min'           => 0,
                'required'      => true,
            ],
            [
                'name'          => 'unitprice',
                'label'         => 'Harga',
                'type'          => 'number',
                'class'         => 'text-end input-price',
                'min'           => 0,
                'required'      => true,
            ],
            [
                'name'          => 'discount',
                'label'         => 'Diskon',
                'type'          => 'number',
                'class'         => 'text-end input-discount',
                'max'           => 100,
                'min'           => 0,
                'required'      => true,
            ],
            [
                'name'          => 'subtotal',
                'label'         => 'Sub Total',
                'type'          => 'number',
                'class'         => 'text-end input-subtotal',
                'width'         => 'auto',
                'readonly'      => true
            ],
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
        $items       = $postData['items'] ?? $postData['details'] ?? [];
        
        unset($postData['items'], $postData['details'], $postData['btn_submit']);

        $headerData  = $this->prepareHeaderOnCreate($postData, $status, $items);
        $detailsData = $this->prepareDetailsOnCreate($items, $txnNumber);

        DB::beginTransaction();

        try {
            $saveResult = $this->model->saveadd($headerData, $detailsData, $txnNumberField);
            
            if (($saveResult['status'] ?? '') !== 'success') {
                throw new \Exception($saveResult['message'] ?? 'Gagal menyimpan transaksi Sales Order.');
            }

            if ($status === 1) {
                $whsCode = $headerData['whscode'] ?? '';
                if (!empty($whsCode)) {
                    foreach ($detailsData as $row) {
                        $itemCode = $row['itemcode'] ?? '';
                        $qty      = (float)($row['qty'] ?? 0);

                        if (!empty($itemCode) && $qty > 0) {
                            if (method_exists($this->mItemwarehouse, 'addCommitted')) {
                                $this->mItemwarehouse->addCommitted($itemCode, $whsCode, $qty);
                            }
                        }
                    }
                }
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
        $items       = $postData['items'] ?? $postData['details'] ?? [];
        
        $oldStatus = (int)($headerArray['status'] ?? 9);
        $newStatus = ($oldStatus === 9) ? (($btnSubmit === 'draft') ? 9 : 1) : 1;

        $headerData  = $this->prepareHeaderData($txnNumber, $postData, $newStatus, $items);
        $detailsData = $this->prepareDetailsOnCreate($items, $txnNumber);

        unset($headerData['docdate'], $headerData['createddate'], $headerData['createdby']);

        $headerData['updateddate'] = date('Y-m-d H:i:s');
        $headerData['updatedby']   = (int) (Session::get('usercode') ?? 1);

        if ($newStatus === 1) {
            $headerData['status'] = 1;
            if (empty($headerArray['approveddate'])) {
                $headerData['approveddate'] = date('Y-m-d H:i:s');
                $headerData['approvedby']   = (int) (Session::get('usercode') ?? 1);
            }
        } else {
            $headerData['status']       = 9;
            $headerData['approveddate'] = null;
            $headerData['approvedby']   = null;
        }
        
        unset($headerData['items'], $headerData['details']);

        $oldData    = $this->model->getWithDetails($txnNumber);
        $oldDetails = $oldData['details'] ?? [];
        $oldWhsCode = $oldData['header']['whscode'] ?? $headerArray['whscode'] ?? '';

        DB::beginTransaction();

        try {
            if ($oldStatus === 1 && !empty($oldWhsCode) && !empty($oldDetails)) {
                foreach ($oldDetails as $oldRow) {
                    $oldRowArray = is_object($oldRow) ? (array)$oldRow : $oldRow;
                    $oldItemCode = $oldRowArray['itemcode'] ?? '';
                    $oldQty      = (float)($oldRowArray['qty'] ?? 0);
                    if (!empty($oldItemCode) && $oldQty > 0) {
                        if (method_exists($this->mItemwarehouse, 'reduceCommitted')) {
                            $this->mItemwarehouse->reduceCommitted($oldItemCode, $oldWhsCode, $oldQty);
                        }
                    }
                }
            }

            $editResult = $this->model->saveedit($id, $headerData, $detailsData);
            if (($editResult['status'] ?? '') === 'error') {
                throw new \Exception($editResult['message'] ?? 'Gagal memperbarui data.');
            }

            if ($newStatus === 1) {
                $newWhsCode = $headerData['whscode'] ?? '';
                if (!empty($newWhsCode)) {
                    foreach ($detailsData as $newRow) {
                        $newItemCode = $newRow['itemcode'] ?? '';
                        $newQty      = (float)($newRow['qty'] ?? 0);
                        if (!empty($newItemCode) && $newQty > 0) {
                            if (method_exists($this->mItemwarehouse, 'addCommitted')) {
                                $this->mItemwarehouse->addCommitted($newItemCode, $newWhsCode, $newQty);
                            }
                        }
                    }
                }
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

    public function saveactive(Request $request, $id): JsonResponse
    {
        if (!$request->isMethod('post') && !$request->ajax()) {
            return $this->ajaxResponse('error', 'Metode request tidak valid.');
        }

        $header = $this->model->find($id);
        if (!$header) {
            return $this->ajaxResponse('error', 'Data tidak ditemukan di database.');
        }

        $headerArray   = is_object($header) ? (array)$header : $header;
        $currentStatus = (int)($headerArray['status'] ?? 0);

        if ($currentStatus !== 9) {
            return $this->ajaxResponse('error', 'Hanya dokumen berstatus Draft yang dapat diubah menjadi Aktif.');
        }

        $txnNumber   = $headerArray[$this->getTxnNumberField()];
        $whsCode     = $headerArray['whscode'] ?? '';
        $detailsData = $this->model->getWithDetails($txnNumber)['details'] ?? []; 

        DB::beginTransaction();

        try {
            $updateStatus = $this->model->update($id, [
                'status'       => 1,
                'approveddate' => date('Y-m-d H:i:s'),
                'approvedby'   => (int) (Session::get('usercode') ?? 1),
            ]);

            if (!$updateStatus) {
                throw new \Exception('Gagal memperbarui status dokumen.');
            }

            if (!empty($whsCode)) {
                foreach ($detailsData as $row) {
                    $rowArray = is_object($row) ? (array)$row : $row;
                    $itemCode = $rowArray['itemcode'] ?? '';
                    $qty      = (float)($rowArray['qty'] ?? 0);

                    if (!empty($itemCode) && $qty > 0) {
                        if (method_exists($this->mItemwarehouse, 'addCommitted')) {
                            $this->mItemwarehouse->addCommitted($itemCode, $whsCode, $qty);
                        }
                    }
                }
            }

            DB::commit();

            return $this->ajaxResponse('success', 'Sales Order berhasil diaktifkan.', [
                'redirect' => url($this->getBaseRoute())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'DB Error: ' . $e->getMessage());
        }
    }

    public function savecancel(Request $request, $id): JsonResponse
    {
        if (!$request->isMethod('post') && !$request->ajax()) {
            return $this->ajaxResponse('error', 'Metode request tidak valid.');
        }

        $header = $this->model->find($id);
        if (!$header) {
            return $this->ajaxResponse('error', 'Data tidak ditemukan di database.');
        }

        $headerArray   = is_object($header) ? (array)$header : $header;
        $currentStatus = (int)($headerArray['status'] ?? 0);

        if ($currentStatus === 0) {
            return $this->ajaxResponse('error', 'Dokumen sudah dalam status Cancel.');
        }

        $txnNumber   = $headerArray[$this->getTxnNumberField()];
        $whsCode     = $headerArray['whscode'] ?? '';
        $detailsData = $this->model->getWithDetails($txnNumber)['details'] ?? [];

        DB::beginTransaction();

        try {
            $updateStatus = $this->model->update($id, [
                'status'       => 0,
                'canceleddate' => date('Y-m-d H:i:s'),
                'canceledby'   => (int) (Session::get('usercode') ?? 1),
            ]);

            if (!$updateStatus) {
                throw new \Exception('Gagal membatalkan dokumen.');
            }

            if ($currentStatus === 1 && !empty($whsCode) && !empty($detailsData)) {
                foreach ($detailsData as $row) {
                    $rowArray = is_object($row) ? (array)$row : $row;
                    $itemCode = $rowArray['itemcode'] ?? '';
                    $qty         = (float)($rowArray['qty'] ?? 0);
                    $qtyDelivered = (float)($rowArray['qtydelivered'] ?? 0);
                    $remainingQty = max(0, $qty - $qtyDelivered);

                    if (!empty($itemCode) && $remainingQty > 0) {
                        if (method_exists($this->mItemwarehouse, 'reduceCommitted')) {
                            $this->mItemwarehouse->reduceCommitted($itemCode, $whsCode, $remainingQty);
                        }
                    }
                }
            }

            DB::commit();

            return $this->ajaxResponse('success', 'Sales Order berhasil dibatalkan dan alokasi stok dikembalikan.', [
                'redirect' => url($this->getBaseRoute())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'DB Error: ' . $e->getMessage());
        }
    }

    protected function prepareHeaderOnCreate(array $postData, int $status, Request $request = null): array
    {
        $header = parent::prepareHeaderOnCreate($postData, $status, request());
        $computed = $this->calculateHeaderTotals(array_merge($postData, ['items' => $items]));

        $data = array_merge($header, [
            'docdate'        => !empty($header['docdate']) ? $header['docdate'] : date('Y-m-d H:i:s'),
            'baseentry'      => !empty($postData['baseentry']) ? $postData['baseentry'] : null,
            'cardcode'       => $postData['cardcode'] ?? '',
            'sellcode'       => $postData['sellcode'] ?? '',
            'whscode'        => $postData['whscode'] ?? '',
            'currcode'       => $postData['currcode'] ?? 'IDR',
            'delivereddate'  => !empty($postData['delivereddate']) ? $postData['delivereddate'] : date('Y-m-d'),
            'termcode'       => $postData['termcode'] ?? '',
            'remark'         => $postData['remark'] ?? '',
            'taxclude'       => isset($postData['taxclude']) ? (int)$postData['taxclude'] : 0,
            'taxcode'        => $postData['taxcode'] ?? '',
            'status'         => $status,
            'subtotal'       => $computed['subtotal'],
            'discountamount' => $computed['discountamount'],
            'dppamount'      => $computed['dppamount'],
            'taxamount'      => $computed['taxamount'],
            'grandtotal'     => $computed['grandtotal'],
            'createddate'    => date('Y-m-d H:i:s'),
            'createdby'      => (int) (Session::get('usercode') ?? 1),
        ]);

        if ($status === 1) {
            $data['approveddate'] = date('Y-m-d H:i:s');
            $data['approvedby']   = (int) (Session::get('usercode') ?? 1);
        }

        return $data;
    }

    protected function prepareHeaderData(string $txnNumber, array $postData, int $status = 1, array $items = []): array
    {
        $computed = $this->calculateHeaderTotals(array_merge($postData, ['items' => $items]));

        $data = [
            'docnum'         => $txnNumber,
            'baseentry'      => !empty($postData['baseentry']) ? $postData['baseentry'] : null,
            'docdate'        => $postData['docdate'] ?? date('Y-m-d H:i:s'),
            'cardcode'       => $postData['cardcode'] ?? '',
            'sellcode'       => $postData['sellcode'] ?? '',
            'whscode'        => $postData['whscode'] ?? '',
            'currcode'       => $postData['currcode'] ?? 'IDR',
            'delivereddate'  => $postData['delivereddate'] ?? date('Y-m-d'),
            'termcode'       => $postData['termcode'] ?? '',
            'taxclude'       => isset($postData['taxclude']) ? (int)$postData['taxclude'] : 0,
            'taxcode'        => $postData['taxcode'] ?? '',
            'subtotal'       => $computed['subtotal'],
            'discountamount' => $computed['discountamount'],
            'dppamount'      => $computed['dppamount'],
            'taxamount'      => $computed['taxamount'],
            'grandtotal'     => $computed['grandtotal'],
            'remark'         => $postData['remark'] ?? '',
            'status'         => $status,
            'updateddate'    => date('Y-m-d H:i:s'),
            'updatedby'      => (int) (Session::get('usercode') ?? 1),
        ];

        if ($status === 1) {
            $data['approveddate'] = date('Y-m-d H:i:s');
            $data['approvedby']   = (int) (Session::get('usercode') ?? 1);
        }

        return $data;
    }

    protected function prepareDetailsOnCreate(array $items, string $txnNumber, Request $request = null): array
    {
        $detailsData = [];

        foreach ($items as $index => $item) {
            $qty       = $this->parseNumber($item['qty'] ?? 0);
            $unitprice = $this->parseNumber($item['unitprice'] ?? 0);
            $discount  = $this->parseNumber($item['discount'] ?? 0);

            $itemGross   = $qty * $unitprice;
            $discNominal = $itemGross * ($discount / 100);
            $itemSubtotal = $itemGross - $discNominal;

            $detailsData[] = [
                'docnum'        => $txnNumber,
                'linenum'       => $index + 1,
                'baseline'      => $item['baseline'] ?? $item['linenum'] ?? ($index + 1),
                'itemcode'      => $item['itemcode'] ?? '',
                'uomcode'       => $item['uomcode'] ?? '',
                'qty'           => $qty,
                'qtydelivered'  => 0,
                'qtyinvoiced'   => 0,
                'unitprice'     => $unitprice,
                'discount'      => $discount,
                'subtotal'      => $itemSubtotal,
            ];
        }

        return $detailsData;
    }

    private function parseNumber($val): float
    {
        if ($val === null || $val === '') return 0.0;
        if (is_numeric($val)) return (float) $val;

        $val = trim((string) $val);

        if (strpos($val, '.') !== false && strpos($val, ',') !== false) {
            $val = str_replace('.', '', $val);
            $val = str_replace(',', '.', $val);
        } else if (strpos($val, '.') !== false) {
            $val = str_replace('.', '', $val);
        } else if (strpos($val, ',') !== false) {
            $val = str_replace(',', '.', $val);
        }

        return (float) $val;
    }

    protected function validateTransaction(array $postData): ?string
    {
        if (empty($postData['baseentry'])) {
            return 'Sales Order wajib dibuat melalui referensi Sales Quotation (Base Entry tidak boleh kosong).';
        }

        if (empty($postData['cardcode'])) {
            return 'Customer wajib dipilih.';
        }
        if (empty($postData['whscode'])) {
            return 'Gudang tujuan wajib dipilih.';
        }
        
        $sellCode = $postData['sellcode'] ?? '';
        if (empty($sellCode)) {
            return 'Tipe Penjualan wajib dipilih.';
        }

        $items = $postData['items'] ?? $postData['details'] ?? [];
        if (empty($items)) {
            return 'Detail barang yang dipesan tidak boleh kosong!';
        }

        foreach ($items as $index => $item) {
            $rowNum = $index + 1;
            
            if (empty($item['itemcode'])) {
                return "Barang pada baris ke-{$rowNum} belum dipilih.";
            }
            if ($this->parseNumber($item['qty'] ?? 0) <= 0) {
                return "Jumlah (Qty) pada baris ke-{$rowNum} harus lebih dari 0.";
            }
            if ($this->parseNumber($item['unitprice'] ?? 0) < 0) {
                return "Harga satuan pada baris ke-{$rowNum} tidak boleh bernilai negatif.";
            }
        }

        return null;
    }

    public function getOpenList(Request $request): JsonResponse
    {
        if (!$request->ajax()) {
            return response()->json([], 404);
        }

        $listData = $this->mSalesquotation->getOpen();

        $html = view('basetransaction.popcopyfromrows', [
            'listData' => $listData,
            'copy_from_columns' => $this->getCopyFromColumns(),
        ])->render();

        return response()->json([
            'status' => true, 
            'html'   => $html
        ]);
    }

    public function getOpenDetail(Request $request, $docnum = null): JsonResponse
    {
        if (!$request->ajax() || empty($docnum)) {
            return response()->json([], 404);
        }

        $sqData = $this->mSalesquotation->getWithDetails($docnum);

        if (!$sqData || (isset($sqData['header']['status']) && (int)$sqData['header']['status'] !== 1)) {
            return response()->json([
                'status'  => false, 
                'message' => 'Data Sales Quotation tidak ditemukan atau status sudah tidak aktif.'
            ]);
        }

        $header = $sqData['header'];
        $headerArray = is_object($header) ? (array)$header : $header;
        $headerArray['baseentry'] = $headerArray['docnum'] ?? $docnum;

        unset(
            $headerArray['docnum'], 
            $headerArray['docdate'], 
            $headerArray['status'], 
            $headerArray['createddate'], 
            $headerArray['createdby'], 
            $headerArray['updateddate'], 
            $headerArray['updatedby']
        );

        if (!empty($headerArray['requireddate'])) {
            $headerArray['delivereddate'] = date('Y-m-d', strtotime($headerArray['requireddate']));
            unset($headerArray['requireddate']);
        }

        foreach ($headerArray as $key => $val) {
            if ($val && (strpos($key, 'date') !== false) && strtotime($val)) {
                $headerArray[$key] = date('Y-m-d', strtotime($val));
            }
        }

        $details = [];
        foreach ($sqData['details'] as $det) {
            $detArray = is_object($det) ? (array)$det : $det;
            $details[] = [
                'baseline'  => $detArray['linenum'] ?? 1,
                'itemcode'  => $detArray['itemcode'] ?? '',
                'itemname'  => $detArray['itemname'] ?? '',
                'uomcode'   => $detArray['uomcode'] ?? '',
                'qty'       => $detArray['qty'] ?? 0,
                'unitprice' => $detArray['unitprice'] ?? 0,
                'discount'  => $detArray['discount'] ?? 0,
                'subtotal'  => $detArray['subtotal'] ?? 0,
                'remark'    => $detArray['remark'] ?? '',
            ];
        }

        return response()->json([
            'status'  => true,
            'header'  => $headerArray,
            'details' => $details
        ]);
    }
}
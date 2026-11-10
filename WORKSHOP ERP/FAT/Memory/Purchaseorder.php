<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Models\MPurchaseorder;
use App\Models\MWarehousemaster;
use App\Models\MItemmaster;
use App\Models\MCardmaster;
use App\Models\MPurchaserequest;
use App\Models\MCurrency;
use App\Models\MTax;
use App\Models\MTermin;
use App\Models\MItemwarehouse;

class Purchaseorder extends BaseTransactionController
{
    protected $model;
    protected MWarehousemaster $mWarehousemaster;
    protected MItemmaster $mItemmaster;
    protected MCardmaster $mCardmaster;
    protected MPurchaserequest $mPurchaserequest;
    protected MCurrency $mCurrency;
    protected MTax $mTax;
    protected MTermin $mTermin;
    protected MItemwarehouse $mItemwarehouse;

    protected bool $showImport  = false;
    protected bool $showRemark  = true;
    protected bool $showSummary = true;
    protected bool $showFreight = false;
    protected bool $showDpp     = true;
    protected bool $showBtndraft = true;

    public function __construct()
    {
        $this->model             = new MPurchaseorder();
        $this->mItemmaster       = new MItemmaster();
        $this->mCardmaster       = new MCardmaster();
        $this->mWarehousemaster  = new MWarehousemaster();
        $this->mCurrency         = new MCurrency();
        $this->mTax              = new MTax();
        $this->mTermin           = new MTermin();
        $this->mPurchaserequest  = new MPurchaserequest();
        $this->mItemwarehouse    = new MItemwarehouse();    
    }

    protected function getTitle(): string { return 'Purchase Order'; }
    protected function getBaseRoute(): string { return 'purchaseorder'; }
    protected function getTxnNumberField(): string { return 'docnum'; }
    protected function getDateField(): string { return 'docdate'; }
    
    protected function generateTxnNumber(): string 
    { 
        return method_exists($this->model, 'generateNumber') ? $this->model->generateNumber() : 'PO-' . date('ymdHis'); 
    }

    protected function getTableColumns(): array 
    { 
        return ['No', 'Nomor', 'Tanggal', 'Supplier / Vendor', 'Total', 'Diskon', 'Tax', 'Grand Total', 'Status', 'Aksi']; 
    }

    protected function getExtraColumns($item): array 
    { 
        $itemArray = is_object($item) ? (array)$item : $item;
        return [
            e($itemArray['cardname'] ?? '-'),
            number_format((float)($itemArray['subtotal'] ?? 0), 2, ',', '.'),
            number_format((float)($itemArray['discountamount'] ?? 0), 2, ',', '.'),
            number_format((float)($itemArray['taxamount'] ?? 0), 2, ',', '.'),
            number_format((float)($itemArray['grandtotal'] ?? 0), 2, ',', '.'),
        ]; 
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
                'name'  => 'whsname',
                'label' => 'Gudang'
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
            'title'                => $this->getTitle(),
            'base_route'           => $this->getBaseRoute(),
            'field_code'           => $this->getTxnNumberField(),
            'field_date'           => $this->getDateField(),
            'current_number'       => $this->generateTxnNumber(),
            'extra'                => $this->getExtraFields(),
            'detail_columns'       => $this->getDetailFields(),
            'action_url'           => $this->getBaseRoute() . '/saveadd',
            'show_remark'          => $this->showRemark,
            'show_summary'         => $this->showSummary,
            'show_dpp'             => $this->showDpp,
            'show_btndraft'        => $this->showBtndraft,
            'message'              => '',
            
            'copy_from_url'        => url('purchaseorder/getOpenList'),
            'copy_from_detail_url' => url('purchaseorder/getOpenDetail'),
            'copy_from_label'      => 'Copy From PR',
            'copy_from_columns'    => $this->getCopyFromColumns(),
        ];

        return view('basetransaction.add', $data);
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
                'name'    => 'cardcode',
                'label'   => 'Supplier / Vendor',
                'type'    => 'select',
                'value'   => $row['cardcode'] ?? '',
                'options' => $this->formatOptions($this->mCardmaster->filters([
                    'cardgroupcode' => 'VS',
                    'status'        => 1
                ]), 'cardcode', 'cardname'),
            ],
            [
                'name'     => 'whscode',
                'label'    => 'Gudang',
                'type'     => 'text',
                'value'    => $row['whscode'] ?? 'VPCH',
                'width'    => '10%',
                'readonly' => true,
            ],
            [
                'name'    => 'delivereddate',
                'label'   => 'Estimasi Pengiriman',
                'type'    => 'date',
                'value'   => $row['delivereddate'] ?? date('Y-m-d'),
            ],
            [
                'name'    => 'currcode',
                'label'   => 'Mata Uang',
                'type'    => 'select',
                'value'   => $row['currcode'] ?? 'IDR',
                'options' => $this->formatOptions($this->mCurrency->filters([
                    'status' => 1
                ]), 'currcode', 'currname'),
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
                'label'   => 'Kode Pajak',
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
            ];
        }

        return [
            [
                'name'          => 'itemcode',
                'label'         => 'Kode Produk',
                'type'          => 'datalist',
                'options'       => $itemOptions,
                'placeholder'   => 'Pilih Item',
                'width'         => 'auto',
                'required'      => true,
            ],
            [
                'name'          => 'uomcode',
                'label'         => 'Uom',
                'type'          => 'text',
                'width'         => '8%',
                'readonly'      => true,
            ],
            [
                'name'          => 'qty',
                'label'         => 'Jumlah',
                'type'          => 'number',
                'class'         => 'text-end input-qty',
                'width'         => '10%',
                'min'           => 0,
                'required'      => true,
            ],
            [
                'name'          => 'unitprice',
                'label'         => 'Harga',
                'type'          => 'number',
                'class'         => 'text-end input-price',
                'width'         => '12%',
                'min'           => 0,
                'required'      => true,
            ],
            [
                'name'          => 'discount',
                'label'         => 'Diskon',
                'type'          => 'number',
                'class'         => 'text-end input-discount',
                'width'         => '10%',
                'max'           => 100,
                'min'           => 0
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

        $validator = validator($request->all(), $this->getRules());
        if ($validator->fails()) {
            return $this->ajaxResponse('error', implode(', ', $validator->errors()->all()));
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

        $headerData  = $this->prepareHeaderOnCreate($postData, $status, $items, $request);
        $detailsData = $this->prepareDetailsOnCreate($items, $txnNumber);

        DB::beginTransaction();

        try {
            $saveResult = $this->model->saveadd($headerData, $detailsData, $txnNumberField);
            
            if (($saveResult['status'] ?? '') !== 'success') {
                throw new \Exception($saveResult['message'] ?? 'Gagal menyimpan transaksi PO.');
            }

            if ($status === 1) {
                $whsCode   = $headerData['whscode'] ?? '';
                $baseEntry = $headerData['baseentry'] ?? null;
                if (!empty($whsCode)) {
                    foreach ($detailsData as $row) {
                        $itemCode = $row['itemcode'] ?? '';
                        $qty      = (float)($row['qty'] ?? 0);
                        $baseLine = $row['baseline'] ?? null;

                        if (!empty($itemCode) && $qty > 0) {
                            $this->mItemwarehouse->addOrdered($itemCode, $whsCode, $qty);
                        }

                        if (!empty($baseEntry) && !empty($baseLine)) {
                            $this->updatePRBaseQty(DB::getFacadeRoot(), $itemCode, $baseEntry, $baseLine, $qty);
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
        $headerArray = is_object($header) ? (array)$header : $header;
        if (!$headerArray) {
            return $this->ajaxResponse('error', 'Data tidak ditemukan di database.');
        }

        $validator = validator($request->all(), $this->getRules());
        if ($validator->fails()) {
            return $this->ajaxResponse('error', implode(', ', $validator->errors()->all()));
        }

        $postData = $request->post();
        if ($valError = $this->validateTransaction($postData)) {
            return $this->ajaxResponse('error', $valError);
        }

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

        $oldWhsCode = $headerArray['whscode'] ?? '';
        $oldBaseEnt = $headerArray['baseentry'] ?? null;
        $oldDetails = DB::table('podetail')->where('docnum', $txnNumber)->get()->toArray();

        DB::beginTransaction();

        try {
            if ($oldStatus === 1) {
                foreach ($oldDetails as $oldRow) {
                    $oldRowArray = is_object($oldRow) ? (array)$oldRow : $oldRow;
                    $oldItemCode = $oldRowArray['itemcode'] ?? '';
                    $oldQty      = (float)($oldRowArray['qty'] ?? 0);
                    $oldBaseLine = $oldRowArray['baseline'] ?? null;

                    if (!empty($oldItemCode) && $oldQty > 0) {
                        $this->mItemwarehouse->reduceOrdered($oldItemCode, $oldWhsCode, $oldQty);
                    }
                    if (!empty($oldBaseEnt) && !empty($oldBaseLine)) {
                        $this->updatePRBaseQty(DB::getFacadeRoot(), $oldItemCode, $oldBaseEnt, $oldBaseLine, -$oldQty);
                    }
                }
            }

            $editResult = $this->model->saveedit($id, $headerData, $detailsData);
            if (($editResult['status'] ?? '') === 'error') {
                throw new \Exception($editResult['message'] ?? 'Gagal memperbarui data.');
            }

            if ($newStatus === 1) {
                $newWhsCode = $headerData['whscode'] ?? '';
                $newBaseEnt = $headerData['baseentry'] ?? null; 

                if (!empty($newWhsCode)) {
                    foreach ($detailsData as $newRow) {
                        $newItemCode = $newRow['itemcode'] ?? '';
                        $newQty      = (float)($newRow['qty'] ?? 0);
                        $newBaseLine = $newRow['baseline'] ?? null;

                        if (!empty($newItemCode) && $newQty > 0) {
                            $this->mItemwarehouse->addOrdered($newItemCode, $newWhsCode, $newQty);
                        }
                        if (!empty($newBaseEnt) && !empty($newBaseLine)) {
                            $this->updatePRBaseQty(DB::getFacadeRoot(), $newItemCode, $newBaseEnt, $newBaseLine, $newQty);
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
        $headerArray = is_object($header) ? (array)$header : $header;
        if (!$headerArray) {
            return $this->ajaxResponse('error', 'Data tidak ditemukan di database.');
        }

        $currentStatus = (int)($headerArray['status'] ?? 0);

        if ($currentStatus !== 9) {
            return $this->ajaxResponse('error', 'Hanya dokumen berstatus Draft yang dapat diubah menjadi Aktif.');
        }

        $txnNumber   = $headerArray[$this->getTxnNumberField()];
        $whsCode     = $headerArray['whscode'] ?? '';
        $baseEntry   = $headerArray['baseentry'] ?? null;
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
                    $itemCode  = $rowArray['itemcode'] ?? '';
                    $qty       = (float)($rowArray['qty'] ?? 0);
                    $baseLine  = $rowArray['baseline'] ?? null;

                    if (!empty($itemCode) && $qty > 0) {
                        $this->mItemwarehouse->addOrdered($itemCode, $whsCode, $qty);
                    }
                    if (!empty($baseEntry) && !empty($baseLine)) {
                        $this->updatePRBaseQty(DB::getFacadeRoot(), $itemCode, $baseEntry, $baseLine, $qty);
                    }
                }
            }

            DB::commit();

            return $this->ajaxResponse('success', 'Purchase Order berhasil diaktifkan.', [
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
        $headerArray = is_object($header) ? (array)$header : $header;
        if (!$headerArray) {
            return $this->ajaxResponse('error', 'Data tidak ditemukan di database.');
        }

        $currentStatus = (int)($headerArray['status'] ?? 0);

        if ($currentStatus === 0) {
            return $this->ajaxResponse('error', 'Dokumen sudah dalam status Cancel.');
        }

        $txnNumber   = $headerArray[$this->getTxnNumberField()];
        $whsCode     = $headerArray['whscode'] ?? '';
        $baseEntry   = $headerArray['baseentry'] ?? null;
        $detailsData = $this->model->getWithDetails($txnNumber)['details'] ?? [];

        DB::beginTransaction();

        try {
            $updateStatus = $this->model->update($id, [
                'status'      => 0,
                'updateddate' => date('Y-m-d H:i:s'),
                'updatedby'   => (int) (Session::get('usercode') ?? 1),
            ]);

            if (!$updateStatus) {
                throw new \Exception('Gagal membatalkan dokumen.');
            }

            if ($currentStatus === 1 && !empty($whsCode)) {
                foreach ($detailsData as $row) {
                    $rowArray = is_object($row) ? (array)$row : $row;
                    $itemCode  = $rowArray['itemcode'] ?? '';
                    $qty       = (float)($rowArray['qty'] ?? 0);
                    $baseLine  = $rowArray['baseline'] ?? null;

                    if (!empty($itemCode) && $qty > 0) {
                        $this->mItemwarehouse->reduceOrdered($itemCode, $whsCode, $qty);
                    }
                    if (!empty($baseEntry) && !empty($baseLine)) {
                        $this->updatePRBaseQty(DB::getFacadeRoot(), $itemCode, $baseEntry, $baseLine, -$qty);
                    }
                }
            }

            DB::commit();

            return $this->ajaxResponse('success', 'Purchase Order berhasil dibatalkan.', [
                'redirect' => url($this->getBaseRoute())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'DB Error: ' . $e->getMessage());
        }
    }

    protected function prepareHeaderOnCreate(array $postData, int $status, array $items = [], Request $request = null): array
    {
        $header = parent::prepareHeaderOnCreate($postData, $status, $request ?? request());
        $computed = $this->calculateHeaderTotals(array_merge($postData, ['items' => $items]));

        $data = array_merge($header, [
            'docdate'        => !empty($header['docdate']) ? $header['docdate'] : date('Y-m-d H:i:s'),
            'baseentry'      => !empty($postData['baseentry']) ? $postData['baseentry'] : null,
            'cardcode'       => $postData['cardcode'] ?? '',
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

    protected function prepareDetailsOnCreate(array $items, string $txnNumber): array
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
                'qtyreceived'   => 0,
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
        $baseEntry = $postData['baseentry'] ?? null;

        if (empty($postData['cardcode']) && empty($baseEntry)) {
            return 'Supplier / Vendor wajib dipilih.';
        }
        if (empty($postData['whscode'])) {
            return 'Gudang tujuan wajib dipilih.';
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

    private function updatePRBaseQty($db, string $itemCode, ?string $baseEntry, ?int $baseLine, float $qtyChange)
    {
        if (empty($baseEntry) || empty($baseLine) || $qtyChange == 0) {
            return;
        }

        $db->table('prdetail')
           ->where('docnum', $baseEntry)
           ->where('linenum', $baseLine)
           ->update(['baseqty' => DB::raw('baseqty + ' . $qtyChange)]);

        $details = $db->table('prdetail')
                      ->where('docnum', $baseEntry)
                      ->get()
                      ->getResultArray();

        $isAllClosed = true;
        foreach ($details as $det) {
            $detArray = is_object($det) ? (array)$det : $det;
            $totalQty  = (float)($detArray['qty'] ?? 0);
            $totalBase = (float)($detArray['baseqty'] ?? 0);
            if ($totalBase < $totalQty) {
                $isAllClosed = false;
                break;
            }
        }

        $userCode = (int) (Session::get('usercode') ?? 1);
        if ($isAllClosed && !empty($details)) {
            $db->table('prheader')
               ->where('docnum', $baseEntry)
               ->update([
                   'status' => 2,
                   'closeddate' => date('Y-m-d H:i:s'),
                   'closedby' => $userCode
               ]);
        } else {
            $db->table('prheader')
               ->where('docnum', $baseEntry)
               ->where('status', 2)
               ->update([
                   'status' => 1,
                   'closeddate' => null,
                   'closedby' => null
               ]);
        }
    }

    public function getOpenList(Request $request): JsonResponse
    {
        if (!$request->ajax()) {
            return response()->json([], 404);
        }

        $listData = $this->mPurchaserequest->getOpen();

        $html = view('basetransaction.popcopyfromrows', [
            'listData'          => $listData,
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

        $prData = $this->mPurchaserequest->getWithDetails($docnum);
        $prHeader = $prData['header'] ?? null;
        $prHeaderArray = is_object($prHeader) ? (array)$prHeader : $prHeader;

        if (!$prData || ($prHeaderArray && isset($prHeaderArray['status']) && (int)$prHeaderArray['status'] !== 1)) {
            return response()->json([
                'status'  => false, 
                'message' => 'Data Purchase Request tidak ditemukan atau status sudah tidak aktif.'
            ]);
        }

        $header = $prHeaderArray;
        $header['baseentry'] = $header['docnum'] ?? $docnum;

        unset(
            $header['docnum'], 
            $header['docdate'], 
            $header['status'], 
            $header['createddate'], 
            $header['createdby'], 
            $header['updateddate'], 
            $header['updatedby']
        );

        if (!empty($header['requireddate'])) {
            $header['delivereddate'] = date('Y-m-d', strtotime($header['requireddate']));
            unset($header['requireddate']);
        }

        foreach ($header as $key => $val) {
            $header[$key] = $val;
            if ($val && (strpos($key, 'date') !== false) && strtotime($val)) {
                $header[$key] = date('Y-m-d', strtotime($val));
            }
        }

        $details = [];
        foreach ($prData['details'] as $row) {
            $rowArray = is_object($row) ? (array)$row : $row;
            $qty     = (float)($rowArray['qty'] ?? 0);
            $baseqty = (float)($rowArray['baseqty'] ?? 0);
            $openQty = $qty - $baseqty;
            if ($openQty > 0) {
                $rowArray['qty']      = $openQty;
                $rowArray['baseline'] = $rowArray['linenum'] ?? 1;
                $details[]       = $rowArray;
            }
        }

        return response()->json([
            'status'  => true,
            'header'  => $header,
            'details' => $details
        ]);
    }
}
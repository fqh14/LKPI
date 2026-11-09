<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Models\MDeliveryorder;
use App\Models\MSalesorder;
use App\Models\MCardmaster;
use App\Models\MWarehousemaster;
use App\Models\MSelltype;
use App\Models\MCurrency;
use App\Models\MTax;
use App\Models\MTermin;
use App\Models\MItemgroup;
use App\Models\MItemmaster;
use App\Models\MAccount;
use App\Models\MItemwarehouse;
use App\Models\MItembatch;
use App\Models\MStockledger;
use App\Models\MJournal;

class Deliveryorder extends BaseTransactionController
{
    protected $model;
    protected MSalesorder $mSalesorder;
    protected MCardmaster $mCardmaster;
    protected MWarehousemaster $mWarehousemaster;
    protected MSelltype $mSelltype;
    protected MCurrency $mCurrency;
    protected MTax $mTax;
    protected MTermin $mTermin;
    protected MItemgroup $mItemgroup;
    protected MItemmaster $mItemmaster;
    protected MAccount $mAccount;
    protected MItemwarehouse $mItemwarehouse;
    protected MItembatch $mItembatch;
    protected MStockledger $mStockledger;
    protected MJournal $mJournal;
    
    protected bool $showImport  = false;
    protected bool $showRemark  = true;
    protected bool $showSummary = true;
    protected bool $showFreight = false;
    protected bool $showDpp     = true;
    protected bool $showBtndraft = true;

    public function __construct()
    {
        $this->model            = new MDeliveryorder();
        $this->mSalesorder      = new MSalesorder();
        $this->mCardmaster      = new MCardmaster();
        $this->mWarehousemaster = new MWarehousemaster();
        $this->mSelltype        = new MSelltype();
        $this->mCurrency        = new MCurrency();
        $this->mTax             = new MTax();
        $this->mTermin          = new MTermin();
        $this->mItemgroup       = new MItemgroup();
        $this->mItemmaster      = new MItemmaster();
        $this->mAccount         = new MAccount();
        $this->mItemwarehouse   = new MItemwarehouse();
        $this->mItembatch       = new MItembatch();
        $this->mStockledger     = new MStockledger();
        $this->mJournal         = new MJournal();
    }

    protected function getTitle(): string { return 'Delivery Order'; }
    protected function getBaseRoute(): string { return 'deliveryorder'; }
    protected function getTxnNumberField(): string { return 'docnum'; }
    protected function getDateField(): string { return 'docdate'; }
    
    protected function generateTxnNumber(): string 
    { 
        return method_exists($this->model, 'generateNumber') ? $this->model->generateNumber() : 'DO-' . date('ymdHis'); 
    }

    protected function getTableColumns(): array 
    { 
        return ['No', 'Nomor', 'Tanggal', 'Nomor SO', 'Customer', 'Total', 'Diskon', 'Tax', 'Grand Total', 'Status', 'Aksi'];
    }

    protected function getExtraColumns($item): array 
    { 
        $itemArray = (array)$item;
        return [
            e($itemArray['baseentry'] ?? '-'),
            e($itemArray['cardname'] ?? '-'),
            number_format((float)($itemArray['subtotal'] ?? 0), 2, ',', '.'),
            number_format((float)($itemArray['discountamount'] ?? 0), 2, ',', '.'),
            number_format((float)($itemArray['taxamount'] ?? 0), 2, ',', '.'),
            number_format((float)($itemArray['grandtotal'] ?? 0), 2, ',', '.'),
        ]; 
    }

    public function add()
    {
        if ($guard = $this->guardModuleAccess('isadd')) return $guard;
        if ($guard = $this->guardAuthenticatedView()) return $guard;

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
            
            'copy_from_url'        => url('deliveryorder/getOpenList'),
            'copy_from_detail_url' => url('deliveryorder/getOpenDetail'),
            'copy_from_label'      => 'Copy From SO',
            'copy_from_columns'    => $this->getCopyFromColumns(),

            'batch_allocation'     => true,
        ];

        return view('basetransaction.add', $data);
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
                'label' => 'Tipe'
            ],
            [
                'name'  => 'cardname',
                'label' => 'Customer'
            ],
            [
                'name'   => 'grandtotal',
                'label'  => 'Total',
                'class'  => 'text-end',
                'format' => 'number' 
            ],
            [
                'name'  => 'remark',
                'label' => 'Catatan'
            ]
        ];
    }

    protected function getExtraFields($row = null): array
    {
        return [
            [
                'name'          => 'baseentry',
                'label'         => 'No. SO Referensi',
                'type'          => 'text',
                'value'         => $row['baseentry'] ?? '',
                'readonly'      => true,
                'disable'       => true
            ],
            [
                'name'          => 'whscode',
                'label'         => 'Gudang',
                'type'          => 'select',
                'options'       => $this->formatOptions($this->mWarehousemaster->filters([
                    'status'    => 1
                ]), 'whscode', 'whsname'),
                'value'         => $row['whscode'] ?? '',
                'width'         => '10%',
                'readonly'      => true
            ],
            [
                'name'          => 'cardcode',
                'label'         => 'Customer',
                'type'          => 'select',
                'value'         => $row['cardcode'] ?? '',
                'options'       => $this->formatOptions($this->mCardmaster->filters([
                    'cardgroupcode' => 'VC',
                    'status'        => 1
                ]), 'cardcode', 'cardname'),
            ],
            [
                'name'          => 'sellcode',
                'label'         => 'Tipe Penjualan',
                'type'          => 'select',
                'options'       => $this->formatOptions($this->mSelltype->filters([
                    'status'    => 1
                ]), 'sellcode', 'sellname'),
                'required'      => true,
                'readonly'      => true
            ],
            [
                'name'          => 'currcode',
                'label'         => 'Mata Uang',
                'type'          => 'select',
                'value'         => $row['currcode'] ?? 'IDR',
                'options'       => $this->formatOptions($this->mCurrency->filters([
                    'status'    => 1
                ]), 'currcode', 'currname'),
                'required'      => true,
                'readonly'      => true
            ],
            [
                'name'          => 'taxclude',
                'label'         => 'Tipe Pajak',
                'type'          => 'select',
                'value'         => $row['taxclude'] ?? 0,
                'options'       => [0 => 'Exclude', 1 => 'Include'],
                'required'      => true,
                'readonly'      => true
            ],
            [
                'name'          => 'taxcode',
                'label'         => 'Kode Pajak Global',
                'type'          => 'select',
                'value'         => $row['taxcode'] ?? '',
                'options'       => $this->formatOptions($this->mTax->filters([
                    'status'    => 1
                ]), 'taxcode', 'taxname'),
                'placeholder'   => 'Pilih Pajak',
                'required'      => true,
                'readonly'      => true
            ],
            [
                'name'          => 'termcode',
                'label'         => 'Syarat Pembayaran',
                'type'          => 'select',
                'value'         => $row['termcode'] ?? '',
                'options'       => $this->formatOptions($this->mTermin->filters([
                    'status'    => 1
                ]), 'termcode', 'termname'),
                'required'      => true,
                'readonly'      => true
            ],
        ];
    }

    protected function getDetailFields(): array
    {
        $items = $this->mItemmaster->filters(['status' => 1]);
        $itemOptions = [];
        foreach ($items as $item) {
            $itemArray = (array)$item;
            $itemOptions[$itemArray['itemcode']] = [
                'label' => $itemArray['itemname'],
                'uom'   => $itemArray['uomcode'] ?? $itemArray['uom'] ?? '',
                'price' => $itemArray['price'] ?? $itemArray['unitprice'] ?? 0
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
                'name'     => 'uomcode',
                'label'    => 'Uom',
                'type'     => 'text',
                'width'    => '7%',
                'readonly' => true,
            ],
            [
                'name'     => 'qty',
                'label'    => 'Jumlah',
                'type'     => 'number',
                'class'    => 'text-end input-qty',
                'required' => true,
                'min'      => 0
            ],
            [
                'name'        => 'batchnum',
                'label'       => 'Batch Alokasi',
                'type'        => 'batch_with_btn', 
                'class'       => 'bg-light text-primary font-weight-bold', 
                'placeholder' => 'Hasil Alokasi',
                'width'       => '20%',
                'readonly'    => true,
                'required'    => true,
            ],
            [
                'name'     => 'expdate',
                'label'    => 'Kadaluarsa',
                'type'     => 'date',
                'width'    => '10%',
                'readonly' => true,
            ],
            [
                'name'     => 'unitprice',
                'label'    => 'Harga',
                'type'     => 'number',
                'class'    => 'text-end input-price',
                'required' => true,
                'min'      => 0
            ],
            [
                'name'  => 'discount',
                'label' => 'Diskon',
                'type'  => 'number',
                'class' => 'text-end input-discount',
                'max'   => 100,
                'min'   => 0
            ],
            [
                'name'     => 'subtotal',
                'label'    => 'Sub Total',
                'type'     => 'number',
                'class'    => 'text-end input-subtotal',
                'width'    => 'auto',
                'readonly' => true
            ],
        ];
    }

    private function checkAndUpdateSoStatus($db, $baseEntry)
    {
        if (empty($baseEntry)) return;

        $soDetails = DB::table('sodetail')->where('docnum', $baseEntry)->get();
        
        $isFullyDelivered = true;
        foreach ($soDetails as $detail) {
            $detailArray = (array)$detail;
            $qtyOrder     = (float)($detailArray['qty'] ?? 0);
            $qtyDelivered = (float)($detailArray['qtydelivered'] ?? 0);
            
            if ($qtyDelivered < $qtyOrder) {
                $isFullyDelivered = false;
                break;
            }
        }

        $newSoStatus = $isFullyDelivered ? 2 : 1;

        DB::table('soheader')
           ->where('docnum', $baseEntry)
           ->update(['status' => $newSoStatus]);
    }

    private function processSoFulfillment($db, $itemCode, $whsCode, $qty, $baseEntry, $baseLine)
    {
        if (empty($itemCode) || $qty <= 0) return;

        if (!empty($baseEntry)) {
            $builder = DB::table('sodetail')->where('docnum', $baseEntry);
            if (!empty($baseLine)) {
                $builder->where('linenum', $baseLine);
            } else {
                $builder->where('itemcode', $itemCode);
            }
            $builder->increment('qtydelivered', $qty);
        }
    }

    public function saveadd(Request $request): JsonResponse
    {
        if (!$request->ajax() || !$request->isMethod('post')) {
            return response()->json([], 405);
        }

        $postData = $request->post();
        
        $validationError = $this->validateTransaction($postData);
        if ($validationError) {
            return $this->ajaxResponse('error', $validationError);
        }

        $txnNumberField = $this->getTxnNumberField();
        $txnNumber      = $postData[$txnNumberField] ?? $this->generateTxnNumber();
        $status = ($postData['btn_submit'] ?? 'save') === 'draft' ? 9 : 1;
        
        $items  = $postData['items'] ?? $postData['details'] ?? [];
        if (is_string($items)) $items = json_decode($items, true) ?? [];
        unset($postData['items'], $postData['details'], $postData['btn_submit']);

        $headerData = $this->prepareHeaderOnCreate($postData, $status, $items, $request);
        $headerData[$txnNumberField] = $txnNumber;
        $detailData = $this->prepareDetailsOnCreate($items, $txnNumber, $request);

        DB::beginTransaction();

        try {
            if (method_exists($this->model, 'saveadd')) {
                $saveResult = $this->model->saveadd($headerData, $detailData, $txnNumberField);
                if (is_array($saveResult) && ($saveResult['status'] ?? '') === 'error') {
                    throw new \Exception($saveResult['message'] ?? 'Gagal menyimpan transaksi Delivery Order.');
                }
            } else {
                $this->model->insert($headerData);
                foreach ($detailData as $detail) {
                    DB::table('dodetail')->insert($detail);
                }
            }

            if ($status === 1) {
                $defaultWhs = $headerData['whscode'] ?? '';
                $baseEntry  = $headerData['baseentry'] ?? null;

                foreach ($detailData as $item) {
                    $itemCode = $item['itemcode'] ?? '';
                    $qty      = (float)($item['qty'] ?? 0);
                    $whsCode  = $item['whscode'] ?? $defaultWhs;
                    $baseLine = $item['baseline'] ?? null;

                    $this->processSoFulfillment(DB::getFacadeRoot(), $itemCode, $whsCode, $qty, $baseEntry, $baseLine);
                }

                if (!empty($baseEntry)) {
                    $this->checkAndUpdateSoStatus(DB::getFacadeRoot(), $baseEntry);
                }
            }

            DB::commit();

            $this->flashSession(['status' => ($status === 9 ? 'draft' : 'add'), 'fa_id' => $txnNumber, 'fa_name' => $this->getTitle()]);
            $message = $status === 9 ? 'Draft Delivery Order berhasil disimpan.' : 'Delivery Order berhasil disimpan (Ready for Packing).';

            return $this->ajaxResponse('success', $message, ['redirect' => url($this->getBaseRoute())]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    public function edit(Request $request, $id)
    {
        if ($guard = $this->guardModuleAccess('isedit')) return $guard;
        if ($guard = $this->guardAuthenticatedView()) return $guard;

        $header = $this->model->find($id);
        if (!$header) {
            return redirect()->to(url($this->getBaseRoute()))->with('error_msg', 'Data transaksi tidak ditemukan.');
        }

        $headerArray = is_object($header) && method_exists($header, 'toArray') 
            ? $header->toArray() 
            : (array)$header;

        $fieldCode = $this->getTxnNumberField();
        $fieldDate = $this->getDateField();
        $docNumber = $headerArray[$fieldCode] ?? $id;
        $docRemark = $headerArray['remark'] ?? $headerArray['catatan'] ?? '';

        $status = (string)($headerArray['status'] ?? '');
        if (in_array($status, ['0', '2'], true)) { 
            return redirect()->to(url($this->getBaseRoute()))->with('error_msg', 'Transaksi yang sudah selesai/batal tidak dapat diubah.');
        }

        $details = $this->model->get_details($docNumber) ?? [];

        return view('basetransaction.edit', [
            'title'          => 'Edit ' . $this->getTitle(),
            'base_route'     => $this->getBaseRoute(),
            'action_url'     => $this->getBaseRoute() . '/saveedit/' . $id,
            'field_code'     => $fieldCode,
            'field_date'     => $fieldDate,
            'docNumber'      => $docNumber,
            'docRemark'      => $docRemark,
            'header'         => $headerArray,
            'details'        => $details,
            'show_remark'    => $this->showRemark,
            'show_summary'   => $this->showSummary,
            'show_dpp'       => $this->showDpp,
            'show_btndraft'  => $this->showBtndraft,
            'extra'          => $this->getExtraFields($headerArray),
            'detail_columns' => $this->getDetailFields() ?? [],
            'message'        => Session::get('message') ?? Session::get('error_msg') ?? '',
            
            'batch_allocation' => true 
        ]);
    }

    public function saveedit(Request $request, $id = null): JsonResponse
    {
        if (!$request->ajax() || !$request->isMethod('post')) return response()->json([], 405);

        $postData  = $request->post();
        $txnNumber = $id ?? $postData['docnum'] ?? null;

        if (empty($txnNumber)) return $this->ajaxResponse('error', 'Nomor transaksi tidak valid.');

        $validationError = $this->validateTransaction($postData, $txnNumber);
        if ($validationError) return $this->ajaxResponse('error', $validationError);

        $status = ($postData['btn_submit'] ?? 'save') === 'draft' ? 9 : 1;
        $items  = $postData['items'] ?? $postData['details'] ?? [];
        if (is_string($items)) $items = json_decode($items, true) ?? [];
        unset($postData['items'], $postData['details'], $postData['btn_submit']);

        DB::beginTransaction();

        try {
            $oldHeader = $this->model->find($txnNumber) ?? $this->model->where('docnum', $txnNumber)->first();
            $oldHeaderArray = is_object($oldHeader) ? (array)$oldHeader : $oldHeader;
            if (!$oldHeaderArray) return $this->ajaxResponse('error', 'Data transaksi tidak ditemukan.');

            $realId       = $oldHeaderArray['id'] ?? $id ?? $txnNumber;
            $oldDetails   = $this->model->get_details($txnNumber);
            $oldWhs       = $oldHeaderArray['whscode'] ?? '';
            $oldStatus    = (int)($oldHeaderArray['status'] ?? 9);
            $oldBaseEntry = $oldHeaderArray['baseentry'] ?? null;

            if ($oldStatus === 1 && !empty($oldDetails)) {
                foreach ($oldDetails as $oldItem) {
                    $oldItemArray = (array)$oldItem;
                    $oldItemCode = $oldItemArray['itemcode'] ?? '';
                    $oldQty      = (float)($oldItemArray['qty'] ?? 0);
                    $oldWhsCode  = $oldItemArray['whscode'] ?? $oldWhs;
                    $oldBaseLine = $oldItemArray['baseline'] ?? null;

                    if (!empty($oldItemCode) && $oldQty > 0) {
                        if (!empty($oldBaseEntry)) {
                            $builder = DB::table('sodetail')->where('docnum', $oldBaseEntry);
                            if (!empty($oldBaseLine)) $builder->where('linenum', $oldBaseLine);
                            else $builder->where('itemcode', $oldItemCode);
                            
                            $builder->decrement('qtydelivered', $oldQty);
                        }
                    }
                }
                if (!empty($oldBaseEntry)) $this->checkAndUpdateSoStatus(DB::getFacadeRoot(), $oldBaseEntry);
            }

            $headerData = $this->prepareHeaderData($txnNumber, $postData, $status, $items);
            $detailData = $this->prepareDetailsOnCreate($items, $txnNumber, $request);
            
            $saveResult = $this->model->saveedit($realId, $headerData, $detailData);
            if (is_array($saveResult) && isset($saveResult['status']) && $saveResult['status'] === 'error') {
                throw new \Exception($saveResult['message']);
            }

            if ($status === 1) {
                $defaultWhs = $headerData['whscode'] ?? '';
                $newBaseEntry  = $headerData['baseentry'] ?? null;

                foreach ($detailData as $item) {
                    $itemCode = $item['itemcode'] ?? '';
                    $qty      = (float)($item['qty'] ?? 0);
                    $whsCode  = $item['whscode'] ?? $defaultWhs;
                    $baseLine = $item['baseline'] ?? null;

                    $this->processSoFulfillment(DB::getFacadeRoot(), $itemCode, $whsCode, $qty, $newBaseEntry, $baseLine);
                }
                if (!empty($newBaseEntry)) $this->checkAndUpdateSoStatus(DB::getFacadeRoot(), $newBaseEntry);
            }

            DB::commit();

            $this->flashSession(['status' => ($status === 9 ? 'draft' : 'edit'), 'fa_id' => $txnNumber, 'fa_name' => $this->getTitle()]);
            return $this->ajaxResponse('success', 'Delivery Order berhasil diperbarui.', ['redirect' => url($this->getBaseRoute())]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function saveactive(Request $request, $id = null): JsonResponse
    {
        if (!$request->ajax() || !$request->isMethod('post')) return response()->json([], 405);

        $txnNumber = $id ?? $request->post('docnum') ?? $request->post('id') ?? null;
        if (empty($txnNumber)) return $this->ajaxResponse('error', 'Nomor transaksi tidak valid.');

        DB::beginTransaction();

        try {
            $header = $this->model->find($txnNumber) ?? $this->model->where('docnum', $txnNumber)->first();
            $headerArray = is_object($header) ? (array)$header : $header;
            if (!$headerArray) return $this->ajaxResponse('error', 'Data tidak ditemukan.');
            if ((int)($headerArray['status'] ?? 0) === 1) return $this->ajaxResponse('error', 'Sudah Active.');

            $realId     = $headerArray['id'] ?? $txnNumber;
            $docNum     = $headerArray['docnum'] ?? $txnNumber;
            $defaultWhs = $headerArray['whscode'] ?? '';
            $baseEntry  = $headerArray['baseentry'] ?? null;

            $details = $this->model->get_details($docNum);
            if (empty($details)) return $this->ajaxResponse('error', 'Detail kosong.');

            $updateData = ['status' => 1, 'approveddate' => date('Y-m-d H:i:s'), 'approvedby' => Session::get('usercode') ?? 1];

            if (method_exists($this->model, 'saveactive')) $this->model->saveactive($realId, $updateData);
            else $this->model->update($realId, $updateData);

            foreach ($details as $item) {
                $itemArray = (array)$item;
                $itemCode = $itemArray['itemcode'] ?? '';
                $qty      = (float)($itemArray['qty'] ?? 0);
                $whsCode  = $itemArray['whscode'] ?? $defaultWhs;
                $baseLine = $itemArray['baseline'] ?? null;

                $this->processSoFulfillment(DB::getFacadeRoot(), $itemCode, $whsCode, $qty, $baseEntry, $baseLine);
            }

            if (!empty($baseEntry)) $this->checkAndUpdateSoStatus(DB::getFacadeRoot(), $baseEntry);

            DB::commit();

            $this->flashSession(['status' => 'active', 'fa_id' => $docNum, 'fa_name' => $this->getTitle()]);
            return $this->ajaxResponse('success', 'Delivery Order berhasil diaktifkan.', ['redirect' => url($this->getBaseRoute())]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'Sistem Error: ' . $e->getMessage());
        }
    }

    public function savecancel(Request $request, $id = null): JsonResponse
    {
        if (!$request->ajax() || !$request->isMethod('post')) return response()->json([], 405);

        $txnNumber = $id ?? $request->post('docnum') ?? null;
        if (empty($txnNumber)) return $this->ajaxResponse('error', 'Nomor transaksi tidak valid.');

        DB::beginTransaction();

        try {
            $oldHeader = $this->model->find($txnNumber) ?? $this->model->where('docnum', $txnNumber)->first();
            $oldHeaderArray = is_object($oldHeader) ? (array)$oldHeader : $oldHeader;
            if (!$oldHeaderArray) return $this->ajaxResponse('error', 'Data tidak ditemukan.');
            
            $oldStatus = (int)($oldHeaderArray['status'] ?? 0);
            if ($oldStatus === 0) return $this->ajaxResponse('error', 'Sudah dibatalkan.');

            $oldDetails   = $this->model->get_details($txnNumber);
            $oldWhs       = $oldHeaderArray['whscode'] ?? '';
            $oldBaseEntry = $oldHeaderArray['baseentry'] ?? null;

            if ($oldStatus === 1 && !empty($oldDetails)) {
                foreach ($oldDetails as $oldItem) {
                    $oldItemArray = (array)$oldItem;
                    $oldItemCode = $oldItemArray['itemcode'] ?? '';
                    $oldQty      = (float)($oldItemArray['qty'] ?? 0);
                    $oldWhsCode  = $oldItemArray['whscode'] ?? $oldWhs;
                    $oldBaseLine = $oldItemArray['baseline'] ?? null;

                    if (!empty($oldItemCode) && $oldQty > 0) {
                        if (!empty($oldBaseEntry)) {
                            $builder = DB::table('sodetail')->where('docnum', $oldBaseEntry);
                            if (!empty($oldBaseLine)) $builder->where('linenum', $oldBaseLine);
                            else $builder->where('itemcode', $oldItemCode);
                            
                            $builder->decrement('qtydelivered', $oldQty);
                        }
                    }
                }
                if (!empty($oldBaseEntry)) $this->checkAndUpdateSoStatus(DB::getFacadeRoot(), $oldBaseEntry);
            }

            $userCode = (int) (Session::get('usercode') ?? 1);
            if (method_exists($this->model, 'cancelDocument')) {
                $this->model->cancelDocument($txnNumber, $userCode); 
            } else {
                $this->model->update($oldHeaderArray['id'] ?? $id, [
                    'status'       => 0,
                    'canceleddate' => date('Y-m-d H:i:s'),
                    'canceledby'   => $userCode
                ]);
            }

            DB::commit();

            $this->flashSession(['status' => 'savecancel', 'fa_id' => $txnNumber, 'fa_name' => $this->getTitle()]);
            $alertMessage = '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                          . 'Nomor transaksi <strong>' . e($txnNumber) . '</strong> berhasil dibatalkan (Cancel).'
                          . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'
                          . '</div>';

            return $this->ajaxResponse('success', $alertMessage, ['redirect' => url($this->getBaseRoute())]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'Error: ' . $e->getMessage());
        }
    }

    protected function prepareHeaderOnCreate(array $postData, int $status, array $items = [], Request $request = null): array
    {
        $header   = parent::prepareHeaderOnCreate($postData, $status, $request ?? request());
        $computed = $this->calculateHeaderTotals(array_merge($postData, ['items' => $items]));

        $data = array_merge($header, [
            'docdate'        => !empty($header['docdate']) ? $header['docdate'] : date('Y-m-d H:i:s'),
            'baseentry'      => !empty($postData['baseentry']) ? $postData['baseentry'] : null,
            'cardcode'       => $postData['cardcode'] ?? '',
            'whscode'        => $postData['whscode'] ?? '',
            'currcode'       => $postData['currcode'] ?? 'IDR',
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
            'cardcode'       => $postData['cardcode'] ?? '',
            'whscode'        => $postData['whscode'] ?? '',
            'currcode'       => $postData['currcode'] ?? 'IDR',
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
        $lineNumCount = 1; 

        foreach ($items as $index => $item) {
            $itemCode  = $item['itemcode'] ?? '';
            $uomCode   = $item['uomcode'] ?? '';
            $unitprice = $this->parseNumber($item['unitprice'] ?? 0);
            $discount  = $this->parseNumber($item['discount'] ?? 0);
            $baseLine  = $item['baseline'] ?? $item['linenum'] ?? $lineNumCount;

            $batchAllocJson = $item['batch_allocations'] ?? null;
            $allocs = [];
            if (!empty($batchAllocJson) && $batchAllocJson !== 'null') {
                $allocs = json_decode($batchAllocJson, true);
            }

            if (is_array($allocs) && !empty($allocs)) {
                foreach ($allocs as $batchNum => $bQty) {
                    $qty = (float)$bQty;
                    if ($qty <= 0) continue;

                    $itemGross    = $qty * $unitprice;
                    $discNominal  = $itemGross * ($discount / 100);
                    $itemSubtotal = $itemGross - $discNominal;

                    $detailsData[] = [
                        'docnum'        => $txnNumber,
                        'linenum'       => $lineNumCount, 
                        'baseline'      => $baseLine,
                        'itemcode'      => $itemCode,
                        'uomcode'       => $uomCode,
                        'qty'           => $qty,
                        'unitprice'     => $unitprice,
                        'discount'      => $discount,
                        'subtotal'      => $itemSubtotal,
                        'batchnum'      => $batchNum,
                        'expdate'       => $item['expdate'] ?? null,
                    ];
                    
                    $lineNumCount++;
                }
            } else {
                $qty = $this->parseNumber($item['qty'] ?? 0);
                if ($qty <= 0) continue;

                $itemGross    = $qty * $unitprice;
                $discNominal  = $itemGross * ($discount / 100);
                $itemSubtotal = $itemGross - $discNominal;

                $detailsData[] = [
                    'docnum'        => $txnNumber,
                    'linenum'       => $lineNumCount, 
                    'baseline'      => $baseLine,
                    'itemcode'      => $itemCode,
                    'uomcode'       => $uomCode,
                    'qty'           => $qty,
                    'unitprice'     => $unitprice,
                    'discount'      => $discount,
                    'subtotal'      => $itemSubtotal,
                    'batchnum'      => $item['batchnum'] ?? '',
                    'expdate'       => !empty($item['expdate']) ? $item['expdate'] : null,
                ];
                
                $lineNumCount++; 
            }
        }

        return $detailsData;
    }

    protected function prepareDetailsData(string $txnNumber, array $postData): array
    {
        $items = $postData['items'] ?? $postData['details'] ?? [];
        if (is_string($items)) {
            $items = json_decode($items, true) ?? [];
        }
        return $this->prepareDetailsOnCreate($items, $txnNumber, request());
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

    public function getBatchAllocation(Request $request): JsonResponse
    {
        if (!$request->ajax()) {
            return response()->json([], 404);
        }

        $itemcode = $request->post('itemcode');
        $whscode  = $request->post('whscode');
        $reqQty   = (float)$request->post('qty');

        if (empty($itemcode) || empty($whscode)) {
            return response()->json(['status' => false, 'message' => 'Item atau Gudang belum dipilih.']);
        }

        $batches = $this->mItembatch->getStockByFEFO($itemcode, $whscode);

        return response()->json([
            'status' => true,
            'data'   => $batches,
            'reqQty' => $reqQty
        ]);
    }

    protected function validateTransaction(array $postData, ?string $currentDocNum = null): ?string
    {
        if (empty($postData['cardcode'])) {
            return 'Customer wajib dipilih.';
        }
        $whsCode = $postData['whscode'] ?? '';
        if (empty($whsCode)) {
            return 'Gudang tujuan wajib dipilih.';
        }

        $items = $postData['items'] ?? $postData['details'] ?? [];
        if (is_string($items)) {
            $items = json_decode($items, true) ?? [];
        }
        if (empty($items)) {
            return 'Detail barang yang dipesan tidak boleh kosong!';
        }

        $baseEntry = $postData['baseentry'] ?? null;

        $oldDetailsMap = [];
        if (!empty($currentDocNum)) {
            $oldDetails = $this->model->get_details($currentDocNum);
            if (!empty($oldDetails)) {
                foreach ($oldDetails as $old) {
                    $oldArray = (object)$old;
                    $key = $oldArray->itemcode . '_' . ($oldArray->batchnum ?? '');
                    $oldDetailsMap[$key] = (float)($oldArray->qty ?? 0);
                }
            }
        }

        foreach ($items as $index => $item) {
            $rowNum   = $index + 1;
            $itemCode = $item['itemcode'] ?? '';
            $qtyInput = $this->parseNumber($item['qty'] ?? 0);
            $batchNum = $item['batchnum'] ?? '';
            $baseLine = $item['baseline'] ?? ($index + 1);
            
            if (empty($itemCode)) {
                return "Barang pada baris ke-{$rowNum} belum dipilih.";
            }
            if ($qtyInput <= 0) {
                return "Jumlah (Qty) pada baris ke-{$rowNum} harus lebih dari 0.";
            }

            $batchAllocJson = $item['batch_allocations'] ?? null;
            $allocs = [];
            if (!empty($batchAllocJson) && $batchAllocJson !== 'null') {
                $allocs = json_decode($batchAllocJson, true);
            }

            if (is_array($allocs) && !empty($allocs)) {
                $sumAlloc = 0;
                foreach ($allocs as $bNum => $aQty) {
                    $sumAlloc += (float)$aQty;
                }
                if (abs($sumAlloc - $qtyInput) > 0.001) {
                    return "Total alokasi batch (" . $sumAlloc . ") tidak sesuai dengan Qty DO (" . $qtyInput . ") pada baris ke-{$rowNum}. Pastikan Qty DO disesuaikan dengan nilai total batch.";
                }
            } else {
                if (empty($batchNum)) {
                    return "Baris ke-{$rowNum}: Nomor Batch (Batch Number) wajib diisi untuk item {$itemCode}.";
                }
            }

            $currentStockOnhand = $this->mItemwarehouse->where([
                'itemcode' => $itemCode,
                'whscode'  => $whsCode
            ])->first();
            $currentStockOnhandArray = is_object($currentStockOnhand) ? (array)$currentStockOnhand : $currentStockOnhand;

            $availableStock = $currentStockOnhandArray ? (float)$currentStockOnhandArray['onhand'] : 0;

            $totalOldQtyForLine = 0;
            foreach ($oldDetailsMap as $k => $v) {
                if (strpos($k, $itemCode . '_') === 0) {
                    $totalOldQtyForLine += $v;
                }
            }

            $effectiveAvailableStock = $availableStock + $totalOldQtyForLine;

            if ($qtyInput > $effectiveAvailableStock) {
                return "Baris ke-{$rowNum}: Qty DO ({$qtyInput}) melebihi total stok gudang {$whsCode} yang tersedia ({$effectiveAvailableStock}) untuk item {$itemCode}.";
            }

            if (!empty($baseEntry)) {
                $soDetail = DB::table('sodetail')->where('docnum', $baseEntry)
                    ->when(!empty($baseLine), function($q) use ($baseLine) {
                        return $q->where('linenum', $baseLine);
                    }, function($q) use ($itemCode) {
                        return $q->where('itemcode', $itemCode);
                    })->first();
                $soDetailArray = json_decode(json_encode($soDetail), true);

                if (!$soDetailArray) {
                    return "Baris ke-{$rowNum}: Item {$itemCode} tidak ditemukan pada SO referensi ({$baseEntry}).";
                }

                $qtySo        = (float)($soDetailArray['qty'] ?? 0);
                $qtyDelivered = (float)($soDetailArray['qtydelivered'] ?? 0);

                $remainingQty = ($qtySo - $qtyDelivered) + $totalOldQtyForLine;

                if ($qtyInput > $remainingQty) {
                    return "Baris ke-{$rowNum}: Qty DO ({$qtyInput}) melebihi sisa Qty SO yang tersedia ({$remainingQty}) untuk item {$itemCode}.";
                }
            }
        }

        return null;
    }

    public function getOpenList(Request $request): JsonResponse
    {
        if (!$request->ajax()) {
            return response()->json([], 404);
        }

        $cardcode = $request->input('cardcode');

        $listData = $this->mSalesorder->getOpen();

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

        $soData = $this->mSalesorder->getWithDetails($docnum);

        if (!$soData || (isset($soData['header']['status']) && (int)$soData['header']['status'] !== 1)) {
            return response()->json([
                'status'  => false,
                'message' => 'Data Sales Order tidak ditemukan atau status sudah tidak aktif.'
            ]);
        }

        $header = $soData['header'];
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

        foreach ($header as $key => $val) {
            $valStr = (string)$val;
            if ($valStr !== '' && (strpos($key, 'date') !== false) && strtotime($valStr)) {
                $header[$key] = date('Y-m-d', strtotime($valStr));
            }
        }

        $processedDetails = [];
        if (!empty($soData['details'])) {
            $whsCode = $header['whscode'] ?? '';
            foreach ($soData['details'] as $row) {
                $rowArray = (array)$row;
                $qtySo        = (float)($rowArray['qty'] ?? 0);
                $qtyDelivered = (float)($rowArray['qtydelivered'] ?? 0);
                $remainingQty = $qtySo - $qtyDelivered; 

                if ($remainingQty > 0) {
                    $itemCode = $rowArray['itemcode'] ?? '';
                    $fefoBatches = method_exists($this->mItembatch, 'getStockByFEFO') 
                        ? $this->mItembatch->getStockByFEFO($itemCode, $whsCode) : [];

                    $qtyNeeded = $remainingQty;
                    $batchNumArray = [];
                    $firstExpDate = null;
                    $allocMap = []; 
                    $totalAvailableStock = 0;

                    if (!empty($fefoBatches)) {
                        foreach ($fefoBatches as $batch) {
                            $batchArray = (array)$batch;
                            $batchStock = (float)($batchArray['onhand'] ?? 0);
                            if ($batchStock <= 0) continue;
                            
                            $totalAvailableStock += $batchStock; 
                            if ($qtyNeeded > 0) {
                                $qtyToTake  = min($batchStock, $qtyNeeded);
                                $batchNumArray[] = $batchArray['batchnum'];
                                $allocMap[$batchArray['batchnum']] = $qtyToTake; 
                                
                                if (!$firstExpDate) $firstExpDate = $batchArray['expdate'];
                                $qtyNeeded -= $qtyToTake;
                            }
                        }
                    }

                    $finalQty = ($totalAvailableStock > 0) ? min($remainingQty, $totalAvailableStock) : $remainingQty;

                    $unitprice   = (float)($rowArray['unitprice'] ?? 0);
                    $discount    = (float)($rowArray['discount'] ?? 0);
                    $itemGross   = $finalQty * $unitprice;
                    $discNominal = $itemGross * ($discount / 100);
                    $itemSubtotal = $itemGross - $discNominal;

                    $processedDetails[] = array_merge($rowArray, [
                        'baseline'          => $rowArray['linenum'] ?? 1,
                        'qty'               => $finalQty,        
                        'subtotal'          => $itemSubtotal,
                        'batchnum'          => !empty($batchNumArray) ? implode(', ', $batchNumArray) : '',
                        'batch_allocations' => !empty($allocMap) ? json_encode($allocMap) : null,
                        'expdate'           => $firstExpDate,
                    ]);
                }
            }
        }

        return response()->json([
            'status'  => true,
            'header'  => $header,
            'details' => $processedDetails
        ]);
    }

    public function getEditBatchData(Request $request, $docnum = null): JsonResponse
    {
        if (!$request->ajax() || empty($docnum)) {
            return response()->json([], 404);
        }

        $details = $this->model->get_details($docnum) ?? [];
        $result = [];

        foreach ($details as $index => $det) {
            $detArray = (array)$det;
            $itemCode = $detArray['itemcode'] ?? '';
            $batchNum = $detArray['batchnum'] ?? '';
            $qtyVal   = (float)($detArray['qty'] ?? 0);
            
            $allocMap = [];
            if (!empty($batchNum)) {
                $allocMap = [$batchNum => $qtyVal];
            }

            $result[] = [
                'baseline'    => $detArray['baseline'] ?? ($index + 1),
                'itemcode'    => $itemCode,
                'batchnum'    => $batchNum,
                'allocations' => $allocMap
            ];
        }

        return response()->json([
            'status' => true,
            'data'   => $result
        ]);
    }
}
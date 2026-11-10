<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Models\MApinvoice;
use App\Models\MGrpo;
use App\Models\MWarehousemaster;
use App\Models\MItemmaster;
use App\Models\MCardmaster;
use App\Models\MPurchaseorder;
use App\Models\MCurrency;
use App\Models\MTax;
use App\Models\MTermin;
use App\Models\MAccount;
use App\Models\MItemgroup;
use App\Models\MJournal;

class Apinvoice extends BaseTransactionController
{
    protected $model;    
    protected MGrpo $mGrpo;
    protected MWarehousemaster $mWarehousemaster;
    protected MItemmaster $mItemmaster;
    protected MCardmaster $mCardmaster;
    protected MPurchaseorder $mPurchaseorder;
    protected MCurrency $mCurrency;
    protected MTax $mTax;
    protected MTermin $mTermin;
    protected MAccount $mAccount;
    protected MItemgroup $mItemgroup;
    protected MJournal $mJournal;
    
    protected bool $showImport   = false;
    protected bool $showRemark   = true;
    protected bool $showSummary  = true;
    protected bool $showFreight  = true;
    protected bool $showDpp      = true;
    protected bool $showBtndraft = true;

    public function __construct()
    {
        $this->model            = new MApinvoice();
        $this->mGrpo            = new MGrpo();
        $this->mWarehousemaster = new MWarehousemaster();
        $this->mItemmaster      = new MItemmaster();
        $this->mCardmaster      = new MCardmaster();
        $this->mPurchaseorder   = new MPurchaseorder();
        $this->mCurrency        = new MCurrency();
        $this->mTax             = new MTax();
        $this->mTermin          = new MTermin();
        $this->mAccount         = new MAccount();
        $this->mItemgroup       = new MItemgroup();
        $this->mJournal         = new MJournal();
    }

    protected function getTitle(): string { return 'AP Invoice'; }
    protected function getBaseRoute(): string { return 'apinvoice'; }
    protected function getTxnNumberField(): string { return 'docnum'; }
    protected function getDateField(): string { return 'docdate'; }
    
    protected function generateTxnNumber(): string 
    { 
        return method_exists($this->model, 'generateNumber') ? $this->model->generateNumber() : 'AP-' . date('ymdHis'); 
    }

    protected function getTableColumns(): array 
    { 
        return ['No', 'Nomor AP', 'Tanggal', 'Nomor Ref', 'Supplier', 'Total', 'Diskon', 'Tax', 'Freight', 'Grand Total', 'Status', 'Aksi']; 
    }

    protected function getExtraColumns($item): array 
    { 
        return [
            e($item['baseentry'] ?? '-'),
            e($item['cardname'] ?? '-'),
            number_format((float)($item['subtotal'] ?? 0), 2, ',', '.'),
            number_format((float)($item['discountamount'] ?? 0), 2, ',', '.'),
            number_format((float)($item['taxamount'] ?? 0), 2, ',', '.'),
            number_format((float)($item['freighttotal'] ?? 0), 2, ',', '.'),
            number_format((float)($item['grandtotal'] ?? 0), 2, ',', '.'),
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
            'show_freight'         => $this->showFreight,
            'show_dpp'             => $this->showDpp,
            'show_btndraft'        => $this->showBtndraft,
            'message'              => '',
            
            'copy_from_url'        => url('apinvoice/getOpenList'),
            'copy_from_detail_url' => url('apinvoice/getOpenDetail'),
            'copy_from_label'      => 'Copy From',
            'copy_from_columns'    => $this->getCopyFromColumns(),
        ];

        return view('basetransaction.add', $data);
    }

    protected function getExtraFields($row = null): array
    {
        return [
            [
                'name'     => 'baseentry',
                'label'    => 'No. Referensi (PO/GRPO)',
                'type'     => 'text',
                'value'    => $row['baseentry'] ?? '',
                'readonly' => true,
                'disable'  => true
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
                'name'        => 'taxcode',
                'label'       => 'Kode Pajak',
                'type'        => 'select',
                'value'       => $row['taxcode'] ?? '',
                'options'     => $this->formatOptions($this->mTax->filters([
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
            [
                'name'  => 'freighttotal',
                'label' => 'Biaya Kirim',
                'type'  => 'number',
                'value' => $row['freighttotal'] ?? 0,
                'class' => 'text-end input-freighttotal',
                'min'   => 0,
                'step'  => '0.01'
            ],
        ];
    }

    protected function getDetailFields(): array
    {
        $items = $this->mItemmaster->filters(['status' => 1]);
        $itemOptions = [];
        foreach ($items as $item) {
            $itemOptions[$item['itemcode']] = [
                'label' => $item['itemname'],
                'uom'   => $item['uomcode'] ?? $item['uom'] ?? '',
                'price' => $item['price'] ?? $item['unitprice'] ?? 0
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
                'width'    => 'auto',
                'readonly' => true,
            ],
            [
                'name'     => 'qty',
                'label'    => 'Jumlah',
                'type'     => 'number',
                'class'    => 'text-end input-qty',
                'required' => true,
                'min'      => 0,
                'max'      => 9999999
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
                'width' => '8%',
                'min'   => 0,
                'max'   => 100
            ],
            [
                'name'     => 'subtotal',
                'label'    => 'Sub Total',
                'type'     => 'number',
                'width'    => 'auto',
                'class'    => 'text-end input-subtotal',
                'readonly' => true
            ],
        ];
    }

    private function getSourceType($db, $baseEntry): string
    {
        if (empty($baseEntry)) return 'PO';
        $grpo = $db->table('grpoheader')->where('docnum', $baseEntry)->get()->getRowArray();
        if ($grpo) return 'GRPO';
        return 'PO';
    }

    private function checkAndUpdateSourceStatus($db, $baseEntry, $sourceType)
    {
        if (empty($baseEntry)) return;

        if ($sourceType === 'PO') {
            $details = $db->table('podetail')->where('docnum', $baseEntry)->get()->getResultArray();
            if (empty($details)) return;

            $isFullyInvoiced = true;
            foreach ($details as $detail) {
                $qtyTotal    = (float)($detail['qty'] ?? 0);
                $qtyInvoiced = (float)($detail['qtyinvoiced'] ?? 0);
                
                if ($qtyInvoiced < $qtyTotal) {
                    $isFullyInvoiced = false;
                    break;
                }
            }

            $newStatus = $isFullyInvoiced ? 2 : 1;
            $db->table('poheader')
               ->where('docnum', $baseEntry)
               ->update(['status' => $newStatus]);
        } else {
            $db->table('grpoheader')
               ->where('docnum', $baseEntry)
               ->update(['status' => 2]);
        }
    }

    private function resolveTargetPoInfo($db, string $baseEntry, string $sourceType, array $itemDetail, int $index = 0): ?array
    {
        if (empty($baseEntry)) return null;

        $itemBaseline = $itemDetail['baseline'] 
                     ?? $itemDetail['linenum'] 
                     ?? $itemDetail['line_num'] 
                     ?? $itemDetail['base_line'] 
                     ?? null;

        $itemCode = $itemDetail['itemcode'] 
                 ?? $itemDetail['item_code'] 
                 ?? $itemDetail['code'] 
                 ?? '';

        if ($sourceType === 'PO') {
            return [
                'poDocNum'  => $baseEntry,
                'poLineNum' => !empty($itemBaseline) ? $itemBaseline : ($index + 1),
                'itemCode'  => $itemCode
            ];
        } else if ($sourceType === 'GRPO') {
            $grpoHeader = $db->table('grpoheader')->where('docnum', $baseEntry)->get()->getRowArray();
            $poDocNum   = $grpoHeader['baseentry'] ?? null;
            if (empty($poDocNum)) return null;

            $poLineNum = null;
            $grpoQuery = $db->table('grpodetail')->where('docnum', $baseEntry);
            if (!empty($itemBaseline)) {
                $grpoQuery->where('linenum', $itemBaseline);
            } else {
                $allGrpo = $grpoQuery->get()->getResultArray();
                $grpoRow = $allGrpo[$index] ?? $allGrpo[0] ?? null;
                $itemBaseline = $grpoRow['linenum'] ?? null;
                
                if ($itemBaseline) {
                    $grpoQuery = $db->table('grpodetail')->where('docnum', $baseEntry)->where('linenum', $itemBaseline);
                }
            }

            $grpoDetailRow = $grpoQuery->get()->getRowArray();
            if (!empty($grpoDetailRow['baseline'])) {
                $poLineNum = $grpoDetailRow['baseline'];
            }
            if (empty($poLineNum)) {
                if (!empty($itemCode)) {
                    $fallbackPo = $db->table('podetail')
                        ->where('docnum', $poDocNum)
                        ->where('itemcode', $itemCode)
                        ->get()
                        ->getRowArray();
                    $poLineNum = $fallbackPo['linenum'] ?? ($index + 1);
                } else {
                    $poLineNum = $index + 1;
                }
            }

            return [
                'poDocNum'  => $poDocNum,
                'poLineNum' => $poLineNum,
                'itemCode'  => $grpoDetailRow['itemcode'] ?? $itemCode
            ];
        }

        return null;
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

        DB::beginTransaction();

        try {
            $headerData = $this->prepareHeaderOnCreate($postData, $status, $request);
            $headerData[$txnNumberField] = $txnNumber;

            $baseEntry  = $headerData['baseentry'] ?? $postData['baseentry'] ?? null;
            $sourceType = $headerData['sourcetype'] ?? $postData['sourcetype'] ?? $this->getSourceType(DB::getFacadeRoot(), $baseEntry);

            $items = $postData['items'] ?? $postData['details'] ?? [];
            if (is_string($items)) {
                $items = json_decode($items, true) ?? [];
            }
            $detailData = $this->prepareDetailsOnCreate($items, $txnNumber, $request);

            if (method_exists($this->model, 'saveadd')) {
                $this->model->saveadd($headerData, $detailData, $txnNumberField);
            } else {
                $this->model->insert($headerData);
                DB::table($this->model->table . 'detail')->insert($detailData);
            }

            if ($status === 1) {
                if (method_exists($this, 'handleJournal')) {
                    $this->handleJournal($txnNumber, $status, $headerData, $detailData, 'APINV');
                } elseif (method_exists($this->mJournal, 'createJournal')) {
                    $this->mJournal->createJournal('APINV', $txnNumber, $headerData, $detailData);
                }

                if (!empty($baseEntry)) {
                    foreach ($detailData as $index => $item) {
                        $qty = (float)($item['qty'] ?? $item['quantity'] ?? 0);
                        if ($qty > 0) {
                            $poInfo = $this->resolveTargetPoInfo(DB::getFacadeRoot(), $baseEntry, $sourceType, $item, $index);
                            if ($poInfo && !empty($poInfo['poDocNum'])) {
                                DB::table('podetail')->where('docnum', $poInfo['poDocNum'])
                                    ->when(!empty($poInfo['poLineNum']), function($q) use ($poInfo) {
                                        return $q->where('linenum', $poInfo['poLineNum']);
                                    }, function($q) use ($poInfo) {
                                        return $q->where('itemcode', $poInfo['itemCode']);
                                    })
                                    ->increment('qtyinvoiced', $qty);
                            }
                        }
                    }

                    if ($sourceType === 'PO') {
                        $this->checkAndUpdateSourceStatus(DB::getFacadeRoot(), $baseEntry, 'PO');
                    } else {
                        DB::table('grpoheader')->where('docnum', $baseEntry)->update(['status' => 2]);
                        $grpoHeader = DB::table('grpoheader')->where('docnum', $baseEntry)->first();
                        $grpoHeaderArray = json_decode(json_encode($grpoHeader), true);
                        if (!empty($grpoHeaderArray['baseentry'])) {
                            $this->checkAndUpdateSourceStatus(DB::getFacadeRoot(), $grpoHeaderArray['baseentry'], 'PO');
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

            $message = $status === 9 ? 'Draft AP Invoice berhasil disimpan.' : 'AP Invoice berhasil disimpan dan di-approve.';

            return $this->ajaxResponse('success', $message, [
                'redirect' => url($this->getBaseRoute())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    public function saveedit(Request $request, $id = null): JsonResponse
    {
        if (!$request->ajax() || !$request->isMethod('post')) {
            return response()->json([], 405);
        }

        $postData  = $request->post();
        $txnNumber = $id ?? $postData['docnum'] ?? null;

        if (empty($txnNumber)) {
            return $this->ajaxResponse('error', 'Nomor transaksi tidak valid untuk proses edit.');
        }

        $validationError = $this->validateTransaction($postData, $txnNumber);
        if ($validationError) {
            return $this->ajaxResponse('error', $validationError);
        }

        $status = ($postData['btn_submit'] ?? 'save') === 'draft' ? 9 : 1;

        DB::beginTransaction();

        try {
            $oldHeader = $this->model->find($id) ?? $this->model->where('docnum', $txnNumber)->first();
            $oldHeaderArray = is_object($oldHeader) ? (array)$oldHeader : $oldHeader;
            $oldDetails   = $this->model->get_details($txnNumber);
            $oldStatus    = (int)($oldHeaderArray['status'] ?? 9); 
            $oldBaseEntry = $oldHeaderArray['baseentry'] ?? null;
            $oldSourceType= $oldHeaderArray['sourcetype'] ?? $this->getSourceType(DB::getFacadeRoot(), $oldBaseEntry);
            
            if ($oldStatus === 1) {
                if (!empty($oldBaseEntry)) {
                    if (!empty($oldDetails)) {
                        foreach ($oldDetails as $index => $oldItem) {
                            $oldItemArray = (array)$oldItem;
                            $oldQty = (float)($oldItemArray['qty'] ?? $oldItemArray['quantity'] ?? 0);
                            if ($oldQty > 0) {
                                $poInfo = $this->resolveTargetPoInfo(DB::getFacadeRoot(), $oldBaseEntry, $oldSourceType, $oldItemArray, $index);
                                if ($poInfo && !empty($poInfo['poDocNum'])) {
                                    DB::table('podetail')->where('docnum', $poInfo['poDocNum'])
                                        ->when(!empty($poInfo['poLineNum']), function($q) use ($poInfo) {
                                            return $q->where('linenum', $poInfo['poLineNum']);
                                        }, function($q) use ($poInfo) {
                                            return $q->where('itemcode', $poInfo['itemCode']);
                                        })
                                        ->decrement('qtyinvoiced', $oldQty);
                                }
                            }
                        }
                    }

                    if ($oldSourceType === 'PO') {
                        $this->checkAndUpdateSourceStatus(DB::getFacadeRoot(), $oldBaseEntry, 'PO');
                    } else {
                        DB::table('grpoheader')->where('docnum', $oldBaseEntry)->update(['status' => 1]);
                        $grpoHeader = DB::table('grpoheader')->where('docnum', $oldBaseEntry)->first();
                        $grpoHeaderArray = json_decode(json_encode($grpoHeader), true);
                        if (!empty($grpoHeaderArray['baseentry'])) {
                            $this->checkAndUpdateSourceStatus(DB::getFacadeRoot(), $grpoHeaderArray['baseentry'], 'PO');
                        }
                    }
                }
            }

            $headerData = $this->prepareHeaderData($txnNumber, $postData); 
            $baseEntry  = $headerData['baseentry'] ?? $postData['baseentry'] ?? $oldBaseEntry ?? null;
            $sourceType = $headerData['sourcetype'] ?? $postData['sourcetype'] ?? $oldSourceType ?? $this->getSourceType(DB::getFacadeRoot(), $baseEntry); 
            $headerData['baseentry']  = $baseEntry;
            $headerData['sourcetype'] = $sourceType;

            $items = $postData['items'] ?? $postData['details'] ?? [];
            if (is_string($items)) {
                $items = json_decode($items, true) ?? [];
            }
            $detailData = $this->prepareDetailsData($txnNumber, $postData); 
            $saveResult = $this->model->saveedit($id, $headerData, $detailData);
            if (isset($saveResult['status']) && $saveResult['status'] === 'error') {
                throw new \Exception($saveResult['message'] ?? 'Gagal memperbarui data.');
            } 

            if ($status === 1) {
                if (method_exists($this, 'handleJournal')) {
                    $this->handleJournal($txnNumber, $status, $headerData, $detailData, 'APINV');
                } else {
                    if (method_exists($this->mJournal, 'updateJournal')) {
                        $this->mJournal->updateJournal('APINV', $txnNumber, $headerData, $detailData);
                    } elseif (method_exists($this->mJournal, 'createJournal')) {
                        $this->mJournal->createJournal('APINV', $txnNumber, $headerData, $detailData);
                    }
                }

                if (!empty($baseEntry)) {
                    foreach ($detailData as $index => $item) {
                        $qty = (float)($item['qty'] ?? $item['quantity'] ?? 0);
                        if ($qty > 0) {
                            $poInfo = $this->resolveTargetPoInfo(DB::getFacadeRoot(), $baseEntry, $sourceType, $item, $index);
                            if ($poInfo && !empty($poInfo['poDocNum'])) {
                                DB::table('podetail')->where('docnum', $poInfo['poDocNum'])
                                    ->when(!empty($poInfo['poLineNum']), function($q) use ($poInfo) {
                                        return $q->where('linenum', $poInfo['poLineNum']);
                                    }, function($q) use ($poInfo) {
                                        return $q->where('itemcode', $poInfo['itemCode']);
                                    })
                                    ->increment('qtyinvoiced', $qty);
                            }
                        }
                    }

                    if ($sourceType === 'PO') {
                        $this->checkAndUpdateSourceStatus(DB::getFacadeRoot(), $baseEntry, 'PO');
                    } else {
                        DB::table('grpoheader')->where('docnum', $baseEntry)->update(['status' => 2]);
                        $grpoHeader = DB::table('grpoheader')->where('docnum', $baseEntry)->first();
                        $grpoHeaderArray = json_decode(json_encode($grpoHeader), true);
                        if (!empty($grpoHeaderArray['baseentry'])) {
                            $this->checkAndUpdateSourceStatus(DB::getFacadeRoot(), $grpoHeaderArray['baseentry'], 'PO');
                        }
                    }
                }
            } else {
                if ($oldStatus === 1 && method_exists($this->mJournal, 'deleteByReference')) {
                    $this->mJournal->deleteByReference($txnNumber);
                }
            }

            DB::commit();

            $this->flashSession([
                'status'  => ($status === 9 ? 'draft' : 'edit'), 
                'fa_id'   => $txnNumber, 
                'fa_name' => $this->getTitle()
            ]);

            return $this->ajaxResponse('success', 'AP Invoice berhasil diperbarui.', [
                'redirect' => url($this->getBaseRoute())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    public function saveactive(Request $request, $id = null): JsonResponse
    {
        if (!$request->ajax() || !$request->isMethod('post')) {
            return response()->json([], 405);
        }

        $txnNumber = $id ?? $request->post('docnum') ?? $request->post('id') ?? null;

        if (empty($txnNumber)) {
            return $this->ajaxResponse('error', 'Nomor transaksi tidak valid untuk proses aktivasi.');
        }

        DB::beginTransaction();

        try {
            $header = $this->model->find($txnNumber) ?? $this->model->where('docnum', $txnNumber)->first();
            $headerArray = is_object($header) ? (array)$header : $header;

            if (!$headerArray) {
                return $this->ajaxResponse('error', 'Data transaksi AP Invoice tidak ditemukan.');
            }

            $currentStatus = (int)($headerArray['status'] ?? 0);
            if ($currentStatus === 1) {
                return $this->ajaxResponse('error', 'Transaksi ini sudah dalam status Active.');
            }

            $realId     = $headerArray['id'] ?? $txnNumber;
            $docNum     = $headerArray['docnum'] ?? $txnNumber;
            $baseEntry  = $headerArray['baseentry'] ?? null;
            $sourceType = $headerArray['sourcetype'] ?? $this->getSourceType(DB::getFacadeRoot(), $baseEntry);

            $details = $this->model->get_details($docNum);
            if (empty($details)) {
                return $this->ajaxResponse('error', 'Detail item transaksi kosong, tidak dapat diaktifkan.');
            }

            $updateData = [
                'status'       => 1,
                'approveddate' => date('Y-m-d H:i:s'),
                'approvedby'   => (int) (Session::get('usercode') ?? 1)
            ];

            if (method_exists($this->model, 'saveactive')) {
                $this->model->saveactive($realId, $updateData);
            } else {
                $this->model->update($realId, $updateData);
            }

            if (method_exists($this, 'handleJournal')) {
                $this->handleJournal($docNum, 1, $headerArray, $details, 'APINV');
            } else {
                if (method_exists($this->mJournal, 'deleteByReference')) {
                    $this->mJournal->deleteByReference($docNum);
                }
                if (method_exists($this->mJournal, 'createJournal')) {
                    $this->mJournal->createJournal('APINV', $docNum, $headerArray, $details);
                }
            }

            if (!empty($baseEntry)) {
                if ($sourceType === 'PO') {
                    foreach ($details as $item) {
                        $itemArray = (array)$item;
                        $itemCode = $itemArray['itemcode'] ?? '';
                        $qty      = (float)($itemArray['qty'] ?? $itemArray['quantity'] ?? 0);
                        $baseLine = $itemArray['baseline'] ?? null;

                        if (!empty($itemCode) && $qty > 0) {
                            DB::table('podetail')->where('docnum', $baseEntry)
                                ->when(!empty($baseLine), function($q) use ($baseLine) {
                                    return $q->where('linenum', $baseLine);
                                }, function($q) use ($itemCode) {
                                    return $q->where('itemcode', $itemCode);
                                })
                                ->increment('qtyinvoiced', $qty);
                        }
                    }
                }
                $this->checkAndUpdateSourceStatus(DB::getFacadeRoot(), $baseEntry, $sourceType);
            }

            DB::commit();

            $this->flashSession([
                'status'  => 'active',
                'fa_id'   => $docNum,
                'fa_name' => $this->getTitle()
            ]);

            return $this->ajaxResponse('success', 'AP Invoice berhasil diaktifkan (Approved).', [
                'redirect' => url($this->getBaseRoute())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    public function savecancel(Request $request, $id = null): JsonResponse
    {
        if (!$request->ajax() || !$request->isMethod('post')) {
            return response()->json([], 405);
        }

        $txnNumber = $id ?? $request->post('docnum') ?? null;

        if (empty($txnNumber)) {
            return $this->ajaxResponse('error', 'Nomor transaksi tidak valid untuk proses pembatalan.');
        }

        DB::beginTransaction();

        try {
            $oldHeader = $this->model->find($id) ?? $this->model->where('docnum', $txnNumber)->first();
            $oldHeaderArray = is_object($oldHeader) ? (array)$oldHeader : $oldHeader;

            if (!$oldHeaderArray) {
                return $this->ajaxResponse('error', 'Data transaksi AP Invoice tidak ditemukan.');
            }

            $oldStatus    = (int)($oldHeaderArray['status'] ?? 0);
            if ($oldStatus === 0) {
                return $this->ajaxResponse('error', 'Transaksi ini sudah dalam status Canceled.');
            }

            $oldDetails   = $this->model->get_details($txnNumber);
            $oldBaseEntry = $oldHeaderArray['baseentry'] ?? null;
            $oldSourceType= $oldHeaderArray['sourcetype'] ?? $this->getSourceType(DB::getFacadeRoot(), $oldBaseEntry);

            if ($oldStatus === 1) {
                if (!empty($oldBaseEntry)) {
                    if (!empty($oldDetails)) {
                        foreach ($oldDetails as $index => $oldItem) {
                            $oldItemArray = (array)$oldItem;
                            $oldQty = (float)($oldItemArray['qty'] ?? $oldItemArray['quantity'] ?? 0);
                            if ($oldQty > 0) {
                                $poInfo = $this->resolveTargetPoInfo(DB::getFacadeRoot(), $oldBaseEntry, $oldSourceType, $oldItemArray, $index);
                                if ($poInfo && !empty($poInfo['poDocNum'])) {
                                    DB::table('podetail')->where('docnum', $poInfo['poDocNum'])
                                        ->when(!empty($poInfo['poLineNum']), function($q) use ($poInfo) {
                                            return $q->where('linenum', $poInfo['poLineNum']);
                                        }, function($q) use ($poInfo) {
                                            return $q->where('itemcode', $poInfo['itemCode']);
                                        })
                                        ->decrement('qtyinvoiced', $oldQty);
                                }
                            }
                        }
                    }

                    if ($oldSourceType === 'PO') {
                        $this->checkAndUpdateSourceStatus(DB::getFacadeRoot(), $oldBaseEntry, 'PO');
                    } else {
                        DB::table('grpoheader')->where('docnum', $oldBaseEntry)->update(['status' => 1]);
                        $grpoHeader = DB::table('grpoheader')->where('docnum', $oldBaseEntry)->first();
                        $grpoHeaderArray = json_decode(json_encode($grpoHeader), true);
                        if (!empty($grpoHeaderArray['baseentry'])) {
                            $this->checkAndUpdateSourceStatus(DB::getFacadeRoot(), $grpoHeaderArray['baseentry'], 'PO');
                        }
                    }
                }
            }

            $userCode = (int) (Session::get('usercode') ?? 1);
            $this->model->cancelDocument($id ?? $oldHeaderArray['id'] ?? $txnNumber, $userCode);

            if (method_exists($this, 'handleJournal')) {
                $this->handleJournal($txnNumber, 0, $oldHeaderArray, $oldDetails, 'APINV');
            } else {
                if (method_exists($this->mJournal, 'updateStatusByReference')) {
                    $this->mJournal->updateStatusByReference($txnNumber, 0);
                }
            }

            DB::commit();

            $this->flashSession([
                'status'  => 'savecancel',
                'fa_id'   => $txnNumber,
                'fa_name' => $this->getTitle()
            ]);

            $alertMessage = '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                          . 'Nomor transaksi AP Invoice <strong>' . e($txnNumber) . '</strong> berhasil dibatalkan (Cancel).'
                          . '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'
                          . '</div>';

            return $this->ajaxResponse('success', $alertMessage, [
                'redirect' => url($this->getBaseRoute())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->ajaxResponse('error', 'System Error: ' . $e->getMessage());
        }
    }

    protected function prepareHeaderOnCreate(array $postData, int $status, Request $request = null): array
    {
        $header   = parent::prepareHeaderOnCreate($postData, $status, $request ?? request());
        $computed = $this->calculateHeaderTotals($postData);

        $baseEntry  = !empty($postData['baseentry']) ? $postData['baseentry'] : null;
        $sourceType = !empty($baseEntry) ? $this->getSourceType(DB::getFacadeRoot(), $baseEntry) : '';

        $data = array_merge($header, [
            'docdate'        => date('Y-m-d H:i:s'),
            'baseentry'      => $baseEntry,
            'sourcetype'     => $sourceType,
            'cardcode'       => $postData['cardcode'] ?? '',
            'whscode'        => $postData['whscode'] ?? '',
            'currcode'       => $postData['currcode'] ?? 'IDR',
            'termcode'       => $postData['termcode'] ?? '',
            'taxclude'       => isset($postData['taxclude']) ? (int)$postData['taxclude'] : 0,
            'taxcode'        => $postData['taxcode'] ?? '',
            'remark'         => $postData['remark'] ?? '',
            'status'         => $status,
            'subtotal'       => $computed['subtotal'],
            'discountamount' => $computed['discountamount'],
            'dppamount'      => $computed['dppamount'],
            'taxamount'      => $computed['taxamount'],
            'freighttotal'   => $computed['freighttotal'],
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

    protected function prepareHeaderData(string $txnNumber, array $postData): array
    {
        $computed = $this->calculateHeaderTotals($postData);

        $baseEntry  = !empty($postData['baseentry']) ? $postData['baseentry'] : null;
        $sourceType = !empty($baseEntry) ? $this->getSourceType(DB::getFacadeRoot(), $baseEntry) : '';

        $data = [
            'baseentry'      => $baseEntry,
            'sourcetype'     => $sourceType,
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
            'freighttotal'   => $computed['freighttotal'],
            'grandtotal'     => $computed['grandtotal'],
            'remark'         => $postData['remark'] ?? '',
            'updateddate'    => date('Y-m-d H:i:s'),
            'updatedby'      => (int) (Session::get('usercode') ?? 1),
        ];

        return $data;
    }

    protected function prepareDetailsOnCreate(array $items, string $txnNumber, Request $request = null): array
    {
        $detailsData = [];

        foreach ($items as $index => $item) {
            $qty       = $this->parseNumber($item['qty'] ?? 0);
            $unitprice = $this->parseNumber($item['unitprice'] ?? 0);
            $discount  = $this->parseNumber($item['discount'] ?? 0);

            $itemGross    = $qty * $unitprice;
            $discNominal  = $itemGross * ($discount / 100);
            $itemSubtotal = $itemGross - $discNominal;

            $detailsData[] = [
                'docnum'    => $txnNumber,
                'linenum'   => $index + 1,
                'baseline'  => $item['baseline'] ?? $item['linenum'] ?? ($index + 1),
                'itemcode'  => $item['itemcode'] ?? '',
                'uomcode'   => $item['uomcode'] ?? '',
                'qty'       => $qty,
                'unitprice' => $unitprice,
                'discount'  => $discount,
                'subtotal'  => $itemSubtotal,
            ];
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

    protected function validateTransaction(array $postData, ?string $currentDocNum = null): ?string
    {
        if (empty($postData['cardcode'])) {
            return 'Supplier / Vendor wajib dipilih.';
        }
        if (empty($postData['whscode'])) {
            return 'Gudang tujuan wajib dipilih.';
        }

        $freightTotal = $this->parseNumber($postData['freighttotal'] ?? 0);
        if ($freightTotal < 0) {
            return 'Biaya angkut (freight total) tidak boleh bernilai negatif.';
        }

        $items = $postData['items'] ?? $postData['details'] ?? [];
        if (is_string($items)) {
            $items = json_decode($items, true) ?? [];
        }

        if (empty($items)) {
            return 'Detail barang yang dipesan tidak boleh kosong!';
        }

        $baseEntry  = $postData['baseentry'] ?? null;
        $sourceType = !empty($baseEntry) ? $this->getSourceType(DB::getFacadeRoot(), $baseEntry) : ($postData['sourcetype'] ?? 'GRPO');

        $oldDetailsMap = [];
        if (!empty($currentDocNum)) {
            $oldDetails = $this->model->get_details($currentDocNum);
            if (!empty($oldDetails)) {
                foreach ($oldDetails as $old) {
                    $oldArray = (array)$old;
                    $key = !empty($oldArray['baseline']) ? $oldArray['baseline'] : $oldArray['itemcode'];
                    $oldDetailsMap[$key] = (float)($oldArray['qty'] ?? 0);
                }
            }
        }

        foreach ($items as $index => $item) {
            $rowNum   = $index + 1;
            $itemCode = $item['itemcode'] ?? '';
            $qtyInput = $this->parseNumber($item['qty'] ?? 0);
            $baseLine = $item['baseline'] ?? ($index + 1);
            
            if (empty($itemCode)) {
                return "Barang pada baris ke-{$rowNum} belum dipilih.";
            }
            if ($qtyInput <= 0) {
                return "Jumlah (Qty) pada baris ke-{$rowNum} harus lebih dari 0.";
            }
            if ($this->parseNumber($item['unitprice'] ?? 0) < 0) {
                return "Harga satuan pada baris ke-{$rowNum} tidak boleh bernilai negatif.";
            }

            if (!empty($baseEntry)) {
                if ($sourceType === 'PO') {
                    $builder = DB::table('podetail')->where('docnum', $baseEntry);
                    if (!empty($baseLine)) {
                        $builder->where('linenum', $baseLine);
                    } else {
                        $builder->where('itemcode', $itemCode);
                    }
                    $refDetail = $builder->first();
                    $refDetailArray = json_decode(json_encode($refDetail), true);

                    if (!$refDetailArray) {
                        return "Baris ke-{$rowNum}: Item {$itemCode} tidak ditemukan pada referensi PO ({$baseEntry}).";
                    }

                    $qtyDoc      = (float)($refDetailArray['qty'] ?? 0);
                    $qtyInvoiced = (float)($refDetailArray['qtyinvoiced'] ?? 0);

                    $mapKey = !empty($baseLine) ? $baseLine : $itemCode;
                    $oldQty = $oldDetailsMap[$mapKey] ?? 0;
                    $effectiveInvoiced = $qtyInvoiced - $oldQty;

                    $remainingQty = $qtyDoc - $effectiveInvoiced;

                    if ($qtyInput > $remainingQty) {
                        return "Baris ke-{$rowNum}: Qty AP Invoice ({$qtyInput}) melebihi sisa Qty PO yang tersedia ({$remainingQty}) untuk item {$itemCode}.";
                    }
                } else {
                    $builder = DB::table('grpodetail')->where('docnum', $baseEntry);
                    if (!empty($baseLine)) {
                        $builder->where('linenum', $baseLine);
                    } else {
                        $builder->where('itemcode', $itemCode);
                    }
                    $refDetail = $builder->first();
                    $refDetailArray = json_decode(json_encode($refDetail), true);

                    if (!$refDetailArray) {
                        return "Baris ke-{$rowNum}: Item {$itemCode} tidak ditemukan pada referensi GRPO ({$baseEntry}).";
                    }

                    $qtyDoc = (float)($refDetailArray['qty'] ?? 0);
                    if ($qtyInput > $qtyDoc) {
                        return "Baris ke-{$rowNum}: Qty AP Invoice ({$qtyInput}) melebihi Qty GRPO ({$qtyDoc}) untuk item {$itemCode}.";
                    }
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

        $poList   = $this->mPurchaseorder->getOpen($cardcode) ?? [];
        $grpoList = $this->mGrpo->getOpen($cardcode) ?? [];

        $listData = array_merge($poList, $grpoList);

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

        $sourceType = 'GRPO';
        $docData = $this->mGrpo->getWithDetails($docnum);

        if (!$docData || (isset($docData['header']['status']) && (int)$docData['header']['status'] !== 1)) {
            $docData = $this->mPurchaseorder->getWithDetails($docnum);
            $sourceType = 'PO';
        }

        if (!$docData || (isset($docData['header']['status']) && (int)$docData['header']['status'] !== 1)) {
            return response()->json([
                'status'  => false, 
                'message' => 'Data referensi (PO / GRPO) tidak ditemukan atau status sudah tidak aktif.'
            ]);
        }

        $header = $docData['header'];
        $header['baseentry']  = $header['docnum'] ?? $docnum;
        $header['sourcetype'] = $sourceType; 

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
            $valStr = (string)$val;
            if ($valStr !== '' && (strpos($key, 'date') !== false) && strtotime($valStr)) {
                $header[$key] = date('Y-m-d', strtotime($valStr));
            }
        }

        $processedDetails = [];
        if (!empty($docData['details'])) {
            foreach ($docData['details'] as $row) {
                $rowArray = (array)$row;
                $qtyDoc = (float)($rowArray['qty'] ?? 0);

                if ($sourceType === 'PO') {
                    $qtyinvoiced  = (float)($rowArray['qtyinvoiced'] ?? 0);
                    $remainingQty = $qtyDoc - $qtyinvoiced; 
                } else {
                    $remainingQty = $qtyDoc;
                }

                if ($remainingQty > 0) {
                    $unitprice = (float)($rowArray['unitprice'] ?? 0);
                    $discount  = (float)($rowArray['discount'] ?? 0);
                    $subtotal  = ($remainingQty * $unitprice) - (($discount / 100) * $remainingQty * $unitprice);

                    $processedDetails[] = array_merge($rowArray, [
                        'baseline' => $rowArray['linenum'] ?? $rowArray['lineid'] ?? 1, 
                        'qty'      => $remainingQty,         
                        'subtotal' => $subtotal              
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
}
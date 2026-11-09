<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
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
use App\Models\MItemwarehouse;
use App\Models\MItembatch;
use App\Models\MStockledger;
use App\Models\MJournal;

class Grpo extends BaseTransactionController
{
    protected $model;
    protected MWarehousemaster $mWarehousemaster;
    protected MItemmaster $mItemmaster;
    protected MCardmaster $mCardmaster;
    protected MPurchaseorder $mPurchaseorder;
    protected MCurrency $mCurrency;
    protected MTax $mTax;
    protected MTermin $mTermin;
    protected MAccount $mAccount;
    protected MItemgroup $mItemgroup;
    protected MItemwarehouse $mItemwarehouse;
    protected MItembatch $mItembatch;
    protected MStockledger $mStockledger;
    protected MJournal $mJournal;
    
    protected bool $showImport   = false;
    protected bool $showRemark   = true;
    protected bool $showSummary  = true;
    protected bool $showFreight  = false;
    protected bool $showDpp      = true;
    protected bool $showBtndraft = true;

    public function __construct()
    {
        parent::__construct();
        $this->model            = new MGrpo();
        $this->mWarehousemaster = new MWarehousemaster();
        $this->mItemmaster      = new MItemmaster();
        $this->mCardmaster      = new MCardmaster();
        $this->mPurchaseorder   = new MPurchaseorder();
        $this->mCurrency        = new MCurrency();
        $this->mTax             = new MTax();
        $this->mTermin          = new MTermin();
        $this->mAccount         = new MAccount();
        $this->mItemgroup       = new MItemgroup();
        $this->mItemwarehouse   = new MItemwarehouse();
        $this->mItembatch       = new MItembatch();
        $this->mStockledger     = new MStockledger();
        $this->mJournal         = new MJournal();
    }

    protected function getTitle(): string { return 'Goods Receipt PO'; }
    protected function getBaseRoute(): string { return 'grpo'; }
    protected function getTxnNumberField(): string { return 'docnum'; }
    protected function getDateField(): string { return 'docdate'; }
    
    protected function generateTxnNumber(): string 
    { 
        return method_exists($this->model, 'generateNumber') ? $this->model->generateNumber() : 'GO-' . date('ymdHis'); 
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
            'show_dpp'             => $this->showDpp,
            'show_btndraft'        => $this->showBtndraft,
            'message'              => '',
            
            'copy_from_url'        => url('grpo/getOpenList'),
            'copy_from_detail_url' => url('grpo/getOpenDetail'),
            'copy_from_label'      => 'Copy From PO',
            'copy_from_columns'    => $this->getCopyFromColumns(),
        ];

        return view('basetransaction.add', $data);
    }

    protected function getTableColumns(): array 
    { 
        return ['No', 'Nomor GRPO', 'Tanggal', 'Nomor PO', 'Supplier', 'Total', 'Diskon', 'Tax', 'Grand Total', 'Status', 'Aksi']; 
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

    protected function getExtraFields($row = null): array
    {
        return [
            [
                'name'      => 'baseentry',
                'label'     => 'No. PO Referensi',
                'type'      => 'text',
                'value'     => $row['baseentry'] ?? '',
                'readonly'  => true,
                'disable'   => true
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
            $itemArray = (array)$item;
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
                'width'         => '8%',
                'readonly'      => true,
            ],
            [
                'name'          => 'qty',
                'label'         => 'Jumlah',
                'type'          => 'number',
                'class'         => 'text-end input-qty',
                'required'      => true,
                'min'           => 0,
                'max'           => 9999999
            ],
            [
                'name'          => 'unitprice',
                'label'         => 'Harga',
                'type'          => 'number',
                'class'         => 'text-end input-price',
                'required'      => true,
                'min'           => 0
            ],
            [
                'name'          => 'discount',
                'label'         => 'Diskon',
                'type'          => 'number',
                'class'         => 'text-end input-discount',                
                'width'         => '8%',
                'min'           => 0,
                'max'           => 100
            ],
            [
                'name'          => 'batchnum',
                'label'         => 'Kode Batch',
                'type'          => 'text',
                'width'         => '9%',
                'readonly'      => true,
            ],
            [
                'name'          => 'expdate',
                'label'         => 'Kadaluarsa',
                'type'          => 'date',
                'width'         => '9%',
                'readonly'      => true,
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

    private function generateBatchNumber(string $itemCode, string $docDate): string
    {
        $year = date('y', strtotime($docDate));
        $itemMaster = $this->mItemmaster->where('itemcode', $itemCode)->first();
        $itemMasterArray = is_object($itemMaster) ? (array)$itemMaster : $itemMaster;
        $itemBatch  = str_pad($itemMasterArray['itembatch'] ?? '000', 3, '0', STR_PAD_LEFT);
        
        $currentYear = date('Y', strtotime($docDate));
         
        $sequence = DB::table('grpodetail as b')
            ->join('grpoheader as a', 'a.docnum', '=', 'b.docnum')
            ->where('b.itemcode', $itemCode)
            ->whereYear('a.docdate', $currentYear)
            ->count() + 1; 

        $seqFormatted = str_pad($sequence % 1000, 3, '0', STR_PAD_LEFT);
        
        return $year . $itemBatch . $seqFormatted;
    }

    private function checkAndUpdatePoStatus($db, $baseEntry)
    {
        if (empty($baseEntry)) return;

        $poDetails = DB::table('podetail')->where('docnum', $baseEntry)->get();
        
        $isFullyReceived = true;
        foreach ($poDetails as $detail) {
            $detailArray = (array)$detail;
            $qtyOrder   = (float)($detailArray['qty'] ?? 0);
            $qtyReceive = (float)($detailArray['qtyreceived'] ?? 0);
            
            if ($qtyReceive < $qtyOrder) {
                $isFullyReceived = false;
                break;
            }
        }

        $newPoStatus = $isFullyReceived ? 2 : 1;

        DB::table('poheader')
           ->where('docnum', $baseEntry)
           ->update(['status' => $newPoStatus]);
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
            $items       = $postData['items'] ?? $postData['details'] ?? [];
            if (is_string($items)) {
                $items = json_decode($items, true) ?? [];
            }

            $headerData  = $this->prepareHeaderOnCreate($postData, $status, $items, $request);
            $headerData[$txnNumberField] = $txnNumber;
            
            $detailData  = $this->prepareDetailsOnCreate($items, $txnNumber);

            if (method_exists($this->model, 'saveadd')) {
                $this->model->saveadd($headerData, $detailData, $txnNumberField);
            } else {
                $this->model->insert($headerData);
            }

            if ($status === 1) {
                if (method_exists($this, 'handleJournal')) {
                    $this->handleJournal($txnNumber, $status, $headerData, $detailData, 'GRPO');
                } elseif (method_exists($this->mJournal, 'createJournal')) {
                    $this->mJournal->createJournal('GRPO', $txnNumber, $headerData, $detailData);
                }

                $defaultWhs = $headerData['whscode'] ?? '';
                $docDate    = $headerData['docdate'] ?? date('Y-m-d H:i:s');
                $baseEntry  = $headerData['baseentry'] ?? null;

                foreach ($detailData as $item) {
                    $itemCode = $item['itemcode'] ?? '';
                    $qty      = (float)($item['qty'] ?? $item['quantity'] ?? 0);
                    $unitCost = (float)($item['unitprice'] ?? $item['price'] ?? $item['unitcost'] ?? 0);
                    $whsCode  = $item['whscode'] ?? $defaultWhs;
                    $baseLine = $item['baseline'] ?? null;
                    $batchNum = $item['batchnum'] ?? '';
                    $expDate  = $item['expdate'] ?? null;

                    if (!empty($itemCode) && $qty > 0) {
                        if (method_exists($this->mStockledger, 'recordEntry')) {
                            $this->mStockledger->recordEntry([
                                'docdate'  => $docDate,
                                'docnum'   => $txnNumber,
                                'source'   => 'GRPO',
                                'itemcode' => $itemCode,
                                'whscode'  => $whsCode,
                                'areacode' => null,
                                'batchnum' => $batchNum,
                                'expdate'  => $expDate,
                                'in_qty'   => $qty,
                                'out_qty'  => 0.0000,
                                'unitcost' => $unitCost
                            ]);
                        }

                        if (method_exists($this->mItemwarehouse, 'addStock')) {
                            $this->mItemwarehouse->addStock($itemCode, $whsCode, $qty);
                        }
                        if (method_exists($this->mItembatch, 'addBatchStock')) {
                            $this->mItembatch->addBatchStock($itemCode, $whsCode, null, $batchNum, $expDate, $unitCost, $qty);
                        }
                        if (method_exists($this->mItemwarehouse, 'reduceOrdered')) {
                            $this->mItemwarehouse->reduceOrdered($itemCode, $whsCode, $qty);
                        }

                        if (!empty($baseEntry)) {
                            DB::table('podetail')->where('docnum', $baseEntry)
                                ->when(!empty($baseLine), function($q) use ($baseLine) {
                                    return $q->where('linenum', $baseLine);
                                }, function($q) use ($itemCode) {
                                    return $q->where('itemcode', $itemCode);
                                })
                                ->increment('qtyreceived', $qty);
                        }
                    }
                }

                if (!empty($baseEntry)) {
                    $this->checkAndUpdatePoStatus(DB::getFacadeRoot(), $baseEntry);
                }
            }

            DB::commit();

            $this->flashSession([
                'status'  => ($status === 9 ? 'draft' : 'add'), 
                'fa_id'   => $txnNumber, 
                'fa_name' => $this->getTitle()
            ]);

            $message = $status === 9 ? 'Draft Goods Receipt PO berhasil disimpan.' : 'Goods Receipt PO berhasil disimpan dan di-approve.';

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

            if ($oldStatus === 1 && !empty($oldDetails)) {
                $oldLedgers = DB::table('stockledger')->where('docnum', $txnNumber)->get();
                
                $netLedgerMap = [];
                foreach ($oldLedgers as $l) {
                    $lArray = (array)$l;
                    $iCode = $lArray['itemcode'];
                    $wCode = $lArray['whscode'];
                    $bNum  = $lArray['batchnum'] ?? '';
                    $key   = $iCode . '|' . $wCode . '|' . $bNum;
                    
                    if (!isset($netLedgerMap[$key])) {
                        $netLedgerMap[$key] = [
                            'itemcode' => $iCode,
                            'whscode'  => $wCode,
                            'batchnum' => $bNum,
                            'areacode' => $lArray['areacode'] ?? '',
                            'expdate'  => $lArray['expdate'],
                            'unitcost' => (float)$lArray['unitcost'],
                            'net_qty'  => 0
                        ];
                    }
                    $netLedgerMap[$key]['net_qty'] += ((float)$lArray['in_qty'] - (float)$lArray['out_qty']);
                }

                foreach ($netLedgerMap as $ledger) {
                    $netQty = $ledger['net_qty'];
                    if ($netQty <= 0.0001) continue; 

                    if (method_exists($this->mStockledger, 'recordEntry')) {
                        $this->mStockledger->recordEntry([
                            'docdate'  => date('Y-m-d H:i:s'),
                            'docnum'   => $txnNumber,
                            'source'   => 'Edit GRPO',
                            'itemcode' => $ledger['itemcode'],
                            'whscode'  => $ledger['whscode'],
                            'batchnum' => $ledger['batchnum'],
                            'expdate'  => $ledger['expdate'],
                            'areacode' => $ledger['areacode'],
                            'in_qty'   => 0.0000, 
                            'out_qty'  => $netQty,
                            'unitcost' => $ledger['unitcost']
                        ]);
                    }

                    if (method_exists($this->mItemwarehouse, 'reduceStock')) {
                        $this->mItemwarehouse->reduceStock($ledger['itemcode'], $ledger['whscode'], $netQty);
                    }
                    if (method_exists($this->mItembatch, 'reduceBatchStock')) {
                        $this->mItembatch->reduceBatchStock($ledger['itemcode'], $ledger['whscode'], $ledger['areacode'], $ledger['batchnum'], $netQty);
                    }
                    if (method_exists($this->mItemwarehouse, 'addOrdered')) {
                        $this->mItemwarehouse->addOrdered($ledger['itemcode'], $ledger['whscode'], $netQty);
                    }
                }

                if (!empty($oldBaseEntry)) {
                    foreach ($oldDetails as $oldItem) {
                        $oldItemArray = (array)$oldItem;
                        $oldItemCode = $oldItemArray['itemcode'] ?? '';
                        $oldQty      = (float)($oldItemArray['qty'] ?? $oldItemArray['quantity'] ?? 0);
                        $oldBaseLine = $oldItemArray['baseline'] ?? null;

                        if (!empty($oldItemCode) && $oldQty > 0) {
                            DB::table('podetail')->where('docnum', $oldBaseEntry)
                                ->when(!empty($oldBaseLine), function($q) use ($oldBaseLine) {
                                    return $q->where('linenum', $oldBaseLine);
                                }, function($q) use ($oldItemCode) {
                                    return $q->where('itemcode', $oldItemCode);
                                })
                                ->decrement('qtyreceived', $oldQty);
                        }
                    }
                    $this->checkAndUpdatePoStatus(DB::getFacadeRoot(), $oldBaseEntry);
                }
            }

            $items = $postData['items'] ?? $postData['details'] ?? [];
            if (is_string($items)) {
                $items = json_decode($items, true) ?? [];
            }

            $headerData = $this->prepareHeaderData($txnNumber, $postData, $status, $items);
            $detailData = $this->prepareDetailsData($txnNumber, $postData); 
            $newBaseEntry = $headerData['baseentry'] ?? $oldBaseEntry;

            $saveResult = $this->model->saveedit($id, $headerData, $detailData);
            if (is_array($saveResult) && ($saveResult['status'] ?? '') === 'error') {
                throw new \Exception($saveResult['message'] ?? 'Gagal memperbarui data.');
            }

            if ($status === 1) {
                if (method_exists($this, 'handleJournal')) {
                    $this->handleJournal($txnNumber, $status, $headerData, $detailData, 'GRPO');
                } else {
                    if (method_exists($this->mJournal, 'updateJournal')) {
                        $this->mJournal->updateJournal('GRPO', $txnNumber, $headerData, $detailData);
                    } elseif (method_exists($this->mJournal, 'createJournal')) {
                        $this->mJournal->createJournal('GRPO', $txnNumber, $headerData, $detailData);
                    }
                }

                $defaultWhs = $headerData['whscode'] ?? '';
                $rawDocDate = $headerData['docdate'] ?? date('Y-m-d');
                $docDate    = (strlen($rawDocDate) === 10) ? $rawDocDate . ' ' . date('H:i:s') : $rawDocDate;

                foreach ($detailData as $item) {
                    $itemCode = $item['itemcode'] ?? '';
                    $qty      = (float)($item['qty'] ?? $item['quantity'] ?? 0);
                    $unitCost = (float)($item['unitprice'] ?? $item['price'] ?? $item['unitcost'] ?? 0);
                    $whsCode  = $item['whscode'] ?? $defaultWhs;
                    $baseLine = $item['baseline'] ?? null;
                    $batchNum = $item['batchnum'] ?? '';
                    $expDate  = $item['expdate'] ?? null;

                    if (!empty($itemCode) && $qty > 0) {
                        if (method_exists($this->mStockledger, 'recordEntry')) {
                            $this->mStockledger->recordEntry([
                                'docdate'  => $docDate,
                                'docnum'   => $txnNumber,
                                'source'   => 'GRPO',
                                'itemcode' => $itemCode,
                                'whscode'  => $whsCode,
                                'areacode' => null,
                                'batchnum' => $batchNum,
                                'expdate'  => $expDate,
                                'in_qty'   => $qty,
                                'out_qty'  => 0.0000,
                                'unitcost' => $unitCost
                            ]);
                        }

                        if (method_exists($this->mItemwarehouse, 'addStock')) {
                            $this->mItemwarehouse->addStock($itemCode, $whsCode, $qty);
                        }
                        if (method_exists($this->mItembatch, 'addBatchStock')) {
                            $this->mItembatch->addBatchStock($itemCode, $whsCode, null, $batchNum, $expDate, $unitCost, $qty);
                        }
                        if (method_exists($this->mItemwarehouse, 'reduceOrdered')) {
                            $this->mItemwarehouse->reduceOrdered($itemCode, $whsCode, $qty);
                        }

                        if (!empty($newBaseEntry)) {
                            DB::table('podetail')->where('docnum', $newBaseEntry)
                                ->when(!empty($baseLine), function($q) use ($baseLine) {
                                    return $q->where('linenum', $baseLine);
                                }, function($q) use ($itemCode) {
                                    return $q->where('itemcode', $itemCode);
                                })
                                ->increment('qtyreceived', $qty);
                        }
                    }
                }

                if (!empty($newBaseEntry)) {
                    $this->checkAndUpdatePoStatus(DB::getFacadeRoot(), $newBaseEntry);
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

            return $this->ajaxResponse('success', 'Goods Receipt PO berhasil diperbarui.', [
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
                return $this->ajaxResponse('error', 'Data transaksi Goods Receipt PO tidak ditemukan.');
            }

            $currentStatus = (int)($headerArray['status'] ?? 0);
            if ($currentStatus === 1) {
                return $this->ajaxResponse('error', 'Transaksi ini sudah dalam status Active.');
            }

            $realId     = $headerArray['id'] ?? $txnNumber;
            $docNum     = $headerArray['docnum'] ?? $txnNumber;
            $defaultWhs = $headerArray['whscode'] ?? '';
            $docDate    = $headerArray['docdate'] ?? date('Y-m-d H:i:s');
            $baseEntry  = $headerArray['baseentry'] ?? null;

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
                $this->handleJournal($txnNumber, 1, $headerArray, $details, 'GRPO');
            } else {
                if (method_exists($this->mJournal, 'deleteByReference')) {
                    $this->mJournal->deleteByReference($docNum);
                }
                if (method_exists($this->mJournal, 'createJournal')) {
                    $this->mJournal->createJournal('GRPO', $docNum, $headerArray, $details);
                }
            }

            $existingLedgers = DB::table('stockledger')
                ->where('docnum', $docNum)
                ->where('source', 'GRPO')
                ->get();

            $matchedLedgerIds = [];

            foreach ($details as $item) {
                $itemArray = (array)$item;
                $itemCode = $itemArray['itemcode'] ?? '';
                $qty      = (float)($itemArray['qty'] ?? $itemArray['quantity'] ?? 0);
                $unitCost = (float)($itemArray['unitprice'] ?? $itemArray['price'] ?? $itemArray['unitcost'] ?? 0);
                $whsCode  = $itemArray['whscode'] ?? $defaultWhs;
                $baseLine = $itemArray['baseline'] ?? null;
                $batchNum = $itemArray['batchnum'] ?? '';
                $expDate  = $itemArray['expdate'] ?? null;

                if (!empty($itemCode) && $qty > 0) {
                    $foundId = null;
                    foreach ($existingLedgers as $ledger) {
                        $ledgerArray = (array)$ledger;
                        if (!in_array($ledgerArray['id'], $matchedLedgerIds) && 
                            $ledgerArray['itemcode'] === $itemCode && 
                            (($ledgerArray['batchnum'] ?? '') === $batchNum)) {
                            $foundId = $ledgerArray['id'];
                            $matchedLedgerIds[] = $foundId;
                            break;
                        }
                    }

                    if ($foundId) {
                        DB::table('stockledger')
                            ->where('id', $foundId)
                            ->update([
                                'in_qty'   => $qty,
                                'out_qty'  => 0.0000,
                                'unitcost' => $unitCost
                            ]);
                    } else {
                        if (method_exists($this->mStockledger, 'recordEntry')) {
                            $this->mStockledger->recordEntry([
                                'docdate'  => $docDate,
                                'docnum'   => $docNum,
                                'source'   => 'GRPO',
                                'itemcode' => $itemCode,
                                'whscode'  => $whsCode,
                                'areacode' => null,
                                'batchnum' => $batchNum,
                                'expdate'  => $expDate,
                                'in_qty'   => $qty,
                                'out_qty'  => 0.0000,
                                'unitcost' => $unitCost
                            ]);
                        }
                    }

                    if (method_exists($this->mItemwarehouse, 'addStock')) {
                        $this->mItemwarehouse->addStock($itemCode, $whsCode, $qty);
                    }
                    if (method_exists($this->mItembatch, 'addBatchStock')) {
                        $this->mItembatch->addBatchStock($itemCode, $whsCode, null, $batchNum, $expDate, $unitCost, $qty);
                    }
                    if (method_exists($this->mItemwarehouse, 'reduceOrdered')) {
                        $this->mItemwarehouse->reduceOrdered($itemCode, $whsCode, $qty);
                    }

                    if (!empty($baseEntry)) {
                        DB::table('podetail')->where('docnum', $baseEntry)
                            ->when(!empty($baseLine), function($q) use ($baseLine) {
                                return $q->where('linenum', $baseLine);
                            }, function($q) use ($itemCode) {
                                return $q->where('itemcode', $itemCode);
                            })
                            ->increment('qtyreceived', $qty);
                    }
                }
            }

            if (!empty($baseEntry)) {
                $this->checkAndUpdatePoStatus(DB::getFacadeRoot(), $baseEntry);
            }

            DB::commit();

            $this->flashSession([
                'status'  => 'active',
                'fa_id'   => $docNum,
                'fa_name' => $this->getTitle()
            ]);

            return $this->ajaxResponse('success', 'Goods Receipt PO berhasil diaktifkan (Approved).', [
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
                return $this->ajaxResponse('error', 'Data transaksi Goods Receipt PO tidak ditemukan.');
            }

            $oldStatus = (int)($oldHeaderArray['status'] ?? 0);
            if ($oldStatus === 0) {
                return $this->ajaxResponse('error', 'Transaksi ini sudah dalam status Canceled.');
            }

            $oldDetails   = $this->model->get_details($txnNumber);
            $oldBaseEntry = $oldHeaderArray['baseentry'] ?? null;

            if ($oldStatus === 1 && !empty($oldDetails)) {
                $oldLedgers = DB::table('stockledger')->where('docnum', $txnNumber)->get();
                
                $netLedgerMap = [];
                foreach ($oldLedgers as $l) {
                    $lArray = (array)$l;
                    $iCode = $lArray['itemcode'];
                    $wCode = $lArray['whscode'];
                    $bNum  = $lArray['batchnum'] ?? '';
                    $key   = $iCode . '|' . $wCode . '|' . $bNum;
                    
                    if (!isset($netLedgerMap[$key])) {
                        $netLedgerMap[$key] = [
                            'itemcode' => $iCode,
                            'whscode'  => $wCode,
                            'batchnum' => $bNum,
                            'areacode' => $lArray['areacode'] ?? '',
                            'expdate'  => $lArray['expdate'],
                            'unitcost' => (float)$lArray['unitcost'],
                            'net_qty'  => 0
                        ];
                    }
                    $netLedgerMap[$key]['net_qty'] += ((float)$lArray['in_qty'] - (float)$lArray['out_qty']);
                }
                    
                foreach ($netLedgerMap as $ledger) {
                    $netQty = $ledger['net_qty'];
                    if ($netQty <= 0.0001) continue; 

                    if (method_exists($this->mStockledger, 'recordEntry')) {
                        $this->mStockledger->recordEntry([
                            'docdate'  => date('Y-m-d H:i:s'),
                            'docnum'   => $txnNumber,
                            'source'   => 'Cancel GRPO',
                            'itemcode' => $ledger['itemcode'],
                            'whscode'  => $ledger['whscode'],
                            'batchnum' => $ledger['batchnum'],
                            'expdate'  => $ledger['expdate'],
                            'areacode' => $ledger['areacode'],
                            'in_qty'   => 0.0000, 
                            'out_qty'  => $netQty,
                            'unitcost' => $ledger['unitcost']
                        ]);
                    }

                    if (method_exists($this->mItemwarehouse, 'reduceStock')) {
                        $this->mItemwarehouse->reduceStock($ledger['itemcode'], $ledger['whscode'], $netQty);
                    }
                    if (method_exists($this->mItembatch, 'reduceBatchStock')) {
                        $this->mItembatch->reduceBatchStock($ledger['itemcode'], $ledger['whscode'], $ledger['areacode'], $ledger['batchnum'], $netQty);
                    }
                    if (method_exists($this->mItemwarehouse, 'addOrdered')) {
                        $this->mItemwarehouse->addOrdered($ledger['itemcode'], $ledger['whscode'], $netQty);
                    }
                }

                if (!empty($oldBaseEntry)) {
                    foreach ($oldDetails as $oldItem) {
                        $oldItemArray = (array)$oldItem;
                        $oldItemCode = $oldItemArray['itemcode'] ?? '';
                        $oldQty      = (float)($oldItemArray['qty'] ?? $oldItemArray['quantity'] ?? 0);
                        $oldBaseLine = $oldItemArray['baseline'] ?? null;

                        if (!empty($oldItemCode) && $oldQty > 0) {
                            DB::table('podetail')->where('docnum', $oldBaseEntry)
                                ->when(!empty($oldBaseLine), function($q) use ($oldBaseLine) {
                                    return $q->where('linenum', $oldBaseLine);
                                }, function($q) use ($oldItemCode) {
                                    return $q->where('itemcode', $oldItemCode);
                                })
                                ->decrement('qtyreceived', $oldQty);
                        }
                    }
                    $this->checkAndUpdatePoStatus(DB::getFacadeRoot(), $oldBaseEntry);
                }
            }

            $userCode = (int) (Session::get('usercode') ?? 1);
            if (method_exists($this->model, 'cancelDocument')) {
                $this->model->cancelDocument($oldHeaderArray['id'] ?? $id, $userCode);
            } else {
                $this->model->update($oldHeaderArray['id'] ?? $id, [
                    'status'       => 0,
                    'canceleddate' => date('Y-m-d H:i:s'),
                    'canceledby'   => $userCode
                ]);
            }

            if (method_exists($this->mJournal, 'updateStatusByReference')) {
                $this->mJournal->updateStatusByReference($txnNumber, 0);
            }

            DB::commit();

            $this->flashSession([
                'status'  => 'savecancel',
                'fa_id'   => $txnNumber,
                'fa_name' => $this->getTitle()
            ]);

            $alertMessage = '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                          . 'Nomor transaksi <strong>' . e($txnNumber) . '</strong> berhasil dibatalkan (Cancel).'
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

    protected function prepareDetailsOnCreate(array $items, string $txnNumber): array
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
                'docnum'      => $txnNumber,
                'linenum'     => $index + 1,
                'baseline'    => $item['baseline'] ?? $item['linenum'] ?? ($index + 1),
                'itemcode'    => $item['itemcode'] ?? '',
                'uomcode'     => $item['uomcode'] ?? '',
                'qty'         => $qty,
                'unitprice'   => $unitprice,
                'discount'    => $discount,
                'subtotal'    => $itemSubtotal,
                'batchnum'    => $item['batchnum'] ?? '',
                'expdate'     => !empty($item['expdate']) ? $item['expdate'] : null,
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
        return $this->prepareDetailsOnCreate($items, $txnNumber);
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

    protected function validateTransaction(array $postData, ?string $currentDocNum = null): ?string
    {
        if (empty($postData['cardcode'])) {
            return 'Supplier / Vendor wajib dipilih.';
        }
        if (empty($postData['whscode'])) {
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
                $poDetail = DB::table('podetail')->where('docnum', $baseEntry)
                    ->when(!empty($baseLine), function($q) use ($baseLine) {
                        return $q->where('linenum', $baseLine);
                    }, function($q) use ($itemCode) {
                        return $q->where('itemcode', $itemCode);
                    })->first();
                $poDetailArray = json_decode(json_encode($poDetail), true);

                if (!$poDetailArray) {
                    return "Baris ke-{$rowNum}: Item {$itemCode} tidak ditemukan pada PO referensi ({$baseEntry}).";
                }

                $qtyPo       = (float)($poDetailArray['qty'] ?? 0);
                $qtyReceived = (float)($poDetailArray['qtyreceived'] ?? 0);

                $mapKey = !empty($baseLine) ? $baseLine : $itemCode;
                $oldQty = $oldDetailsMap[$mapKey] ?? 0;
                $effectiveReceived = $qtyReceived - $oldQty;

                $remainingQty = $qtyPo - $effectiveReceived;

                if ($qtyInput > $remainingQty) {
                    return "Baris ke-{$rowNum}: Qty GRPO ({$qtyInput}) melebihi sisa Qty PO yang tersedia ({$remainingQty}) untuk item {$itemCode}.";
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

        $listData = $this->mPurchaseorder->getOpen();

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

        $poData = $this->mPurchaseorder->getWithDetails($docnum);

        if (!$poData || (isset($poData['header']['status']) && (int)$poData['header']['status'] !== 1)) {
            return response()->json([
                'status'  => false, 
                'message' => 'Data Purchase Order tidak ditemukan atau status sudah tidak aktif.'
            ]);
        }

        $header = $poData['header'];
        $header['baseentry'] = $header['docnum'] ?? $docnum;
        $docDate = date('Y-m-d'); 

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
        if (!empty($poData['details'])) {
            foreach ($poData['details'] as $row) {
                $rowArray = (array)$row;
                $qtyPo        = (float)($rowArray['qty'] ?? 0);
                $qtyReceived  = (float)($rowArray['qtyreceived'] ?? 0);
                $remainingQty = $qtyPo - $qtyReceived; 

                if ($remainingQty > 0) {
                    $itemCode  = $rowArray['itemcode'] ?? '';
                    $unitprice = (float)($rowArray['unitprice'] ?? 0);
                    $discount  = (float)($rowArray['discount'] ?? 0);
                    $subtotal  = ($remainingQty * $unitprice) - (($discount / 100) * $remainingQty * $unitprice);

                    $itemMaster = $this->mItemmaster->where('itemcode', $itemCode)->first();
                    $itemMasterArray = is_object($itemMaster) ? (array)$itemMaster : $itemMaster;
                    $monthED    = (int)($itemMasterArray['monthED'] ?? 0);

                    $itemName   = $itemMasterArray['itemname'] ?? '';
                    
                    $expDate  = date('Y-m-d', strtotime("+{$monthED} months", strtotime($docDate)));
                    $batchNum = $this->generateBatchNumber($itemCode, $docDate);

                    $processedDetails[] = array_merge($rowArray, [
                        'baseline' => $rowArray['linenum'] ?? 1, 
                        'qty'      => $remainingQty,         
                        'subtotal' => $subtotal,
                        'batchnum' => $batchNum,
                        'expdate'  => $expDate,
                        'itemname' => $itemName
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
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\MJournal;

class Reportjournal extends BaseReportController
{
    protected MJournal $mJournal;

    protected bool $showShow      = true;
    protected bool $showReset     = true;
    protected bool $showStartDate = true;
    protected bool $showEndDate   = true;

    public function __construct()
    {
        $this->mJournal = new MJournal();
        $this->model = $this->mJournal;
    }

    protected function getTitle(): string { return 'Journal'; }
    protected function getBaseRoute(): string { return 'reportjournal'; }

    protected function getTableColumns(): array 
    { 
        return ['No', 'Jurnal', 'Tanggal', 'Referensi', 'Sumber', 'Akun', 'Nama Akun', 'Debit', 'Kredit', 'Keterangan']; 
    }

    protected function getExtraFilterFields(): array
    {
        return [
            [
                'name'    => 'source',
                'label'   => 'Sumber Dokumen (Source Type)',
                'type'    => 'select',
                'options' => [
                    ''       => '-- Semua Sumber --',
                    'GRPO'   => 'Goods Receipt PO (GRPO)',
                    'APINV'  => 'AP Invoice (APINV)',
                    'DO'     => 'Delivery Order (DO)',
                    'ARINV'  => 'AR Invoice (ARINV)',
                    'OUTPAY' => 'Outgoing Payment (OUTPAY)',
                    'INPAY'  => 'Incoming Payment (INPAY)',
                    'IP'     => 'Issue for Production (IP)',
                    'RP'     => 'Receipt from Production (RP)',
                ]
            ]
        ];
    }

    public function ajax_list(Request $request): JsonResponse
    {
        if (!$request->ajax()) {
            return response()->json([], 404);
        }

        $list       = $this->mJournal->get_datatables_report();
        $data       = [];
        $no         = (int)($request->post('start') ?? 0);

        foreach ($list as $item) {
            $docNum        = $this->getFieldValue($item, 'docnum');
            $docDate       = $this->getFieldValue($item, 'docdate');
            $refNum        = $this->getFieldValue($item, 'refnum');
            $source        = $this->getFieldValue($item, 'source');
            $acctCode      = $this->getFieldValue($item, 'acctcode');
            $acctName      = $this->getFieldValue($item, 'acctname');
            $debit         = (float)$this->getFieldValue($item, 'debit');
            $credit        = (float)$this->getFieldValue($item, 'credit');
            $remark        = $this->getFieldValue($item, 'remark');

            $row = [
                ++$no,
                e($docNum),
                e($docDate),
                e($refNum),
                e($source),
                e($acctCode),
                e($acctName),
                number_format($debit, 2, ',', '.'),
                number_format($credit, 2, ',', '.'),
                e($remark)
            ];

            $data[] = $row;
        }

        return response()->json([
            "draw"            => (int)$request->post('draw'),
            "recordsTotal"    => (int)$this->mJournal->count_all(),
            "recordsFiltered" => (int)$this->mJournal->count_filtered(),
            "data"            => $data
        ]);
    }
}
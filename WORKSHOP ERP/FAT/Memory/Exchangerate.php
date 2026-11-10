<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\MExchangerate;
use App\Models\MCurrency;

class Exchangerate extends BaseTransactionController
{
    protected $model;    
    protected MCurrency $mCurrency;
    
    protected bool $showRemark = false;
    protected bool $showSummary = false;
    protected bool $showBtndraft = true;

    public function __construct()
    {
        $this->model     = new MExchangerate();
        $this->mCurrency = new MCurrency();
    }

    protected function getTitle(): string {return 'Exchange Rate';}
    protected function getBaseRoute(): string {return 'exchangerate';}
    protected function getTxnNumberField(): string {return 'docnum';}
    protected function getDateField(): string {return 'docdate';}
    protected function getTableColumns(): array {return ['No', 'Dokumen', 'Tanggal', 'Status', 'Aksi'];}

    protected function generateTxnNumber(): string 
    { 
        return method_exists($this->model, 'generateNumber') ? $this->model->generateNumber() : 'ER-' . date('ymdHis'); 
    }

    protected function getDetailFields(): array
    { 
        return [
            [
                'name'        => 'currcode',
                'label'       => 'Mata Uang',
                'type'        => 'select',
                'options'     => $this->formatOptions($this->mCurrency->getActiveCurrencies(), 'currcode', 'currname'),
                'required'    => true,
                'placeholder' => 'Masukkan Mata Uang'
            ],
            [
                'name'        => 'rate',
                'label'       => 'Nilai Kurs',
                'type'        => 'number',
                'class'       => 'text-end',
                'required'    => true,
                'min'         => 0
            ],
            [
                'name'        => 'startdate',
                'label'       => 'Tanggal Mulai',
                'type'        => 'date',
                'class'       => 'text-end',
                'required'    => true
            ],
            [
                'name'        => 'enddate',
                'label'       => 'Tanggal Berakhir',
                'type'        => 'date',
                'class'       => 'text-end',
                'required'    => true,
            ]
        ];
    }

    protected function prepareHeaderOnCreate(array $postData, int $status, Request $request): array
    {
        $header = parent::prepareHeaderOnCreate($postData, $status, $request ?? request());
        $header['docdate'] = !empty($header['docdate']) ? $header['docdate'] : date('Y-m-d H:i:s');
        
        return $header;
    }

    protected function prepareDetailsOnCreate(array $items, string $txnNumber, Request $request): array
    {
        $detailsData = [];
        $today       = date('Y-m-d');

        foreach ($items as $index => $item) {
            $detailsData[] = [
                'docnum'      => $txnNumber,
                'linenum'     => $index + 1,
                'currcode'    => $item['currcode'] ?? '',
                'rate'        => $item['rate'] ?? 0,
                'startdate'   => !empty($item['startdate']) ? $item['startdate'] : $today,
                'enddate'     => !empty($item['enddate']) ? $item['enddate'] : $today,
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
            return 'Detail item kurs tidak boleh kosong! Mohon masukkan minimal 1 mata uang.';
        }

        foreach ($items as $index => $item) {
            $rowNum = $index + 1;

            $currCode  = trim($item['currcode'] ?? '');
            $startDate = trim($item['startdate'] ?? '');
            $endDate   = trim($item['enddate'] ?? '');
            $rate      = $item['rate'] ?? '';

            if (empty($currCode)) {
                return "Mata Uang pada baris ke-{$rowNum} belum dipilih.";
            }
            if ($rate === '') {
                return "Kurs pada baris ke-{$rowNum} harus diisi.";
            }
            if ((float)$rate < 0) {
                return "Kurs pada baris ke-{$rowNum} tidak boleh minus.";
            }
            if (empty($startDate) || empty($endDate)) {
                return "Tanggal Mulai dan Akhir pada baris ke-{$rowNum} harus diisi.";
            }
            if (strtotime($startDate) > strtotime($endDate)) {
                return "Tanggal Mulai pada baris ke-{$rowNum} tidak boleh melebihi Tanggal Berakhir.";
            }

            $currName = $this->mCurrency->where('currcode', $currCode)->value('currname') ?? $currCode;
            $currLabel = "{$currCode} - {$currName}";

            for ($i = 0; $i < $index; $i++) {
                $prevItem = $items[$i];
                $prevCurr = trim($prevItem['currcode'] ?? '');
                
                if ($prevCurr === $currCode) {
                    $prevStart = trim($prevItem['startdate'] ?? '');
                    $prevEnd   = trim($prevItem['enddate'] ?? '');

                    if ($startDate <= $prevEnd && $endDate >= $prevStart) {
                        $prevRow = $i + 1;
                        return "Mata Uang '{$currLabel}' pada baris ke-{$rowNum} ({$startDate} s/d {$endDate}) bertabrakan/beririsan dengan baris ke-{$prevRow} ({$prevStart} s/d {$prevEnd}).";
                    }
                }
            }

            $conflictDocNum = $this->model->getConflictingDocNum($currCode, $startDate, $endDate, $currentDocNum);
            if ($conflictDocNum) {
                return "Mata Uang '{$currLabel}' pada rentang tanggal ({$startDate} s/d {$endDate}) sudah terdaftar atau beririsan dengan dokumen nomor: {$conflictDocNum}.";
            }
        }

        return null;
    }

    protected function getExcelMapping(): array
    {
        return [
            'Currency'         => 'currcode',
            'Kurs'             => 'rate',
            'Tanggal Mulai'    => 'startdate',
            'Tanggal Berakhir' => 'enddate',
        ];
    }

    public function gettemplates(): array
    {
        return [
            'default' => 'exchangerate.xlsx',
        ];
    }
}
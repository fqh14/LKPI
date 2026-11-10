<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\MOutgoingpayment;
use App\Models\MApinvoice;

class Outgoingpayment extends BasePaymentController
{
    protected $model;
    protected MApinvoice $mApinvoice;

    public function __construct()
    {
        $this->model      = new MOutgoingpayment();
        $this->mApinvoice = new MApinvoice();
    }

    protected function getTitle(): string { return 'Outgoing Payment / Pembayaran Keluar'; }
    protected function getBaseRoute(): string { return 'outgoingpayment'; }
    protected function getCardGroupCode(): string { return 'VS'; }
    
    protected function generateTxnNumber(): string 
    { 
        return method_exists($this->model, 'generateNumber') ? $this->model->generateNumber() : 'OP-' . date('ymdHis'); 
    }

    protected function getTableColumns(): array 
    { 
        return ['No', 'Nomor', 'Tanggal', 'Supplier', 'Tipe', 'Total', 'Status', 'Aksi']; 
    }

    protected function getInvoiceModel() { return $this->mApinvoice; }
    protected function getInvoiceTableName(): string { return 'apinvoiceheader'; }
    protected function getDetailTableName(): string { return 'outgoingpaymentdetail'; }
    protected function getSourceType(): string { return 'OUTPAY'; }
    protected function getBaseTypeName(): string { return 'APINVOICE'; }
    protected function getPartnerLabel(): string { return 'Supplier / Vendor'; }
    protected function getInvoiceLabel(): string { return 'No AP Invoice'; }
}
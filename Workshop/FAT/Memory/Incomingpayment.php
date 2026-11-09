<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MIncomingpayment;
use App\Models\MArinvoice;

class Incomingpayment extends BasePaymentController
{
    protected $model;
    protected MArinvoice $mArinvoice;

    public function __construct()
    {
        $this->model      = new MIncomingpayment();
        $this->mArinvoice = new MArinvoice();
    }

    protected function getTitle(): string { return 'Incoming Payment / Penerimaan'; }
    protected function getBaseRoute(): string { return 'incomingpayment'; }
    protected function getCardGroupCode(): string { return 'VC'; }
    
    protected function generateTxnNumber(): string 
    { 
        return method_exists($this->model, 'generateNumber') ? $this->model->generateNumber() : 'IP-' . date('ymdHis'); 
    }

    protected function getTableColumns(): array 
    { 
        return ['No', 'Nomor', 'Tanggal', 'Customer', 'Tipe', 'Total', 'Status', 'Aksi']; 
    }

    protected function getInvoiceModel() { return $this->mArinvoice; }
    protected function getInvoiceTableName(): string { return 'arinvoiceheader'; }
    protected function getDetailTableName(): string { return 'incomingpaymentdetail'; }
    protected function getSourceType(): string { return 'INPAY'; }
    protected function getBaseTypeName(): string { return 'ARINVOICE'; }
    protected function getPartnerLabel(): string { return 'Customer'; }
    protected function getInvoiceLabel(): string { return 'No AR Invoice'; }
}
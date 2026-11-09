<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Session;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Dompdf\Dompdf;
use Dompdf\Options;

abstract class BaseReportController extends BaseController
{
    protected $model;
    protected string $moduleCode = '';

    protected bool $showShow      = true;
    protected bool $showReset     = true;
    protected bool $showStartDate = true;
    protected bool $showEndDate   = true;

    abstract protected function getTitle(): string;
    abstract protected function getBaseRoute(): string;
    abstract protected function getTableColumns(): array;

    protected function getExtraFilterFields(): array { return []; }

    protected function guardAuthenticatedView()
    {
        if (!Session::has('username')) {
            return redirect()->to(url('/'));
        }
        return null;
    }

    protected function getFieldValue($item, string $field)
    {
        return is_object($item) ? ($item->{$field} ?? null) : ($item[$field] ?? null);
    }

    protected function ajaxResponse($status, string $message, array $extra = []): JsonResponse
    {
        $statusValue = is_bool($status) ? ($status ? 'success' : 'error') : $status;

        return response()->json(array_merge([
            'status'  => $statusValue,
            'message' => $message    
        ], $extra));
    }

    protected function formatOptions($list, string $codeKey, string $nameKey): array
    {
        if (empty($list)) {
            return [];
        }

        if ($list instanceof \Illuminate\Support\Collection) {
            $list = $list->toArray();
        }

        $options = [];
        foreach ($list as $item) {
            $itemObj = (object)$item;
            $code    = $itemObj->{$codeKey} ?? '';
            $name    = $itemObj->{$nameKey} ?? '';

            if ($code !== '') {
                $options[$code] = $name;
            }
        }
        return $options;
    }

    protected function formatOptionsCodeName($list, string $codeKey, string $nameKey): array
    {
        if (empty($list)) {
            return [];
        }

        if ($list instanceof \Illuminate\Support\Collection) {
            $list = $list->toArray();
        }

        $options = [];
        foreach ($list as $item) {
            $itemObj = (object)$item;
            $code    = $itemObj->{$codeKey} ?? '';
            $name    = $itemObj->{$nameKey} ?? '';

            if ($code !== '') {
                $options[$code] = $code . ' - ' . $name;
            }
        }
        return $options;
    }

    public function index(Request $request)
    {
        if ($guard = $this->guardAuthenticatedView()) return $guard;

        return view('reports.index', [
            'title'          => $this->getTitle(),
            'base_route'     => $this->getBaseRoute(),
            'table_columns'  => $this->getTableColumns(),
            'extra_filters'  => $this->getExtraFilterFields(),
            'usercode'       => Session::get('usercode'),
            'name'           => Session::get('name'),
            'show_show'      => $this->showShow ?? true,
            'show_reset'     => $this->showReset ?? true,
            'show_startdate' => $this->showStartDate ?? true,
            'show_enddate'   => $this->showEndDate ?? true,
        ]);
    }

    protected function applyReportFilters($builder, Request $request): void
    {
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        if (!empty($startDate) && !empty($endDate)) {
            $builder->where('a.docdate', '>=', $startDate . ' 00:00:00');
            $builder->where('a.docdate', '<=', $endDate . ' 23:59:59');
        }

        $extraFields = $this->getExtraFilterFields();
        foreach ($extraFields as $field) {
            $fieldName = $field['name'] ?? '';
            if (!empty($fieldName)) {
                $val = $request->input($fieldName);
                if ($val !== null && $val !== '') {
                    $builder->where("a.{$fieldName}", $val);
                }
            }
        }
    }

    public function exportexcel(Request $request)
    {
        if ($guard = $this->guardAuthenticatedView()) return $guard;

        if (method_exists($this->model, 'getReportData')) {
            $data = $this->model->getReportData($request);
        } else {
            $builder = \Illuminate\Support\Facades\DB::table('journalheader as a');
            $this->applyReportFilters($builder, $request);
            $data = $builder->get()->map(fn($item) => (array)$item)->toArray();
        }

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        
        $columns = $this->getTableColumns();
        
        $colIndex = 1;
        $exportColumns = [];
        foreach ($columns as $col) {
            if (in_array(strtolower($col), ['no', 'aksi', 'action'], true)) continue;
            
            $exportColumns[] = $col;
            $cellCoordinate  = Coordinate::stringFromColumnIndex($colIndex) . '1';
            $sheet->setCellValue($cellCoordinate, $col);
            $colIndex++;
        }

        $rowNum = 2;
        foreach ($data as $rowItem) {
            $colIndex = 1;
            $rowArray = (array)$rowItem;
            $values = array_values($rowArray);

            foreach ($exportColumns as $colName) {
                $cellCoordinate = Coordinate::stringFromColumnIndex($colIndex) . $rowNum;
                $val = $values[$colIndex - 1] ?? '';
                
                $sheet->setCellValue($cellCoordinate, $val);
                $colIndex++;
            }
            $rowNum++;
        }

        $filename = strtolower(str_replace(' ', '_', $this->getTitle())) . '_' . date('YmdHis') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function exportpdf(Request $request)
    {
        if ($guard = $this->guardAuthenticatedView()) return $guard;

        $data = [];
        if (method_exists($this->model, 'getReportData')) {
            $data = $this->model->getReportData($request);
        } 

        $html = view('reports.print_pdf', [
            'title'   => $this->getTitle(),
            'columns' => $this->getTableColumns(),
            'data'    => $data
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = strtolower(str_replace(' ', '_', $this->getTitle())) . '_' . date('YmdHis') . '.pdf';
        $dompdf->stream($filename, ["Attachment" => false]);
    }
}
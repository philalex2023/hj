<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx\Workbook;

class Export implements FromArray, WithHeadings, WithMapping, WithEvents
{
    use Exportable;
    protected array $data;
    protected array $header;
    protected array $columns;
    private string $fileName='demo.xlsx';

    public function __construct(array $data)
    {
        //实例化该脚本的时候，需要传入要导出的数据
        $this->data = $data['data'];
        $this->header = $data['header'];
        $this->columns = $data['fields'];
    }

    /**
     * 指定excel的表头
     * @return array
     * @forexample ['ID','企业名称',]
     */
    public function headings(): array
    {
        return $this->header;
    }

    /**
     * // 返回的数据
     * @return array
     */
    public function array(): array
    {
        return $this->data;
    }

    public function map($row): array
    {
        foreach ($this->columns as $column){
            $res[] = $row[$column];
        }
        return $res??[];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                // ... 此处你可以任意格式化
                //设置列宽
                $event->sheet->getDelegate()->getColumnDimension('A')->setWidth(30);
            },
        ];
    }
}

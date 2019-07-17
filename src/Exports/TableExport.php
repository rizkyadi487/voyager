<?php

namespace TCG\Voyager\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use TCG\Voyager\Models\DataRow;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Voyager;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;


class TableExport implements FromQuery, WithHeadings, ShouldAutoSize
{
    use Exportable;

    public function __construct(string $slug)
    {
        $this->slug = $slug;
        $this->dataType = DataType::where('slug', '=', $slug)->first();

        if (strlen($this->dataType->model_name) != 0) {
            $this->dataRow = DataRow::where('data_type_id', '=', $this->dataType->id)
                ->where('type', '<>', 'hidden')
                ->select('field')
                ->orderBy('order')
                ->pluck('field');
            $this->dataHeader = DataRow::where('data_type_id', '=', $this->dataType->id)
                ->where('type', '<>', 'hidden')
                ->selectRaw('upper(display_name) display_name')
                ->orderBy('order')
                ->pluck('display_name');
        } else {
            $this->dataRow = [];
            $this->dataHeader = [];
        }
    }

    public function headings(): array
    {
        return $this->dataHeader->toArray();
    }

    public function query()
    {
        $model = app($this->dataType->model_name);
        $sel = $this->dataRow->values()->all();
        $query = $model::select($sel);

        return $query;
    }
}

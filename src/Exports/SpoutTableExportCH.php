<?php

namespace TCG\Voyager\Exports;

use TCG\Voyager\Models\DataRow;
use TCG\Voyager\Models\DataType;
use Box\Spout\Writer\WriterFactory;
use Box\Spout\Common\Type;
use Box\Spout\Writer\Style\StyleBuilder;
use DB;

class SpoutTableExportCH
{
    public function __construct(string $slug, object $search)
    {
        $this->slug = $slug;
        $this->search = $search;
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

    public function download(string $fileName = null)
    {
        $writer = WriterFactory::create(Type::XLSX);
        $model = app($this->dataType->model_name);
        $table_name = with(new $model)->getTable();
        $connection_name = with(new $model)->getConnectionName();

        $writer->openToBrowser($fileName);

        $headerStyle = (new StyleBuilder())
            ->setFontBold()
            ->setFontSize(10)
            ->setFontName('Calibri')
            ->setBackgroundColor('8b919b')
            ->setShouldWrapText(false)
            ->build();

        $rowStyle = (new StyleBuilder())
            ->setShouldWrapText(false)
            ->setFontName('Calibri')
            ->setFontSize(10)
            ->build();

        $writer->addRowWithStyle($this->dataHeader->toArray(), $headerStyle);

        $i=0;
        do {
            $i++;
            if($this->search->key!=null){
                $hasilnya = DB::connection($connection_name)->table($table_name)->where($this->search->key,$this->search->value)->simplePaginate(10000,['*'],'page',$i);
            }else{
                $hasilnya = DB::connection($connection_name)->table($table_name)->simplePaginate(10000,['*'],'page',$i);
            }
            
            foreach ($hasilnya as $idx => $data) {
                $writer->addRowWithStyle(array_values((array)($data)), $rowStyle);
            }
        } while ($hasilnya->hasMorePages());

        $writer->close();
    }

    public function headings()
    {
        return $this->dataHeader->toArray();
    }
}

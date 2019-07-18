<?php

namespace TCG\Voyager\Exports;

use TCG\Voyager\Models\DataRow;
use TCG\Voyager\Models\DataType;
use Box\Spout\Writer\WriterFactory;
use Box\Spout\Common\Type;
use Box\Spout\Writer\Style\StyleBuilder;

class SpoutTableExport
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
        $datas_chunk = $this->query();

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

        $writer->addRowWithStyle($this->headings(), $headerStyle);

        $datas_chunk->chunk(10000, function ($datas) use ($writer, $rowStyle) {
            foreach ($datas as $idx => $data) {
                $writer->addRowWithStyle($data->toArray(), $rowStyle);
            }
        });

        $writer->close();
    }

    public function headings()
    {
        return $this->dataHeader->toArray();
    }

    public function query()
    {
        $search = $this->search;

        $model = app($this->dataType->model_name);
        $sel = $this->dataRow->values()->all();

        // must have PK column
        $query = $model::select($sel);

        if ($search->value && $search->key && $search->filter) {
            $search_filter = ($search->filter == 'equals') ? '=' : 'LIKE';
            $search_value = ($search->filter == 'equals') ? $search->value : '%'.$search->value.'%';
            $query->where($search->key, $search_filter, $search_value);
        }

        return $query;
    }
}

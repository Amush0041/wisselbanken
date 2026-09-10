<?php

namespace App\DataTables;

use App\Models\ProductPricing;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class ProductPricingDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
        ->addColumn('color_name', function ($row) {
            return $row->color ? $row->color->name : '';
        })
        ->addColumn('product_name', function ($row) {
            return $row->product ? $row->product->name : '';
        })
        ->addColumn('manufacturer_name', function ($row) { 
            return $row->manufacturer ? $row->manufacturer->name : '';
        })->editColumn('created_at', function ($row) {
            return $row->created_at ? $row->created_at->format('d-m-Y H:i') : '-';
        }) 
        ->addColumn('action', function ($row) { 
            return '<div class="btn-group" role="group">
                        <a href="javascript:void(0)"  class="btn btn-sm btn-primary editProductPricing"  data-toggle="tooltip" data-id="' . $row->id . '" data-original-title="Edit">
                            <i class="fas fa-edit"></i> 
                        </a> 
                        <button class="btn btn-sm btn-danger deleteProductPricing" data-id="' . $row->id . '" data-toggle="tooltip" title="Delete">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>';
        }) 
        ->rawColumns(['status','color_name','product_name','manufacturer_name', 'created_at','action'])
        ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(ProductPricing $model): QueryBuilder
    {
        return $model->newQuery()->with([
            'color',
            'product',
            'manufacturer',
        ]);
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
        ->setTableId('product-pricing-table')
        ->columns($this->getColumns())
        ->minifiedAjax()
        //->dom('Bfrtip')
        ->orderBy(0,'Asc')
        ->selectStyleSingle()
        ->dom('<"row"<"col-md-6"l><"col-md-6 d-flex justify-content-end"f>>rtip') // Custom layout
        ->buttons([
            Button::make('excel'),
            Button::make('csv'),
            Button::make('pdf'),
            Button::make('print'),
        ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('id')->title('ID'), 
            Column::make('manufacturer_name')->title('Manufacturer'), 
            Column::make('product_name')->title('Product'), 
            Column::make('part_name')->title('Part Name'), 
            Column::make('part_number')->title('Part Number'), 
            Column::make('color_name')->title('Color'), 
            Column::make('price')->title('Price'), 
            Column::make('unit_price')->title('Unit Price'), 
            Column::make('quantity')->title('Quantity'),  
            Column::computed('action')
                  ->exportable(false)
                  ->printable(false)
                  ->width(100)
                  ->addClass('text-center'),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'ProductPricing_' . date('YmdHis');
    }
}

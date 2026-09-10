<?php

namespace App\DataTables;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class ProductDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
        ->addColumn('status', function ($row) {
            return $row->status == 1  
                ? '<span class="badge bg-success">Active</span>' 
                : '<span class="badge bg-danger">In Active</span>';
        })->editColumn('created_at', function ($row) {
            return $row->created_at ? $row->created_at->format('d-m-Y H:i') : '';
        })
        ->addColumn('feature_image', function ($row) {
            $imageUrl = $row->feature_image 
                ? asset($row->feature_image) 
                : asset('demo.jpg'); // Path to default image
        
            $errorImage = asset('demo.jpg'); // Path to error image
        
            return '<img src="' . $imageUrl . '" onerror="this.onerror=null;this.src=\'' . $errorImage . '\';" alt="Feature Image" width="50" height="50" />';
        })
        ->addColumn('division', function ($row) {
            return $row->division ? $row->division->code : '';
        })
        ->addColumn('specification', function ($row) {
            return $row->specification ? $row->specification->specification_number : '';
        })
        ->addColumn('material_type', function ($row) {
            return $row->specification ? $row->specification->material_type : '';
        })
        ->addColumn('manufacturer', function ($row) {
            return $row->manufacturer ? $row->manufacturer->name : '';
        })
        ->addColumn('action', function ($row) {
            $editUrl = route('products.edit', $row->id);
            $upload  = route('product-files.show',$row->id);
            return '<div class="btn-group" role="group">
                        <a href="' . $editUrl . '" class="btn btn-sm btn-primary" data-toggle="tooltip" title="Edit">
                            <i class="fas fa-edit"></i> 
                        </a>
                         <a href="' . $upload . '" class="btn btn-sm btn-success" data-toggle="tooltip" title="File Upload">
                            <i class="fas fa-file-upload"></i> 
                        </a>
                        <button class="btn btn-sm btn-danger deleteProduct" data-id="' . $row->id . '" data-toggle="tooltip" title="Delete">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>';
        })
        ->rawColumns(['status', 'feature_image','division','specification','material_type','manufacturer','created_at','action'])
        ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(Product $model): QueryBuilder
    {
        return $model->newQuery()
        ->with([
            'division',
            'specification',
            'manufacturer',
            'productVariation', 
        ]);
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('product-table')
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
            Column::make('feature_image')->title('Image'),
            Column::make('name')->title('Name'),
            Column::make('division')->title('Division code'),
            Column::make('specification')->title('Specification'),
            Column::make('material_type')->title('Material Type'),
            Column::make('status')->title('Status'), 
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
        return 'Product_' . date('YmdHis');
    }
}

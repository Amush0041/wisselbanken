<?php

namespace App\DataTables;

use App\Models\Division;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class DivisionDataTable extends DataTable
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
            })
            ->addColumn('action', function ($row) { 
                return '<div class="btn-group" role="group">
                            <a href="javascript:void(0)"  class="btn btn-sm btn-primary editDivision"  data-toggle="tooltip" data-id="' . $row->id . '" data-original-title="Edit">
                                <i class="fas fa-edit"></i> 
                            </a> 
                            <button class="btn btn-sm btn-danger deleteDivision" data-id="' . $row->id . '" data-toggle="tooltip" title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>';
            }) 
            ->rawColumns(['status', 'action'])
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(Division $model): QueryBuilder
    {
        return $model->newQuery();
    }


    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('division-table')
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

            Column::make('code')->title('Code'),
            Column::make('name')->title('Division Name'),
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
        return 'Division_' . date('YmdHis');
    }
}

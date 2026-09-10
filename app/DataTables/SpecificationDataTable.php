<?php

namespace App\DataTables;
 
use App\Models\Specification;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class SpecificationDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('division', function ($specification) {
                return $specification->division 
                    ? $specification->division->code . ' - ' . $specification->division->name 
                    : 'N/A';
            })
            ->addColumn('status', function ($row) {
                return $row->status == 1  
                    ? '<span class="badge bg-success">Active</span>' 
                    : '<span class="badge bg-danger">Inactive</span>';
            })->addColumn('action', function ($row) { 
                return '<div class="btn-group" role="group">
                            <a href="javascript:void(0)"  class="btn btn-sm btn-primary editSpecification"  data-toggle="tooltip" data-id="' . $row->id . '" data-original-title="Edit">
                                <i class="fas fa-edit"></i> 
                            </a> 
                            <button class="btn btn-sm btn-danger deleteSpecification" data-id="' . $row->id . '" data-toggle="tooltip" title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>';
            }) 
            ->rawColumns(['status', 'action']); // Ensure HTML is rendered correctly
    }


    /**
     * Get the query source of dataTable.
     */
    public function query(Specification $model): QueryBuilder
    {
        return $model->newQuery()->with('division');
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('specification-table')
                    ->columns($this->getColumns())
                    ->minifiedAjax()
                    //->dom('Bfrtip')
                    ->orderBy(1)
                    ->selectStyleSingle()
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

        Column::make('specification_number')->title('Specification Number')->addClass('text-center'),
        Column::computed('division')->title('Division (Code - Name)'),
        Column::make('material_type')->title('Material Type')->addClass('text-center'),
        Column::computed('status')->title('Status')->exportable(false)->printable(false),
        Column::computed('action')->exportable(false)->printable(false)->addClass('text-center'),
    ];
}


    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Specification_' . date('YmdHis');
    }
}

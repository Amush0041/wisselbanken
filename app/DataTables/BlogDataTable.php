<?php

namespace App\DataTables;
 
use App\Models\Blog;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class BlogDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('featured_image', function ($blog) {
                if ($blog->featured_image && file_exists(storage_path('app/public/' . $blog->featured_image))) {
                    $imagePath = asset('storage/' . $blog->featured_image);
                    return '<img src="' . $imagePath . '" alt="' . htmlspecialchars($blog->title, ENT_QUOTES) . '" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; display: block; margin: 0 auto;">';
                }
                return '<span class="badge bg-secondary">No Image</span>';
            })
            ->addColumn('status', function ($row) {
                return $row->status == 1  
                    ? '<span class="badge bg-success">Active</span>' 
                    : '<span class="badge bg-danger">Inactive</span>';
            })
            ->addColumn('action', function ($row) { 
                return '<div class="btn-group" role="group">
                            <a href="javascript:void(0)"  class="btn btn-sm btn-primary editBlog"  data-toggle="tooltip" data-id="' . $row->id . '" data-original-title="Edit">
                                <i class="fas fa-edit"></i> 
                            </a> 
                            <button class="btn btn-sm btn-danger deleteBlog" data-id="' . $row->id . '" data-toggle="tooltip" title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>';
            }) 
            ->rawColumns(['featured_image', 'status', 'action']);
    }

    public function query(Blog $model): QueryBuilder
    {
        return $model->newQuery();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('blog-table')
                    ->columns($this->getColumns())
                    ->minifiedAjax()
                    ->orderBy(1)
                    ->selectStyleSingle()
                    ->buttons([
                        Button::make('excel'),
                        Button::make('csv'),
                        Button::make('pdf'),
                        Button::make('print'),
                    ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('id')->title('ID')->addClass('text-center'),
            Column::make('featured_image')->title('Image')->exportable(false)->printable(false)->addClass('text-center'),
            Column::make('title')->title('Title'),
            Column::make('slug')->title('Slug'),
            Column::computed('status')->title('Status')->exportable(false)->printable(false),
            Column::make('views')->title('Views')->addClass('text-center'),
            Column::make('created_at')->title('Created At')->addClass('text-center'),
            Column::computed('action')->exportable(false)->printable(false)->addClass('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Blog_' . date('YmdHis');
    }
}


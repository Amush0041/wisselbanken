<?php

namespace App\DataTables;

use App\Models\Order;
use Yajra\DataTables\Services\DataTable;

class OrderDataTable extends DataTable
{
    public function dataTable($query)
    {
        return datatables()
            ->eloquent($query)
            ->addColumn('user', function($order) {
                return $order->user->name ?? 'Guest';
            })
            ->addColumn('status', function($order) {
                return ucfirst($order->status);
            })
            ->addColumn('total', function($order) {
                return '$' . number_format($order->total, 2);
            })
            ->addColumn('created_at', function($order) {
                return $order->created_at->format('Y-m-d H:i');
            })
            ->addColumn('action', function($order) {
                return '<a href="' . route('admin.orders.show', $order->id) . '" class="btn btn-sm btn-primary">View</a>';
            })
            ->rawColumns(['action']);
    }

    public function query(Order $model)
    {
        return $model->newQuery()->with('user');
    }

    public function html()
    {
        return $this->builder()
            ->setTableId('orders-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->dom('Bfrtip')
            ->orderBy(0)
            ->parameters([
                'responsive' => true,
                'autoWidth' => false,
            ]);
    }

    protected function getColumns()
    {
        return [
            ['data' => 'id', 'name' => 'id', 'title' => '#ID'],
            ['data' => 'user', 'name' => 'user.name', 'title' => 'User'],
            ['data' => 'status', 'name' => 'status', 'title' => 'Status'],
            ['data' => 'total', 'name' => 'total', 'title' => 'Total'],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Created At'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false],
        ];
    }

    protected function filename(): string
    {
        return 'Orders_' . date('YmdHis');
    }
} 
<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\SizeDataTable;
use App\Http\Controllers\Controller;
use App\Imports\SizeImport;
use App\Models\Size;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class SizeController extends Controller
{
      /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(SizeDataTable $dataTable)
    {
        return $dataTable->render('admin.sizes.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreSizeRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([ 
            'name' => 'required',
            'slug' => 'required|unique:sizes,slug',
            'status' => 'required|in:1,0'
        ]);

        Size::create($request->all());

        return response()->json(['message' => 'Size added successfully!']);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Size  $size
     * @return \Illuminate\Http\Response
     */
    public function show(Size $size)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Size  $size
     * @return \Illuminate\Http\Response
     */
    public function edit(Size $size)
    {
        return $size;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateSizeRequest  $request
     * @param  \App\Models\Size  $size
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Size $size)
    {
        $request->validate([ 
            'name' => 'required',
            'slug' => 'required|unique:sizes,slug,' . $size->id,
            'status' => 'required|in:1,0'
        ]);

        $size->update($request->all());

        return response()->json(['message' => 'Size updated successfully!']);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Size  $size
     * @return \Illuminate\Http\Response
     */
    public function destroy(Size $size)
    {
        if(!$size->delete()){
            return response()->json(['status' => 'failure','message'=>'Something going wrong!.']);
        }
        return response()->json(['status' => 'success','message'=>'Size delete successfully.']);
    }


   
}

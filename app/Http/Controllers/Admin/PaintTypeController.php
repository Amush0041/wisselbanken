<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\PaintTypeDataTable;
use App\Http\Controllers\Controller;
use App\Models\PaintType;
use Illuminate\Http\Request;

class PaintTypeController extends Controller
{
      /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(PaintTypeDataTable $dataTable)
    {
        return $dataTable->render('admin.paint_types.index');
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
     * @param  \App\Http\Requests\StorePaintTypeRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([ 
            'name' => 'required',
            'slug' => 'required|unique:paint_types,slug',
            'status' => 'required|in:1,0'
        ]);

        PaintType::create($request->all());

        return response()->json(['message' => 'Paint type added successfully!']);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\PaintType  $paint_type
     * @return \Illuminate\Http\Response
     */
    public function show(PaintType $paint_type)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\PaintType  $paint_type
     * @return \Illuminate\Http\Response
     */
    public function edit(PaintType $paint_type)
    {
        return $paint_type;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdatePaintTypeRequest  $request
     * @param  \App\Models\PaintType   $paint_type
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PaintType $paint_type)
    {
        $request->validate([ 
            'name' => 'required',
            'slug' => 'required|unique:paint_types,slug,' . $paint_type->id,
            'status' => 'required|in:1,0'
        ]);

        $paint_type->update($request->all());

        return response()->json(['message' => 'Paint Type updated successfully!']);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\PaintType  $paint_type
     * @return \Illuminate\Http\Response
     */
    public function destroy(PaintType $paint_type)
    {
        if(!$paint_type->delete()){
            return response()->json(['status' => 'failure','message'=>'Something going wrong!.']);
        }
        return response()->json(['status' => 'success','message'=>'Paint Type delete successfully.']);
    }


}

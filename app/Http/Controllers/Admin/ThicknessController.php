<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\ThicknessDataTable;
use App\Http\Controllers\Controller;
use App\Models\Thickness;
use Illuminate\Http\Request;

class ThicknessController extends Controller
{
       /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(ThicknessDataTable $dataTable)
    { 
        return $dataTable->render('admin.thicknesses.index');
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
     * @param  \App\Http\Requests\StoreThicknessRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([ 
            'name' => 'required',
            'slug' => 'required|unique:thicknesses,slug',
            'status' => 'required|in:1,0'
        ]);

        Thickness::create($request->all());

        return response()->json(['message' => 'Thickness added successfully!']);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Thickness  $thickness
     * @return \Illuminate\Http\Response
     */
    public function show(Thickness $thickness)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Thickness  $thickness
     * @return \Illuminate\Http\Response
     */
    public function edit(Thickness $thickness)
    {
        return $thickness;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateThicknessRequest  $request
     * @param  \App\Models\Thickness  $thickness
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Thickness $thickness)
    {
        $request->validate([ 
            'name' => 'required',
            'slug' => 'required|unique:thicknesses,slug,' . $thickness->id,
            'status' => 'required|in:1,0'
        ]);

        $thickness->update($request->all());

        return response()->json(['message' => 'Thickness updated successfully!']);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Size  $size
     * @return \Illuminate\Http\Response
     */
    public function destroy(Thickness $thickness)
    {
        if(!$thickness->delete()){
            return response()->json(['status' => 'failure','message'=>'Something going wrong!.']);
        }
        return response()->json(['status' => 'success','message'=>'Thickness delete successfully.']);
    }
}

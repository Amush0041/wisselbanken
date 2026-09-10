<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\ColorDataTable;
use App\Http\Controllers\Controller;
use App\Models\Color;
use App\Models\PaintType;
use Illuminate\Http\Request;

class ColorController extends Controller
{
      /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(ColorDataTable $dataTable)
    {
        $paint_types = PaintType::where('status', 1)->get();
        return $dataTable->render('admin.colors.index', compact('paint_types'));
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
     * @param  \App\Http\Requests\StoreColorRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([ 
            'name' => 'required',
            'paint_type_id' => 'required',
            'slug' => 'required|unique:colors,slug',
            'status' => 'required|in:1,0'
        ]);

        Color::create($request->all());

        return response()->json(['message' => 'Color added successfully!']);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Color  $color
     * @return \Illuminate\Http\Response
     */
    public function show(Color $color)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Color  $color
     * @return \Illuminate\Http\Response
     */
    public function edit(Color $color)
    {
        return $color;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateColorRequest  $request
     * @param  \App\Models\Color  $color
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Color $color)
    {
        $request->validate([ 
            'name' => 'required',
            'paint_type_id' => 'required',
            'slug' => 'required|unique:colors,slug,' . $color->id,
            'status' => 'required|in:1,0'
        ]);

        $color->update($request->all());

        return response()->json(['message' => 'Color updated successfully!']);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Color  $color
     * @return \Illuminate\Http\Response
     */
    public function destroy(Color $color)
    {
        if(!$color->delete()){
            return response()->json(['status' => 'failure','message'=>'Something going wrong!.']);
        }
        return response()->json(['status' => 'success','message'=>'Color delete successfully.']);
    }


}

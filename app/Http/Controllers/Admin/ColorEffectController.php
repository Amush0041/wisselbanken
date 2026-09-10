<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\ColorEffectDataTable;
use App\Http\Controllers\Controller;
use App\Models\ColorEffect;
use Illuminate\Http\Request;

class ColorEffectController extends Controller
{
      /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(ColorEffectDataTable $dataTable)
    {
        return $dataTable->render('admin.color_effects.index');
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
     * @param  \App\Http\Requests\StoreColorEffectRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([ 
            'name' => 'required',
            'slug' => 'required|unique:color_effects,slug',
            'status' => 'required|in:1,0'
        ]);

        ColorEffect::create($request->all());

        return response()->json(['message' => 'ColorEffect added successfully!']);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ColorEffect  $color_effect
     * @return \Illuminate\Http\Response
     */
    public function show(ColorEffect $color_effect)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\ColorEffect  $color_effect
     * @return \Illuminate\Http\Response
     */
    public function edit(ColorEffect $color_effect)
    {
        return $color_effect;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateColorEffectRequest  $request
     * @param  \App\Models\ColorEffect  $color_effect
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ColorEffect $color_effect)
    {
        $request->validate([ 
            'name' => 'required',
            'slug' => 'required|unique:color_effects,slug,' . $color_effect->id,
            'status' => 'required|in:1,0'
        ]);

        $color_effect->update($request->all());

        return response()->json(['message' => 'Color Effect updated successfully!']);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ColorEffect  $color_effect
     * @return \Illuminate\Http\Response
     */
    public function destroy(ColorEffect $color_effect)
    {
        if(!$color_effect->delete()){
            return response()->json(['status' => 'failure','message'=>'Something going wrong!.']);
        }
        return response()->json(['status' => 'success','message'=>'Color Effect delete successfully.']);
    }

}

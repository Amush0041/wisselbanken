<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\FinishDataTable;
use App\Http\Controllers\Controller;
use App\Models\Finish;
use Illuminate\Http\Request;

class FinishController extends Controller
{
        /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(FinishDataTable $dataTable)
    {
        return $dataTable->render('admin.finishes.index');
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
     * @param  \App\Http\Requests\StoreFinishRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([ 
            'name' => 'required',
            'slug' => 'required|unique:finishes,slug',
            'status' => 'required|in:1,0'
        ]);

        Finish::create($request->all());

        return response()->json(['message' => 'Finishes added successfully!']);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Finish  $finish
     * @return \Illuminate\Http\Response
     */
    public function show(Finish $finish)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Finish  $finish
     * @return \Illuminate\Http\Response
     */
    public function edit(Finish $finish)
    {
        return $finish;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateFinishRequest  $request
     * @param  \App\Models\Finish  $finish
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Finish $finish)
    {
        $request->validate([ 
            'name' => 'required',
            'slug' => 'required|unique:finishes,slug,' . $finish->id,
            'status' => 'required|in:1,0'
        ]);

        $finish->update($request->all());

        return response()->json(['message' => 'Finishes updated successfully!']);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Finish  $finish
     * @return \Illuminate\Http\Response
     */
    public function destroy(Finish $finish)
    {
        if(!$finish->delete()){
            return response()->json(['status' => 'failure','message'=>'Something going wrong!.']);
        }
        return response()->json(['status' => 'success','message'=>'Finish delete successfully.']);
    }

}

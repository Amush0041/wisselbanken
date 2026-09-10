<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\DivisionDataTable;
use App\Http\Controllers\Controller;
use App\Imports\DivisionImport;
use App\Models\Division;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class DivisionController extends Controller
{
     /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(DivisionDataTable $dataTable)
    {
        return $dataTable->render('admin.divisions.index');
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
     * @param  \App\Http\Requests\StoreDivisionRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:divisions,code',
            'name' => 'required',
            'status' => 'required|in:1,0'
        ]);

        $data = $request->all();
        $data['slug'] = Str::slug($request->name);
        
        // Ensure slug is unique
        $originalSlug = $data['slug'];
        $counter = 1;
        while (Division::where('slug', $data['slug'])->exists()) {
            $data['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        Division::create($data);

        return response()->json(['message' => 'Division added successfully!']);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Division  $division
     * @return \Illuminate\Http\Response
     */
    public function show(Division $division)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Divison  $division
     * @return \Illuminate\Http\Response
     */
    public function edit(Division $division)
    {
        return $division;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateDivisionRequest  $request
     * @param  \App\Models\Division  $division
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Division $division)
    {
        $request->validate([
            'code' => 'required|unique:divisions,code,' . $division->id,
            'name' => 'required',
            'status' => 'required|in:1,0'
        ]);

        $data = $request->all();
        $data['slug'] = Str::slug($request->name);
        
        // Ensure slug is unique (excluding current division)
        $originalSlug = $data['slug'];
        $counter = 1;
        while (Division::where('slug', $data['slug'])->where('id', '!=', $division->id)->exists()) {
            $data['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        $division->update($data);

        return response()->json(['message' => 'Division updated successfully!']);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Division  $division
     * @return \Illuminate\Http\Response
     */
    public function destroy(Division $division)
    {
        if(!$division->delete()){
            return response()->json(['status' => 'failure','message'=>'Something going wrong!.']);
        }
        return response()->json(['status' => 'success','message'=>'Division delete successfully.']);
    }


    public function importDivision(Request $request)
    { 
        // Validate the uploaded file
        $request->validate([
            'file' => 'required|mimes:xlsx,csv',
        ]);

        // Import the file
        try {
            Excel::import(new DivisionImport, $request->file('file'));

            return redirect()->back()->with('success', 'Data imported successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error importing data: ' . $e->getMessage());
        }
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\SpecificationDataTable;
use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Specification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SpecificationController extends Controller
{
      /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(SpecificationDataTable $dataTable)
    {
        $divisions = Division::get();
        return $dataTable->render('admin.specifications.index', compact('divisions'));
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
            'division_id' => 'required',
            'specification_number' => 'required|unique:specifications,specification_number',
            'material_type' => 'required',
            'status' => 'required|in:1,0'
        ]);

        $data = $request->all();
        $data['slug'] = Str::slug($request->material_type);
        
        // Ensure slug is unique
        $originalSlug = $data['slug'];
        $counter = 1;
        while (Specification::where('slug', $data['slug'])->exists()) {
            $data['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        Specification::create($data);

        return response()->json(['message' => 'Specification added successfully!']);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Division  $division
     * @return \Illuminate\Http\Response
     */
    public function show(Specification $division)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Divison  $division
     * @return \Illuminate\Http\Response
     */
    public function edit(Specification $specification)
    { 
        return $specification;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateDivisionRequest  $request
     * @param  \App\Models\Division  $division
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Specification $Specification)
    {
        $request->validate([
            'division_id'           => 'required',
            'specification_number'  => 'required|unique:specifications,specification_number,' . $Specification->id,
            'material_type'         => 'required',
            'status'                => 'required|in:1,0'
        ]);

        $data = $request->all();
        $data['slug'] = Str::slug($request->material_type);
        
        // Ensure slug is unique (excluding current specification)
        $originalSlug = $data['slug'];
        $counter = 1;
        while (Specification::where('slug', $data['slug'])->where('id', '!=', $Specification->id)->exists()) {
            $data['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        $Specification->update($data);

        return response()->json(['message' => 'Specification updated successfully!']);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Division  $division
     * @return \Illuminate\Http\Response
     */
    public function destroy(Specification $Specification)
    {
        if(!$Specification->delete()){
            return response()->json(['status' => 'failure','message'=>'Something going wrong!.']);
        }
        return response()->json(['status' => 'success','message'=>'Specification delete successfully.']);
    }


}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

class ProductFileController extends Controller
{
    public function show($product_id){ 
        $product = Product::find($product_id);
        $results = ProductFile::where('product_id', $product_id)->get()->groupBy('type');
        return view('admin.products.fileupload',compact('product_id','product','results'));
    }

    public function store(Request $request)
    {
        $file = $request->file('file');
        if ($file) {
            $path = $request->file->store('product-detail/files', 'public');
        }

        $data = ProductFile::create([
            'product_id' => $request->product_id,
            'name' => $request->file->getClientOriginalName(),
            'user_id' => Auth::user()->id,
            'type' => $request->type,
            'extension' => $file->getClientOriginalExtension(),
            'size' => $file->getSize(),
            'path' => 'storage/'.$path
        ]);

        if ($data) {
            return response()->json([
                'status' => true,
                'message' => 'File uploaded successfully'
            ]);
        }
        return response()->json([
            'status' => false,
            'message' => 'Something going wrong'
        ]);
    }

    public function destroy(ProductFile $product_file)
    {
        if(File::exists($product_file->path)){
            File::delete($product_file->path);
        }
        $product_file->delete();
        return response()->json(['success' => 'Record deleted successfully!']);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\FileImport;
use App\Models\Division;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\Rbac\Organization;
use App\Models\Rbac\UserOrgRole;
use App\Models\Specification;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class DashboardController extends Controller
{
    public function index(){
        $divisions          = Division::count();
        $specifications     = Specification::count();
        $manufacturers      = Manufacturer::count();
        $products           = Product::count();
        $rbacOrgs           = Organization::count();
        $rbacAssignments    = UserOrgRole::where('is_active', true)->count();
        return view('admin/dashboard', compact('divisions','specifications','manufacturers','products','rbacOrgs','rbacAssignments'));
    }

    public function importFile(Request $request)
    { 
        // Validate the uploaded file
        $request->validate([
            'file' => 'required|mimes:xlsx,csv',
        ]);

        // Import the file
        try {
            $import = new FileImport();
            Excel::import($import, $request->file('file'));

            return redirect()->back()->with('success', 'Records imported successfully!');
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Check if error message contains our custom error format
            if (strpos($errorMessage, 'Import completed with errors:') !== false) {
                $errorDetails = explode("\n", $errorMessage);
                array_shift($errorDetails); // Remove the first line "Import completed with errors:"
                
                return redirect()->back()
                    ->with('error', 'Import completed with some errors. Please review and fix the issues below:')
                    ->with('error_details', $errorDetails);
            }
            
            return redirect()->back()
                ->with('error', 'Error importing data: ' . $errorMessage);
        }
    }
}

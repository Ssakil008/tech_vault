<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use App\Models\CredentialForServer;
use App\Models\CredentialForUser;
use App\Models\AdditionalInformation;
use App\Models\Role;
use App\Models\Permission;
use App\Models\UserRole;
use App\Models\Menu;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Yajra\DataTables\Facades\DataTables;


class UserController extends Controller
{
    public function loginUser(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'password' => 'required',
        ]);

        $credential = $request->only('email', 'password');

        if (Auth::attempt($credential)) {
            return response()->json(['success' => true]);
        } else {
            return response()->json(['fail' => 'The email is not recognized']);
        }
    }


    // public function getEntries()
    // {
    //     $credentials = CredentialForServer::query();

    //     return DataTables::of($credentials)
    //         ->make(true); // Enable server-side processing
    // }

    public function getEntries(Request $request)
    {
        $menuId = $request->input('menuId');

        if ($this->checkPermissions($menuId)) {
            $queries = CredentialForServer::query()->get();

            // DataTables expects specific JSON response structure
            $data = [];
            foreach ($queries as $query) {
                $data[] = [
                    'id' => $query->id,
                    'credential_for' => $query->credential_for,
                    'email' => $query->email, // Access user email through relationship
                    'mobile' => $query->mobile,
                    'url' => $query->url,
                    'ip_address' => $query->ip_address,
                    'username' => $query->username,
                    'password' => $query->password,
                    // Add other columns as needed
                ];
            }

            return DataTables::of($data)->make(true);
        } else {
            return view('Permission denied');
        }
    }


    public function getAllUserData(Request $request)
    {
        $menuId = $request->input('menuId');

        if ($this->checkPermissions($menuId)) {
            $users = User::with('user_role.role')
                ->where('email', '!=', 'monir.uddincloudone@gmail.com')
                ->get();

            // Modify user data with role information before returning
            $users->each(function ($user) {
                if ($user->user_role) {
                    $user->role = $user->user_role->role->role; // Access role name
                } else {
                    $user->role = ''; // Set default value if no role assigned
                }
            });

            return DataTables::of($users)->make(true);
        } else {
            return view('Permission denied');
        }
    }

    public function getAllMenuData(Request $request)
    {
        $menuId = $request->input('menuId');

        if ($this->checkPermissions($menuId)) {
            $menu = Menu::all();
            return DataTables::of($menu)->make(true);
        } else {
            return view('Permission denied');
        }
    }

    public function getAllRoleData(Request $request)
    {
        $menuId = $request->input('menuId');

        if ($this->checkPermissions($menuId)) {
            $roles = Role::all();

            return response()->json(['data' => $roles]);
        } else {
            return view('Permission denied');
        }
    }

    public function getDynamicData(Request $request)
    {
        $menuId = $request->input('menuId');

        if ($this->checkPermissions($menuId)) {
            // Fetch data from the dynamic table
            $data = CredentialForUser::all();

            return DataTables::of($data)
                ->make(true);
        } else {
            return view('Permission denied');
        }
    }

    public function getAllInformation()
    {
        $data = AdditionalInformation::all();

        return response()->json(['data' => $data]);
    }

    public function getEntry($id)
    {
        $entry = CredentialForServer::find($id);

        if ($entry) {
            return response()->json(['data' => $entry]);
        } else {
            return response()->json(['error' => 'Entry not found'], 404);
        }
    }

    public function getUserData($id)
    {
        $user = User::with('user_role.role')->find($id);

        if ($user) {
            return response()->json(['data' => $user]);
        } else {
            return response()->json(['error' => 'Entry not found'], 404);
        }
    }

    public function getMenuData($id)
    {
        $menu = Menu::find($id);

        if ($menu) {
            return response()->json(['data' => $menu]);
        } else {
            return response()->json(['error' => 'Entry not found'], 404);
        }
    }

    public function getRoleData($id)
    {
        $role = Role::find($id);

        if ($role) {
            return response()->json(['data' => $role]);
        } else {
            return response()->json(['error' => 'Entry not found'], 404);
        }
    }

    public function getPermissionData($id)
    {
        $permission = Permission::with('menu')->find($id); // Assuming 'menu' is the relationship between Permission and Menu models

        if ($permission) {
            return response()->json(['data' => $permission]);
        } else {
            return response()->json(['error' => 'Entry not found'], 404);
        }
    }


    public function getAllPermission(Request $request, $id)
    {
        $menuId = $request->input('menuId');

        if ($this->checkPermissions($menuId)) {
            // Find permissions associated with the given role ID and eager load the related menu data
            $permissions = Permission::with('menu')->where('role_id', $id)->get();

            if ($permissions->isNotEmpty()) {
                // If permissions are found, return them as JSON response
                return response()->json(['data' => $permissions]);
            } else {
                // If no permissions are found, return a 404 error response
                return response()->json(['error' => 'No permissions found for the given role ID'], 404);
            }
        } else {
            return view('Permission denied');
        }
    }

    public function getCredentialForUserData($id)
    {
        $data = CredentialForUser::find($id);

        if ($data) {
            return response()->json(['data' => $data]);
        } else {
            return response()->json(['error' => 'Entry not found'], 404);
        }
    }

    public function getMoreInfo($id)
    {
        $data = AdditionalInformation::where('credential_for_user_id', $id)->get();

        if ($data->isEmpty()) {
            return response()->json(['error' => 'Additional Information is empty'], 404);
        }

        // Modify field_name values
        $data->transform(function ($item, $key) {
            $item['field_name'] = $this->formatFieldName($item['field_name']);
            return $item;
        });

        return response()->json(['data' => $data]);
    }

    private function formatFieldName($fieldName)
    {
        // Remove underscores, hyphens, and other symbols
        $formattedFieldName = str_replace(['_', '-'], ' ', $fieldName);

        // Separate words in camelCase
        $formattedFieldName = preg_replace('/(?<!^)([A-Z][a-z]|(?<=[a-z])[A-Z])/', ' $1', $formattedFieldName);

        // Capitalize the first letter of each word
        $formattedFieldName = ucwords($formattedFieldName);

        return $formattedFieldName;
    }

    public function logout()
    {
        Session::flush();
        return response()->json(['success' => true]);
    }

    function generateSidebarMenu()
    {
        $userId = auth()->id();
        $userRole = UserRole::where('user_id', $userId)->first();

        if (!$userRole) {
            // Handle the case where user role is not found
            return [];
        }

        // Step 2: Find all permissions associated with the retrieved role ID
        $permissions = Permission::where('role_id', $userRole->role_id)->get();

        $sidebarMenu = [];

        // Step 3-5: Iterate over each permission and retrieve menu name and link
        foreach ($permissions as $permission) {
            // Find the menu associated with the permission
            $menu = Menu::find($permission->menu_id);

            if ($menu) {
                // If menu exists, add its name and link to the sidebar menu
                $sidebarMenu[] = [
                    'id' => $menu->id,
                    'name' => $menu->name,
                    'link' => $menu->link,
                ];
            }
        }
        return response()->json(['sidebarMenu' => $sidebarMenu]);
    }

    public function checkPermissions($menuId)
    {
        $userId = Auth::id();
        $roleId = DB::table('user_role')->where('user_id', $userId)->value('role_id');

        // Check if a permission exists for the role and the specified menu ID
        return Permission::where('role_id', $roleId)
            ->where('menu_id', $menuId)
            ->where('read', 'yes')
            ->exists();
    }
}

<?php

namespace App\Http\Controllers\Backend;

use App\Http\Helpers\AppHelper;
use App\Registration;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\AcademicYear;

class AdministratorController extends Controller
{
    /**
     * academic year  manage
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function academicYearIndex(Request $request)
    {
        //for save on POST request
        if ($request->isMethod('post')) {//
            $this->validate($request, [
                'hiddenId' => 'required|integer',
            ]);
            $year = AcademicYear::findOrFail($request->get('hiddenId'));

            // now check is academic year set or not
            $settings = AppHelper::getAppSettings();
            $haveStudent = Registration::where('academic_year_id', $year->id)->count();
            if((isset($settings['academic_year']) && (int)$settings['academic_year'] == $year->id) || ($haveStudent > 0)){
                return redirect()->route('administrator.academic_year')->with('error', 'Can not delete it because this year have student or have in default setting.');
            }
            $year->delete();

            return redirect()->route('administrator.academic_year')->with('success', 'Record deleted!');
        }

        //for get request
        $academicYears = AcademicYear::orderBy('id', 'desc')->get();


        return view('backend.administrator.academic.list', compact('academicYears'));
    }

    /**
     * academic year  manage
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function academicYearCru(Request $request, $id=0)
    {
        //for save on POST request
        if ($request->isMethod('post')) {
            ;
            $this->validate($request, [
                'title' => 'required|min:4|max:255',
                'start_date' => 'required|min:10|max:255',
                'end_date' => 'required|min:10|max:255',
            ]);

            $data = $request->all();
            $datetime = Carbon::createFromFormat('d/m/Y',$data['start_date']);
            $data['start_date'] = $datetime;
            $datetime = Carbon::createFromFormat('d/m/Y',$data['end_date']);
            $data['end_date'] = $datetime;
            if(!$id){
                $data['status'] = '1';
            }

            AcademicYear::updateOrCreate(
                ['id' => $id],
                $data
            );
            $msg = "Academic year ";
            $msg .= $id ? 'updated.' : 'added.';

            return redirect()->route('administrator.academic_year')->with('success', $msg);
        }

        //for get request
        $academicYear = AcademicYear::find($id);

        return view('backend.administrator.academic.add', compact('academicYear'));
    }

    /**
     * academic year  manage
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function academicYearChangeStatus(Request $request, $id=0)
    {
        $year =  AcademicYear::findOrFail($id);
        if(!$year){
            return [
                'success' => false,
                'message' => 'Record not found!'
            ];
        }

        $settings = AppHelper::getAppSettings();
        if(isset($settings['academic_year']) && (int)$settings['academic_year'] == $year->id){
            return [
                'success' => false,
                'message' => 'Can not change status! Year is using as academic year right now.'
            ];
        }

        $year->status = (string)$request->get('status');

        $year->save();

        return [
            'success' => true,
            'message' => 'Status updated.'
        ];

    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function userIndex()
    {

        $users = User::rightJoin('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->leftJoin('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.role_id', '=', AppHelper::USER_ADMIN)
            ->select('users.*','roles.name as role')
            ->get();

        return view('backend.administrator.user.list', compact('users'));

    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function userCreate()
    {
        $roles = Role::where('id', '<>', AppHelper::USER_ADMIN)->pluck('name', 'id');
        $user = null;
        $role = null;
        return view('backend.user.add', compact('roles','user', 'role'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function userStore(Request $request)
    {

        $this->validate(
            $request, [
                'name' => 'required|min:5|max:255',
                'email' => 'email|max:255|unique:users,email',
                'username' => 'required|min:5|max:255|unique:users,username',
                'password' => 'required|min:6|max:50',
                'role_id' => 'required|numeric',

            ]
        );

        $data = $request->all();

        if($data['role_id'] == AppHelper::USER_ADMIN){
            return redirect()->route('user.create')->with("error",'Do not mess with the system!!!');

        }

        DB::beginTransaction();
        try {
            //now create user
            $user = User::create(
                [
                    'name' => $data['name'],
                    'username' => $request->get('username'),
                    'email' => $data['email'],
                    'password' => bcrypt($request->get('password')),
                    'remember_token' => null,
                ]
            );
            //now assign the user to role
            UserRole::create(
                [
                    'user_id' => $user->id,
                    'role_id' => $data['role_id']
                ]
            );

            DB::commit();

            return redirect()->route('user.create')->with('success', 'User added!');


        }
        catch(\Exception $e){
            DB::rollback();
            $message = str_replace(array("\r", "\n","'","`"), ' ', $e->getMessage());
            return $message;
            return redirect()->route('user.create')->with("error",$message);
        }

        return redirect()->route('user.create');


    }

    public function useredit($id)
    {

        $user = User::rightJoin('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->where('user_roles.role_id', '<>', AppHelper::USER_ADMIN)
            ->where('users.id', $id)
            ->select('users.*','user_roles.role_id')
            ->first();

        if(!$user){
            abort(404);
        }


        $roles = Role::where('id', '<>', AppHelper::USER_ADMIN)->pluck('name', 'id');
        $role = $user->role_id;

        return view('backend.user.add', compact('user','roles','role'));

    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Item  $item
     * @return \Illuminate\Http\Response
     */
    public function userUpdate(Request $request, $id)
    {
        $user = User::rightJoin('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->where('user_roles.role_id', '<>', AppHelper::USER_ADMIN)
            ->where('users.id', $id)
            ->select('users.*','user_roles.role_id')
            ->first();

        if(!$user){
            abort(404);
        }
        //validate form
        $this->validate(
            $request, [
                'name' => 'required|min:5|max:255',
                'email' => 'email|max:255|unique:users,email,'.$user->id,
                'role_id' => 'required|numeric'

            ]
        );

        if($request->get('role_id') == AppHelper::USER_ADMIN){
            return redirect()->route('user.index')->with("error",'Do not mess with the system!!!');

        }


        $data['name'] = $request->get('name');
        $data['email'] = $request->get('email');
        $user->fill($data);
        $user->save();

        if($user->role_id != $request->get('role_id')){
            $userRole = UserRole::where('user_id', $user->id)->first();
            $userRole->role_id = $request->get('role_id');
            $userRole->save();
        }

        return redirect()->route('user.index')->with('success', 'User updated!');


    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Item  $item
     * @return \Illuminate\Http\Response
     */
    public function userDestroy($id)
    {

        $user =  User::findOrFail($id);

        $userRole = UserRole::where('user_id', $user->id)->first();

        if($userRole && $userRole->role_id == AppHelper::USER_ADMIN){
            return redirect()->route('user.index')->with('Error', 'Don not mess with the system');

        }


        $user->delete();

        return redirect()->route('user.index')->with('success', 'User deleted.');

    }

    /**
     * status change
     * @return mixed
     */
    public function userChangeStatus(Request $request, $id=0)
    {

        $user =  User::findOrFail($id);

        if(!$user){
            return [
                'success' => false,
                'message' => 'Record not found!'
            ];
        }

        $user->status = (string)$request->get('status');

        $user->save();

        return [
            'success' => true,
            'message' => 'Status updated.'
        ];

    }


}

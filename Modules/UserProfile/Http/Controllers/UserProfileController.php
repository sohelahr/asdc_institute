<?php

namespace Modules\UserProfile\Http\Controllers;

use App\Http\Helpers\GetLocation;
use Database\Seeders\UserSeeder;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Assessment\Entities\AssessmentLine;
use Modules\Admission\Entities\Admission;
use Modules\Occupation\Entities\Occupation;
use Modules\Qualification\Entities\Qualification;
use Modules\UserProfile\Entities\UserProfile;
use Yajra\Datatables\Datatables;

class UserProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        //     $userprofiles = UserProfile::where('is_profile_completed',"1")->get();
        return view('userprofile::admin_profile_list' /*, compact('userprofiles') */);
    }
    public function viewProfile($id)
    {
        $userprofile = UserProfile::where('user_id', $id)->first();
        return redirect()->route('user_profile_admin', [$userprofile->id]);
    }

    function UserProfileData(Request $request)
    {
        //$userprofiles = UserProfile::where('is_profile_completed',"1")->get();
        $limit = $request->length;
        $start = $request->start;
        $search = $request->search['value'];

        $userprofiles = UserProfile::query();

        $userprofiles = $userprofiles->where('is_profile_completed', "1");

        //$totalApplicationCount = $userprofiles->count();
        $totalRegistrationRecord = $userprofiles->count();

        if (isset($search)) {
            $userprofiles = $userprofiles->where('mobile', 'LIKE', '%' . $search . '%')
                //->orWhere('firstname','LIKE','%'.$search.'%')
                ->orWhereIn('user_id', function ($query) use ($search) {
                    $query->select('id')->from('users')->where('name', 'LIKE', '%' . $search . '%');
                })
                ->orWhereIn('user_id', function ($query) use ($search) {
                    $query->select('id')->from('users')->where('email', 'LIKE', '%' . $search . '%');
                });

        }
        $filteredRegistrationCount = $userprofiles->count();
        $userprofiles = $userprofiles->where('is_profile_completed', '1')->orderBy('id', 'DESC')->skip($start)->limit($limit)->get();
        //dd($userprofiles);

        if (isset($search)) {
            $totalFiltered = $filteredRegistrationCount;
        } else {
            $totalFiltered = $totalRegistrationRecord;
        }
        return Datatables::of($userprofiles)
            ->addIndexColumn()
            ->addColumn('name', function ($profile) {
                if (\App\Http\Helpers\CheckPermission::hasPermission('view.profiles')) {
                    return ['perm' => true, 'name' => $profile->firstname . " " . $profile->lastname];
                } else {
                    return ["perm" => false, 'name' => $profile->firstname . " " . $profile->lastname];
                }
            })
            /* ->addColumn('qualification',function($profile){
                return $profile->Qualification->name;
            }) */
            ->addColumn('email', function ($profile) {
                return $profile->User->email;
            })
            ->addColumn('type', function ($profile) {
                if ($profile->User->Registrations->count() > 0)
                    return "True";
                else
                    return "FALSE";
            })
            ->addColumn('edit', function ($registration) {
                return \App\Http\Helpers\CheckPermission::hasPermission('update.profiles');
            })
            ->setTotalRecords($totalRegistrationRecord)
            ->setOffset($start)
            ->setFilteredRecords($totalFiltered)
            ->toJson();
    }
    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('userprofile::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show()
    {

        $profile = Auth::user()->UserProfile;
        $state_name = "";
        $city_name = "";
        if ($profile->is_profile_completed) {
            if (is_numeric($profile->state)) {
                $state_name = GetLocation::getOneState($profile->state)[0]->name;
                if ($profile->city != "undefined") {
                    //dd($profile->city);
                    try {
                        $city_name = GetLocation::getOneCity($profile->city)[0]->city_name;
                    } catch (\Exception $e) {
                        $city_name = $profile->city;
                    }
                }

            }
            return view('userprofile::user_profile', compact('profile', 'state_name', 'city_name'));
        }
        return redirect('/dashboard')->with('profile_not_complete', 'not complete');

    }
    public function Adminshow($id)
    {
        $profile = UserProfile::find($id);
        $state_name = "";
        $city_name = "";
        //dd($profile->state);
        if ($profile->is_profile_completed) {
            if (is_numeric($profile->state)) {
                $state_name = GetLocation::getOneState($profile->state)[0]->name;
                if ($profile->city != "undefined") {
                    //dd($profile->city);
                    try {
                        $city_name = GetLocation::getOneCity($profile->city)[0]->city_name;
                    } catch (\Exception $e) {
                        $city_name = $profile->city;
                    }
                }

            }
            $admissions = Admission::where('student_id', $profile->user_id)->get();
            return view('userprofile::admin_user_profile', compact('state_name', 'city_name', 'profile', 'admissions'));
        }
        return redirect()->back()->with('not_completed', 'your message,here');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($step)
    {
        $userprofile = Auth::User()->UserProfile;
        if ($userprofile->is_profile_completed && Auth::user()->user_type == "3") {
            return redirect('/dashboard')->with('profile_complete', 'user profile already completed');
        }
        if ($step == "one") {
            return view('userprofile::edit_step_one', compact('userprofile'));
        } elseif ($step == "two") {
            if ($userprofile->mobile == null) {
                return redirect()->route('profile_update', ['one']);
            }
            $cities = null;
            $states = GetLocation::getStates(101);

            if (!preg_match('/^[0-9]*$/', $userprofile->state)) {
                $cities = null;
            } else {
                $cities = GetLocation::getCities($userprofile->state);
            }
            return view('userprofile::edit_step_two', compact('userprofile', 'states', 'cities'));
        } elseif ($step == "three") {
            if ($userprofile->mobile == null) {
                return redirect()->route('profile_update', ['one']);
            } elseif ($userprofile->state == null) {
                return redirect()->route('profile_update', ['two']);
            }
            $qualifications = Qualification::all();
            $occupations = Occupation::all();

            return view('userprofile::edit_step_three', compact('userprofile', 'qualifications', 'occupations'));
        } elseif ($step == "four") {
            if ($userprofile->mobile == null) {
                return redirect()->route('profile_update', ['one']);
            } elseif ($userprofile->state == null) {
                return redirect()->route('profile_update', ['two']);
            } elseif ($userprofile->qualification_id == null) {
                return redirect()->route('profile_update', ['three']);
            }
            return view('userprofile::edit_step_four', compact('userprofile'));
        } else {
            return redirect('/dashboard')->with('error', 'xyz');
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $step)
    {

        try {

            $userprofile = Auth::User()->UserProfile;

            if ($userprofile->is_profile_completed && Auth::user()->user_type == "3") {
                return redirect('/dashboard')->with('profile_complete', 'user profile already completed');
            }

            if ($step == "one") {

                $addhaar_count = UserProfile::where('aadhaar', $request->aadhaar)->where('user_id', '<>', Auth::user()->id)->count();

                if ($addhaar_count > 0) {
                    return back()->withErrors('The adhaar is already been taken')->withInput();
                }

                $userprofile->firstname = $request->firstname;
                $userprofile->lastname = $request->lastname;
                $userprofile->mobile = $request->mobile;
                $userprofile->gender = $request->gender;
                $userprofile->dob = date('y/m/d', strtotime($request->dob));
                $userprofile->age = $request->age;
                $userprofile->aadhaar = $request->aadhaar;
                $userprofile->blood_group = $request->blood_group;
                $userprofile->marital_status = $request->marital_status;
                //saving files
                if ($request->file('photo')) {
                    $filename = 'profile-' . time() . "." . $request->file('photo')->getClientOriginalExtension();
                    $path = $request->file('photo')->storeAs('/profile_photos/', $filename);
                    $userprofile->photo = $filename;
                }
                if ($request->file('update_photo')) {
                    Storage::delete('/profile_photos/' . $userprofile->photo);

                    $filename = 'profile-' . time() . "." . $request->file('update_photo')->getClientOriginalExtension();
                    $path = $request->file('update_photo')->storeAs('/profile_photos/', $filename);
                    $userprofile->photo = $filename;
                }
                $userprofile->save();
                return redirect()->route('profile_update', ['two']);
            } elseif ($step == "two") {
                if ($userprofile->mobile == null) {
                    return redirect()->route('profile_update', ['one']);
                }

                /* Address */
                $userprofile->home_type = $request->home_type;
                $userprofile->house_details = $request->house_details;
                $userprofile->street = $request->street;
                $userprofile->landmark = $request->landmark;
                $userprofile->pincode = $request->pincode;
                $userprofile->state = $request->state;
                $userprofile->city = $request->city;


                $userprofile->save();
                return redirect()->route('profile_update', ['three']);

            } elseif ($step == "three") {
                if ($userprofile->mobile == null) {
                    return redirect()->route('profile_update', ['one']);
                } elseif ($userprofile->state == null) {
                    return redirect()->route('profile_update', ['two']);
                }

                /* Education */
                $userprofile->qualification_id = $request->qualification_id;
                $userprofile->qualification_specilization = $request->qualification_specilization;
                $userprofile->school_name = $request->school_name;
                $userprofile->qualification_status = $request->qualification_status;
                $userprofile->occupation_id = $request->occupation_id;


                $userprofile->save();
                return redirect()->route('profile_update', ['four']);

            } elseif ($step == "four") {

                if ($userprofile->mobile == null) {
                    return redirect()->route('profile_update', ['one']);
                } elseif ($userprofile->qualification_id == null) {
                    return redirect()->route('profile_update', ['two']);
                }
                $userprofile->comments = $request->comments;
                $userprofile->how_know_us = $request->how_know_us;
                $userprofile->father_name = $request->father_name;
                $userprofile->father_occupation = $request->father_occupation;
                $userprofile->fathers_income = $request->fathers_income;
                $userprofile->fathers_mobile = $request->fathers_mobile;

                $userprofile->is_profile_completed = "1";
                $userprofile->save();
                return redirect('/dashboard')->with('profile_updated', 'xyz');

            } else {
                return redirect('/dashboard')->with('error', 'xyz');
            }
        } catch (\Exception $e) {
            return redirect('/dashboard')->with('error', 'xyz');
        }

    }

    public function AdminEdit(Request $request, $id)
    {
        $userprofile = UserProfile::find($id);
        $occupations = Occupation::all();
        $qualifications = Qualification::all();
        if ($request->method() == "GET") {
            $cities = null;
            $states = GetLocation::getStates(101);
            if (!preg_match('/^[0-9]*$/', $userprofile->state)) {
                $cities = null;
            } else {
                $cities = GetLocation::getCities($userprofile->state);
            }
            return view('userprofile::admin_profile_edit', compact('states', 'cities', 'userprofile', 'occupations', 'qualifications'));
        } else {

            $userprofile->firstname = $request->firstname;
            $userprofile->lastname = $request->lastname;
            $userprofile->dob = date('y/m/d', strtotime($request->dob));
            $userprofile->age = $request->age;
            $userprofile->mobile = $request->mobile;
            $userprofile->occupation_id = $request->occupation_id;
            $userprofile->qualification_id = $request->qualification_id;
            $userprofile->qualification_specilization = $request->qualification_specilization;
            $userprofile->qualification_status = $request->qualification_status;
            $userprofile->gender = $request->gender;
            $userprofile->comments = $request->comments . '\n Registration Last Updated By ' . Auth::user()->name;
            $userprofile->house_details = $request->house_details;
            $userprofile->street = $request->street;
            $userprofile->landmark = $request->landmark;
            $userprofile->city = $request->city;
            $userprofile->state = $request->state;
            $userprofile->pincode = $request->pincode;
            $userprofile->how_know_us = $request->how_know_us;
            $userprofile->father_name = $request->father_name;
            $userprofile->father_occupation = $request->father_occupation;
            $userprofile->fathers_income = $request->fathers_income;
            $userprofile->fathers_mobile = $request->fathers_mobile;
            $userprofile->school_name = $request->school_name;

            $userprofile->blood_group = $request->blood_group;
            $userprofile->marital_status = $request->marital_status;
            $userprofile->aadhaar = $request->aadhaar;
            $userprofile->home_type = $request->home_type;

            //saving files
            if ($request->file('photo')) {

                Storage::delete('/profile_photos/' . $userprofile->photo);

                $filename = 'profile-' . time() . "." . $request->file('photo')->getClientOriginalExtension();
                $path = $request->file('photo')->storeAs('/profile_photos/', $filename);
                $userprofile->photo = $filename;
            } /* 
          if($request->is_profile_completed) */
            $userprofile->is_profile_completed = "1";
            $userprofile->save();

            return redirect()->route('user_profile_list')->with('updated', '0');
        }
    }

    public function GetCity($stateid)
    {
        $cities = GetLocation::getCities($stateid);
        return response()->json(['cities' => $cities]);
    }
    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        //
    }

    public function getSidebarData()
    {
        $userprofile = UserProfile::select('id', 'firstname', 'lastname', 'photo')->where('user_id', Auth::user()->id)->first();
        return response()->json($userprofile);
    }

}
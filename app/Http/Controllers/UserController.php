<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;


class UserController extends Controller
{


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return User::where('role_id', '!=', '4')->withTrashed()->with('role')->get();
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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required',
            'password' => 'required',
            'role_id' => 'required'
        ]);
        $data['name'] = $request->name;
        $data['email'] = $request->email;
        $data['password'] = bcrypt($request->password);
        $data['role_id'] = $request->role_id;


        return User::create($data);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $user = User::withTrashed()->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|max:255|unique:users,email,' . $user->id,
            'role_id' => 'nullable|exists:roles,id',
        ]);

        $data = ['name' => $request->name];
        if ($request->filled('email')) {
            $data['email'] = $request->email;
        }
        if ($request->filled('role_id')) {
            $data['role_id'] = $request->role_id;
        }
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return $user->load('role');
    }

    /**
     * Suppression definitive d'un utilisateur.
     * Refusee si l'utilisateur a deja un historique (caisses, syntheses, fiches chef de piste)
     * pour ne pas casser les rapports : dans ce cas il faut le desactiver.
     */
    public function force_destroy($id)
    {
        $user = User::withTrashed()->findOrFail($id);

        if (Auth::id() == $user->id) {
            return response()->json(['error' => 'Vous ne pouvez pas supprimer votre propre compte.'], 422);
        }

        $historique = DB::table('caisses')->where('user_id', $user->id)->exists()
            || DB::table('syntheses')->where('user_id', $user->id)->exists()
            || DB::table('fiche_chef_pistes')->where('user_id', $user->id)->exists();

        if ($historique) {
            return response()->json([
                'error' => "Cet utilisateur a deja un historique (caisses, rapports). Il ne peut pas etre supprime definitivement : desactivez-le plutot.",
            ], 422);
        }

        $user->tokens()->delete();
        $user->forceDelete();

        return 1;
    }

    public function update_password(Request $request)
    {
        $request->validate([
            'password' => 'required',
        ]);
        $user = Auth::user();
        // $data['password']=bcrypt($request->password);
        if(Hash::check($request->oldPassword,   $user->password)) {
            //return true;
            $user->update(['password' => Hash::make($request->password)]);
            return 1;
        }else{
            return ["error"=>"Mot de passe incorect "];
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        $user->delete();
        return 1;
    }
    /**
     * Restore the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function restore($id)
    {
        User::onlyTrashed()->where('id', $id)->restore();
        return 1;
    }
}

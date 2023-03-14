<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void 22
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();
        \App\Models\Role::create([
            'nom'=>'pompiste'
        ]);
        \App\Models\Role::create([
            'nom'=>'gerant'
        ]);
        \App\Models\Role::create([
            'nom'=>'chefpiste'
        ]);
        \App\Models\Role::create([
            'nom'=>'admin'
        ]);
        
        \App\Models\User::create([
        	'name'=>'Malang',
        	'email'=>'malang@gmail.com',
        	'role_id'=>(1),
        	'password'=>bcrypt(1234)
        ]);
        \App\Models\User::create([
        	'name'=>'Papis',
        	'email'=>'papis@gmail.com',
        	'role_id'=>(1),
        	'password'=>bcrypt(1234)
        ]);
        \App\Models\User::create([
        	'name'=>'Traoré',
        	'email'=>'traore@gmail.com',
        	'role_id'=>(1),
        	'password'=>bcrypt(1234)
        ]);
        \App\Models\User::create([
        	'name'=>'Ganius',
        	'email'=>'ganius@gmail.com',
        	'role_id'=>(3),
        	'password'=>bcrypt(1234)
        ]);
         \App\Models\User::create([
        	'name'=>'Rawa',
        	'email'=>'rawan@gmail.com',
        	'role_id'=>(2),
        	'password'=>bcrypt(1234)
        ]);
         \App\Models\User::create([
        	'name'=>'Root',
        	'email'=>'root@root.com',
        	'role_id'=>(4),
        	'password'=>bcrypt(1234)
        ]);

        $l = new LubrifiantsTableSeeder();
        $l->run();
    }
}

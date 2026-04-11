<?php

namespace App\Http\Controllers;

use App\Models\Usulan;
use Illuminate\Http\Request;

class UsulanController extends Controller
{
    public function index(){

        dd(Usulan::all());
        return view('usulan.list-usulan');
    }

    public function create(){
        return view('usulan.add-usulan');
    }
}

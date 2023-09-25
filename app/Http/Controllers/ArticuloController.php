<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{
    Articulo
};

class ArticuloController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show($codigo)
    {
        $articulo = Articulo::where('codigo', $codigo)->first();

        return !$articulo
            ? response()->json(['descripcion' => 'Inexistente'], 200)
            : response()->json(['descripcion' => $articulo->descripcion], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use App\Models\{
    Articulo
};

use Exception;


class InventarioController extends Controller
{

    public function index()
    {
        // $articles = test_inventario::all();

        // return $articles->isEmpty()
        //     ? response()->json(['message' => 'No se encontraron registros'], 200)
        //     : response()->json($articles, 200);

        $articulo = Articulo::limit(20)->get();;

        return response()->json([$articulo], 200);
    }


    public function store(Request $request) //Guardar inventario
    {

        $validator = Validator::make($request->all(), [
            'txt_usuario' => 'required|string|max:12',
            'sel_conteo' => 'required|numeric',
            'txt_ubicacion' => 'required|string|max:9',
            'txt_codigo' => 'required|numeric|min:1|max:9999999999999',
            'txt_descripcion' => 'required|string',
            'txt_cantidad' => 'required|numeric|min:-9999|max:9999',
            'uuid' => 'required|string'
        ]);

        if ($validator->fails()) {
            // Manejar los errores de validación
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }

        $usuario = $request->input('txt_usuario');
        $conteo = $request->input('sel_conteo');
        $ubicacion = $request->input('txt_ubicacion');
        $codigo = strval($request->input('txt_codigo'));
        $descripcion = $request->input('txt_descripcion');
        $cantidad = $request->input('txt_cantidad');
        $uuid = $request->input('uuid');

        /***************************************************************************************/

        //Actualizar tiempo
        date_default_timezone_set('America/Asuncion');
        $DateAndTime = date('Y-m-d H:i:s', time());

        try {
            //$confGuardadoData = conf_guardado::select('base', 'dia')->first();
            $base = "test_inventario";
            $dia = "1";
        } catch (Exception $e) {
            error_log($e->getMessage());
        }

        $flag = null;

        //Consultar existencia
        $result = DB::table($base)
            ->where('codigo', $codigo)
            ->where('descripcion', $descripcion)
            ->where('ubicacion', $ubicacion)
            ->where('dia', $dia)
            ->get();

        if ($descripcion == "INEXISTENTE") {
            $flag = 11;
        } else {

            if ($result->count() === 0) {  //Creación de conteo

                if ($conteo == 1) {

                    DB::insert(
                        'insert into "' . $base . '"(codigo, descripcion, c1, tiempo_c1, ubicacion, usuario_c1, dia, uuid)
                        values (?, ?, ?, ?, ?, ?, ?, ?)',
                        [$codigo, $descripcion, $cantidad, $DateAndTime, $ubicacion, $usuario, $dia, $uuid]
                    );

                    $flag = 1; //Guardado normal

                    return $flag;
                } else {

                    $flag = 2;  //Se necesita conteo 1
                }
            } else {     //Actualizacion de conteo

                $usuario_c1 = $usuario_c2 = $usuario_reconteo = $c1 = $c2 = $reconteo = null;
                $total = 0.0;

                if ($conteo == 1) {

                    try {
                        $consulta = DB::table($base)
                            ->select('usuario_c1', 'c1', 'c2', 'reconteo')
                            ->where('codigo', $codigo)
                            ->where('descripcion', $descripcion)
                            ->where('ubicacion', $ubicacion)
                            ->where('dia', $dia)
                            ->get();

                        foreach ($consulta as $data) {
                            $usuario_c1 = $data->usuario_c1;
                            $c1 = $data->c1;
                            $c2 = $data->c2;
                            $reconteo = $data->reconteo;
                        }
                    } catch (Exception $e) {
                        return response()->json($e->getMessage());
                    }


                    if (empty($reconteo)) {

                        if ($usuario_c1 == $usuario) { //Editado por el mismo usuario

                            $total = $c1 + $cantidad;

                            //Actualizar conteo 1
                            DB::update(
                                'update "' . $base . '" set c1 = ' . $total .
                                    'where codigo = ? and descripcion = ? and ubicacion = ? and dia = ?',
                                [$codigo, $descripcion, $ubicacion, $dia]
                            );

                            //Diferencia con el conteo 2
                            $diferencia = $total - $c2;

                            if ($c2 != 0) { //Si ya hubo conteo 2
                                //Actualizar diferencia
                                DB::update(
                                    'update "' . $base . '" set diferencia = ' . $diferencia .
                                        'where codigo = ? and descripcion = ? and ubicacion = ? and dia = ?',
                                    [$codigo, $descripcion, $ubicacion, $dia]
                                );
                            }

                            //Si la diferencia es distinto de 0, entonces se pasa el reconteo como 0 para
                            //que se pueda realizar dicho conteo.
                            if ($diferencia != 0) {

                                DB::update(
                                    'update "' . $base . '" set reconteo = 0.0 where codigo = ?
                                 and descripcion = ? and ubicacion = ? and dia = ?',
                                    [$codigo, $descripcion, $ubicacion, $dia]
                                );
                            } else { //Diferencia es igual a 0. Se establece reconteo automático

                                DB::update(
                                    'update "' . $base . '" set reconteo = ' . $total .
                                        'where codigo = ? and descripcion = ? and ubicacion = ? and dia = ?',
                                    [$codigo, $descripcion, $ubicacion, $dia]
                                );
                            }

                            $flag = 3; //Actualizacion normal

                        } else {

                            $flag = 4; // Solo el usuario_c1 puede editar

                        }
                        //Si hay reconteo; pero debe verificarse también si hay usuario
                    } else {
                        //No es '0', pero no tiene usuario
                        if (empty($usuario_reconteo)) {

                            if (empty($c1)) {

                                $flag = 2; //Se necesita conteo 1

                            } else {

                                if (empty($c2)) { //No hay conteo 2 previo
                                    $total = $cantidad;

                                    if ($usuario_c1 == $usuario) { //Mismo del conteo 1
                                        $flag = 5; //Usuario del conteo 1 no puede hacer el conteo 2
                                    } else {

                                        //Actualizar conteo 2
                                        try {

                                            $diferencia = $c1 - $total;

                                            DB::update(
                                                'UPDATE "' . $base .
                                                    '" SET c2 = ?, diferencia = ?, usuario_c2 = ?, tiempo_c2 = ?
                                                  WHERE codigo = ? AND descripcion = ? AND ubicacion = ? AND dia = ?',
                                                [
                                                    $total, $diferencia, $usuario, $DateAndTime, $codigo,
                                                    $descripcion, $ubicacion, $dia
                                                ]
                                            );
                                        } catch (Exception $e) {
                                            return response()->json($e->getMessage());
                                        }

                                        //Si la diferencia es distinto de 0, entonces se pasa el reconteo como 0 para
                                        //que se pueda realizar dicho conteo.
                                        if ($diferencia != 0) {

                                            DB::update(
                                                'update "' . $base . '" set reconteo = 0.0 where codigo = ?
                                        and descripcion = ? and ubicacion = ? and dia = ?',
                                                [$codigo, $descripcion, $ubicacion, $dia]
                                            );
                                        } else { //Diferencia es igual a 0. Se establece reconteo automático

                                            DB::update(
                                                'update "' . $base . '" set reconteo = ' . $total .
                                                    'where codigo = ? and descripcion = ? and ubicacion = ? and dia = ?',
                                                [$codigo, $descripcion, $ubicacion, $dia]
                                            );
                                        }

                                        $flag = 3; //Actualizacion normal
                                    }
                                } else { //Hay conteo 2 previo
                                    $total = $c2 + $cantidad;

                                    if ($usuario_c2 == $usuario) { //Editado por el mismo usuario

                                        //Actualizar conteo 2
                                        DB::update(
                                            'update "' . $base . '" set c2 = ' . $total .
                                                'where codigo = ? and descripcion = ? and ubicacion = ? and dia = ?',
                                            [$codigo, $descripcion, $ubicacion, $dia]
                                        );

                                        $diferencia = $c1 - $total;

                                        //Actualizar diferencia
                                        DB::update(
                                            'update "' . $base . '" set diferencia = ' . $diferencia .
                                                'where codigo = ? and descripcion = ? and ubicacion = ? and dia = ?',
                                            [$codigo, $descripcion, $ubicacion, $dia]
                                        );

                                        //Si la diferencia es distinto de 0, entonces se pasa el reconteo como 0 para
                                        //que se pueda realizar dicho conteo.
                                        if ($diferencia != 0) {

                                            DB::update(
                                                'update "' . $base . '" set reconteo = 0.0 where codigo = ?
                                         and descripcion = ? and ubicacion = ? and dia = ?',
                                                [$codigo, $descripcion, $ubicacion, $dia]
                                            );
                                        } else { //Diferencia es igual a 0. Se establece reconteo automático

                                            DB::update(
                                                'update "' . $base . '" set reconteo = ' . $total .
                                                    'where codigo = ? and descripcion = ? and ubicacion = ? and dia = ?',
                                                [$codigo, $descripcion, $ubicacion, $dia]
                                            );
                                        }

                                        $flag = 3; //Actualizacion normal

                                    } else {

                                        $flag = 6; // Solo el usuario_c2 puede editar

                                    }
                                }
                            }
                        } else {

                            $flag = 12; //Si ya existe reconteo, ya no se puede editar el conteo 1 y 2

                        }
                    }
                } else if ($conteo == 2) {

                    //Obtener datos
                    foreach (DB::table($base)->select('usuario_c2', 'c2', 'c1', 'usuario_c1', 'reconteo', 'usuario_reconteo')
                        ->where('codigo', $codigo)
                        ->where('ubicacion', $ubicacion)
                        ->where('dia', $dia)
                        ->get() as $data) {

                        $usuario_c2 = $data->usuario_c2;
                        $c2 = $data->c2;
                        $c1 = $data->c1;
                        $usuario_c1 = $data->usuario_c1;
                        $reconteo = $data->reconteo;
                        $usuario_reconteo = $data->usuario_reconteo;
                    }

                    //Si no hay reconteo previo
                    if (empty($reconteo)) {

                        if (empty($c1)) {

                            $flag = 2; //Se necesita conteo 1

                        } else {

                            if (empty($c2)) { //No hay conteo 2 previo
                                $total = $cantidad;

                                if ($usuario_c1 == $usuario) { //Mismo del conteo 1
                                    $flag = 5; //Usuario del conteo 1 no puede hacer el conteo 2
                                } else {

                                    $diferencia = $c1 - $total;

                                    //Actualizar conteo 2
                                    try {

                                        $diferencia = $c1 - $total;

                                        DB::update(
                                            'UPDATE "' . $base .
                                                '" SET c2 = ?, diferencia = ?, usuario_c2 = ?, tiempo_c2 = ?
                                              WHERE codigo = ? AND descripcion = ? AND ubicacion = ? AND dia = ?',
                                            [
                                                $total, $diferencia, $usuario, $DateAndTime, $codigo,
                                                $descripcion, $ubicacion, $dia
                                            ]
                                        );
                                    } catch (Exception $e) {
                                        return response()->json($e->getMessage());
                                    }

                                    //Si la diferencia es distinto de 0, entonces se pasa el reconteo como 0 para
                                    //que se pueda realizar dicho conteo.
                                    if ($diferencia != 0) {

                                        DB::update(
                                            'update "' . $base . '" set reconteo = 0.0 where codigo = ?
                                    and descripcion = ? and ubicacion = ? and dia = ?',
                                            [$codigo, $descripcion, $ubicacion, $dia]
                                        );
                                    } else { //Diferencia es igual a 0. Se establece reconteo automático

                                        DB::update(
                                            'update "' . $base . '" set reconteo = ' . $total .
                                                'where codigo = ? and descripcion = ? and ubicacion = ? and dia = ?',
                                            [$codigo, $descripcion, $ubicacion, $dia]
                                        );
                                    }

                                    $flag = 3; //Actualizacion normal
                                }
                            } else { //Hay conteo 2 previo
                                $total = $c2 + $cantidad;

                                if ($usuario_c2 == $usuario) { //Editado por el mismo usuario

                                    //Actualizar conteo 2
                                    DB::update(
                                        'update "' . $base . '" set c2 = ' . $total .
                                            'where codigo = ? and descripcion = ? and ubicacion = ? and dia = ?',
                                        [$codigo, $descripcion, $ubicacion, $dia]
                                    );

                                    $diferencia = $c1 - $total;

                                    //Actualizar diferencia
                                    DB::update(
                                        'update "' . $base . '" set diferencia = ' . $diferencia .
                                            'where codigo = ? and descripcion = ? and ubicacion = ? and dia = ?',
                                        [$codigo, $descripcion, $ubicacion, $dia]
                                    );

                                    //Si la diferencia es distinto de 0, entonces se pasa el reconteo como 0 para
                                    //que se pueda realizar dicho conteo.
                                    if ($diferencia != 0) {

                                        DB::update(
                                            'update "' . $base . '" set reconteo = 0.0 where codigo = ?
                                     and descripcion = ? and ubicacion = ? and dia = ?',
                                            [$codigo, $descripcion, $ubicacion, $dia]
                                        );
                                    } else { //Diferencia es igual a 0. Se establece reconteo automático

                                        DB::update(
                                            'update "' . $base . '" set reconteo = ' . $total .
                                                'where codigo = ? and descripcion = ? and ubicacion = ? and dia = ?',
                                            [$codigo, $descripcion, $ubicacion, $dia]
                                        );
                                    }

                                    $flag = 3; //Actualizacion normal

                                } else {

                                    $flag = 6; // Solo el usuario_c2 puede editar

                                }
                            }
                        }

                        //Si hay reconteo previo (Reconteo no es 0)
                    } else {

                        //No es '0', pero no tiene usuario
                        if (empty($usuario_reconteo)) {

                            if (empty($c1)) {

                                $flag = 2; //Se necesita conteo 1

                            } else {

                                if (empty($c2)) { //No hay conteo 2 previo
                                    $total = $cantidad;

                                    if ($usuario_c1 == $usuario) { //Mismo del conteo 1
                                        $flag = 5; //Usuario del conteo 1 no puede hacer el conteo 2
                                    } else {
                                        $diferencia = $c1 - $total;

                                        //Actualizar conteo 2
                                        DB::update(
                                            'UPDATE "' . $base .
                                                '" SET c2 = ?, diferencia = ?, usuario_c2 = ?, tiempo_c2 = ?
                                              WHERE codigo = ? AND descripcion = ? AND ubicacion = ? AND dia = ?',
                                            [
                                                $total, $diferencia, $usuario, $DateAndTime, $codigo,
                                                $descripcion, $ubicacion, $dia
                                            ]
                                        );

                                        //Si la diferencia es distinto de 0, entonces se pasa el reconteo como 0 para
                                        //que se pueda realizar dicho conteo.
                                        if ($diferencia != 0) {

                                            DB::update(
                                                'update "' . $base . '" set reconteo = 0.0 where codigo = ?
                                        and descripcion = ? and ubicacion = ? and dia = ?',
                                                [$codigo, $descripcion, $ubicacion, $dia]
                                            );
                                        } else { //Diferencia es igual a 0. Se establece reconteo automático

                                            DB::update(
                                                'update "' . $base . '" set reconteo = ' . $total .
                                                    'where codigo = ? and descripcion = ? and ubicacion = ? and dia = ?',
                                                [$codigo, $descripcion, $ubicacion, $dia]
                                            );
                                        }

                                        $flag = 3; //Actualizacion normal
                                    }
                                } else { //Hay conteo 2 previo
                                    $total = $c2 + $cantidad;

                                    if ($usuario_c2 == $usuario) { //Editado por el mismo usuario

                                        //Actualizar conteo 2
                                        DB::update(
                                            'update "' . $base . '" set c2 = ' . $total .
                                                'where codigo = ? and descripcion = ? and ubicacion = ? and dia = ?',
                                            [$codigo, $descripcion, $ubicacion, $dia]
                                        );

                                        $diferencia = $c1 - $total;

                                        //Actualizar diferencia
                                        DB::update(
                                            'update "' . $base . '" set diferencia = ' . $diferencia .
                                                'where codigo = ? and descripcion = ? and ubicacion = ? and dia = ?',
                                            [$codigo, $descripcion, $ubicacion, $dia]
                                        );

                                        //Si la diferencia es distinto de 0, entonces se pasa el reconteo como 0 para
                                        //que se pueda realizar dicho conteo.
                                        if ($diferencia != 0) {

                                            DB::update(
                                                'update "' . $base . '" set reconteo = 0.0 where codigo = ?
                                         and descripcion = ? and ubicacion = ? and dia = ?',
                                                [$codigo, $descripcion, $ubicacion, $dia]
                                            );
                                        } else { //Diferencia es igual a 0. Se establece reconteo automático

                                            DB::update(
                                                'update "' . $base . '" set reconteo = ' . $total .
                                                    'where codigo = ? and descripcion = ? and ubicacion = ? and dia = ?',
                                                [$codigo, $descripcion, $ubicacion, $dia]
                                            );
                                        }

                                        $flag = 3; //Actualizacion normal

                                    } else {

                                        $flag = 6; // Solo el usuario_c2 puede editar

                                    }
                                }
                            }
                        } else {

                            $flag = 12; //Si ya existe reconteo, ya no se puede editar el conteo 1 y 2

                        }
                    }
                } else { //Reconteo

                    //Obtener datos
                    foreach (DB::table($base)->select(
                        'usuario_reconteo',
                        'reconteo',
                        'usuario_c2',
                        'c2',
                        'c1',
                        'usuario_c1'
                    )
                        ->where('codigo', $codigo)
                        ->where('descripcion', $descripcion)
                        ->where('dia', $dia)
                        ->where('ubicacion', $ubicacion)
                        ->get() as $data) {

                        $usuario_reconteo = $data->usuario_reconteo;
                        $reconteo = $data->reconteo;
                        $usuario_c2 = $data->usuario_c2;
                        $c2 = $data->c2;
                        $c1 = $data->c1;
                        $usuario_c1 = $data->usuario_c1;
                    }

                    if (empty($c1) || empty($c2)) {

                        $flag = 7; //Se necesita conteo 1 y 2

                    } else {

                        if (empty($reconteo)) { //No hay reconteo previo

                            $total = $cantidad;

                            if ($usuario_c1 == $usuario  || $usuario_c2 == $usuario) { //Mismo del reconteo
                                $flag = 8; //Usuario del conteo 1 o 2 no puede hacer el 3
                            } else {

                                //Actualizar reconteo
                                DB::update(
                                    'update "' . $base . '" set reconteo = ' . $total .
                                        'where codigo = ? and descripcion = ? and ubicacion = ? and dia = ?',
                                    [$codigo, $descripcion, $ubicacion, $dia]
                                );

                                //Actualizar usuario
                                DB::update(
                                    'update "' . $base . '" set usuario_reconteo = ?
                                 where codigo = ? and descripcion = ? and ubicacion = ? and dia = ?',
                                    [$usuario, $codigo, $descripcion, $ubicacion, $dia]
                                );

                                //Actualizar tiempo
                                DB::update(
                                    'update "' . $base . '" set tiempo_reconteo = ?
                                 where codigo = ? and descripcion = ? and ubicacion = ? and dia = ?',
                                    [$DateAndTime, $codigo, $descripcion, $ubicacion, $dia]
                                );

                                $flag = 1; //Guardado normal
                            }
                        } else { //Hay reconteo previo

                            if ($c1 == $c2) { //Si ambos conteos estan igualados, no se admite reconteo.

                                $flag = 9; //No se admite reconteo

                            } else {

                                $total = $reconteo + $cantidad;

                                if ($usuario_reconteo == $usuario) {



                                    //Actualizar reconteo
                                    DB::update(
                                        'update "' . $base . '" set reconteo = ' . $total .
                                            'where codigo = ? and descripcion = ? and ubicacion = ? and dia = ?',
                                        [$codigo, $descripcion, $ubicacion, $dia]
                                    );

                                    $flag = 3; //Actualizacion normal
                                } else {
                                    $flag = 10; //Solo puede editar el usuario del reconteo
                                }
                            }
                        }
                    }
                }
            }
        }

        return response()->json($flag);
    }

    /***************************************************************************************/

    public function show(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'txt_usuario' => 'required|string|max:12',
            'sel_conteo' => 'required|numeric',
        ]);

        try {
             //$confGuardadoData = conf_guardado::select('base', 'dia')->first();
             $base = "test_inventario";
             $dia = "1";
        } catch (Exception $e) {
            error_log($e->getMessage());
        }

        //Eliminar espacios
        $usuario = $request->input('txt_usuario');
        $conteo = $request->input('sel_conteo');

        //Definir conteo para la busqueda
        switch ($conteo) {
            case "1":
                $num_conteo = "c1";
                $usuario_conteo = "usuario_c1";
                $tiempo_conteo = "tiempo";
                break;
            case "2":
                $num_conteo = "c2";
                $usuario_conteo = "usuario_c2";
                $tiempo_conteo = "tiempo_c2";
                break;
            case "3":
                $num_conteo = "reconteo";
                $usuario_conteo = "usuario_reconteo";
                $tiempo_conteo = "tiempo_reconteo";
                break;
        }

        //Dado que el numero de conteo varía, se pone un parámetro ($num_conteo) como cantidad.
        $dato = DB::table($base)
            ->select('codigo', 'descripcion', DB::raw($num_conteo . ' as cantidad'))
            ->where($usuario_conteo, $usuario)
            ->where('dia', $dia)
            ->orderBy($tiempo_conteo, 'desc')
            ->get();

        return response()->json($dato);
    }


    public function update(Request $request, string $id)
    {
    }


    public function destroy(string $id)
    {
    }
}

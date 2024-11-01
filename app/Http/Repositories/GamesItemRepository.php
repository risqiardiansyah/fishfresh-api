<?php

namespace App\Http\Repositories;

use Illuminate\Support\Facades\DB;

class GamesItemRepository
{
    // All item game
    public function allItem()
    {
        $items = DB::table('games_item')->get(['code', 'title', 'game_code', 'ag_code', 'price', 'isActive', 'from']);
        return $items;
    }

    // Add item game
    public function addItem($request)
    {
        $data = [
            'code' => generateFiledCode('ITEM'),
            'title' => $request->title,
            'game_code' => $request->game_code,
            'ag_code' => $request->ag_code,
            'price' => $request->price,
            'isActive' => $request->has('isActive') ? $request->isActive : 1

        ];
        $result = DB::table('games_item')->insert($data);
        return $data;
    }

    // Edit item game
    public function editItem($request, $code)
    {
        $data = [
            'title' => $request->title,
            'game_code' => $request->game_code,
            'ag_code' => $request->ag_code,
            'price' => $request->price,
            'isActive' => $request->isActive

        ];
        DB::table('games_item')->where('code', $code)->update($data);
        return $data;
    }

    // Delete item game
    public function deleteItem($code)
    {
        $result = DB::table('games_item')->where('code', $code)->delete();
        return ['success' => $result];
    }
}

<?php

namespace App\Http\Repositories;

use App\Helpers\Unipin;
use App\Models\Games;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Carbon;

class GamesRepository
{
    public function allGames($request)
    {
        $search = $request->search;

        $games = DB::table('games')->where('is_delete', 0);
        if (!empty($search)) {
            $games = $games->where('title', 'LIKE', '%' . $search . '%');
        }
        $games = $games->orderBy('title', 'asc')->get();

        $result = array();

        foreach ($games as $game) {
            $img = str_replace(" ", "%20", $game->img);
            if (filter_var($img, FILTER_VALIDATE_URL)) {
                $img_path = $game->img;
            } else {
                $img_path = getImage($game->img);
            }

            if (filter_var($game->field_img, FILTER_VALIDATE_URL)) {
                $field_img = $game->field_img;
            } else {
                $field_img = getImage($game->field_img);
            }

            $result[] = [
                'code' => $game->code,
                'title' => $game->title,
                'slug' => $game->slug,
                'category_code' => $game->category_code,
                'img' => $img_path,
                'game_description' => $game->game_description,
                'field_description' => $game->field_description,
                'field_img' => $field_img,
                'from' => $game->from == 'voucher' ? 1 : 0
            ];
        }
        return $result;
    }

    public function detailGames($slug)
    {
        $user = auth('sanctum')->user();
        $game = DB::table('games')->where('slug', $slug)->first();

        if (empty($game)) {
            return [];
        }

        $items = DB::table('games_item')->where('game_code', $game->code)
            ->get(['code', 'title', 'price', 'isActive', 'ag_code', 'price_not_member', 'price_reseller']);
        foreach ($items as $value) {
            if (!$user) {
                $value->price = $value->price_not_member;
            } elseif ($user) {
                if ($user->memberType == 2) {
                    $value->price = $value->price_reseller;
                }
            }

            if ($game->from == 'voucher') {
                $voucher = DB::table('games_item_voucher')
                    ->where('games_item_code', $value->code)
                    ->where('voucher_status', 1)
                    ->where('is_delete', 0)
                    ->count();
                $oncart = 0;
                // if ($user) {
                //     $oncart = DB::table('carts')
                //         ->where('users_code', $user->users_code)
                //         ->where('item_code', $value->code)
                //         ->sum('quantity');
                // }
                $stockHold = getStockHold($value->code);
                $stock = ($voucher - $oncart - $stockHold);
                if ($stock <= 0) {
                    $value->isActive = 0;
                    $value->stock = 0;
                } else {
                    $value->stock = $stock;
                }
            }

            unset($value->price_not_member);
            unset($value->price_reseller);
        }
        $game_item = $items;

        if (filter_var($game->img, FILTER_VALIDATE_URL)) {
            $img_path = $game->img;
        } else {
            // $img_path = asset('storage/gamesimg/' . $game->img);
            $img_path = getImage($game->img);
        }

        if (filter_var($game->field_img, FILTER_VALIDATE_URL)) {
            $field_img = $game->field_img;
        } else {
            $field_img = getImage($game->field_img);
        }

        $fields = DB::table('fields')->select('name', 'type', 'display_name')->where('game_code', $game->code)->get();
        foreach ($fields as $value) {
            $value->data = [];
            if ($value->type == 'dropdown') {
                $dropdown = DB::table('fields_data')
                    ->where('game_code', $game->code)
                    ->where('fields_name', $value->name)
                    ->get();
                $value->data = $dropdown;
            }
        }

        $data['code'] = $game->code;
        $data['title'] = $game->title;
        $data['category_code'] = $game->category_code;
        $data['img'] = $img_path;
        $data['game_item'] = $game_item;
        $data['fields'] = $fields;
        $data['user'] = $user;
        $data['game_description'] = $game->game_description;
        $data['field_description'] = $game->field_description;
        $data['lg_variant'] = $game->lg_variant;
        $data['field_img'] = $field_img;
        $data['meta_title'] = $game->meta_title;
        $data['meta_desc'] = $game->meta_desc;
        $data['meta_keyword'] = $game->meta_keyword;

        return $data;
    }

    public function addGame($request)
    {
        $title = $request->title;
        $categoryCode = $request->category_code;
        $gameDesc = $request->game_description;
        $fieldDesc = $request->field_description;

        if ($request->hasFile('img')) {
            $image = $request->file('img');
            $imageName = file_get_contents($image->getRealPath());
            $imageGame = uploadFotoWithFileName(base64_encode($imageName), 'GM', 'gamesimg');
        } else {
            $imgUrl = $request->img;
            if (filter_var($imgUrl, FILTER_VALIDATE_URL)) {
                $imageGame = $imgUrl;
            }
        }
        if ($request->hasFile('field_img')) {
            $imageData = $request->file('field_img');
            $imageName = file_get_contents($imageData->getRealPath());
            $imageField = uploadFotoWithFileName(base64_encode($imageName), 'GM', 'gamesfield_img');
        } else {
            $fieldImageUrl = $request->field_img;
            if (filter_var($fieldImageUrl, FILTER_VALIDATE_URL)) {
                $imageField = $fieldImageUrl;
            }
        }
        $data = [
            'code' => generateFiledCode('GAME'),
            'title' => $title,
            'category_code' => $categoryCode,
            'img' => $imageGame,
            'game_description' => $gameDesc,
            'field_description' => $fieldDesc,
            'field_img' => $imageField
        ];
        DB::table('games')->insert($data);
        return $data;
    }

    public function deleteGame($code)
    {
        $game = DB::table('games')->where('code', $code)->first();
        $image_path = storage_path('app/public/gamesimg/' . $game->img);
        if (file_exists($image_path)) {
            unlink($image_path);
        }
        $deleted = DB::table('games')->where('code', $code)->delete();
        return $deleted;
    }

    public function editGame($request, $code)
    {
        $game = DB::table('games')->where('code', $code)->first();
        if ($request->hasFile('img')) {
            $image_path = storage_path('app/public/gamesimg/' . $game->img);
            unlink($image_path);

            $image = $request->file('img');
            $imageName = file_get_contents($image->getRealPath());
            $imageFile = uploadFotoWithFileName(base64_encode($imageName), 'GM', 'gamesimg');
            $data['img'] = $imageFile;
        }
        if ($request->hasFile('field_img')) {
            $image_path = storage_path('app/public/gamesfield_img/' . $game->field_img);
            unlink($image_path);

            $image = $request->file('field_img');
            $imageName = file_get_contents($image->getRealPath());
            $imageFile = uploadFotoWithFileName(base64_encode($imageName), 'GM', 'gamesfield_img');
            $data['field_img'] = $imageFile;
        }
        $data['title'] = $request->title;
        $data['category_code'] = $request->category_code;
        $data['game_description'] = $request->game_description;
        $data['field_description'] = $request->field_description;
        $data['updated_at'] = Carbon::now();
        DB::table('games')->where('code', $code)->update($data);
        return $data;
    }

    public function gamesByCategory($code)
    {
        $games = DB::table('games')->where('category_code', $code)->get();

        $result = array();

        foreach ($games as $game) {
            if (filter_var($game->img, FILTER_VALIDATE_URL)) {
                $img_path = $game->img;
            } else {
                // $img_path = asset('storage/gamesimg/' . $game->img);
                $img_path = getImage($game->img);
            }

            if (filter_var($game->field_img, FILTER_VALIDATE_URL)) {
                $field_img = $game->field_img;
            } else {
                $field_img = getImage($game->field_img);
            }

            $result[] = [
                "id" => $game->id,
                'img' => $img_path,
                'title' => $game->title,
                'code' => $game->code,
                'category_code' => $game->category_code,
                'created_at' => $game->created_at,
                'updated_at' => $game->updated_at,
                'game_description' => $game->game_description,
                'field_description' => $game->field_description,
                'field_img' => $field_img,
            ];
        }
        return $result;
    }

    public function saveGameSync($request)
    {
        $data = [
            'img' => $request->icon_url,
            'title' => $request->game_name,
            'code' => $request->game_code,
            'from' => 'unipin',
            // 'category_code' => 'MG',
        ];

        $cek = DB::table('games')->where('code', $request->game_code)->exists();
        if ($cek) {
            return $data;
        }
        $data['slug'] = clean($data['title']);

        DB::table("games")->insert($data);

        Unipin::gameDetail($request, false, true);
        
        return $data;
    }

    public static function saveGameItemSync($request)
    {
        $gameCode = $request->game_code;
        $title = $request->title;
        $price = $request->price;
        $priceUnipin = $request->price_unipin;
        $priceNotMember = $request->price_not_member;
        $priceReseller = $request->price_reseller;
        $denominationId = $request->denomination_id;

        $data = [
            'code' => generateFiledCode('GAMESITEM'),
            'game_code' => $gameCode,
            'title' => $title,
            'price' => $price,
            'price_unipin' => $priceUnipin,
            'price_not_member' => $priceNotMember,
            'price_reseller' => $priceReseller,
            'denomination_id' => $denominationId,
            'from' => 'unipin'
        ];
        $cek = DB::table('games')->where('code', $gameCode)->exists();
        if ($cek) {
            DB::table("games_item")->insert($data);
            return $data;
        }
        return $data;
    }
}

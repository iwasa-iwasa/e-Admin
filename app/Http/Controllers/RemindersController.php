<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use Illuminate\Http\Request;

class RemindersController extends Controller
{
    public function index()
    {
        // データベースの 'products' テーブルから全てのレコードを取得
        $products = Reminder::all();

        // 取得したデータを 'products.index' ビューに渡して表示
        return view('products.index', [
            'products' => $products
        ]);
    }
}

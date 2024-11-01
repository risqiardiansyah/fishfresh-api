<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('home');
    }

    public function noRoute()
    {
        // dd(date('Y-m-d H:i:s'));
        return response()->json([
            'message' => 'no route and no API found with those values',
        ], 400);
    }

    public function notAuth()
    {
        return response()->json([
            'message' => 'Not Authenticate.',
        ], 401);
    }

    public function notFound()
    {
        return response()->json([
            'message' => 'Not Found.',
        ], 404);
    }

    public function sendResponseFile($path)
    {
        return response()->file($path);
    }

    public function redirect()
    {
        return Redirect::to('https://www.vogaon.com/');
    }

    public function getImage($folder = '', $img = '')
    {
        try {
            $file_name = $img;
            $storagePath  = Storage::disk('public')->getDriver()->getAdapter()->getPathPrefix();
            $fullPath = $storagePath . '/';
            if ($folder) {
                $fullPath .= $folder . '/';
            }
            $fullPath .= $file_name;

            return $this->sendResponseFile($fullPath);
        } catch (\Exception $e) {
            return $this->notFound();
        }
    }

    public function getImageNested($folder1 = '', $folder2 = '', $img = '')
    {
        try {
            $file_name = $img;
            $storagePath  = Storage::disk('public')->getDriver()->getAdapter()->getPathPrefix();
            $fullPath = $storagePath . '/';
            if ($folder1) {
                $fullPath .= $folder1 . '/';
            }
            if ($folder2) {
                $fullPath .= $folder2 . '/';
            }
            $fullPath .= $file_name;

            return $this->sendResponseFile($fullPath);
        } catch (\Exception $e) {
            return $this->notFound();
        }
    }
}

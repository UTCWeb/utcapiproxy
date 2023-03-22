<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WPRestCache extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  string  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // Checks if the response key json exist already or it fetches a new one.
        $wpResponse = null;
        if (Cache::has('wpResponsekey'.$id)) {
            dd("cached content");
            $wpResponse = Cache::pull('wpResponsekey'.$id);
            return $wpResponse;
        }
        // ])->get("https://blog.utc.edu/".$id."/wp-json/wp/v2/posts?_embed&per_page=".$id)->json();
        // $wpResponse = Http::withHeaders([
        // ])->get("https://blog.utc.edu/".$id."/wp-json/wp/v2/posts?_embed&per_page=".$id)->json();
        // Cache::put('wpResponsekey'.$id, $wpResponse, now()->addMinutes(10));
        // return $wpResponse;

        // $id = $request->input('id');
        //  dd($id);
        $wpResponse = Http::withHeaders([])->get("https://blog.utc.edu/".$id."/wp-json/wp/v2/posts", [
            '_embed' => '1',
            'per_page' => '4',
            // 'categories' => 4,
        ])->json();

        Cache::put('wpResponsekey'.$id, $wpResponse, now()->addMinutes(10));
        return $wpResponse;
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
        //
        // Checks if the response key json exist already or it fetches a new one.
        $wpResponse = $wpqueryparameters = $wpper_page = $wpcategories = null;
        $wpper_page = $request->input('per_page') ?? '4';
        $wpcategories = $request->input('categories');
        $wpqueryparameters = implode(",", array_filter([$wpper_page, $wpcategories])) ;
        if($wpper_page > 12 ? $wpper_page = 12 : $wpper_page);
        if (Cache::has('wpResponsekey'.$id.$wpqueryparameters)) {
            dd("cached content");
            $wpResponse = Cache::pull('wpResponsekey'.$id.$wpqueryparameters);
            return $wpResponse;
        }
        // ])->get("https://blog.utc.edu/".$id."/wp-json/wp/v2/posts?_embed&per_page=".$id)->json();
        // $wpResponse = Http::withHeaders([
        // ])->get("https://blog.utc.edu/".$id."/wp-json/wp/v2/posts?_embed&per_page=".$id)->json();
        // Cache::put('wpResponsekey'.$id, $wpResponse, now()->addMinutes(10));
        // return $wpResponse;

        // $id = $request->input('id');
        // dd($request->input('per_page'));
        if ($wpcategories!= null) {
            $wpResponse = Http::withHeaders([])->get("https://blog.utc.edu/".$id."/wp-json/wp/v2/posts", [
                '_embed' => '1',
                'per_page' => $wpper_page,
                'categories' =>  $wpcategories,
            ])->json();

            Cache::put('wpResponsekey'.$id.$wpqueryparameters, $wpResponse, now()->addMinutes(60));
            return $wpResponse;
        }

        $wpResponse = Http::withHeaders([])->get("https://blog.utc.edu/".$id."/wp-json/wp/v2/posts", [
            '_embed' => '1',
            'per_page' => $wpper_page,
        ])->json();

        Cache::put('wpResponsekey'.$id.$wpqueryparameters, $wpResponse, now()->addMinutes(60));
        return $wpResponse;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}

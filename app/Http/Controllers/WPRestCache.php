<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WPRestCache extends Controller
{
    /**
     * Update the specified resource under storage/cache
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
        // Checks if the response key json exist already or it fetches a new one.
        // It uses web query paraemeters to determine if more info is needed or
        // it defaults to 4 items with any category on a given blog id(name).
        $wpResponse = $wpqueryparameters = $wpper_page = $wpcategories = null;
        $wpper_page = $request->input('per_page') ?? '4';
        $wpcategories = $request->input('categories');
        $wpqueryparameters = implode(",", array_filter([$wpper_page, $wpcategories])) ;
        if ($wpper_page > 12 ? $wpper_page = 12 : $wpper_page);
        if (Cache::has('wpResponsekey'.$id.$wpqueryparameters)) {
            dd("cached content");
            $wpResponse = Cache::pull('wpResponsekey'.$id.$wpqueryparameters);
            return $wpResponse;
        }

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
}

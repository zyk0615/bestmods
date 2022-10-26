<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('global', ['page' => 'index']);
});

Route::get('/retrieve', function() {
    $mods = Db::table('mods')->join('games', 'mods.game', '=', 'games.id')->join('seeds', 'mods.seed', '=', 'seeds.id')->select(array('mods.id', 'games.name AS gname', 'games.name_short AS gname_short', 'mods.name AS name', 'seeds.name AS sname', 'description_short', 'mods.url AS murl', 'seeds.url AS surl', 'custom_url', 'mods.image AS mimage', 'seeds.image AS simage', 'downloads', 'created_at', 'updated_at', 'rating', 'total_downloads', 'total_views', 'games.image AS gimage'))->get();

    $json = array('data' => array());
    
    // We have to format it for DataTables.
    foreach ($mods as $mod)
    {
        $json['data'][] = array($mod->id, $mod->mimage, $mod->name, $mod->description_short, $mod->gname, $mod->sname, $mod->rating, $mod->total_downloads, $mod->total_views, $mod->murl, $mod->surl, $mod->custom_url, '', $mod->gimage, $mod->simage);
    }

    return json_encode($json);
});

Route::get('/view/{mod}', function ($mod) {
    // Assume we're firstly loading based off of custom URL.
    $mod_db = Db::table('mods')->where('custom_url', $mod);

    // If we're invalid, try searching by ID.
    $mod_db = ($mod_db->count() < 1) ? Db::table('mods')->where('mods.id', intval($mod)) : $mod_db;
    
    $mod_db = ($mod_db->count() > 0) ? $mod_db->join('games', 'mods.game', '=', 'games.id')->join('seeds', 'mods.seed', '=', 'seeds.id')->paginate(1, array('mods.id', 'games.name AS gname', 'mods.name AS name', 'seed', 'description', 'description_short', 'mods.url AS murl', 'seeds.url AS surl', 'custom_url', 'mods.image AS mimage', 'seeds.image AS simage', 'install_help', 'downloads', 'screenshots', 'created_at', 'updated_at', 'rating', 'total_downloads', 'total_views', 'seeds.name AS sname'))->first() : NULL;

    return view('global', ['page' => 'view', 'mod' => $mod_db]);
});

Route::get('/card/{mod}', function ($mod) {
    $mod_db = Db::table('mods')->where('mods.id', intval($mod));
    
    $mod_db = ($mod_db->count() > 0) ? $mod_db->join('games', 'mods.game', '=', 'games.id')->join('seeds', 'mods.seed', '=', 'seeds.id')->paginate(1, array('mods.id', 'games.name AS gname', 'mods.name AS name', 'seed', 'description', 'description_short', 'mods.url AS murl', 'seeds.url AS surl', 'custom_url', 'mods.image AS mimage', 'seeds.image AS simage', 'install_help', 'downloads', 'screenshots', 'created_at', 'updated_at', 'rating', 'total_downloads', 'total_views', 'seeds.name AS sname'))->first() : NULL;

    return view('modCard', ['mod' => $mod_db]);
});
<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Mod;
use App\Models\Seed;
use App\Models\User;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

class HomeController extends Controller
{
    public function __construct()
    {
    }

    public function index()
    {
        $img = '/images/bestmods-filled.png';
        $icon = '/images/bestmods-icon.png';

        $base_url = Url::to('/');

        $headinfo = array(
            'image' => Url::to($img),
            'icon' => Url::to($icon),
            'url' => $base_url
        );

        return view('home.index', ['headinfo' => $headinfo, 'base_url' => $base_url]);
    }

    public function retrieve()
    {

        $page = Request::get('page', 1);
        $numPerPage = env('MODS_PER_PAGE', 50);

        $start = ($page == 1) ? 0 : (($page - 1) * $numPerPage) - 1;

        $searchVal = Request::get('s', '');

        // Fake rows.
        $fakeRows = env('FAKE_ROWS', false);
        $fakeRowsCnt = env('FAKE_ROWS_CNT', 100000);

        $mods = Mod::select([
            'mods.id AS id',
            'seeds.id AS sid',
            'games.id AS gid',
            'mods.name AS mname',
            'description_short',
            'seeds.name AS sname',
            'games.name AS gname',
            'games.name_short AS gnameshort',
            'mods.total_views AS total_views',
            'mods.total_downloads AS total_downloads',
            'mods.rating AS rating',
            'mods.url AS url',
            'mods.custom_url AS custom_url',
            'mods.image as mimage',
            'seeds.classes AS sclasses',
            'games.classes AS gclasses',
            'seeds.url AS surl',
            'games.image AS gimage',
            'seeds.image AS simage',
            'seeds.image_banner AS simage_banner',
            'seeds.protocol AS sprotocol'
        ])->join('seeds', 'mods.seed', '=', 'seeds.id')->join('games', 'mods.game', '=', 'games.id');

        if (strlen($searchVal) > 0) {
            $cols = array(
                'mods.name',
                'games.name_short',
                'mods.description_short',
                'seeds.name',
                'games.name'
            );

            foreach ($cols as $col) {
                $mods = $mods->orWhere($col, 'like', '%' . $searchVal . '%');
            }
        }

        $mods = $mods->skip(($fakeRows) ? 0 : $start)->take($numPerPage)->get();

        $json = [];

        // We have to format it for DataTables.
        foreach ($mods as $mod) {
            $data = [];

            // Firstly, decide the image.
            $img = asset('images/default_mod.png');

            if (!empty($mod->simage_banner)) {
                $img = asset('storage/images/seeds/' . $mod->simage_banner);
            }

            if (!empty($mod->mimage)) {
                $img = asset('storage/images/mods/' . $mod->mimage);
            }

            // Return parsed output.
            $data['id'] = $mod->id;

            // Basic mod info (image, name, and short description).
            $data['image'] = '<img class="card-image" src="' . $img . '" alt="Mod Image"></img>';
            $data['name'] = '<h1 class="text-3xl font-bold text-center"><a href="/view/' . $mod->custom_url . '" class="hover:underline">' . $mod->mname . '</a></h1>';
            $data['description'] = $mod->description_short;

            // Game and seed.
            // First generate seed link (create proper link using protocol, etc.).
            $seedProto = isset($mod->sprotocol) ? $mod->sprotocol : 'https';

            $seedLink = $seedProto . '://' . $mod->surl;

            $gameIcon = isset($mod->gimage) ? asset('storage/images/games/' . $mod->gimage) : '';
            $seedIcon = isset($mod->simage) ? asset('storage/images/seeds/' . $mod->simage) : '';

            $data['game'] = '<div class="card-seed"><img class="card-icon" src="' . $gameIcon . '" alt="Icon" /> ' . $mod->gname . '</div>';
            $data['seed'] = '<div class="card-seed"><img class="card-icon" src="' . $seedIcon . '" alt="Icon" /> <a href="' . $seedLink . '" class="hover:underline" target="_blank">' . $mod->sname . '</a></div>';

            // Stats (total views, downloads, and stars/rating).
            $data['stats'] = '<div class="card-icons"><div class="card-icon-div text-center"><svg class="card-icon" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2" d="M12 21c-5 0-11-5-11-9s6-9 11-9s11 5 11 9s-6 9-11 9zm0-14a5 5 0 1 0 0 10a5 5 0 0 0 0-10h0z"></path></svg> <span class="card-icon-text">' . $mod->total_views . '</span></div> <div class="card-icon-div text-center"><svg class="card-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z" clip-rule="evenodd"></path></svg> <span class="card-icon-text">' . $mod->rating . '</span></div> <div class="card-icon-div text-center"><svg class="card-icon" xmlns="http://www.w3.org/2000/svg" fill="currentColor" stroke="none" viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 8zM4 19h16v2H4z"></path></svg> <span class="card-icon-text">' . $mod->total_downloads . '</span></div></div>';

            // Bottom buttons.
            $viewLink = '/view/' . $mod->custom_url;
            $origLink = $seedLink . '/' . $mod->url;

            $data['buttons'] = '<div class="flex flex-col text-center"><a href="' . $viewLink . '" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2 mt-2">View</a> <a href="' . $origLink . '" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2 mt-2" target="_blank">Original</a></div>';

            // Classes.
            $data['classes'] = 'card-style-default';

            if ($mod->sclasses || $mod->gclasses) {
                $data['classes'] = $mod->sclasses . ' ' . $mod->gclasses;
            }


            if ($fakeRows) {
                $offset = ($page - 1) * $numPerPage;
                $newId = (!$offset || $page == 1) ? 1 : ($offset + 1);

                $data['oldname'] = $data['name'];
                $data['name'] = $data['name'] . ' ' . $newId . ' (orig)';
                $data['id'] = $newId;

                // Check if we need more.
                $offset = ($page - 1) * $numPerPage;
                $newMaxCnt = $offset + $numPerPage;

                // Return nothing if we've hit our max.
                if ($newMaxCnt > $fakeRowsCnt) {
                    return json_encode($json);
                }
            }

            $json[] = $data;

            // For testing...
            if ($fakeRows) {
                $data['name'] = $data['oldname'];

                for ($i = 1; $i < $numPerPage; $i++) {
                    $dup = $data;

                    $dup['id'] = $dup['id'] + $i;
                    $dup['name'] = $dup['name'] . ' ' . $dup['id'];

                    $json[] = $dup;
                }
            }
        }

        return htmlspecialchars(json_encode($json), ENT_NOQUOTES);
    }

    /**
     * todo
     * maybe can optimization.
     *
     */
    public function viewMod($mod, $view = null)
    {

        if (empty($view)) {
            $view = Request::get('view', 'overview');
        }

        // Assume we're firstly loading based off of custom URL.
        $mod = Mod::with('seedReal')->with('gameReal')->where('custom_url', $mod)->get()->first();

        // If we're invalid, try searching by ID.
        $mod = ($mod->exists) ? $mod : Mod::with('seedReal')->with('gameReal')->where('id', intval($mod));

        $type = null;
        $id = null;
        $gameReal = null;
        $seedReal = null;
        $image = null;
        $icon = null;
        $name = null;
        $name_short = null;
        $protocol = null;
        $url = null;
        $custom_url = null;
        $description = null;
        $description_short = null;
        $install_help = null;
        $downloads = null;
        $screenshots = null;
        $games = null;
        $seeds = null;
        $classes = null;

        $item_created = false;

        // If we're in edit mode, fill out needed variables.
        if ($view == 'edit') {
            $edited = Request::get('edited', false);

            if ($edited) {
                $item_created = true;
            }

            $type = 'mod';
            $id = $mod->id;
            $name = $mod->name;
            $image = $mod->image;
            $protocol = $mod->protocol;
            $url = $mod->url;
            $custom_url = $mod->custom_url;
            $description = $mod->description;
            $description_short = $mod->description_short;
            $install_help = $mod->install_help;


            $downloads = array();
            $screenshots = array();

            $gameReal = $mod->gameReal;
            $seedReal = $mod->seedReal;

            $classes = $seedReal->classes;

            $games = Game::all();
            $seeds = Seed::all();

            $dls = json_decode($mod->downloads, true);

            if ($dls && is_array($dls)) {
                foreach ($dls as $dl) {
                    $downloads[] = array('name' => $dl['name'], 'url' => $dl['url']);
                }
            }

            $SSs = json_decode($mod->screenshots);

            if ($SSs && is_array($SSs)) {
                foreach ($SSs as $ss) {
                    $screenshots[] = $ss;
                }
            }
        } else {
            // Increment view count.
            $mod->update(array('total_views' => $mod->total_views + 1));

            // Firstly, decide the image.
            $image = asset('images/default_mod.png');

            if (!empty($mod->seedReal->image_banner)) {
                $image = asset('storage/images/seeds/' . $mod->seedReal->image_banner);
            }

            if (!empty($mod->image)) {
                $image = asset('storage/images/mods/' . $mod->image);
            }

            $icon = 'bestmods-icon.png';

            $key = 'mod_desc.' . $mod->id;

            $description = Cache::remember($key, 8640, function () use ($mod) {
                return $mod->description;
            });

            $key = 'mod_install.' . $mod->id;

            $install_help = Cache::remember($key, 8640, function () use ($mod) {
                return $mod->install_help;
            });

            // Parse screenshots.
            $screenshots = json_decode($mod->screenshots, true);

            // Loop through each and replace with index.
            if (is_array($screenshots)) {
                $i = 1;

                foreach ($screenshots as $screenshot) {
                    $html = '<img class="modScreenshot" src="' . $screenshot . '" alt="screenshot" />';

                    // Replace instances in description and install.
                    $description = str_replace('{' . $i . '}', $html, $description);
                    $install_help = str_replace('{' . $i . '}', $html, $install_help);
                }
            }

            // Parse downloads.
            $downloads = json_decode($mod->downloads, true);

            // Loop through each and replace with index.
            if (is_array($downloads)) {
                $i = 1;

                foreach ($downloads as $download) {
                    $html = '<a class="modDownload" href="' . $download['url'] . '" target="_blank">' . $download['name'] . '</a>';

                    // Replace instances in description and install.
                    $description = str_replace('{' . $i . '}', $html, $description);
                    $install_help = str_replace('{' . $i . '}', $html, $install_help);
                }
            }

            $description = new HtmlString(Markdown::parse($description));
            $install_help = new HtmlString(Markdown::parse($install_help));
        }

        $base_url = Url::to('/view', array('mod' => $mod->custom_url));

        $headinfo = array(
            'title' => $mod->name . ' - Best Mods',
            'robots' => 'index, nofollow',
            'type' => 'article',
            'image' => $image,
            'icon' => Url::to('/images' . $icon),
            'description' => $mod->description_short,
            'item1' => $mod->total_views,
            'item2' => $mod->total_downloads,
            'url' => ($view == 'overview') ? $base_url : Url::to(
                '/view',
                array('mod' => $mod->custom_url, 'view' => $view)
            )
        );

        return view('home.viewMod', [
            'mod' => $mod,
            'view' => $view,
            'headinfo' => $headinfo,
            'base_url' => $base_url,
            'type' => $type,
            'id' => $id,
            'name' => $name,
            'name_short' => $name_short,
            'image' => $image,
            'protocol' => $protocol,
            'url' => $url,
            'custom_url' => $custom_url,
            'description' => $description,
            'install_help' => $install_help,
            'description_short' => $description_short,
            'downloads' => $downloads,
            'screenshots' => $screenshots,
            'games' => $games,
            'seeds' => $seeds,
            'classes' => $classes,
            'seedReal' => $seedReal,
            'gameReal' => $gameReal,
            'item_created' => $item_created
        ]);
    }

    /**
     *
     */
    public function create($type = null)
    {
        $auth0user = Auth::user();
        $db_user = User::find($auth0user->getAttribute('id'));

        // We'll use @can in the template in the future so it isn't just a blank page.
        if (!$db_user || !$db_user->hasRole('Admin')) {
            return 'NO PERMISSION';
        }

        $item_created = false;

        $new_type = Request::get('type', null);
        $mod = null;

        if ($new_type) {
            $id = Request::get('id', -1);

            $image = Request::file('image');
            $image_remove = (Request::get('image-remove', '') == 'on') ? true : false;

            // Handle Mod insert.
            if ($new_type == 'mod') {
                $seed = Request::get('seed', 0);
                $game = Request::get('game', 0);

                $name = Request::get('name', '');
                $url = Request::get('url', '');
                $custom_url = Request::get('custom_url', '');
                $description = Request::get('description', '');
                $description_short = Request::get('description_short', '');
                $install_help = Request::get('install_help', '');

                // Handle downloads (100 is max which should be enough, I hope, lol).
                $downloads = array();

                for ($i = 1; $i <= 100; $i++) {
                    $data = Request::get('download-' . $i . '-name');

                    // If we're not set, #break.
                    if (!$data) {
                        continue;
                    }

                    // We must be set, so add onto downloads array.
                    $downloads[] = array(
                        'name' => Request::get('download-' . $i . '-name', 'Download'),
                        'url' => Request::get('download-' . $i . '-url', null),
                    );
                }

                // Handle screenshots (50 is max which should be enough, I hope, lololozlzozlzozlzo).
                $screenshots = array();

                for ($i = 1; $i <= 100; $i++) {
                    $data = Request::get('screenshot-' . $i . '-url', null);

                    // If we're not set, #break.
                    if (!$data) {
                        continue;
                    }

                    // We must be set, so add onto screenshots array.
                    $screenshots[] = $data;
                }

                $info = [
                    'seed' => $seed,
                    'game' => $game,

                    'name' => $name,
                    'url' => $url,
                    'custom_url' => $custom_url,
                    'description' => $description,
                    'description_short' => $description_short,
                    'install_help' => $install_help,

                    'image' => '',

                    'downloads' => json_encode($downloads),
                    'screenshots' => json_encode($screenshots),

                    'rating' => 0,
                    'total_downloads' => 0,
                    'total_views' => 0
                ];

                // Create or update.
                if ($id < 1) {
                    $mod = Mod::create($info);
                    $item_created = true;
                } else {
                    unset($info['rating']);
                    unset($info['total_downloads']);
                    unset($info['total_views']);

                    if (!$image_remove) {
                        unset($info['image']);
                    }

                    // Retrieve and update if exists.
                    $mod = Mod::where('id', $id)->get()->first();

                    if ($mod->exists) {
                        $mod->update($info);
                        $item_created = true;
                    }
                }

                if ($image != null) {
                    $ext = $image->clientExtension();

                    $imgName = $mod->id . '.' . $ext;
                    $mod->image = $imgName;

                    $image->storePubliclyAs('images/mods', $imgName, 'public');

                    $mod->save();
                }
            } elseif ($new_type == 'seed') {
                $name = Request::get('name', 'Seed');
                $protocol = Request::get('protocol', 'https');
                $url = Request::get('url', 'moddingcommunity.com');
                $classes = Request::get('classes', '');

                $image_banner = Request::file('image_banner');
                $image_banner_remove = (Request::get('image_banner-remove', '') == 'on') ? true : false;

                $info = [
                    'name' => $name,
                    'protocol' => $protocol,
                    'url' => $url,
                    'image' => '',
                    'image_banner' => '',
                    'classes' => ($classes) ? $classes : ''
                ];

                $seed = null;

                // Create or update.
                if ($id < 1) {
                    $addInfo = $info;
                    array_splice($addInfo, 2, 1);

                    if (!$image_remove) {
                        unset($addInfo['image']);
                    }

                    if (!$image_banner_remove) {
                        unset($addInfo['image_banner']);
                    }

                    $seed = Seed::updateOrCreate(['url' => $url], $addInfo);
                    $item_created = true;
                } else {
                    // Retrieve and update if exists.
                    $seed = Seed::find($id);

                    if (!$image_remove) {
                        unset($info['image']);
                    }

                    if (!$image_banner_remove) {
                        unset($info['image_banner']);
                    }

                    if ($seed->exists) {
                        $seed->update($info);
                        $item_created = true;
                    }
                }

                if ($image != null) {
                    $ext = $image->clientExtension();

                    $imgName = strtolower($seed->url) . '.' . $ext;
                    $seed->image = $imgName;

                    $image->storePubliclyAs('images/seeds', $imgName, 'public');

                    $seed->save();
                }

                if ($image_banner != null) {
                    $ext = $image_banner->clientExtension();

                    $imgName = strtolower($seed->url) . '_full.' . $ext;
                    $seed->image_banner = $imgName;

                    $image_banner->storePubliclyAs('images/seeds', $imgName, 'public');

                    $seed->save();
                }
            } else {
                $name = Request::get('name', 'Game');
                $name_short = Request::get('name_short', 'Game Short');
                $classes = Request::get('classes', '');

                $info = [
                    'name' => $name,
                    'name_short' => $name_short,
                    'image' => '',
                    'classes' => ($classes) ? $classes : ''
                ];

                $game = null;

                // Create or update.
                if ($id < 1) {
                    $addInfo = $info;
                    array_splice($addInfo, 1, 1);

                    if (!$image_remove) {
                        unset($addInfo['image']);
                    }

                    $game = Game::updateOrCreate(['name_short' => $name_short], $addInfo);
                    $item_created = true;
                } else {
                    // Retrieve and update if exists.
                    $game = Game::where('id', $id)->get()->first();

                    if (!$image_remove) {
                        unset($info['image']);
                    }

                    if ($game->exists) {
                        $game->update($info);
                        $item_created = true;
                    }
                }

                if ($image != null) {
                    $ext = $image->clientExtension();

                    $imgName = strtolower($game->name_short) . '.' . $ext;
                    $game->image = $imgName;

                    $image->storePubliclyAs('images/games', $imgName, 'public');

                    $game->save();
                }
            }

            // Before redirecting, destroy caches for description and install.
            if (isset($mod) && $mod) {
                $key = 'mod_desc.' . $mod->id;

                Cache::forget($key);

                $key = 'mod_install.' . $mod->id;

                Cache::forget($key);

                return redirect(Url::to('/view', array('mod' => $mod->custom_url, 'view' => 'edit')) . '?edited=1');
            }
        }

        $base_url = Url::to('/create');

        $headinfo = array(
            'title' => 'Submit - Best Mods',
            'robots' => 'index, nofollow',
            'type' => 'article',
            'url' => $base_url . '/' . $type
        );

        $games = Game::all();
        $seeds = Seed::all();

        return view('home.create', [
            'headinfo' => $headinfo,
            'base_url' => $base_url,
            'games' => $games,
            'seeds' => $seeds,
            'type' => $type,
            'item_created' => $item_created
        ]);
    }
}

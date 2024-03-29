<?php

namespace TCG\Voyager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use TCG\Voyager\Events\MenuDisplay;
use TCG\Voyager\Facades\Voyager;

/**
 * @todo: Refactor this class by using something like MenuBuilder Helper.
 */
class Menu extends Model
{
    protected $table = 'menus';

    protected $guarded = [];

    public static function boot()
    {
        parent::boot();

        static::saved(function ($model) {
            $model->removeMenuFromCache();
        });

        static::deleted(function ($model) {
            $model->removeMenuFromCache();
        });
    }

    public function items()
    {
        return $this->hasMany(Voyager::modelClass('MenuItem'));
    }

    public function parent_items()
    {
        return $this->hasMany(Voyager::modelClass('MenuItem'))
            ->whereNull('parent_id');
    }

    /**
     * Display menu.
     *
     * @param string $menuName
     * @param string|null $type
     * @param array $options
     *
     * @return string
     */
    public static function display($menuName, $type = null, array $options = [])
    {
        // GET THE MENU - sort collection in blade
        $menu = \Cache::remember('voyager_menu_' . $menuName, \Carbon\Carbon::now()->addDays(30), function () use ($menuName) {
            return static::where('name', '=', $menuName)
                ->with(['parent_items.children' => function ($q) {
                    $q->orderBy('order');
                }])
                ->first();
        });

        // Check for Menu Existence
        if (!isset($menu)) {
            return false;
        }

        event(new MenuDisplay($menu));

        // Convert options array into object
        $options = (object)$options;

        $items = $menu->parent_items->sortBy('order');

        if ($menuName == 'admin' && $type == '_json') {
            $items = static::processItems($items);
        }

        if ($type == 'admin') {
            $type = 'voyager::menu.' . $type;
        } else {
            if (is_null($type)) {
                $type = 'voyager::menu.default';
            } elseif ($type == 'bootstrap' && !view()->exists($type)) {
                $type = 'voyager::menu.bootstrap';
            }
        }

        if (!isset($options->locale)) {
            $options->locale = app()->getLocale();
        }

        if ($type === '_json') {
            return $items;
        }

        return new \Illuminate\Support\HtmlString(
            \Illuminate\Support\Facades\View::make($type, ['items' => $items, 'options' => $options])->render()
        );
    }

    public function removeMenuFromCache()
    {
        \Cache::forget('voyager_menu_' . $this->name);
    }

    private static function processItems($items)
    {
        $items = $items->transform(function ($item) {
            // Translate title
            $item->title = $item->getTranslatedAttribute('title');
            // Resolve URL/Route
            $item->href = $item->link();

            if (url($item->href) == url()->current() && $item->href != '') {
                // The current URL is exactly the URL of the menu-item
                $item->active = true;
            } elseif (starts_with(url()->current(), str_finish(url($item->href), '/'))) {
                // The current URL is "below" the menu-item URL. For example "admin/posts/1/edit" => "admin/posts"
                $item->active = true;
            }

            if (($item->href == '' || url($item->href) == route('voyager.dashboard')) && $item->children->count() > 0) {
                // Exclude sub-menus
                $item->active = false;
            } elseif (url($item->href) == route('voyager.dashboard') && url()->current() != route('voyager.dashboard')) {
                // Exclude dashboard
                $item->active = false;
            }

            if ($item->children->count() > 0) {
                $item->children = static::processItems($item->children);

                if (!$item->children->where('active', true)->isEmpty()) {
                    $item->active = true;
                }
            }

            if (strpos($item->href, config('app.endpoin_url')) !== true) {
                $item->href = config('app.endpoin_url') . $item->href;
            }

            if(substr( $item->route, 0, strlen("voyager") ) === "voyager" && substr( $item->route, strlen("index")*-1, strlen("index") ) === "index"){
                if (Auth::user()->can('browse', $item) || Auth::user()->hasRole('admin')) {
                    $item->allow = true;
                } else {
                    if($item->route == null && $item->children->count() > 0){
                        $item->allow = true;
                    }
                }
            }else{
                if (Auth::user()->hasPermission($item->route) || Auth::user()->hasRole('admin')) {
                    $item->allow = true;
                } else {
                    if($item->route == null && $item->children->count() > 0){
                        foreach ($item->children as $index => $child) {
                            if($child->allow==true){
                                $item->allow = true;
                            }
                        }
                    }
                }
            }

            return $item;
        });

        // Filter items by permission
        if (Auth::user()->hasRole('admin')) {
            $items = $items->filter(function ($item) {
                // Filter out empty menu-items
                if ($item->url == '' && $item->route == '' && $item->children->count() == 0) {
                    return false;
                }
                return true;
            });

        } else {
            $items = $items->filter(function ($item) {
                return !$item->children->isEmpty()
                    || (Auth::user()->can('browse', $item)
                    || Auth::user()->hasPermission($item->route));
            })->filter(function ($item) {
                // Filter out empty menu-items
                if ($item->url == '' && $item->route == '' && $item->children->count() == 0) {
                    return false;
                }
                return true;
            });
        }

        return $items->values();
    }
}

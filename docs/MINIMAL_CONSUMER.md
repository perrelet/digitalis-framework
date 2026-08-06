# Minimal Consumer Plugin

The minimum a WordPress plugin needs to use the Digitalis Framework is two files: the WP plugin header and an `App` subclass. Everything else — models, views, features, routes — drops into the same directory and is picked up automatically.

---

## File layout

```
my-plugin/
├── my-plugin.php          ← WP plugin header + bootstrap
└── include/
    ├── my-plugin.app.php  ← App subclass (determines autoload root)
    ├── post-types/
    │   └── article.post-type.php
    ├── views/
    │   └── article-card.view.php
    └── _admin/            ← Skipped by default autoload; loaded on is_admin()
        └── article-table.posts-table.php
```

---

## Plugin header file — `my-plugin.php`

```php
<?php
/*
Plugin Name: My Plugin
Description: A plugin built on the Digitalis Framework.
Version: 1.0.0
Author: Your Name
*/

defined('ABSPATH') || exit;

define('MY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MY_PLUGIN_URI',  plugin_dir_url(__FILE__));

require MY_PLUGIN_PATH . 'framework/load.php';
require MY_PLUGIN_PATH . 'include/my-plugin.app.php';

My_Plugin\My_Plugin::get_instance();
```

**Two requires, one bootstrap call:**
1. `framework/load.php` — loads all framework classes and registers WP hooks the framework needs.
2. `include/my-plugin.app.php` — loads the plugin's own `App` subclass.
3. `My_Plugin::get_instance()` — instantiates the App singleton, which schedules `boot()` on `plugins_loaded`.

> **Use `get_instance()`, not `new`.** `App` inherits from `Factory` (a Singleton). Calling `new My_Plugin()` bypasses the singleton cache and breaks `App::get_apps()` — the method `App::render()` uses to discover every active plugin. Always bootstrap with `get_instance()`.

---

## App subclass — `include/my-plugin.app.php`

```php
<?php

namespace My_Plugin;

use Digitalis\App;

class My_Plugin extends App {

    // The default load() calls $this->autoload() — no override needed for most plugins.
    // Override load_admin() to add admin-only files if you use _admin/:

    public function load_admin(): void {
        parent::load_admin();
        // parent already calls $this->autoload($this->path . '_admin')
    }

}
```

The minimal subclass is empty — the inherited `load()` calls `$this->autoload()` which is enough to pick up every file under `include/`.

---

## How `$this->path` resolves

`App::__construct()` sets `$this->path` via reflection:

```php
$this->reflection = new ReflectionClass(static::class);
$this->path       = plugin_dir_path($this->reflection->getFileName());
```

`getFileName()` returns the **physical path of the subclass file** — `include/my-plugin.app.php` in this example. `plugin_dir_path()` strips the filename and adds a trailing slash, so `$this->path` becomes `…/my-plugin/include/`.

> **The App subclass's file location is the autoload root.** Moving the file to a different directory changes what `autoload()` picks up. Place it in the directory that contains all your plugin's classes.

---

## What `autoload()` picks up

`autoload()` without arguments scans `$this->path` recursively and `require`s every `.php` file it finds, sorted by inheritance order (parent classes before children). The `App` boot sequence calls it on `plugins_loaded`:

```
plugins_loaded
  └── App::boot()
        ├── load()         → autoload($this->path)  — all of include/
        ├── load_admin()   → autoload($this->path . '_admin')  — only on is_admin()
        ├── load_rest()    → autoload($this->path . '_rest')   — only on REST_REQUEST
        └── load_cron()    → autoload($this->path . '_cron')   — only on wp_doing_cron()
```

**Directory prefixes that affect autoloading:**

| Prefix | Behaviour |
|--------|-----------|
| *(none)* | Always scanned |
| `_` | Skipped by recursive scan; loaded explicitly via a `load_*()` override |
| `~plugin-name/` | Loaded only if the named plugin is active |

The file naming convention encodes inheritance so the autoloader can sort correctly: `article.post-type.php` means `Article extends Post_Type`. See [AUTOLOADER.md](./AUTOLOADER.md) for the full suffix table.

---

## Relationship between plugin and framework

```
my-plugin/
├── framework/          ← Digitalis Framework (git submodule or Composer package)
│   └── load.php        ← Registers all framework classes; must be required first
└── my-plugin.php       ← Requires framework/load.php, then bootstraps App subclass
```

The framework is a standalone library. It registers no WordPress hooks until `load.php` is required. The consumer plugin owns the plugin header and the bootstrap call — the framework does not auto-register itself.

Framework classes live in the `Digitalis\` namespace. Consumer plugin classes use their own namespace (e.g. `My_Plugin\`). There is no name conflict.

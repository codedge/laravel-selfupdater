# Laravel Application Self-Updater

[![Latest Stable Version](https://poser.pugx.org/codedge/laravel-selfupdater/v/stable?format=flat-square)](https://packagist.org/packages/codedge/laravel-selfupdater)
[![Total Downloads](https://poser.pugx.org/codedge/laravel-selfupdater/downloads?format=flat-square)](https://packagist.org/packages/codedge/laravel-selfupdater)
[![](https://github.com/codedge/laravel-selfupdater/workflows/Tests/badge.svg)](https://github.com/codedge/laravel-selfupdater/actions)
[![StyleCI](https://styleci.io/repos/64463948/shield)](https://styleci.io/repos/64463948)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/dd836e58656b4e25b34b2a4ac8197142)](https://www.codacy.com/app/codedge/laravel-selfupdater?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=codedge/laravel-selfupdater)
[![codecov](https://codecov.io/gh/codedge/laravel-selfupdater/branch/master/graph/badge.svg)](https://codecov.io/gh/codedge/laravel-selfupdater)

This package provides some basic methods to implement a self updating
functionality for your Laravel 5 application. Already bundled are some
methods to provide a self-update mechanism via Github.

Usually you need this when distributing a self-hosted Laravel application
that needs some updating mechanism without [Composer](https://getcomposer.org/).

## Compatibility

* PHP: 7.3 & 7.4
* Laravel: 6.x & 7.x
  
## Install

To install the latest version from the master using [Composer](https://getcomposer.org/):
```sh
$ composer require codedge/laravel-selfupdater
```

## Configuration
After installing the package you need to publish the configuration file via
```sh
$ php artisan vendor:publish --provider="Codedge\Updater\UpdaterServiceProvider"
```
 
**Note:** Please enter correct value for vendor and repository name in your `config/self-updater.php` if you want to use Github as source for your updates.

### :information_source: Setting the currently installed version

Before starting an update, make sure to set the version installed correctly.
You're responsible to set the current version installed, either in the config file or better via the env variable `SELF_UPDATER_VERSION_INSTALLED`.

#### `tag`-based updates

Set the installed version to one of the tags set for a release.

#### `branch`-based updates

Set the installed version to to a datetime of one of the latest commits.  
A valid version would be: `2020-04-19T22:35:48Z`

### Running artisan commands
Artisan commands can be run before or after the update process and can be configured in `config/self-updater.php`:

__Example:__
```php
'artisan_commands' => [
    'pre_update' => [
        'updater:prepare' => [
            'class' => \App\Console\Commands\PreUpdateTasks::class,
            'params' => []
        ],
    ],
    'post_update' => [
        'postupdate:cleanup' => [
            'class' => \App\Console\Commands\PostUpdateCleanup::class,
            'params' => [
                'log' => 1,
                'reset' => false,
                // etc.
            ]
        ]
    ]
]
```

### Notifications via email
You need to specify a recipient email address and a recipient name to receive
update available notifications.
You can specify these values by adding `SELF_UPDATER_MAILTO_NAME` and
`SELF_UPDATER_MAILTO_ADDRESS` to your `.env` file.

| Config name              | Description |
| -----------              | ----------- |
| SELF_UPDATER_MAILTO_NAME | Name of email recipient |
| SELF_UPDATER_MAILTO_ADDRESS    | Address of email recipient |
| SELF_UPDATER_MAILTO_UPDATE_AVAILABLE_SUBJECT | Subject of update available email |
| SELF_UPDATER_MAILTO_UPDATE_SUCCEEDED_SUBJECT | Subject of update succeeded email |

### Private repositories

Private repositories can be accessed via (Bearer) tokens. Each repository inside the config file should have
a `private_access_token` field, where you can set the token.

**Note:** Do not prefix the token with `Bearer `. This is done automatically.

## Usage
To start an update process, i. e. in a controller, just use:
```php
Route::get('/', function (\Codedge\Updater\UpdaterManager $updater) {

    // Check if new version is available
    if($updater->source()->isNewVersionAvailable()) {

        // Get the current installed version
        echo $updater->source()->getVersionInstalled();

        // Get the new version available
        $versionAvailable = $updater->source()->getVersionAvailable();

        // Create a release
        $release = $updater->source()->fetch($versionAvailable);

        // Run the update process
        $updater->source()->update($release);
        
    } else {
        echo "No new version available.";
    }

});
```

Currently, the fetching of the source is a _synchronous_ process.
It is not run in background.

### Using Github
The package comes with a _Github_ source repository type to fetch 
releases from Github - basically use Github to pull the latest version
of your software.

Just make sure you set the proper repository in your `config/self-updater.php`
file.

#### Tag-based updates

This is the default. Updates will be fetched by using a tagged commit, aka release.

#### Branch-based updates

Select the branch that should be used via the `use_branch` setting [inside the configuration](https://github.com/codedge/laravel-selfupdater/blob/master/config/self-update.php).

```php
// ...
'repository_types' => [
    'github' => [
        'type' => 'github',
        'repository_vendor' => env('SELF_UPDATER_REPO_VENDOR', ''),
        'repository_name' => env('SELF_UPDATER_REPO_NAME', ''),
        // ...
        'use_branch' => 'v2',
   ],          
   // ...
];
```

### Using Http archives
The package comes with an _Http_ source repository type to fetch 
releases from an HTTP directory listing containing zip archives.

To run with HTTP archives, use following settings in your `.env` file:

| Config name              | Value / Description |
| -----------              | ----------- |
| SELF_UPDATER_SOURCE | `http` |
| SELF_UPDATER_REPO_URL    | Archive URL, e.g. `http://archive.webapp/` |
| SELF_UPDATER_PKG_FILENAME_FORMAT | Zip package filename format |
| SELF_UPDATER_DOWNLOAD_PATH | Download path on the webapp host server|

The archive URL should contain nothing more than a simple directory listing with corresponding zip-Archives.

`SELF_UPDATER_PKG_FILENAME_FORMAT` contains the filename format for all webapp update packages. I.e. when the update packages listed on the archive URL contain names like `webapp-v1.2.0.zip`, `webapp-v1.3.5.zip`, ... then the format should be `webapp-v_VERSION_`. The `_VERSION_` part is used as semantic versionioning variable for `MAJOR.MINOR.PATCH` versioning. The zip-extension is automatically added.

The target archive files must be zip archives and should contain all files on root level, not within an additional folder named like the archive itself.

## Contributing
Please see the [contributing guide](CONTRIBUTING.md).

## Licence
The MIT License (MIT). Please see [Licence file](LICENSE) for more information.

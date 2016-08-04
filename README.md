# Laravel Application Self-Updater

This package provides some basic methods to implement a self updating
functionality for your Laravel 5 application. Already bundled are some
methods to provide a self-update mechanism via Github.

Usually you need this when distributing a self-hosted Laravel application
that needs some updating mechanism, as you do not want to bother your
lovely users with Git commands ;-)

## Install with Composer
To install the library using [Composer](https://getcomposer.org/):
```sh
$ composer require codedge/laravel-selfupdater:"dev-master"
```

This adds the _codedge/laravel-selfupdater_ package to your `composer.json` and downloads the project.

Additionally you need to include the service provider in your `config/app.php` `[1]` and optionally the _facade_ `[2]`:
```php
// config/app.php

return [

    //...
    
    'providers' => [
        // ...
        
        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        Codedge\Updater\UpdaterServiceProvider::class, // [1]
    ],
    
    // ...
    
    'aliases' => [
        'App' => Illuminate\Support\Facades\App::class,
        'Artisan' => Illuminate\Support\Facades\Artisan::class,
        
        // ...
        
        'View' => Illuminate\Support\Facades\View::class,
        'Updater' => Codedge\Updater\UpdaterManager::class, // [2]

]
```

## Configuration
After installing the package you need to publish the configuration file via
 ```sh
 $ php artisan vendor:publish --provider="Codedge\Updater\UpdaterServiceProvider"
 ```
 
 **Note:** Please enter correct value for vendor and repository name in
 your `config/self-updater.php` if you want to use Github as source for
 your updates.

## Usage
To start an update process, i. e. in a controller, just use:
```php
public function update()
{
    // This downloads and install the latest version of your repo
    Updater::update();
    
    // Just download the source and do the actual update elsewhere
    Updater::fetch();
    
    // Check if a new version is available and pass current version
    Updater::isNewVersionAvailable('1.2');
}
```

Of course you can inject the _updater_ via method injection:
```php
public function update(UpdaterManager $updater)
{

    $updater->update(); // Same as above
    
    // .. and shorthand for this:
    $updater->source()->update;
    
    $updater->fetch() // Same as above...
}
```

**Note:** Currently the fetching of the source is a _synchronous_ process.
It is not run in background.

### Using Github
The package comes with a _Github_ source repository type to fetch 
releases from Github - basically use Github to pull the latest version
of your software.

Just make sure you set the proper repository in your `config/self-updater.php`
file.

## Extending and adding new source repository types
You want to pull your new versions from elsewhere? Feel free to create
your own source repository type somewhere but keep in mind for the new
source repository type:

- It _needs to_ extend **AbstractRepositoryType**
- It _needs to_ implement **SourceRepositoryTypeContract**

So the perfect class head looks like
```
class BitbucketRepositoryType extends AbstractRepositoryType implements SourceRepositoryTypeContract
```

Afterwards you may create your own [service provider](https://laravel.com/docs/5.2/providers),
i. e. BitbucketUpdaterServiceProvider, with your boot method like so:

```php
public function boot()
{
    Updater::extend('bitbucket', function($app) {
        return Updater::sourceRepository(new BitbucketRepositoryType);
    });
}

```

Now you call your own update source with:
```php
public function update(UpdaterManager $updater)
{
    $updater->source('bitbucket')->update();
}
```

## Contributing
Please see the [contributing guide](CONTRIBUTING.md).

## Roadmap
Just a quickly sketched [roadmap](https://github.com/codedge/laravel-selfupdater/wiki/Roadmap) what still needs to be implemented.

## Licence
The MIT License (MIT). Please see [Licencse file](LICENSE) for more information.
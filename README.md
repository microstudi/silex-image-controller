# ImageControllerServiceProvider

A auto-image resize controller using [Intervention/image](http://image.intervention.io/) service provider for [Silex](http://silex.sensiolabs.org)

[![Downloads](https://img.shields.io/packagist/dt/microstudi/silex-image-controller.svg?style=flat-square)](https://packagist.org/packages/microstudi/silex-image-controller)
![Travis status](https://travis-ci.org/microstudi/silex-image-controller.svg?branch=master)
[![License](https://img.shields.io/packagist/l/microstudi/silex-image-controller.svg?style=flat-square)](http://opensource.org/licenses/MIT)

## Requirements

- PHP >= 5.5.9 (Same as Silex 2.0)
- [`InterventionImageServiceProvider`](https://github.com/microstudi/silex-intervention-image)

## Install

Using composer:

```
composer require microstudi/silex-image-controller
```

## Usage

```php
use Microstudi\Silex\ImageController\ImageControllerServiceProvider;
use Microstudi\Silex\InterventionImage\InterventionImageServiceProvider;

$app = new Silex\Application();
$app->register(new InterventionImageServiceProvider);
      ;

//Automatic images
$app->mount('/your_path', new Microstudi\Silex\Controller\ImageController(array(
                'image_path' => '/path/to/original/images',
                'image_cache_path' => '/path/to/cache/folder'
            ) ));

$app->run();
```

**Twig Helper**: If twig is present a convenient function can be used to generate proper urls for auto-resized images `image_path(image, size)`:

```twig
{{ image_path('path/to/image.png', 100, 100) }}
{{ image_path('path/to/image.png', 100, 100, 'c') }}
```


URLs examples:

```
your_path/200x300/products/image_product.jpg    <- width or height will be
                                                   changed if necessary but
                                                   never increased
your_path/200x300xc/products/image_product.jpg  <- cropped, ensures exact sizes
your_path/200x0/products/image_product.jpg      <- auto-calculate height
your_path/0x300/products/image_product.jpg      <- auto-calculate width
```

### Options

- `intervention.image` - Instance of `Intervention\Image\ImageManager`.
- `intervention.response` - For use ImageManager directly such as `$app['intervention.response']($image)`
- `intervention.driver` -  Driver used (*imagick* or *gd*)


## Tests

```bash
$ composer install
$ vendor/bin/phpunit
```


## Changelog

### 1.0.0

- First release

### 1.0.1

- Readme fix
- Locked Silex to version 1.1

### 2.0.0

- Updated for Silex 2.0

# LICENSE

The MIT LICENSE (MIT)

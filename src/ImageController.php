<?php

/*
 * This file is part of Image Silex Controller.
 * The Microstudi\Silex\InterventionImage\InterventionImageServiceProvider is a requirement
 *
 * (c) 2013 Ivan Vergés <ivan@microstudi.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Usage:
 *
 * //registering required service
 * $app->register(new Microstudi\Silex\InterventionImage\InterventionImageServiceProvider());
 *
 * // Define the mount place where to work:
 *
 * $app->mount('/image_resize', new Microstudi\Silex\ImageController\ImageController('/path/to/images'));
 *
 */

namespace Microstudi\Silex\ImageController;

use Microstudi\Silex\ImageController\Exception\ConfigurationException;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Silex\Api\ControllerProviderInterface;
use Intervention\Image\ImageManager;
use Intervention\Image\Image;
use Intervention\Image\Exception\NotReadableException;

class ImageController implements ControllerProviderInterface
{
    //TODO: setFallbackImage()
    private $fallback_image = 'iVBORw0KGgoAAAANSUhEUgAAAB4AAAAeCAYAAAA7MK6iAAABPUlEQVRIie2WIY+FMAzHZzDPPIXGYDAkS0XbrQ7zDOa+/4c5g9iNDrpBuORyS2pg22/9r+3q3P9QBgB0IYQhxkgissYYv1LbvlEIYQCA7hYoEU0arGQishLRdMXLFzMvVmBuzLwAwKsW+q7x8sh7AHibPS1BmfmDiLP3vk8NEWdm/hzAzz0vycvMi2HtqB36dC0RTSfSgUEx9ZqKAQcAneVeW+Gb5PtU2/J0J1ErnJnHfF0IYdhNjDFSHkjOOScicAGeBxztJuXSIOKc/GuCI+Kcy615/GNT732fHawa7r3v8/nV4Bb4beBa+K3gGrgJfBRcrXBrcKnpdAVuSqdCARlb4VrxUQuIVjKtz1oJruyldyfaI3EX/LQr0SQSkdUi+5Un9fcagQ3+fOuTev54s5eOx9vbzPvnG/o/N74BIMikCFSoRXYAAAAASUVORK5CYII=';

    protected $image_path;
    protected $cache_path;
    protected $cached_image;
    protected $cache_ttl = 2592000; //30days (60sec * 60min * 24hours * 30days)
    protected $cache_url; // absolute path where to find the cached images
    protected $image_manager;
    protected $default_w = 32,
              $default_h = 32,
              $default_quality = 90;
    protected $resize_callbacks = [];
    protected $twig_function = 'image_path';

    /**
     * Requires the path where to find the file
     * TODO: Agnostic filesystem.
     *
     * @param string $config:
     *                        'image_path' (Mandatory) Base path where to find the images
     *                        'cache_path' (Optional) Path to store cached versions of the resized images
     */
    public function __construct(array $config)
    {
        if (!isset($config['image_path'])) {
            throw new ConfigurationException('Image path not defined', 1);
        }
        //base path
        if (substr($config['image_path'], -1, 1) !== '/') {
            $config['image_path'] .= '/';
        }
        $this->image_path = $config['image_path'];

        //sets cache path if available
        if (isset($config['image_cache_path'])) {
            if (substr($config['image_cache_path'], -1, 1) !== '/') {
                $config['image_cache_path'] .= '/';
            }
            $this->cache_path = $config['image_cache_path'];
        }
        //sets cache url if available
        if (isset($config['image_cache_url']) && !empty($config['image_cache_url'])) {
            if (substr($config['image_cache_url'], -1, 1) !== '/') {
                $config['image_cache_url'] .= '/';
            }
            $this->cache_url = $config['image_cache_url'];
        }
        if (isset($config['image_cache_ttl'])) {
            $this->cache_ttl = (int) $config['image_cache_ttl'];
        }
        if (isset($config['image_default_width'])) {
            $this->default_w = (int) $config['image_default_width'];
        }
        if (isset($config['image_default_height'])) {
            $this->default_h = (int) $config['image_default_height'];
        }
        if (isset($config['image_default_quality'])) {
            $this->default_quality = (int) $config['image_default_quality'];
        }
        if (isset($config['resize_callbacks'])) {
            $this->resize_callbacks = $config['resize_callbacks'];
        }
        if (isset($config['twig_function'])) {
            $this->twig_function = $config['twig_function'];
        }
    }

    /**
     * Provides handy routes to the resizing images.
     *
     * @param Application $app Silex
     *
     * @return [type] [description]
     */
    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];
        if (!$this->image_manager && $app['intervention.image'] instanceof ImageManager) {
            $this->image_manager = $app['intervention.image'];
        }

        if (isset($app['twig'])) {
            $self = $this;
            $app['twig'] = $app->extend('twig', function ($twig, $app) use ($self) {
                $twig->addFunction(new \Twig_SimpleFunction($this->twig_function, function ($image, $w = 0, $h = 0, $crop = '', $callback = '') use ($self, $app) {
                    $size = implode('x', array($w, $h, $crop, $callback));
                    //check cache existence
                    if ($self->cache_path && !empty($self->cache_url)) {
                        $cached_file = $self->getCachedFilePath($image, $size);
                        if ($self->getCachedFile($image, $cached_file)) {
                            return $self->cache_url.$cached_file;
                        }
                    }
                    if ($w || $h) {
                        return $app['url_generator']->generate($this->twig_function.'.image_resize', array('image' => $image, 'size' => $size));
                    }

                    return $app['url_generator']->generate($this->twig_function.'.image_flush', array('image' => $image));
                }));

                return $twig;
            });
        }

        // extend twig to use the image_path(image, size) function
        // example:
        // {'image_path('path/to/image.png', 100, 100) }}
        // {'image_path('path/to/image.png', 100, 100, 'c') }}
        // resize image
        // routes like:
        // your_path/200x300/products/image_product.jpg
        // your_path/200x300xc/products/image_product.jpg
        // your_path/200x0/products/image_product.jpg
        // your_path/0x300/products/image_product.jpg
        $controllers
            ->get('/{size}/{image}', array($this, 'resizeAction'))
            ->assert('size', '(\d+)(x)(\d+)([a-z]*)')
            ->assert('image', '.*')
            ->bind($this->twig_function.'.image_resize');

        // flush image
        // routes like:
        // your_path/products/image_product.jpg
        $controllers
            ->get('/{image}', array($this, 'flushAction'))
            ->assert('image', '.*')
            ->bind($this->twig_function.'.image_flush');

        return $controllers;
    }

    protected static function getMimeFromFile($path)
    {
        return finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);
    }

    protected static function getMimeFromData($data)
    {
        return finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $data);
    }

    protected function sendImage(Application $app, $file)
    {
        return $app->sendFile($file, 200);
    }

    protected function streamImage(Application $app, Image $image)
    {
        $image->encode(null, $this->default_quality);
        $mime = self::getMimeFromData($image->getEncoded());

        return $app->stream(
            function () use ($image) {
                // echo $image->response();
                echo $image->getEncoded();
            }, 200, array('Content-Type' => $mime));
    }

    /**
     * Flush action
     * Streams the image directly.
     *
     * @param Request     $request [description]
     * @param Application $app     [description]
     *
     * @return [type] [description]
     */
    public function flushAction(Request $request, Application $app)
    {
        $file = $request->attributes->get('image');
        try {
            return $this->sendImage($app, $this->image_path.$file);
        } catch (FileNotFoundException $e) {
            $msg = $e->getMessage();
            $w = $this->default_w;
            $h = $this->default_h;

            //flush data
            $image = $this->image_manager
                           ->canvas($w, $h)
                           ->insert($this->fallback_image, 'center')
                           ->text($msg, round($w / 2), round($h / 2), function ($font) {
                               $font->align('center');
                               $font->valign('middle');
                               $font->color('#777777');
                           });
            $image->encode('png', $this->default_quality);

            return $this->streamImage($app, $image);
        }
    }

    /**
     * Resize action.
     *
     * @param Request     $request [description]
     * @param Application $app     [description]
     *
     * @return [type] [description]
     */
    public function resizeAction(Request $request, Application $app)
    {
        $size = $request->attributes->get('size');
        $file = $request->attributes->get('image');
        @list($w, $h, $crop, $callback) = @explode('x', $size);
        $w = (int) $w;
        $h = (int) $h;
        if ($w <= 0) {
            $w = null;
        }
        if ($h <= 0) {
            $h = null;
        }

        $cached_file = $this->getCachedFilePath($file, $size);
        if ($this->getCachedFile($file, $cached_file)) {
            return $this->sendImage($app, $this->cache_path.$cached_file);
        }

        try {
            $image = $this->image_manager->make($this->image_path.$file);

            //default size if not specified
            if (is_null($w) && is_null($h)) {
                $w = $image->width();
                $h = $image->height();
            }

            if ($crop === 'c') {
                $image->fit($w, $h, function ($constraint) {
                    $constraint->upsize();
                });
            } else {
                $image->resize($w, $h, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }

            if (isset($this->resize_callbacks['default']) && !is_null($this->resize_callbacks['default']) && is_callable($this->resize_callbacks['default'])) {
                $image = call_user_func($this->resize_callbacks['default'], $image);
            }

            if (!empty($callback) && isset($this->resize_callbacks[$callback]) && !is_null($this->resize_callbacks[$callback]) && is_callable($this->resize_callbacks[$callback])) {
                $image = call_user_func($this->resize_callbacks[$callback], $image);
            }

            //save to cache
            $this->saveCacheFile($image, $cached_file);
        } catch (NotReadableException $e) {
            $msg = $e->getMessage();
            $w = $w ? $w : $this->default_w;
            $h = $h ? $h : $this->default_h;

            //flush data
            $image = $this->image_manager->canvas($w, $h)
                           ->insert($this->fallback_image, 'center')
                           ->encode('png')
                           ->text($msg, round($w / 2), round($h / 2), function ($font) {
                               $font->align('center');
                               $font->valign('middle');
                               $font->color('#777777');
                           });
        }

        return $this->streamImage($app, $image);
    }

    protected function getCachedFilePath($file, $size = '')
    {
        if ($this->cache_path && $file) {
            return ($size ? "$size/" : '').$file;
        }

        return false;
    }

    protected function getCachedFile($file, $cached_file)
    {
        if ($this->cache_path && $file) {
            $cached_file = $this->cache_path.$cached_file;
            $original_file = $this->image_path.$file;

            if (!is_file($cached_file)) {
                return false;
            }
            $mtime_cache = @filemtime($cached_file);
            $mtime_original = @filemtime($original_file);
            if ($mtime_original > $mtime_cache) {
                return false;
            }
            if ($this->cache_ttl && time() - $mtime_cache > $this->cache_ttl) {
                return false;
            }

            return $mtime_cache;
        }

        return false;
    }

    protected function saveCacheFile($image, $cached_file)
    {
        if ($this->cache_path && $image instanceof Image && $cached_file) {
            $cached_file = $this->cache_path.$cached_file;
            //recreate dirs if needed
            $dir = dirname($cached_file);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            return $image->save($cached_file);
        }

        return false;
    }
}

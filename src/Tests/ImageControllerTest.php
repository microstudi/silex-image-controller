<?php
/**
 * Part of the ImageController.
 *
 * For the full copyright and license information,
 * view the LICENSE file that was distributed with this source code.
 *
 * @author  Ivan Vergés <ivan@microstudi.net>
 */
namespace Microstudi\Silex\ImageController\Tests;

use Microstudi\Silex\ImageController\ImageController;
use Microstudi\Silex\InterventionImage\InterventionImageServiceProvider;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
/**
 * Class ImageControllerTest.
 */
class ImageControllerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Returns Silex Application instance.
     *
     * @return Application
     */
    private function createApplication()
    {
        $app = new Application();
        $controller = new ImageController(array(
                'image_path' => __DIR__,
                // 'image_cache_path' => $app['image_cache_path'],
                // 'image_cache_url' => isset($app['image_cache_cdn']) ? $app['image_cache_cdn'] : ''
                ));
        $app
            ->register(new InterventionImageServiceProvider())
            ;

        //Automatic images mount
        $app->mount('/', $controller);

        return $app;
    }

    /**
     * @expectedException Microstudi\Silex\ImageController\Exception\ConfigurationException
     */
    public function testMisconfiguration() {
        $provider = new ImageController(array());
    }

    public function testDefaultImagePng()
    {
        $app = $this->createApplication();
        $request = Request::create('/microstudi.png');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/png', $response->headers->get('Content-Type'));
        ob_start();
        $response->sendContent();
        $content = ob_get_contents();
        ob_end_clean();
        $mime = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $content);
        $this->assertEquals('image/png', $mime);
    }

    public function testDefaultImageJpg()
    {
        $app = $this->createApplication();
        $request = Request::create('/microstudi.jpg');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/jpeg', $response->headers->get('Content-Type'));
        ob_start();
        $response->sendContent();
        $content = ob_get_contents();
        ob_end_clean();
        $mime = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $content);
        $this->assertEquals('image/jpeg', $mime);
    }

    public function testNotFoundImage()
    {
        $app = $this->createApplication();
        $request = Request::create('/not-existing.png');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/png', $response->headers->get('Content-Type'));
        ob_start();
        $response->sendContent();
        $content = ob_get_contents();
        ob_end_clean();
        $mime = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $content);
        $this->assertEquals('image/png', $mime);

    }

    // resize check
    public function testResizeImagePng()
    {
        $app = $this->createApplication();
        $request = Request::create('/30x20xc/microstudi.png');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/png', $response->headers->get('Content-Type'));
        ob_start();
        $response->sendContent();
        $content = ob_get_contents();
        ob_end_clean();
        $mime = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $content);
        $this->assertEquals('image/png', $mime);
        $img = imagecreatefromstring($content);
        $this->assertEquals(30, imagesx($img));
        $this->assertEquals(20, imagesy($img));
    }

    // resize check
    public function testResizeImageJpg()
    {
        $app = $this->createApplication();
        $request = Request::create('/30x20xc/microstudi.jpg');
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/jpeg', $response->headers->get('Content-Type'));
        ob_start();
        $response->sendContent();
        $content = ob_get_contents();
        ob_end_clean();
        $mime = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $content);
        $this->assertEquals('image/jpeg', $mime);
        $img = imagecreatefromstring($content);
        $this->assertEquals(30, imagesx($img));
        $this->assertEquals(20, imagesy($img));
    }

    // TODO:
    // cached image check
    // twig check
}
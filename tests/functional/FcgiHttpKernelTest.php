<?php

use Adoy\FastCGI\Client;
use Igorw\FcgiHttpKernel\FcgiHttpKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\ProcessBuilder;

class FcgiHttpKernelTest extends \PHPUnit_Framework_TestCase
{
    static public $server;

    static public function setupBeforeClass()
    {
        $phpCgiBin = getenv('FCGI_HTTP_KERNEL_BIN');
        $host = getenv('FCGI_HTTP_KERNEL_HOST');
        $port = getenv('FCGI_HTTP_KERNEL_PORT');

        $builder = ProcessBuilder::create()
            ->add('exec')
            ->add($phpCgiBin)
            ->add('-d expose_php=Off')
            ->add('-b')
            ->add("$host:$port");

        static::$server = $builder->getProcess();
        static::$server->start();

        usleep(500000);

        if (static::$server->isTerminated() && !static::$server->isSuccessful()) {
            throw new \RuntimeException(sprintf('Can not start a server at %s:%s. Do you have "%s" installed?', $host, $port, $phpCgiBin));
        }
    }

    static public function tearDownAfterClass()
    {
        static::$server->stop();
    }

    private $client;
    private $kernel;

    public function __construct($name = NULL, array $data = array(), $dataName = '')
    {
        $this->client = new Client(getenv('FCGI_HTTP_KERNEL_HOST'), getenv('FCGI_HTTP_KERNEL_PORT'));
        $this->kernel = new FcgiHttpKernel($this->client, __DIR__.'/Fixtures');

        parent::__construct($name, $data, $dataName);
    }

    /** @test */
    public function handleShouldRenderRequestedFile()
    {
        $request = Request::create('/hello.php');
        $response = $this->kernel->handle($request);

        $this->assertSame('Hello World', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/html', $response->headers->get('Content-type'));
    }

    /** @test */
    public function missingFileShouldResultIn404()
    {
        $request = Request::create('/missing.php');
        $response = $this->kernel->handle($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    /** @test */
    public function customHeadersShouldBeSent()
    {
        $request = Request::create('/redirect.php');
        $response = $this->kernel->handle($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/foo.php', $response->headers->get('Location'));
    }

    /** @test */
    public function customErrorStatusCodeShouldBeSent()
    {
        $request = Request::create('/custom-error.php');
        $response = $this->kernel->handle($request);

        $this->assertSame(500, $response->getStatusCode());
    }

    /** @test */
    public function frontControllerShouldLoadPathInfo()
    {
        $this->kernel = new FcgiHttpKernel($this->client, __DIR__.'/Fixtures', 'silex.php');

        $request = Request::create('/foo');
        $response = $this->kernel->handle($request);

        $this->assertSame('bar', $response->getContent());
    }

    /** @test */
    public function frontControllerShouldConvertRequestMethod()
    {
        $this->kernel = new FcgiHttpKernel($this->client, __DIR__.'/Fixtures', 'silex.php');

        $request = Request::create('/baz', 'POST');
        $response = $this->kernel->handle($request);

        $this->assertSame('qux', $response->getContent());
    }

    /** @test */
    public function frontControllerShouldSupportPut()
    {
        $this->kernel = new FcgiHttpKernel($this->client, __DIR__.'/Fixtures', 'silex.php');

        $request = Request::create('/put-target', 'PUT');
        $response = $this->kernel->handle($request);

        $this->assertSame('putted', $response->getContent());
    }

    /** @test */
    public function frontControllerShouldSupportDelete()
    {
        $this->kernel = new FcgiHttpKernel($this->client, __DIR__.'/Fixtures', 'silex.php');

        $request = Request::create('/delete-target', 'DELETE');
        $response = $this->kernel->handle($request);

        $this->assertSame('deleted', $response->getContent());
    }

    /** @test */
    public function itShouldForwardRequestParameters()
    {
        $request = Request::create('/post-params.php', 'POST', array('foo' => 'bar'));
        $response = $this->kernel->handle($request);

        $this->assertSame('bar', $response->getContent());
    }

    /** @test */
    public function itShouldForwardRequestBody()
    {
        $content = 'bazinga';

        $request = Request::create('/post-body.php', 'POST', array(), array(), array(), array(), $content);
        $response = $this->kernel->handle($request);

        $this->assertSame('bazinga', $response->getContent());
    }

    /** @test */
    public function itShouldForwardHostHeader()
    {
        $request = Request::create('http://localhost/host-header.php');
        $response = $this->kernel->handle($request);

        $this->assertSame('localhost', $response->getContent());
    }

    /** @test */
    public function itShouldForwardCookies()
    {
        $request = Request::create('/cookie-get.php', 'GET', array(), array('foo' => 'bar'));
        $response = $this->kernel->handle($request);

        $this->assertSame('bar', $response->getContent());
    }

    /** @test */
    public function isShouldSetReturnedCookiesOnResponse()
    {
        $request = Request::create('/cookie-set.php');
        $response = $this->kernel->handle($request);

        $cookies = $response->headers->getCookies();
        $this->assertSame('foo', $cookies[0]->getName());
        $this->assertSame('bar', $cookies[0]->getValue());
    }

    /** @test */
    public function isShouldParseMultipleCookiesFromResponse()
    {
        $request = Request::create('/cookie-set-many.php');
        $response = $this->kernel->handle($request);

        $cookies = $response->headers->getCookies();
        $this->assertSame('foo', $cookies[0]->getName());
        $this->assertSame('baz', $cookies[0]->getValue());
        $this->assertSame('qux', $cookies[1]->getName());
        $this->assertSame('quux', $cookies[1]->getValue());
    }

    /** @test */
    public function isShouldParseEmptyCookieValue()
    {
        $request = Request::create('/cookie-set-empty.php');
        $response = $this->kernel->handle($request);

        $cookies = $response->headers->getCookies();
        $this->assertSame('foo', $cookies[0]->getName());
        $this->assertSame('', $cookies[0]->getValue());
    }

    /** @test */
    public function isShouldParseFullCookieValue()
    {
        $request = Request::create('/cookie-set-full.php');
        $response = $this->kernel->handle($request);

        $cookies = $response->headers->getCookies();
        $this->assertSame('foo', $cookies[0]->getName());
        $this->assertSame('bar', $cookies[0]->getValue());
        $this->assertSame(1353842823, $cookies[0]->getExpiresTime());
        $this->assertSame('/baz', $cookies[0]->getPath());
        $this->assertSame('example.com', $cookies[0]->getDomain());
    }

    /** @test */
    public function itShouldSetHttpAuth()
    {
        $request = Request::create('http://igorw:secret@localhost/auth.php');
        $response = $this->kernel->handle($request);

        $this->assertSame('igorw:secret', $response->getContent());
    }

    /** @test */
    public function scriptNameShouldBeFrontController()
    {
        $request = Request::create('/script-name.php');
        $response = $this->kernel->handle($request);

        $this->assertSame('/script-name.php', $response->getContent());
    }

    /** @test */
    public function scriptNameShouldBeFrontControllerWithCustomFrontController()
    {
        $this->kernel = new FcgiHttpKernel($this->client, __DIR__.'/Fixtures', 'silex.php');

        $request = Request::create('/script-name');
        $response = $this->kernel->handle($request);

        $this->assertSame('/silex.php', $response->getContent());
    }

    public function filesProvider()
    {
        return array(
            array(
                __DIR__.'/Fixtures/pixel.gif',
                'pixel.gif',
                'image/gif',
                false,
            ),
            array(
                __DIR__.'/Fixtures/sadkitten.gif',
                'sadkitten.gif',
                'image/gif',
                'not getting response from FCGI server',
            ),
        );
    }

    /**
     * @dataProvider filesProvider
     * @test
     */
    public function uploadShouldPutFileInFiles($path, $name, $type, $skipped)
    {
        if ($skipped) {
            $this->markTestSkipped($skipped);
        }

        $file = new UploadedFile($path, $name, $type);

        $request = Request::create('/upload.php', 'POST');
        $request->files->add(array('kitten' => $file));
        $response = $this->kernel->handle($request);

        $expected = implode("\n", array(
            $name,
            $type,
            $file->getSize(),
            '1',
            '0',
        ));

        $this->assertSame($expected."\n", $response->getContent());
    }

    /** @test */
    public function attributesShouldBeSerializedToEnv()
    {
        $attributes = array(
            'foo'     => 'bar',
            'baz.qux' => array(
                'one'   => 'two',
                'three' => 'four'
            ),
        );

        $request = Request::create('/attributes.php');
        $request->attributes->replace($attributes);
        $response = $this->kernel->handle($request);

        $expected = json_encode($attributes);
        $this->assertSame($expected, $response->getContent());
    }

    /** @test */
    public function doubleCrlfResponseBodyShouldBeDecodedProperly()
    {
        $request = Request::create('/double-crlf-response-body.php');
        $response = $this->kernel->handle($request);

        $expected = "foo\r\n\r\nbar";
        $this->assertSame($expected, $response->getContent());
    }

    /** @test */
    public function emptyResponseShouldWorkFine()
    {
        $request = Request::create('/empty.php');
        $response = $this->kernel->handle($request);

        $expected = '';
        $this->assertSame($expected, $response->getContent());
    }
}

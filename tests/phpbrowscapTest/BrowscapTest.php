<?php

namespace phpbrowscapTest;

use phpbrowscap\Browscap;
use ReflectionClass;

/**
 * Browscap.ini parsing class with caching and update capabilities
 *
 * PHP version 5
 *
 * Copyright (c) 2006-2012 Jonathan Stoppani
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package    Browscap
 * @author     Vítor Brandão <noisebleed@noiselabs.org>
 * @copyright  Copyright (c) 2006-2012 Jonathan Stoppani
 * @version    1.0
 * @license    http://www.opensource.org/licenses/MIT MIT License
 * @link       https://github.com/GaretJax/phpbrowscap/
 */
class BrowscapTest extends TestCase
{
    /**
     * @expectedException \phpbrowscap\Exception
     * @expectedExceptionMessage You have to provide a path to read/store the browscap cache file
     */
    public function testConstructorFails2()
    {
        new Browscap(null);
    }

    /**
     *
     */
    public function testConstructorFails3()
    {
        $path = '/abc/test';

        $this->setExpectedException(
            '\\phpbrowscap\\Exception',
            'The cache path ' . $path . ' is invalid. Are you sure that it exists and that you have permission to access it?'
        );

        new Browscap($path);
    }

    public function testProxyAutoDetection()
    {
        $browscap = $this->createBrowscap();

        putenv('http_proxy=http://proxy.example.com:3128');
        putenv('https_proxy=http://proxy.example.com:3128');
        putenv('ftp_proxy=http://proxy.example.com:3128');

        $browscap->autodetectProxySettings();
        $options = $browscap->getStreamContextOptions();

        self::assertEquals($options['http']['proxy'], 'tcp://proxy.example.com:3128');
        self::assertTrue($options['http']['request_fulluri']);

        self::assertEquals($options['https']['proxy'], 'tcp://proxy.example.com:3128');
        self::assertTrue($options['https']['request_fulluri']);

        self::assertEquals($options['ftp']['proxy'], 'tcp://proxy.example.com:3128');
        self::assertTrue($options['ftp']['request_fulluri']);
    }

    public function testAddProxySettings()
    {
        $browscap = $this->createBrowscap();

        $browscap->addProxySettings('proxy.example.com', 3128, 'http');
        $options = $browscap->getStreamContextOptions();

        self::assertEquals($options['http']['proxy'], 'tcp://proxy.example.com:3128');
        self::assertTrue($options['http']['request_fulluri']);
    }

    public function testAddProxySettingsWithUsername()
    {
        $browscap = $this->createBrowscap();

        $browscap->addProxySettings('proxy.example.com', 3128, 'http', 'test', 'test');
        $options = $browscap->getStreamContextOptions();

        self::assertEquals($options['http']['proxy'], 'tcp://proxy.example.com:3128');
        self::assertEquals($options['http']['header'], 'Proxy-Authorization: Basic dGVzdDp0ZXN0');
        self::assertTrue($options['http']['request_fulluri']);
    }

    public function testClearProxySettings()
    {
        $browscap = $this->createBrowscap();

        $browscap->addProxySettings('proxy.example.com', 3128, 'http');
        $options = $browscap->getStreamContextOptions();

        self::assertEquals($options['http']['proxy'], 'tcp://proxy.example.com:3128');
        self::assertTrue($options['http']['request_fulluri']);

        $clearedWrappers = $browscap->clearProxySettings();
        $options = $browscap->getStreamContextOptions();

        $defaultStreamContextOptions = array(
            'http' => array(
                'timeout' => $browscap->timeout,
            )
        );

        $this->assertEquals($defaultStreamContextOptions, $options);
        self::assertEquals($clearedWrappers, array('http'));
    }

    public function testGetStreamContext()
    {
        $cacheDir = $this->createCacheDir();

        $class = new ReflectionClass('\\phpbrowscap\\Browscap');
        $method = $class->getMethod('_getStreamContext');
        $method->setAccessible(true);

        $browscap = new Browscap($cacheDir);

        $browscap->addProxySettings('proxy.example.com', 3128, 'http');

        $resource = $method->invoke($browscap);

        self::assertTrue(is_resource($resource));
    }

    /**
     * @expectedException \phpbrowscap\Exception
     * @expectedExceptionMessage Local file is not readable
     */
    public function testGetLocalMTimeFails()
    {
        $cacheDir = $this->createCacheDir();

        $class = new ReflectionClass('\\phpbrowscap\\Browscap');
        $method = $class->getMethod('_getLocalMTime');
        $method->setAccessible(true);

        $browscap = new Browscap($cacheDir);

        $method->invoke($browscap);
    }

    /**
     *
     */
    public function testGetLocalMTime()
    {
        $cacheDir = $this->createCacheDir();

        $class = new ReflectionClass('\\phpbrowscap\\Browscap');
        $method = $class->getMethod('_getLocalMTime');
        $method->setAccessible(true);

        $browscap = new Browscap($cacheDir);
        $browscap->localFile = __FILE__;

        $mtime = $method->invoke($browscap);
        $expected = filemtime(__FILE__);

        self::assertSame($expected, $mtime);
    }

    /**
     * @expectedException \phpbrowscap\Exception
     * @expectedExceptionMessage Bad datetime format from http://browscap.org/version
     */
    public function testGetRemoteMTimeFails()
    {
        $class = new ReflectionClass('\\phpbrowscap\\Browscap');
        $method = $class->getMethod('_getRemoteMTime');
        $method->setAccessible(true);

        $browscap = $this->getMock('\\phpbrowscap\\Browscap', array('_getRemoteData'), array(), '', false);
        $browscap->expects($this->any())
            ->method('_getRemoteData')
            ->will(self::returnValue(null));

        $method->invoke($browscap);
    }

    /**
     *
     */
    public function testGetRemoteMTime()
    {
        $class = new ReflectionClass('\\phpbrowscap\\Browscap');
        $method = $class->getMethod('_getRemoteMTime');
        $method->setAccessible(true);

        $expected = 'Mon, 29 Jul 2013 22:22:31 -0000';

        $browscap = $this->getMock('\\phpbrowscap\\Browscap', array('_getRemoteData'), array(), '', false);
        $browscap->expects($this->any())
            ->method('_getRemoteData')
            ->will(self::returnValue($expected));

        $mtime = $method->invoke($browscap);

        self::assertSame(strtotime($expected), $mtime);
    }

    /**
     *
     */
    public function testArray2string()
    {
        $cacheDir = $this->createCacheDir();

        $class = new ReflectionClass('\\phpbrowscap\\Browscap');
        $method = $class->getMethod('_array2string');
        $method->setAccessible(true);

        $browscap = new Browscap($cacheDir);

        $result = 'array(' . "\n" . '\'a\'=>1,' . "\n" . '\'b\'=>\'abc\',' . "\n" . '1=>\'cde\',' . "\n" . '\'def\',' . "\n" . '\'["abc",1,2]\'' . "\n" . ')';

        self::assertSame($result, $method->invoke($browscap, array('a' => 1, 'b' => 'abc', '1.0' => 'cde', 1 => 'def', 2 => array('abc', 1, 2))));
    }

    /**
     *
     */
    public function testGetUpdateMethodReturnsFopen()
    {
        $cacheDir = $this->createCacheDir();

        $class = new ReflectionClass('\\phpbrowscap\\Browscap');
        $method = $class->getMethod('_getUpdateMethod');
        $method->setAccessible(true);

        $browscap = new Browscap($cacheDir);
        $browscap->updateMethod = null;

        $expected = Browscap::UPDATE_FOPEN;

        self::assertSame($expected, $method->invoke($browscap));
    }

    /**
     *
     */
    public function testGetUpdateMethodReturnsLocal()
    {
        $cacheDir = $this->createCacheDir();

        $class = new ReflectionClass('\\phpbrowscap\\Browscap');
        $method = $class->getMethod('_getUpdateMethod');
        $method->setAccessible(true);

        $browscap = new Browscap($cacheDir);
        $browscap->updateMethod = null;
        $browscap->localFile = __FILE__;

        $expected = Browscap::UPDATE_LOCAL;

        self::assertSame($expected, $method->invoke($browscap));
    }

    /**
     *
     */
    public function testGetUserAgent()
    {
        $cacheDir = $this->createCacheDir();

        $class = new ReflectionClass('\\phpbrowscap\\Browscap');
        $method = $class->getMethod('_getUserAgent');
        $method->setAccessible(true);

        $browscap = new Browscap($cacheDir);

        $expected = 'Browser Capabilities Project - PHP Browscap/2.0.4 URL-wrapper';

        self::assertSame($expected, $method->invoke($browscap));
    }
}

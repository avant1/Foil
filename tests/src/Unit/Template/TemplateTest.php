<?php
/*
 * This file is part of the Foil package.
 *
 * (c) Giuseppe Mazzapica <giuseppe.mazzapica@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Foil\Tests\Template;

use Foil\Tests\TestCase;
use Foil\Template\Template;
use Foil\Template\Alias;
use Mockery;

/**
 * @author  Giuseppe Mazzapica <giuseppe.mazzapica@gmail.com>
 * @package foil\foil
 * @license http://opensource.org/licenses/MIT MIT
 */
class TemplateTest extends TestCase
{
    public function testCall()
    {
        /** @var \Foil\Section\Factory $sections */
        $sections = Mockery::mock('Foil\Section\Factory');
        /** @var \Foil\Engine $engine */
        $engine = Mockery::mock('Foil\Engine');
        /** @var \Foil\Kernel\Command|\Mockery\MockInterface $command */
        $command = Mockery::mock('Foil\Kernel\Command');
        $command->shouldReceive('run')->with('foo', 'bar')->once()->andReturn('Foo!');

        $template = new Template('/path', $sections, $engine, $command);

        assertSame('Foo!', $template->foo('bar'));
    }

    public function testFilter()
    {
        /** @var \Foil\Section\Factory $sections */
        $sections = Mockery::mock('Foil\Section\Factory');

        /** @var \Foil\Engine $engine */
        $engine = Mockery::mock('Foil\Engine');

        /** @var \Foil\Kernel\Command|\Mockery\MockInterface $command */
        $command = Mockery::mock('Foil\Kernel\Command');
        $command->shouldReceive('filter')
                ->with(Mockery::type('string'), Mockery::any(), [])
                ->andReturnValues(['foo', 'bar', null, 'baz']);

        $template = new Template('/path', $sections, $engine, $command);
        assertSame('baz', $template->filter('first|foo|bar|baz', 'Lorem Ipsum'));
    }

    public function testFilterArgs()
    {
        /** @var \Foil\Section\Factory $sections */
        $sections = Mockery::mock('Foil\Section\Factory');

        /** @var \Foil\Engine $engine */
        $engine = Mockery::mock('Foil\Engine');

        /** @var \Foil\Kernel\Command|\Mockery\MockInterface $command */
        $command = Mockery::mock('Foil\Kernel\Command');
        $command->shouldReceive('filter')
                ->once()
                ->with('first', 'Lorem Ipsum', ['foo'])
                ->andReturn('Lorem!');
        $command->shouldReceive('filter')
                ->once()
                ->with('last', 'Lorem!', ['bar'])
                ->andReturn('Ipsum!');

        $template = new Template('/path', $sections, $engine, $command);

        $filter = $template->filter('first|last', 'Lorem Ipsum', [['foo'], ['bar']]);

        assertSame('Ipsum!', $filter);
    }

    public function testRenderNoLayout()
    {
        /** @var \Foil\Section\Factory $sections */
        $sections = Mockery::mock('Foil\Section\Factory');

        // the file foo.php contains the code `echo implode(',', $this->data());`
        $base = realpath(getenv('FOIL_TESTS_BASEPATH')).DIRECTORY_SEPARATOR;
        $path = $base.implode(DIRECTORY_SEPARATOR, ['_files', 'foo', 'foo.php']);

        /** @var \Foil\Engine|\Mockery\MockInterface $engine */
        $engine = Mockery::mock('Foil\Engine');
        /** @var \Foil\Kernel\Command|\Mockery\MockInterface $command */
        $command = Mockery::mock('Foil\Kernel\Command');

        $template = new Template($path, $sections, $engine, $command);

        $command->shouldReceive('filter')
                ->with(Mockery::type('string'), Mockery::any(), [])
                ->andReturnValues(['foo', 'bar', null, 'baz']);

        $engine->shouldReceive('fire')
               ->with('f.template.prerender', $template)
               ->once()
               ->andReturnNull();

        $engine->shouldReceive('fire')
               ->with('f.template.rendered', $template)
               ->once()
               ->andReturnNull();

        $render = $template->render(['foo', 'bar']);
        assertSame('foo,bar', $render);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testLayoutFailsIfBadFile()
    {
        /** @var \Foil\Section\Factory $sections */
        $sections = Mockery::mock('Foil\Section\Factory');
        /** @var \Foil\Engine|\Mockery\MockInterface $engine */
        $engine = Mockery::mock('Foil\Engine');
        $engine->shouldReceive('find')->with('foo')->once()->andReturn(false);
        /** @var \Foil\Kernel\Command|\Mockery\MockInterface $command */
        $command = Mockery::mock('Foil\Kernel\Command');
        $template = new Template('/path', $sections, $engine, $command);
        $template->layout('foo');
    }

    public function testRenderLayout()
    {
        $base = realpath(getenv('FOIL_TESTS_BASEPATH')).DIRECTORY_SEPARATOR;
        // the file foo.php contains the code `echo implode(',', $this->data());`
        $path = $base.implode(DIRECTORY_SEPARATOR, ['_files', 'foo', 'foo.php']);
        // the file foo.php contains the code `echo implode('|', $this->data());`
        $layout = $base.implode(DIRECTORY_SEPARATOR, ['_files', 'foo', 'bar.inc']);

        /** @var \Foil\Section\Factory $sections */
        $sections = Mockery::mock('Foil\Section\Factory');

        /** @var \Foil\Engine|\Mockery\MockInterface $engine */
        $engine = Mockery::mock('Foil\Engine');
        /** @var \Foil\Kernel\Command|\Mockery\MockInterface $command */
        $command = Mockery::mock('Foil\Kernel\Command');

        $template = new Template($path, $sections, $engine, $command);

        $engine->shouldReceive('fire')
               ->with('f.template.prerender', $template)
               ->once()
               ->andReturnNull();

        $engine->shouldReceive('fire')
               ->with('f.template.layout', $layout, $template)
               ->once()
               ->andReturnNull();

        $engine->shouldReceive('fire')
               ->with('f.template.renderlayout', $layout, $template)
               ->once()
               ->andReturnNull();

        $engine->shouldReceive('fire')
               ->with('f.template.rendered', $template)
               ->once()
               ->andReturnNull();

        $engine->shouldReceive('find')
               ->with('bar.inc')
               ->once()
               ->andReturn($layout);

        $template->layout('bar.inc');
        assertSame('foo|bar', $template->render(['foo', 'bar']));
        assertSame('foo,bar', $template->lastBuffer());
    }

    public function testSupply()
    {
        $section = Mockery::mock('Foil\Section\Section');
        $section->shouldReceive('content')->once()->andReturn('Ok!');

        /** @var \Foil\Section\Factory|\Mockery\MockInterface $sections */
        $sections = Mockery::mock('Foil\Section\Factory');
        $sections->shouldReceive('has')->once()->with('foo')->andReturn(true);
        $sections->shouldReceive('get')->once()->with('foo')->andReturn($section);

        /** @var \Foil\Engine|\Mockery\MockInterface $engine */
        $engine = Mockery::mock('Foil\Engine');
        /** @var \Foil\Kernel\Command|\Mockery\MockInterface $command */
        $command = Mockery::mock('Foil\Kernel\Command');

        $template = new Template('/path', $sections, $engine, $command);

        assertSame('Ok!', $template->supply('foo'));
    }

    public function testSupplyDefaultString()
    {
        /** @var \Foil\Section\Factory|\Mockery\MockInterface $sections */
        $sections = Mockery::mock('Foil\Section\Factory');
        $sections->shouldReceive('has')->once()->with('foo')->andReturn(false);
        /** @var \Foil\Engine|\Mockery\MockInterface $engine */
        $engine = Mockery::mock('Foil\Engine');
        /** @var \Foil\Kernel\Command|\Mockery\MockInterface $command */
        $command = Mockery::mock('Foil\Kernel\Command');
        $template = new Template('/path', $sections, $engine, $command);

        assertSame('Ok!', $template->supply('foo', 'Ok!'));
    }

    public function testSupplyDefaultCallable()
    {
        /** @var \Foil\Section\Factory|\Mockery\MockInterface $sections */
        $sections = Mockery::mock('Foil\Section\Factory');
        $sections->shouldReceive('has')->once()->with('foo')->andReturn(false);
        /** @var \Foil\Engine|\Mockery\MockInterface $engine */
        $engine = Mockery::mock('Foil\Engine');
        /** @var \Foil\Kernel\Command|\Mockery\MockInterface $command */
        $command = Mockery::mock('Foil\Kernel\Command');
        $template = new Template('/path', $sections, $engine, $command);

        assertSame('Ok!', $template->supply('foo', function ($section, $tmpl) use ($template) {
            assertSame('foo', $section);
            assertSame($template, $tmpl);

            return 'Ok!';
        }));
    }

    public function testInsert()
    {
        /** @var \Foil\Section\Factory $sections */
        $sections = Mockery::mock('Foil\Section\Factory');
        /** @var \Foil\Engine|\Mockery\MockInterface $engine */
        $engine = Mockery::mock('Foil\Engine');
        /** @var \Foil\Kernel\Command|\Mockery\MockInterface $command */
        $command = Mockery::mock('Foil\Kernel\Command');

        $template = new Template('/path', $sections, $engine, $command);

        $engine->shouldReceive('fire')
               ->once()
               ->with('f.template.prepartial', 'foo', ['foo' => 'foo'], $template)
               ->andReturnNull();
        $engine->shouldReceive('fire')
               ->once()
               ->with('f.template.afterpartial', $template)
               ->andReturnNull();

        $engine->shouldReceive('render')
               ->once()
               ->with('foo', ['foo' => 'foo'])
               ->andReturn('Ok!');

        assertSame('Ok!', $template->insert('foo', ['foo' => 'foo']));
    }

    public function testInsertIfDoNothingIfFileNotExists()
    {
        /** @var \Foil\Section\Factory $sections */
        $sections = Mockery::mock('Foil\Section\Factory');
        /** @var \Foil\Engine|\Mockery\MockInterface $engine */
        $engine = Mockery::mock('Foil\Engine');
        $engine->shouldReceive('find')->with('foo')->once()->andReturn(false);
        /** @var \Foil\Kernel\Command|\Mockery\MockInterface $command */
        $command = Mockery::mock('Foil\Kernel\Command');
        $template = new Template('/path', $sections, $engine, $command);

        assertSame('', $template->insertif('foo'));
    }

    public function testAlias()
    {
        /** @var \Foil\Section\Factory $sections */
        $sections = Mockery::mock('Foil\Section\Factory');
        /** @var \Foil\Engine $engine */
        $engine = Mockery::mock('Foil\Engine');
        /** @var \Foil\Kernel\Command|\Mockery\MockInterface $command */
        $command = Mockery::mock('Foil\Kernel\Command');
        $command->shouldReceive('run')
                ->with('v', 'foo')
                ->andReturn('Foo!');

        $template = new Template('/path', $sections, $engine, $command);

        $template->alias(new Alias('T'));

        $file = realpath(getenv('FOIL_TESTS_BASEPATH').'/_files/foo/alias.php');

        $this->bindClosure(function ($file) {
            /** @noinspection PhpUndefinedMethodInspection */
            $this->collect($file);
        }, $template, [$file]);

        assertSame('Foo!', $template->buffer());
    }
}

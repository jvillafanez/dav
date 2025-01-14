<?php

declare(strict_types=1);

namespace Sabre\DAV;

class TreeTest extends \PHPUnit\Framework\TestCase
{
    public function testNodeExists()
    {
        $tree = new TreeMock();

        $this->assertTrue($tree->nodeExists('hi'));
        $this->assertFalse($tree->nodeExists('hello'));
    }

    public function testCopy()
    {
        $tree = new TreeMock();
        $tree->copy('hi', 'hi2');

        $this->assertArrayHasKey('hi2', $tree->getNodeForPath('')->newDirectories);
        $this->assertEquals('foobar', $tree->getNodeForPath('hi/file')->get());
        $this->assertEquals(['test1' => 'value'], $tree->getNodeForPath('hi/file')->getProperties([]));
    }

    public function testCopyFile()
    {
        $tree = new TreeMock();
        $tree->copy('hi/file', 'hi/newfile');

        $this->assertArrayHasKey('newfile', $tree->getNodeForPath('hi')->newFiles);
    }

    public function testCopyFile0()
    {
        $tree = new TreeMock();
        $tree->copy('hi/file', 'hi/0');

        $this->assertArrayHasKey('0', $tree->getNodeForPath('hi')->newFiles);
    }

    public function testMove()
    {
        $tree = new TreeMock();
        $tree->move('hi', 'hi2');

        $this->assertEquals('hi2', $tree->getNodeForPath('hi')->getName());
        $this->assertTrue($tree->getNodeForPath('hi')->isRenamed);
    }

    public function testDeepMove()
    {
        $tree = new TreeMock();
        $tree->move('hi/sub', 'hi2');

        $this->assertArrayHasKey('hi2', $tree->getNodeForPath('')->newDirectories);
        $this->assertTrue($tree->getNodeForPath('hi/sub')->isDeleted);
    }

    public function testDeepMoveNumber()
    {
        $tree = new TreeMock();
        $tree->move('1/2', 'hi/sub');

        $this->assertArrayHasKey('sub', $tree->getNodeForPath('hi')->newDirectories);
        $this->assertTrue($tree->getNodeForPath('1/2')->isDeleted);
    }

    public function testDelete()
    {
        $tree = new TreeMock();
        $tree->delete('hi');
        $this->assertTrue($tree->getNodeForPath('hi')->isDeleted);
    }

    public function testGetChildren()
    {
        $tree = new TreeMock();
        $children = $tree->getChildren('');
        $firstChild = $children->current();
        $this->assertEquals('hi', $firstChild->getName());
    }

    public function testGetMultipleNodes()
    {
        $tree = new TreeMock();
        $result = $tree->getMultipleNodes(['hi/sub', 'hi/file']);
        $this->assertArrayHasKey('hi/sub', $result);
        $this->assertArrayHasKey('hi/file', $result);

        $this->assertEquals('sub', $result['hi/sub']->getName());
        $this->assertEquals('file', $result['hi/file']->getName());
    }

    public function testGetMultipleNodes2()
    {
        $tree = new TreeMock();
        $result = $tree->getMultipleNodes(['multi/1', 'multi/2']);
        $this->assertArrayHasKey('multi/1', $result);
        $this->assertArrayHasKey('multi/2', $result);
    }
}

class TreeMock extends Tree
{
    private $nodes = [];

    public function __construct()
    {
        $file = new TreeFileTester('file');
        $file->properties = ['test1' => 'value'];
        $file->data = 'foobar';

        parent::__construct(
            new TreeDirectoryTester('root', [
                new TreeDirectoryTester('hi', [
                    new TreeDirectoryTester('sub'),
                    $file,
                ]),
                new TreeMultiGetTester('multi', [
                    new TreeFileTester('1'),
                    new TreeFileTester('2'),
                    new TreeFileTester('3'),
                ]),
                new TreeDirectoryTester('1', [
                    new TreeDirectoryTester('2'),
                ]),
            ])
        );
    }
}

class TreeDirectoryTester extends SimpleCollection
{
    public $newDirectories = [];
    public $newFiles = [];
    public $isDeleted = false;
    public $isRenamed = false;

    public function createDirectory($name)
    {
        $this->newDirectories[$name] = true;
    }

    public function createFile($name, $data = null)
    {
        $this->newFiles[$name] = $data;
    }

    public function getChild($name)
    {
        if (isset($this->newDirectories[$name])) {
            return new self($name);
        }
        if (isset($this->newFiles[$name])) {
            return new TreeFileTester($name, $this->newFiles[$name]);
        }

        return parent::getChild($name);
    }

    public function childExists($name)
    {
        return (bool) $this->getChild($name);
    }

    public function delete()
    {
        $this->isDeleted = true;
    }

    public function setName($name)
    {
        $this->isRenamed = true;
        $this->name = $name;
    }
}

class TreeFileTester extends File implements IProperties
{
    public $name;
    public $data;
    public $properties = [];

    public function __construct($name, $data = null)
    {
        $this->name = $name;
        if (is_null($data)) {
            $data = 'bla';
        }
        $this->data = $data;
    }

    public function getName()
    {
        return $this->name;
    }

    public function get()
    {
        return $this->data;
    }

    public function getProperties($properties)
    {
        return $this->properties;
    }

    /**
     * Updates properties on this node.
     *
     * This method received a PropPatch object, which contains all the
     * information about the update.
     *
     * To update specific properties, call the 'handle' method on this object.
     * Read the PropPatch documentation for more information.
     */
    public function propPatch(PropPatch $propPatch)
    {
        $this->properties = $propPatch->getMutations();
        $propPatch->setRemainingResultCode(200);
    }
}

class TreeMultiGetTester extends TreeDirectoryTester implements IMultiGet
{
    /**
     * This method receives a list of paths in it's first argument.
     * It must return an array with Node objects.
     *
     * If any children are not found, you do not have to return them.
     *
     * @return array
     */
    public function getMultipleChildren(array $paths)
    {
        $result = [];
        foreach ($paths as $path) {
            try {
                $child = $this->getChild($path);
                $result[] = $child;
            } catch (Exception\NotFound $e) {
                // Do nothing
            }
        }

        return $result;
    }
}

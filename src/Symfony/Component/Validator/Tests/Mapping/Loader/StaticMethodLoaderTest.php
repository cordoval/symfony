<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping\Loader;

use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\StaticMethodLoader;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;

/**
 * @runTestsInSeparateProcesses
 */
class StaticMethodLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $loader = new StaticMethodLoader('loadMetadata');
        $metadata = new ClassMetadata(__NAMESPACE__.'\StaticLoaderEntity');

        $this->assertTrue($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadataReturnsFalseIfNotSuccessful()
    {
        $loader = new StaticMethodLoader('loadMetadata');
        $metadata = new ClassMetadata('\stdClass');

        $this->assertFalse($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadata()
    {
        $loader = new StaticMethodLoader('loadMetadata');
        $metadata = new ClassMetadata(__NAMESPACE__.'\StaticLoaderEntity');

        $loader->loadClassMetadata($metadata);

        $this->assertEquals(StaticLoaderEntity::$invokedWith, $metadata);
    }

    public function testLoadClassMetadataDoesNotRepeatLoadWithParentClasses()
    {
        $loader = new StaticMethodLoader('loadMetadata');
        $metadata = new ClassMetadata(__NAMESPACE__.'\StaticLoaderDocument');
        $loader->loadClassMetadata($metadata);
        $this->assertCount(0, $metadata->getConstraints());

        $loader = new StaticMethodLoader('loadMetadata');
        $metadata = new ClassMetadata(__NAMESPACE__.'\BaseStaticLoaderDocument');
        $loader->loadClassMetadata($metadata);
        $this->assertCount(1, $metadata->getConstraints());
    }

    public function testLoadClassMetadataIgnoresInterfaces()
    {
        $loader = new StaticMethodLoader('loadMetadata');
        $metadata = new ClassMetadata(__NAMESPACE__.'\StaticLoaderInterface');

        $loader->loadClassMetadata($metadata);

        $this->assertCount(0, $metadata->getConstraints());
    }

    public function testLoadClassMetadataInAbstractClasses()
    {
        $loader = new StaticMethodLoader('loadMetadata');
        $metadata = new ClassMetadata(__NAMESPACE__.'\AbstractStaticLoader');

        $loader->loadClassMetadata($metadata);

        $this->assertCount(1, $metadata->getConstraints());
    }

    public function testLoadClassMetadataIgnoresAbstractMethods()
    {
        $loader = new StaticMethodLoader('loadMetadata');
        try {
            include __DIR__ . '/AbstractMethodStaticLoader.php';
            $this->fail('AbstractMethodStaticLoader should produce a strict standard error.');
        } catch (\Exception $e) {
        }

        $metadata = new ClassMetadata(__NAMESPACE__.'\AbstractMethodStaticLoader');
        $loader->loadClassMetadata($metadata);

        $this->assertCount(0, $metadata->getConstraints());
    }
}

interface StaticLoaderInterface
{
    public static function loadMetadata(ClassMetadata $metadata);
}

abstract class AbstractStaticLoader
{
    public static function loadMetadata(ClassMetadata $metadata)
    {
        $metadata->addConstraint(new ConstraintA());
    }
}

class StaticLoaderEntity
{
    public static $invokedWith = null;

    public static function loadMetadata(ClassMetadata $metadata)
    {
        self::$invokedWith = $metadata;
    }
}

class StaticLoaderDocument extends BaseStaticLoaderDocument
{
}

class BaseStaticLoaderDocument
{
    public static function loadMetadata(ClassMetadata $metadata)
    {
        $metadata->addConstraint(new ConstraintA());
    }
}

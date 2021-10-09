<?php

namespace dnj\IsoMaker\Windows\Tests;

use dnj\Filesystem\Contracts\IFile;
use dnj\Filesystem\Local;
use dnj\IsoMaker\Contracts\Bitness;
use dnj\IsoMaker\OperatingSystem;
use dnj\IsoMaker\Windows\Customization;
use dnj\IsoMaker\Windows\WindowsIsoMaker;
use PHPUnit\Framework\TestCase;

class WindowsIsoMakerTest extends TestCase
{
    public function test(): void
    {
        $factory = (new UnattendMakerTest())->getUnattend();
        $customization = new Customization($factory);
        $customization->setUnattend($factory);

        $iso = $this->getISO(false);
        $os = new OperatingSystem('Windows', Bitness::X64(), $iso);

        $maker = new WindowsIsoMaker($os);
        $ouputIsos = $maker->customize($customization);
        $this->assertIsArray($ouputIsos);
        $this->assertContainsOnlyInstancesOf(IFile::class, $ouputIsos);
        if ($iso) {
            $this->assertContains($iso, $ouputIsos);
        }
        $unattend = new Local\File('/tmp/unattend.iso');
        $ouputIsos[count($ouputIsos) - 1]->copyTo($unattend);
    }

    public function getISO(bool $required = true): ?Local\File
    {
        $path = getenv('ISOMAKER_ISO');
        if (!$path) {
            if ($required) {
                $this->markTestSkipped('This test needs iso file (ISOMAKER_ISO)');
            }

            return null;
        }

        return new Local\File($path);
    }
}

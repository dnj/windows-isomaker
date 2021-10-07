<?php

namespace dnj\IsoMaker\Windows\Tests;

use dnj\Filesystem\Tmp;
use dnj\IsoMaker\Windows\Customization;
use PHPUnit\Framework\TestCase;

class CustomizationTest extends TestCase
{
    public function test(): void
    {
        $factory = (new UnattendMakerTest())->getUnattend();
        $customization = new Customization($factory);
        $this->assertSame($factory, $customization->getUnattend());

        $customization->enableRemoveBootfix();
        $this->assertTrue($customization->getRemoveBootfix());

        $customization->setUnattend($factory);
        $this->assertSame($factory, $customization->getUnattend());

        $this->assertEmpty($customization->getAttachments());

        $tmpFile1 = new Tmp\File();

        $customization->setAttachments([$tmpFile1]);
        $this->assertCount(1, $customization->getAttachments());
        $this->assertContains($tmpFile1, $customization->getAttachments());

        $tmpDir1 = new Tmp\Directory();
        $customization->addAttachment($tmpDir1);
        $this->assertCount(2, $customization->getAttachments());
        $this->assertContains($tmpDir1, $customization->getAttachments());

        $customization->removeAttachment($tmpFile1);
        $this->assertCount(1, $customization->getAttachments());
        $this->assertContains($tmpDir1, $customization->getAttachments());
    }
}

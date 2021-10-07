<?php

namespace dnj\IsoMaker\Windows\Tests;

use dnj\Autounattend\Factory;
use dnj\IsoMaker\Windows\UnattendMaker;
use PHPUnit\Framework\TestCase;

class UnattendMakerTest extends TestCase
{
    public function testExport(): void
    {
        $factory = $this->getUnattend();
        $xml = $factory->toXML();
        $this->assertIsString($xml);
    }

    public function getUnattend(): Factory
    {
        $maker = new UnattendMaker();
        $factory = $maker
            ->wipeDisk()
            ->installFromLocal()
            ->setTimezone('Iran Standard Time')
            ->setPassword('123456')
            ->enableICMP()
            ->enableAutoUpdate(false)
            ->enableSystemRestore(false)
            ->enableAntiSpyware(false)
            ->enableRemoteDesktop()
            ->setupNetwork([
                'identifier' => 'Ethernet0',
                'ipv4' => [
                    'dhcp' => false,
                    'address' => '10.1.0.2',
                    'netmask' => '255.255.255.0',
                    'gateway' => '10.1.0.1',
                ],
                'dns-servers' => [
                    '8.8.8.8',
                    '8.8.4.4',
                ],
            ])
            ->make();

        return $factory;
    }
}

<?php

namespace dnj\IsoMaker\Windows;

use dnj\Autounattend\Command;
use dnj\Autounattend\Deployment;
use dnj\Autounattend\DnsClient;
use dnj\Autounattend\Factory;
use dnj\Autounattend\LocalSessionManager;
use dnj\Autounattend\LUASettings;
use dnj\Autounattend\MPSSVC;
use dnj\Autounattend\NetBT;
use dnj\Autounattend\Password;
use dnj\Autounattend\SecuritySPP;
use dnj\Autounattend\Setup;
use dnj\Autounattend\SetupInternational;
use dnj\Autounattend\ShellSetup;
use dnj\Autounattend\SystemRestore;
use dnj\Autounattend\TCPIP;
use dnj\Autounattend\WindowsDefender;
use dnj\IsoMaker\Exception;

class UnattendMaker
{
    /**
     * @var array{"identifier":string,"ipv4":array{"dhcp"?:bool,"address"?:string,"netmask"?:string,"gateway"?:string},"dns-servers"?:string[]}|null
     */
    protected ?array $network = null;

    /**
     * @var array{"action":"wipeDisk","diskID":int}|null
     */
    protected ?array $diskConfiguration = null;

    /**
     * @var array{"type":"local","imageIdex":int}|null
     */
    protected ?array $imageSource = null;
    protected ?string $timezone = null;
    protected string $password = '';
    protected bool $icmp = true;
    protected bool $autoUpdate = false;
    protected bool $systemRestore = false;
    protected bool $remoteDesktop = true;
    protected bool $antiSpyware = false;

    /**
     * @param array{"identifier":string,"ipv4":array{"dhcp"?:bool,"address"?:string,"netmask"?:string,"gateway"?:string},"dns-servers"?:string[]} $options
     *
     * @return static
     */
    public function setupNetwork(array $options)
    {
        $this->network = $options;

        return $this;
    }

    /**
     * @return static
     */
    public function wipeDisk(int $diskID = 0)
    {
        $this->diskConfiguration = [
            'action' => 'wipeDisk',
            'diskID' => $diskID,
        ];

        return $this;
    }

    /**
     * @return static
     */
    public function installFromLocal(int $imageIndex = 1)
    {
        $this->imageSource = [
            'type' => 'local',
            'imageIdex' => $imageIndex,
        ];

        return $this;
    }

    /**
     * @return static
     */
    public function setTimezone(string $timezone)
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * @return static
     */
    public function setPassword(string $password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return static
     */
    public function enableICMP(bool $enable = true)
    {
        $this->icmp = $enable;

        return $this;
    }

    /**
     * @return static
     */
    public function enableAutoUpdate(bool $enable = true)
    {
        $this->autoUpdate = $enable;

        return $this;
    }

    /**
     * @return static
     */
    public function enableRemoteDesktop(bool $enable = true)
    {
        $this->remoteDesktop = $enable;

        return $this;
    }

    /**
     * @return static
     */
    public function enableSystemRestore(bool $enable = true)
    {
        $this->systemRestore = $enable;

        return $this;
    }

    /**
     * @return static
     */
    public function enableAntiSpyware(bool $enable = true)
    {
        $this->antiSpyware = $enable;

        return $this;
    }

    public function make(): Factory
    {
        $factory = new Factory();

        $networkConfigs = $this->getNetworkConfiguration();
        if (isset($networkConfigs['netBT'])) {
            $factory->netBT = $networkConfigs['netBT'];
        }
        if (isset($networkConfigs['dnsClient'])) {
            $factory->dnsClient = $networkConfigs['dnsClient'];
        }
        if (isset($networkConfigs['tcpip'])) {
            $factory->tcpip = $networkConfigs['tcpip'];
        }

        $setup = new Setup();
        $setup->enableNetwork = !empty($this->network);
        $setup->enableFirewall = false;
        $setup->diskConfiguration = $this->getDiskConfiguration();

        $userData = new Setup\UserData();
        $userData->acceptEula = true;
        $userData->fullName = 'Administrator';
        $userData->organization = 'Organization';
        $setup->userData = $userData;

        $installTo = null;
        if ($this->diskConfiguration) {
            if ('wipeDisk' == $this->diskConfiguration['action']) {
                $installTo = new Setup\ImageInstall\Image\InstallTo($this->diskConfiguration['diskID'], 2);
            }
        }
        if (!$installTo) {
            throw new Exception('undefined installTo');
        }

        $installFrom = new Setup\ImageInstall\Image\InstallFrom();
        if ($this->imageSource) {
            if ('local' == $this->imageSource['type']) {
                $installFrom->metaData = [
                    new Setup\ImageInstall\Image\InstallFrom\MetaData('/IMAGE/INDEX', strval($this->imageSource['imageIdex'])),
                ];
            } else {
                throw new Exception('unsuported image source type');
            }
        } else {
            throw new Exception('undefined image source');
        }

        $osImage = new Setup\ImageInstall\Image();
        $osImage->installTo = $installTo;
        $osImage->installFrom = $installFrom;
        $osImage->willShowUI = 'OnError';

        $imageInstall = new Setup\ImageInstall($osImage);
        $setup->imageInstall = $imageInstall;
        $factory->setup = $setup;

        $setupInternational = new SetupInternational();
        $setupUILanguage = new SetupInternational\SetupUILanguage();
        $setupUILanguage->uiLanguage = 'en-US';
        $setupInternational->setupUILanguage = $setupUILanguage;
        $setupInternational->inputLocale = 'en-US';
        $setupInternational->uiLanguage = 'en-US';
        $setupInternational->uiLanguageFallback = 'en-US';
        $setupInternational->userLocale = 'en-US';
        $setupInternational->systemLocale = 'en-US';
        $factory->setupInternational = $setupInternational;

        $luaSettings = new LUASettings();
        $luaSettings->enableLUA = false;
        $factory->luaSettings = $luaSettings;

        $shellSetup = new ShellSetup();
        $shellSetup->computerName = '*';
        if ($this->timezone) {
            $shellSetup->timeZone = $this->timezone;
        }
        $shellSetup->registeredOwner = '';

        $oobe = new ShellSetup\OOBE();
        $oobe->hideEULAPage = true;
        $oobe->hideOEMRegistrationScreen = true;
        $oobe->hideOnlineAccountScreens = true;
        $oobe->hideWirelessSetupInOOBE = true;
        $oobe->networkLocation = 'Other';
        $oobe->protectYourPC = 1;
        $shellSetup->oobe = $oobe;

        $password = new Password($this->password ?? '', true);
        $autoLogon = new ShellSetup\AutoLogon();
        $autoLogon->enabled = true;
        $autoLogon->username = 'Administrator';
        $autoLogon->password = $password;
        $shellSetup->autoLogon = $autoLogon;

        $userAccounts = new ShellSetup\UserAccounts();
        $userAccounts->administratorPassword = $password;
        $shellSetup->userAccounts = $userAccounts;

        $shellSetup->firstLogonCommands = [];
        if ($this->icmp) {
            $shellSetup->firstLogonCommands[] = new Command(
                'cmd.exe /c netsh advfirewall firewall add rule name=ICMP protocol=icmpv4 dir=in action=allow',
                'Open the firewall for ICMP traffic.'
            );
        }
        if (!$this->autoUpdate) {
            $shellSetup->firstLogonCommands[] = new Command(
                "cmd /c reg.exe add \"HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Windows\CurrentVersion\WindowsUpdate\Auto Update\" /v AUOptions /t REG_DWORD /d 2 /f",
                'Disable Windows Auto Update'
            );
        }
        $factory->shellSetup = $shellSetup;

        $securitySPP = new SecuritySPP();
        $securitySPP->skipAutoActivation = true;
        $factory->securitySPP = $securitySPP;

        if ($this->remoteDesktop) {
            $localSessionManager = new LocalSessionManager();
            $localSessionManager->fDenyTSConnections = false;
            $factory->localSessionManager = $localSessionManager;

            $remoteDesktopGroup = new MPSSVC\FirewallGroup('@FirewallAPI.dll,-28752', 'RemoteDesktop', 'all', true);
            $mpssvc = new MPSSVC();
            $mpssvc->firewallGroups = [$remoteDesktopGroup];
            $factory->mpssvc = $mpssvc;
        }

        if (!$this->systemRestore) {
            $systemRestore = new SystemRestore();
            $systemRestore->disableSR = 1;
            $factory->systemRestore = $systemRestore;
        }

        if (!$this->antiSpyware) {
            $windowsDefender = new WindowsDefender();
            $windowsDefender->disableAntiSpyware = true;
            $factory->windowsDefender = $windowsDefender;
        }

        $enableAdmin = new Command(
            'cmd /c net user Administrator /active:yes',
            'Enable built-in admin.'
        );
        $deployment = new Deployment();
        $deployment->runAsynchronous = [$enableAdmin];
        $factory->deployment = $deployment;

        return $factory;
    }

    protected function getDiskConfiguration(): Setup\DiskConfiguration
    {
        if (!$this->diskConfiguration) {
            throw new Exception('unconfigured disk configuration');
        }
        if ('wipeDisk' != $this->diskConfiguration['action']) {
            throw new Exception('unsuported disk configuration action');
        }
        $createMSR = new Setup\DiskConfiguration\Disk\CreatePartition('Primary', 350);
        $modifyMSR = new Setup\DiskConfiguration\Disk\ModifyPartition(1);
        $modifyMSR->active = true;
        $modifyMSR->format = 'NTFS';
        $modifyMSR->label = 'System Reserved';
        $modifyMSR->typeID = '0x27';

        $createC = new Setup\DiskConfiguration\Disk\CreatePartition('Primary', null, true);
        $modifyC = new Setup\DiskConfiguration\Disk\ModifyPartition(2);
        $modifyC->active = true;
        $modifyC->extend = false;
        $modifyC->format = 'NTFS';
        $modifyC->label = 'Windows';

        $disk0 = new Setup\DiskConfiguration\Disk($this->diskConfiguration['diskID']);
        $disk0->willWipeDisk = true;
        $disk0->createPartitions = [$createMSR, $createC];
        $disk0->modifyPartitions = [$modifyMSR, $modifyC];

        $diskConfiguration = new Setup\DiskConfiguration();
        $diskConfiguration->disks = [$disk0];
        $diskConfiguration->willShowUI = 'OnError';

        return $diskConfiguration;
    }

    /**
     * @return array{"netBT"?:NetBT,"dnsClient"?:DnsClient,"tcpip"?:TCPIP}
     */
    protected function getNetworkConfiguration(): array
    {
        if (!$this->network) {
            return [];
        }

        $result = [];
        $ips = [];
        $routes = [];

        if (isset($this->network['ipv4']['netmask'], $this->network['ipv4']['address'])) {
            $long = ip2long($this->network['ipv4']['netmask']);
            $base = ip2long('255.255.255.255');
            $netmask = 32 - log(($long ^ $base) + 1, 2);
            $ips[] = $this->network['ipv4']['address'].'/'.$netmask;
        }
        if (isset($this->network['ipv4']['gateway'])) {
            $routes[] = new TCPIP\NetworkInterface\Route('0.0.0.0/0', 20, $this->network['ipv4']['gateway']);
        }
        $ethernet = new TCPIP\NetworkInterface($this->network['identifier'], $routes);
        $ethernet->ipv4Settings = new TCPIP\NetworkInterface\IpSettings($this->network['ipv4']['dhcp'] ?? false);
        $ethernet->unicastIpAddresses = $ips;
        $result['tcpip'] = new TCPIP([$ethernet]);

        if (isset($this->network['dns-servers'])) {
            $dnsClient = new DnsClient();
            $ethernet = new DnsClient\NetworkInterface($this->network['identifier']);
            $ethernet->dnsServerSearchOrder = [];
            foreach ($this->network['dns-servers'] as $x => $dns) {
                $ethernet->dnsServerSearchOrder[] = new DnsClient\IpAddress($dns, "{$x}");
            }
            $dnsClient->interfaces = [$ethernet];
            $result['dnsClient'] = $dnsClient;

            $netBT = new NetBT();
            $ethernet = new NetBT\NetworkInterface($this->network['identifier'], $this->network['dns-servers']);
            $netBT->interfaces = [$ethernet];
            $result['netBT'] = $netBT;
        }

        return $result;
    }
}

<?php

namespace dnj\IsoMaker\Windows;

use dnj\Autounattend\Factory;
use dnj\Filesystem\Contracts\IDirectory;
use dnj\Filesystem\Contracts\IFile;
use dnj\Filesystem\Contracts\INode;
use dnj\Filesystem\Tmp;
use dnj\IsoMaker\Contracts\ICustomization;
use dnj\IsoMaker\Exception;
use dnj\IsoMaker\IsoMaker;

class WindowsIsoMaker extends IsoMaker
{
    public function customize(ICustomization $customization): array
    {
        if (!$customization instanceof Customization) {
            throw new Exception('unsuported customization');
        }
        $isoFiles = [];
        if (null !== $this->os->getISOFile()) {
            $isoFiles[] = $this->os->getISOFile();
            if ($customization->getRemoveBootfix()) {
                $this->logger->debug('Removing bootfix');
                $this->removeBootfix();
            }
        } else {
            $this->logger->debug('OS iso file is missing, skiping remove bootfix');
        }

        $this->logger->debug('making unattend iso');
        $isoFiles[] = $this->makeUnattendISO($customization->getUnattend(), $customization->getAttachments());

        return $isoFiles;
    }

    public function removeBootfix(): void
    {
        $iso = $this->os->getISOFile();
        if (null === $iso) {
            throw new Exception('ISO File is required');
        }
        $this->insureCommand('7z');
        $hasBootFix = trim($this->runCommand(['7z', 'l', '-ba', $iso->getPath(), 'boot/bootfix.bin']));
        if (!$hasBootFix) {
            return;
        }
        $repo = $this->unpackISO($iso);
        $repo->file('boot/bootfix.bin')->delete();
        $label = $this->getISOLabel($iso);
        $iso->delete();
        $this->packISO($repo, $iso, $label);
        $this->runCommand(['rm', '-fr', $repo->getPath()]);
    }

    protected function packISO(IDirectory $directory, IFile $iso, ?string $label = null): void
    {
        $this->logger->debug('Pack dir:'.$directory->getPath().' to iso:'.$iso->getPath());
        $this->insureCommand('mkisofs');
        $bootOptions = [];
        if (null !== $label) {
            array_push($bootOptions, '-V', $label);
        }
        if ($directory->file('boot/etfsboot.com')->exists()) {
            array_push($bootOptions,
                '-no-emul-boot',
                '-b', 'boot/etfsboot.com',
                '-boot-load-seg', '0x07C0',
                '-boot-load-size', '8'
            );
        }
        $command = ['mkisofs', ...$bootOptions, '-iso-level', '2', '-udf', '-joliet', '-D', '-N', '-relaxed-filenames', '-o', $iso->getPath(), $directory->getPath()];
        $this->runCommand($command);
    }

    /**
     * @param INode[] $attachments
     */
    public function makeUnattendISO(Factory $unattend, array $attachments): IFile
    {
        $repo = new Tmp\Directory();
        foreach ($attachments as $attachment) {
            if ($attachment instanceof IFile) {
                $attachment->copyTo($repo->file($attachment->getBasename()));
            } elseif ($attachment instanceof IDirectory) {
                $attachment->copyTo($repo->directory($attachment->getBasename()));
            } else {
                throw new Exception('Unsupported attachment type');
            }
        }
        $repo->file('Autounattend.xml')->write($unattend->toXML());
        $iso = new Tmp\File();
        $iso->rename($iso->getBasename().'.iso');
        $this->packISO($repo, $iso);

        return $iso;
    }
}

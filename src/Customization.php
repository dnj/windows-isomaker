<?php

namespace dnj\IsoMaker\Windows;

use dnj\Autounattend\Factory;
use dnj\Filesystem\Contracts\INode;
use dnj\IsoMaker\Contracts\ICustomization;

class Customization implements ICustomization
{
    protected Factory $unattend;
    protected bool $removeBootfix = true;

    /**
     * @var INode[]
     */
    protected array $attachments = [];

    public function __construct(Factory $unattend)
    {
        $this->unattend = $unattend;
    }

    /**
     * @return static
     */
    public function enableRemoveBootfix(bool $enable = true)
    {
        $this->removeBootfix = $enable;

        return $this;
    }

    public function getRemoveBootfix(): bool
    {
        return $this->removeBootfix;
    }

    /**
     * @return static
     */
    public function setUnattend(Factory $unattend)
    {
        $this->unattend = $unattend;

        return $this;
    }

    public function getUnattend(): Factory
    {
        return $this->unattend;
    }

    /**
     * @return INode[]
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * @param INode[] $attachments
     *
     * @return static
     */
    public function setAttachments(array $attachments)
    {
        $this->attachments = $attachments;

        return $this;
    }

    /**
     * @return static
     */
    public function addAttachment(INode $attachment)
    {
        $this->attachments[] = $attachment;

        return $this;
    }

    /**
     * @return static
     */
    public function removeAttachment(INode $attachment)
    {
        $key = array_search($attachment, $this->attachments);
        if (false !== $key) {
            if (is_int($key)) {
                array_splice($this->attachments, $key, 1);
            } else {
                unset($this->attachments[$key]);
            }
        }

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace Bolt\Boltpay\Model\Source;

use Magento\Customer\Api\GroupManagementInterface;
use Magento\Framework\Convert\DataObject as Converter;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class Group implements OptionSourceInterface
{
    /**
     * @var array|null
     */
    private $options;

    /**
     * @var GroupManagementInterface
     */
    private $groupManagement;

    /**
     * @var Converter
     */
    private $converter;

    public function __construct(
        Converter $converter,
        GroupManagementInterface $groupManagement
    ) {
        $this->groupManagement = $groupManagement;
        $this->converter = $converter;
    }

    /**
     * Retrieve customer groups as array
     *
     * @return array
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function toOptionArray(): array
    {
        if ($this->options === null) {
            $this->options = $this->converter->toOptionArray(
                $this->groupManagement->getLoggedInGroups(),
                'id',
                'code'
            );

            $notLoggedGroup = $this->groupManagement->getNotLoggedInGroup();
            array_unshift($this->options, [
                'value' => $notLoggedGroup->getId(),
                'label' => __('Not Logged In')  // @phpstan-ignore-line
            ]);
        }

        return $this->options;
    }
}

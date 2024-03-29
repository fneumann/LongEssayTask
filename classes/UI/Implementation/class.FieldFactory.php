<?php

namespace ILIAS\Plugin\LongEssayTask\UI\Implementation;

use ILIAS\Data;
use ILIAS\Plugin\LongEssayTask\UI;
use ILIAS\UI\Implementation\Component\SignalGeneratorInterface;

class FieldFactory implements \ILIAS\Plugin\LongEssayTask\UI\Component\FieldFactory
{
	/**
	 * @var    Data\Factory
	 */
	protected $data_factory;

	/**
	 * @var SignalGeneratorInterface
	 */
	protected $signal_generator;

	/**
	 * @var \ILIAS\Refinery\Factory
	 */
	private $refinery;

	/**
	 * @var	\ilLanguage
	 */
	protected $lng;

	/**
	 * Factory constructor.
	 *
	 * @param SignalGeneratorInterface $signal_generator
	 * @param Data\Factory $data_factory
	 * @param \ILIAS\Refinery\Factory $refinery
	 */
	public function __construct(
		SignalGeneratorInterface $signal_generator,
		Data\Factory $data_factory,
		\ILIAS\Refinery\Factory $refinery,
		\ilLanguage $lng
	) {
		$this->signal_generator = $signal_generator;
		$this->data_factory = $data_factory;
		$this->refinery = $refinery;
		$this->lng = $lng;
	}

	/**
	 * @inheritdoc
	 */
	public function numeric($label, $byline = null) : \ILIAS\Plugin\LongEssayTask\UI\Implementation\Numeric
	{
		return new Numeric($this->data_factory, $this->refinery, $label, $byline);
	}
}
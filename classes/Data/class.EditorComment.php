<?php
/* Copyright (c) 2021 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\LongEssayTask\Data;

/**
 * @author Fabian Wolf <wolf@ilias.de>
 */
class EditorComment extends ActivePluginRecord
{
    /**
     * @var string
     */
    protected $connector_container_name = 'xlet_editor_comment';

	/**
	 * Editor notice id
	 *
	 * @var integer
	 * @con_has_field        true
	 * @con_is_primary       true
	 * @con_sequence         true
	 * @con_is_notnull       true
	 * @con_fieldtype        integer
	 * @con_length           4
	 */
	protected int $id;

	/**
	 * The task_id
	 *
	 * @var integer
	 * @con_has_field        true
	 * @con_is_primary       false
	 * @con_sequence         false
	 * @con_is_notnull       true
	 * @con_fieldtype        integer
	 * @con_length           4
	 */
	protected int $task_id;

	/**
	 * Comment (richtext)
	 *
	 * @var null|string
	 * @con_has_field        true
	 * @con_is_notnull       false
	 * @con_fieldtype        clob
	 */
	protected ?string $comment = null;

	/**
	 * @var int
	 * @con_has_field        true
	 * @con_is_notnull       true
	 * @con_fieldtype        integer
	 * @con_length           4
	 */
	protected int $start_position = 0;

	/**
	 * @var int
	 * @con_has_field        true
	 * @con_is_notnull       true
	 * @con_fieldtype        integer
	 * @con_length           4
	 */
	protected int $end_position = 0;

	/**
	 * @return int
	 */
	public function getId(): int
	{
		return $this->id;
	}

	/**
	 * @param int $id
	 * @return EditorComment
	 */
	public function setId(int $id): EditorComment
	{
		$this->id = $id;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getTaskId(): int
	{
		return $this->task_id;
	}

	/**
	 * @param int $task_id
	 * @return EditorComment
	 */
	public function setTaskId(int $task_id): EditorComment
	{
		$this->task_id = $task_id;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getComment(): ?string
	{
		return $this->comment;
	}

	/**
	 * @param string|null $comment
	 * @return EditorComment
	 */
	public function setComment(?string $comment): EditorComment
	{
		$this->comment = $comment;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getStartPosition(): int
	{
		return $this->start_position;
	}

	/**
	 * @param int $start_position
	 * @return EditorComment
	 */
	public function setStartPosition(int $start_position): EditorComment
	{
		$this->start_position = $start_position;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getEndPosition(): int
	{
		return $this->end_position;
	}

	/**
	 * @param int $end_position
	 * @return EditorComment
	 */
	public function setEndPosition(int $end_position): EditorComment
	{
		$this->end_position = $end_position;
		return $this;
	}
}
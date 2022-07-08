<?php
/* Copyright (c) 2021 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\LongEssayTask\WriterAdmin;

use ILIAS\Plugin\LongEssayTask\BaseGUI;
use ILIAS\Plugin\LongEssayTask\Data\LogEntry;
use ILIAS\Plugin\LongEssayTask\Data\WriterNotice;
use ILIAS\Plugin\LongEssayTask\LongEssayTaskDI;
use ILIAS\UI\Factory;
use \ilUtil;

/**
 *Start page for corrector admins
 *
 * @package ILIAS\Plugin\LongEssayTask\WriterAdmin
 * @ilCtrl_isCalledBy ILIAS\Plugin\LongEssayTask\WriterAdmin\WriterAdminLogGUI: ilObjLongEssayTaskGUI
 */
class WriterAdminLogGUI extends BaseGUI
{
	/**
	 * Execute a command
	 * This should be overridden in the child classes
	 * note: permissions are already checked in the object gui
	 */
	public function executeCommand()
	{
		$cmd = $this->ctrl->getCmd('showStartPage');
		switch ($cmd) {
			case 'showStartPage':
			case 'createWriterNotice':
			case 'createLogEntry':
				$this->$cmd();
				break;

			default:
				$this->tpl->setContent('unknown command: ' . $cmd);
		}
	}


	/**
	 * Show the items
	 */
	protected function showStartPage()
	{
		$modal_log_entry = $this->buildFormModalLogEntry();
		$button_log_entry = $this->uiFactory->button()->standard($this->plugin->txt("create_log_entry"), '#')
			->withOnClick($modal_log_entry->getShowSignal());
		$this->toolbar->addComponent($button_log_entry);

		$modal_writer_notice = $this->buildFormModalWriterNotice();
		$button_writer_notice = $this->uiFactory->button()->standard($this->plugin->txt("create_writer_notice"), '#')
			->withOnClick($modal_writer_notice->getShowSignal());
		$this->toolbar->addComponent($button_writer_notice);

		$task_repo = LongEssayTaskDI::getInstance()->getTaskRepo();

		$list = new WriterAdminLogListGUI($this, "showStartPage", $this->plugin, $this->object->getId());
		$list->addLogEntries($task_repo->getLogEntriesByTaskId($this->object->getId()));
		$list->addWriterNotices($task_repo->getWriterNoticeByTaskId($this->object->getId()));

		$this->tpl->setContent($this->renderer->render([$modal_log_entry, $modal_writer_notice]) . $list->getContent());
	}

	private function createWriterNotice()
	{
		if ($this->request->getMethod() == "POST") {
			$data = $_POST;

			// inputs are ok => save data
			if (array_key_exists("text", $data) && array_key_exists("recipient", $data) && strlen($data["text"]) > 0 ) {
				$writer_notice = new WriterNotice();
				$writer_notice->setTaskId($this->object->getId());
				$writer_notice->setCreated((new \ilDateTime(time(), IL_CAL_UNIX))->get(IL_CAL_DATETIME));
				$writer_notice->setNoticeText($data['text']);

				if($data['recipient'] != -1) {
					$writer_notice->setWriterId((int) $data['recipient']);
				}
				$task_repo = LongEssayTaskDI::getInstance()->getTaskRepo();
				$task_repo->createWriterNotice($writer_notice);

				ilUtil::sendSuccess($this->plugin->txt("writer_notice_send"), true);
			} else {
				ilUtil::sendFailure($this->lng->txt("validation_error"), true);
			}
			$this->ctrl->redirect($this, "showStartPage");
		}
	}

	private function createLogEntry()
	{

		if ($this->request->getMethod() == "POST") {
			$data = $_POST;

			// inputs are ok => save data
			if (array_key_exists("entry", $data) && strlen($data["entry"]) > 0 ) {
				$log_entry = new LogEntry();
				$log_entry->setTaskId($this->object->getId());
				$log_entry->setTimestamp((new \ilDateTime(time(), IL_CAL_UNIX))->get(IL_CAL_DATETIME));
				$log_entry->setEntry($data['entry']);
				$log_entry->setCategory(LogEntry::CATEGORY_NOTE);

				$task_repo = LongEssayTaskDI::getInstance()->getTaskRepo();
				$task_repo->createLogEntry($log_entry);

				ilUtil::sendSuccess($this->plugin->txt("log_entry_created"), true);
			} else {
				ilUtil::sendFailure($this->lng->txt("validation_error"), true);
			}
			$this->ctrl->redirect($this, "showStartPage");
		}
	}

	private function buildFormModalWriterNotice(): \ILIAS\UI\Component\Modal\RoundTrip
	{
		$form = new \ilPropertyFormGUI();
		$form->setId(uniqid('form'));

		$options = array_replace(
			["-1" => $this->plugin->txt("writer_notice_recipient_all")],
			$this->getWriterNameOptions()
		);

		$item = new \ilSelectInputGUI($this->plugin->txt("writer_notice_recipient"), 'recipient');
		$item->setOptions($options);
		$item->setRequired(true);
		$form->addItem($item);

		$item = new \ilTextAreaInputGUI($this->plugin->txt("writer_notice_text"), 'text');
		$item->setRequired(true);
		$form->addItem($item);

		$form->setFormAction($this->ctrl->getFormAction($this, "createWriterNotice"));

		$item = new \ilHiddenInputGUI('cmd');
		$item->setValue('submit');
		$form->addItem($item);

		return $this->buildFormModal($this->plugin->txt("create_writer_notice"), $form);
	}

	private function buildFormModalLogEntry(): \ILIAS\UI\Component\Modal\RoundTrip
	{

		$form = new \ilPropertyFormGUI();
		$form->setId(uniqid('form'));

		$item = new \ilTextAreaInputGUI($this->plugin->txt("log_entry_text"), 'entry');
		$item->setRequired(true);
		$form->addItem($item);

		$form->setFormAction($this->ctrl->getFormAction($this, "createLogEntry"));

		$item = new \ilHiddenInputGUI('cmd');
		$item->setValue('submit');
		$form->addItem($item);

		return $this->buildFormModal($this->plugin->txt("create_log_entry"), $form);
	}


	private function buildFormModal(string $title, \ilPropertyFormGUI $form): \ILIAS\UI\Component\Modal\RoundTrip
	{
		global $DIC;
		$factory = $DIC->ui()->factory();
		$renderer = $DIC->ui()->renderer();

		// Build the form
		$item = new \ilHiddenInputGUI('cmd');
		$item->setValue('submit');
		$form->addItem($item);

		// Build a submit button (action button) for the modal footer
		$form_id = 'form_' . $form->getId();
		$submit = $factory->button()->primary('Submit', '#')
			->withOnLoadCode(function ($id) use ($form_id) {
				return "$('#{$id}').click(function() { $('#{$form_id}').submit(); return false; });";
			});

		return $factory->modal()->roundtrip($title, $factory->legacy($form->getHTML()))
			->withActionButtons([$submit]);
	}

	private function getWriterNameOptions(): array
	{
		$writer_repo = LongEssayTaskDI::getInstance()->getWriterRepo();
		$writers = [];
		foreach($writer_repo->getWritersByTaskId($this->object->getId()) as $writer){
			$writers[$writer->getUserId()] = $writer;
		}

		$user_ids = array_map(function ($x) {
			return $x->getUserId();
		}, $writers);
		$out = [];

		foreach (\ilUserUtil::getNamePresentation(array_unique($user_ids), false, false, "", true) as $usr_id => $user){
			$out[(string)$writers[$usr_id]->getId()] = $user;
		}

		return $out;
	}

}
<?php

namespace ILIAS\Plugin\LongEssayTask\WriterAdmin;

use ILIAS\Plugin\LongEssayTask\Data\TimeExtension;
use ILIAS\Plugin\LongEssayTask\Data\Writer;

class WriterAdminListGUI extends WriterListGUI
{
	/**
	 * @var TimeExtension[]
	 */
	private $extensions = [];


	public function getContent() :string
	{
		$this->loadUserData();
		$this->sortWriter();

		$items = [];
		$modals = [];

        $count_total = count($this->getWriters());
        $count_filtered = 0;
        foreach($this->getWriters() as $writer)
		{
			if(!$this->isFiltered($writer)){
				continue;
			}
            $count_filtered++;

			$actions = [];
			if($this->canGetSight($writer)) {
				$sight_modal = $this->uiFactory->modal()->lightbox($this->uiFactory->modal()->lightboxTextPage(
                    $this->localDI->getDataService($writer->getTaskId())->cleanupRichText($this->essays[$writer->getId()]->getWrittenText()),
					$this->plugin->txt("submission"),
				));
				$modals[] = $sight_modal;
				$actions[] = $this->uiFactory->button()->shy($this->plugin->txt('view_processing'), '')->withOnClick($sight_modal->getShowSignal());
                $actions[] = $this->uiFactory->button()->shy($this->plugin->txt('export_steps'), $this->getExportStepsTarget($writer));
            }

			if($this->canGetAuthorized($writer)) {
				$authorize_modal = $this->uiFactory->modal()->interruptive(
					$this->plugin->txt("authorize_writing"),
					$this->plugin->txt("authorize_writing_confirmation"),
					$this->getAuthorizeAction($writer)
				)->withAffectedItems([
					$this->uiFactory->modal()->interruptiveItem($writer->getUserId(), $this->getWriterName($writer))
				])->withActionButtonLabel("confirm");

				$modals[] = $authorize_modal;
				$actions[] = $this->uiFactory->button()->shy($this->plugin->txt('authorize_writing'), "",)->withOnClick($authorize_modal->getShowSignal());
			}

			if($this->canGetExtension($writer)) {
				$actions[] = $this->uiFactory->button()->shy($this->plugin->txt('extent_time'), $this->getExtensionAction($writer));
			}

			if($this->canGetRepealed($writer)){
				$repeal_modal = $this->uiFactory->modal()->interruptive(
					$this->plugin->txt("repeal_exclude_participant"),
					$this->plugin->txt("repeal_exclude_participant_confirmation"),
					$this->getRepealExclusionAction($writer)
				)->withAffectedItems([
					$this->uiFactory->modal()->interruptiveItem($writer->getUserId(), $this->getUsername($writer->getUserId()))
				]);

				$actions[] = $this->uiFactory->button()->shy($this->plugin->txt("repeal_exclude_participant"), '')
					->withOnClick($repeal_modal->getShowSignal());

				$modals[] = $repeal_modal;
			}else{
				$exclusion_modal = $this->uiFactory->modal()->interruptive(
					$this->plugin->txt("exclude_participant"),
					$this->plugin->txt("exclude_participant_confirmation"),
					$this->getExclusionAction($writer)
				)->withAffectedItems([
					$this->uiFactory->modal()->interruptiveItem($writer->getUserId(), $this->getUsername($writer->getUserId()))
				])->withActionButtonLabel("remove");

				$actions[] = $this->uiFactory->button()->shy($this->plugin->txt("exclude_participant"), '')
					->withOnClick($exclusion_modal->getShowSignal());

				$modals[] = $exclusion_modal;
			}

			$remove_modal = $this->uiFactory->modal()->interruptive(
				$this->plugin->txt("remove_writer"),
				$this->plugin->txt("remove_writer_confirmation"),
				$this->getRemoveAction($writer)
			)->withAffectedItems([
				$this->uiFactory->modal()->interruptiveItem($writer->getUserId(), $this->getUsername($writer->getUserId()))
			])->withActionButtonLabel("remove");

			$actions[] = $this->uiFactory->button()->shy($this->plugin->txt("remove_writer"), '')
				->withOnClick($remove_modal->getShowSignal());
			$modals[] = $remove_modal;

			$actions_dropdown = $this->uiFactory->dropdown()->standard($actions)
				->withLabel($this->plugin->txt("actions"));


            $properties = [
                $this->plugin->txt("pseudonym") => $writer->getPseudonym(),
                $this->plugin->txt("essay_status") => $this->essayStatus($writer),
                $this->plugin->txt("writing_time_extension") => $this->extensionString($writer),
            ];
            if (!empty($this->lastSave($writer))) {
                $properties[ $this->plugin->txt("writing_last_save")] = $this->lastSave($writer);
            }
			$items[] = $this->uiFactory->item()->standard($this->getWriterName($writer, true) . $this->getWriterAnchor($writer))
				->withLeadIcon($this->getWriterIcon($writer))
				->withProperties($properties)
                ->withActions($actions_dropdown);
		}

		$resources = array_merge([$this->uiFactory->item()->group(
            $this->plugin->txt("participants")
                . $this->localDI->getDataService(0)->formatCounterSuffix($count_filtered, $count_total)
            , $items)], $modals);

		return $this->renderer->render($this->filterControl()) . '<br><br>' .
			$this->renderer->render($resources);
	}

	private function canGetSight(Writer $writer){
		if(isset($this->essays[$writer->getId()])) {
			$essay = $this->essays[$writer->getId()];
			return /*$essay->getEditEnded() === null &&*/ $essay->getEditStarted() !== null;
		}
		return false;
	}


	private function canGetAuthorized(Writer $writer){
		if(isset($this->essays[$writer->getId()])) {
			$essay = $this->essays[$writer->getId()];

			return $essay->getEditStarted() !== null
				/*&& $essay->getEditEnded() !== null*/
				&& $essay->getWritingAuthorized() === null;
		}
		return false;
	}

	private function getAuthorizeAction(Writer $writer) {
		$this->ctrl->setParameter($this->parent,"writer_id", $writer->getId());
		return $this->ctrl->getFormAction($this->parent, "authorizeWriting");
	}

	private function canGetExtension(Writer $writer) {
		if(isset($this->essays[$writer->getId()])) {
			$essay = $this->essays[$writer->getId()];

			return $essay->getWritingAuthorized() === null && $essay->getCorrectionFinalized() === null;
		}
		return true;
	}

	private function getExtensionAction(Writer $writer){
		$this->ctrl->setParameter($this->parent,"writer_id", $writer->getId());
		return $this->ctrl->getFormAction($this->parent, "editExtension");
	}

	private function canGetRepealed(Writer $writer){
		if(isset($this->essays[$writer->getId()])) {
			$essay = $this->essays[$writer->getId()];

			return $essay->getWritingExcluded() !== null;
		}
		return false;
	}

	private function getRepealExclusionAction(Writer $writer){
		$this->ctrl->setParameter($this->parent,"writer_id", $writer->getId());
		return $this->ctrl->getFormAction($this->parent, "repealExclusion");
	}

	private function getExclusionAction(Writer $writer){
		$this->ctrl->setParameter($this->parent,"writer_id", $writer->getId());
		return $this->ctrl->getFormAction($this->parent, "excludeWriter");
	}

	private function getRemoveAction(Writer $writer){
		$this->ctrl->setParameter($this->parent,"writer_id", $writer->getId());
		return $this->ctrl->getFormAction($this->parent, "removeWriter");
	}

	/**
	 * @param Writer $writer
	 * @return void
	 */
	private function extensionString(Writer $writer): string
	{
		if(isset($this->extensions[$writer->getId()])){
			return $this->extensions[$writer->getId()]->getMinutes() . " " . $this->plugin->txt("min");
		}
		return $this->plugin->txt("writing_none_extension");
	}

	private function essayStatus(Writer $writer): string
	{
		if(isset($this->essays[$writer->getId()])){
			$essay = $this->essays[$writer->getId()];

			if($essay->getWritingExcluded() !== null)
			{
				return $this->plugin->txt("writing_excluded_from") . " " .
					$this->getUsername($essay->getWritingExcludedBy(), true);
			}

			if($essay->getWritingAuthorized() !== null){
				$name = $this->plugin->txt("participant");
				if($essay->getWritingAuthorizedBy() != $writer->getUserId()){
					$name = $this->getUsername($essay->getWritingAuthorizedBy(), true);
				}

				return $this->plugin->txt("writing_authorized_from") . " " . $name;
			}

			if($essay->getEditStarted() !== null){
				return $this->plugin->txt("writing_edit_started");
			}
		}

		return $this->plugin->txt("writing_not_started");
	}

	private function lastSave(Writer $writer): string
	{

		if(isset($this->essays[$writer->getId()])){
			$essay = $this->essays[$writer->getId()];
            if (!empty($essay->getEditEnded())) {
                return \ilDatePresentation::formatDate(
                    new \ilDateTime($essay->getEditEnded(), IL_CAL_DATETIME));
            }
		}
        return '';
	}


	/**
	 * @return TimeExtension[]
	 */
	public function getExtensions(): array
	{
		return $this->extensions;
	}

	/**
	 * @param TimeExtension[] $extensions
	 */
	public function setExtensions(array $extensions): void
	{
		foreach($extensions as $extension) {
			$this->extensions[$extension->getWriterId()] = $extension;
		}
	}

	public function isFiltered(Writer $writer):bool
	{
		$filter = $this->getFilter();
		$essay = null;
		$extension = null;

		if(array_key_exists($writer->getId(), $this->essays)){
			$essay = $this->essays[$writer->getId()];
		}

		if(array_key_exists($writer->getId(), $this->extensions)){
			$extension = $this->extensions[$writer->getId()];
		}

		switch($filter){
			case "attended":
				return $essay !== null && $essay->getEditStarted() !== null;
			case "not_attended":
				return $essay === null || $essay->getEditStarted() === null;
			case "with_extension":
				return $extension !== null;
			case "all":
			default:
				return true;
		}
	}

	public function getFilter():string
	{
		global $DIC;
		$query = $DIC->http()->request()->getQueryParams();
		if(array_key_exists("filter", $query) && in_array($query["filter"], ["attended", "not_attended", "with_extension", "all"])){
			return $query["filter"];
		}
		return "all";
	}

	public function filterControl()
	{
		$link = $this->ctrl->getLinkTarget($this->parent, $this->parent_cmd);
		$filter = [
			"all",
			"attended",
			"not_attended",
			"with_extension"
		];

		$actions  = [];
		foreach ($filter as $key){
			$actions[$this->plugin->txt("filter_writer_admin_list_" . $key)] = $link . "&filter=" . $key;
		}

		$aria_label = "change_the_currently_displayed_mode";
		return $this->uiFactory->viewControl()->mode($actions, $aria_label)->withActive($this->plugin->txt("filter_writer_admin_list_" . $this->getFilter()));
	}
}
